<template>
  <GuestLayout>
    <!-- Auto-login loading (trusted device) -->
    <div v-if="autoLogging" class="text-center py-5">
      <div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;"></div>
      <p class="fw-semibold">Signing you in...</p>
      <p class="text-muted small">Trusted device recognized</p>
    </div>

    <template v-else>
      <h4 class="fw-bold mb-1">Welcome to PMBF</h4>
      <p class="text-muted small mb-4">Sign in with your Employee ID</p>

      <!-- Login Method Tabs -->
      <ul class="nav nav-pills nav-fill mb-4">
        <li class="nav-item">
          <button class="nav-link" :class="{ active: method === 'otp' }" @click="switchMethod('otp')">
            <i class="bi bi-envelope me-1"></i>Email OTP
          </button>
        </li>
        <li class="nav-item">
          <button class="nav-link" :class="{ active: method === 'qr' }" @click="switchMethod('qr')">
            <i class="bi bi-qr-code me-1"></i>QR Code
          </button>
        </li>
      </ul>

      <!-- ─── OTP Login ───────────────────────────────────── -->
      <div v-if="method === 'otp'">
        <!-- Step 1: Employee ID -->
        <div v-if="step === 1">
          <form @submit.prevent="requestOtp">
            <AppInput
              v-model="form.employee_id"
              label="Employee ID"
              placeholder="e.g. 15-0312"
              :error="errors.employee_id"
              required
            />
            <AppButton type="submit" variant="primary" block :loading="processing">
              <i class="bi bi-send me-1"></i>Continue
            </AppButton>
          </form>
        </div>

        <!-- Step 2: OTP + Trust Device -->
        <div v-if="step === 2">
          <div class="alert alert-info small">
            <i class="bi bi-person-check me-1"></i>
            Hello <strong>{{ employeeInfo.first_name }}</strong>!
            OTP sent to <strong>{{ employeeInfo.email }}</strong>
          </div>

          <form @submit.prevent="verifyOtp">
            <div class="mb-3">
              <label class="form-label fw-semibold">Enter OTP Code</label>
              <input
                ref="otpInput"
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

            <!-- Trust this device (like Apple) -->
            <div class="trust-device-card mb-3">
              <div class="d-flex align-items-start">
                <div class="form-check form-switch me-2 mt-1">
                  <input
                    id="trustDevice"
                    v-model="trustDevice"
                    class="form-check-input"
                    type="checkbox"
                    role="switch"
                  />
                </div>
                <label for="trustDevice" class="flex-grow-1 cursor-pointer">
                  <div class="fw-semibold small">Trust this device</div>
                  <div class="text-muted" style="font-size: 0.75rem;">
                    You won't be asked for an OTP on this browser for 30 days
                  </div>
                </label>
                <i class="bi bi-shield-check text-success ms-2" style="font-size: 1.3rem;"></i>
              </div>
            </div>

            <AppButton type="submit" variant="primary" block :loading="verifying">
              <i class="bi bi-shield-check me-1"></i>Verify & Sign In
            </AppButton>
          </form>

          <div class="text-center mt-3">
            <button class="btn btn-link text-muted small" :disabled="resendCooldown > 0" @click="resendLoginOtp">
              {{ resendCooldown > 0 ? `Resend OTP in ${resendCooldown}s` : "Didn't receive? Resend OTP" }}
            </button>
          </div>
          <div class="text-center">
            <button class="btn btn-link text-muted small" @click="step = 1">
              <i class="bi bi-arrow-left me-1"></i>Use different ID
            </button>
          </div>
        </div>
      </div>

      <!-- ─── QR Login ────────────────────────────────────── -->
      <div v-if="method === 'qr'" class="text-center">
        <div v-if="qr.loading" class="py-4">
          <div class="spinner-border text-primary mb-3"></div>
          <p class="text-muted small">Generating QR code...</p>
        </div>
        <div v-else-if="qr.expired" class="py-4">
          <i class="bi bi-clock-history text-muted" style="font-size: 3rem;"></i>
          <p class="text-muted mt-2">QR code expired</p>
          <AppButton variant="outline-primary" @click="generateQr">
            <i class="bi bi-arrow-clockwise me-1"></i>Generate New QR
          </AppButton>
        </div>
        <div v-else>
          <canvas ref="qrCanvas" class="mx-auto d-block mb-3" style="max-width: 220px;"></canvas>
          <p class="text-muted small mb-1">Scan with PMBF Mobile App</p>
          <p class="text-muted small">
            <i class="bi bi-hourglass-split me-1"></i>Expires in {{ qr.countdown }}s
          </p>
        </div>
      </div>

      <hr class="my-3">
      <div class="text-center">
        <router-link to="/register" class="text-decoration-none small">
          New employee? <strong>Register here</strong>
        </router-link>
      </div>
      <div class="text-center mt-2" v-if="false">
        <a href="/cost-breakdown" target="_blank" rel="noopener" class="text-decoration-none small text-muted">
          <i class="bi bi-bar-chart-line me-1"></i>View Project Cost Breakdown
        </a>
      </div>

      <!-- Demo -->
      <div class="alert alert-light border mt-3 mb-0 small">
        <div class="fw-semibold mb-2"><i class="bi bi-info-circle me-1"></i>Demo Accounts</div>
        <table class="table table-sm table-borderless mb-0" style="font-size: 0.8rem;">
          <tbody>
            <tr><td class="text-muted py-0">Admin</td><td class="py-0 fw-medium">15-0312 (Jayson Gamilla)</td></tr>
            <tr><td class="text-muted py-0">Member</td><td class="py-0 fw-medium">15-0313 (Art Arocena)</td></tr>
            <tr><td class="text-muted py-0">Receiver</td><td class="py-0 fw-medium">15-0314 (Ronnie Rimando)</td></tr>
            <tr><td class="text-muted py-0">Committee</td><td class="py-0 fw-medium">15-0315 (Timothy Rivera)</td></tr>
            <tr><td class="text-muted py-0">Chairperson</td><td class="py-0 fw-medium">15-0316 (PMBF Chair)</td></tr>
          </tbody>
        </table>
      </div>
    </template>
  </GuestLayout>
