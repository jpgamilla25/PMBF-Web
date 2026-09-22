<template>
  <component :is="embedded ? 'div' : AppCard" :class="embedded ? '' : 'mb-4'">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
      <h5 v-if="!embedded" class="mb-0 fw-semibold">
        <i class="bi bi-cash-coin me-2 text-primary"></i>Import Existing Loans
      </h5>
      <span v-else></span>
      <AppButton variant="outline-primary" size="sm" :loading="downloading" @click="downloadTemplate">
        <i class="bi bi-download me-1"></i>Download Template
      </AppButton>
    </div>

    <p class="text-muted small mb-3">
      For loans that pre-date the system. Fill in the template — employee ID, loan type, principal, term,
      monthly amortization and the original application date — then upload it. For a loan that is part-paid,
      add <code>amount_paid</code> or <code>months_paid</code>: it is recorded as a real payment, so it shows
      on the Payments page and the balance is right from day one. You will see every row before anything is
      created, and the same loan can never be imported twice.
    </p>

    <!-- Step 1: upload -->
    <div
      class="upload-dropzone mb-3"
      :class="{ 'dropzone-active': dragOver, 'dropzone-has-file': file }"
      @dragover.prevent="dragOver = true"
      @dragleave.prevent="dragOver = false"
      @drop.prevent="onDrop"
      @click="$refs.fileInput.click()"
    >
      <input ref="fileInput" type="file" accept=".csv,.xlsx,.xls" class="d-none" @change="onSelect" />
      <div v-if="!file" class="text-center">
        <i class="bi bi-cloud-arrow-up fs-1 text-muted"></i>
        <p class="mb-0 mt-2 text-muted">Drag the filled-in template here, or click to browse</p>
        <p class="mb-0 small text-muted">Accepted formats: .xlsx, .xls, .csv (max 10MB)</p>
      </div>
      <div v-else class="text-center">
        <i class="bi bi-file-earmark-check fs-1 text-success"></i>
        <p class="mb-0 mt-2 fw-semibold">{{ file.name }}</p>
        <p class="mb-0 small text-muted">{{ formatFileSize(file.size) }}</p>
        <a href="#" class="small text-danger" @click.stop="reset">Remove</a>
      </div>
    </div>

    <div class="d-flex gap-2 flex-wrap">
      <AppButton variant="primary" :loading="checking" :disabled="!file || checking" @click="runPreview">
        <i class="bi bi-search me-1"></i>Check File
      </AppButton>
      <AppButton
        v-if="preview"
        variant="success"
        :loading="posting"
        :disabled="importableCount === 0 || posting || (preview.already_imported && !forceReimport)"
        @click="commit"
      >
        <i class="bi bi-check2-circle me-1"></i>Proceed — import {{ importableCount }} loan{{ importableCount === 1 ? '' : 's' }}
      </AppButton>
    </div>

    <div v-if="errorMessage" class="alert alert-danger mt-3 mb-0 py-2 small">
      <i class="bi bi-exclamation-triangle me-1"></i>{{ errorMessage }}
    </div>

    <div v-if="committed" class="alert alert-success mt-3 mb-0 py-2 small">
      <i class="bi bi-check-circle me-1"></i>{{ committed }}
    </div>

    <!-- Step 2: preview -->
    <div v-if="preview" class="mt-4">
      <div v-if="preview.already_imported" class="alert alert-warning py-2 small">
        <p class="fw-semibold mb-1">
          <i class="bi bi-exclamation-triangle me-1"></i>This exact file was already imported.
        </p>
        <p class="mb-2">
          Batch #{{ preview.already_imported.batch_id }} on {{ preview.already_imported.imported_at }}
          <span v-if="preview.already_imported.uploaded_by">by {{ preview.already_imported.uploaded_by }}</span>
          — {{ preview.already_imported.rows_imported }} loans. Rows below are still checked individually,
          so anything already on file shows as a duplicate.
        </p>
        <div class="form-check mb-0">
          <input id="force-loan-reimport" v-model="forceReimport" class="form-check-input" type="checkbox" />
          <label for="force-loan-reimport" class="form-check-label">
            I understand — import the rows that are not duplicates anyway
          </label>
        </div>
      </div>

      <div class="d-flex gap-2 flex-wrap mb-3">
        <div class="result-badge result-success">{{ preview.summary.ok }} ready</div>
        <div v-if="preview.summary.warning" class="result-badge result-warning">
          {{ preview.summary.warning }} with warnings
        </div>
        <div v-if="preview.summary.duplicate" class="result-badge result-dupe">
          {{ preview.summary.duplicate }} duplicates (skipped)
        </div>
        <div v-if="preview.summary.error" class="result-badge result-error">
          {{ preview.summary.error }} errors
        </div>
        <div class="result-badge result-total">₱{{ formatAmount(preview.summary.amount) }} principal</div>
        <div v-if="preview.summary.paid > 0" class="result-badge result-paid">
          ₱{{ formatAmount(preview.summary.paid) }} already paid
        </div>
      </div>

      <div class="d-flex align-items-center justify-content-between mb-2">
        <h6 class="fw-semibold mb-0">Rows</h6>
        <div class="form-check form-switch">
          <input id="loan-problems-only" v-model="problemsOnly" class="form-check-input" type="checkbox" />
          <label for="loan-problems-only" class="form-check-label small text-muted">Problems only</label>
        </div>
      </div>

      <div class="table-responsive preview-table">
        <table class="table table-sm table-bordered align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width: 60px">Row</th>
              <th style="width: 110px">Status</th>
              <th>Member</th>
              <th>Loan</th>
              <th class="text-end" style="width: 110px">Principal</th>
              <th class="text-end" style="width: 110px">Monthly</th>
              <th class="text-end" style="width: 120px">Total payable</th>
              <th class="text-end" style="width: 120px">Already paid</th>
              <th class="text-end" style="width: 110px">Balance</th>
              <th>Notes</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in visibleRows" :key="row.row" :class="rowClass(row.status_verdict)">
              <td>{{ row.row }}</td>
              <td>
                <span class="badge" :class="badgeClass(row.status_verdict)">
                  {{ statusLabel(row.status_verdict) }}
                </span>
              </td>
              <td>
                <div class="fw-semibold small">{{ row.member_name || '—' }}</div>
                <code class="small">{{ row.employee_id || '—' }}</code>
              </td>
              <td class="small">
                <div>{{ row.loan_type || '—' }}</div>
                <div class="text-muted">
                  {{ row.term_months }} mo · {{ row.interest_rate }}% {{ row.interest_method }} ·
                  {{ row.status }}
                </div>
                <div class="text-muted">applied {{ row.applied_at || '—' }}</div>
              </td>
              <td class="text-end">{{ formatAmount(row.amount) }}</td>
              <td class="text-end">{{ formatAmount(row.monthly_amortization) }}</td>
              <td class="text-end">{{ formatAmount(row.total_payable) }}</td>
              <td class="text-end">
                <span :class="row.amount_paid > 0 ? 'text-success fw-semibold' : 'text-muted'">
                  {{ formatAmount(row.amount_paid) }}
                </span>
                <div v-if="row.months_paid > 0" class="text-muted small">{{ row.months_paid }} mo</div>
              </td>
              <td class="text-end">{{ formatAmount(row.balance) }}</td>
              <td class="small" :class="row.status_verdict === 'error' ? 'text-danger' : 'text-muted'">
                {{ row.message }}
              </td>
            </tr>
            <tr v-if="visibleRows.length === 0">
              <td colspan="10" class="text-center text-muted small py-3">
                No rows to show. Completely blank rows are skipped.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Step 3: history -->
    <div class="mt-4">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <h6 class="fw-semibold mb-0">Import History</h6>
        <AppButton variant="outline-secondary" size="sm" :loading="loadingBatches" @click="loadBatches">
          <i class="bi bi-arrow-clockwise me-1"></i>Refresh
        </AppButton>
      </div>

      <div class="table-responsive">
        <table class="table table-sm table-bordered align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width: 70px">Batch</th>
              <th>File</th>
              <th style="width: 160px">Imported</th>
              <th class="text-end" style="width: 80px">Loans</th>
              <th class="text-end" style="width: 120px">Principal</th>
              <th style="width: 130px">Status</th>
              <th style="width: 110px"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="batch in batches" :key="batch.id">
              <td>#{{ batch.id }}</td>
              <td class="small text-truncate" style="max-width: 240px">{{ batch.file_name }}</td>
              <td class="small">
                {{ formatDate(batch.created_at) }}
                <div v-if="batch.uploader" class="text-muted">
                  {{ batch.uploader.first_name }} {{ batch.uploader.last_name }}
                </div>
              </td>
              <td class="text-end">{{ batch.rows_imported }}</td>
              <td class="text-end">{{ formatAmount(batch.amount_total) }}</td>
              <td>
                <span v-if="batch.status === 'rolled_back'" class="badge bg-secondary">Rolled back</span>
                <span v-else class="badge bg-success">Imported</span>
              </td>
              <td class="text-end">
                <AppButton
                  v-if="batch.status !== 'rolled_back'"
                  variant="outline-danger"
                  size="sm"
                  @click="rollback(batch)"
                >
                  Undo
                </AppButton>
              </td>
            </tr>
            <tr v-if="batches.length === 0">
              <td colspan="7" class="text-center text-muted small py-3">No loan imports yet.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </component>
