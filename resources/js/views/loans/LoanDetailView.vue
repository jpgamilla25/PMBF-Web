<template>
  <AppLayout>
    <div class="d-flex align-items-center justify-content-between mb-4">
      <div class="d-flex align-items-center">
        <router-link to="/loans" class="btn btn-outline-secondary btn-sm me-3">
          <i class="bi bi-arrow-left"></i>
        </router-link>
        <div>
          <h4 class="fw-bold mb-0 d-inline-block">Loan Details</h4>
          <AppStatusBadge v-if="loan" :status="loan.status" class="ms-2" />
          <div v-if="loan?.reference_no" class="text-muted small font-monospace mt-1">
            Ref. No. {{ loan.reference_no }}
          </div>
        </div>
      </div>
      <div class="d-flex gap-2">
        <!-- Print PDF Button -->
        <a
          v-if="loan"
          :href="pdfUrl"
          target="_blank"
          class="btn btn-outline-primary btn-sm"
        >
          <i class="bi bi-file-earmark-pdf me-1"></i>Print PDF
        </a>
        <!-- Cancel Button (only for pending loans) -->
        <button
          v-if="loan && canCancel"
          class="btn btn-outline-danger btn-sm"
          @click="showCancelModal = true"
        >
          <i class="bi bi-x-circle me-1"></i>Cancel Application
        </button>
      </div>
    </div>

    <AppLoading :loading="loading" text="Loading loan details..." />

    <template v-if="!loading && loan">
      <div class="row g-4">
        <!-- Left Column -->
        <div class="col-lg-8">
          <AppCard title="Loan Information" class="mb-4">
            <div class="row g-3">
              <div class="col-sm-6">
                <div class="text-muted small">Loan Type</div>
                <div class="fw-semibold">{{ loan.loan_type }}</div>
              </div>
              <div class="col-sm-6">
                <div class="text-muted small">Loan Amount</div>
                <div class="fw-semibold fs-5 text-primary">&#8369;{{ Number(loan.amount ?? 0).toLocaleString() }}</div>
              </div>
              <div class="col-sm-6">
                <div class="text-muted small">Interest Rate</div>
                <div class="fw-semibold">{{ loan.interest_rate }}% / month</div>
              </div>
              <div class="col-sm-6">
                <div class="text-muted small">Term</div>
                <div class="fw-semibold">{{ loan.term_months }} months</div>
              </div>
              <div class="col-sm-6">
                <div class="text-muted small">Monthly Amortization</div>
                <div class="fw-semibold">&#8369;{{ Number(loan.monthly_amortization ?? 0).toLocaleString() }}</div>
              </div>
              <div class="col-sm-6">
                <div class="text-muted small">Status</div>
                <div><AppStatusBadge :status="loan.status" /></div>
              </div>
              <div v-if="loan.purpose" class="col-12">
                <div class="text-muted small">Purpose</div>
                <div>{{ loan.purpose }}</div>
              </div>
              <div v-if="loan.co_maker" class="col-sm-6">
                <div class="text-muted small">Co-Maker</div>
                <div class="fw-semibold">{{ loan.co_maker.full_name }} ({{ loan.co_maker.employee_id }})</div>
              </div>
              <div class="col-sm-6">
                <div class="text-muted small">Date Applied</div>
                <div>{{ formatDate(loan.applied_at ?? loan.created_at) }}</div>
              </div>
            </div>
          </AppCard>

          <!-- Co-Maker Pending Banner -->
          <div v-if="loan.status === 'co_maker_pending'" class="alert alert-warning d-flex align-items-start mb-4">
            <i class="bi bi-hourglass-split fs-5 me-3 mt-1"></i>
            <div>
              <strong>Awaiting Co-Maker Consent</strong><br>
              <span class="small">
                An email has been sent to your co-maker
                <strong>{{ loan.co_maker?.full_name }}</strong>.
                The application will proceed to the approvers once they agree.
              </span>
            </div>
          </div>

          <!-- Admin Approval Pending Banner -->
          <div v-if="loan.status === 'admin_pending'" class="alert alert-warning d-flex align-items-start mb-4">
            <i class="bi bi-shield-lock fs-5 me-3 mt-1"></i>
            <div>
              <strong>Awaiting Admin Approval</strong><br>
              <span class="small">
                Your loan amount exceeds the standard maximum. An administrator must approve it before it proceeds to the regular approval chain (Receiver → Committee → Chairperson).
              </span>
            </div>
          </div>

          <!-- Co-Maker Declined Banner -->
          <div v-if="loan.status === 'co_maker_declined'" class="alert alert-danger d-flex align-items-start mb-4">
            <i class="bi bi-x-circle fs-5 me-3 mt-1"></i>
            <div>
              <strong>Co-Maker Declined</strong><br>
              <span class="small">
                Your co-maker <strong>{{ loan.co_maker?.full_name }}</strong> has declined to co-sign this loan.
                Please cancel this application and reapply with a different co-maker.
              </span>
            </div>
          </div>

          <!-- Approval Timeline -->
          <AppCard title="Approval Timeline">
            <div v-if="!approvalSteps.length" class="text-muted small py-2">
              No approval records yet.
            </div>
            <div v-else class="approval-timeline">
              <div v-for="(step, i) in approvalSteps" :key="i" class="timeline-item d-flex mb-3">
                <div class="timeline-icon me-3">
                  <div
                    class="rounded-circle d-flex align-items-center justify-content-center"
                    :class="stepBgClass(step.status)"
                    style="width: 36px; height: 36px;"
                  >
                    <i :class="stepIcon(step.status)" class="small"></i>
                  </div>
                  <div v-if="i < approvalSteps.length - 1" class="timeline-line"></div>
                </div>
                <div class="flex-grow-1">
                  <div class="fw-semibold small">{{ step.label }}</div>
                  <div class="small">
                    <AppStatusBadge :status="step.status" />
                    <span v-if="step.approver" class="text-muted ms-2">by {{ step.approver }}</span>
                  </div>
                  <div v-if="step.remarks" class="text-muted small fst-italic mt-1">"{{ step.remarks }}"</div>
                  <div v-if="step.acted_at" class="text-muted small">{{ formatDateTime(step.acted_at) }}</div>
                </div>
              </div>
            </div>
          </AppCard>
        </div>

        <!-- Right Column -->
        <div class="col-lg-4">
          <AppCard title="Payment Summary" class="mb-4">
            <div class="mb-3">
              <div class="d-flex justify-content-between small mb-1">
                <span class="text-muted">Payment Progress</span>
                <span class="fw-semibold">{{ paymentProgress }}%</span>
              </div>
              <div class="progress" style="height: 8px;">
                <div class="progress-bar bg-success" :style="{ width: paymentProgress + '%' }"></div>
              </div>
            </div>
            <ul class="list-group list-group-flush small">
              <li class="list-group-item d-flex justify-content-between px-0">
                <span class="text-muted">Total Due</span>
                <span class="fw-semibold">&#8369;{{ Number(totalDue).toLocaleString() }}</span>
              </li>
              <li class="list-group-item d-flex justify-content-between px-0">
                <span class="text-muted">Total Paid</span>
                <span class="fw-semibold text-success">&#8369;{{ Number(loan.total_paid ?? 0).toLocaleString() }}</span>
              </li>
              <li class="list-group-item d-flex justify-content-between px-0">
                <span class="text-muted">Remaining</span>
                <span class="fw-semibold text-danger">&#8369;{{ Number(remaining).toLocaleString() }}</span>
              </li>
            </ul>
          </AppCard>

          <AppCard title="Payments" :padding="false">
            <AppTable :columns="paymentColumns" :items="payments" empty-text="No payments yet.">
              <template #cell(payment_date)="{ value }">{{ formatDate(value) }}</template>
              <template #cell(amount)="{ value }">&#8369;{{ Number(value).toLocaleString() }}</template>
              <template #cell(or_number)="{ value }">{{ value || '-' }}</template>
            </AppTable>
          </AppCard>
        </div>
      </div>
    </template>

    <!-- Not Found -->
    <div v-if="!loading && !loan" class="text-center py-5">
      <i class="bi bi-exclamation-triangle fs-1 text-muted"></i>
      <p class="text-muted mt-2">Loan not found.</p>
      <router-link to="/loans" class="btn btn-primary"><i class="bi bi-arrow-left me-1"></i>Back</router-link>
    </div>

    <!-- Cancel Modal -->
    <AppModal :show="showCancelModal" title="Cancel Loan Application" @close="showCancelModal = false">
      <div class="text-center mb-4">
        <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-danger bg-opacity-10 mb-3" style="width: 80px; height: 80px;">
          <i class="bi bi-x-circle text-danger" style="font-size: 2.5rem;"></i>
        </div>
        <h5 class="fw-bold">Cancel this loan?</h5>
        <p class="text-muted small">
          Your <strong>{{ loan?.loan_type }}</strong> application for
          <strong class="text-primary">&#8369;{{ Number(loan?.amount ?? 0).toLocaleString() }}</strong>
          will be cancelled. All approvers will be notified.
        </p>
        <div class="alert alert-warning small text-start">
          <i class="bi bi-exclamation-triangle me-1"></i>
          This action cannot be undone. You can submit a new application after cancellation.
        </div>
      </div>
      <template #footer>
        <button class="btn btn-secondary" @click="showCancelModal = false">Keep Application</button>
        <button class="btn btn-danger" :disabled="cancelling" @click="handleCancel">
          <span v-if="cancelling" class="spinner-border spinner-border-sm me-1"></span>
          <i v-else class="bi bi-x-circle me-1"></i>Yes, Cancel
        </button>
      </template>
    </AppModal>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useLoading } from '@/composables/useLoading'
