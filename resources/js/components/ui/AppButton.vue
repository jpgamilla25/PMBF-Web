<template>
  <button
    :type="type"
    :class="buttonClasses"
    :disabled="disabled || loading"
    :aria-busy="loading ? 'true' : undefined"
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

<script>
// Every variant the app actually uses. A missing entry used to fall through to
// btn-primary, which silently rendered 17 outline buttons across the admin as
// solid primary ones — the validator below makes the next gap fail loudly in
// dev instead.
//
// Declared in a plain <script> so it sits in module scope, where the hoisted
// defineProps() call below can reach it.
export const VARIANTS = {
  primary: 'btn-primary',
  secondary: 'btn-secondary',
  success: 'btn-success',
  danger: 'btn-danger',
  warning: 'btn-warning',
  info: 'btn-info',
  light: 'btn-light',
  dark: 'btn-dark',
  link: 'btn-link',
  'outline-primary': 'btn-outline-primary',
  'outline-secondary': 'btn-outline-secondary',
  'outline-success': 'btn-outline-success',
  'outline-danger': 'btn-outline-danger',
  'outline-warning': 'btn-outline-warning',
  'outline-info': 'btn-outline-info',
}
</script>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  type: {
    type: String,
    default: 'button',
    validator: (v) => ['button', 'submit', 'reset'].includes(v),
  },
  variant: {
    type: String,
    default: 'primary',
    validator: (v) => Object.keys(VARIANTS).includes(v),
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

const sizeMap = {
  sm: 'btn-sm',
  md: '',
  lg: 'btn-lg',
}

const buttonClasses = computed(() => [
  'btn',
  VARIANTS[props.variant] || 'btn-primary',
  sizeMap[props.size] || '',
  { 'w-100': props.block },
])
</script>
