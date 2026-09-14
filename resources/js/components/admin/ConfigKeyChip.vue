<template>
  <button
    type="button"
    class="cfg-key"
    :title="copied ? 'Copied' : `Copy ${configKey}`"
    :aria-label="`Copy config key ${configKey}`"
    @click="copy"
  >
    <i class="bi" :class="copied ? 'bi-check-lg' : 'bi-code-slash'" aria-hidden="true"></i>
    <span>{{ configKey }}</span>
  </button>
</template>

<script setup>
/**
 * The underlying configuration key, shown only when "Show config keys" is on.
 *
 * It used to sit under every field permanently, competing with the label for
 * attention in a page an admin reads by label. It stays available because
 * support conversations and the audit trail refer to keys.
 */
import { ref } from 'vue'
import { useNotificationStore } from '@/stores/notification'

const props = defineProps({
  configKey: { type: String, required: true },
})

const notify = useNotificationStore()
const copied = ref(false)

async function copy() {
  try {
    await navigator.clipboard.writeText(props.configKey)
    copied.value = true
    setTimeout(() => (copied.value = false), 2000)
  } catch {
    notify.info(props.configKey)
  }
}
</script>

<style scoped>
.cfg-key {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  margin-top: 0.35rem;
  padding: 0.05rem 0.4rem;
  border: 1px solid var(--bs-border-color);
  border-radius: 3px;
  background: var(--bs-tertiary-bg);
  color: var(--bs-secondary-color);
  font-family: var(--bs-font-monospace);
  font-size: 0.68rem;
  line-height: 1.5;
  cursor: pointer;
}

.cfg-key:hover {
  color: var(--bs-body-color);
  border-color: var(--bs-secondary-color);
}

.cfg-key:focus-visible {
  outline: 2px solid var(--bs-primary);
  outline-offset: 1px;
}
</style>