import { useNotificationStore } from '@/stores/notification'
import loansService from '@/services/loans'
import AppLayout from '@/components/layout/AppLayout.vue'
import AppCard from '@/components/ui/AppCard.vue'
import AppTable from '@/components/ui/AppTable.vue'
import AppModal from '@/components/ui/AppModal.vue'
import AppStatusBadge from '@/components/ui/AppStatusBadge.vue'
import AppLoading from '@/components/ui/AppLoading.vue'

const route = useRoute()
const router = useRouter()
const notify = useNotificationStore()
const { loading, withLoading } = useLoading()

const loan = ref(null)
const approvalSteps = ref([])
const payments = ref([])
const showCancelModal = ref(false)
const cancelling = ref(false)

const paymentColumns = [
  { key: 'payment_date', label: 'Date' },
  { key: 'amount', label: 'Amount' },
  { key: 'or_number', label: 'OR#' },
]

const pdfUrl = computed(() => {
  if (!loan.value) return '#'
  const token = localStorage.getItem('pmbf_token')
  return `/api/v1/loans/${loan.value.id}/pdf?token=${token}`
})

const canCancel = computed(() => {
  if (!loan.value) return false
  return ['co_maker_pending', 'admin_pending', 'pending', 'receiver_approved', 'committee_approved'].includes(loan.value.status)
})

