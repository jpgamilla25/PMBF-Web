import { defineStore } from 'pinia'
import { useAuthStore } from './auth'

export const useAdminContextStore = defineStore('adminContext', {
  state: () => ({
    memberType: sessionStorage.getItem('pmbf_admin_type') || null,
  }),

  getters: {
    /** Has the admin selected a type this session? */
    hasSelected: (state) => state.memberType !== null,

    label: (state) => {
      const labels = {
        all: 'All Members',
        Permanent: 'Permanent',
        'Contract of Service': 'Contract of Service',
        'Non-Member': 'Non-Members',
      }
      return labels[state.memberType] || 'All Members'
    },

    badgeColor: (state) => {
      const colors = {
        all: 'primary',
        Permanent: 'success',
        'Contract of Service': 'warning',
        'Non-Member': 'secondary',
      }
      return colors[state.memberType] || 'primary'
    },

    filterParam: (state) => {
      if (!state.memberType || state.memberType === 'all') return {}
      return { employment_type: state.memberType }
    },
  },

  actions: {
    setType(type) {
      this.memberType = type
      sessionStorage.setItem('pmbf_admin_type', type)
      // Refresh approval badge counts for new context
      const authStore = useAuthStore()
      authStore.fetchPendingApprovalCount()
    },

    /** Reset on logout */
    clear() {
      this.memberType = null
      sessionStorage.removeItem('pmbf_admin_type')
    },
  },
})
