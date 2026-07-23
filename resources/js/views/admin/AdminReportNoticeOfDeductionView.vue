<template>
  <AppLayout>
    <div class="d-flex align-items-center gap-2 mb-1">
      <button class="btn btn-sm btn-outline-secondary" @click="$router.push('/admin/reports')">
        <i class="bi bi-arrow-left"></i>
      </button>
      <h4 class="fw-bold mb-0">Notice of Deduction</h4>
    </div>
    <p class="text-muted small mb-4 ms-5">
      Per-division payroll deduction notice — one row per active salary loan whose term
      overlaps the selected cutoff. Prints as A4 portrait, ready for signing.
    </p>

    <!-- Filters -->
    <AppCard class="mb-4">
      <div class="row g-3 align-items-end">
        <div class="col-md-3">
          <label class="form-label fw-semibold small">Division</label>
          <select v-model="filters.division" class="form-select form-select-sm">
            <option value="">— Select division —</option>
            <option v-for="d in divisions" :key="d.value" :value="d.value">{{ d.label }}</option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label fw-semibold small">Year</label>
          <select v-model.number="filters.year" class="form-select form-select-sm">
            <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label fw-semibold small">Month</label>
          <select v-model.number="filters.month" class="form-select form-select-sm">
            <option v-for="(m, i) in monthNames" :key="i" :value="i + 1">{{ m }}</option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label fw-semibold small">Cutoff</label>
          <select v-model.number="filters.half" class="form-select form-select-sm">
            <option :value="1">1st half (1–15)</option>
            <option :value="2">2nd half (16–end)</option>
          </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
          <AppButton variant="primary" size="sm" :loading="loading" class="w-100" :disabled="!filters.division" @click="fetchReport">
            <i class="bi bi-search me-1"></i>Generate
          </AppButton>
          <AppButton variant="outline-secondary" size="sm" @click="resetFilters">
            <i class="bi bi-x"></i>
          </AppButton>
        </div>
      </div>
    </AppCard>

    <AppLoading :loading="loading" text="Generating notice..." />

    <template v-if="!loading && report">
      <div class="d-flex justify-content-end gap-2 mb-3">
        <AppButton variant="outline-danger" size="sm" @click="exportPdf">
          <i class="bi bi-filetype-pdf me-1"></i>Export PDF
        </AppButton>
      </div>

      <AppCard>
        <!-- Preview matches the printed form -->
        <div class="text-center mb-3">
          <p class="fw-bold mb-0">PHILRICE MUTUAL BENEFIT FUND, INC.</p>
          <p class="fw-bold mb-0">NOTICE OF DEDUCTION{{ report.division ? ' – ' + report.division.toUpperCase() : '' }}</p>
          <p class="mb-0">{{ report.cutoff.label }} payroll</p>
        </div>

        <div class="table-responsive">
          <table class="table table-bordered mb-0 preview-table">
            <thead>
              <tr>
                <th style="width:6%">&nbsp;</th>
                <th style="width:40%">NAME</th>
                <th class="text-end" style="width:20%">LOAN AMORTIZATION</th>
                <th style="width:34%">Remarks</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!report.rows.length">
                <td colspan="4" class="text-center text-muted py-3">
                  No active loans in this division for the selected cutoff.
                </td>
              </tr>
              <tr v-for="row in report.rows" :key="row.loan_id">
                <td class="text-center">{{ row.row_no }}</td>
                <td>{{ row.name }}</td>
                <td class="text-end">{{ fmt(row.semi_monthly) }}</td>
                <td>{{ row.remarks }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="mt-4 small">
          <div class="mb-4">Prepared by: <span class="fw-bold ms-2">{{ report.prepared_by }}</span></div>
          <div>Date: {{ report.generated_at }}</div>
        </div>
      </AppCard>
    </template>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useNotificationStore } from '@/stores/notification'
import admin from '@/services/admin'
import AppLayout from '@/components/layout/AppLayout.vue'
import AppCard from '@/components/ui/AppCard.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppLoading from '@/components/ui/AppLoading.vue'

const notify = useNotificationStore()
const loading = ref(false)
const report = ref(null)
const divisions = ref([])

const monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December']

const today = new Date()
const filters = ref({
  division: '',
  year: today.getFullYear(),
  month: today.getMonth() + 1,
  half: today.getDate() <= 15 ? 1 : 2,
})

const yearOptions = computed(() => {
  const y = today.getFullYear()
  return [y - 2, y - 1, y, y + 1]
})

function fmt(val) {
  return Number(val ?? 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

async function loadDivisions() {
  try {
    const { data: res } = await admin.getDivisions()
    divisions.value = res.data ?? res ?? []
  } catch {
    notify.error('Failed to load divisions.')
  }
}

async function fetchReport() {
  if (!filters.value.division) return
  loading.value = true
  try {
    const { data: res } = await admin.getReportNoticeOfDeduction(filters.value)
    report.value = res?.data ?? res
    console.debug('[NoticeOfDeduction] payload:', report.value)
  } catch {
    notify.error('Failed to generate notice.')
  } finally {
    loading.value = false
  }
}

function resetFilters() {
  filters.value = {
    division: '',
    year: today.getFullYear(),
    month: today.getMonth() + 1,
    half: today.getDate() <= 15 ? 1 : 2,
  }
  report.value = null
}

function exportPdf() {
  const token = localStorage.getItem('pmbf_token') ?? ''
  const params = new URLSearchParams({ token, ...filters.value })
  window.open(`/api/v1/reports/notice-of-deduction/pdf?${params}`, '_blank')
}

onMounted(loadDivisions)
</script>

<style scoped>
.preview-table th {
  background: #f8f9fa;
  font-size: 0.85rem;
}
.preview-table td {
  font-size: 0.9rem;
  vertical-align: middle;
}
</style>
