<template>
  <div class="pmbf-app">
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark pmbf-navbar fixed-top">
      <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="/dashboard">
          <i class="bi bi-bank me-2"></i>PMBF
        </a>

        <button
          class="navbar-toggler border-0"
          type="button"
          @click="sidebarOpen = !sidebarOpen"
        >
          <span class="navbar-toggler-icon"></span>
        </button>

        <div class="d-flex align-items-center ms-auto">
          <!-- Cost Breakdown link -->
          <a
            v-if="false"
            href="/cost-breakdown"
            target="_blank"
            rel="noopener"
            class="text-white me-3 d-flex align-items-center gap-1 text-decoration-none cost-link"
            title="Project Cost Breakdown"
          >
            <i class="bi bi-bar-chart-line fs-5"></i>
            <span class="d-none d-lg-inline" style="font-size:0.8rem;opacity:.85;">Budget</span>
          </a>

          <!-- Notification bell -->
          <div class="dropdown me-3">
            <a
              href="#"
              class="text-white position-relative"
              role="button"
              data-bs-toggle="dropdown"
              data-bs-auto-close="outside"
              aria-expanded="false"
              @click="fetchNotifications"
            >
              <i class="bi bi-bell fs-5"></i>
              <span
                v-if="unreadCount > 0"
                class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
                style="font-size: 0.6rem;"
              >
                {{ unreadCount > 99 ? '99+' : unreadCount }}
              </span>
            </a>
            <div class="dropdown-menu dropdown-menu-end shadow notification-dropdown p-0" style="width: 360px; max-height: 440px;">
              <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom">
                <span class="fw-semibold small">Notifications</span>
                <a
                  v-if="unreadCount > 0"
                  href="#"
                  class="text-primary small text-decoration-none"
                  @click.prevent="markAllRead"
                >
                  Mark all read
                </a>
              </div>
              <div class="overflow-auto" style="max-height: 370px;">
                <div v-if="notificationsLoading" class="text-center py-4">
                  <div class="spinner-border spinner-border-sm text-muted"></div>
                </div>
                <div v-else-if="notifications.length === 0" class="text-center py-4 text-muted small">
                  No notifications
                </div>
                <a
                  v-for="n in notifications"
                  :key="n.id"
                  href="#"
                  class="d-flex align-items-start px-3 py-2 text-decoration-none border-bottom notification-item"
                  :class="{ 'bg-primary bg-opacity-10': !n.read_at }"
                  @click.prevent="handleNotificationClick(n)"
                >
                  <i :class="n.icon || 'bi-bell'" class="bi me-2 mt-1" :style="{ color: n.read_at ? '#9ca3af' : '#1e40af' }"></i>
                  <div class="flex-grow-1 small">
                    <div class="fw-semibold" :class="{ 'text-dark': !n.read_at, 'text-muted': n.read_at }">{{ n.title }}</div>
                    <div class="text-muted" style="font-size: 0.78rem;">{{ n.message }}</div>
                    <div class="text-muted" style="font-size: 0.7rem;">{{ timeAgo(n.created_at) }}</div>
                  </div>
                </a>
              </div>
            </div>
          </div>

          <!-- User dropdown -->
          <div class="dropdown">
            <a
              class="d-flex align-items-center text-white text-decoration-none dropdown-toggle"
              href="#"
              role="button"
              data-bs-toggle="dropdown"
              aria-expanded="false"
            >
              <div
                class="rounded-circle bg-white bg-opacity-25 d-flex align-items-center justify-content-center me-2"
                style="width: 32px; height: 32px;"
              >
                <i class="bi bi-person-fill text-white"></i>
              </div>
              <div class="d-none d-md-block">
                <div class="small fw-semibold lh-1">{{ authStore.fullName }}</div>
                <div class="small opacity-75 lh-1 mt-1" style="font-size: 0.7rem;">
                  {{ userRoleLabel }}
                </div>
              </div>
            </a>
            <ul class="dropdown-menu dropdown-menu-end shadow">
              <li>
                <router-link class="dropdown-item" to="/profile">
                  <i class="bi bi-person me-2"></i>My Profile
                </router-link>
              </li>
              <li><hr class="dropdown-divider" /></li>
              <li>
                <a class="dropdown-item text-danger" href="#" @click.prevent="handleLogout">
                  <i class="bi bi-box-arrow-right me-2"></i>Logout
                </a>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </nav>

    <div class="pmbf-body">
      <!-- Sidebar -->
      <aside class="pmbf-sidebar" :class="{ open: sidebarOpen }">
        <div class="pmbf-sidebar-inner">
          <nav class="nav flex-column py-3">
            <!-- Main menu -->
            <div class="sidebar-section-label">MAIN MENU</div>
            <router-link
              v-for="item in mainMenuItems"
              :key="item.to"
              :to="item.to"
              class="nav-link sidebar-link"
              :class="{ active: $route.path === item.to }"
              @click="sidebarOpen = false"
            >
              <i :class="item.icon" class="me-2"></i>{{ item.label }}
            </router-link>

            <!-- Approvals section (staff) -->
            <template v-if="authStore.canApprove">
              <div class="sidebar-section-label mt-3">APPROVALS</div>
              <router-link
                to="/approvals"
                class="nav-link sidebar-link d-flex align-items-center"
                :class="{ active: $route.path.startsWith('/approvals') }"
                @click="sidebarOpen = false"
              >
                <i class="bi bi-clipboard-check me-2"></i>Loan Approvals
                <span class="ms-auto d-flex gap-1">
                  <span
                    v-if="authStore.pendingApprovalCount > 0"
                    class="badge rounded-pill bg-danger"
                    style="font-size: 0.65rem;"
                    title="Pending approval"
                  >
                    {{ authStore.pendingApprovalCount }}
                  </span>
                  <span
                    v-if="authStore.releaseCount > 0"
                    class="badge rounded-pill bg-primary"
                    style="font-size: 0.65rem;"
                    title="For release"
                  >
                    {{ authStore.releaseCount }}
                  </span>
                </span>
              </router-link>
            </template>

            <!-- Admin section -->
            <template v-if="authStore.isAdmin">
              <div class="sidebar-section-label mt-3">ADMINISTRATION</div>

              <router-link
                v-for="item in adminMenuItems"
                :key="item.to"
                :to="item.to"
                class="nav-link sidebar-link"
                :class="{ active: $route.path === item.to }"
                @click="sidebarOpen = false"
              >
                <i :class="item.icon" class="me-2"></i>{{ item.label }}
              </router-link>
            </template>
          </nav>
        </div>
      </aside>

      <!-- Overlay for mobile sidebar -->
      <div
        v-if="sidebarOpen"
        class="pmbf-sidebar-overlay d-lg-none"
        @click="sidebarOpen = false"
      ></div>

      <!-- Main content -->
      <main class="pmbf-main">
        <!-- Admin Context Banner -->
        <div v-if="authStore.isAdmin && adminContext.hasSelected" class="admin-context-bar">
          <div class="container-fluid d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center">
              <span class="context-label me-2">
                <i class="bi bi-funnel me-1"></i>Managing:
              </span>
              <span class="badge fs-6" :class="`bg-${adminContext.badgeColor}`">
                {{ adminContext.label }}
              </span>
            </div>
            <router-link to="/admin/select-type" class="btn btn-sm btn-outline-primary">
              <i class="bi bi-arrow-repeat me-1"></i>Switch
            </router-link>
          </div>
        </div>

        <div class="container-fluid py-4">
          <slot />
        </div>
      </main>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import { useAdminContextStore } from '../../stores/adminContext'
