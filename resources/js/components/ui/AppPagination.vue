<template>
  <nav v-if="meta && meta.total > 0" aria-label="Pagination">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
      <small class="text-muted">
        Showing {{ from }}-{{ to }} of {{ meta.total }} results
      </small>
      <ul class="pagination pagination-sm mb-0">
        <li class="page-item" :class="{ disabled: isFirst }">
          <button
            type="button"
            class="page-link"
            :disabled="isFirst"
            aria-label="Previous page"
            @click="changePage(meta.current_page - 1)"
          >
            &laquo;
          </button>
        </li>

        <li
          v-for="(page, i) in visiblePages"
          :key="`${page}-${i}`"
          class="page-item"
          :class="{ active: page === meta.current_page, disabled: page === GAP }"
        >
          <!-- The gap is decoration, not a destination: keep it out of the
               tab order rather than offering focus to a dead control. -->
          <span v-if="page === GAP" class="page-link" aria-hidden="true">&hellip;</span>
          <button
            v-else
            type="button"
            class="page-link"
            :aria-label="`Page ${page}`"
            :aria-current="page === meta.current_page ? 'page' : undefined"
            @click="changePage(page)"
          >
            {{ page }}
          </button>
        </li>

        <li class="page-item" :class="{ disabled: isLast }">
          <button
            type="button"
            class="page-link"
            :disabled="isLast"
            aria-label="Next page"
            @click="changePage(meta.current_page + 1)"
          >
            &raquo;
          </button>
        </li>
      </ul>
    </div>
  </nav>
</template>

<script setup>
import { computed } from 'vue'

const GAP = '…'

const props = defineProps({
  meta: {
    type: Object,
    required: true,
  },
})

const emit = defineEmits(['page-change'])

const isFirst = computed(() => props.meta.current_page <= 1)
const isLast = computed(() => props.meta.current_page >= props.meta.last_page)

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

  if (current > 3) pages.push(GAP)

  const start = Math.max(2, current - 1)
  const end = Math.min(last - 1, current + 1)

  for (let i = start; i <= end; i++) pages.push(i)

  if (current < last - 2) pages.push(GAP)

  pages.push(last)

  return pages
})

function changePage(page) {
  if (page >= 1 && page <= props.meta.last_page && page !== props.meta.current_page) {
    emit('page-change', page)
  }
}
</script>
