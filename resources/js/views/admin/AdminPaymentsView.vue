<template>
  <AppLayout>
    <div class="d-flex align-items-center justify-content-between mb-4">
      <h4 class="fw-bold mb-0">Cash Payments</h4>
      <div class="d-flex gap-2">
        <AppButton variant="outline-success" @click="showImportModal = true">
          <i class="bi bi-upload me-1"></i>Import CSV
        </AppButton>
        <AppButton variant="primary" @click="openModal">
          <i class="bi bi-plus-lg me-1"></i>Record Payment
        </AppButton>
      </div>
    </div>

    <!-- Filters -->
    <div class="card mb-3">
      <div class="card-body py-2">
        <div class="row g-2 align-items-end">
          <!-- Quick date presets -->
          <div class="col-auto">
            <label class="form-label small fw-medium mb-1">Quick Range</label>
            <div class="btn-group btn-group-sm">
              <button
                v-for="p in datePresets" :key="p.label"
                class="btn"
                :class="activePreset === p.label ? 'btn-primary' : 'btn-outline-secondary'"
                @click="applyPreset(p)"
              >{{ p.label }}</button>
            </div>
          </div>
          <!-- From date -->
          <div class="col-md-2">
            <label class="form-label small fw-medium mb-1">From</label>
            <input v-model="filterFrom" type="date" class="form-control form-control-sm" @change="onDateChange" />
          </div>
          <!-- To date -->
          <div class="col-md-2">
            <label class="form-label small fw-medium mb-1">To</label>
            <input v-model="filterTo" type="date" class="form-control form-control-sm" @change="onDateChange" />
          </div>
          <!-- Method -->
          <div class="col-md-2">
            <label class="form-label small fw-medium mb-1">Method</label>
            <select v-model="filterMethod" class="form-select form-select-sm" @change="applyFilters">
              <option value="">All Methods</option>
              <option value="cash">Cash</option>
              <option value="payroll_deduction">Payroll Deduction</option>
              <option value="bank_transfer">Bank Transfer</option>
            </select>
          </div>
          <!-- Search -->
          <div class="col">
            <label class="form-label small fw-medium mb-1">Search Member / OR#</label>
            <div class="input-group input-group-sm">
              <input v-model="filterSearch" type="text" class="form-control" placeholder="Name, ID, or OR#..." @keyup.enter="applyFilters" />
              <button class="btn btn-primary btn-sm" @click="applyFilters"><i class="bi bi-search"></i></button>
              <button class="btn btn-outline-secondary btn-sm" title="Clear filters" @click="clearFilters"><i class="bi bi-x-lg"></i></button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Payments Table -->
    <AppCard :padding="false">
      <AppTable :columns="columns" :items="items" :loading="loading" empty-text="No payments recorded yet.">
        <template #cell(index)="{ item }">
          {{ items.indexOf(item) + 1 + (meta.currentPage - 1) * meta.perPage }}
        </template>
        <template #cell(member)="{ item }">
          <div class="fw-semibold">{{ item.member?.full_name ?? item.member_name ?? '-' }}</div>
          <small class="text-muted">{{ item.member?.employee_id ?? '' }}</small>
        </template>
        <template #cell(loan)="{ item }">
          <span class="small">{{ formatLoanType(item.loan?.loan_type ?? item.loan_type) }}</span>
        </template>
        <template #cell(amount)="{ value }">
          <span class="fw-medium">&#8369;{{ Number(value ?? 0).toLocaleString() }}</span>
        </template>
        <template #cell(payment_method)="{ value }">
          <span class="badge bg-light text-dark">{{ formatMethod(value) }}</span>
        </template>
        <template #cell(payment_date)="{ value }">
          {{ formatDate(value) }}
        </template>
        <template #cell(recorded_by)="{ item }">
          {{ item.recorder?.full_name ?? '-' }}
        </template>
      </AppTable>

      <template #footer>
        <AppPagination
          :meta="{ current_page: meta.currentPage, last_page: meta.lastPage, total: meta.total, per_page: meta.perPage }"
          @page-change="fetch"
        />
      </template>
    </AppCard>

    <!-- Record Payment Modal -->
    <AppModal :show="showModal" title="Record Payment" size="lg" @close="closeModal">
      <div class="row">
        <div class="col-12">
          <AppSearchSelect
            v-model="paymentForm.form.loan_id"
            label="Loan *"
            :options="loanOptions"
            placeholder="Search by name, ID, or loan #..."
            :error="paymentForm.errors.loan_id"
          />
        </div>
        <div class="col-md-6">
          <AppInput
            v-model="paymentForm.form.amount"
            type="number"
            label="Amount"
            required
            placeholder="0.00"
            :error="paymentForm.errors.amount"
          />
        </div>
        <div class="col-md-6">
          <AppInput
            v-model="paymentForm.form.or_number"
            label="OR Number"
            placeholder="Official Receipt #"
            :error="paymentForm.errors.or_number"
          />
        </div>
        <div class="col-md-6">
          <AppInput
            v-model="paymentForm.form.payment_method"
            type="select"
            label="Payment Method"
            required
            :options="methodOptions"
            :error="paymentForm.errors.payment_method"
          />
        </div>
        <div class="col-md-6">
          <AppInput
            v-model="paymentForm.form.payment_date"
            type="date"
            label="Payment Date"
            required
            :error="paymentForm.errors.payment_date"
          />
        </div>
        <!-- Receipt image (required for cash) -->
        <div v-if="paymentForm.form.payment_method === 'cash'" class="col-12">
          <label class="form-label fw-semibold">Receipt Image <span class="text-danger">*</span></label>
          <input
            ref="receiptInput"
            type="file"
            class="form-control"
            accept="image/*"
            @change="onReceiptChange"
          />
          <div v-if="receiptPreview" class="mt-2">
            <img :src="receiptPreview" alt="Receipt preview" class="img-thumbnail" style="max-height: 200px;" />
          </div>
          <div class="form-text text-muted">Attach a photo of the official receipt (max 5MB)</div>
        </div>
        <div class="col-12">
          <AppInput
            v-model="paymentForm.form.remarks"
            type="textarea"
            label="Remarks"
            placeholder="Optional remarks..."
            :error="paymentForm.errors.remarks"
          />
        </div>
      </div>

      <template #footer>
        <AppButton variant="secondary" @click="closeModal">Cancel</AppButton>
        <AppButton variant="primary" :loading="paymentForm.processing.value" @click="handleSubmit">
          <i class="bi bi-check-lg me-1"></i>Record Payment
        </AppButton>
      </template>
    </AppModal>

    <!-- Import Payments Modal -->
    <AppModal :show="showImportModal" title="Import Payments from CSV" @close="closeImportModal">
      <div class="mb-3">
        <p class="text-muted small">
          Upload a CSV file with payment records. The system will match employees by ID and find their released loans.
        </p>
        <a href="#" class="btn btn-sm btn-outline-primary mb-3" @click.prevent="downloadTemplate">
          <i class="bi bi-download me-1"></i>Download CSV Template
        </a>
      </div>

      <div class="mb-3">
        <label class="form-label fw-semibold">CSV File *</label>
        <input
          ref="importFileInput"
          type="file"
          class="form-control"
          accept=".csv,.txt,.xlsx,.xls"
          @change="onImportFileChange"
        />
      </div>

      <!-- Import Results -->
      <div v-if="importResult" class="mt-3">
        <div v-if="importResult.imported > 0" class="alert alert-success small">
          <i class="bi bi-check-circle me-1"></i>
          {{ importResult.imported }} payments imported successfully.
        </div>
        <div v-if="importResult.failed > 0" class="alert alert-danger small">
          <i class="bi bi-exclamation-triangle me-1"></i>
          {{ importResult.failed }} rows failed:
          <ul class="mb-0 mt-1">
            <li v-for="err in importResult.errors?.slice(0, 10)" :key="err.row">
              Row {{ err.row }} ({{ err.employee_id }}): {{ err.error }}
            </li>
            <li v-if="importResult.errors?.length > 10">...and {{ importResult.errors.length - 10 }} more</li>
          </ul>
        </div>
      </div>

      <template #footer>
        <AppButton variant="secondary" @click="closeImportModal">Close</AppButton>
        <AppButton variant="success" :loading="importing" :disabled="!importFile" @click="handleImport">
          <i class="bi bi-upload me-1"></i>Import
        </AppButton>
      </template>
    </AppModal>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import { usePagination } from '@/composables/usePagination'
