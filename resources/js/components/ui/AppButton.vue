<template>
  <button
    :type="type"
    :class="buttonClasses"
    :disabled="disabled || loading"
  >
    <span
      v-if="loading"
      class="spinner-border spinner-border-sm me-1"
      role="status"
      aria-hidden="true"
    ></span>
    <slot />
  </button>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  type: {
    type: String,
    default: 'button',
    validator: (v) => ['button', 'submit'].includes(v),
  },
  variant: {
    type: String,
    default: 'primary',
  },
  size: {
    type: String,
    default: 'md',
    validator: (v) => ['sm', 'md', 'lg'].includes(v),
  },
  loading: {
    type: Boolean,
    default: false,
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  block: {
    type: Boolean,
    default: false,
  },
})

const variantMap = {
  primary: 'btn-primary',
  danger: 'btn-danger',
  success: 'btn-success',
  warning: 'btn-warning',
  secondary: 'btn-secondary',
  'outline-primary': 'btn-outline-primary',
  'outline-danger': 'btn-outline-danger',
}

const sizeMap = {
  sm: 'btn-sm',
  md: '',
  lg: 'btn-lg',
}

const buttonClasses = computed(() => [
  'btn',
  variantMap[props.variant] || 'btn-primary',
  sizeMap[props.size] || '',
  { 'w-100': props.block },
])
</script>
