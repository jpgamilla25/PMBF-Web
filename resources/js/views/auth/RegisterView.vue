<template>
  <GuestLayout>
    <h4 class="fw-bold mb-1">Create Account</h4>
    <p class="text-muted small mb-4">Register with your Employee ID</p>

    <!-- Step Indicators (2 steps only) -->
    <div class="d-flex align-items-center justify-content-center mb-4">
      <div class="d-flex align-items-center" v-for="s in 2" :key="s">
        <div
          class="step-circle d-flex align-items-center justify-content-center rounded-circle"
          :class="{
            'bg-primary text-white': currentStep >= s,
            'bg-light text-muted border': currentStep < s,
          }"
        >
          <i v-if="currentStep > s" class="bi bi-check"></i>
          <span v-else>{{ s }}</span>
        </div>
        <div
          v-if="s < 2"
          class="step-line mx-2"
          :class="{ 'bg-primary': currentStep > s, 'bg-light': currentStep <= s }"
        ></div>
      </div>
    </div>

    <!-- Step 1: Employee ID Lookup -->
    <div v-if="currentStep === 1">
      <p class="text-center text-muted small mb-3">Enter your Employee ID to get started</p>

      <form @submit.prevent="handleLookup">
        <AppInput
          v-model="form.employee_id"
          label="Employee ID"
          placeholder="e.g. 20-0501"
          :error="errors.employee_id"
          required
        />
        <AppButton type="submit" variant="primary" block :loading="processing">
          <i class="bi bi-search me-1"></i>Verify Employee ID
        </AppButton>
      </form>
    </div>

    <!-- Step 2: Confirm Info + OTP -->
    <div v-if="currentStep === 2">
      <!-- Employee Preview -->
      <div class="card bg-light mb-3">
        <div class="card-body py-2 small">
          <div class="row">
            <div class="col-5 text-muted">Name:</div>
            <div class="col-7 fw-semibold">{{ employee.first_name }} {{ employee.last_name }}</div>
            <div class="col-5 text-muted">Type:</div>
            <div class="col-7"><span class="badge bg-primary">{{ employee.employment_type }}</span></div>
            <div class="col-5 text-muted">Department:</div>
            <div class="col-7">{{ employee.department }}</div>
            <div class="col-5 text-muted">Position:</div>
            <div class="col-7">{{ employee.position }}</div>
          </div>
        </div>
      </div>

      <div class="alert alert-info small">
        <i class="bi bi-envelope me-1"></i>
        OTP sent to <strong>{{ employee.email }}</strong>
      </div>

      <!-- ── Wrong-email path ─────────────────────────────── -->
      <template v-if="showIdentity">
        <!-- Nothing to check the answers against without a mobile on file. -->
        <div v-if="!employee.can_verify_identity" class="alert alert-warning small">
          <div class="fw-semibold mb-1">
            <i class="bi bi-telephone-x me-1"></i>We can't verify you automatically
          </div>
          There is no mobile number on file in HRIS for your Employee ID, so we have no way to
          confirm it's you. Please contact the <strong>HR / Human Resources Division</strong> to
          have your email address (or mobile number) corrected in HRIS, then register again.
        </div>

        <template v-else>
          <div class="alert alert-secondary small">
            <i class="bi bi-shield-check me-1"></i>
            Answer these to confirm it's you, then give us an email you can actually open.
          </div>

          <form @submit.prevent="handleVerifyIdentity">
            <AppInput
              v-model="identity.middle_name"
              label="Middle Name"
              placeholder="As recorded in HRIS"
              :error="errors.middle_name"
              required
            />

            <div class="mb-3">
              <label class="form-label fw-semibold small">
                Last 4 digits of your mobile number
                <span v-if="employee.mobile_hint" class="text-muted fw-normal">
                  ({{ employee.mobile_hint }})
                </span>
              </label>
              <input
                v-model="identity.mobile_last4"
                type="text"
                inputmode="numeric"
                maxlength="4"
                class="form-control text-center"
                :class="{ 'is-invalid': errors.mobile_last4 }"
                placeholder="0000"
                style="letter-spacing: 8px; font-weight: 700;"
                required
              />
              <div v-if="errors.mobile_last4" class="invalid-feedback">{{ errors.mobile_last4 }}</div>
            </div>

            <AppInput
              v-model="identity.email"
              label="Your Email Address"
              type="email"
              placeholder="you@example.com"
              :error="errors.email"
              hint="We'll send your verification code here and use it for your account."
              required
            />

            <AppButton type="submit" variant="primary" block :loading="processing">
              <i class="bi bi-shield-check me-1"></i>Verify &amp; Send Code
            </AppButton>
          </form>
        </template>

        <div class="text-center mt-3">
          <button class="btn btn-link text-muted small" @click="showIdentity = false">
            <i class="bi bi-arrow-left me-1"></i>Back
          </button>
        </div>
      </template>

      <form v-else @submit.prevent="handleComplete">
        <div class="mb-3">
          <label class="form-label fw-semibold">Enter OTP Code</label>
          <input
            v-model="form.otp"
            type="text"
            class="form-control form-control-lg text-center"
            :class="{ 'is-invalid': errors.otp }"
            maxlength="6"
            placeholder="000000"
            style="letter-spacing: 10px; font-size: 1.5rem; font-weight: 700;"
            autofocus
            required
          />
          <div v-if="errors.otp" class="invalid-feedback">{{ errors.otp }}</div>
        </div>

        <AppButton type="submit" variant="primary" block :loading="processing">
          <i class="bi bi-check-circle me-1"></i>Verify & Create Account
        </AppButton>
      </form>

      <div v-if="!showIdentity" class="text-center mt-3">
        <button
          class="btn btn-link text-muted small"
          :disabled="resendCooldown > 0"
          @click="handleResendOtp"
        >
          {{ resendCooldown > 0 ? `Resend OTP in ${resendCooldown}s` : "Didn't receive? Resend OTP" }}
        </button>
      </div>

      <!-- The HRIS email is wrong for some members; this is their way in. -->
      <div v-if="!showIdentity && !identityVerified" class="text-center">
        <button class="btn btn-link small" @click="openIdentity">
          <i class="bi bi-envelope-exclamation me-1"></i>This isn't my email / I can't open it
        </button>
      </div>

      <div v-if="!showIdentity" class="text-center">
        <button class="btn btn-link text-muted small" @click="currentStep = 1">
          <i class="bi bi-arrow-left me-1"></i>Use different ID
        </button>
      </div>
    </div>

    <hr class="my-3">
    <div class="text-center">
      <router-link to="/login" class="text-decoration-none small">
        Already registered? <strong>Sign in here</strong>
      </router-link>
    </div>
  </GuestLayout>