import { useForm } from '@/composables/useForm'
import { useNotificationStore } from '@/stores/notification'
import { useAdminContextStore } from '@/stores/adminContext'
import admin from '@/services/admin'
import AppLayout from '@/components/layout/AppLayout.vue'
import AppCard from '@/components/ui/AppCard.vue'
import AppTable from '@/components/ui/AppTable.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppInput from '@/components/ui/AppInput.vue'
import AppModal from '@/components/ui/AppModal.vue'
import AppPagination from '@/components/ui/AppPagination.vue'
import AppSearchSelect from '@/components/ui/AppSearchSelect.vue'

const notification = useNotificationStore()
const adminContext = useAdminContextStore()
const { items, meta, loading, filters, fetch } = usePagination(admin.getPayments)

// ─── Filters ──────────────────────────────────────────────
const filterFrom = ref('')
const filterTo = ref('')
const filterMethod = ref('')
const filterSearch = ref('')
const activePreset = ref('This Month')

// Format a Date as YYYY-MM-DD in LOCAL timezone (avoids UTC offset shifting the date)
function localDate(d) {
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

const datePresets = [
  {
    label: 'This Month',
    from: () => localDate(new Date(new Date().getFullYear(), new Date().getMonth(), 1)),
    to: () => localDate(new Date()),
  },
  {
    label: 'Last 60 Days',
    from: () => localDate(new Date(new Date().setDate(new Date().getDate() - 60))),
    to: () => localDate(new Date()),
  },
  {
    label: 'Last Month',
    from: () => { const d = new Date(); d.setDate(1); d.setMonth(d.getMonth() - 1); return localDate(d) },
    to: () => { const d = new Date(); d.setDate(0); return localDate(d) },
  },
  { label: 'All', from: () => '', to: () => '' },
]

function applyPreset(preset) {
  activePreset.value = preset.label
  filterFrom.value = preset.from()
  filterTo.value = preset.to()
  applyFilters()
}

function onDateChange() {
  activePreset.value = ''
  applyFilters()
}

function applyFilters() {
  filters.employment_type = (adminContext.memberType && adminContext.memberType !== 'all')
    ? adminContext.memberType : undefined
  filters.from           = filterFrom.value || undefined
  filters.to             = filterTo.value || undefined
  filters.payment_method = filterMethod.value || undefined
  const s = filterSearch.value.trim()
  filters.search    = s || undefined
  filters.or_number = undefined
  fetch(1)
}

function clearFilters() {
  filterFrom.value   = ''
  filterTo.value     = ''
  filterMethod.value = ''
  filterSearch.value = ''
  activePreset.value = ''
  applyFilters()
}

function applyContextFilter() {
  applyFilters()
}

watch(() => adminContext.memberType, () => applyContextFilter())

const showModal = ref(false)
const releasedLoans = ref([])
const loanOptions = ref([])

const paymentForm = useForm({
  loan_id: '',
  amount: '',
  or_number: '',
  payment_method: '',
  payment_date: '',
  remarks: '',
})

const methodOptions = [
  { value: 'cash', label: 'Cash' },
  { value: 'payroll_deduction', label: 'Payroll Deduction' },
  { value: 'bank_transfer', label: 'Bank Transfer' },
]

const columns = [
  { key: 'index', label: '#', class: 'text-center' },
  { key: 'member', label: 'Member' },
  { key: 'loan', label: 'Loan' },
  { key: 'amount', label: 'Amount' },
  { key: 'or_number', label: 'OR#' },
  { key: 'payment_method', label: 'Method' },
  { key: 'payment_date', label: 'Date' },
  { key: 'recorded_by', label: 'Recorded By' },
]

function formatLoanType(type) {
  if (!type) return '-'
  return type.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
}

function formatMethod(method) {
  if (!method) return '-'
  return method.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
}

function formatDate(dateStr) {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleDateString('en-PH', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })
}