import notificationsService from '../../services/notifications'

const authStore = useAuthStore()
const adminContext = useAdminContextStore()
const router = useRouter()
const sidebarOpen = ref(false)

// ── Notifications ──
const notifications = ref([])
const unreadCount = ref(0)
const notificationsLoading = ref(false)

async function fetchUnreadCount() {
  try {
    const { data } = await notificationsService.getUnreadCount()
    unreadCount.value = (data.data ?? data).count ?? 0
  } catch { /* ignore */ }
}

async function fetchNotifications() {
  notificationsLoading.value = true
  try {
    const { data } = await notificationsService.getAll({ per_page: 15 })
    notifications.value = data.data ?? []
    unreadCount.value = data.meta?.unread_count ?? unreadCount.value
  } catch { /* ignore */ }
  finally { notificationsLoading.value = false }
}

async function handleNotificationClick(n) {
  if (!n.read_at) {
    await notificationsService.markAsRead(n.id)
    n.read_at = new Date().toISOString()
    unreadCount.value = Math.max(0, unreadCount.value - 1)
  }
  if (n.url) router.push(n.url)
}

async function markAllRead() {
  await notificationsService.markAllAsRead()
  notifications.value.forEach(n => n.read_at = n.read_at || new Date().toISOString())
  unreadCount.value = 0
}