</template>

<script setup>
import { ref, reactive, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useNotificationStore } from '@/stores/notification'
import { useForm } from '@/composables/useForm'
import { useDeviceFingerprint } from '@/composables/useDeviceFingerprint'
import auth from '@/services/auth'
import GuestLayout from '@/components/layout/GuestLayout.vue'
import AppInput from '@/components/ui/AppInput.vue'
import AppButton from '@/components/ui/AppButton.vue'

const router = useRouter()
const authStore = useAuthStore()
const notify = useNotificationStore()
const device = useDeviceFingerprint()

const currentStep = ref(1)
const employee = reactive({
  first_name: '', last_name: '', email: '', employment_type: '', department: '', position: '',
  can_verify_identity: false, mobile_hint: '',
})
const resendCooldown = ref(0)
let resendTimer = null

// ── Wrong-email path ──────────────────────────────────────
// A member whose HRIS email is wrong proves who they are against other HRIS
// fields, then nominates an email they can actually open.
const showIdentity = ref(false)
const identityVerified = ref(false)
const identityToken = ref(null)
const identity = reactive({ middle_name: '', mobile_last4: '', email: '' })

function openIdentity() {
  clearErrors()
  showIdentity.value = true
}

async function handleVerifyIdentity() {
  clearErrors()
  try {
    await submit(async () => {
      const { data } = await auth.registerVerifyIdentity({
        employee_id: form.employee_id,
        middle_name: identity.middle_name,
        mobile_last4: identity.mobile_last4,
        email: identity.email,
      })
      const result = data.data ?? data

      identityToken.value = result.identity_token
      identityVerified.value = true
      employee.email = result.email      // the OTP now goes to their address
      showIdentity.value = false
      form.otp = ''

      notify.success('Verified. Check your email for the code.')
      startResendCooldown()
    })
  } catch (error) {
    const msg = error.response?.data?.message || 'We could not verify those details.'
    if (error.response?.data?.errors && typeof error.response.data.errors === 'object'
        && !error.response.data.errors.reason) {
      setErrors(error.response.data.errors)
    }
    notify.error(msg)
  }
}

