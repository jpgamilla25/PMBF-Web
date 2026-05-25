<template>
  <div class="mb-3">
    <label v-if="label" class="form-label fw-medium small mb-1">{{ label }}</label>

    <div class="tag-input-wrapper" :class="{ focused }" @click="focusInput">
      <!-- Tags -->
      <span v-for="(tag, i) in tags" :key="i" class="tag-chip">
        <span class="tag-text">{{ tag }}</span>
        <button type="button" class="tag-remove" @click.stop="removeTag(i)" title="Remove">
          <i class="bi bi-x"></i>
        </button>
      </span>

      <!-- Input -->
      <input
        ref="inputRef"
        v-model="inputValue"
        type="text"
        class="tag-input"
        :placeholder="tags.length ? '' : placeholder"
        @keydown.enter.prevent="addFromInput"
        @keydown.comma.prevent="addFromInput"
        @keydown.tab.prevent="addFromInput"
        @keydown.delete="onBackspace"
        @focus="focused = true"
        @blur="onBlur"
      />
    </div>

    <!-- Add button -->
    <div class="d-flex align-items-center mt-2 gap-2">
      <button type="button" class="btn btn-outline-primary btn-sm" @click="showAddModal = true">
        <i class="bi bi-plus-lg me-1"></i>Add {{ itemLabel }}
      </button>
      <small v-if="suffix" class="text-muted">{{ suffix }}</small>
    </div>

    <small v-if="hint" class="text-muted d-block mt-1">{{ hint }}</small>
    <small v-if="error" class="text-danger d-block mt-1">{{ error }}</small>

    <!-- Add/Edit Modal -->
    <teleport to="body">
      <div v-if="showAddModal" class="modal-backdrop-custom" @click.self="closeModal">
        <div class="modal-box">
          <div class="modal-box-header">
            <h6 class="mb-0 fw-bold">
              <i class="bi bi-plus-circle me-1"></i>
              {{ editingIndex !== null ? 'Edit' : 'Add' }} {{ itemLabel }}
            </h6>
            <button type="button" class="btn-close btn-sm" @click="closeModal"></button>
          </div>
          <div class="modal-box-body">
            <input
              ref="modalInputRef"
              v-model="modalValue"
              :type="type === 'number' ? 'number' : 'text'"
              class="form-control"
              :class="{ 'is-invalid': modalError }"
              :placeholder="`Enter ${itemLabel.toLowerCase()}...`"
              :min="type === 'number' ? 1 : undefined"
              @keydown.enter.prevent="confirmModal"
              @input="modalError = ''"
              autofocus
            />
            <div v-if="modalError" class="invalid-feedback d-block">{{ modalError }}</div>
          </div>
          <div class="modal-box-footer">
            <button class="btn btn-secondary btn-sm" @click="closeModal">Cancel</button>
            <button class="btn btn-primary btn-sm" :disabled="!modalValue.toString().trim()" @click="confirmModal">
              <i class="bi bi-check-lg me-1"></i>{{ editingIndex !== null ? 'Update' : 'Add' }}
            </button>
          </div>
        </div>
      </div>
    </teleport>
  </div>
</template>

<script setup>
import { ref, computed, watch, nextTick } from 'vue'

const props = defineProps({
  modelValue: { type: String, default: '' },
  label: { type: String, default: '' },
  placeholder: { type: String, default: 'Type and press Enter...' },
  suffix: { type: String, default: '' },
  hint: { type: String, default: '' },
  error: { type: String, default: '' },
  itemLabel: { type: String, default: 'Item' },
  type: { type: String, default: 'text' }, // 'text' or 'number'
})

const emit = defineEmits(['update:modelValue'])

const inputRef = ref(null)
const modalInputRef = ref(null)
const inputValue = ref('')
const modalValue = ref('')
const focused = ref(false)
const showAddModal = ref(false)
const editingIndex = ref(null)
const modalError = ref('')

const tags = computed(() => {
  if (!props.modelValue) return []
  return props.modelValue.split(',').map(v => v.trim()).filter(v => v !== '')
})

function emitUpdate(newTags) {
  emit('update:modelValue', newTags.join(','))
}

