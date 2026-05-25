<template>
  <AppLayout>
    <div class="d-flex align-items-center mb-4">
      <router-link to="/loans" class="btn btn-outline-secondary btn-sm me-3">
        <i class="bi bi-arrow-left"></i>
      </router-link>
      <h4 class="fw-bold mb-0">Apply for Loan</h4>
    </div>

    <!-- User Info Bar -->
    <div v-if="authStore.user" class="alert alert-primary d-flex align-items-center mb-4">
      <i class="bi bi-person-badge fs-5 me-3"></i>
      <div class="small">
        <strong>{{ authStore.fullName }}</strong>
        <span class="mx-1">&mdash;</span>
        <span>{{ authStore.user.employment_type }}</span>
        <span v-if="authStore.user.base_pay" class="ms-3">
          Base Pay: <strong>&#8369;{{ Number(authStore.user.base_pay).toLocaleString() }}</strong>
        </span>
        <span v-if="authStore.user.take_home_pay" class="ms-3">
          Take-Home: <strong>&#8369;{{ Number(authStore.user.take_home_pay).toLocaleString() }}</strong>
        </span>
      </div>
    </div>

    <!-- Loading -->
    <AppLoading v-if="currentStep === 'loading'" loading text="Checking eligibility..." />

    <!-- Blocked — has pending loan -->
    <AppCard v-if="currentStep === 'blocked'">
      <div class="text-center py-5">
        <i class="bi bi-exclamation-triangle text-warning" style="font-size: 3.5rem;"></i>
        <h5 class="mt-3 fw-bold">Cannot Apply</h5>
        <p class="text-muted mb-4">{{ blockedMessage }}</p>
        <router-link to="/loans" class="btn btn-primary">
          <i class="bi bi-arrow-left me-1"></i>View My Loans
        </router-link>
      </div>
    </AppCard>

    <!-- ─── Step 1: Loan Application Form ───────────────── -->
    <AppCard v-if="currentStep === 'form'" title="Loan Application">
      <AppLoading :loading="typesLoading" text="Loading loan types..." />

      <form v-if="!typesLoading" @submit.prevent="handleSubmit">
        <div class="row">
          <div class="col-md-6">
            <AppInput v-model="form.loan_type" label="Loan Type" type="select" :options="loanTypeOptions" :error="errors.loan_type" required @change="onTypeChange" />

            <!-- Show max amount info for selected type -->
            <div v-if="selectedTypeInfo" class="alert alert-light small mt-2 mb-3">
              <div v-if="selectedTypeInfo.max_amount">
                <i class="bi bi-info-circle me-1"></i>
                Max loan: <strong>&#8369;{{ Number(selectedTypeInfo.max_amount).toLocaleString() }}</strong>
                <span v-if="selectedTypeInfo.monthly_salary">
                  (&#8369;{{ Number(selectedTypeInfo.monthly_salary).toLocaleString() }}/mo &#215; {{ selectedTypeInfo.contract_months }} months)
                </span>
              </div>
              <div v-if="selectedTypeInfo.requires_co_maker" class="text-warning mt-1">
                <i class="bi bi-exclamation-triangle me-1"></i>Requires a Permanent employee as co-maker
              </div>
              <div v-if="selectedTypeInfo.can_request_extension" class="text-muted mt-1">
                <i class="bi bi-arrow-up-circle me-1"></i>You can request a higher amount with approval
              </div>
            </div>
          </div>
          <div class="col-md-6">
            <AppInput v-model="form.amount" label="Loan Amount (&#8369;)" type="number" placeholder="Enter amount" :error="errors.amount" required @input="onAmountInput" />
          </div>
        </div>

        <div class="row">
          <div class="col-md-6">
            <AppInput v-model="form.term_months" label="Term (Months)" type="select" :options="computedTermOptions" :error="errors.term_months" required />
          </div>
          <div v-if="selectedTypeInfo?.requires_co_maker" class="col-md-6">
            <AppSearchSelect
              v-model="form.co_maker_id"
              label="Co-Maker (Permanent Employee) *"
              :options="coMakerOptions"
              :loading="coMakerLoading"
              :error="errors.co_maker_id"
              placeholder="Search by name or ID..."
              hint="Required: a Permanent employee must co-sign your loan"
              remote
              @search="searchCoMakers"
            />
          </div>
        </div>

        <!-- Amount warning -->
        <div v-if="amountExceedsMax" class="alert alert-warning small">
          <i class="bi bi-exclamation-triangle me-1"></i>
          Amount exceeds max of <strong>&#8369;{{ Number(selectedTypeInfo?.max_amount ?? 0).toLocaleString() }}</strong>.
          This loan will require <strong>Admin approval</strong> before proceeding to the normal approval chain.
        </div>

        <AppInput v-model="form.purpose" label="Purpose" type="textarea" placeholder="Briefly describe the purpose of this loan" :error="errors.purpose" required />

        <div class="d-flex justify-content-end">
          <router-link to="/loans" class="btn btn-outline-secondary me-2">Cancel</router-link>
          <AppButton type="button" variant="primary" @click="goToReview">
            <i class="bi bi-eye me-1"></i>Review Application
          </AppButton>
        </div>
      </form>
    </AppCard>

    <!-- ─── Step: Review & Confirm ──────────────────────── -->
    <AppCard v-if="currentStep === 'review'" title="Review & Submit Application">
      <div class="alert alert-info small mb-4">
        <i class="bi bi-info-circle me-1"></i>
        Please review your loan application details before submitting.
      </div>

      <div class="card bg-light mb-4">
        <div class="card-body">
          <div class="row g-3">
            <div class="col-sm-6">
              <div class="text-muted small">Applicant</div>
              <div class="fw-bold">{{ authStore.fullName }}</div>
              <div class="text-muted small">{{ authStore.user?.employment_type }} &mdash; {{ authStore.user?.department }}</div>
            </div>
            <div class="col-sm-6">
              <div class="text-muted small">Loan Type</div>
              <div class="fw-bold">{{ form.loan_type }}</div>
            </div>
            <div class="col-sm-6">
              <div class="text-muted small">Loan Amount</div>
              <div class="fw-bold fs-5 text-primary">&#8369;{{ Number(form.amount).toLocaleString() }}</div>
              <div v-if="amountExceedsMax" class="text-warning small">
                <i class="bi bi-exclamation-triangle me-1"></i>Exceeds max — requires Admin approval first
              </div>
            </div>
            <div class="col-sm-6">
              <div class="text-muted small">Term</div>
              <div class="fw-bold">{{ form.term_months }} months</div>
            </div>
            <div class="col-sm-6">
              <div class="text-muted small">Interest Rate</div>
              <div class="fw-bold">{{ reviewData.interest_rate }}% / month</div>
            </div>
            <div class="col-sm-6">
              <div class="text-muted small">Est. Monthly Amortization</div>
              <div class="fw-bold">&#8369;{{ Number(reviewData.monthly_amortization).toLocaleString() }}</div>
            </div>
            <div class="col-sm-6">
              <div class="text-muted small">Est. Total Payable</div>
              <div class="fw-bold">&#8369;{{ Number(reviewData.total_payable).toLocaleString() }}</div>
            </div>
            <div v-if="reviewData.co_maker_name" class="col-sm-6">
              <div class="text-muted small">Co-Maker</div>
              <div class="fw-bold">{{ reviewData.co_maker_name }}</div>
            </div>
            <div class="col-12">
              <div class="text-muted small">Purpose</div>
              <div>{{ form.purpose }}</div>
            </div>
          </div>
        </div>
      </div>

      <div class="d-flex justify-content-between">
        <button class="btn btn-outline-secondary" @click="currentStep = 'form'">
          <i class="bi bi-arrow-left me-1"></i>Edit Application
        </button>
        <AppButton variant="primary" :loading="processing" @click="handleSubmit">
          <i class="bi bi-send me-1"></i>Confirm & Submit
        </AppButton>
      </div>
    </AppCard>

    <!-- ─── Step: OTP Verification ──────────────────────── -->
    <AppCard v-if="currentStep === 'otp'" title="OTP Verification">
      <div class="alert alert-info small">
        <i class="bi bi-envelope me-1"></i>
        An OTP has been sent to your registered email. Enter it below to confirm.
      </div>

      <form @submit.prevent="handleVerifyOtp">
        <div class="row justify-content-center">
          <div class="col-md-6">
            <div class="mb-3">
              <label class="form-label fw-semibold">OTP Code</label>
              <input v-model="otpCode" type="text" class="form-control form-control-lg text-center" maxlength="6" placeholder="000000" style="letter-spacing: 10px; font-size: 1.5rem; font-weight: 700;" autofocus required />
            </div>
            <AppButton type="submit" variant="primary" block :loading="otpProcessing">
              <i class="bi bi-check-circle me-1"></i>Verify & Submit
            </AppButton>
            <div class="text-center mt-3">
              <button type="button" class="btn btn-link btn-sm text-muted" :disabled="resendLoading" @click="handleResendOtp">
                {{ resendLoading ? 'Sending...' : 'Resend OTP' }}
              </button>
              <span class="mx-2 text-muted">|</span>
              <button type="button" class="btn btn-link btn-sm text-muted" @click="currentStep = 'form'">
                <i class="bi bi-arrow-left me-1"></i>Back to form
              </button>
            </div>
          </div>
        </div>
      </form>
    </AppCard>

    <!-- ─── Step 3: Ineligible — Request Exemption ──────── -->
    <AppCard v-if="currentStep === 'ineligible'" title="Loan Eligibility Issue">
      <div class="alert alert-danger">
        <i class="bi bi-x-circle me-2"></i>
        <strong>Not Eligible:</strong> {{ ineligibleMessage }}
      </div>

      <div v-if="canRequestExemption" class="card border-warning">
        <div class="card-header bg-warning bg-opacity-10">
          <h6 class="mb-0"><i class="bi bi-send-exclamation me-2"></i>Request Special Approval</h6>
        </div>
        <div class="card-body">
          <p class="text-muted small">
            You can request an exemption for this loan. Your request will be sent to the administrator for review.
            You'll receive an email notification once it's been decided.
          </p>

          <!-- Show details based on exemption type -->
          <div class="row mb-3" v-if="exemptionType === 'below_minimum_pay'">
            <div class="col-md-6">
              <div class="small text-muted">Your pay (from FMIS)</div>
              <div class="fw-bold text-danger">&#8369;{{ Number(exemptionDetails.actual_amount || exemptionDetails.current_value || 0).toLocaleString() }}</div>
            </div>
            <div class="col-md-6">
              <div class="small text-muted">Required minimum</div>
              <div class="fw-bold">&#8369;{{ Number(exemptionDetails.required_amount || exemptionDetails.required_value || 0).toLocaleString() }}</div>
            </div>
          </div>
          <div class="row mb-3" v-if="exemptionType === 'exceed_max_amount'">
            <div class="col-md-6">
              <div class="small text-muted">Maximum allowed</div>
              <div class="fw-bold">&#8369;{{ Number(exemptionDetails.max_allowed || 0).toLocaleString() }}</div>
            </div>
            <div class="col-md-6">
              <div class="small text-muted">You requested</div>
              <div class="fw-bold text-danger">&#8369;{{ Number(exemptionDetails.requested_amount || 0).toLocaleString() }}</div>
            </div>
          </div>

          <div v-if="exemptionType === 'exceed_max_amount' && exemptionDetails.extended_max" class="alert alert-info small mb-3">
            <i class="bi bi-info-circle me-1"></i>
            With approval, you can borrow up to <strong>&#8369;{{ Number(exemptionDetails.extended_max).toLocaleString() }}</strong>
            (&#8369;{{ Number(exemptionDetails.monthly_salary || 0).toLocaleString() }}/mo &#215; 12 months)
          </div>

          <form @submit.prevent="handleExemptionRequest">
            <div v-if="exemptionType === 'exceed_max_amount'" class="alert alert-light small mb-3">
              <i class="bi bi-cash-stack me-1"></i>
              Requesting approval for: <strong>&#8369;{{ Number(exemptionForm.requested_value || 0).toLocaleString() }}</strong>
            </div>

            <AppInput v-model="exemptionForm.reason" label="Why do you need this amount? *" type="textarea" placeholder="Explain why you need this loan amount..." required :disabled="exemptionOtpSent" />

            <!-- OTP step for exemption -->
            <div v-if="exemptionOtpSent" class="mt-3">
              <div class="alert alert-info small">
                <i class="bi bi-envelope me-1"></i>
                OTP sent to your email. Enter it below to confirm your request.
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold">OTP Code</label>
                <input v-model="exemptionOtp" type="text" class="form-control form-control-lg text-center" maxlength="6" placeholder="000000" style="letter-spacing: 10px; font-size: 1.5rem; font-weight: 700;" autofocus required />
              </div>
            </div>

            <div class="d-flex justify-content-end">
              <button type="button" class="btn btn-outline-secondary me-2" @click="currentStep = 'form'; exemptionOtpSent = false; exemptionOtp = ''">
                <i class="bi bi-arrow-left me-1"></i>Go Back
              </button>
              <AppButton type="submit" variant="warning" :loading="exemptionProcessing">
                <i class="bi bi-send me-1"></i>{{ exemptionOtpSent ? 'Verify & Submit' : 'Submit Exemption Request' }}
              </AppButton>
            </div>
          </form>
        </div>
      </div>

      <div v-else class="text-center py-3">
        <router-link to="/loans" class="btn btn-outline-secondary">
          <i class="bi bi-arrow-left me-1"></i>Back to My Loans
        </router-link>
      </div>
    </AppCard>

    <!-- ─── Exemption Submitted Confirmation ────────────── -->
    <AppCard v-if="currentStep === 'exemption_sent'">
      <div class="text-center py-4">
        <i class="bi bi-envelope-check text-success" style="font-size: 4rem;"></i>
        <h5 class="mt-3">Exemption Request Submitted</h5>
        <p class="text-muted">
          Your request has been sent to the administrator. You will receive an email once it's been reviewed.
          After approval, you can come back and apply for your loan.
        </p>
        <router-link to="/loans" class="btn btn-primary">
          <i class="bi bi-arrow-left me-1"></i>Back to My Loans
        </router-link>
      </div>
    </AppCard>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useNotificationStore } from '@/stores/notification'
import { useForm } from '@/composables/useForm'
import { useLoading } from '@/composables/useLoading'
import loansService from '@/services/loans'
import exemptionsService from '@/services/exemptions'
import AppLayout from '@/components/layout/AppLayout.vue'
import AppCard from '@/components/ui/AppCard.vue'
import AppInput from '@/components/ui/AppInput.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppLoading from '@/components/ui/AppLoading.vue'
import AppSearchSelect from '@/components/ui/AppSearchSelect.vue'

const router = useRouter()
const authStore = useAuthStore()
const notify = useNotificationStore()

// ─── State ────────────────────────────────────────────────
const currentStep = ref('loading') // 'loading' | 'blocked' | 'form' | 'review' | 'otp' | 'ineligible' | 'exemption_sent'
const blockedMessage = ref('')
const loanTypes = ref({}) // raw types from API (keyed object)
const loanTypeOptions = ref([])
const minLoanAmount = ref(1000)
const maxTermMonths = ref(60)
const coMakerOptions = ref([])
const coMakerLoading = ref(false)
const { loading: typesLoading, withLoading: withTypesLoading } = useLoading()

// OTP
const otpCode = ref('')
const otpProcessing = ref(false)
const resendLoading = ref(false)
const savedLoanData = ref({})

// Ineligible / Exemption
const ineligibleMessage = ref('')
const canRequestExemption = ref(false)
const exemptionType = ref('')
const exemptionDetails = ref({})
const exemptionProcessing = ref(false)
const exemptionOtpSent = ref(false)
const exemptionOtp = ref('')
const exemptionForm = ref({ reason: '', requested_value: '' })

const defaultTerms = [3, 6, 12, 18, 24, 36, 48, 60]

/** Term options change based on selected loan type (from config) */
const computedTermOptions = computed(() => {
  const info = selectedTypeInfo.value
  const terms = info?.available_terms?.length ? info.available_terms : defaultTerms
  return terms.map(m => ({ value: String(m), label: `${m} months` }))
})

const { form, errors, processing, submit, setErrors } = useForm({
  loan_type: '',
  amount: '',
  purpose: '',
  term_months: '',
  co_maker_id: '',
})

const selectedTypeInfo = computed(() => {
  if (!form.loan_type || !loanTypes.value[form.loan_type]) return null
  return loanTypes.value[form.loan_type]
})

const amountExceedsMax = computed(() => {
  const info = selectedTypeInfo.value
  if (!info?.max_amount || !form.amount) return false
  return Number(form.amount) > info.max_amount
})

const reviewData = ref({
  interest_rate: 0,
  monthly_amortization: 0,
  total_payable: 0,
  co_maker_name: '',
})

function onTypeChange() {
  // Auto-set term to max when amount exceeds max loan
  const info = selectedTypeInfo.value
  if (!info) return
  if (Number(form.amount) > (info.max_amount ?? Infinity)) {
    const terms = info.available_terms ?? defaultTerms
    form.term_months = String(Math.max(...terms))
  }
}

// Also watch amount changes for auto-term
function onAmountInput() {
  const info = selectedTypeInfo.value
  if (!info) return
  if (Number(form.amount) > (info.max_amount ?? Infinity)) {
    const terms = info.available_terms ?? defaultTerms
    form.term_months = String(Math.max(...terms))
  }
}

function goToReview() {
  // Basic validation
  if (!form.loan_type || !form.amount || !form.term_months || !form.purpose) {
    notify.error('Please fill in all required fields.')
    return
  }
  if (Number(form.amount) < minLoanAmount.value) {
    notify.error(`Minimum loan amount is ₱${minLoanAmount.value.toLocaleString()}.`)
    return
  }
  if (selectedTypeInfo.value?.requires_co_maker && !form.co_maker_id) {
    notify.error('Please select a co-maker.')
    return
  }

  // Calculate review data
  const rate = getEstimatedRate()
  const amount = Number(form.amount)
  const months = Number(form.term_months)
  const totalInterest = amount * (rate / 100) * months
  const totalPayable = amount + totalInterest
  const monthly = totalPayable / months

  let coMakerName = ''
  if (form.co_maker_id) {
    const cm = coMakerOptions.value.find(o => String(o.value) === String(form.co_maker_id))
    coMakerName = cm?.label ?? ''
  }

  reviewData.value = {
    interest_rate: rate,
    monthly_amortization: Math.round(monthly * 100) / 100,
    total_payable: Math.round(totalPayable * 100) / 100,
    co_maker_name: coMakerName,
  }

  currentStep.value = 'review'
}

function getEstimatedRate() {
  return selectedTypeInfo.value?.interest_rate ?? 0
}

// ─── Load data ────────────────────────────────────────────
onMounted(async () => {
  // Check if user has pending or active loans first
  try {
    const { data } = await loansService.getStats()
    const stats = data.data ?? data
    if (stats.pending_loans > 0) {
      blockedMessage.value = 'You have a loan application that is still in progress (pending co-maker consent, admin approval, or approver review). Please wait until it is fully processed before applying again.'
      currentStep.value = 'blocked'
      return
    }
    // SC members can only have one active loan at a time
    if (stats.active_loans > 0 && authStore.user?.employment_type === 'SC') {
      blockedMessage.value = 'You already have an active Salary Loan. SC members may only have one active loan at a time. Please settle your current loan before applying again.'
      currentStep.value = 'blocked'
      return
    }
  } catch {
    // continue to form
  }

  // Check for pending exemption requests
  try {
    const { data } = await exemptionsService.getMyRequests()
    const all = data.data ?? data
    const hasPendingExemption = all.some(e => e.status === 'pending')
    if (hasPendingExemption) {
      blockedMessage.value = 'You have a pending special approval request. Please wait for the administrator to review it before applying.'
      currentStep.value = 'blocked'
      return
    }
  } catch {
    // continue to form
  }

  currentStep.value = 'form'
  await loadLoanTypes()
  await searchCoMakers('')
})

async function loadLoanTypes() {
  await withTypesLoading(async () => {
    const { data } = await loansService.getTypes()
    const result = data.data ?? data
    // API returns { types: {...}, otp_required: bool } or just the types object
    const raw = result.types ?? result
    loanTypes.value = raw
    loanTypeOptions.value = Object.keys(raw).map(key => ({
      value: key,
      label: raw[key].name || key,
    }))
    if (result.min_loan_amount) minLoanAmount.value = Number(result.min_loan_amount)
    if (result.max_term_months) maxTermMonths.value = Number(result.max_term_months)
  })
}

async function searchCoMakers(query) {
  coMakerLoading.value = true
  try {
    const { data } = await loansService.getCoMakers({ search: query || '' })
    coMakerOptions.value = data.data ?? data
  } catch {
    // silently fail
  } finally {
    coMakerLoading.value = false
  }
}

// ─── Submit loan ──────────────────────────────────────────
async function handleSubmit() {
  try {
    const response = await submit(async (data) => {
      return await loansService.createLoan(data)
    })
    const result = response.data.data ?? response.data

    if (result.otp_required === false && result.loan) {
      const id = result.loan.id ?? result.loan.data?.id
      notify.success('Loan application submitted successfully!')
      router.push(`/loans/${id}`)
    } else {
      savedLoanData.value = { ...form }
      notify.info('OTP sent to your email for verification.')
      currentStep.value = 'otp'
    }
  } catch (error) {
    const resp = error.response?.data
    const errData = resp?.errors

    // Eligibility error — can request exemption
    if (errData?.can_request_exemption === true) {
      ineligibleMessage.value = resp.message
      canRequestExemption.value = true
      exemptionType.value = errData.exemption_type
      exemptionDetails.value = errData.details || {}
      currentStep.value = 'ineligible'
      return
    }

    // Hard block (active loan, etc.) — show blocked screen
    if (errData && 'can_request_exemption' in errData && errData.can_request_exemption === false) {
      blockedMessage.value = resp.message
      currentStep.value = 'blocked'
      return
    }

    const msg = resp?.message || 'Failed to submit loan application.'
    notify.error(msg)
  }
}

// ─── OTP verification ─────────────────────────────────────
async function handleVerifyOtp() {
  otpProcessing.value = true
  try {
    const { data } = await loansService.verifyOtp({
      otp: otpCode.value,
      ...savedLoanData.value,
    })
    const result = data.data ?? data
    const id = result.id ?? result.data?.id
    notify.success('Loan application verified and submitted!')
    router.push(`/loans/${id}`)
  } catch (error) {
    notify.error(error.response?.data?.message || 'OTP verification failed.')
  } finally {
    otpProcessing.value = false
  }
}

async function handleResendOtp() {
  resendLoading.value = true
  try {
    await loansService.resendOtp()
    notify.success('OTP resent.')
  } catch {
    notify.error('Failed to resend OTP.')
  } finally {
    resendLoading.value = false
  }
}

// ─── Exemption request ────────────────────────────────────
async function handleExemptionRequest() {
  if (!exemptionForm.value.reason) {
    notify.error('Please provide a reason.')
    return
  }

  exemptionProcessing.value = true
  try {
    const payload = {
      type: exemptionType.value,
      loan_type: form.loan_type,
      reason: exemptionForm.value.reason,
      requested_value: exemptionForm.value.requested_value || null,
    }

    // Include OTP if we already sent it
    if (exemptionOtpSent.value && exemptionOtp.value) {
      payload.otp = exemptionOtp.value
    }

    const { data } = await exemptionsService.createRequest(payload)
    const result = data.data ?? data

    // Backend says OTP required — show OTP input
    if (result.otp_required) {
      exemptionOtpSent.value = true
      notify.info('OTP sent to your email. Enter it to confirm.')
      exemptionProcessing.value = false
      return
    }

    // Success
    currentStep.value = 'exemption_sent'
  } catch (error) {
    const msg = error.response?.data?.message || 'Failed to submit exemption request.'
    notify.error(msg)
  } finally {
    exemptionProcessing.value = false
  }
}
</script>
