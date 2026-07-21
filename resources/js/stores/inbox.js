import { defineStore } from 'pinia'
import notificationsService from '../services/notifications'

/**
 * In-app notification inbox (the navbar bell).
 *
 * Named `inbox` to keep it distinct from the `notification` store, which
 * drives transient toasts.
 *
 * Polls rather than using websockets: the app has no broadcast driver
 * configured, and a 30s poll of a single COUNT query is cheap enough for a
 * few hundred members. Polling pauses while the tab is hidden so background
 * tabs don't hammer the API.
 */

const POLL_MS = 30000

let pollTimer = null
let visibilityBound = false

export const useInboxStore = defineStore('inbox', {
  state: () => ({
    items: [],
    unreadCount: 0,
    loading: false,
    lastFetchedAt: null,
  }),

  getters: {
    hasUnread: (state) => state.unreadCount > 0,
    // The bell caps at 99+; anything higher is noise.
    badge: (state) => (state.unreadCount > 99 ? '99+' : String(state.unreadCount)),
  },

  actions: {
    async fetchUnreadCount() {
      try {
        const { data } = await notificationsService.getUnreadCount()
        this.unreadCount = (data.data ?? data).count ?? 0
      } catch {
        // Offline or 401 — the axios interceptor handles auth failures.
      }
    },

    async fetchItems(perPage = 15) {
      this.loading = true
      try {
        const { data } = await notificationsService.getAll({ per_page: perPage })
        this.items = data.data ?? []
        if (data.meta?.unread_count !== undefined) {
          this.unreadCount = data.meta.unread_count
        }
        this.lastFetchedAt = new Date()
      } catch {
        // Leave the previous list in place rather than blanking the dropdown.
      } finally {
        this.loading = false
      }
    },

    async markAsRead(id) {
      const item = this.items.find((n) => n.id === id)
      if (!item || item.read_at) return

      // Optimistic: the dropdown closes on click, so waiting would look laggy.
      item.read_at = new Date().toISOString()
      this.unreadCount = Math.max(0, this.unreadCount - 1)

      try {
        await notificationsService.markAsRead(id)
      } catch {
        item.read_at = null
        this.unreadCount += 1
      }
    },

    async markAllAsRead() {
      const previous = this.items.map((n) => n.read_at)
      const now = new Date().toISOString()
      this.items.forEach((n) => { n.read_at = n.read_at ?? now })
      this.unreadCount = 0

      try {
        await notificationsService.markAllAsRead()
      } catch {
        this.items.forEach((n, i) => { n.read_at = previous[i] })
        this.fetchUnreadCount()
      }
    },

    startPolling() {
      if (pollTimer) return

      this.fetchUnreadCount()
      pollTimer = setInterval(() => {
        if (!document.hidden) this.fetchUnreadCount()
      }, POLL_MS)

      if (!visibilityBound) {
        visibilityBound = true
        // Catch up immediately when the user comes back to the tab.
        document.addEventListener('visibilitychange', () => {
          if (!document.hidden && pollTimer) this.fetchUnreadCount()
        })
      }
    },

    stopPolling() {
      clearInterval(pollTimer)
      pollTimer = null
    },

    reset() {
      this.stopPolling()
      this.items = []
      this.unreadCount = 0
      this.lastFetchedAt = null
    },
  },
})