function timeAgo(dateStr) {
  if (!dateStr) return ''
  const diff = Date.now() - new Date(dateStr).getTime()
  const mins = Math.floor(diff / 60000)
  if (mins < 1) return 'Just now'
  if (mins < 60) return `${mins}m ago`
  const hours = Math.floor(mins / 60)
  if (hours < 24) return `${hours}h ago`
  const days = Math.floor(hours / 24)
  if (days < 7) return `${days}d ago`
  return new Date(dateStr).toLocaleDateString('en-PH', { month: 'short', day: 'numeric' })
}

onMounted(() => {
  if (authStore.isAuthenticated && authStore.token) {
    fetchUnreadCount()
    setInterval(() => {
      if (authStore.isAuthenticated) fetchUnreadCount()
    }, 60000)
  }
})

const memberTypeOptions = [
  { value: 'all', label: 'All Members', short: 'All', icon: 'bi bi-grid-3x3-gap', color: 'ctx-all' },
  { value: 'Permanent', label: 'Permanent', short: 'Perm', icon: 'bi bi-building', color: 'ctx-perm' },
  { value: 'Contract of Service', label: 'Contract of Service', short: 'COS', icon: 'bi bi-file-earmark-text', color: 'ctx-sc' },
  { value: 'Non-Member', label: 'Non-Members', short: 'Non', icon: 'bi bi-person-dash', color: 'ctx-non' },
]

// Dependents, Claims and Benefits are member benefits available only to Permanent employees.
const allMainMenuItems = [
  { to: '/dashboard', label: 'Dashboard', icon: 'bi bi-speedometer2' },
  { to: '/loans', label: 'My Loans', icon: 'bi bi-cash-stack' },
  { to: '/loans/new', label: 'Apply for Loan', icon: 'bi bi-plus-circle' },
  { to: '/dependents', label: 'Dependents', icon: 'bi bi-people', permanentOnly: true },
  { to: '/claims', label: 'Claims', icon: 'bi bi-file-earmark-text', permanentOnly: true },
  { to: '/benefits', label: 'Benefits', icon: 'bi bi-gift', permanentOnly: true },
  { to: '/shares', label: 'My Shares', icon: 'bi bi-pie-chart' },
  { to: '/profile', label: 'Profile', icon: 'bi bi-person-gear' },
]

const mainMenuItems = computed(() =>
  allMainMenuItems.filter((item) => !item.permanentOnly || authStore.isPermanent)
)

const adminMenuItems = [
  { to: '/admin/user-types', label: 'User Types', icon: 'bi bi-person-gear' },
  { to: '/admin/members', label: 'Members', icon: 'bi bi-people-fill' },
  { to: '/admin/loans', label: 'All Loans', icon: 'bi bi-cash-coin' },
  { to: '/admin/payments', label: 'Payments', icon: 'bi bi-credit-card' },
  { to: '/admin/config', label: 'Configuration', icon: 'bi bi-gear' },
  { to: '/admin/reports', label: 'Reports', icon: 'bi bi-file-earmark-bar-graph' },
  { to: '/admin/shares', label: 'Share Capital', icon: 'bi bi-pie-chart' },
  { to: '/admin/import', label: 'Import Data', icon: 'bi bi-upload' },
  { to: '/admin/mobile-settings', label: 'Mobile App', icon: 'bi bi-phone' },
]

const userRoleLabel = computed(() => {
  const role = authStore.user?.role
  if (!role) return ''
  return role.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
})

async function handleLogout() {
  await authStore.logout()
  router.push('/login')
}
</script>

<style>
:root {
  --pmbf-primary: #1e40af;
  --pmbf-secondary: #3b82f6;
  --pmbf-accent: #dbeafe;
  --pmbf-sidebar-width: 260px;
  --pmbf-navbar-height: 60px;
}

