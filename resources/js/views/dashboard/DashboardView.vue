<template>
  <AppLayout>
    <div class="d-flex align-items-center justify-content-between mb-4">
      <h4 class="fw-bold mb-0">Dashboard</h4>
      <span class="text-muted small">Welcome back, {{ authStore.fullName }}</span>
    </div>

    <AppLoading :loading="loading" text="Loading dashboard..." />

    <template v-if="!loading">
      <!-- ═══ ACTION REQUIRED ═══ (renders nothing when there is nothing to do) -->
      <div v-if="actionItems.length" class="card border-0 shadow-sm mb-4 action-required">
        <div class="card-header bg-warning bg-opacity-25 border-0 d-flex align-items-center">
          <i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>
          <span class="fw-bold">Action Required</span>
          <span class="badge rounded-pill bg-warning text-dark ms-2">{{ actionItems.length }}</span>
        </div>
        <div class="list-group list-group-flush">
          <router-link
            v-for="item in actionItems"
            :key="item.key"
            :to="item.to"
            class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-3"
          >
            <div
              class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
              :class="`bg-${item.variant} bg-opacity-10`"
              style="width:44px;height:44px;"
            >
              <i :class="[item.icon, `text-${item.variant}`]" class="fs-5"></i>
            </div>
            <div class="flex-grow-1">
              <div class="fw-semibold">{{ item.title }}</div>
              <div class="small text-muted">{{ item.description }}</div>
            </div>
            <span v-if="item.count" class="badge rounded-pill" :class="`bg-${item.variant}`">{{ item.count }}</span>
            <i class="bi bi-chevron-right text-muted"></i>
          </router-link>
        </div>
      </div>

      <!-- ═══ ADMIN DASHBOARD ═══ -->
      <template v-if="authStore.isAdmin">
        <!-- Financial Cards -->
        <div class="row g-3 mb-4">
          <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm border-0 h-100">
              <div class="card-body">
                <div class="d-flex align-items-center mb-2">
                  <div class="rounded-circle bg-success bg-opacity-10 d-flex align-items-center justify-content-center me-3" style="width:48px;height:48px;">
                    <i class="bi bi-cash-coin fs-4 text-success"></i>
                  </div>
                  <div>
                    <div class="text-muted small text-uppercase fw-semibold">Cash On Hand</div>
                    <div class="fs-4 fw-bold text-success">&#8369;{{ fmt(adminData.financial?.cash_on_hand) }}</div>
                  </div>
                </div>
                <div class="small text-muted">Beginning: &#8369;{{ fmt(adminData.financial?.beginning_balance) }} + Collections: &#8369;{{ fmt(adminData.financial?.total_collected_this_year) }}</div>
              </div>
            </div>
          </div>
          <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm border-0 h-100">
              <div class="card-body">
                <div class="d-flex align-items-center mb-2">
                  <div class="rounded-circle bg-warning bg-opacity-10 d-flex align-items-center justify-content-center me-3" style="width:48px;height:48px;">
                    <i class="bi bi-receipt fs-4 text-warning"></i>
                  </div>
                  <div>
                    <div class="text-muted small text-uppercase fw-semibold">Collectibles</div>
                    <div class="fs-4 fw-bold text-warning">&#8369;{{ fmt(adminData.financial?.collectibles) }}</div>
                  </div>
                </div>
                <div class="small text-muted">Outstanding balance from active loans</div>
              </div>
            </div>
          </div>
          <div class="col-sm-6 col-xl-3">
            <AppStatCard title="Total Loans" :value="adminData.loans?.total_count ?? 0" icon="bi bi-file-earmark-text" color="primary" />
          </div>
          <div class="col-sm-6 col-xl-3">
            <AppStatCard title="Total Loan Amount" :value="adminData.loans?.total_amount ?? 0" icon="bi bi-cash-stack" color="primary" prefix="₱" />
          </div>
        </div>

        <div class="row g-3 mb-4">
          <div class="col-sm-6 col-xl-3">
            <AppStatCard title="Active Loans" :value="adminData.loans?.active_count ?? 0" icon="bi bi-wallet2" color="success" />
          </div>
          <div class="col-sm-6 col-xl-3">
            <AppStatCard title="Pending Approval" :value="adminData.loans?.pending ?? 0" icon="bi bi-hourglass-split" color="warning" />
          </div>
          <div class="col-sm-6 col-xl-3">
            <AppStatCard title="Total Members" :value="adminData.members?.total ?? 0" icon="bi bi-people" color="primary" />
          </div>
          <div class="col-sm-6 col-xl-3">
            <AppStatCard title="Collected This Month" :value="adminData.payments?.total_this_month ?? 0" icon="bi bi-calendar-check" color="success" prefix="₱" />
          </div>
        </div>

        <!-- Shares row for admin -->
        <div class="row g-3 mb-4">
          <div class="col-sm-6 col-xl-3">
            <AppStatCard title="Total Share Capital" :value="adminData.shares?.total_this_year ?? 0" icon="bi bi-pie-chart" color="primary" prefix="₱" />
          </div>
          <div class="col-sm-6 col-xl-3">
            <AppStatCard title="Members with Shares" :value="adminData.shares?.members_with_shares ?? 0" icon="bi bi-person-check" color="success" />
          </div>
        </div>

        <!-- Beginning Balance -->
        <div class="row g-3 mb-4">
          <div class="col-12">
            <div class="card shadow-sm border-0">
              <div class="card-body py-2 d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-3">
                  <span class="text-muted small fw-semibold">Beginning Balance ({{ currentYear }}):</span>
                  <div class="input-group input-group-sm" style="max-width: 240px;">
                    <span class="input-group-text">₱</span>
                    <input v-model="beginningBalanceInput" type="number" class="form-control" placeholder="0.00" />
                    <button class="btn btn-outline-primary" :disabled="savingBalance" @click="saveBeginningBalance">
                      <span v-if="savingBalance" class="spinner-border spinner-border-sm"></span>
                      <i v-else class="bi bi-check-lg"></i>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Charts -->
        <div class="row g-4 mb-4">
          <div class="col-lg-8">
            <AppCard title="Monthly Collections">
              <div style="height: 280px;">
                <BarChart v-if="monthlyChartData" :data="monthlyChartData" :options="chartOptions" />
              </div>
            </AppCard>
          </div>
          <div class="col-lg-4">
            <AppCard title="Loans by Type">
              <div style="height: 280px;">
                <DoughnutChart v-if="loanTypeChartData" :data="loanTypeChartData" :options="doughnutOptions" />
              </div>
            </AppCard>
          </div>
        </div>

        <!-- Recent Loans -->
        <AppCard title="Recent Loans">
          <AppTable :columns="adminLoanColumns" :items="adminData.loans?.recent ?? []">
            <template #cell(user)="{ item }">
              <div class="fw-semibold">{{ item.user?.full_name ?? '-' }}</div>
              <small class="text-muted">{{ item.user?.employee_id ?? '' }}</small>
            </template>
            <template #cell(amount)="{ value }">
              &#8369;{{ Number(value ?? 0).toLocaleString() }}
            </template>
            <template #cell(status)="{ value }">
              <AppStatusBadge :status="value" />
            </template>
            <template #cell(created_at)="{ value }">
              {{ formatDate(value) }}
            </template>
          </AppTable>
        </AppCard>
      </template>

      <!-- ═══ STAFF DASHBOARD (Receiver / Committee / Chairperson) ═══ -->
      <template v-else-if="authStore.isStaff">
        <div class="row g-3 mb-4">
          <div class="col-sm-6 col-xl-3">
            <AppStatCard title="Pending Approvals" :value="stats.pending_approvals ?? 0" icon="bi bi-clipboard-check" color="danger" />
          </div>
          <div class="col-sm-6 col-xl-3">
            <AppStatCard title="My Active Loans" :value="stats.active_loans ?? 0" icon="bi bi-cash-stack" color="primary" />
          </div>
          <div class="col-sm-6 col-xl-3">
            <AppStatCard title="Total Borrowed" :value="stats.total_borrowed ?? 0" icon="bi bi-wallet2" color="success" prefix="₱" />
          </div>
          <div class="col-sm-6 col-xl-3">
            <AppStatCard title="Total Payable Balance" :value="stats.total_remaining ?? 0" icon="bi bi-graph-down-arrow" color="warning" prefix="₱" />
          </div>
        </div>

        <!-- Next Payment Alert -->
        <div v-if="stats.next_payment" class="alert mb-4" :class="stats.next_payment.is_overdue ? 'alert-danger' : 'alert-info'">
          <div class="d-flex align-items-center">
            <i :class="stats.next_payment.is_overdue ? 'bi-exclamation-triangle' : 'bi-clock'" class="bi fs-4 me-3"></i>
            <div>
              <strong>{{ stats.next_payment.is_overdue ? 'Payment Overdue!' : 'Next Payment Due' }}</strong>
              <div class="small">
                {{ stats.next_payment.loan_type }} &mdash;
                <strong>&#8369;{{ Number(stats.next_payment.amount).toLocaleString() }}</strong>
                due <strong>{{ stats.next_payment.due_date }}</strong>
                <span v-if="stats.next_payment.is_overdue" class="text-danger fw-bold">
                  ({{ Math.abs(stats.next_payment.days_until) }} days overdue)
                </span>
                <span v-else-if="stats.next_payment.days_until <= 5" class="text-warning fw-bold">
                  (in {{ stats.next_payment.days_until }} days)
                </span>
              </div>
            </div>
            <router-link :to="`/loans/${stats.next_payment.loan_id}`" class="btn btn-sm btn-outline-primary ms-auto">View Loan</router-link>
          </div>
        </div>

        <div class="row g-4">
          <div class="col-lg-8">
            <AppCard title="Quick Actions">
              <div class="row g-3">
                <div class="col-sm-6">
                  <router-link to="/approvals" class="card border-0 bg-primary bg-opacity-10 text-decoration-none h-100">
                    <div class="card-body text-center py-4">
                      <i class="bi bi-clipboard-check fs-2 text-primary"></i>
                      <div class="fw-semibold mt-2">Loan Approvals</div>
                      <div class="small text-muted">{{ stats.pending_approvals ?? 0 }} pending</div>
                    </div>
                  </router-link>
                </div>
                <div class="col-sm-6">
                  <router-link to="/loans/new" class="card border-0 bg-success bg-opacity-10 text-decoration-none h-100">
                    <div class="card-body text-center py-4">
                      <i class="bi bi-plus-circle fs-2 text-success"></i>
                      <div class="fw-semibold mt-2">Apply for Loan</div>
                      <div class="small text-muted">Submit new application</div>
                    </div>
                  </router-link>
                </div>
              </div>
            </AppCard>
          </div>
          <div class="col-lg-4">
            <AppCard title="Profile Summary">
              <div class="text-center mb-3">
                <div class="rounded-circle bg-primary bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-2" style="width:64px;height:64px;">
                  <i class="bi bi-person-fill fs-3 text-primary"></i>
                </div>
                <h6 class="fw-semibold mb-0">{{ authStore.fullName }}</h6>
                <small class="text-muted">{{ authStore.user?.employee_id }}</small>
              </div>
              <ul class="list-group list-group-flush small">
                <li class="list-group-item d-flex justify-content-between px-0">
                  <span class="text-muted">Department</span>
                  <span class="fw-medium">{{ authStore.user?.department ?? '-' }}</span>
                </li>
                <li class="list-group-item d-flex justify-content-between px-0">
                  <span class="text-muted">Employment Type</span>
                  <span class="fw-medium">{{ authStore.user?.employment_type ?? '-' }}</span>
                </li>
                <li class="list-group-item d-flex justify-content-between px-0">
                  <span class="text-muted">Role</span>
                  <span class="fw-medium">{{ roleLabel }}</span>
                </li>
              </ul>
              <div class="mt-3">
                <router-link to="/profile" class="btn btn-outline-primary btn-sm w-100">
                  <i class="bi bi-person-gear me-1"></i>View Profile
                </router-link>
              </div>
            </AppCard>
          </div>
        </div>
      </template>

      <!-- ═══ MEMBER DASHBOARD ═══ -->
      <template v-else>
        <div class="row g-3 mb-4">
          <div class="col-sm-6 col-xl-3">
            <AppStatCard title="Active Loans" :value="stats.active_loans ?? 0" icon="bi bi-cash-stack" color="primary" />
          </div>
          <div class="col-sm-6 col-xl-3">
            <AppStatCard title="Pending Applications" :value="stats.pending_loans ?? 0" icon="bi bi-hourglass-split" color="warning" />
          </div>
          <div class="col-sm-6 col-xl-3">
            <AppStatCard title="Total Borrowed" :value="stats.total_borrowed ?? 0" icon="bi bi-wallet2" color="success" prefix="₱" />
          </div>
          <div class="col-sm-6 col-xl-3">
            <AppStatCard title="Total Payable Balance" :value="stats.total_remaining ?? 0" icon="bi bi-graph-down-arrow" color="warning" prefix="₱" />
          </div>
        </div>

        <!-- Shares for member -->
        <div v-if="stats.total_shares !== undefined" class="row g-3 mb-4">
          <div class="col-sm-6 col-xl-3">
            <AppStatCard title="My Total Shares" :value="stats.total_shares ?? 0" icon="bi bi-pie-chart" color="primary" prefix="₱" />
          </div>
          <div class="col-sm-6 col-xl-3">
            <AppStatCard title="Monthly Share" :value="stats.current_monthly_share ?? 0" icon="bi bi-calendar-check" color="success" prefix="₱" />
          </div>
        </div>

        <!-- Next Payment Alert -->
        <div v-if="stats.next_payment" class="alert mb-4" :class="stats.next_payment.is_overdue ? 'alert-danger' : 'alert-info'">
          <div class="d-flex align-items-center">
            <i :class="stats.next_payment.is_overdue ? 'bi-exclamation-triangle' : 'bi-clock'" class="bi fs-4 me-3"></i>
            <div>
              <strong>{{ stats.next_payment.is_overdue ? 'Payment Overdue!' : 'Next Payment Due' }}</strong>
              <div class="small">
                {{ stats.next_payment.loan_type }} &mdash;
                <strong>&#8369;{{ Number(stats.next_payment.amount).toLocaleString() }}</strong>
                due <strong>{{ stats.next_payment.due_date }}</strong>
                <span v-if="stats.next_payment.is_overdue" class="text-danger fw-bold">
                  ({{ Math.abs(stats.next_payment.days_until) }} days overdue)
                </span>
                <span v-else-if="stats.next_payment.days_until <= 5" class="text-warning fw-bold">
                  (in {{ stats.next_payment.days_until }} days)
                </span>
              </div>
            </div>
            <router-link :to="`/loans/${stats.next_payment.loan_id}`" class="btn btn-sm btn-outline-primary ms-auto">View Loan</router-link>
          </div>
        </div>

        <!-- Payment Progress -->
        <div v-if="stats.active_loans > 0" class="row g-3 mb-4">
          <div class="col-12">
            <AppCard title="Payment Progress">
              <div class="d-flex justify-content-between small mb-1">
                <span class="text-muted">Total Paid</span>
                <span class="fw-semibold">{{ paymentPercent }}%</span>
              </div>
              <div class="progress mb-2" style="height: 10px;">
                <div class="progress-bar bg-success" :style="{ width: paymentPercent + '%' }"></div>
              </div>
              <div class="d-flex justify-content-between small text-muted">
                <span>Paid: &#8369;{{ Number(stats.total_paid ?? 0).toLocaleString() }}</span>
                <span>Remaining: &#8369;{{ Number(stats.total_remaining ?? 0).toLocaleString() }}</span>
              </div>
            </AppCard>
          </div>
        </div>

        <div class="row g-4">
          <div class="col-lg-8">
            <AppCard title="Recent Loans">
              <AppTable :columns="loanColumns" :items="stats.recent_loans ?? []" empty-text="No loans yet.">
                <template #cell(status)="{ value }">
                  <AppStatusBadge :status="value" />
                </template>
                <template #cell(amount)="{ value }">
                  &#8369;{{ Number(value).toLocaleString() }}
                </template>
                <template #cell(created_at)="{ value }">
                  {{ formatDate(value) }}
                </template>
                <template #cell(actions)="{ item }">
                  <router-link :to="`/loans/${item.id}`" class="btn btn-sm btn-outline-primary">View</router-link>
                </template>
              </AppTable>
            </AppCard>
          </div>
          <div class="col-lg-4">
            <AppCard title="Profile Summary">
              <div class="text-center mb-3">
                <div class="rounded-circle bg-primary bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-2" style="width:64px;height:64px;">
                  <i class="bi bi-person-fill fs-3 text-primary"></i>
                </div>
                <h6 class="fw-semibold mb-0">{{ authStore.fullName }}</h6>
                <small class="text-muted">{{ authStore.user?.employee_id }}</small>
              </div>
              <ul class="list-group list-group-flush small">
                <li class="list-group-item d-flex justify-content-between px-0">
                  <span class="text-muted">Department</span>
                  <span class="fw-medium">{{ authStore.user?.department ?? '-' }}</span>
                </li>
                <li class="list-group-item d-flex justify-content-between px-0">
                  <span class="text-muted">Employment Type</span>
                  <span class="fw-medium">{{ authStore.user?.employment_type ?? '-' }}</span>
                </li>
              </ul>
              <div class="mt-3">
                <router-link to="/profile" class="btn btn-outline-primary btn-sm w-100">
                  <i class="bi bi-person-gear me-1"></i>View Profile
                </router-link>
              </div>
            </AppCard>
          </div>
        </div>
      </template>
    </template>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { Bar as BarChart, Doughnut as DoughnutChart } from 'vue-chartjs'
