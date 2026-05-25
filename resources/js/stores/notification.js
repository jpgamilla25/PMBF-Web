import { defineStore } from 'pinia'

let nextId = 0

export const useNotificationStore = defineStore('notification', {
  state: () => ({
    notifications: [],
  }),

  actions: {
    _add(type, message, timeout = 4000) {
      const id = ++nextId
      this.notifications.push({ id, type, message, timeout })
      setTimeout(() => {
        this.remove(id)
      }, timeout)
      return id
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
      const index = this.notifications.findIndex((n) => n.id === id)
      if (index !== -1) {
        this.notifications.splice(index, 1)
      }
    },
  },
})
