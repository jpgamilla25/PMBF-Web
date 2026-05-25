<template>
  <Teleport to="body">
    <div v-if="show" class="modal-backdrop fade show" @click="$emit('close')"></div>
    <div
      v-if="show"
      class="modal fade show d-block"
      tabindex="-1"
      role="dialog"
      @click.self="$emit('close')"
      @keydown.escape="$emit('close')"
    >
      <div class="modal-dialog" :class="modalSizeClass" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">{{ title }}</h5>
            <button
              type="button"
              class="btn-close"
              aria-label="Close"
              @click="$emit('close')"
            ></button>
          </div>
          <div class="modal-body">
            <slot />
          </div>
          <div v-if="$slots.footer" class="modal-footer">
            <slot name="footer" />
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { computed, watch } from 'vue'

const props = defineProps({
  show: {
    type: Boolean,
    default: false,
  },
  title: {
    type: String,
    default: '',
  },
  size: {
    type: String,
    default: 'md',
    validator: (v) => ['sm', 'md', 'lg', 'xl'].includes(v),
  },
})

defineEmits(['close'])

const sizeMap = {
  sm: 'modal-sm',
  md: '',
  lg: 'modal-lg',
  xl: 'modal-xl',
}

const modalSizeClass = computed(() => sizeMap[props.size] || '')

watch(
  () => props.show,
  (val) => {
    if (val) {
      document.body.classList.add('modal-open')
    } else {
      document.body.classList.remove('modal-open')
    }
  }
)
</script>