import { Chart as ChartJS, CategoryScale, LinearScale, BarElement, ArcElement, Title, Tooltip, Legend } from 'chart.js'
import { useAuthStore } from '@/stores/auth'
import { useAdminContextStore } from '@/stores/adminContext'
import { useNotificationStore } from '@/stores/notification'
import { useLoading } from '@/composables/useLoading'
import loans from '@/services/loans'
import admin from '@/services/admin'
import AppLayout from '@/components/layout/AppLayout.vue'
import AppStatCard from '@/components/ui/AppStatCard.vue'
import AppCard from '@/components/ui/AppCard.vue'
import AppTable from '@/components/ui/AppTable.vue'
import AppStatusBadge from '@/components/ui/AppStatusBadge.vue'
import AppLoading from '@/components/ui/AppLoading.vue'

ChartJS.register(CategoryScale, LinearScale, BarElement, ArcElement, Title, Tooltip, Legend)

const authStore = useAuthStore()
const adminContext = useAdminContextStore()
const notify = useNotificationStore()
const { loading, withLoading } = useLoading()

const currentYear = new Date().getFullYear()

// ── Member / Staff data ──
const stats = ref({})

const loanColumns = [
  { key: 'loan_type', label: 'Type' },
  { key: 'amount', label: 'Amount' },
  { key: 'status', label: 'Status' },
  { key: 'created_at', label: 'Applied' },
  { key: 'actions', label: '', class: 'text-end' },
]

