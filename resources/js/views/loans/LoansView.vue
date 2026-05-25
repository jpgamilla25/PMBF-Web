<template>
  <AppLayout>
    <div class="d-flex align-items-center justify-content-between mb-4">
      <h4 class="fw-bold mb-0">My Loans</h4>
      <router-link to="/loans/new" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i>Apply for Loan
      </router-link>
    </div>

    <!-- Co-Maker Consent Requests -->
    <AppCard v-if="pendingCoMakerRequests.length" title="Co-Maker Consent Requests" class="mb-4">
      <div
        v-for="loan in pendingCoMakerRequests"
        :key="loan.id"
        class="py-3 border-bottom"
      >
        <div class="d-flex align-items-start justify-content-between">
          <div>
            <div class="fw-semibold">
              {{ loan.applicant.full_name }}
              <span class="text-muted fw-normal small">({{ loan.applicant.employee_id }})</span>
              &mdash; <span class="text-primary">{{ loan.loan_type }}</span>
            </div>
            <div class="small text-muted mt-1">
              Amount: <strong>&#8369;{{ Number(loan.amount).toLocaleString() }}</strong>
              &bull; {{ loan.term_months }} months
              &bull; Monthly: <strong>&#8369;{{ Number(loan.monthly_amortization).toLocaleString() }}</strong>
            </div>
            <div v-if="loan.purpose" class="small text-muted fst-italic mt-1">"{{ loan.purpose }}"</div>
          </div>
          <div class="d-flex gap-2 ms-3 flex-shrink-0">
            <button
              class="btn btn-sm btn-success"
              :disabled="respondingId === loan.id"
              @click="respondCoMaker(loan.id, 'approve')"
            >
              <span v-if="respondingId === loan.id" class="spinner-border spinner-border-sm me-1"></span>
              <i v-else class="bi bi-check-lg me-1"></i>Agree
            </button>
            <button
              class="btn btn-sm btn-outline-danger"
              :disabled="respondingId === loan.id"
              @click="respondCoMaker(loan.id, 'decline')"
            >
              <i class="bi bi-x-lg me-1"></i>Decline
            </button>
          </div>
        </div>
        <div class="alert alert-warning small mt-2 mb-0 py-2">
          <i class="bi bi-exclamation-triangle me-1"></i>
          By agreeing, you guarantee this loan. If the borrower fails to pay, you may be held responsible.
        </div>
      </div>
    </AppCard>

    <!-- Pending Exemption Requests -->
    <AppCard v-if="pendingExemptions.length" title="Special Approval Requests" class="mb-4">
      <div
        v-for="ex in pendingExemptions"
        :key="ex.id"
        class="d-flex align-items-start justify-content-between py-2 border-bottom"
      >
        <div>
          <span class="badge bg-warning text-dark me-2">Pending Admin Review</span>
          <strong>{{ ex.loan_type }}</strong> — {{ formatExemptionType(ex.type) }}
          <div class="small text-muted mt-1">
            Requested: <strong>&#8369;{{ Number(ex.requested_value || 0).toLocaleString() }}</strong>
            &nbsp;&bull;&nbsp; Submitted {{ formatDate(ex.created_at) }}
          </div>
          <div v-if="ex.reason" class="small text-muted fst-italic">"{{ ex.reason }}"</div>
        </div>
        <span class="badge bg-warning text-dark ms-3 flex-shrink-0">Awaiting Approval</span>
      </div>
      <div class="small text-muted mt-2">
        <i class="bi bi-info-circle me-1"></i>
        Once approved, you can return here and apply for your loan.
      </div>
    </AppCard>

    <AppCard :padding="false">
      <AppTable :columns="columns" :items="items" :loading="loading" empty-text="No loans found.">
        <template #cell(index)="{ item }">
          {{ items.indexOf(item) + 1 + (paginationMeta.current_page - 1) * paginationMeta.per_page }}
        </template>
        <template #cell(loan_type)="{ value }">
          {{ formatLoanType(value) }}
        </template>
        <template #cell(amount)="{ value }">
          ₱{{ Number(value).toLocaleString() }}
        </template>
        <template #cell(monthly_amortization)="{ value }">
          ₱{{ Number(value).toLocaleString() }}
        </template>
        <template #cell(status)="{ value }">
          <AppStatusBadge :status="value" />
        </template>
        <template #cell(created_at)="{ value }">
          {{ formatDate(value) }}
        </template>
        <template #cell(actions)="{ item }">
          <router-link
            :to="`/loans/${item.id}`"
            class="btn btn-sm btn-outline-primary"
          >
            <i class="bi bi-eye me-1"></i>View
          </router-link>
        </template>
      </AppTable>

      <div v-if="paginationMeta.last_page > 1" class="p-3 border-top">
        <AppPagination :meta="paginationMeta" @page-change="fetch" />
      </div>
    </AppCard>
  </AppLayout>