</template>

<script setup>
import { ref, reactive, onUnmounted, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useNotificationStore } from '@/stores/notification'
import { useForm } from '@/composables/useForm'
import { useDeviceFingerprint } from '@/composables/useDeviceFingerprint'
import auth from '@/services/auth'
import QRCode from 'qrcode'
import GuestLayout from '@/components/layout/GuestLayout.vue'
import AppInput from '@/components/ui/AppInput.vue'
import AppButton from '@/components/ui/AppButton.vue'

const router = useRouter()
const authStore = useAuthStore()
const notify = useNotificationStore()
const device = useDeviceFingerprint()

const method = ref('otp')
const step = ref(1)
const autoLogging = ref(false)
const trustDevice = ref(true) // default ON like Apple
const employeeInfo = reactive({ first_name: '', email: '' })
const resendCooldown = ref(0)
const otpInput = ref(null)
let resendTimer = null

const { form, errors, processing, submit, setErrors, clearErrors } = useForm({
  employee_id: '',
  otp: '',
})

/** Redirect admin → select-type, others → dashboard */
function goAfterLogin() {
  if (authStore.isAdmin) {
    router.push('/admin/select-type')
  } else {
    router.push('/dashboard')
  }
}

// QR
const qrCanvas = ref(null)
const qr = reactive({ loading: false, expired: false, countdown: 0, sessionToken: '' })
let qrPollTimer = null
let qrCountdownTimer = null

