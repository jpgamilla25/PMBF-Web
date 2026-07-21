<template>
  <AppCard title="Payment Statement">
    <template #header>
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h5 class="card-title mb-0">
          <i class="bi bi-receipt me-1"></i>Payment Statement
        </h5>
        <AppButton variant="outline-primary" size="sm" :disabled="loading" @click="openPdf">
          <i class="bi bi-file-earmark-pdf me-1"></i>Download Statement
        </AppButton>
      </div>
    </template>

    <!-- Filters -->
    <div class="row g-2 align-items-end mb-3">
      <div class="col-6 col-md-3">
        <label class="form-label small fw-semibold mb-1">From</label>
        <input v-model="filters.from" type="date" class="form-control form-control-sm">
      </div>
      <div class="col-6 col-md-3">
        <label class="form-label small fw-semibold mb-1">To</label>
        <input v-model="filters.to" type="date" class="form-control form-control-sm">
      </div>
      <div class="col-12 col-md-4">
        <label class="form-label small fw-semibold mb-1">Loan</label>
        <select v-model="filters.loan_id" class="form-select form-select-sm">
          <option value="">All loans</option>
          <option v-for="opt in loanOptions" :key="opt.loan_id" :value="opt.loan_id">
            {{ opt.reference_no }} — {{ formatLoanType(opt.loan_type) }}
          </option>
        </select>
      </div>
      <div class="col-12 col-md-2 d-flex gap-2">
        <AppButton variant="primary" size="sm" :loading="loading" block @click="fetchStatement">
          <i class="bi bi-search me-1"></i>Preview
        </AppButton>
      </div>
    </div>

    <div v-if="filters.from || filters.to || filters.loan_id" class="mb-3">
      <button class="btn btn-link btn-sm p-0 text-decoration-none" @click="resetFilters">
        <i class="bi bi-x-circle me-1"></i>Clear filters
      </button>
    </div>

    <AppLoading :loading="loading" text="Building statement..." />

    <template v-if="!loading && statement">
      <!-- Summary -->
      <div class="row g-2 mb-3">
        <div v-for="tile in summaryTiles" :key="tile.label" class="col-6 col-lg-3">
          <div class="border rounded p-2 bg-body-tertiary h-100">
            <div class="text-body-secondary text-uppercase fw-semibold" style="font-size: .7rem;">
              {{ tile.label }}
            </div>
            <div class="fw-bold" :class="tile.class">{{ tile.value }}</div>
          </div>
        </div>
      </div>

      <!-- Per-loan payment history -->
      <div v-if="statement.loans.length" class="d-flex flex-column gap-3">
        <div v-for="loan in statement.loans" :key="loan.loan_id" class="border rounded">
          <div class="px-3 py-2 border-bottom bg-body-tertiary">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
              <div>
                <span class="fw-semibold">{{ loan.reference_no }}</span>
                <span class="text-body-secondary"> — {{ formatLoanType(loan.loan_type) }}</span>
              </div>
              <AppStatusBadge :status="loan.status" />
            </div>
            <div class="small text-body-secondary mt-1">
              Total payable {{ peso(loan.total_payable) }}
              &bull; Paid {{ peso(loan.total_paid) }}
              &bull; Outstanding <span class="fw-semibold">{{ peso(loan.remaining) }}</span>
            </div>
          </div>

          <div v-if="loan.payments.length" class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Reference / DV No.</th>
                  <th>Method</th>
                  <th class="text-end">Amount</th>
                  <th class="text-end">Running Balance</th>
                </tr>
              </thead>
              <tbody>
                <tr class="text-body-secondary fst-italic">
                  <td colspan="4">Balance brought forward</td>
                  <td class="text-end">{{ peso(loan.opening_balance) }}</td>
                </tr>
                <tr v-for="p in loan.payments" :key="p.id">
                  <td>{{ formatDate(p.date) }}</td>
                  <td class="font-monospace small">{{ p.reference || '-' }}</td>
                  <td class="small">{{ p.method ? formatLoanType(p.method) : '-' }}</td>
                  <td class="text-end">{{ peso(p.amount) }}</td>
                  <td class="text-end">{{ peso(p.balance) }}</td>
                </tr>
              </tbody>
              <tfoot>
                <tr class="fw-semibold border-top">
                  <td colspan="3">Total shown</td>
                  <td class="text-end">{{ peso(loan.paid_in_range) }}</td>
                  <td class="text-end">{{ peso(loan.remaining) }}</td>
                </tr>
              </tfoot>
            </table>
          </div>
          <div v-else class="px-3 py-2 small text-body-secondary fst-italic">
            No payments recorded for this loan in the selected period.
          </div>
        </div>
      </div>
      <p v-else class="text-body-secondary small mb-0">No loans on record for this period.</p>

      <!-- FMIS payroll deductions — separate, never mixed into loan totals -->
      <div v-if="statement.payroll_deductions.length" class="mt-4">
        <h6 class="fw-semibold mb-1">Payroll Deduction History (FMIS)</h6>
        <p class="small text-body-secondary">
          Recorded per payroll period and not tied to a specific loan — shown for reference only
          and excluded from the balances above.
        </p>
        <div class="table-responsive border rounded">
          <table class="table table-sm mb-0">
            <thead>
              <tr>
                <th>Period</th>
                <th>DV Number</th>
                <th>DV Date</th>
                <th>Fund</th>
                <th class="text-end">Amount</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(d, i) in statement.payroll_deductions" :key="i">
                <td>{{ d.period }}</td>
                <td class="font-monospace small">{{ d.dv_number || '-' }}</td>
                <td>{{ formatDate(d.dv_date) }}</td>
                <td class="small">{{ d.fund || '-' }}</td>
                <td class="text-end">{{ peso(d.amount) }}</td>
              </tr>
            </tbody>
            <tfoot>
              <tr class="fw-semibold">
                <td colspan="4">Total deductions</td>
                <td class="text-end">{{ peso(statement.payroll_total) }}</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </template>
  </AppCard>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import members from '@/services/members'
