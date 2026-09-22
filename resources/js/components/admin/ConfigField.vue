<template>
  <!-- Boolean: a switch row, label and explanation on the left, control on the
       right. These used to sit in the same grid as text inputs, where a short
       switch and a tall input made every row ragged. -->
  <div v-if="isBoolean" class="cfg-switch" :class="{ 'cfg-changed': changed }">
    <div class="flex-grow-1">
      <label :for="config.key" class="form-check-label fw-medium">{{ text.label }}</label>
      <div v-if="text.hint" class="text-muted cfg-hint">{{ text.hint }}</div>
      <ConfigKeyChip v-if="showKey" :config-key="config.key" />
    </div>
    <div class="d-flex align-items-center gap-2">
      <button
        v-if="changed"
        type="button"
        class="btn btn-link btn-sm p-0 cfg-revert"
        :aria-label="`Revert ${text.label}`"
        @click="$emit('revert')"
      >
        Revert
      </button>
      <div class="form-check form-switch m-0">
        <input
          :id="config.key"
          class="form-check-input"
          type="checkbox"
          role="switch"
          :checked="modelValue === '1'"
          @change="$emit('update:modelValue', $event.target.checked ? '1' : '0')"
        />
      </div>
    </div>
  </div>

  <!-- Everything else: label, control, then explanation beneath. -->
  <div v-else class="cfg-field" :class="{ 'cfg-changed': changed }">
    <div class="d-flex align-items-baseline gap-2 mb-1">
      <label :for="controlId" class="form-label fw-medium mb-0 flex-grow-1">{{ label }}</label>
      <button
        v-if="changed"
        type="button"
        class="btn btn-link btn-sm p-0 cfg-revert"
        :aria-label="`Revert ${label}`"
        @click="$emit('revert')"
      >
        Revert
      </button>
    </div>

    <!-- Select -->
    <select
      v-if="config.type === 'select'"
      :id="config.key"
      class="form-select form-select-sm"
      :value="modelValue"
      :aria-describedby="describedBy"
      @change="$emit('update:modelValue', $event.target.value)"
    >
      <option v-for="opt in config.options" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
    </select>

    <!-- Decimal / number -->
    <div v-else-if="isNumeric" class="input-group input-group-sm">
      <input
        :id="config.key"
        class="form-control"
        type="number"
        inputmode="decimal"
        min="0"
        :step="step"
        :value="modelValue"
        :aria-describedby="describedBy"
        @input="$emit('update:modelValue', $event.target.value)"
        @blur="$emit('blur')"
      />
      <span v-if="config.suffix" class="input-group-text">{{ config.suffix }}</span>
    </div>

    <!-- Comma-separated text -->
    <AppTagInput
      v-else
      :model-value="modelValue"
      :suffix="config.suffix"
      :item-label="itemLabel"
      :type="numericTags ? 'number' : 'text'"
      :placeholder="numericTags ? 'Type a number and press Enter...' : 'Type and press Enter...'"
      @update:model-value="$emit('update:modelValue', $event)"
    />

    <div v-if="text.hint" :id="`${config.key}-hint`" class="text-muted cfg-hint mt-1">
      {{ text.hint }}
    </div>
    <ConfigKeyChip v-if="showKey" :config-key="config.key" />
    <span v-if="changed" class="visually-hidden">Changed, not yet saved</span>
  </div>
</template>

<script setup>
/**
 * One configuration setting.
 *
 * Splits the seeded description into a short label and a hint, so a dense page
 * of settings can be scanned by label rather than read sentence by sentence.
 */
import { computed } from 'vue'
import AppTagInput from '@/components/ui/AppTagInput.vue'
import ConfigKeyChip from './ConfigKeyChip.vue'

const props = defineProps({
  config: { type: Object, required: true },
  modelValue: { type: [String, Number], default: '' },
  /** Overrides the derived label — interest rates append their period. */
  labelOverride: { type: String, default: '' },
  changed: { type: Boolean, default: false },
  showKey: { type: Boolean, default: false },
})

defineEmits(['update:modelValue', 'revert', 'blur'])

const isBoolean = computed(() => props.config.type === 'boolean')
const isNumeric = computed(() => ['decimal', 'number'].includes(props.config.type))

/**
 * Seeded descriptions read like sentences — "Available loan terms for
 * Permanent (comma-separated, in months)". The trailing parenthetical is
 * guidance, not part of the name, so it moves to hint text under the control.
 */
const text = computed(() => {
  const raw = props.labelOverride || props.config.description || props.config.key
  const match = /^(.*?)\s*\(([^()]*)\)\s*$/.exec(raw)

  if (match && match[1].trim().length > 3) {
    return { label: match[1].trim(), hint: capitalise(match[2].trim()) }
  }

  return { label: raw, hint: '' }
})

const label = computed(() => text.value.label)

function capitalise(value) {
  return value ? value.charAt(0).toUpperCase() + value.slice(1) : value
}

// Interest rates may need finer precision (8% p.a. → 0.6667%/month), so they
// keep four decimals; amounts stay at two.
const step = computed(() => {
  if (props.config.type === 'number') return '1'
  return String(props.config.key).startsWith('interest_rate') ? '0.0001' : '0.01'
})

const controlId = computed(() => props.config.key)

const describedBy = computed(() => (text.value.hint ? `${props.config.key}-hint` : undefined))

const numericTags = computed(() => String(props.config.key).includes('term'))

const itemLabel = computed(() => {
  const key = String(props.config.key)
  if (key.includes('term')) return 'Term (months)'
  if (key.includes('coverage')) return 'Coverage Option'
  return 'Value'
})
</script>

<style scoped>
.cfg-field,
.cfg-switch {
  position: relative;
  padding-left: 0.75rem;
  border-left: 2px solid transparent;
}

.cfg-switch {
  display: flex;
  align-items: flex-start;
  gap: 1rem;
  padding-block: 0.65rem;
}

/* An unsaved edit is marked where it happened, not only in the save bar. */
.cfg-changed {
  border-left-color: var(--bs-warning);
}

.cfg-hint {
  font-size: 0.78rem;
  line-height: 1.4;
}

.cfg-revert {
  font-size: 0.75rem;
  text-decoration: none;
  white-space: nowrap;
}

.cfg-revert:hover {
  text-decoration: underline;
}
</style>
