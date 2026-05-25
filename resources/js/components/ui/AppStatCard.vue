<template>
  <div class="card shadow-sm border-0 h-100">
    <div class="card-body d-flex align-items-center">
      <div
        class="stat-icon-circle me-3 d-flex align-items-center justify-content-center rounded-circle"
        :class="`bg-${color} bg-opacity-10`"
      >
        <i :class="icon" class="fs-4" :style="{ color: `var(--bs-${color})` }"></i>
      </div>
      <div>
        <div class="text-muted small text-uppercase fw-semibold">{{ title }}</div>
        <div class="fs-4 fw-bold">
          <span v-if="prefix" class="me-1">{{ prefix }}</span>{{ formattedValue }}
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  title: {
    type: String,
    required: true,
  },
  value: {
    type: [Number, String],
    default: 0,
  },
  icon: {
    type: String,
    default: 'bi bi-bar-chart',
  },
  color: {
    type: String,
    default: 'primary',
    validator: (v) => ['primary', 'success', 'warning', 'danger'].includes(v),
  },
  prefix: {
    type: String,
    default: '',
  },
})

const formattedValue = computed(() => {
  const val = Number(props.value)
  if (isNaN(val)) return props.value
  if (props.prefix === '₱' || props.prefix === '₱') {
    return val.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
  }
  return val.toLocaleString()
})
</script>

<style scoped>
.stat-icon-circle {
  width: 56px;
  height: 56px;
  min-width: 56px;
}
</style>