async function fetchReleasedLoans() {
  try {
    const response = await admin.getLoans({ status: 'released', per_page: 200 })
    const data = response.data.data ?? response.data
    releasedLoans.value = Array.isArray(data) ? data : data.data ?? []

    // Sort by last_name, first_name
    const sorted = [...releasedLoans.value].sort((a, b) => {
      const nameA = `${a.user?.last_name ?? ''} ${a.user?.first_name ?? ''}`.toLowerCase()
      const nameB = `${b.user?.last_name ?? ''} ${b.user?.first_name ?? ''}`.toLowerCase()
      return nameA.localeCompare(nameB)
    })

    loanOptions.value = sorted.map((loan) => {
      const u = loan.user ?? {}
      const name = [u.last_name, u.first_name, u.middle_name].filter(Boolean).join(', ')
      const loanDate = loan.applied_at || loan.created_at
      const dateStr = loanDate ? new Date(loanDate).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' }) : ''
      return {
        value: loan.id,
        label: `#${loan.id} ${name || 'Unknown'} — ${formatLoanType(loan.loan_type)} (₱${Number(loan.amount ?? 0).toLocaleString()})`,
        sublabel: `${u.employee_id ?? ''} • ${dateStr}`,
      }
    })
  } catch {
    // Silently fail; options remain empty
  }
}

