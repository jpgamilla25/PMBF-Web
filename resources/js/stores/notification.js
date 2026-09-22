import { defineStore } from 'pinia'

let nextId = 0

/**
 * Dismiss timers, kept outside the reactive store — a pending setTimeout handle
 * is not state anyone renders, and making it reactive only invites churn.
 *
 * @type {Map<number, {handle: number, startedAt: number, remaining: number}>}
 */
const timers = new Map()

/** Beyond this the stack covers the screen rather than informing anyone. */
const MAX_VISIBLE = 4

export const useNotificationStore = defineStore('notification', {
  state: () => ({
    notifications: [],
  }),

  actions: {
    _add(type, message, timeout = 4000) {
      const id = ++nextId

      // `alert` interrupts a screen reader mid-sentence; routine successes
      // should wait their turn, failures should not.
      const politeness = type === 'danger' || type === 'warning' ? 'assertive' : 'polite'

      this.notifications.push({ id, type, message, timeout, politeness })

      while (this.notifications.length > MAX_VISIBLE) {
        this.remove(this.notifications[0].id)
      }

      this._schedule(id, timeout)

      return id
    },

    _schedule(id, remaining) {
      this._clearTimer(id)

      timers.set(id, {
        handle: setTimeout(() => this.remove(id), remaining),
        startedAt: Date.now(),
        remaining,
      })
    },

    _clearTimer(id) {
      const timer = timers.get(id)
      if (timer) {
        clearTimeout(timer.handle)
        timers.delete(id)
      }
    },

    /**
     * Hold a toast open while the pointer or keyboard focus is on it, so a
     * message can be read — or a value copied out of it — without racing the
     * clock. WCAG 2.2.1.
     */
    pause(id) {
      const timer = timers.get(id)
      if (!timer) return

      clearTimeout(timer.handle)
      timer.remaining = Math.max(1000, timer.remaining - (Date.now() - timer.startedAt))
      timer.handle = null
    },

    resume(id) {
      const timer = timers.get(id)
      if (!timer || timer.handle) return

      this._schedule(id, timer.remaining)
    },

    success(message) {
      return this._add('success', message)
    },

    error(message) {
      return this._add('danger', message, 6000)
    },

    warning(message) {
      return this._add('warning', message)
    },

    info(message) {
      return this._add('info', message)
    },

    remove(id) {
      this._clearTimer(id)

      const index = this.notifications.findIndex((n) => n.id === id)
      if (index !== -1) {
        this.notifications.splice(index, 1)
      }
    },
  },
})
