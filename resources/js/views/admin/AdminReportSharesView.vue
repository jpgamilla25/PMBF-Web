<template>
  <AppLayout>
    <div class="d-flex align-items-center gap-2 mb-1">
      <button class="btn btn-sm btn-outline-secondary" @click="$router.push('/admin/reports')">
        <i class="bi bi-arrow-left"></i>
      </button>
      <h4 class="fw-bold mb-0">Share Capital Report</h4>
    </div>
    <p class="text-muted small mb-4 ms-5">Monthly share capital contributions per member.</p>

    <!-- Filters -->
    <AppCard class="mb-4">
      <div class="row g-3 align-items-end">
        <div class="col-md-2">
          <label class="form-label fw-semibold small">Year</label>
          <select v-model="filters.year" class="form-select form-select-sm">
            <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
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
        <div class="col-md-3 d-flex gap-2 align-items-end">
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
          <AppStatCard title="Members" :value="summary.member_count" icon="bi bi-people" color="warning" />
        </div>
        <div class="col-sm-6 col-lg-4">
          <AppStatCard :title="`Total Share Capital ${filters.year}`" :value="summary.total_amount" icon="bi bi-pie-chart-fill" color="warning" prefix="₱" />
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
                <th>Employee ID</th>
                <th>Name</th>
                <th>Type</th>
                <th v-for="m in monthNames" :key="m" class="text-end">{{ m }}</th>
                <th class="text-end fw-bold">Total</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!shares.length">
                <td :colspan="4 + 12 + 1" class="text-center text-muted py-3">No data found.</td>
              </tr>
              <tr v-for="(s, i) in shares" :key="s.user_id">
                <td>{{ i + 1 }}</td>
                <td>{{ s.employee_id }}</td>
                <td>{{ s.name }}</td>
                <td>{{ s.employment_type }}</td>
                <td v-for="(m, mi) in monthNames" :key="mi" class="text-end">
                  <span v-if="getMonth(s, mi + 1)" class="small">₱{{ fmt(getMonth(s, mi + 1)) }}</span>
                  <span v-else class="text-muted">-</span>
                </td>
                <td class="text-end fw-bold text-success">₱{{ fmt(s.total) }}</td>
              </tr>
            </tbody>
            <tfoot v-if="shares.length">
              <tr class="table-warning fw-bold">
                <td colspan="4">Total</td>
                <td v-for="(m, mi) in monthNames" :key="mi" class="text-end small">
                  ₱{{ fmt(monthTotal(mi + 1)) }}
                </td>
                <td class="text-end">₱{{ fmt(summary.total_amount) }}</td>
              </tr>
            </tfoot>
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
const shares = ref([])
const summary = ref({})

const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
const yearOptions = Array.from({ length: 5 }, (_, i) => new Date().getFullYear() - i)

const filters = ref({ year: new Date().getFullYear(), employment_type: '' })

function activeFilters() {
  const manual = Object.fromEntries(Object.entries(filters.value).filter(([, v]) => v !== ''))
  return { ...adminContext.filterParam, ...manual }
}

function fmt(val) {
  return Number(val ?? 0).toLocaleString('en-PH', { minimumFractionDigits: 2 })
}

function getMonth(row, month) {
  const entry = row.monthly?.find(m => m.month === month)
  return entry?.amount ?? null
}

function monthTotal(month) {
  return shares.value.reduce((sum, s) => sum + Number(getMonth(s, month) ?? 0), 0)
}

async function fetchReport() {
  loading.value = true
  try {
    const { data: res } = await admin.getReportShares(activeFilters())
    data.value = res.data ?? res
    shares.value = data.value.shares ?? []
    summary.value = data.value.summary ?? {}
  } catch {
    notify.error('Failed to generate report.')
  } finally {
    loading.value = false
  }
}

function resetFilters() {
  filters.value = { year: new Date().getFullYear(), employment_type: '' }
  data.value = null
  shares.value = []
}

async function exportCsv() {
  exportingCsv.value = true
  try {
    const response = await admin.downloadReportCsv('shares', activeFilters())
    const url = URL.createObjectURL(new Blob([response.data]))
    const a = document.createElement('a')
    a.href = url
    a.download = `shares-report-${filters.value.year}.csv`
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
  window.open(`/api/v1/reports/shares/pdf?${params}`, '_blank')
}
</script>
