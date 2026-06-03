<template>
  <AppLayout>
    <div class="d-flex align-items-center mb-4">
      <router-link to="/approvals" class="btn btn-outline-secondary btn-sm me-3">
        <i class="bi bi-arrow-left me-1"></i>Back
      </router-link>
      <h4 class="fw-bold mb-0">Loan Approval Review</h4>
    </div>

    <AppLoading :loading="loading" text="Loading loan details..." />

    <template v-if="!loading && loan">
      <div class="row g-4">
        <!-- Left Column -->
        <div class="col-lg-8">
          <!-- Applicant Information -->
          <AppCard title="Applicant Information" class="mb-4">
            <div class="row">
              <div class="col-sm-6 mb-3">
                <div class="text-muted small">Full Name</div>
                <div class="fw-semibold">{{ loan.user?.full_name ?? '-' }}</div>
              </div>
              <div class="col-sm-6 mb-3">
                <div class="text-muted small">Employee ID</div>
                <div class="fw-semibold">{{ loan.user?.employee_id ?? '-' }}</div>
              </div>
              <div class="col-sm-6 mb-3">
                <div class="text-muted small">Employment Type</div>
                <div>
                  <AppBadge
                    :variant="loan.user?.employment_type === 'permanent' ? 'success' : 'info'"
                    :text="loan.user?.employment_type ?? '-'"
                  />
                </div>
              </div>
              <div class="col-sm-6 mb-3">
                <div class="text-muted small">Department</div>
                <div class="fw-semibold">{{ loan.user?.department ?? '-' }}</div>
              </div>
              <div class="col-sm-6 mb-3">
                <div class="text-muted small">Base Pay</div>
                <div class="fw-semibold">&#8369;{{ Number(loan.user?.base_pay ?? 0).toLocaleString() }}</div>
              </div>
              <div class="col-sm-6 mb-3">
                <div class="text-muted small">Take-Home Pay</div>
                <div class="fw-semibold">&#8369;{{ Number(loan.user?.take_home_pay ?? 0).toLocaleString() }}</div>
              </div>
            </div>
          </AppCard>

          <!-- Loan Details -->
          <AppCard title="Loan Details" class="mb-4">
            <div class="row">
              <div class="col-sm-6 mb-3">
                <div class="text-muted small">Loan Type</div>
                <div class="fw-semibold">{{ formatLoanType(loan.loan_type) }}</div>
              </div>
              <div class="col-sm-6 mb-3">
                <div class="text-muted small">Amount Requested</div>
                <div class="fw-semibold fs-5 text-primary">&#8369;{{ Number(loan.amount ?? 0).toLocaleString() }}</div>
              </div>
              <div class="col-sm-6 mb-3">
                <div class="text-muted small">Term (Months)</div>
                <div class="fw-semibold">{{ loan.term_months ?? '-' }}</div>
              </div>
              <div class="col-sm-6 mb-3">
                <div class="text-muted small">Interest Rate</div>
                <div class="fw-semibold">{{ loan.interest_rate ?? '-' }}%</div>
              </div>
              <div class="col-sm-6 mb-3">
                <div class="text-muted small">Monthly Amortization</div>
                <div class="fw-semibold">&#8369;{{ Number(loan.monthly_amortization ?? 0).toLocaleString() }}</div>
              </div>
              <div class="col-sm-6 mb-3">
                <div class="text-muted small">Status</div>
                <div><AppStatusBadge :status="loan.status" /></div>
              </div>
              <div v-if="loan.purpose" class="col-12 mb-3">
                <div class="text-muted small">Purpose</div>
                <div class="fw-semibold">{{ loan.purpose }}</div>
              </div>
              <div v-if="loan.co_maker" class="col-12 mb-3">
                <div class="text-muted small">Co-Maker</div>
                <div class="fw-semibold">{{ loan.co_maker?.full_name ?? loan.co_maker_name ?? '-' }}</div>
              </div>
            </div>
          </AppCard>

          <!-- Approval Timeline -->
          <AppCard title="Approval Timeline">
            <div v-if="loan.approval_progress && loan.approval_progress.length > 0">
              <div
                v-for="(step, idx) in loan.approval_progress"
                :key="idx"
                class="d-flex align-items-start mb-3"
              >
                <div class="me-3 mt-1">
                  <div
                    class="rounded-circle d-flex align-items-center justify-content-center"
                    :class="stepIconClass(step)"
                    style="width: 32px; height: 32px;"
                  >
                    <i :class="stepIcon(step)"></i>
                  </div>
                </div>
                <div class="flex-grow-1">
                  <div class="d-flex justify-content-between align-items-start">
                    <div>
                      <div class="fw-semibold">{{ step.label ?? formatStepRole(step.level) }}</div>
                      <div class="small text-muted">{{ step.approver ?? '-' }}</div>
                    </div>
                    <div class="text-end">
                      <AppStatusBadge :status="step.status ?? 'pending'" />
                      <div v-if="step.acted_at" class="small text-muted mt-1">{{ formatDate(step.acted_at) }}</div>
                    </div>
                  </div>
                  <div v-if="step.remarks" class="small text-muted mt-1 fst-italic">
                    "{{ step.remarks }}"
                  </div>
                </div>
              </div>
            </div>
            <div v-else class="text-center text-muted py-3">
              <i class="bi bi-clock-history fs-3 d-block mb-2"></i>
              No approval history yet.
            </div>
          </AppCard>
        </div>

        <!-- Right Column -->
        <div class="col-lg-4">
          <!-- Action Panel -->
          <template v-if="loan.can_act">
            <AppCard title="Action Panel" class="mb-4">
              <div class="text-center mb-3">
                <div class="text-muted small mb-2">Next step: <strong>{{ formatStepRole(loan.next_approval_level) }}</strong></div>
                <div class="fs-4 fw-bold text-primary mb-1">&#8369;{{ Number(loan.amount ?? 0).toLocaleString() }}</div>
                <div class="text-muted small">{{ loan.user?.full_name }} &mdash; {{ loan.loan_type }}</div>
              </div>
              <div class="d-grid gap-2">
                <AppButton variant="success" size="lg" @click="showApproveModal = true">
                  <i class="bi bi-check-circle me-1"></i>Approve Loan
                </AppButton>
                <AppButton variant="outline-danger" @click="showDisapproveModal = true">
                  <i class="bi bi-x-circle me-1"></i>Disapprove
                </AppButton>
              </div>
            </AppCard>
          </template>

          <!-- Release Panel -->
          <template v-if="loan.can_release">
            <AppCard title="Release Loan" class="mb-4">
              <div class="text-center mb-3">
                <i class="bi bi-check-all text-success" style="font-size: 2.5rem;"></i>
                <p class="fw-semibold mt-2 mb-1">All Approvals Complete</p>
                <p class="text-muted small">This loan is ready for release.</p>
              </div>
              <AppButton variant="primary" size="lg" block @click="showReleaseModal = true">
                <i class="bi bi-cash-stack me-1"></i>Release Loan
              </AppButton>
            </AppCard>
          </template>

          <!-- No action -->
          <template v-if="!loan.can_act && !loan.can_release">
            <AppCard title="Action Panel">
              <div class="text-center text-muted py-3">
                <i class="bi bi-info-circle fs-3 d-block mb-2"></i>
                <p class="mb-0">No actions available at this time.</p>
              </div>
            </AppCard>
          </template>
        </div>
      </div>

      <!-- ═══ Approve Modal ═══ -->
      <AppModal :show="showApproveModal" title="Approve Loan" @close="showApproveModal = false">
        <div class="text-center mb-4">
          <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-success bg-opacity-10 mb-3" style="width: 80px; height: 80px;">
            <i class="bi bi-check-circle text-success" style="font-size: 2.5rem;"></i>
          </div>
          <h5 class="fw-bold">Approve this loan?</h5>
          <p class="text-muted small mb-0">
            You are approving <strong>{{ loan.user?.full_name }}</strong>'s
            <strong>{{ loan.loan_type }}</strong> for
            <strong class="text-primary">&#8369;{{ Number(loan.amount ?? 0).toLocaleString() }}</strong>
          </p>
        </div>
        <AppInput
          v-model="approveForm.form.remarks"
          type="textarea"
          label="Remarks (optional)"
          placeholder="Add any notes or conditions..."
          :error="approveForm.errors.remarks"
        />
        <template #footer>
          <button class="btn btn-secondary" @click="showApproveModal = false">Cancel</button>
          <AppButton variant="success" :loading="approveForm.processing.value" @click="handleApprove">
            <i class="bi bi-check-lg me-1"></i>Yes, Approve
          </AppButton>
        </template>
      </AppModal>

      <!-- ═══ Disapprove Modal ═══ -->
      <AppModal :show="showDisapproveModal" title="Disapprove Loan" @close="showDisapproveModal = false">
        <div class="text-center mb-4">
          <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-danger bg-opacity-10 mb-3" style="width: 80px; height: 80px;">
            <i class="bi bi-x-circle text-danger" style="font-size: 2.5rem;"></i>
          </div>
          <h5 class="fw-bold">Disapprove this loan?</h5>
          <p class="text-muted small mb-0">
            This will reject <strong>{{ loan.user?.full_name }}</strong>'s application.
            The applicant will be notified via email.
          </p>
        </div>
        <AppInput
          v-model="disapproveForm.form.remarks"
          type="textarea"
          label="Reason for disapproval *"
          placeholder="Please provide a clear reason..."
          required
          :error="disapproveForm.errors.remarks"
        />
        <template #footer>
          <button class="btn btn-secondary" @click="showDisapproveModal = false">Cancel</button>
          <AppButton variant="danger" :loading="disapproveForm.processing.value" @click="handleDisapprove">
            <i class="bi bi-x-lg me-1"></i>Yes, Disapprove
          </AppButton>
        </template>
      </AppModal>

      <!-- ═══ Release Modal ═══ -->
      <AppModal :show="showReleaseModal" title="Release Loan" @close="showReleaseModal = false">
        <div class="text-center mb-4">
          <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary bg-opacity-10 mb-3" style="width: 80px; height: 80px;">
            <i class="bi bi-cash-stack text-primary" style="font-size: 2.5rem;"></i>
          </div>
          <h5 class="fw-bold">Release this loan?</h5>
          <p class="text-muted small mb-0">
            <strong class="text-primary">&#8369;{{ Number(loan.amount ?? 0).toLocaleString() }}</strong> will be released to
            <strong>{{ loan.user?.full_name }}</strong>.
            This action cannot be undone.
          </p>
          <div class="alert alert-light border small mt-3 text-start">
            <div class="d-flex justify-content-between"><span>Monthly Amortization:</span><strong>&#8369;{{ Number(loan.monthly_amortization ?? 0).toLocaleString() }}</strong></div>
            <div class="d-flex justify-content-between"><span>Term:</span><strong>{{ loan.term_months }} months</strong></div>
          </div>
        </div>
        <template #footer>
          <button class="btn btn-secondary" @click="showReleaseModal = false">Cancel</button>
          <AppButton variant="primary" :loading="releasing" @click="handleRelease">
            <i class="bi bi-cash-stack me-1"></i>Yes, Release Loan
          </AppButton>
        </template>
      </AppModal>
    </template>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useLoading } from '@/composables/useLoading'
