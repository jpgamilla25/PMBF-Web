<template>
  <Teleport to="body">
    <div v-if="show" class="modal-backdrop fade show" @click="requestClose('backdrop')"></div>
    <div
      v-if="show"
      ref="dialogRoot"
      class="modal fade show d-block"
      tabindex="-1"
      role="dialog"
      aria-modal="true"
      :aria-labelledby="titleId"
      @click.self="requestClose('backdrop')"
      @keydown.tab="onTab"
    >
      <div class="modal-dialog" :class="modalSizeClass" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 :id="titleId" class="modal-title">{{ title }}</h5>
            <button
              type="button"
              class="btn-close"
              aria-label="Close"
              @click="requestClose('button')"
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

<script>
// Module scope, deliberately. Top-level bindings in <script setup> compile into
// setup() and are therefore re-created per component instance — a counter there
// would restart at 0 for every modal and hand them all the same DOM id.
let idSeq = 0
let instanceSeq = 0

// Shared across instances so closing a stacked dialog doesn't unlock scrolling
// while another is still open, and so only the topmost dialog answers Escape:
// both listen on document, where stopPropagation cannot stop a sibling
// listener, so without this one keypress would close the whole stack.
const openStack = []

export function nextModalIds() {
  return { titleId: `app-modal-title-${++idSeq}`, instanceId: ++instanceSeq }
}
</script>

<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'

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
  /**
   * Whether a click on the backdrop closes the dialog.
   *
   * Data-entry dialogs pass false so a stray click outside cannot throw away a
   * filled-in form. Cancel, the header ✕ and Escape still close it, so the user
   * always leaves deliberately.
   */
  dismissable: {
    type: Boolean,
    default: true,
  },
})

const emit = defineEmits(['close'])

const { titleId, instanceId } = nextModalIds()

const sizeMap = { sm: 'modal-sm', md: '', lg: 'modal-lg', xl: 'modal-xl' }
const modalSizeClass = computed(() => sizeMap[props.size] || '')

// ── Focus management ───────────────────────────────────────────
// Without this the dialog opens with focus still on the page behind it: screen
// readers never announce it, Tab walks the background, and the Escape handler
// below could never fire because nothing inside held focus.

const dialogRoot = ref(null)
let lastFocused = null
let opened = false

const FOCUSABLE = [
  'a[href]',
  'button:not([disabled])',
  'input:not([disabled]):not([type="hidden"])',
  'select:not([disabled])',
  'textarea:not([disabled])',
  '[tabindex]:not([tabindex="-1"])',
].join(', ')

function focusables() {
  if (!dialogRoot.value) return []

  // getClientRects() rather than offsetParent — the dialog sits inside a
  // position:fixed ancestor, where offsetParent is unreliable.
  return Array.from(dialogRoot.value.querySelectorAll(FOCUSABLE))
    .filter((el) => el.getClientRects().length > 0)
}

function onTab(event) {
  const list = focusables()

  if (!list.length) {
    event.preventDefault()
    dialogRoot.value?.focus()
    return
  }

  const first = list[0]
  const last = list[list.length - 1]
  const active = document.activeElement

  if (event.shiftKey && (active === first || active === dialogRoot.value)) {
    event.preventDefault()
    last.focus()
  } else if (!event.shiftKey && active === last) {
    event.preventDefault()
    first.focus()
  }
}

function onDocumentKeydown(event) {
  if (event.key !== 'Escape') return

  // Only the dialog on top of the stack closes.
  if (openStack[openStack.length - 1] !== instanceId) return

  event.stopPropagation()
  requestClose('escape')
}

function requestClose(source) {
  // Only the backdrop is suppressed for non-dismissable dialogs.
  if (source === 'backdrop' && !props.dismissable) return

  emit('close')
}

async function activate() {
  if (opened) return
  opened = true

  lastFocused = document.activeElement
  openStack.push(instanceId)
  document.body.classList.add('modal-open')
  document.addEventListener('keydown', onDocumentKeydown, true)

  await nextTick()

  // Land on the first real control rather than the ✕, so a form dialog opens
  // ready to type into.
  const list = focusables()
  const target = list.find((el) => !el.classList.contains('btn-close')) ?? list[0] ?? dialogRoot.value
  target?.focus()
}

function deactivate() {
  if (!opened) return
  opened = false

  document.removeEventListener('keydown', onDocumentKeydown, true)

  const at = openStack.lastIndexOf(instanceId)
  if (at !== -1) openStack.splice(at, 1)
  if (openStack.length === 0) document.body.classList.remove('modal-open')

  const target = lastFocused
  lastFocused = null

  // Return the user to whatever opened the dialog.
  if (target && typeof target.focus === 'function' && document.contains(target)) {
    target.focus()
  }
}

watch(
  () => props.show,
  (open) => (open ? activate() : deactivate()),
  { immediate: true }
)

onBeforeUnmount(deactivate)
</script>
