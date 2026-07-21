<template>
  <div class="pin-input" :class="{ 'pin-shake': shaking }">
    <div class="d-flex justify-content-center gap-2 gap-sm-3">
      <div
        v-for="i in length"
        :key="i"
        class="pin-box"
        :class="{
          filled: digits[i - 1] !== '',
          active: focused && i - 1 === activeIndex,
          'is-error': error,
        }"
        @click="focusAt(i - 1)"
      >
        <span v-if="digits[i - 1] !== ''" class="pin-dot"></span>
      </div>
    </div>

    <!--
      One real input behind the boxes: keeps the native mobile numeric keypad,
      OS autofill and paste working while the boxes stay purely presentational.
    -->
    <input
      ref="inputEl"
      :value="modelValue"
      type="text"
      inputmode="numeric"
      autocomplete="one-time-code"
      :maxlength="length"
      :disabled="disabled"
      class="pin-hidden-input"
      @input="onInput"
      @keydown="onKeydown"
      @focus="focused = true"
      @blur="focused = false"
    />

    <p v-if="error" class="text-danger small text-center mt-2 mb-0">{{ error }}</p>
    <p v-else-if="hint" class="text-muted small text-center mt-2 mb-0">{{ hint }}</p>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, nextTick } from 'vue'

const props = defineProps({
  modelValue: { type: String, default: '' },
  length: { type: Number, default: 4 },
  error: { type: String, default: '' },
  hint: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
  autofocus: { type: Boolean, default: true },
})

const emit = defineEmits(['update:modelValue', 'complete'])

const inputEl = ref(null)
const focused = ref(false)
const shaking = ref(false)

const digits = computed(() =>
  Array.from({ length: props.length }, (_, i) => props.modelValue[i] ?? '')
)

const activeIndex = computed(() =>
  Math.min(props.modelValue.length, props.length - 1)
)

function onInput(e) {
  const clean = e.target.value.replace(/\D/g, '').slice(0, props.length)
  // Keep the DOM in sync when non-digits were stripped, otherwise the input
  // holds characters the model never sees.
  e.target.value = clean
  emit('update:modelValue', clean)
  if (clean.length === props.length) emit('complete', clean)
}

function onKeydown(e) {
  // Block the characters `inputmode="numeric"` still lets through on desktop.
  if (e.key.length === 1 && !/\d/.test(e.key) && !e.ctrlKey && !e.metaKey) {
    e.preventDefault()
  }
}

function focus() {
  inputEl.value?.focus()
}

function focusAt() {
  // The single input has no per-box caret; tapping any box just focuses it.
  focus()
}

/** Play the wrong-PIN shake and clear the boxes. */
function shake() {
  shaking.value = true
  setTimeout(() => {
    shaking.value = false
    emit('update:modelValue', '')
    focus()
  }, 450)
}

watch(() => props.error, (val) => { if (val) shake() })

onMounted(() => {
  if (props.autofocus) nextTick(focus)
})

defineExpose({ focus, shake })
</script>

<style scoped>
.pin-input { position: relative; }

.pin-box {
  width: 56px;
  height: 64px;
  border: 2px solid #e5e7eb;
  border-radius: 14px;
  background: #f9fafb;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: border-color .15s, background .15s, transform .15s;
}

.pin-box.filled {
  border-color: #1e40af;
  background: #eff6ff;
}

.pin-box.active {
  border-color: #3b82f6;
  background: #fff;
  transform: translateY(-2px);
  box-shadow: 0 0 0 4px rgba(59, 130, 246, .15);
}

.pin-box.is-error {
  border-color: #dc2626;
  background: #fef2f2;
}

.pin-dot {
  width: 14px;
  height: 14px;
  border-radius: 50%;
  background: #1e40af;
}

.pin-box.is-error .pin-dot { background: #dc2626; }

/* Sits invisibly over the boxes so a tap anywhere opens the keypad. */
.pin-hidden-input {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 64px;
  opacity: 0;
  border: 0;
  background: transparent;
  font-size: 16px; /* stops iOS Safari zooming on focus */
  caret-color: transparent;
}

@keyframes pin-shake {
  0%, 100% { transform: translateX(0); }
  20% { transform: translateX(-8px); }
  40% { transform: translateX(8px); }
  60% { transform: translateX(-5px); }
  80% { transform: translateX(5px); }
}

.pin-shake { animation: pin-shake .45s ease; }

@media (max-width: 400px) {
  .pin-box { width: 48px; height: 56px; }
}
</style>