const paymentPercent = computed(() => {
  const paid = Number(stats.value.total_paid ?? 0)
  const remaining = Number(stats.value.total_remaining ?? 0)
  const total = paid + remaining
  if (total <= 0) return 0
  return Math.min(100, Math.round((paid / total) * 100))
})

// ── Action Required strip ──
// Only surfaces items that need THIS user to do something. Counts come from the
// auth store (already fetched on login/init) and from the loan stats payload —
// no extra endpoints.

/** Own loans whose status is stuck until the member does something. */
const MEMBER_ACTION_STATUSES = {
  co_maker_declined: {
    variant: 'danger',
    icon: 'bi bi-person-x',
    title: 'Co-maker declined your application',
    description: 'Cancel this application and reapply with a different co-maker.',
  },
  disapproved: {
    variant: 'danger',
    icon: 'bi bi-x-octagon',
    title: 'Loan application disapproved',
    description: 'Review the remarks, then submit a new application if you still need the loan.',
  },
}

const actionItems = computed(() => {
  const items = []

  // 1. Loans waiting for this user's co-maker consent.
  const coMaker = authStore.coMakerPendingCount
  if (coMaker > 0) {
    items.push({
      key: 'co-maker',
      to: '/loans',
      variant: 'danger',
      icon: 'bi bi-people-fill',
      count: coMaker,
      title: `Co-maker consent needed on ${coMaker} loan${coMaker === 1 ? '' : 's'}`,
      description: 'A colleague listed you as co-maker. Agree or decline so their application can move on.',
    })
  }

  // 2. Loans waiting for this user's approval / release (staff only).
  if (authStore.canApprove) {
    const pending = authStore.pendingApprovalCount
    if (pending > 0) {
      items.push({
        key: 'approvals',
        to: '/approvals',
        variant: 'warning',
        icon: 'bi bi-clipboard-check',
        count: pending,
        title: `${pending} loan${pending === 1 ? '' : 's'} awaiting your approval`,
        description: 'Review the applications queued at your approval level.',
      })
    }

    const release = authStore.releaseCount
    if (release > 0) {
      items.push({
        key: 'release',
        to: '/approvals',
        variant: 'primary',
        icon: 'bi bi-cash-stack',
        count: release,
        title: `${release} approved loan${release === 1 ? '' : 's'} ready for release`,
        description: 'Fully approved — mark them released once the proceeds are disbursed.',
      })
    }
  }

  // 3. This user's own loans that are stuck pending their action.
  for (const loan of stats.value.recent_loans ?? []) {
    const meta = MEMBER_ACTION_STATUSES[loan.status]
    if (!meta) continue
    items.push({
      key: `loan-${loan.id}`,
      to: `/loans/${loan.id}`,
      variant: meta.variant,
      icon: meta.icon,
      count: 0,
      title: `${meta.title} — ${loan.loan_type} (₱${Number(loan.amount ?? 0).toLocaleString()})`,
      description: meta.description,
    })
  }

  return items
})

