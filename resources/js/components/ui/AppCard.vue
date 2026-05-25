<template>
  <div class="card shadow-sm">
    <div v-if="title || $slots.header" class="card-header bg-white">
      <slot name="header">
        <h5 class="card-title mb-0">{{ title }}</h5>
      </slot>
    </div>
    <div :class="['card-body', { 'p-0': !padding }]">
      <slot />
    </div>
    <div v-if="$slots.footer" class="card-footer bg-white">
      <slot name="footer" />
    </div>
  </div>
</template>

<script setup>
defineProps({
  title: {
    type: String,
    default: '',
  },
  padding: {
    type: Boolean,
    default: true,
  },
})
</script>
