<template>
  <AppLayout>
    <!-- Header -->
    <div class="d-flex align-items-center gap-2 mb-1">
      <button class="btn btn-sm btn-outline-secondary" @click="$router.push('/admin/reports')">
        <i class="bi bi-arrow-left"></i>
      </button>
      <h4 class="fw-bold mb-0">Loans Report</h4>
    </div>
    <p class="text-muted small mb-4 ms-5">Loan applications with amounts, status, and payment balances.</p>

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
          <label class="form-label fw-semibold small">Status</label>
          <select v-model="filters.status" class="form-select form-select-sm">
            <option value="">All</option>
            <option value="released">Released</option>
            <option value="completed">Completed</option>
            <option value="pending">Pending</option>
            <option value="disapproved">Disapproved</option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label fw-semibold small">Loan Type</label>
          <select v-model="filters.loan_type" class="form-select form-select-sm">
            <option value="">All</option>
            <option value="Emergency Loan">Emergency Loan</option>
            <option value="SC Loan">SC Loan</option>
            <option value="Employee Loan">Employee Loan</option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label fw-semibold small">Employment Type</label>
          <div v-if="adminContext.memberType && adminContext.memberType !== 'all'" class="form-control form-control-sm bg-light text-muted d-flex align-items-center gap-1" style="cursor:not-allowed;">
            <i class="bi bi-lock-fill" style="font-size:0.7rem;"></i>
            {{ adminContext.memberType === 'Contract of Service' ? 'Contract of Service' : adminContext.memberType }}
          </div>
          <select v-else v-model="filters.employment_type" class="form-select form-select-sm">
            <option value="">All</option>
            <option value="Permanent">Permanent</option>
            <option value="Contract of Service">Contract of Service</option>
            <option value="Non-Member">Non-Member</option>
          </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
          <AppButton variant="primary" size="sm" :loading="loading" class="w-100" @click="fetchReport">
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
        <div class="col-sm-6 col-lg-3">
          <AppStatCard title="Total Loans" :value="summary.total_count" icon="bi bi-file-earmark-text" color="primary" />
        </div>
        <div class="col-sm-6 col-lg-3">
          <AppStatCard title="Total Amount" :value="summary.total_amount" icon="bi bi-cash-stack" color="primary" prefix="₱" />
        </div>
        <div class="col-sm-6 col-lg-3">
          <AppStatCard title="Total Collected" :value="summary.total_paid" icon="bi bi-check-circle" color="success" prefix="₱" />
        </div>
        <div class="col-sm-6 col-lg-3">
          <AppStatCard title="Completed" :value="summary.total_completed" icon="bi bi-award" color="warning" prefix="₱" />
        </div>
      </div>

      <!-- Export Buttons -->
      <div class="d-flex justify-content-end gap-2 mb-3">
        <AppButton variant="outline-danger" size="sm" :loading="exportingPdf" @click="exportPdf">
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
                <th>Applicant</th>
                <th>Emp. ID</th>
                <th>Type</th>
                <th>Emp. Type</th>
                <th class="text-end">Amount</th>
                <th>Rate</th>
                <th>Term</th>
                <th class="text-end">Monthly</th>
                <th class="text-end">Total Payable</th>
                <th class="text-end">Paid</th>
                <th class="text-end">Balance</th>
                <th>Status</th>
                <th>Applied</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!loans.length">
                <td colspan="14" class="text-center text-muted py-3">No data found.</td>
              </tr>
              <tr v-for="l in loans" :key="l.id">
                <td>{{ l.id }}</td>
                <td>{{ l.applicant }}</td>
                <td>{{ l.employee_id }}</td>
                <td>{{ l.loan_type }}</td>
                <td>{{ l.employment_type }}</td>
                <td class="text-end">₱{{ fmt(l.amount) }}</td>
                <td>{{ l.interest_rate }}%</td>
                <td>{{ l.term_months }}mo</td>
                <td class="text-end">₱{{ fmt(l.monthly_amortization) }}</td>
                <td class="text-end">₱{{ fmt(l.total_payable) }}</td>
                <td class="text-end text-success">₱{{ fmt(l.total_paid) }}</td>
                <td class="text-end text-danger">₱{{ fmt(l.remaining_balance) }}</td>
                <td><AppStatusBadge :status="l.status" /></td>
                <td>{{ l.applied_at ?? '-' }}</td>
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
import AppStatusBadge from '@/components/ui/AppStatusBadge.vue'
import AppLoading from '@/components/ui/AppLoading.vue'

const notify = useNotificationStore()
const adminContext = useAdminContextStore()
const loading = ref(false)
const exportingPdf = ref(false)
const exportingCsv = ref(false)
const data = ref(null)
const loans = ref([])
const summary = ref({})

const filters = ref({ from: '', to: '', status: '', loan_type: '', employment_type: '' })

function activeFilters() {
  const manual = Object.fromEntries(Object.entries(filters.value).filter(([, v]) => v !== ''))
  return { ...adminContext.filterParam, ...manual }
}

function fmt(val) {
  return Number(val ?? 0).toLocaleString('en-PH', { minimumFractionDigits: 2 })
}

async function fetchReport() {
  loading.value = true
  try {
    const { data: res } = await admin.getReportLoans(activeFilters())
    data.value = res.data ?? res
    loans.value = data.value.loans ?? []
    summary.value = data.value.summary ?? {}
  } catch {
    notify.error('Failed to generate report.')
  } finally {
    loading.value = false
  }
}

function resetFilters() {
  filters.value = { from: '', to: '', status: '', loan_type: '', employment_type: '' }
  data.value = null
  loans.value = []
}

async function exportCsv() {
  exportingCsv.value = true
  try {
    const response = await admin.downloadReportCsv('loans', activeFilters())
    const url = URL.createObjectURL(new Blob([response.data]))
    const a = document.createElement('a')
    a.href = url
    a.download = `loans-report-${new Date().toISOString().slice(0, 10)}.csv`
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
  window.open(`/api/v1/reports/loans/pdf?${params}`, '_blank')
}
</script>