const receiptFile = ref(null)
const receiptPreview = ref(null)
const receiptInput = ref(null)

function onReceiptChange(e) {
  const file = e.target.files[0]
  receiptFile.value = file ?? null
  if (file) {
    receiptPreview.value = URL.createObjectURL(file)
  } else {
    receiptPreview.value = null
  }
}

function openModal() {
  paymentForm.reset()
  receiptFile.value = null
  receiptPreview.value = null
  fetchReleasedLoans()
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  paymentForm.reset()
  receiptFile.value = null
  receiptPreview.value = null
}

async function handleSubmit() {
  // Validate receipt for cash
  if (paymentForm.form.payment_method === 'cash' && !receiptFile.value) {
    notification.error('Please attach a receipt image for cash payments.')
    return
  }

  try {
    await paymentForm.submit(async (data) => {
      const formData = new FormData()
      Object.entries(data).forEach(([key, val]) => {
        if (val !== '' && val !== null && val !== undefined) formData.append(key, val)
      })
      if (receiptFile.value) {
        formData.append('receipt', receiptFile.value)
      }
      await admin.storePayment(formData)
    })
    notification.success('Payment recorded successfully.')
    closeModal()
    fetch(meta.currentPage)
  } catch {
    notification.error('Failed to record payment.')
  }
}

// ─── Import ──────────────────────────────────────────────
const showImportModal = ref(false)
const importFile = ref(null)
const importFileInput = ref(null)
const importing = ref(false)
const importResult = ref(null)

function onImportFileChange(e) {
  importFile.value = e.target.files[0] ?? null
  importResult.value = null
}

function closeImportModal() {
  showImportModal.value = false
  importFile.value = null
  importResult.value = null
  if (importFileInput.value) importFileInput.value.value = ''
}

async function downloadTemplate() {
  try {
    const response = await admin.downloadPaymentTemplate()
    const url = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', 'payment_import_template.csv')
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)
  } catch {
    notification.error('Failed to download template.')
  }
}

async function handleImport() {
  if (!importFile.value) return
  importing.value = true
  importResult.value = null
  try {
    const formData = new FormData()
    formData.append('file', importFile.value)
    const { data } = await admin.importPayments(formData)
    importResult.value = data.data ?? data
    if (importResult.value.imported > 0) {
      fetch(1) // refresh table
    }
  } catch (e) {
    const msg = e.response?.data?.message || 'Import failed.'
    notification.error(msg)
    importResult.value = null
  } finally {
    importing.value = false
  }
}

onMounted(() => {
  // default: This Month
  applyPreset(datePresets[0])
  applyContextFilter()
})
</script>