import { useForm } from '@/composables/useForm'
import { useNotificationStore } from '@/stores/notification'
import { useAuthStore } from '@/stores/auth'
import approvalsService from '@/services/approvals'
import AppLayout from '@/components/layout/AppLayout.vue'
import AppCard from '@/components/ui/AppCard.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppInput from '@/components/ui/AppInput.vue'
import AppModal from '@/components/ui/AppModal.vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import AppStatusBadge from '@/components/ui/AppStatusBadge.vue'
import AppLoading from '@/components/ui/AppLoading.vue'

const route = useRoute()
const router = useRouter()
const notification = useNotificationStore()
const authStore = useAuthStore()
const { loading, withLoading } = useLoading()

const loan = ref(null)
const releasing = ref(false)
const showApproveModal = ref(false)
const showDisapproveModal = ref(false)
const showReleaseModal = ref(false)

const approveForm = useForm({ remarks: '' })
const disapproveForm = useForm({ remarks: '' })

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
    hour: '2-digit',
    minute: '2-digit',
  })
}

function formatStepRole(role) {
  if (!role) return '-'
  return role.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
}

function stepIcon(step) {
  const action = step.action ?? step.status
  if (action === 'approved') return 'bi bi-check-lg text-white'
  if (action === 'disapproved') return 'bi bi-x-lg text-white'
  return 'bi bi-hourglass-split text-white'
}

