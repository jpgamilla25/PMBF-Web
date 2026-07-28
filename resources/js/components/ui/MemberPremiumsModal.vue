<template>
  <AppModal
    :show="show"
    :title="data ? `Premiums — ${data.user.last_name}, ${data.user.first_name}` : 'Premiums'"
    size="lg"
    @close="$emit('close')"
  >
    <div v-if="!data && loading" class="text-center py-4">
      <div class="spinner-border spinner-border-sm text-muted"></div>
      <div class="small text-muted mt-2">Loading premiums…</div>
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
        <div class="col-sm-4">
          <div class="border rounded p-2">
            <div class="small text-muted">Curated total ({{ data.year }})</div>
            <div class="fw-bold text-primary">&#8369;{{ formatPeso(data.totals.curated) }}</div>
          </div>
        </div>
        <div class="col-sm-4">
          <div class="border rounded p-2">
            <div class="small text-muted">FMIS total ({{ data.year }})</div>
            <div class="fw-bold text-success">&#8369;{{ formatPeso(data.totals.fmis) }}</div>
          </div>
        </div>
        <div class="col-sm-4">
          <div class="border rounded p-2">
            <div class="small text-muted">Lifetime FMIS</div>
            <div class="fw-bold">&#8369;{{ formatPeso(data.totals.lifetime_fmis) }}</div>
          </div>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead>
            <tr>
              <th style="width:28px"></th>
              <th>Month</th>
              <th>Type</th>
              <th class="text-end">Curated</th>
              <th class="text-end">FMIS</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <template v-for="row in data.months" :key="row.month">
              <tr>
                <td class="text-center">
                  <button
                    v-if="row.fmis_amount != null || row.curated_amount != null"
                    class="btn btn-sm btn-link p-0"
                    :title="expanded[row.month] ? 'Hide DV' : 'View DV'"
                    @click="toggle(row.month)"
                  >
                    <i class="bi" :class="expanded[row.month] ? 'bi-chevron-down' : 'bi-chevron-right'"></i>
                  </button>
                </td>
                <td class="fw-semibold">{{ monthNames[row.month - 1] }}</td>
                <td>
                  <span v-if="row.type === 'share'" class="badge bg-info-subtle text-info-emphasis border border-info-subtle">share</span>
                  <span v-else-if="row.type === 'premium'" class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">premium</span>
                  <span v-else class="text-muted">—</span>
                </td>
                <td class="text-end">{{ row.curated_amount != null ? '₱' + formatPeso(row.curated_amount) : '—' }}</td>
                <td class="text-end">{{ row.fmis_amount != null ? '₱' + formatPeso(row.fmis_amount) : '—' }}</td>
                <td>
                  <span v-if="row.voided" class="badge bg-danger">voided</span>
                  <span v-else-if="row.fmis_amount != null && row.curated_amount != null && Number(row.fmis_amount) !== Number(row.curated_amount)" class="badge bg-warning text-dark">drift</span>
                  <span v-else-if="row.curated_amount != null" class="badge bg-success">synced</span>
                  <span v-else class="text-muted">—</span>
                </td>
              </tr>
              <tr v-if="expanded[row.month]">
                <td></td>
                <td colspan="5" class="bg-light">
                  <div class="small text-muted mb-1 fw-semibold">DVs for {{ monthNames[row.month - 1] }} {{ data.year }}</div>
                  <div v-if="row.dv_number || row.fmis_amount != null" class="border rounded p-2 bg-white">
                    <div class="row g-2 small">
                      <div class="col-sm-3"><span class="text-muted">DV No.</span><div class="font-monospace">{{ row.dv_number ?? '—' }}</div></div>
                      <div class="col-sm-3"><span class="text-muted">DV Date</span><div>{{ row.dv_date ?? '—' }}</div></div>
                      <div class="col-sm-3"><span class="text-muted">Fund</span><div>{{ row.fund ?? '—' }}</div></div>
                      <div class="col-sm-3"><span class="text-muted">Amount</span><div class="fw-bold">₱{{ formatPeso(row.fmis_amount ?? 0) }}</div></div>
                      <div class="col-sm-12">
                        <span class="text-muted">Classification:</span>
                        <span v-if="row.type === 'share'" class="badge bg-info-subtle text-info-emphasis border border-info-subtle ms-1">share</span>
                        <span v-else-if="row.type === 'premium'" class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle ms-1">premium</span>
                        <span v-else class="badge bg-secondary-subtle text-secondary-emphasis ms-1">no curated row</span>
                      </div>
                      <div v-if="row.remarks" class="col-sm-12 fst-italic text-muted">"{{ row.remarks }}"</div>
                    </div>
                  </div>
                  <div v-else class="text-muted small fst-italic">No DV recorded from FMIS for this month.</div>
                </td>
              </tr>
            </template>
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
import premiumsService from '@/services/premiums'
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
const expanded = ref({}) // month(1-12) => bool
const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']

function toggle(month) {
  expanded.value = { ...expanded.value, [month]: !expanded.value[month] }
}

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
    const { data: payload } = await premiumsService.getMemberPremiums(props.userId, { year: year.value })
    data.value = payload.data ?? payload
  } catch (e) {
    notify.error(e.response?.data?.message || 'Failed to load member premiums.')
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
      expanded.value = {}
      fetchData()
    } else if (!show) {
      data.value = null
      expanded.value = {}
    }
  },
  { immediate: true }
)
</script>
