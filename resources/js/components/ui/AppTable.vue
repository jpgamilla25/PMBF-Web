<template>
  <div class="position-relative">
    <!-- Loading overlay -->
    <div
      v-if="loading"
      class="position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center"
      style="background: rgba(255,255,255,0.7); z-index: 2;"
    >
      <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading...</span>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th
              v-for="col in columns"
              :key="col.key"
              :class="col.class"
            >
              <slot :name="`header(${col.key})`" :column="col">{{ col.label }}</slot>
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!items || items.length === 0">
            <td :colspan="columns.length" class="text-center text-muted py-4">
              {{ emptyText }}
            </td>
          </tr>
          <tr v-for="(item, index) in items" :key="item.id ?? index">
            <td v-for="col in columns" :key="col.key" :class="col.class">
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
defineProps({
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
})
</script>
