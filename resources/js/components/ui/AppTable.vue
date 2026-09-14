<template>
  <div class="position-relative">
    <!-- Loading overlay. The colour comes from a theme token — an inline
         rgba(255,255,255,…) used to flash a white sheet over dark mode. -->
    <div
      v-if="loading"
      class="app-table-overlay position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center"
    >
      <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading…</span>
      </div>
    </div>

    <div class="table-responsive">
      <table
        class="table table-hover align-middle mb-0"
        :class="{ 'app-table-stack': stack }"
        :aria-busy="loading ? 'true' : undefined"
      >
        <thead>
          <tr>
            <th
              v-for="col in columns"
              :key="col.key"
              scope="col"
              :class="col.class"
              :aria-sort="ariaSort(col)"
            >
              <slot :name="`header(${col.key})`" :column="col">
                <!-- A sortable header is a real button, so it can be reached
                     and operated from the keyboard. -->
                <button
                  v-if="col.sortable"
                  type="button"
                  class="app-table-sort"
                  :aria-label="sortLabel(col)"
                  @click="toggleSort(col)"
                >
                  <span>{{ col.label }}</span>
                  <i class="bi" :class="sortIcon(col)" aria-hidden="true"></i>
                </button>
                <span v-else-if="col.label">{{ col.label }}</span>
                <!-- An unlabelled column still needs a header for its cells to
                     be announced against. -->
                <span v-else-if="col.srLabel" class="visually-hidden">{{ col.srLabel }}</span>
              </slot>
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!items || items.length === 0">
            <td :colspan="columns.length" class="app-table-empty text-center text-muted py-4">
              {{ emptyText }}
            </td>
          </tr>
          <tr v-for="(item, index) in items" :key="item.id ?? index">
            <td
              v-for="col in columns"
              :key="col.key"
              :class="col.class"
              :data-label="col.label || col.srLabel || ''"
            >
              <slot :name="`cell(${col.key})`" :item="item" :value="item[col.key]">
                {{ item[col.key] ?? '-' }}
              </slot>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  columns: {
    type: Array,
    required: true,
  },
  items: {
    type: Array,
    default: () => [],
  },
  loading: {
    type: Boolean,
    default: false,
  },
  emptyText: {
    type: String,
    default: 'No records found.',
  },
  /** Current sort key, for columns marked `sortable`. */
  sortKey: {
    type: String,
    default: '',
  },
  sortDirection: {
    type: String,
    default: 'asc',
    validator: (v) => ['asc', 'desc'].includes(v),
  },
  /**
   * Collapse rows into stacked cards below the `md` breakpoint. A wide table
   * otherwise becomes a sideways scroll on a phone, with the action column —
   * usually the reason for the visit — furthest off screen.
   */
  stack: {
    type: Boolean,
    default: true,
  },
})

const emit = defineEmits(['sort-change'])

function isSorted(col) {
  return props.sortKey === col.key
}

function ariaSort(col) {
  if (!col.sortable) return undefined
  if (!isSorted(col)) return 'none'
  return props.sortDirection === 'asc' ? 'ascending' : 'descending'
}

function sortIcon(col) {
  if (!isSorted(col)) return 'bi-arrow-down-up opacity-25'
  return props.sortDirection === 'asc' ? 'bi-sort-down-alt' : 'bi-sort-up-alt'
}

function sortLabel(col) {
  if (!isSorted(col)) return `Sort by ${col.label}, ascending`
  return props.sortDirection === 'asc'
    ? `Sorted by ${col.label} ascending. Sort descending`
    : `Sorted by ${col.label} descending. Sort ascending`
}

function toggleSort(col) {
  const direction = isSorted(col) && props.sortDirection === 'asc' ? 'desc' : 'asc'
  emit('sort-change', { key: col.key, direction })
}
</script>

<style scoped>
.app-table-overlay {
  /* --bs-body-bg follows the active theme, so this dims rather than whitens. */
  background: color-mix(in srgb, var(--bs-body-bg) 72%, transparent);
  z-index: 2;
}

/* Older browsers without color-mix keep a usable, if flatter, overlay. */
@supports not (background: color-mix(in srgb, red 50%, transparent)) {
  .app-table-overlay { opacity: 0.75; background: var(--bs-body-bg); }
}

.app-table-sort {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  background: none;
  border: 0;
  padding: 0;
  font: inherit;
  color: inherit;
  cursor: pointer;
}

.app-table-sort:hover { color: var(--bs-primary); }

.app-table-sort:focus-visible {
  outline: 2px solid var(--bs-primary);
  outline-offset: 2px;
  border-radius: 2px;
}

/* ── Stacked cards on small screens ─────────────────────────── */
@media (max-width: 767.98px) {
  .app-table-stack thead {
    position: absolute;
    width: 1px;
    height: 1px;
    overflow: hidden;
    clip: rect(0 0 0 0);
    clip-path: inset(50%);
    white-space: nowrap;
  }

  .app-table-stack tbody tr {
    display: block;
    border: 1px solid var(--bs-border-color);
    border-radius: 0.5rem;
    padding: 0.25rem 0.85rem;
    margin-bottom: 0.75rem;
  }

  .app-table-stack tbody td {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 1rem;
    border: 0;
    padding: 0.4rem 0;
    text-align: left !important;
  }

  .app-table-stack tbody td + td {
    border-top: 1px solid var(--bs-border-color-translucent);
  }

  .app-table-stack tbody td::before {
    content: attr(data-label);
    flex: none;
    font-size: 0.78rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--bs-secondary-color);
  }

  /* Columns with no header (actions) and the empty-state row get no label. */
  .app-table-stack tbody td[data-label='']::before,
  .app-table-stack tbody td.app-table-empty::before {
    content: none;
  }

  .app-table-stack tbody td.app-table-empty {
    display: block;
    text-align: center !important;
  }

  .app-table-stack tbody tr:has(.app-table-empty) {
    border: 0;
  }
}
</style>