// ── Admin data ──
const adminData = ref({})
const beginningBalanceInput = ref('')
const savingBalance = ref(false)

const adminLoanColumns = [
  { key: 'user', label: 'Applicant' },
  { key: 'loan_type', label: 'Type' },
  { key: 'amount', label: 'Amount' },
  { key: 'status', label: 'Status' },
  { key: 'created_at', label: 'Date' },
]

const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']

const monthlyChartData = computed(() => {
  const raw = adminData.value.charts?.monthly_collections ?? {}
  return {
    labels: monthNames,
    datasets: [{
      label: 'Collections (₱)',
      data: monthNames.map((_, i) => Number(raw[i + 1] ?? 0)),
      backgroundColor: '#3b82f6',
      borderRadius: 4,
    }],
  }
})

const loanTypeChartData = computed(() => {
  const items = adminData.value.charts?.loans_by_type ?? []
  if (!items.length) return null
  const colors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#6b7280']
  return {
    labels: items.map(i => i.loan_type),
    datasets: [{
      data: items.map(i => Number(i.count)),
      backgroundColor: colors.slice(0, items.length),
    }],
  }
})

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { display: false } },
  scales: { y: { beginAtZero: true, ticks: { callback: v => '₱' + Number(v).toLocaleString() } } },
}

const doughnutOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { position: 'bottom' } },
}

const roleLabel = computed(() => {
  const role = authStore.user?.role
  if (!role) return '-'
  return role.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())
})

function fmt(val) {
  return Number(val ?? 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function formatDate(dateStr) {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
}

async function fetchAdminDashboard() {
  const params = {}
  if (adminContext.memberType && adminContext.memberType !== 'all') {
    params.employment_type = adminContext.memberType
  }
  const { data } = await admin.getDashboard(params)
  adminData.value = data.data ?? data
  beginningBalanceInput.value = adminData.value.financial?.beginning_balance ?? ''
}

async function saveBeginningBalance() {
  savingBalance.value = true
  try {
    await admin.setBeginningBalance({ year: currentYear, amount: Number(beginningBalanceInput.value) || 0 })
    notify.success('Beginning balance updated.')
    await fetchAdminDashboard()
  } catch {
    notify.error('Failed to update beginning balance.')
  } finally {
    savingBalance.value = false
  }
}

watch(() => adminContext.memberType, () => {
  if (authStore.isAdmin) fetchAdminDashboard()
})

onMounted(() => {
  withLoading(async () => {
    if (authStore.isAdmin) {
      await fetchAdminDashboard()
    } else {
      const response = await loans.getStats()
      stats.value = response.data.data ?? response.data
    }
  })

  // Keep the Action Required counts fresh without adding endpoints.
  authStore.fetchCoMakerPendingCount()
  authStore.fetchPendingApprovalCount()
})
</script>

<style scoped>
.action-required { border-left: 4px solid var(--bs-warning) !important; }
</style>
