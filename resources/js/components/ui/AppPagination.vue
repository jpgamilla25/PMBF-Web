<template>
  <nav v-if="meta && meta.total > 0" aria-label="Pagination">
    <div class="d-flex align-items-center justify-content-between">
      <small class="text-muted">
        Showing {{ from }}-{{ to }} of {{ meta.total }} results
      </small>
      <ul class="pagination pagination-sm mb-0">
        <li class="page-item" :class="{ disabled: meta.current_page <= 1 }">
          <a class="page-link" href="#" @click.prevent="changePage(meta.current_page - 1)">
            &laquo;
          </a>
        </li>
        <li
          v-for="page in visiblePages"
          :key="page"
          class="page-item"
          :class="{ active: page === meta.current_page, disabled: page === '...' }"
        >
          <a class="page-link" href="#" @click.prevent="page !== '...' && changePage(page)">
            {{ page }}
          </a>
        </li>
        <li class="page-item" :class="{ disabled: meta.current_page >= meta.last_page }">
          <a class="page-link" href="#" @click.prevent="changePage(meta.current_page + 1)">
            &raquo;
          </a>
        </li>
      </ul>
    </div>
  </nav>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  meta: {
    type: Object,
    required: true,
  },
})

const emit = defineEmits(['page-change'])

const from = computed(() => {
  if (!props.meta.total) return 0
  return (props.meta.current_page - 1) * props.meta.per_page + 1
})

const to = computed(() => {
  return Math.min(props.meta.current_page * props.meta.per_page, props.meta.total)
})

const visiblePages = computed(() => {
  const current = props.meta.current_page
  const last = props.meta.last_page
  const pages = []

  if (last <= 7) {
    for (let i = 1; i <= last; i++) pages.push(i)
    return pages
  }

  pages.push(1)

  if (current > 3) pages.push('...')

  const start = Math.max(2, current - 1)
  const end = Math.min(last - 1, current + 1)

  for (let i = start; i <= end; i++) pages.push(i)

  if (current < last - 2) pages.push('...')

  pages.push(last)

  return pages
})

function changePage(page) {
  if (page >= 1 && page <= props.meta.last_page && page !== props.meta.current_page) {
    emit('page-change', page)
  }
}
</script>
