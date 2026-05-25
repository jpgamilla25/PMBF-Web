<template>
  <Teleport to="body">
    <div class="pmbf-toast-container">
      <TransitionGroup name="toast">
        <div
          v-for="notification in notifications"
          :key="notification.id"
          class="toast show mb-2"
          :class="`border-${notification.type}`"
          role="alert"
        >
          <div class="toast-header">
            <span
              class="rounded-circle me-2 d-inline-block"
              :class="`bg-${notification.type}`"
              style="width: 12px; height: 12px;"
            ></span>
            <strong class="me-auto">{{ typeLabel(notification.type) }}</strong>
            <button
              type="button"
              class="btn-close btn-close-sm"
              @click="store.remove(notification.id)"
            ></button>
          </div>
          <div class="toast-body">
            {{ notification.message }}
          </div>
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>

<script setup>
import { computed } from 'vue'
import { useNotificationStore } from '../../stores/notification'

const store = useNotificationStore()
const notifications = computed(() => store.notifications)

function typeLabel(type) {
  const labels = {
    success: 'Success',
    danger: 'Error',
    warning: 'Warning',
    info: 'Info',
  }
  return labels[type] || 'Notice'
}
</script>

<style scoped>
.pmbf-toast-container {
  position: fixed;
  bottom: 1rem;
  right: 1rem;
  z-index: 10000;
  max-width: 350px;
}

.toast {
  min-width: 300px;
}

.toast-enter-active {
  transition: all 0.3s ease;
}

.toast-leave-active {
  transition: all 0.3s ease;
}

.toast-enter-from {
  opacity: 0;
  transform: translateX(100%);
}

.toast-leave-to {
  opacity: 0;
  transform: translateX(100%);
}
</style>
