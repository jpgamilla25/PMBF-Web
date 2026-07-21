<template>
  <GuestLayout>
    <!-- ─── Checking whether this device can use a PIN ───────── -->
    <div v-if="checking" class="text-center py-5">
      <div class="spinner-border text-primary mb-3"></div>
      <p class="text-muted small mb-0">Preparing sign-in...</p>
    </div>

    <!-- ─── Forgot PIN: OTP + new PIN ────────────────────────── -->
    <template v-else-if="mode === 'reset'">
      <div class="text-center mb-4">
        <div class="pin-avatar mb-3"><i class="bi bi-key"></i></div>
        <h5 class="fw-bold mb-1">Reset your PIN</h5>
        <p class="text-muted small mb-0">
          We sent a 6-digit code to <strong>{{ resetEmail }}</strong>
        </p>
      </div>

      <form @submit.prevent="confirmReset">
        <div class="mb-3">
          <label class="form-label fw-semibold small">Email code</label>
          <input
            v-model="reset.otp"
            type="text"
            inputmode="numeric"
            maxlength="6"
            class="form-control form-control-lg text-center"
            placeholder="000000"
            style="letter-spacing: 10px; font-weight: 700;"
          />
        </div>

        <label class="form-label fw-semibold small">New 4-digit PIN</label>
        <PinInput v-model="reset.pin" :autofocus="false" class="mb-3" />

        <label class="form-label fw-semibold small">Confirm new PIN</label>
        <PinInput v-model="reset.pin_confirmation" :autofocus="false" class="mb-4" />

        <AppButton
          type="submit"
          variant="primary"
          block
          :loading="resetting"
          :disabled="!resetReady"
        >
          <i class="bi bi-check2-circle me-1"></i>Set new PIN &amp; sign in
        </AppButton>
      </form>

      <div class="text-center mt-3">
        <button class="btn btn-link text-muted small" @click="mode = 'pin'">
          <i class="bi bi-arrow-left me-1"></i>Back
        </button>
      </div>
    </template>

    <!-- ─── PIN entry ────────────────────────────────────────── -->
    <template v-else>
      <div class="text-center mb-4">
        <div class="pin-avatar mb-3">{{ initials }}</div>
        <h5 class="fw-bold mb-1">Welcome back, {{ hint.first_name }}</h5>
        <p class="text-muted small mb-0">Enter your PIN to continue</p>
      </div>

      <div v-if="lockedUntil" class="alert alert-danger small">
        <i class="bi bi-lock-fill me-1"></i>
        PIN login is locked after too many wrong attempts. Reset your PIN or sign
        in with an email OTP.
      </div>

      <PinInput
        v-else
        ref="pinField"
        v-model="pin"
        :error="pinError"
        :disabled="verifying"
        :hint="verifying ? 'Signing you in...' : ''"
        @complete="submitPin"
      />

      <div class="d-grid gap-2 mt-4">
        <button class="btn btn-link text-decoration-none small" @click="startReset">
          Forgot PIN?
        </button>
        <button class="btn btn-link text-muted text-decoration-none small" @click="useAnotherWay">
          Sign in with Employee ID instead
        </button>
      </div>
    </template>
  </GuestLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useNotificationStore } from '@/stores/notification'
import { useDeviceFingerprint } from '@/composables/useDeviceFingerprint'
import auth from '@/services/auth'
import GuestLayout from '@/components/layout/GuestLayout.vue'
import PinInput from '@/components/ui/PinInput.vue'
import AppButton from '@/components/ui/AppButton.vue'

const router = useRouter()
const authStore = useAuthStore()
const notify = useNotificationStore()
const device = useDeviceFingerprint()

const checking = ref(true)
const mode = ref('pin')
const pin = ref('')
const pinError = ref('')
const verifying = ref(false)
const lockedUntil = ref(null)
const pinField = ref(null)

const hint = reactive({ employee_id: '', first_name: '', email: '' })

const reset = reactive({ otp: '', pin: '', pin_confirmation: '' })
const resetEmail = ref('')
const resetting = ref(false)

const initials = computed(() => (hint.first_name || '?').charAt(0).toUpperCase())

const resetReady = computed(() =>
  reset.otp.length === 6 && reset.pin.length === 4 && reset.pin_confirmation.length === 4
)

/** No usable PIN on this device → fall back to the Employee ID flow. */
function toOtpLogin() {
  router.replace({ name: 'login', query: { otp: '1' } })
}

function useAnotherWay() {
  authStore.clearPinHint()
  toOtpLogin()
}

onMounted(async () => {
  const stored = authStore.pinHint
  if (!stored?.employee_id) return toOtpLogin()

  Object.assign(hint, stored)

  try {
    const { data } = await auth.pinStatus({
      employee_id: stored.employee_id,
      device_fingerprint: device.get(),
    })
    const result = data.data ?? data

    if (!result.pin_available) {
      // Trust expired, PIN removed, or a different browser profile.
      authStore.clearPinHint()
      return toOtpLogin()
    }

    hint.first_name = result.first_name ?? stored.first_name
    hint.email = result.email ?? ''
    lockedUntil.value = result.locked ? result.locked_until : null
    checking.value = false
    await nextTick()
    pinField.value?.focus()
  } catch {
    toOtpLogin()
  }
})

function goAfterLogin() {
  router.push(authStore.isAdmin ? '/admin/select-type' : '/dashboard')
}

async function submitPin(value) {
  if (verifying.value) return
  verifying.value = true
  pinError.value = ''

  try {
    const { data } = await auth.loginWithPin({
      employee_id: hint.employee_id,
      pin: value,
      device_fingerprint: device.get(),
    })
    const result = data.data ?? data
    authStore.setAuth(result.user, result.token)
    authStore.rememberPinHint(result.user)
    goAfterLogin()
  } catch (error) {
    verifying.value = false
    const status = error.response?.status
    const body = error.response?.data

    if (status === 423) {
      lockedUntil.value = body?.errors?.locked_until ?? true
      notify.error(body?.message || 'PIN login is locked.')
      return
    }
    if (status === 403) {
      authStore.clearPinHint()
      return toOtpLogin()
    }
    // Setting the error makes PinInput shake and clear itself.
    pinError.value = body?.message || 'Incorrect PIN.'
  }
}

async function startReset() {
  try {
    const { data } = await auth.pinResetRequest({ employee_id: hint.employee_id })
    const result = data.data ?? data
    resetEmail.value = result.email || hint.email
    mode.value = 'reset'
    notify.info('Reset code sent to your registered email.')
  } catch (error) {
    notify.error(error.response?.data?.message || 'Could not send the reset code.')
  }
}

async function confirmReset() {
  if (reset.pin !== reset.pin_confirmation) {
    return notify.error('The two PINs do not match.')
  }

  resetting.value = true
  try {
    const { data } = await auth.pinResetConfirm({
      employee_id: hint.employee_id,
      otp: reset.otp,
      pin: reset.pin,
      pin_confirmation: reset.pin_confirmation,
      device_fingerprint: device.get(),
    })
    const result = data.data ?? data
    authStore.setAuth(result.user, result.token)
    authStore.rememberPinHint(result.user)
    notify.success('New PIN set.')
    goAfterLogin()
  } catch (error) {
    notify.error(error.response?.data?.message || 'Could not reset your PIN.')
  } finally {
    resetting.value = false
  }
}
</script>

<style scoped>
.pin-avatar {
  width: 64px;
  height: 64px;
  margin: 0 auto;
  border-radius: 50%;
  background: linear-gradient(135deg, #1e40af, #3b82f6);
  color: #fff;
  font-size: 1.6rem;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
}
</style>
