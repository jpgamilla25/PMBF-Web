<template>
  <div class="dropdown">
    <button
      class="btn btn-link text-white position-relative p-1 border-0 shadow-none"
      type="button"
      data-bs-toggle="dropdown"
      data-bs-auto-close="outside"
      aria-label="Notifications"
      @click="onOpen"
    >
      <i class="bi fs-5" :class="inbox.hasUnread ? 'bi-bell-fill' : 'bi-bell'"></i>
      <span
        v-if="inbox.hasUnread"
        class="position-absolute translate-middle badge rounded-pill bg-danger"
        style="top: 4px; left: 100%; font-size: .6rem;"
      >
        {{ inbox.badge }}
      </span>
    </button>

    <div class="dropdown-menu dropdown-menu-end shadow notif-menu p-0">
      <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom">
        <span class="fw-semibold small">Notifications</span>
        <button
          v-if="inbox.hasUnread"
          class="btn btn-link btn-sm p-0 text-decoration-none small"
          @click="inbox.markAllAsRead()"
        >
          Mark all read
        </button>
      </div>

      <div class="notif-list">
        <div v-if="inbox.loading && !inbox.items.length" class="text-center py-4">
          <div class="spinner-border spinner-border-sm text-primary"></div>
        </div>

        <div v-else-if="!inbox.items.length" class="text-center text-muted small py-4 px-3">
          <i class="bi bi-inbox fs-3 d-block mb-2 opacity-50"></i>
          You're all caught up.
        </div>

        <button
          v-for="n in inbox.items"
          :key="n.id"
          class="notif-item d-flex gap-2 w-100 text-start px-3 py-2 border-0"
          :class="{ unread: !n.read_at }"
          @click="open(n)"
        >
          <i class="bi mt-1 flex-shrink-0" :class="[n.icon || 'bi-bell', iconColor(n)]"></i>
          <span class="flex-grow-1 min-w-0">
            <span class="d-block fw-semibold small text-truncate">{{ n.title }}</span>
            <span class="d-block text-muted notif-msg">{{ n.message }}</span>
            <span class="d-block text-muted" style="font-size: .7rem;">{{ ago(n.created_at) }}</span>
          </span>
          <span v-if="!n.read_at" class="notif-dot flex-shrink-0"></span>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useInboxStore } from '@/stores/inbox'

const router = useRouter()
const inbox = useInboxStore()

/** Only load the list when the dropdown is actually opened. */
function onOpen() {
  inbox.fetchItems()
}

function open(n) {
  inbox.markAsRead(n.id)
  if (n.url) router.push(n.url)
}

function iconColor(n) {
  const icon = n.icon || ''
  if (icon.includes('x-circle') || icon.includes('octagon')) return 'text-danger'
  if (icon.includes('exclamation') || icon.includes('clock')) return 'text-warning'
  if (icon.includes('check')) return 'text-success'
  return 'text-primary'
}

function ago(iso) {
  const seconds = Math.floor((Date.now() - new Date(iso)) / 1000)
  if (seconds < 60) return 'just now'

  const units = [
    ['minute', 60],
    ['hour', 60],
    ['day', 24],
    ['week', 7],
  ]

  let value = seconds
  let label = 'second'
  for (const [name, size] of units) {
    if (value < size) break
    value = Math.floor(value / size)
    label = name
  }

  return `${value} ${label}${value === 1 ? '' : 's'} ago`
}

onMounted(() => inbox.startPolling())
onUnmounted(() => inbox.stopPolling())
</script>

<style scoped>
.notif-menu {
  width: 340px;
  max-width: calc(100vw - 24px);
}

.notif-list {
  max-height: 380px;
  overflow-y: auto;
}

.notif-item {
  background: transparent;
  border-bottom: 1px solid var(--bs-border-color) !important;
  cursor: pointer;
}

.notif-item:last-child { border-bottom: 0 !important; }
.notif-item:hover { background: var(--bs-tertiary-bg); }
.notif-item.unread { background: rgba(59, 130, 246, .06); }

.notif-msg {
  font-size: .78rem;
  line-height: 1.3;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.notif-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #3b82f6;
  margin-top: 6px;
}

.min-w-0 { min-width: 0; }
</style>
