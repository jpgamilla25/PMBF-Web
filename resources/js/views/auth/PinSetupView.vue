<template>
  <GuestLayout>
    <div class="text-center mb-4">
      <div class="pin-badge mb-3"><i class="bi bi-shield-lock"></i></div>
      <h5 class="fw-bold mb-1">{{ isChange ? 'Change your PIN' : 'Create your PIN' }}</h5>
      <p class="text-muted small mb-0">
        {{ isChange
          ? 'Enter your current PIN, then choose a new one.'
          : 'Next time, sign in with 4 digits instead of waiting for an email.' }}
      </p>
    </div>

    <form @submit.prevent="save">
      <template v-if="isChange">
        <label class="form-label fw-semibold small">Current PIN</label>
        <PinInput v-model="form.current_pin" class="mb-4" />
      </template>

      <label class="form-label fw-semibold small">
        {{ isChange ? 'New PIN' : 'Choose a 4-digit PIN' }}
      </label>
      <PinInput
        v-model="form.pin"
        :autofocus="!isChange"
        :error="pinError"
        class="mb-4"
      />

      <label class="form-label fw-semibold small">Confirm PIN</label>
      <PinInput
        v-model="form.pin_confirmation"
        :autofocus="false"
        :error="confirmError"
        class="mb-3"
      />

      <div class="pin-note small mb-4">
        <i class="bi bi-info-circle me-1"></i>
        Your PIN only works on this device. On a new browser or phone you'll be
        asked for an email OTP first.
      </div>

      <AppButton type="submit" variant="primary" block :loading="saving" :disabled="!ready">
        <i class="bi bi-check2-circle me-1"></i>{{ isChange ? 'Update PIN' : 'Save PIN' }}
      </AppButton>
    </form>

    <div class="text-center mt-3">
      <button class="btn btn-link text-muted small" @click="skip">
        {{ isChange ? 'Cancel' : 'Skip for now' }}
      </button>
    </div>
  </GuestLayout>
</template>

<script setup>
import { reactive, ref, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useNotificationStore } from '@/stores/notification'
import { useDeviceFingerprint } from '@/composables/useDeviceFingerprint'
import auth from '@/services/auth'
import GuestLayout from '@/components/layout/GuestLayout.vue'
import PinInput from '@/components/ui/PinInput.vue'
import AppButton from '@/components/ui/AppButton.vue'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()
const notify = useNotificationStore()
const device = useDeviceFingerprint()

const isChange = computed(() => authStore.hasPin)

const form = reactive({ current_pin: '', pin: '', pin_confirmation: '' })
const pinError = ref('')
const confirmError = ref('')
const saving = ref(false)

const ready = computed(() =>
  form.pin.length === 4 &&
  form.pin_confirmation.length === 4 &&
  (!isChange.value || form.current_pin.length === 4)
)

/** Mirrors AuthService::isWeakPin so the user is told before a round trip. */
function weakPinReason(pin) {
  if (/^(\d)\1+$/.test(pin)) return 'Avoid repeating the same digit.'

  const steps = [1, 2, 3].map((i) => Number(pin[i]) - Number(pin[i - 1]))
  if (steps.every((s) => s === 1) || steps.every((s) => s === -1)) {
    return 'Avoid sequential digits like 1234.'
  }
  return ''
}

function done() {
  router.push(route.query.redirect || (authStore.isAdmin ? '/admin/select-type' : '/dashboard'))
}

function skip() {
  done()
}

async function save() {
  pinError.value = ''
  confirmError.value = ''

  const weak = weakPinReason(form.pin)
  if (weak) {
    pinError.value = weak
    return
  }
  if (form.pin !== form.pin_confirmation) {
    confirmError.value = 'The two PINs do not match.'
    return
  }

  saving.value = true
  try {
    await auth.setPin({
      pin: form.pin,
      pin_confirmation: form.pin_confirmation,
      current_pin: isChange.value ? form.current_pin : undefined,
      device_fingerprint: device.get(),
    })

    // Refresh so `has_pin` (and the PIN hint) reflect the new state.
    await authStore.fetchUser()
    authStore.rememberPinHint(authStore.user)

    notify.success(isChange.value ? 'PIN updated.' : 'PIN saved. Use it to sign in next time.')
    done()
  } catch (error) {
    const msg = error.response?.data?.message || 'Could not save your PIN.'
    pinError.value = msg
    notify.error(msg)
  } finally {
    saving.value = false
  }
}
</script>

<style scoped>
.pin-badge {
  width: 64px;
  height: 64px;
  margin: 0 auto;
  border-radius: 50%;
  background: #eff6ff;
  color: #1e40af;
  font-size: 1.7rem;
  display: flex;
  align-items: center;
  justify-content: center;
}

.pin-note {
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  border-radius: 10px;
  padding: 10px 12px;
  color: #166534;
}
</style>
