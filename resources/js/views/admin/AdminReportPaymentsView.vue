<template>
  <AppLayout>
    <div class="d-flex align-items-center gap-2 mb-1">
      <button class="btn btn-sm btn-outline-secondary" @click="$router.push('/admin/reports')">
        <i class="bi bi-arrow-left"></i>
      </button>
      <h4 class="fw-bold mb-0">Payments Report</h4>
    </div>
    <p class="text-muted small mb-4 ms-5">Payment transactions with OR numbers, methods, and borrower details.</p>

    <!-- Filters -->
    <AppCard class="mb-4">
      <div class="row g-3 align-items-end">
        <div class="col-md-2">
          <label class="form-label fw-semibold small">From Date</label>
          <input v-model="filters.from" type="date" class="form-control form-control-sm" />
        </div>
        <div class="col-md-2">
          <label class="form-label fw-semibold small">To Date</label>
          <input v-model="filters.to" type="date" class="form-control form-control-sm" />
        </div>
        <div class="col-md-2">
          <label class="form-label fw-semibold small">Method</label>
          <select v-model="filters.payment_method" class="form-select form-select-sm">
            <option value="">All</option>
            <option value="cash">Cash</option>
            <option value="check">Check</option>
            <option value="salary_deduction">Salary Deduction</option>
            <option value="bank_transfer">Bank Transfer</option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label fw-semibold small">Employment Type</label>
          <div v-if="adminContext.memberType && adminContext.memberType !== 'all'" class="form-control form-control-sm bg-light text-muted d-flex align-items-center gap-1" style="cursor:not-allowed;">
            <i class="bi bi-lock-fill" style="font-size:0.7rem;"></i>
            {{ adminContext.memberType === 'SC' ? 'Service Contract' : adminContext.memberType }}
          </div>
          <select v-else v-model="filters.employment_type" class="form-select form-select-sm">
            <option value="">All</option>
            <option value="Permanent">Permanent</option>
            <option value="SC">Service Contract</option>
            <option value="Non-Member">Non-Member</option>
          </select>
        </div>
        <div class="col-md-4 d-flex gap-2 align-items-end">
          <AppButton variant="primary" size="sm" :loading="loading" class="flex-grow-1" @click="fetchReport">
            <i class="bi bi-search me-1"></i>Generate
          </AppButton>
          <AppButton variant="outline-secondary" size="sm" @click="resetFilters">
            <i class="bi bi-x"></i>
          </AppButton>
        </div>
      </div>
    </AppCard>

    <AppLoading :loading="loading" text="Generating report..." />

    <template v-if="!loading && data">
      <!-- Summary -->
      <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-4">
          <AppStatCard title="Total Payments" :value="summary.total_count" icon="bi bi-receipt" color="success" />
        </div>
        <div class="col-sm-6 col-lg-4">
          <AppStatCard title="Total Amount" :value="summary.total_amount" icon="bi bi-cash" color="success" prefix="₱" />
        </div>
        <div class="col-lg-4">
          <AppCard title="By Method" class="h-100">
            <div v-for="(info, method) in summary.by_method" :key="method" class="d-flex justify-content-between small py-1 border-bottom">
              <span>{{ formatMethod(method) }} <span class="text-muted">({{ info.count }})</span></span>
              <span class="fw-semibold">₱{{ fmt(info.amount) }}</span>
            </div>
          </AppCard>
        </div>
      </div>

      <!-- Export Buttons -->
      <div class="d-flex justify-content-end gap-2 mb-3">
        <AppButton variant="outline-danger" size="sm" @click="exportPdf">
          <i class="bi bi-filetype-pdf me-1"></i>Export PDF
        </AppButton>
        <AppButton variant="outline-success" size="sm" :loading="exportingCsv" @click="exportCsv">
          <i class="bi bi-filetype-csv me-1"></i>Export CSV
        </AppButton>
      </div>

      <!-- Table -->
      <AppCard :padding="false">
        <div class="table-responsive">
          <table class="table table-hover table-sm mb-0">
            <thead class="table-light">
              <tr>
                <th>#</th>
                <th>OR Number</th>
                <th>Date</th>
                <th>Borrower</th>
                <th>Emp. ID</th>
                <th>Emp. Type</th>
                <th>Loan #</th>
                <th>Loan Type</th>
                <th class="text-end">Amount</th>
                <th>Method</th>
                <th>Recorded By</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!payments.length">
                <td colspan="11" class="text-center text-muted py-3">No data found.</td>
              </tr>
              <tr v-for="(p, i) in payments" :key="p.id">
                <td>{{ i + 1 }}</td>
                <td>{{ p.or_number ?? '-' }}</td>
                <td>{{ p.payment_date }}</td>
                <td>{{ p.borrower }}</td>
                <td>{{ p.employee_id }}</td>
                <td>{{ p.employment_type }}</td>
                <td>#{{ p.loan_id }}</td>
                <td>{{ p.loan_type }}</td>
                <td class="text-end fw-semibold text-success">₱{{ fmt(p.amount) }}</td>
                <td>{{ formatMethod(p.payment_method) }}</td>
                <td>{{ p.recorder }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </AppCard>
    </template>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import { useNotificationStore } from '@/stores/notification'
import { useAdminContextStore } from '@/stores/adminContext'
import admin from '@/services/admin'
import AppLayout from '@/components/layout/AppLayout.vue'
import AppCard from '@/components/ui/AppCard.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppStatCard from '@/components/ui/AppStatCard.vue'
import AppLoading from '@/components/ui/AppLoading.vue'

const notify = useNotificationStore()
const adminContext = useAdminContextStore()
const loading = ref(false)
const exportingCsv = ref(false)
const data = ref(null)
const payments = ref([])
const summary = ref({})

const filters = ref({ from: '', to: '', payment_method: '', employment_type: '' })

function activeFilters() {
  const manual = Object.fromEntries(Object.entries(filters.value).filter(([, v]) => v !== ''))
  return { ...adminContext.filterParam, ...manual }
}

function fmt(val) {
  return Number(val ?? 0).toLocaleString('en-PH', { minimumFractionDigits: 2 })
}

function formatMethod(m) {
  return (m ?? '').replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())
}

async function fetchReport() {
  loading.value = true
  try {
    const { data: res } = await admin.getReportPayments(activeFilters())
    data.value = res.data ?? res
    payments.value = data.value.payments ?? []
    summary.value = data.value.summary ?? {}
  } catch {
    notify.error('Failed to generate report.')
  } finally {
    loading.value = false
  }
}

function resetFilters() {
  filters.value = { from: '', to: '', payment_method: '', employment_type: '' }
  data.value = null
  payments.value = []
}

async function exportCsv() {
  exportingCsv.value = true
  try {
    const response = await admin.downloadReportCsv('payments', activeFilters())
    const url = URL.createObjectURL(new Blob([response.data]))
    const a = document.createElement('a')
    a.href = url
    a.download = `payments-report-${new Date().toISOString().slice(0, 10)}.csv`
    a.click()
    URL.revokeObjectURL(url)
  } catch {
    notify.error('Failed to export CSV.')
  } finally {
    exportingCsv.value = false
  }
}

function exportPdf() {
  const token = localStorage.getItem('pmbf_token')
  const params = new URLSearchParams({ token, ...activeFilters() })
  window.open(`/api/v1/reports/payments/pdf?${params}`, '_blank')
}
</script>
