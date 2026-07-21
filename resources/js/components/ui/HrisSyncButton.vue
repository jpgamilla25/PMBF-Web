<template>
  <div class="dropdown">
    <button
      class="btn btn-link text-white position-relative p-1 border-0 shadow-none"
      type="button"
      data-bs-toggle="dropdown"
      data-bs-auto-close="outside"
      :title="stale ? 'Your details may be out of date — sync now' : 'Sync my details from HRIS'"
      aria-label="Sync my details from HRIS"
    >
      <i class="bi bi-arrow-repeat fs-5" :class="{ 'spin': syncing }"></i>
      <!-- Nudge only when the snapshot is old enough to plausibly be wrong. -->
      <span
        v-if="stale && !syncing"
        class="position-absolute translate-middle p-1 bg-warning rounded-circle"
        style="top: 6px; left: 92%;"
      ><span class="visually-hidden">Details may be out of date</span></span>
    </button>

    <div class="dropdown-menu dropdown-menu-end shadow sync-menu p-3">
      <div class="fw-semibold mb-1">
        <i class="bi bi-arrow-repeat me-1 text-primary"></i>Sync my details
      </div>

      <!-- The purpose, in the member's own terms. -->
      <p class="small text-body-secondary mb-2">
        Pulls your <strong>employment type, position, department, base pay,
        take-home pay</strong> and <strong>contract dates</strong> from HRIS.
      </p>
      <p class="small text-body-secondary mb-2">
        Use this if your pay or position changed and PMBF still shows the old
        figures. Your loan eligibility is always checked against live HRIS
        data — this updates what's displayed in lists and reports.
      </p>

      <div class="d-flex align-items-center justify-content-between gap-2 pt-2 border-top">
        <span class="small text-body-secondary">
          Last synced: <strong>{{ lastSyncedLabel }}</strong>
        </span>
        <button class="btn btn-sm btn-primary" :disabled="syncing" @click="sync">
          <span v-if="syncing" class="spinner-border spinner-border-sm me-1"></span>
          {{ syncing ? 'Syncing...' : 'Sync now' }}
        </button>
      </div>

      <div v-if="result" class="alert small mt-2 mb-0 py-2" :class="result.variant">
        {{ result.message }}
        <ul v-if="result.fields?.length" class="mb-0 ps-3 mt-1">
          <li v-for="f in result.fields" :key="f">{{ fieldLabel(f) }}</li>
        </ul>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useNotificationStore } from '@/stores/notification'
import auth from '@/services/auth'

const authStore = useAuthStore()
const notify = useNotificationStore()

const syncing = ref(false)
const result = ref(null)

/** Older than a day means the nightly job is the only thing that touched it. */
const STALE_AFTER_MS = 24 * 60 * 60 * 1000

const syncedAt = computed(() => {
  const raw = authStore.user?.hris_synced_at
  return raw ? new Date(raw) : null
})

const stale = computed(() => {
  if (!authStore.isAuthenticated) return false
  if (!syncedAt.value) return true
  return Date.now() - syncedAt.value.getTime() > STALE_AFTER_MS
})

const lastSyncedLabel = computed(() => {
  if (!syncedAt.value) return 'never'

  const mins = Math.floor((Date.now() - syncedAt.value.getTime()) / 60000)
  if (mins < 1) return 'just now'
  if (mins < 60) return `${mins} min ago`

  const hours = Math.floor(mins / 60)
  if (hours < 24) return `${hours} hour${hours === 1 ? '' : 's'} ago`

  return syncedAt.value.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' })
})

const FIELD_LABELS = {
  employment_type: 'Employment type',
  position: 'Position',
  department: 'Department',
  base_pay: 'Base pay',
  take_home_pay: 'Take-home pay',
  contract_start: 'Contract start',
  contract_end: 'Contract end',
}

function fieldLabel(field) {
  return FIELD_LABELS[field] ?? field
}

async function sync() {
  syncing.value = true
  result.value = null

  try {
    const { data } = await auth.syncHris()
    const payload = data.data ?? data

    // Refresh the store so the header, banners and profile all reflect it.
    if (payload.user) authStore.user = payload.user

    result.value = payload.changed
      ? { variant: 'alert-success', message: 'Updated:', fields: payload.changed_fields }
      : { variant: 'alert-secondary', message: 'Already up to date — nothing changed.' }

    notify.success(data.message ?? 'Sync complete.')
  } catch (error) {
    const msg = error.response?.data?.message
      ?? 'Could not reach HRIS right now. Your details are unchanged.'
    result.value = { variant: 'alert-warning', message: msg }
    notify.error(msg)
  } finally {
    syncing.value = false
  }
}
</script>

<style scoped>
.sync-menu {
  width: 330px;
  max-width: calc(100vw - 24px);
}

.spin {
  display: inline-block;
  animation: sync-spin 0.9s linear infinite;
}

@keyframes sync-spin {
  to { transform: rotate(360deg); }
}
</style>