</template>

<script setup>
import { computed, ref, onMounted } from 'vue'
import { usePagination } from '@/composables/usePagination'
import loans from '@/services/loans'
import exemptions from '@/services/exemptions'
import { useNotificationStore } from '@/stores/notification'
import AppLayout from '@/components/layout/AppLayout.vue'
import AppCard from '@/components/ui/AppCard.vue'
import AppTable from '@/components/ui/AppTable.vue'
import AppStatusBadge from '@/components/ui/AppStatusBadge.vue'
import AppPagination from '@/components/ui/AppPagination.vue'

const notify = useNotificationStore()
const { items, meta, loading, fetch } = usePagination(loans.getLoans)
const pendingExemptions = ref([])
const pendingCoMakerRequests = ref([])
const respondingId = ref(null)

const columns = [
  { key: 'index', label: '#', class: 'text-center' },
  { key: 'loan_type', label: 'Type' },
  { key: 'amount', label: 'Amount' },
  { key: 'monthly_amortization', label: 'Monthly' },
  { key: 'term_months', label: 'Term (mo)' },
  { key: 'status', label: 'Status' },
  { key: 'created_at', label: 'Applied' },
  { key: 'actions', label: '', class: 'text-end' },
]

const paginationMeta = computed(() => ({
  current_page: meta.currentPage,
  last_page: meta.lastPage,
  total: meta.total,
  per_page: meta.perPage,
}))

function formatLoanType(type) {
  if (!type) return '-'
  return type.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
}

function formatDate(dateStr) {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleDateString('en-PH', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })
}

function formatExemptionType(type) {
  const map = {
    exceed_max_amount: 'Exceed Max Amount',
    below_minimum_pay: 'Below Minimum Pay',
    extend_term: 'Extend Term',
  }
  return map[type] ?? type
}

async function respondCoMaker(id, action) {
  respondingId.value = id
  try {
    const { data } = await loans.respondCoMaker(id, action)
    const msg = data.message ?? (action === 'approve' ? 'You agreed to be the co-maker.' : 'You declined.')
    notify.success(msg)
    pendingCoMakerRequests.value = pendingCoMakerRequests.value.filter(l => l.id !== id)
  } catch (e) {
    notify.error(e.response?.data?.message || 'Failed to respond.')
  } finally {
    respondingId.value = null
  }
}

onMounted(async () => {
  fetch(1)

  try {
    const { data } = await loans.getPendingCoMaker()
    pendingCoMakerRequests.value = data.data ?? data
  } catch (e) {
    console.error('[LoansView] co-maker fetch failed:', e)
  }

  try {
    const { data } = await exemptions.getMyRequests()
    const all = data.data ?? data
    pendingExemptions.value = all.filter(e => e.status === 'pending')
  } catch (e) {
    console.error('[LoansView] exemptions fetch failed:', e)
  }
})
</script>
