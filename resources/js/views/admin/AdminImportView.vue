<template>
  <AppLayout>
    <div class="d-flex align-items-center justify-content-between mb-4">
      <h4 class="fw-bold mb-0">Import Data</h4>
    </div>

    <!-- Bulk Payments — worklist out, preview, then post -->
    <BulkPaymentImport />

    <BulkLoanImport />

    <!-- Import Shares (Permanent) / Premium Contributions per quincena (COS-Enrolled) -->
    <AppCard v-if="kinds.length">
      <template #header>
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
          <h5 class="mb-0 fw-semibold">
            <i :class="cfg.icon" class="me-2 text-primary"></i>{{ cfg.title }}
          </h5>
          <div class="d-flex gap-2 align-items-center">
            <!-- "All Members" context offers both imports -->
            <div v-if="kinds.length > 1" class="btn-group btn-group-sm" role="group" aria-label="Import type">
              <button
                v-for="k in kinds" :key="k" type="button"
                class="btn" :class="kind === k ? 'btn-primary' : 'btn-outline-primary'"
                @click="switchKind(k)"
              >{{ KINDS[k].short }}</button>
            </div>
            <AppButton variant="outline-primary" size="sm" @click="downloadTemplate" :loading="downloadingBenefits">
              <i class="bi bi-download me-1"></i>Download Template
            </AppButton>
          </div>
        </div>
      </template>

      <p class="text-muted small mb-3">
        <template v-if="kind === 'shares'">
          Upload a CSV with one row per Permanent member per month. Required columns: employee_id, year, month, members_contribution.
          Optional: philrice_counterpart. The total_equity column is just a guide (members contribution + counterpart) — the system computes it, so it's ignored on import. Shows on each member's Shares page; re-importing a month replaces it.
        </template>
        <template v-else>
          Upload a CSV with one row per COS-Enrolled member per <strong>quincena</strong>. Required columns: employee_id, year, month, quincena, premium_contribution.
          Quincena is <strong>1</strong> (1st–15th) or <strong>2</strong> (16th–end of month). The month's premium is the sum of both quincenas, so each cutoff can be imported on its own; re-importing a quincena replaces it.
        </template>
      </p>

      <!-- Dropzone -->
      <div
        class="upload-dropzone mb-3"
        :class="{ 'dropzone-active': benefitsDragOver, 'dropzone-has-file': benefitsFile }"
        @dragover.prevent="benefitsDragOver = true"
        @dragleave.prevent="benefitsDragOver = false"
        @drop.prevent="handleDrop"
        @click="$refs.benefitsFileInput.click()"
      >
        <input
          ref="benefitsFileInput"
          type="file"
          accept=".csv"
          class="d-none"
          @change="handleFileSelect"
        />
        <div v-if="!benefitsFile" class="text-center">
          <i class="bi bi-cloud-arrow-up fs-1 text-muted"></i>
          <p class="mb-0 mt-2 text-muted">Drag and drop your CSV file here, or click to browse</p>
          <p class="mb-0 small text-muted">Accepted format: .csv (max 10MB)</p>
        </div>
        <div v-else class="text-center">
          <i class="bi bi-file-earmark-check fs-1 text-success"></i>
          <p class="mb-0 mt-2 fw-semibold">{{ benefitsFile.name }}</p>
          <p class="mb-0 small text-muted">{{ formatFileSize(benefitsFile.size) }}</p>
          <a href="#" class="small text-danger" @click.stop="benefitsFile = null">Remove</a>
        </div>
      </div>

      <!-- Progress -->
      <div v-if="benefitsUploading" class="mb-3">
        <div class="progress" style="height: 6px;">
          <div
            class="progress-bar progress-bar-striped progress-bar-animated"
            role="progressbar"
            :style="{ width: benefitsProgress + '%' }"
          ></div>
        </div>
        <p class="small text-muted mt-1 mb-0">Processing file...</p>
      </div>

      <AppButton
        variant="primary"
        :loading="benefitsUploading"
        :disabled="!benefitsFile || benefitsUploading"
        @click="importFile"
      >
        <i class="bi bi-upload me-1"></i>{{ cfg.title }}
      </AppButton>

      <!-- Results -->
      <div v-if="benefitsResult" class="mt-4">
        <div class="d-flex gap-3 mb-3">
          <div class="result-badge result-success">
            <i class="bi bi-check-circle me-1"></i>{{ benefitsResult.imported }} imported
          </div>
          <div v-if="benefitsResult.failed > 0" class="result-badge result-error">
            <i class="bi bi-exclamation-circle me-1"></i>{{ benefitsResult.failed }} failed
          </div>
        </div>

        <div v-if="benefitsResult.errors && benefitsResult.errors.length > 0">
          <h6 class="fw-semibold text-danger mb-2">Error Details</h6>
          <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0">
              <thead class="table-light">
                <tr>
                  <th style="width: 70px;">Row</th>
                  <th style="width: 140px;">Employee ID</th>
                  <th>Error</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(err, i) in benefitsResult.errors" :key="i">
                  <td>{{ err.row }}</td>
                  <td><code>{{ err.employee_id }}</code></td>
                  <td class="text-danger small">{{ err.error }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </AppCard>
  </AppLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import admin from '@/services/admin'
import { useAdminContextStore } from '@/stores/adminContext'
import AppLayout from '@/components/layout/AppLayout.vue'
import AppCard from '@/components/ui/AppCard.vue'
import AppButton from '@/components/ui/AppButton.vue'
import BulkPaymentImport from '@/components/admin/BulkPaymentImport.vue'
import BulkLoanImport from '@/components/admin/BulkLoanImport.vue'

const adminContext = useAdminContextStore()

// Which contribution import the current member-type context offers.
const KINDS = {
  shares: {
    short: 'Shares',
    title: 'Import Shares',
    icon: 'bi bi-pie-chart',
    upload: (fd) => admin.importShares(fd),
    template: () => admin.downloadShareTemplate(),
    filename: 'share_import_template.csv',
  },
  premiums: {
    short: 'COS Premiums',
    title: 'Import Premium Contributions',
    icon: 'bi bi-shield-check',
    upload: (fd) => admin.importPremiums(fd),
    template: () => admin.downloadPremiumTemplate(),
    filename: 'premium_quincena_import_template.csv',
  },
}

const kinds = computed(() => {
  switch (adminContext.memberType || 'all') {
    case 'all': return ['shares', 'premiums']
    case 'Permanent': return ['shares']
    case 'Contract of Service': return ['premiums']
    default: return [] // COS-Non Enrolled / PMBF Employee have neither
  }
})
const kind = ref(kinds.value[0] ?? 'shares')
const cfg = computed(() => KINDS[kind.value])

function switchKind(k) {
  kind.value = k
  benefitsFile.value = null
  benefitsResult.value = null
}

watch(kinds, (list) => { if (!list.includes(kind.value) && list.length) switchKind(list[0]) })

// Upload state — loans and payments each live in their own component now.
const benefitsFile = ref(null)
const benefitsDragOver = ref(false)
const benefitsUploading = ref(false)
const benefitsProgress = ref(0)
const benefitsResult = ref(null)
const downloadingBenefits = ref(false)

function handleDrop(event) {
  benefitsDragOver.value = false
  const file = event.dataTransfer.files[0]
  if (file && isValidFile(file)) benefitsFile.value = file
}

function handleFileSelect(event) {
  const file = event.target.files[0]
  if (file && isValidFile(file)) benefitsFile.value = file
  event.target.value = ''
}

function isValidFile(file) {
  const validTypes = [
    'text/csv',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'text/plain',
  ]
  const validExtensions = ['.csv']
  const hasValidExtension = validExtensions.some((ext) => file.name.toLowerCase().endsWith(ext))
  const hasValidType = validTypes.includes(file.type) || file.type === ''
  return (hasValidType || hasValidExtension) && file.size <= 10 * 1024 * 1024
}

function formatFileSize(bytes) {
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
}

async function importFile() {
  if (!benefitsFile.value) return

  const formData = new FormData()
  formData.append('file', benefitsFile.value)

  benefitsUploading.value = true
  benefitsProgress.value = 30
  benefitsResult.value = null

  try {
    benefitsProgress.value = 60
    const response = await cfg.value.upload(formData)
    benefitsProgress.value = 100
    benefitsResult.value = response.data.data
    benefitsFile.value = null
  } catch (error) {
    const message = error.response?.data?.message || 'Import failed. Please check your file and try again.'
    benefitsResult.value = { imported: 0, failed: 0, errors: [{ row: '-', employee_id: '-', error: message }] }
  } finally {
    benefitsUploading.value = false
  }
}

async function downloadTemplate() {
  downloadingBenefits.value = true
  try {
    const response = await cfg.value.template()
    triggerDownload(response.data, cfg.value.filename)
  } catch (error) {
    console.error('Template download failed:', error)
  } finally {
    downloadingBenefits.value = false
  }
}

function triggerDownload(blob, filename) {
  const url = window.URL.createObjectURL(new Blob([blob]))
  const link = document.createElement('a')
  link.href = url
  link.setAttribute('download', filename)
  document.body.appendChild(link)
  link.click()
  link.remove()
  window.URL.revokeObjectURL(url)
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

.result-error {
  background: #fee2e2;
  color: #991b1b;
}
</style>