const { form, errors, processing, submit, setErrors, clearErrors } = useForm({
  employee_id: '',
  otp: '',
})

async function handleLookup() {
  clearErrors()
  try {
    await submit(async () => {
      const { data } = await auth.registerLookup({ employee_id: form.employee_id })
      const info = data.data ?? data
      Object.assign(employee, info)

      // A different Employee ID means any earlier identity check is void.
      identityVerified.value = false
      identityToken.value = null
      showIdentity.value = false
      identity.middle_name = identity.mobile_last4 = identity.email = ''

      notify.success('Employee found! OTP sent to your email.')
      currentStep.value = 2
      form.otp = ''
      startResendCooldown()
    })
  } catch (error) {
    const msg = error.response?.data?.message || 'Employee not found.'
    if (error.response?.data?.errors) {
      setErrors(error.response.data.errors)
    } else {
      notify.error(msg)
    }
  }
}

async function handleComplete() {
  clearErrors()
  try {
    await submit(async () => {
      const { data } = await auth.registerComplete({
        employee_id: form.employee_id,
        otp: form.otp,
        // Present only when the member went through the identity check —
        // it's what authorises registering against their nominated email.
        identity_token: identityToken.value ?? undefined,
        // Registering proves ownership of the account's email, so the backend
        // trusts this device — which is what makes a PIN usable right away.
        device_fingerprint: device.get(),
      })
      const result = data.data ?? data
      authStore.setAuth(result.user, result.token)
      notify.success('Registration complete! Welcome to PMBF.')

      // A brand-new account never has a PIN — this is the first login, so
      // send them straight to PIN setup rather than the dashboard.
      router.push('/pin-setup')
    })
  } catch (error) {
    const msg = error.response?.data?.message || 'Invalid OTP or registration failed.'
    if (error.response?.data?.errors) {
      setErrors(error.response.data.errors)
    } else {
      notify.error(msg)
    }
  }
}

async function handleResendOtp() {
  if (resendCooldown.value > 0) return

  // Identity-verified members get the code at the address they nominated,
  // not the wrong one HRIS holds.
  if (identityVerified.value && identity.email) {
    try {
      await auth.registerResendOtp({ email: identity.email, type: 'registration' })
      notify.success('Code resent to your email.')
      startResendCooldown()
    } catch {
      notify.error('Failed to resend the code.')
    }
    return
  }

  try {
    await auth.registerResendOtp({ email: employee.email, type: 'registration' })
    notify.success('OTP resent to your email.')
    startResendCooldown()
  } catch {
    // The masked email won't work for resend, so re-call lookup
    try {
      await auth.registerLookup({ employee_id: form.employee_id })
      notify.success('OTP resent to your email.')
      startResendCooldown()
    } catch {
      notify.error('Failed to resend OTP.')
    }
  }
}

function startResendCooldown() {
  resendCooldown.value = 60
  clearInterval(resendTimer)
  resendTimer = setInterval(() => {
    resendCooldown.value--
    if (resendCooldown.value <= 0) clearInterval(resendTimer)
  }, 1000)
}

onUnmounted(() => clearInterval(resendTimer))
</script>

<style scoped>
.step-circle {
  width: 36px;
  height: 36px;
  min-width: 36px;
  font-size: 0.875rem;
  font-weight: 600;
  transition: all 0.2s ease;
}
.step-line {
  width: 48px;
  height: 3px;
  border-radius: 2px;
  transition: background-color 0.2s ease;
}
</style>
