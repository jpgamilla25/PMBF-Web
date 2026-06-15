<template>
  <AppModal
    :show="show"
    :title="data ? `Payroll Deductions — ${data.user.last_name}, ${data.user.first_name}` : 'Payroll Deductions'"
    size="lg"
    @close="$emit('close')"
  >
    <div v-if="!data && loading" class="text-center py-4">
      <div class="spinner-border spinner-border-sm text-muted"></div>
      <div class="small text-muted mt-2">Loading payments…</div>
    </div>

    <template v-if="data">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
          <div class="small text-muted">Employee ID</div>
          <div class="fw-semibold font-monospace">{{ data.user.employee_id }}</div>
        </div>
        <div>
          <label class="form-label small text-muted mb-1">Year</label>
          <select v-model.number="year" class="form-select form-select-sm" @change="fetchData">
            <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
          </select>
        </div>
      </div>

      <div class="row g-2 mb-3">
        <div class="col-sm-6">
          <div class="border rounded p-2">
            <div class="small text-muted">{{ data.year }} total</div>
            <div class="fw-bold text-primary">&#8369;{{ formatPeso(data.totals.year) }}</div>
          </div>
        </div>
        <div class="col-sm-6">
          <div class="border rounded p-2">
            <div class="small text-muted">Lifetime payroll deductions</div>
            <div class="fw-bold">&#8369;{{ formatPeso(data.totals.lifetime) }}</div>
          </div>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead>
            <tr>
              <th>Month</th>
              <th class="text-end">Amount</th>
              <th>Method</th>
              <th>DV</th>
              <th>Fund</th>
              <th>Date</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in data.months" :key="row.month">
              <td class="fw-semibold">{{ monthNames[row.month - 1] }}</td>
              <td class="text-end">{{ row.amount != null ? '₱' + formatPeso(row.amount) : '—' }}</td>
              <td class="small">
                <span v-if="row.amount != null" class="badge bg-info text-dark">{{ row.method }}</span>
                <span v-else class="text-muted">—</span>
              </td>
              <td class="font-monospace small">{{ row.dv_number ?? '—' }}</td>
              <td class="small">{{ row.fund ?? '—' }}</td>
              <td class="small">{{ row.dv_date ?? '—' }}</td>
              <td>
                <span v-if="row.voided" class="badge bg-danger">voided</span>
                <span v-else-if="row.amount != null" class="badge bg-success">posted</span>
                <span v-else class="text-muted">—</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="data.years_with_data?.length" class="mt-3 small text-muted">
        Other years on file:
        <a
          v-for="y in data.years_with_data.filter(y => y.year !== data.year)"
          :key="y.year"
          href="#"
          class="ms-2"
          @click.prevent="year = y.year; fetchData()"
        >
          {{ y.year }} (&#8369;{{ formatPeso(y.total) }})
        </a>
      </div>
    </template>

    <template #footer>
      <AppButton variant="secondary" @click="$emit('close')">Close</AppButton>
    </template>
  </AppModal>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { useNotificationStore } from '@/stores/notification'
import loanPaymentsService from '@/services/loanPayments'
import AppModal from '@/components/ui/AppModal.vue'
import AppButton from '@/components/ui/AppButton.vue'

const props = defineProps({
  show: { type: Boolean, default: false },
  userId: { type: [Number, String, null], default: null },
  initialYear: { type: Number, default: () => new Date().getFullYear() },
})
defineEmits(['close'])

const notify = useNotificationStore()
const loading = ref(false)
const data = ref(null)
const year = ref(props.initialYear)
const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']

const yearOptions = computed(() => {
  const current = new Date().getFullYear()
  const fromData = (data.value?.years_with_data ?? []).map(y => y.year)
  const set = new Set([...fromData, current, current - 1, current - 2])
  return Array.from(set).sort((a, b) => b - a)
})

function formatPeso(n) {
  return Number(n ?? 0).toLocaleString('en-PH', { minimumFractionDigits: 2 })
}

async function fetchData() {
  if (!props.userId) return
  loading.value = true
  try {
    const { data: payload } = await loanPaymentsService.getMemberPayments(props.userId, { year: year.value })
    data.value = payload.data ?? payload
  } catch (e) {
    notify.error(e.response?.data?.message || 'Failed to load member payments.')
  } finally {
    loading.value = false
  }
}

watch(
  () => [props.show, props.userId],
  ([show, userId]) => {
    if (show && userId) {
      year.value = props.initialYear
      data.value = null
      fetchData()
    } else if (!show) {
      data.value = null
    }
  },
  { immediate: true }
)
</script>
