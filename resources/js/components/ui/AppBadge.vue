<template>
  <span class="badge" :class="`bg-${variant}`">{{ text }}</span>
</template>

<script setup>
defineProps({
  variant: {
    type: String,
    default: 'primary',
    validator: (v) =>
      ['primary', 'success', 'warning', 'danger', 'info', 'secondary', 'dark'].includes(v),
  },
  text: {
    type: String,
    default: '',
  },
})
</script>