// ─── Step 1: Request OTP (or auto-login if trusted) ──────
async function requestOtp() {
  clearErrors()
  try {
    await submit(async () => {
      const { data } = await auth.loginRequestOtp({
        employee_id: form.employee_id,
        device_fingerprint: device.get(),
      })
      const result = data.data ?? data

      // ── Trusted device → auto-login, no OTP needed ──
      if (result.trusted && result.token) {
        loginDone.value = true
        autoLogging.value = true
        authStore.setAuth(result.user, result.token)
        notify.success('Welcome back! Trusted device.')
        goAfterLogin()
        return
      }

      // ── Not trusted → show OTP form ──
      employeeInfo.first_name = result.first_name
      employeeInfo.email = result.email
      step.value = 2
      form.otp = ''
      startResendCooldown()
      await nextTick()
      otpInput.value?.focus()
    })
  } catch (error) {
    if (loginDone.value) return
    const msg = error.response?.data?.message || 'Employee ID not found.'
    if (error.response?.data?.errors) {
      setErrors(error.response.data.errors)
    } else {
      notify.error(msg)
    }
  }
}

// ─── Step 2: Verify OTP + optional trust ─────────────────
const verifying = ref(false)
const loginDone = ref(false)

async function verifyOtp() {
  if (verifying.value || loginDone.value) return
  verifying.value = true
  try {
    const { data } = await auth.loginVerifyOtp({
      employee_id: form.employee_id,
      otp: form.otp,
      device_fingerprint: device.get(),
      trust_device: trustDevice.value,
    })
    const result = data.data ?? data
    loginDone.value = true
    authStore.setAuth(result.user, result.token)

    if (result.trusted_until) {
      notify.success('Logged in! This device is now trusted for 30 days.')
    } else {
      notify.success('Login successful!')
    }
    goAfterLogin()
  } catch (error) {
    if (loginDone.value) return // already succeeded, ignore stale errors
    verifying.value = false
    const msg = error.response?.data?.message || 'Invalid or expired OTP.'
    notify.error(msg)
  }
}

// ─── Resend ──────────────────────────────────────────────
async function resendLoginOtp() {
  if (resendCooldown.value > 0) return
  try {
    await auth.loginRequestOtp({
      employee_id: form.employee_id,
      device_fingerprint: '', // force OTP by sending empty fingerprint
    })
    notify.success('OTP resent to your email.')
    startResendCooldown()
  } catch {
    notify.error('Failed to resend OTP.')
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

// ─── QR Login ────────────────────────────────────────────
function switchMethod(m) {
  method.value = m
  if (m === 'qr') generateQr()
  else stopQrPolling()
}

async function generateQr() {
  qr.loading = true
  qr.expired = false
  stopQrPolling()
  try {
    const { data } = await auth.qrGenerate()
    const result = data.data ?? data
    qr.sessionToken = result.session_token
    qr.countdown = 300
    qr.loading = false
    await nextTick()
    if (qrCanvas.value) {
      QRCode.toCanvas(qrCanvas.value, result.qr_data, {
        width: 220, margin: 2,
        color: { dark: '#1e40af', light: '#ffffff' },
      })
    }
    startQrPolling()
    startQrCountdown()
  } catch {
    qr.loading = false
    notify.error('Failed to generate QR code.')
  }
}

function startQrPolling() {
  qrPollTimer = setInterval(async () => {
    try {
      const { data } = await auth.qrStatus(qr.sessionToken)
      const result = data.data ?? data
      if (result.status === 'approved' && result.token) {
        stopQrPolling()
        authStore.setAuth(result.user, result.token)
        notify.success('QR login approved!')
        goAfterLogin()
      } else if (result.status === 'expired') {
        stopQrPolling()
        qr.expired = true
      }
    } catch { /* ignore */ }
  }, 2000)
}

function startQrCountdown() {
  qrCountdownTimer = setInterval(() => {
    qr.countdown--
    if (qr.countdown <= 0) { stopQrPolling(); qr.expired = true }
  }, 1000)
}

function stopQrPolling() {
  clearInterval(qrPollTimer)
  clearInterval(qrCountdownTimer)
}

onUnmounted(() => {
  clearInterval(resendTimer)
  stopQrPolling()
})
</script>

<style scoped>
.nav-pills .nav-link { color: #6b7280; font-weight: 600; font-size: 0.9rem; border-radius: 8px; }
.nav-pills .nav-link.active { background: #1e40af; color: #fff; }
.trust-device-card {
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  border-radius: 10px;
  padding: 12px 14px;
}
.cursor-pointer { cursor: pointer; }
</style>
