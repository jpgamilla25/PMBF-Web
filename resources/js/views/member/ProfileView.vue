<template>
  <AppLayout>
    <div class="d-flex align-items-center justify-content-between mb-4">
      <h4 class="fw-bold mb-0">My Profile</h4>
    </div>

    <AppLoading :loading="loading" text="Loading profile..." />

    <template v-if="!loading && profile">
      <div class="row g-4">
        <!-- Personal Information -->
        <div class="col-lg-6">
          <AppCard title="Personal Information">
            <div class="text-center mb-4">
              <div
                class="rounded-circle bg-primary bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-2"
                style="width: 80px; height: 80px;"
              >
                <i class="bi bi-person-fill fs-1 text-primary"></i>
              </div>
              <h5 class="fw-bold mb-0">{{ profile.full_name ?? `${profile.first_name ?? ''} ${profile.last_name ?? ''}`.trim() }}</h5>
              <span class="text-muted">{{ profile.employee_id }}</span>
            </div>

            <ul class="list-group list-group-flush">
              <li class="list-group-item d-flex justify-content-between px-0">
                <span class="text-muted">Employee ID</span>
                <span class="fw-medium font-monospace">{{ profile.employee_id ?? '-' }}</span>
              </li>
              <li class="list-group-item d-flex justify-content-between px-0">
                <span class="text-muted">Full Name</span>
                <span class="fw-medium">{{ profile.full_name ?? `${profile.first_name ?? ''} ${profile.middle_name ?? ''} ${profile.last_name ?? ''}`.trim() }}</span>
              </li>
              <li class="list-group-item d-flex justify-content-between px-0">
                <span class="text-muted">Email</span>
                <span class="fw-medium">{{ profile.email ?? '-' }}</span>
              </li>
              <li class="list-group-item d-flex justify-content-between px-0">
                <span class="text-muted">Mobile</span>
                <span class="fw-medium">{{ profile.mobile ?? '-' }}</span>
              </li>
              <li class="list-group-item d-flex justify-content-between px-0">
                <span class="text-muted">Employment Type</span>
                <AppBadge
                  :variant="profile.employment_type === 'permanent' ? 'success' : 'info'"
                  :text="profile.employment_type ?? '-'"
                />
              </li>
              <li class="list-group-item d-flex justify-content-between px-0">
                <span class="text-muted">Position</span>
                <span class="fw-medium">{{ profile.position ?? '-' }}</span>
              </li>
              <li class="list-group-item d-flex justify-content-between px-0">
                <span class="text-muted">Department</span>
                <span class="fw-medium">{{ profile.department ?? '-' }}</span>
              </li>
              <li class="list-group-item d-flex justify-content-between px-0">
                <span class="text-muted">Status</span>
                <AppStatusBadge :status="profile.status ?? 'active'" />
              </li>
            </ul>
          </AppCard>
        </div>

        <!-- Right Column -->
        <div class="col-lg-6">
          <!-- Compensation -->
          <AppCard title="Compensation" class="mb-4">
            <div class="row text-center mb-3">
              <div class="col-6">
                <div class="text-muted small">Base Pay</div>
                <div class="fs-5 fw-bold text-primary">&#8369;{{ Number(profile.base_pay ?? 0).toLocaleString() }}</div>
              </div>
              <div class="col-6">
                <div class="text-muted small">Take-Home Pay</div>
                <div class="fs-5 fw-bold text-success">&#8369;{{ Number(profile.take_home_pay ?? 0).toLocaleString() }}</div>
              </div>
            </div>
            <ul class="list-group list-group-flush small">
              <li v-if="profile.contract_start" class="list-group-item d-flex justify-content-between px-0">
                <span class="text-muted">Contract Start</span>
                <span class="fw-medium">{{ formatDate(profile.contract_start) }}</span>
              </li>
              <li v-if="profile.contract_end" class="list-group-item d-flex justify-content-between px-0">
                <span class="text-muted">Contract End</span>
                <span class="fw-medium">{{ formatDate(profile.contract_end) }}</span>
              </li>
            </ul>
          </AppCard>

          <!-- Loan Summary -->
          <AppCard title="Loan Summary">
            <div class="row text-center">
              <div class="col-4">
                <div
                  class="rounded-circle bg-primary bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-2"
                  style="width: 48px; height: 48px;"
                >
                  <i class="bi bi-cash-stack text-primary"></i>
                </div>
                <div class="fs-5 fw-bold">{{ profile.total_loans ?? 0 }}</div>
                <div class="text-muted small">Total Loans</div>
              </div>
              <div class="col-4">
                <div
                  class="rounded-circle bg-success bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-2"
                  style="width: 48px; height: 48px;"
                >
                  <i class="bi bi-check-circle text-success"></i>
                </div>
                <div class="fs-5 fw-bold">{{ profile.active_loans ?? 0 }}</div>
                <div class="text-muted small">Active</div>
              </div>
              <div class="col-4">
                <div
                  class="rounded-circle bg-warning bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-2"
                  style="width: 48px; height: 48px;"
                >
                  <i class="bi bi-people text-warning"></i>
                </div>
                <div class="fs-5 fw-bold">{{ profile.dependents_count ?? 0 }}</div>
                <div class="text-muted small">Dependents</div>
              </div>
            </div>
          </AppCard>
        </div>

        <!-- Security -->
        <div id="security" class="col-12">
          <AppCard title="Security">
            <!-- PIN -->
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 pb-3">
              <div>
                <div class="fw-semibold">
                  <i class="bi bi-shield-lock me-1 text-primary"></i>Sign-in PIN
                </div>
                <div class="text-muted small">
                  <template v-if="authStore.hasPin">
                    A 4-digit PIN signs you in on trusted devices instead of an email OTP.
                  </template>
                  <template v-else>
                    Not set. Add a PIN to skip the email OTP on this device.
                  </template>
                </div>
              </div>
              <div class="d-flex gap-2">
                <router-link to="/pin-setup" class="btn btn-sm btn-primary">
                  {{ authStore.hasPin ? 'Change PIN' : 'Create PIN' }}
                </router-link>
                <button
                  v-if="authStore.hasPin"
                  class="btn btn-sm btn-outline-danger"
                  :disabled="removingPin"
                  @click="removePin"
                >
                  Remove
                </button>
              </div>
            </div>

            <!-- Trusted devices -->
            <div class="border-top pt-3">
              <div class="fw-semibold mb-2">
                <i class="bi bi-laptop me-1 text-primary"></i>Trusted devices
              </div>

              <p v-if="!devices.length" class="text-muted small mb-0">
                No trusted devices. You'll be asked for an email OTP each time you sign in.
              </p>

              <ul v-else class="list-group list-group-flush">
                <li
                  v-for="d in devices"
                  :key="d.id"
                  class="list-group-item d-flex flex-wrap align-items-center justify-content-between gap-2 px-0"
                >
                  <div>
                    <div class="fw-medium small">
                      {{ d.device_name || 'Unknown device' }}
                      <span v-if="d.device_fingerprint === currentFingerprint" class="badge bg-success ms-1">
                        This device
                      </span>
                    </div>
                    <div class="text-muted" style="font-size: .75rem;">
                      {{ d.ip_address || 'unknown IP' }} · trusted until {{ formatDate(d.trusted_until) }}
                    </div>
                  </div>
                  <button class="btn btn-sm btn-outline-secondary" @click="revoke(d)">
                    Revoke
                  </button>
                </li>
              </ul>
            </div>
          </AppCard>
        </div>
      </div>
    </template>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useLoading } from '@/composables/useLoading'