const totalDue = computed(() => {
  if (!loan.value) return 0
  return (Number(loan.value.monthly_amortization) || 0) * (Number(loan.value.term_months) || 0)
})

const remaining = computed(() => {
  return Math.max(0, totalDue.value - Number(loan.value?.total_paid ?? 0))
})

const paymentProgress = computed(() => {
  if (totalDue.value <= 0) return 0
  return Math.min(100, Math.round((Number(loan.value?.total_paid ?? 0) / totalDue.value) * 100))
})

function stepIcon(status) {
  if (status === 'approved') return 'bi bi-check-lg'
  if (status === 'disapproved') return 'bi bi-x-lg'
  return 'bi bi-hourglass-split'
}

function stepBgClass(status) {
  if (status === 'approved') return 'bg-success bg-opacity-10 text-success'
  if (status === 'disapproved') return 'bg-danger bg-opacity-10 text-danger'
  return 'bg-warning bg-opacity-10 text-warning'
}

function formatDate(d) {
  if (!d) return '-'
  return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
}

function formatDateTime(d) {
  if (!d) return '-'
  return new Date(d).toLocaleString('en-PH', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}

async function handleCancel() {
  cancelling.value = true
  try {
    await loansService.cancelLoan(route.params.id)
    showCancelModal.value = false
    notify.success('Loan application cancelled. Approvers have been notified.')
    router.push('/loans')
  } catch (err) {
    notify.error(err.response?.data?.message || 'Failed to cancel.')
  } finally {
    cancelling.value = false
  }
}

onMounted(() => {
  withLoading(async () => {
    const { data } = await loansService.getLoan(route.params.id)
    const d = data.data ?? data
    loan.value = d
    approvalSteps.value = d.approval_progress ?? d.approvals ?? []
    payments.value = d.payments ?? []
  })
})
</script>

<style scoped>
.timeline-item { position: relative; }
.timeline-icon { position: relative; display: flex; flex-direction: column; align-items: center; }
.timeline-line { width: 2px; flex-grow: 1; min-height: 16px; background: #dee2e6; margin-top: 4px; }
</style>
