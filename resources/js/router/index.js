import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useAdminContextStore } from '../stores/adminContext'

const routes = [
  // Guest routes
  {
    path: '/login',
    name: 'login',
    component: () => import('../views/auth/LoginView.vue'),
    meta: { guest: true },
  },
  {
    path: '/register',
    name: 'register',
    component: () => import('../views/auth/RegisterView.vue'),
    meta: { guest: true },
  },
  {
    path: '/pin-login',
    name: 'pin-login',
    component: () => import('../views/auth/PinLoginView.vue'),
    meta: { guest: true },
  },
  {
    path: '/pin-setup',
    name: 'pin-setup',
    component: () => import('../views/auth/PinSetupView.vue'),
    meta: { requiresAuth: true },
  },

  // Admin: select member type (shown after login)
  {
    path: '/admin/select-type',
    name: 'admin-select-type',
    component: () => import('../views/admin/AdminSelectTypeView.vue'),
    meta: { requiresAuth: true, requiresAdmin: true },
  },

  // Dashboard
  {
    path: '/dashboard',
    name: 'dashboard',
    component: () => import('../views/dashboard/DashboardView.vue'),
    meta: { requiresAuth: true },
  },

  // Loans
  {
    path: '/loans',
    name: 'loans',
    component: () => import('../views/loans/LoansView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/loans/new',
    name: 'loan-create',
    component: () => import('../views/loans/LoanCreateView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/loans/:id',
    name: 'loan-detail',
    component: () => import('../views/loans/LoanDetailView.vue'),
    meta: { requiresAuth: true },
  },

  // Approvals (staff)
  {
    path: '/approvals',
    name: 'approvals',
    component: () => import('../views/approval/ApprovalsView.vue'),
    meta: { requiresAuth: true, requiresStaff: true },
  },
  {
    path: '/approvals/:id',
    name: 'approval-detail',
    component: () => import('../views/approval/ApprovalDetailView.vue'),
    meta: { requiresAuth: true, requiresStaff: true },
  },

  // Admin
  {
    path: '/admin/user-types',
    name: 'admin-user-types',
    component: () => import('../views/admin/AdminUserTypesView.vue'),
    meta: { requiresAuth: true, requiresAdmin: true },
  },
  {
    path: '/admin/members',
    name: 'admin-members',
    component: () => import('../views/admin/AdminMembersView.vue'),
    meta: { requiresAuth: true, requiresAdmin: true },
  },
  {
    path: '/admin/members/:id',
    name: 'admin-member-detail',
    component: () => import('../views/admin/AdminMemberDetailView.vue'),
    meta: { requiresAuth: true, requiresAdmin: true },
  },
  {
    path: '/admin/loans',
    name: 'admin-loans',
    component: () => import('../views/admin/AdminLoansView.vue'),
    meta: { requiresAuth: true, requiresAdmin: true },
  },
  {
    path: '/admin/payments',
    name: 'admin-payments',
    component: () => import('../views/admin/AdminPaymentsView.vue'),
    meta: { requiresAuth: true, requiresAdmin: true },
  },
  {
    path: '/admin/config',
    name: 'admin-config',
    component: () => import('../views/admin/AdminConfigView.vue'),
    meta: { requiresAuth: true, requiresAdmin: true },
  },
  {
    path: '/admin/config-history',
    name: 'admin-config-history',
    component: () => import('../views/admin/AdminConfigHistoryView.vue'),
    meta: { requiresAuth: true, requiresAdmin: true },
  },
  {
    path: '/admin/exemptions',
    name: 'admin-exemptions',
    component: () => import('../views/admin/AdminExemptionsView.vue'),
    meta: { requiresAuth: true, requiresAdmin: true },
  },
  {
    path: '/admin/activity-logs',
    name: 'admin-activity-logs',
    component: () => import('../views/admin/AdminActivityLogsView.vue'),
    meta: { requiresAuth: true, requiresAdmin: true },
  },
  {
    path: '/admin/mobile-settings',
    name: 'admin-mobile-settings',
    component: () => import('../views/admin/AdminMobileSettingsView.vue'),
    meta: { requiresAuth: true, requiresAdmin: true },
  },
  {
    path: '/admin/reports',
    name: 'admin-reports',
    component: () => import('../views/admin/AdminReportsView.vue'),
    meta: { requiresAuth: true, requiresAdmin: true },
  },
  {
    path: '/admin/reports/loans',
    name: 'admin-report-loans',
    component: () => import('../views/admin/AdminReportLoansView.vue'),
    meta: { requiresAuth: true, requiresAdmin: true },
  },
  {
    path: '/admin/reports/payments',
    name: 'admin-report-payments',
    component: () => import('../views/admin/AdminReportPaymentsView.vue'),
    meta: { requiresAuth: true, requiresAdmin: true },
  },
  {
    path: '/admin/reports/members',
    name: 'admin-report-members',
    component: () => import('../views/admin/AdminReportMembersView.vue'),
    meta: { requiresAuth: true, requiresAdmin: true },
  },
  {
    path: '/admin/reports/shares',
    name: 'admin-report-shares',
    component: () => import('../views/admin/AdminReportSharesView.vue'),
    meta: { requiresAuth: true, requiresAdmin: true },
  },
  {
    path: '/admin/reports/ledger',
    name: 'admin-report-ledger',
    component: () => import('../views/admin/AdminReportLedgerView.vue'),
    meta: { requiresAuth: true, requiresAdmin: true },
  },
  {
    path: '/admin/reports/notice-of-deduction',
    name: 'admin-report-notice-of-deduction',
    component: () => import('../views/admin/AdminReportNoticeOfDeductionView.vue'),
    meta: { requiresAuth: true, requiresAdmin: true },
  },
  {
    path: '/admin/shares',
    name: 'admin-shares',
    component: () => import('../views/admin/AdminSharesView.vue'),
    meta: { requiresAuth: true, requiresAdmin: true },
  },
  {
    path: '/admin/schedule',
    name: 'admin-schedule',
    component: () => import('../views/admin/AdminScheduleView.vue'),
    meta: { requiresAuth: true, requiresAdmin: true },
  },
  {
    path: '/admin/import',
    name: 'admin-import',
    component: () => import('../views/admin/AdminImportView.vue'),
    meta: { requiresAuth: true, requiresAdmin: true },
  },

  // Member
  {
    path: '/profile',
    name: 'profile',
    component: () => import('../views/member/ProfileView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/dependents',
    name: 'dependents',
    component: () => import('../views/member/DependentsView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/claims',
    name: 'claims',
    component: () => import('../views/member/ClaimsView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/benefits',
    name: 'benefits',
    component: () => import('../views/member/BenefitsView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/shares',
    name: 'shares',
    component: () => import('../views/member/SharesView.vue'),
    meta: { requiresAuth: true },
  },

  // Cost Breakdown (public proposal page — no auth required)
  {
    path: '/cost-breakdown',
    name: 'cost-breakdown',
    component: () => import('../views/CostBreakdownView.vue'),
  },

  // Catch-all
  {
    path: '/:pathMatch(.*)*',
    redirect: '/dashboard',
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach(async (to, from, next) => {
  const auth = useAuthStore()
  const adminContext = useAdminContextStore()

  // Initialize auth from localStorage
  if (!auth.user && auth.token) {
    await auth.fetchUser()
  }
  if (!auth.token) {
    const storedToken = localStorage.getItem('pmbf_token')
    if (storedToken) {
      auth.token = storedToken
      await auth.fetchUser()
    }
  }

  const isAuthenticated = auth.isAuthenticated

  // Guest-only → redirect to dashboard
  if (to.meta.guest && isAuthenticated) {
    // Admin who hasn't selected a type → go to select-type
    if (auth.isAdmin && !adminContext.hasSelected) {
      return next({ name: 'admin-select-type' })
    }
    return next({ name: 'dashboard' })
  }

  // Requires auth → redirect to login
  if (to.meta.requiresAuth && !isAuthenticated) {
    return next({ name: 'login', query: { redirect: to.fullPath } })
  }

  // A returning user who set a PIN on this browser goes straight to the PIN
  // screen. `?otp=1` is the escape hatch used by "sign in with Employee ID".
  if (to.name === 'login' && !isAuthenticated && auth.pinHint && !to.query.otp) {
    return next({ name: 'pin-login', query: to.query })
  }

  // Admin must select type before accessing any admin or dashboard page
  if (isAuthenticated && auth.isAdmin && !adminContext.hasSelected) {
    // Allow access to select-type page and non-admin personal pages
    const allowedWithoutType = ['admin-select-type', 'profile', 'login', 'register', 'pin-setup', 'cost-breakdown']
    if (!allowedWithoutType.includes(to.name)) {
      return next({ name: 'admin-select-type' })
    }
  }

  // Admin-only routes
  if (to.meta.requiresAdmin && !auth.isAdmin) {
    return next({ name: 'dashboard' })
  }

  // Staff-only routes
  if (to.meta.requiresStaff && !auth.isStaff) {
    return next({ name: 'dashboard' })
  }

  next()
})

/**
 * Recover from a stale build.
 *
 * Route components are lazy-loaded from content-hashed files. After a deploy
 * the old chunk names no longer exist, so a tab that was already open fails
 * with "Failed to fetch dynamically imported module" the moment the user
 * navigates — which reads as the page being broken.
 *
 * Reloading pulls the new index and its new chunk names. The sessionStorage
 * flag means a genuinely missing chunk reloads once instead of looping.
 */
const RELOAD_FLAG = 'pmbf_chunk_reload'

const isStaleChunkError = (error) =>
  /Failed to fetch dynamically imported module|Importing a module script failed|error loading dynamically imported module/i
    .test(error?.message ?? '')

router.onError((error, to) => {
  if (!isStaleChunkError(error)) return

  if (sessionStorage.getItem(RELOAD_FLAG)) {
    // Already retried — reloading again would spin.
    console.error('Chunk still unavailable after reload:', error)
    return
  }

  sessionStorage.setItem(RELOAD_FLAG, '1')
  window.location.assign(to?.fullPath ?? window.location.pathname)
})

// A completed navigation means the current build is intact; clear the guard
// so a future deploy can recover the same way.
router.afterEach(() => sessionStorage.removeItem(RELOAD_FLAG))

export default router