function addFromInput() {
  const val = inputValue.value.trim()
  if (!val) return
  if (props.type === 'number' && isNaN(Number(val))) return

  const current = [...tags.value]
  if (!current.includes(val)) {
    current.push(val)
    emitUpdate(current)
  }
  inputValue.value = ''
}

function removeTag(index) {
  const current = [...tags.value]
  current.splice(index, 1)
  emitUpdate(current)
}

function editTag(index) {
  editingIndex.value = index
  modalValue.value = tags.value[index]
  showAddModal.value = true
  nextTick(() => modalInputRef.value?.focus())
}

function onBackspace() {
  if (inputValue.value === '' && tags.value.length > 0) {
    removeTag(tags.value.length - 1)
  }
}

function onBlur() {
  focused.value = false
  // Auto-add if there's text
  if (inputValue.value.trim()) {
    addFromInput()
  }
}

function focusInput() {
  inputRef.value?.focus()
}

function closeModal() {
  showAddModal.value = false
  modalValue.value = ''
  editingIndex.value = null
  modalError.value = ''
}

function confirmModal() {
  const val = modalValue.value.toString().trim()
  modalError.value = ''

  if (!val) {
    modalError.value = 'Please enter a value.'
    return
  }

  if (props.type === 'number') {
    if (isNaN(Number(val)) || Number(val) <= 0) {
      modalError.value = 'Please enter a valid positive number.'
      return
    }
  }

  const cleanVal = props.type === 'number' ? String(Number(val)) : val
  const current = [...tags.value]

  // Check duplicate
  const isDuplicate = current.some((t, i) => t === cleanVal && i !== editingIndex.value)
  if (isDuplicate) {
    modalError.value = `"${cleanVal}" already exists.`
    return
  }

  if (editingIndex.value !== null) {
    current[editingIndex.value] = cleanVal
  } else {
    current.push(cleanVal)
  }

  emitUpdate(current)
  closeModal()
}

// Focus modal input when opened
watch(showAddModal, async (val) => {
  if (val) {
    await nextTick()
    modalInputRef.value?.focus()
  }
})
</script>

<style scoped>
.tag-input-wrapper {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  padding: 6px 10px;
  border: 1px solid #dee2e6;
  border-radius: 0.375rem;
  min-height: 38px;
  cursor: text;
  background: #fff;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.tag-input-wrapper.focused {
  border-color: #3b82f6;
  box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.25);
}

.tag-chip {
  display: inline-flex;
  align-items: center;
  background: #e0e7ff;
  color: #1e40af;
  border-radius: 6px;
  padding: 2px 6px 2px 10px;
  font-size: 0.8rem;
  font-weight: 600;
  gap: 4px;
  animation: tagIn 0.15s ease;
}
@keyframes tagIn {
  from { transform: scale(0.8); opacity: 0; }
  to { transform: scale(1); opacity: 1; }
}

.tag-text {
  cursor: default;
}

.tag-remove {
  border: none;
  background: transparent;
  color: #6366f1;
  cursor: pointer;
  padding: 0 2px;
  font-size: 0.9rem;
  line-height: 1;
  border-radius: 3px;
  display: flex;
  align-items: center;
}
.tag-remove:hover {
  background: #c7d2fe;
  color: #dc2626;
}

.tag-input {
  border: none;
  outline: none;
  flex: 1;
  min-width: 80px;
  font-size: 0.875rem;
  padding: 2px 0;
  background: transparent;
}

/* Modal */
.modal-backdrop-custom {
  position: fixed;
  top: 0; left: 0; right: 0; bottom: 0;
  background: rgba(0,0,0,0.4);
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: center;
  animation: fadeIn 0.15s ease;
}
@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

.modal-box {
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 20px 60px rgba(0,0,0,0.2);
  width: 100%;
  max-width: 400px;
  animation: slideUp 0.2s ease;
}
@keyframes slideUp {
  from { transform: translateY(20px); opacity: 0; }
  to { transform: translateY(0); opacity: 1; }
}

.modal-box-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  border-bottom: 1px solid #e5e7eb;
}

.modal-box-body {
  padding: 20px;
}

.modal-box-footer {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  padding: 12px 20px;
  border-top: 1px solid #e5e7eb;
}
</style>