</template>

<script setup>
/**
 * Bulk import of legacy loans.
 *
 * Same shape as BulkPaymentImport: "Check File" is a dry run and nothing is
 * created until Proceed. Duplicate protection is enforced by a UNIQUE
 * fingerprint in the database, so the preview is a courtesy, not the guard.
 */
import { computed, onMounted, ref } from 'vue'
import admin from '@/services/admin'
import { useConfirm } from '@/composables/useConfirm'
import AppCard from '@/components/ui/AppCard.vue'
import AppButton from '@/components/ui/AppButton.vue'

defineProps({
  embedded: { type: Boolean, default: false },
})

const emit = defineEmits(['imported'])

const { confirm } = useConfirm()

const file = ref(null)
const dragOver = ref(false)
const downloading = ref(false)
const checking = ref(false)
const posting = ref(false)
const preview = ref(null)
const errorMessage = ref('')
const committed = ref('')
const forceReimport = ref(false)
const problemsOnly = ref(false)
const batches = ref([])
const loadingBatches = ref(false)

const importableCount = computed(
  () => (preview.value?.summary.ok ?? 0) + (preview.value?.summary.warning ?? 0)
)

const visibleRows = computed(() => {
  const rows = preview.value?.rows ?? []
  return problemsOnly.value ? rows.filter((r) => r.status_verdict !== 'ok') : rows
})

