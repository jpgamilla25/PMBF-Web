<template>
  <div class="mb-3">
    <label v-if="label" :for="inputId" class="form-label">
      {{ label }}
      <span v-if="required" class="text-danger" aria-hidden="true">*</span>
      <span v-if="required" class="visually-hidden">(required)</span>
    </label>

    <!-- Select -->
    <select
      v-if="type === 'select'"
      :id="inputId"
      class="form-select"
      :class="{ 'is-invalid': error }"
      :value="modelValue"
      :disabled="disabled"
      :required="required"
      :aria-invalid="error ? 'true' : undefined"
      :aria-describedby="describedBy"
      @change="$emit('update:modelValue', $event.target.value)"
    >
      <option value="" disabled>{{ placeholder || 'Select an option' }}</option>
      <option
        v-for="opt in options"
        :key="opt.value"
        :value="opt.value"
      >
        {{ opt.label }}
      </option>
    </select>

    <!-- Textarea -->
    <textarea
      v-else-if="type === 'textarea'"
      :id="inputId"
      class="form-control"
      :class="{ 'is-invalid': error }"
      :value="modelValue"
      :placeholder="placeholder"
      :disabled="disabled"
      :required="required"
      :autocomplete="autocomplete"
      :aria-invalid="error ? 'true' : undefined"
      :aria-describedby="describedBy"
      rows="3"
      @input="$emit('update:modelValue', $event.target.value)"
    ></textarea>

    <!-- Input -->
    <input
      v-else
      :id="inputId"
      :type="type"
      class="form-control"
      :class="{ 'is-invalid': error }"
      :value="modelValue"
      :placeholder="placeholder"
      :disabled="disabled"
      :required="required"
      :autocomplete="autocomplete"
      :inputmode="inputmode"
      :aria-invalid="error ? 'true' : undefined"
      :aria-describedby="describedBy"
      @input="$emit('update:modelValue', $event.target.value)"
    />

    <!-- Errors are announced with the field, not just shown beside it. -->
    <div v-if="error" :id="`${inputId}-error`" class="invalid-feedback">
      {{ error }}
    </div>
    <div v-else-if="hint" :id="`${inputId}-hint`" class="form-text">
      {{ hint }}
    </div>
  </div>
</template>

<script>
// Module scope, deliberately. A counter declared at the top of <script setup>
// compiles into setup() and restarts at 0 for every instance, so every field
// would claim the same DOM id and every label would point at the first input.
let idSeq = 0

export function nextInputId() {
  return `app-input-${++idSeq}`
}
</script>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  modelValue: {
    type: [String, Number, null],
    default: '',
  },
  id: {
    type: String,
    default: '',
  },
  label: {
    type: String,
    default: '',
  },
  type: {
    type: String,
    default: 'text',
    validator: (v) =>
      ['text', 'email', 'password', 'number', 'date', 'tel', 'search', 'textarea', 'select'].includes(v),
  },
  error: {
    type: String,
    default: '',
  },
  /** Help text shown when there is no error to show instead. */
  hint: {
    type: String,
    default: '',
  },
  placeholder: {
    type: String,
    default: '',
  },
  autocomplete: {
    type: String,
    default: undefined,
  },
  inputmode: {
    type: String,
    default: undefined,
  },
  required: {
    type: Boolean,
    default: false,
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  options: {
    type: Array,
    default: () => [],
  },
})

defineEmits(['update:modelValue'])

// One stable id per mounted field: the label, the control and the error or
// hint text all reference it.
const inputId = props.id || nextInputId()

const describedBy = computed(() => {
  if (props.error) return `${inputId}-error`
  if (props.hint) return `${inputId}-hint`
  return undefined
})
</script>