function stepIconClass(step) {
  const action = step.action ?? step.status
  if (action === 'approved') return 'bg-success'
  if (action === 'disapproved') return 'bg-danger'
  return 'bg-warning'
}

async function fetchLoan() {
  await withLoading(async () => {
    const response = await approvalsService.getApproval(route.params.id)
    loan.value = response.data.data ?? response.data
  })
}

async function handleApprove() {
  try {
    await approveForm.submit(async (data) => {
      await approvalsService.approve(route.params.id, data)
    })
    showApproveModal.value = false
    notification.success('Loan approved successfully.')
    authStore.fetchPendingApprovalCount()
    await fetchLoan()
  } catch {
    notification.error('Failed to approve loan.')
  }
}

async function handleDisapprove() {
  if (!disapproveForm.form.remarks) {
    disapproveForm.setErrors({ remarks: 'Remarks are required.' })
    return
  }

  try {
    await disapproveForm.submit(async (data) => {
      await approvalsService.disapprove(route.params.id, data)
    })
    showDisapproveModal.value = false
    notification.success('Loan disapproved.')
    authStore.fetchPendingApprovalCount()
    await fetchLoan()
  } catch {
    notification.error('Failed to disapprove loan.')
  }
}

async function handleRelease() {
  releasing.value = true
  try {
    await approvalsService.release(route.params.id)
    showReleaseModal.value = false
    notification.success('Loan released successfully.')
    authStore.fetchPendingApprovalCount()
    await fetchLoan()
  } catch {
    notification.error('Failed to release loan.')
  } finally {
    releasing.value = false
  }
}

onMounted(fetchLoan)
</script>