onMounted(loadBatches)

function onDrop(event) {
  dragOver.value = false
  const dropped = event.dataTransfer.files[0]
  if (dropped && isValidFile(dropped)) setFile(dropped)
}

function onSelect(event) {
  const picked = event.target.files[0]
  if (picked && isValidFile(picked)) setFile(picked)
  event.target.value = ''
}

function setFile(picked) {
  file.value = picked
  // A new file invalidates the previous verdict.
  preview.value = null
  errorMessage.value = ''
  committed.value = ''
  forceReimport.value = false
}

function isValidFile(picked) {
  const ok = ['.csv', '.xlsx', '.xls'].some((ext) => picked.name.toLowerCase().endsWith(ext))
  return ok && picked.size <= 10 * 1024 * 1024
}

function reset() {
  file.value = null
  preview.value = null
  errorMessage.value = ''
  forceReimport.value = false
}

async function downloadTemplate() {
  downloading.value = true
  try {
    const response = await admin.downloadLoanTemplate()
    const url = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', `loan_import_template_${new Date().toISOString().slice(0, 10)}.xlsx`)
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)
  } catch {
    errorMessage.value = 'Could not download the template. Please try again.'
  } finally {
    downloading.value = false
  }
}

async function runPreview() {
  if (!file.value) return

  checking.value = true
  errorMessage.value = ''
  committed.value = ''

  const formData = new FormData()
  formData.append('file', file.value)

  try {
    const response = await admin.previewLoans(formData)
    preview.value = response.data.data
    problemsOnly.value = false
  } catch (error) {
    preview.value = null
    errorMessage.value = error.response?.data?.message || 'Could not read that file.'
  } finally {
    checking.value = false
  }
}

