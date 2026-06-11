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

  // Admin must select type before accessing any admin or dashboard page
  if (isAuthenticated && auth.isAdmin && !adminContext.hasSelected) {
    // Allow access to select-type page and non-admin personal pages
    const allowedWithoutType = ['admin-select-type', 'profile', 'login', 'register', 'cost-breakdown']
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

export default router
