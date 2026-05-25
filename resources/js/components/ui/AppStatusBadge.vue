<template>
  <span class="badge" :class="`bg-${statusColor}`">{{ statusText }}</span>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  status: {
    type: String,
    default: '',
  },
})

const statusColorMap = {
  co_maker_pending: 'warning',
  co_maker_declined: 'danger',
  admin_pending: 'warning',
  pending: 'warning',
  receiver_approved: 'info',
  committee_approved: 'info',
  approved: 'primary',
  released: 'success',
  disapproved: 'danger',
  completed: 'secondary',
  active: 'success',
  inactive: 'secondary',
  cancelled: 'dark',
  chairperson_approved: 'primary',
  overdue: 'danger',
  paid: 'success',
  partially_paid: 'info',
}

const statusColor = computed(() => {
  return statusColorMap[props.status] || 'secondary'
})

const statusText = computed(() => {
  if (!props.status) return ''
  return props.status
    .replace(/_/g, ' ')
    .replace(/\b\w/g, (c) => c.toUpperCase())
})
</script>
