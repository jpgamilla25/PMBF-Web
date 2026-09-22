import { reactive } from 'vue'

/**
 * Shared state for the single <AppConfirm /> host mounted in App.vue.
 *
 * This used to wrap window.confirm, which cannot be themed, ignores the app's
 * dark mode and blocks the tab. The promise-based API below is unchanged, so
 * existing callers keep working as they are.
 */
export const confirmState = reactive({
  open: false,
  title: 'Please confirm',
  message: '',
  confirmLabel: 'Confirm',
  cancelLabel: 'Cancel',
  variant: 'primary',

  /** Settles the promise handed back to the caller and closes the dialog. */
  resolve(answer) {
    this.open = false
    const settle = pending
    pending = null
    settle?.(answer)
  },
})

let pending = null

export function useConfirm() {
  /**
   * @param {string} message
   * @param {{title?: string, confirmLabel?: string, cancelLabel?: string, variant?: string}} [options]
   * @returns {Promise<boolean>}
   */
  function confirm(message = 'Are you sure?', options = {}) {
    // A second call while one is open answers the first as cancelled rather
    // than leaving its promise hanging forever.
    if (pending) confirmState.resolve(false)

    confirmState.title = options.title ?? 'Please confirm'
    confirmState.message = message
    confirmState.confirmLabel = options.confirmLabel ?? 'Confirm'
    confirmState.cancelLabel = options.cancelLabel ?? 'Cancel'
    confirmState.variant = options.variant ?? 'primary'
    confirmState.open = true

    return new Promise((resolve) => {
      pending = resolve
    })
  }

  return { confirm }
}