import { useAuthStore } from '@/stores/auth'
import { useNotificationStore } from '@/stores/notification'
import { useDeviceFingerprint } from '@/composables/useDeviceFingerprint'
import members from '@/services/members'
import auth from '@/services/auth'
import AppLayout from '@/components/layout/AppLayout.vue'
import AppCard from '@/components/ui/AppCard.vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import AppStatusBadge from '@/components/ui/AppStatusBadge.vue'
import AppLoading from '@/components/ui/AppLoading.vue'

const { loading, withLoading } = useLoading()
const authStore = useAuthStore()
const notify = useNotificationStore()
const device = useDeviceFingerprint()

const profile = ref(null)
const devices = ref([])
const removingPin = ref(false)
const currentFingerprint = device.get()

async function loadDevices() {
  try {
    const { data } = await auth.getTrustedDevices()
    devices.value = data.data ?? data
  } catch {
    devices.value = []
  }
}

async function removePin() {
  removingPin.value = true
  try {
    await auth.removePin()
    await authStore.fetchUser()
    authStore.clearPinHint()
    notify.success('PIN removed. You will sign in with an email OTP.')
  } catch {
    notify.error('Could not remove your PIN.')
  } finally {
    removingPin.value = false
  }
}

async function revoke(deviceRow) {
  try {
    await auth.revokeTrust({ device_fingerprint: deviceRow.device_fingerprint })
    // A PIN only works on a trusted device, so revoking the current one also
    // retires the local PIN shortcut.
    if (deviceRow.device_fingerprint === currentFingerprint) {
      authStore.clearPinHint()
    }
    await loadDevices()
    notify.success('Device trust revoked.')
  } catch {
    notify.error('Could not revoke this device.')
  }
}

function formatDate(dateStr) {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleDateString('en-PH', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })
}

onMounted(() => {
  withLoading(async () => {
    const response = await members.getProfile()
    profile.value = response.data.data ?? response.data
  })
  loadDevices()
})
</script>