.pmbf-navbar {
  background: linear-gradient(135deg, var(--pmbf-primary) 0%, var(--pmbf-secondary) 100%);
  height: var(--pmbf-navbar-height);
  z-index: 1040;
}

.pmbf-body {
  display: flex;
  padding-top: var(--pmbf-navbar-height);
  min-height: 100vh;
}

.pmbf-sidebar {
  width: var(--pmbf-sidebar-width);
  min-width: var(--pmbf-sidebar-width);
  background: #fff;
  border-right: 1px solid #e5e7eb;
  position: fixed;
  top: var(--pmbf-navbar-height);
  bottom: 0;
  left: 0;
  z-index: 1030;
  overflow-y: auto;
  transition: transform 0.3s ease;
}

@media (max-width: 991.98px) {
  .pmbf-sidebar {
    transform: translateX(-100%);
  }
  .pmbf-sidebar.open {
    transform: translateX(0);
  }
}

.pmbf-sidebar-overlay {
  position: fixed;
  top: var(--pmbf-navbar-height);
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.4);
  z-index: 1025;
}

.pmbf-main {
  flex: 1;
  margin-left: var(--pmbf-sidebar-width);
  background: #f3f4f6;
  min-height: calc(100vh - var(--pmbf-navbar-height));
}

@media (max-width: 991.98px) {
  .pmbf-main {
    margin-left: 0;
  }
}

.sidebar-section-label {
  padding: 0.5rem 1.25rem;
  font-size: 0.65rem;
  font-weight: 700;
  text-transform: uppercase;
  color: #9ca3af;
  letter-spacing: 0.05em;
}

.sidebar-link {
  padding: 0.6rem 1.25rem;
  color: #4b5563;
  font-size: 0.875rem;
  border-left: 3px solid transparent;
  transition: all 0.15s ease;
}

.sidebar-link:hover {
  background: var(--pmbf-accent);
  color: var(--pmbf-primary);
}

.sidebar-link.active {
  background: var(--pmbf-accent);
  color: var(--pmbf-primary);
  border-left-color: var(--pmbf-primary);
  font-weight: 600;
}

.admin-type-selector {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 4px;
  background: #f3f4f6;
  border-radius: 8px;
  padding: 3px;
}

.type-btn {
  border: none;
  background: transparent;
  padding: 5px 4px;
  border-radius: 6px;
  font-size: 0.7rem;
  font-weight: 600;
  color: #6b7280;
  cursor: pointer;
  transition: all 0.15s ease;
  white-space: nowrap;
}

.type-btn:hover {
  background: #e5e7eb;
}

.type-btn.active.ctx-all { background: #1e40af; color: #fff; }
.type-btn.active.ctx-perm { background: #059669; color: #fff; }
.type-btn.active.ctx-sc { background: #d97706; color: #fff; }
.type-btn.active.ctx-non { background: #6b7280; color: #fff; }

/* ─── Admin Context Banner ─── */
.admin-context-bar {
  background: #fff;
  border-bottom: 2px solid #e5e7eb;
  padding: 10px 0;
  position: sticky;
  top: var(--pmbf-navbar-height);
  z-index: 1010;
}

.context-label {
  font-size: 0.8rem;
  font-weight: 600;
  color: #6b7280;
  white-space: nowrap;
}

.context-buttons {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}

.context-btn {
  border: 2px solid #e5e7eb;
  background: #fff;
  padding: 6px 16px;
  border-radius: 20px;
  font-size: 0.8rem;
  font-weight: 600;
  color: #6b7280;
  cursor: pointer;
  transition: all 0.15s ease;
}

.context-btn:hover {
  border-color: #9ca3af;
  background: #f9fafb;
}

.context-btn.active.ctx-all {
  background: #1e40af;
  border-color: #1e40af;
  color: #fff;
}

.context-btn.active.ctx-perm {
  background: #059669;
  border-color: #059669;
  color: #fff;
}

.context-btn.active.ctx-sc {
  background: #d97706;
  border-color: #d97706;
  color: #fff;
}

.context-btn.active.ctx-non {
  background: #6b7280;
  border-color: #6b7280;
  color: #fff;
}

.notification-dropdown { border-radius: 10px; overflow: hidden; }
.notification-item:hover { background: #f3f4f6 !important; }
.cost-link { opacity: .85; transition: opacity .15s; }
.cost-link:hover { opacity: 1; }
</style>
