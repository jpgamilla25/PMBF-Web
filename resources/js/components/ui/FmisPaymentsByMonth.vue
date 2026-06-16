<template>
  <AppCard class="mb-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <h6 class="fw-bold mb-0">
          <i class="bi bi-table me-1 text-primary"></i>
          FMIS Payments by Month
          <span v-if="meta.total" class="badge bg-light text-dark border ms-2">{{ meta.total }} bucket(s)</span>
        </h6>
        <div class="text-muted small">
          Payroll deductions grouped per member &times; month. Total in range:
          <strong>&#8369;{{ formatPeso(meta.total_amount ?? 0) }}</strong>
        </div>
      </div>
      <AppButton variant="outline-secondary" size="sm" :loading="loading" @click="fetch(1)">
        <i v-if="!loading" class="bi bi-arrow-clockwise me-1"></i>Refresh
      </AppButton>
    </div>

    <div class="row g-2 mb-3 align-items-end">
      <div class="col-auto">
        <label class="form-label small mb-1">Quick Range</label>
        <div class="btn-group btn-group-sm">
          <button
            v-for="p in presets"
            :key="p.label"
            class="btn"
            :class="activePreset === p.label ? 'btn-primary' : 'btn-outline-secondary'"
            @click="applyPreset(p)"
          >{{ p.label }}</button>
        </div>
      </div>
      <div class="col-sm-3 col-md-2">
        <label class="form-label small mb-1">From</label>
        <input v-model="filterFrom" type="date" class="form-control form-control-sm" @change="onManualDate" />
      </div>
      <div class="col-sm-3 col-md-2">
        <label class="form-label small mb-1">To</label>
        <input v-model="filterTo" type="date" class="form-control form-control-sm" @change="onManualDate" />
      </div>
      <div class="col-md-3">
        <label class="form-label small mb-1">Search</label>
        <input
          v-model="filterSearch"
          type="text"
          class="form-control form-control-sm"
          placeholder="Member, employee ID, or DV #..."
          @keyup.enter="fetch(1)"
        />
      </div>
    </div>

    <AppLoading :loading="loading && !items.length" text="Loading payments..." />

    <div v-if="!loading && !items.length" class="text-center text-muted py-3">
      <i class="bi bi-inbox me-1"></i>No FMIS payments in this range.
    </div>

    <div v-else-if="items.length" class="table-responsive">
      <table class="table table-hover table-sm align-middle mb-0">
        <thead>
          <tr>
            <th>Member</th>
            <th>Period</th>
            <th class="text-end">Total</th>
            <th class="text-end">DVs</th>
            <th>Breakdown</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in items" :key="row.bucket_key">
            <td>
              <div class="fw-semibold">{{ row.member?.full_name ?? '—' }}</div>
              <div class="small text-muted font-monospace">{{ row.employee_id }}</div>
              <AppStatusBadge v-if="row.member?.employment_type" :status="row.member.employment_type" class="mt-1" />
            </td>
            <td class="small">{{ monthNames[row.month - 1] }} {{ row.year }}</td>
            <td class="text-end fw-semibold text-primary">&#8369;{{ formatPeso(row.total_amount) }}</td>
            <td class="text-end">
              <span class="badge bg-info text-dark">{{ row.dv_count }}</span>
            </td>
            <td class="small">
              <div v-for="dv in row.dvs" :key="dv.dv_number" class="font-monospace">
                {{ dv.dv_number }}
                <span class="text-muted">({{ dv.dv_date }}, {{ dv.fund }})</span>
                &mdash; &#8369;{{ formatPeso(dv.amount) }}
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <AppPagination
      v-if="items.length"
      :meta="{ current_page: meta.current_page, last_page: meta.last_page, total: meta.total, per_page: meta.per_page }"
      @page-change="fetch"
    />
  </AppCard>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useNotificationStore } from '@/stores/notification'
import loanPaymentsService from '@/services/loanPayments'
import AppCard from '@/components/ui/AppCard.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppLoading from '@/components/ui/AppLoading.vue'
import AppPagination from '@/components/ui/AppPagination.vue'
import AppStatusBadge from '@/components/ui/AppStatusBadge.vue'

const notify = useNotificationStore()
const items = ref([])
const meta = reactive({ current_page: 1, last_page: 1, total: 0, per_page: 20, total_amount: 0 })
const loading = ref(false)

const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']

const filterFrom = ref('')
const filterTo = ref('')
const filterSearch = ref('')
const activePreset = ref('This Year')

function localDate(d) {
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

const presets = [
  {
    label: 'This Month',
    from: () => localDate(new Date(new Date().getFullYear(), new Date().getMonth(), 1)),
    to: () => localDate(new Date()),
  },
  {
    label: 'Last Month',
    from: () => { const d = new Date(); d.setDate(1); d.setMonth(d.getMonth() - 1); return localDate(d) },
    to: () => { const d = new Date(); d.setDate(0); return localDate(d) },
  },
  {
    label: 'This Year',
    from: () => localDate(new Date(new Date().getFullYear(), 0, 1)),
    to: () => localDate(new Date()),
  },
  { label: 'All', from: () => '', to: () => '' },
]

function applyPreset(p) {
  activePreset.value = p.label
  filterFrom.value = p.from()
  filterTo.value = p.to()
  fetch(1)
}

function onManualDate() {
  activePreset.value = ''
  fetch(1)
}

function formatPeso(n) {
  return Number(n ?? 0).toLocaleString('en-PH', { minimumFractionDigits: 2 })
}

async function fetch(page = 1) {
  loading.value = true
  try {
    const { data } = await loanPaymentsService.getByMonth({
      page,
      from: filterFrom.value || undefined,
      to: filterTo.value || undefined,
      search: filterSearch.value || undefined,
    })
    items.value = data.data ?? []
    Object.assign(meta, data.meta ?? {})
  } catch (e) {
    notify.error(e.response?.data?.message || 'Failed to load FMIS payments.')
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  applyPreset(presets.find(p => p.label === 'This Year'))
})

defineExpose({ refresh: () => fetch(meta.current_page) })
</script>