async function commit() {
  if (!preview.value || importableCount.value === 0) return

  const ok = await confirm(
    `Import ${importableCount.value} loans? Duplicates and error rows will be skipped.`,
    { title: 'Import loans', confirmLabel: 'Import loans', variant: 'success' }
  )

  if (!ok) return

  posting.value = true
  errorMessage.value = ''

  try {
    const response = await admin.commitLoans(preview.value.token, forceReimport.value)
    committed.value = response.data.message
    preview.value = null
    file.value = null
    forceReimport.value = false
    await loadBatches()
    emit('imported')
  } catch (error) {
    errorMessage.value = error.response?.data?.message || 'Import failed. Nothing was created.'
  } finally {
    posting.value = false
  }
}

async function loadBatches() {
  loadingBatches.value = true
  try {
    const response = await admin.getLoanImportBatches({ per_page: 10 })
    batches.value = response.data.data?.data ?? []
  } catch {
    batches.value = []
  } finally {
    loadingBatches.value = false
  }
}

async function rollback(batch) {
  const ok = await confirm(
    `Remove the ${batch.rows_imported} loans created by batch #${batch.id}? Loans that already have payments will be kept.`,
    { title: 'Undo import', confirmLabel: 'Undo import', variant: 'danger' }
  )

  if (!ok) return

  try {
    const response = await admin.rollbackLoanImport(batch.id)
    committed.value = response.data.message
    await loadBatches()
    emit('imported')
  } catch (error) {
    errorMessage.value = error.response?.data?.message || 'Could not undo that batch.'
  }
}

function statusLabel(status) {
  return { ok: 'Ready', warning: 'Warning', duplicate: 'Duplicate', error: 'Error' }[status] ?? status
}

function badgeClass(status) {
  return {
    ok: 'bg-success',
    warning: 'bg-warning text-dark',
    duplicate: 'bg-secondary',
    error: 'bg-danger',
  }[status] ?? 'bg-secondary'
}

function rowClass(status) {
  return { error: 'row-error', duplicate: 'row-dupe', warning: 'row-warning' }[status] ?? ''
}

function formatAmount(value) {
  return Number(value ?? 0).toLocaleString('en-PH', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })
}

function formatDate(value) {
  return value ? new Date(value).toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' }) : '—'
}

function formatFileSize(bytes) {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}
</script>

<style scoped>
.upload-dropzone {
  border: 2px dashed #d1d5db;
  border-radius: 12px;
  padding: 2rem;
  cursor: pointer;
  transition: all 0.2s ease;
  background: #fafafa;
}

.upload-dropzone:hover {
  border-color: #93c5fd;
  background: #eff6ff;
}

.dropzone-active {
  border-color: #3b82f6;
  background: #dbeafe;
}

.dropzone-has-file {
  border-color: #22c55e;
  border-style: solid;
  background: #f0fdf4;
}

.result-badge {
  display: inline-flex;
  align-items: center;
  padding: 0.4rem 0.75rem;
  border-radius: 8px;
  font-size: 0.875rem;
  font-weight: 600;
}

.result-success {
  background: #dcfce7;
  color: #166534;
}

.result-warning {
  background: #fef3c7;
  color: #92400e;
}

.result-dupe {
  background: #e5e7eb;
  color: #374151;
}

.result-error {
  background: #fee2e2;
  color: #991b1b;
}

.result-total {
  background: #dbeafe;
  color: #1e40af;
}

.result-paid {
  background: #dcfce7;
  color: #166534;
}

.preview-table {
  max-height: 460px;
  overflow-y: auto;
}

.row-error {
  background: #fef2f2;
}

.row-dupe {
  background: #f9fafb;
}

.row-warning {
  background: #fffbeb;
}
</style>
