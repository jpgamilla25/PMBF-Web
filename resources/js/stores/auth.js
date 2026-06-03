import { defineStore } from 'pinia'
import { useAdminContextStore } from './adminContext'
import { watch } from 'vue'
import authService from '../services/auth'
import approvalsService from '../services/approvals'
import loansService from '../services/loans'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    token: null,
    loading: false,
    pendingApprovalCount: 0,
    releaseCount: 0,
    coMakerPendingCount: 0,
  }),

  getters: {
    isAuthenticated: (state) => !!state.token && !!state.user,
    isAdmin: (state) => state.user?.role === 'admin',
    isStaff: (state) =>
      ['admin', 'receiver', 'loan_committee', 'chairperson'].includes(state.user?.role),
    isPermanent: (state) => state.user?.employment_type === 'Permanent',
    isReceiver: (state) => state.user?.role === 'receiver',
    isLoanCommittee: (state) => state.user?.role === 'loan_committee',
    isChairperson: (state) => state.user?.role === 'chairperson',
    fullName: (state) => {
      if (!state.user) return ''
      return `${state.user.first_name ?? ''} ${state.user.last_name ?? ''}`.trim()
    },
    canApprove: (state) =>
      ['receiver', 'loan_committee', 'chairperson', 'admin'].includes(state.user?.role),
  },

  actions: {
    async fetchUser() {
      this.loading = true
      try {
        const { data } = await authService.getMe()
        this.user = data.data ?? data
        this.fetchPendingApprovalCount()
      } catch {
        this.clearAuth()
      } finally {
        this.loading = false
      }
    },

    async fetchPendingApprovalCount() {
      if (!this.canApprove) return
      try {
        const adminContext = useAdminContextStore()
        const contextFilter = (this.user?.role === 'admin' && adminContext.memberType && adminContext.memberType !== 'all')
          ? { employment_type: adminContext.memberType }
          : {}

        // For admin/receiver, the pending API already includes chairperson_approved (for release)
        const pendingRes = await approvalsService.getApprovals({ status: 'pending', per_page: 1, ...contextFilter })
        const totalPending = (pendingRes.data.meta ?? pendingRes.data).total ?? 0

        // Split into pending (red) and for-release (blue) counts
        const role = this.user?.role
        if (role === 'admin' || role === 'receiver') {
          const releaseRes = await approvalsService.getApprovals({ status: 'chairperson_approved', per_page: 1, ...contextFilter })
          this.releaseCount = (releaseRes.data.meta ?? releaseRes.data).total ?? 0
          this.pendingApprovalCount = totalPending - this.releaseCount
        } else {
          this.releaseCount = 0
          this.pendingApprovalCount = totalPending
        }
      } catch {
        // ignore
      }
    },

    /**
     * Count of loans awaiting this user's co-maker consent (drives the
     * "My Loans" badge so co-makers know they have something to act on).
     */
    async fetchCoMakerPendingCount() {
      try {
        const { data } = await loansService.getPendingCoMaker()
        const list = data.data ?? data
        this.coMakerPendingCount = Array.isArray(list) ? list.length : 0
      } catch {
        // ignore
      }
    },

    /**
     * Set user + token after OTP-verified login or QR login.
     */
    setAuth(user, token) {
      this.user = user
      this.setToken(token)
      this.fetchPendingApprovalCount()
      this.fetchCoMakerPendingCount()
    },

    async logout() {
      try {
        await authService.logout()
      } catch {
        // ignore
      } finally {
        this.clearAuth()
      }
    },

    setToken(token) {
      this.token = token
      if (token) {
        localStorage.setItem('pmbf_token', token)
      } else {
        localStorage.removeItem('pmbf_token')
      }
    },

    clearAuth() {
      this.user = null
      this.token = null
      localStorage.removeItem('pmbf_token')
      // Clear admin context on logout
      const adminContext = useAdminContextStore()
      adminContext.clear()
    },

    async initAuth() {
      const token = localStorage.getItem('pmbf_token')
      if (token) {
        this.token = token
        await this.fetchUser()
        await this.fetchPendingApprovalCount()
        await this.fetchCoMakerPendingCount()
      }
    },
  },
})