import { useNotificationStore } from '@/stores/notification'
import AppCard from '@/components/ui/AppCard.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppLoading from '@/components/ui/AppLoading.vue'
import AppStatusBadge from '@/components/ui/AppStatusBadge.vue'

const props = defineProps({
  // Staff/admin only: generate the statement for another member.
  // Omitted (null) → the signed-in member's own statement.
  userId: {
    type: [Number, String],
    default: null,
  },
})

const notify = useNotificationStore()
const loading = ref(false)
const statement = ref(null)
const loanOptions = ref([])
const filters = reactive({ from: '', to: '', loan_id: '' })

const queryParams = computed(() => ({
  from: filters.from,
  to: filters.to,
  loan_id: filters.loan_id,
  user_id: props.userId ?? '',
}))

const summaryTiles = computed(() => {
  const s = statement.value?.summary
  if (!s) return []
  return [
    { label: 'Total Borrowed', value: peso(s.total_borrowed), class: 'text-primary' },
    { label: 'Total Paid', value: peso(s.total_paid), class: 'text-success' },
    { label: 'Total Outstanding', value: peso(s.total_outstanding), class: 'text-danger' },
    { label: 'Active Loans', value: String(s.active_loans), class: '' },
  ]
})

function peso(value) {
  return `₱${Number(value ?? 0).toLocaleString('en-PH', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })}`
}

function formatDate(value) {
  if (!value) return '-'
  return new Date(value).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: '2-digit' })
}

function formatLoanType(type) {
  if (!type) return '-'
  return type.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
}

async function fetchStatement() {
  loading.value = true
  try {
    const { data: res } = await members.getStatement(queryParams.value)
    statement.value = res.data ?? res
    // Keep the full loan list for the dropdown — an unfiltered load defines it.
    if (!filters.loan_id) {
      loanOptions.value = statement.value.loans.map((l) => ({
        loan_id: l.loan_id,
        reference_no: l.reference_no,
        loan_type: l.loan_type,
      }))
    }
  } catch (e) {
    notify.error(e.response?.data?.message || 'Failed to load payment statement.')
  } finally {
    loading.value = false
  }
}

function resetFilters() {
  filters.from = ''
  filters.to = ''
  filters.loan_id = ''
  fetchStatement()
}

function openPdf() {
  window.open(members.getStatementPdfUrl(queryParams.value), '_blank')
}

onMounted(fetchStatement)
</script>
