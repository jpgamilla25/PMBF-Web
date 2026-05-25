<template>
  <div class="mb-3">
    <label v-if="label" :for="id" class="form-label fw-medium">{{ label }}</label>
    <v-select
      :id="id"
      :model-value="selectedOption"
      :options="options"
      :filterable="!remote"
      :loading="loading"
      :placeholder="placeholder"
      :disabled="disabled"
      :reduce="opt => opt.value"
      label="label"
      @update:model-value="onSelect"
      @search="onSearch"
    >
      <template #no-options="{ search }">
        <span v-if="search && remote">Type to search...</span>
        <span v-else-if="search">No results for "{{ search }}"</span>
        <span v-else>{{ emptyText }}</span>
      </template>
      <template #option="opt">
        <div>
          <strong>{{ opt.label }}</strong>
          <div v-if="opt.sublabel" class="text-muted" style="font-size: 0.75rem;">{{ opt.sublabel }}</div>
        </div>
      </template>
      <template #selected-option="opt">
        {{ opt.label }}
      </template>
    </v-select>
    <div v-if="error" class="text-danger small mt-1">{{ error }}</div>
    <small v-if="hint" class="text-muted">{{ hint }}</small>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import vSelect from 'vue-select'
import 'vue-select/dist/vue-select.css'

const props = defineProps({
  modelValue: { type: [String, Number, null], default: null },
  label: { type: String, default: '' },
  options: { type: Array, default: () => [] },
  placeholder: { type: String, default: 'Select...' },
  error: { type: String, default: '' },
  hint: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
  remote: { type: Boolean, default: false },
  emptyText: { type: String, default: 'No options available' },
})

const emit = defineEmits(['update:modelValue', 'search'])

const id = `search-select-${Math.random().toString(36).slice(2, 8)}`

const selectedOption = computed(() => {
  if (!props.modelValue) return null
  return props.options.find(o => String(o.value) === String(props.modelValue)) || null
})

function onSelect(val) {
  emit('update:modelValue', val)
}

let searchTimeout = null
function onSearch(query) {
  if (!props.remote) return
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    emit('search', query)
  }, 300)
}
</script>

<style>
/* Override vue-select to match Bootstrap */
.vs__dropdown-toggle {
  border: 1px solid #dee2e6 !important;
  border-radius: 0.375rem !important;
  padding: 4px 8px !important;
  min-height: 38px !important;
  background: #fff !important;
}
.vs__dropdown-toggle:focus-within {
  border-color: #3b82f6 !important;
  box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.25) !important;
}
.vs__search {
  font-size: 0.875rem !important;
}
.vs__selected {
  font-size: 0.875rem !important;
}
.vs__dropdown-menu {
  border: 1px solid #dee2e6 !important;
  border-radius: 0.375rem !important;
  box-shadow: 0 4px 12px rgba(0,0,0,0.1) !important;
  font-size: 0.875rem !important;
}
.vs__dropdown-option--highlight {
  background: #dbeafe !important;
  color: #1e40af !important;
}
.vs__clear svg,
.vs__open-indicator {
  fill: #9ca3af !important;
}
</style>
