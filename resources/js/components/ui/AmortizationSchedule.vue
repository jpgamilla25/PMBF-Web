<template>
  <AppCard title="Amortization Schedule">
    <template #header>
      <div class="d-flex align-items-center justify-content-between">
        <h5 class="card-title mb-0">Amortization Schedule</h5>
        <span v-if="summary" class="badge bg-light text-dark border">
          {{ summary.term_months }} monthly deductions
        </span>
      </div>
    </template>

    <AppLoading :loading="loading" text="Loading schedule..." />

    <div v-if="!loading && error" class="text-muted small py-2">
      <i class="bi bi-exclamation-circle me-1"></i>{{ error }}
    </div>

    <template v-if="!loading && summary">
      <!-- Progress -->
      <div class="mb-3">
        <div class="d-flex justify-content-between align-items-baseline mb-1">
          <span class="fw-semibold small">
            Payment {{ Math.min(summary.periods_paid + 1, summary.periods_total) }} of {{ summary.periods_total }}
            <span class="text-muted fw-normal">&middot; {{ summary.percent_complete }}% paid</span>
          </span>
          <span class="small text-muted">
            {{ summary.periods_paid }} paid &middot; {{ summary.periods_remaining }} remaining
          </span>
        </div>
        <div class="progress" style="height: 10px;">
          <div class="progress-bar bg-success" :style="{ width: summary.percent_complete + '%' }"></div>
        </div>
        <div class="d-flex justify-content-between small text-muted mt-1">
          <span>Paid: &#8369;{{ Number(summary.total_paid ?? 0).toLocaleString() }}</span>
          <span>Remaining: &#8369;{{ Number(summary.total_remaining ?? 0).toLocaleString() }}</span>
        </div>
      </div>

      <!-- Next due callout -->
      <div
        v-if="summary.next_due"
        class="alert d-flex align-items-center mb-3"
        :class="summary.next_due.is_overdue ? 'alert-danger' : 'alert-info'"
      >
        <i
          class="bi fs-4 me-3"
          :class="summary.next_due.is_overdue ? 'bi-exclamation-triangle' : 'bi-calendar-event'"
        ></i>
        <div class="small">
          <strong>
            {{ summary.next_due.is_overdue ? 'Deduction Overdue' : 'Next Payroll Deduction' }}
          </strong>
          <div>
            Payment #{{ summary.next_due.period }} &mdash;
            <strong>&#8369;{{ Number(summary.next_due.amount ?? 0).toLocaleString() }}</strong>
            <template v-if="summary.next_due.due_date_label">
              on <strong>{{ summary.next_due.due_date_label }}</strong>
            </template>
            <span v-if="summary.next_due.is_overdue" class="fw-bold ms-1">
              ({{ Math.abs(summary.next_due.days_until ?? 0) }} days late)
            </span>
            <span v-else-if="(summary.next_due.days_until ?? 99) <= 7" class="fw-bold ms-1">
              (in {{ summary.next_due.days_until }} days)
            </span>
          </div>
          <div v-if="!summary.is_released" class="text-muted">
            Projected &mdash; dates firm up once the loan is released.
          </div>
        </div>
      </div>

      <div v-else class="alert alert-success d-flex align-items-center mb-3">
        <i class="bi bi-check-circle fs-4 me-3"></i>
        <div class="small"><strong>Fully paid.</strong> All {{ summary.periods_total }} deductions are settled.</div>
      </div>

      <!-- Schedule table -->
      <div class="table-responsive schedule-scroll">
        <table class="table table-sm table-hover align-middle mb-0">
          <thead class="table-light sticky-head">
            <tr>
              <th style="width: 48px;">#</th>
              <th>Due Date</th>
              <th class="text-end">Principal</th>
              <th class="text-end">Interest</th>
              <th class="text-end">Total Due</th>
              <th class="text-end">Balance</th>
              <th class="text-center">Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in schedule" :key="row.period" :class="{ 'table-danger': row.status === 'overdue' }">
              <td class="text-muted">{{ row.period }}</td>
              <td>{{ row.due_date_label || '-' }}</td>
              <td class="text-end">&#8369;{{ Number(row.principal).toLocaleString() }}</td>
              <td class="text-end">&#8369;{{ Number(row.interest).toLocaleString() }}</td>
              <td class="text-end fw-semibold">&#8369;{{ Number(row.total_due).toLocaleString() }}</td>
              <td class="text-end">&#8369;{{ Number(row.balance).toLocaleString() }}</td>
              <td class="text-center">
                <AppBadge :variant="statusVariant(row.status)" :text="statusLabel(row.status)" />
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </AppCard>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import loansService from '@/services/loans'
import AppCard from '@/components/ui/AppCard.vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import AppLoading from '@/components/ui/AppLoading.vue'
import { useLoading } from '@/composables/useLoading'

const props = defineProps({
  loanId: { type: [String, Number], required: true },
})

const { loading, withLoading } = useLoading()
const schedule = ref([])
const summary = ref(null)
const error = ref('')

const STATUS_LABELS = {
  paid: 'Paid',
  partial: 'Partial',
  overdue: 'Overdue',
  upcoming: 'Upcoming',
  scheduled: 'Scheduled',
}

const STATUS_VARIANTS = {
  paid: 'success',
  partial: 'info',
  overdue: 'danger',
  upcoming: 'warning',
  scheduled: 'secondary',
}

function statusLabel(status) {
  return STATUS_LABELS[status] ?? status
}

function statusVariant(status) {
  return STATUS_VARIANTS[status] ?? 'secondary'
}

async function fetchSchedule() {
  error.value = ''
  await withLoading(async () => {
    try {
      const response = await loansService.getSchedule(props.loanId)
      const data = response.data.data ?? response.data
      schedule.value = data.schedule ?? []
      summary.value = data.summary ?? null
    } catch (err) {
      summary.value = null
      schedule.value = []
      error.value = err.response?.data?.message || 'Unable to load the amortization schedule.'
    }
  })
}

watch(() => props.loanId, fetchSchedule)
onMounted(fetchSchedule)
</script>

<style scoped>
.schedule-scroll { max-height: 380px; overflow-y: auto; }
.sticky-head th { position: sticky; top: 0; z-index: 1; }
</style>
