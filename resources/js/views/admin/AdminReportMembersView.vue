<template>
  <AppLayout>
    <div class="d-flex align-items-center gap-2 mb-1">
      <button class="btn btn-sm btn-outline-secondary" @click="$router.push('/admin/reports')">
        <i class="bi bi-arrow-left"></i>
      </button>
      <h4 class="fw-bold mb-0">Members Report</h4>
    </div>
    <p class="text-muted small mb-4 ms-5">Member directory with employment type, department, and loan activity.</p>

    <!-- Filters -->
    <AppCard class="mb-4">
      <div class="row g-3 align-items-end">
        <div class="col-md-3">
          <label class="form-label fw-semibold small">Search</label>
          <input v-model="filters.search" type="text" class="form-control form-control-sm" placeholder="Name or Employee ID..." />
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
        <div class="col-md-2">
          <label class="form-label fw-semibold small">Status</label>
          <select v-model="filters.status" class="form-select form-select-sm">
            <option value="">All</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
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
        <div class="col-sm-6 col-lg-3">
          <AppStatCard title="Total Members" :value="summary.total_count" icon="bi bi-people-fill" color="primary" />
        </div>
        <div v-for="(count, type) in summary.by_type" :key="type" class="col-sm-6 col-lg-3">
          <AppStatCard :title="type || 'Unknown'" :value="count" icon="bi bi-person" color="secondary" />
        </div>
        <div class="col-sm-6 col-lg-3">
          <AppStatCard title="With Active Loans" :value="summary.active_loans" icon="bi bi-cash" color="warning" />
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
                <th>Email</th>
                <th>Employment Type</th>
                <th>Department</th>
                <th>Role</th>
                <th class="text-center">Total Loans</th>
                <th class="text-center">Active Loans</th>
                <th>Joined</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!members.length">
                <td colspan="10" class="text-center text-muted py-3">No data found.</td>
              </tr>
              <tr v-for="(m, i) in members" :key="m.id">
                <td>{{ i + 1 }}</td>
                <td>{{ m.employee_id }}</td>
                <td>{{ m.name }}</td>
                <td>{{ m.email }}</td>
                <td><AppStatusBadge :status="m.employment_type" /></td>
                <td>{{ m.department ?? '-' }}</td>
                <td>{{ formatRole(m.role) }}</td>
                <td class="text-center">{{ m.loans_count }}</td>
                <td class="text-center">
                  <span v-if="m.active_loans > 0" class="badge bg-warning text-dark">{{ m.active_loans }}</span>
                  <span v-else class="text-muted">0</span>
                </td>
                <td>{{ m.joined_at }}</td>
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
const exportingCsv = ref(false)
const data = ref(null)
const members = ref([])
const summary = ref({})

const filters = ref({ search: '', employment_type: '', status: '' })

function activeFilters() {
  const manual = Object.fromEntries(Object.entries(filters.value).filter(([, v]) => v !== ''))
  return { ...adminContext.filterParam, ...manual }
}

function formatRole(r) {
  return (r ?? '').replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())
}

async function fetchReport() {
  loading.value = true
  try {
    const { data: res } = await admin.getReportMembers(activeFilters())
    data.value = res.data ?? res
    members.value = data.value.members ?? []
    summary.value = data.value.summary ?? {}
  } catch {
    notify.error('Failed to generate report.')
  } finally {
    loading.value = false
  }
}

function resetFilters() {
  filters.value = { search: '', employment_type: '', status: '' }
  data.value = null
  members.value = []
}

async function exportCsv() {
  exportingCsv.value = true
  try {
    const response = await admin.downloadReportCsv('members', activeFilters())
    const url = URL.createObjectURL(new Blob([response.data]))
    const a = document.createElement('a')
    a.href = url
    a.download = `members-report-${new Date().toISOString().slice(0, 10)}.csv`
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
  window.open(`/api/v1/reports/members/pdf?${params}`, '_blank')
}
</script>
