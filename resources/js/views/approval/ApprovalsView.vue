<template>
  <AppLayout>
    <div class="d-flex align-items-center justify-content-between mb-4">
      <h4 class="fw-bold mb-0">Loan Approvals</h4>
    </div>

    <!-- Tabs + Year Filter -->
    <div class="d-flex align-items-center justify-content-between">
      <ul class="nav nav-tabs mb-0 flex-grow-1">
        <li class="nav-item" v-for="tab in tabs" :key="tab.key">
          <button
            class="nav-link"
            :class="{ active: activeTab === tab.key }"
            @click="switchTab(tab.key)"
          >
            {{ tab.label }}
            <span v-if="tabCounts[tab.key]" class="badge ms-1" :class="tab.badgeClass">
              {{ tabCounts[tab.key] }}
            </span>
          </button>
        </li>
      </ul>
      <div class="ms-3 mb-0" style="min-width: 130px;">
        <select class="form-select form-select-sm" v-model="selectedYear" @change="onYearChange">
          <option value="">All Years</option>
          <option v-for="y in availableYears" :key="y" :value="y">{{ y }}</option>
        </select>
      </div>
    </div>

    <AppCard :padding="false" class="border-top-0" style="border-top-left-radius: 0; border-top-right-radius: 0;">
      <!-- Search + Per Page -->
      <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom bg-light">
        <div class="d-flex align-items-center gap-2">
          <select v-model="perPage" class="form-select form-select-sm" style="width: 80px;" @change="onPerPageChange">
            <option value="15">15</option>
            <option value="25">25</option>
            <option value="50">50</option>
            <option value="100">100</option>
          </select>
          <span class="text-muted small">per page</span>
        </div>
        <div style="width: 280px;">
          <input
            v-model="searchQuery"
            type="text"
            class="form-control form-control-sm"
            placeholder="Search applicant..."
            @input="onSearchInput"
          />
        </div>
      </div>

      <AppTable :columns="columns" :items="items" :loading="loading" :empty-text="emptyText">
        <template #cell(index)="{ item }">
          {{ items.indexOf(item) + 1 + (meta.currentPage - 1) * meta.perPage }}
        </template>
        <template #cell(applicant)="{ item }">
          <div class="fw-semibold">{{ item.user?.full_name ?? '-' }}</div>
          <small class="text-muted">{{ item.user?.employee_id ?? '' }}</small>
        </template>
        <template #cell(loan_type)="{ value }">
          {{ formatLoanType(value) }}
        </template>
        <template #cell(amount)="{ value }">
          <span class="fw-medium">&#8369;{{ Number(value).toLocaleString() }}</span>
        </template>
        <template #cell(employment_type)="{ item }">
          <AppBadge
            :variant="item.user?.employment_type === 'Permanent' ? 'success' : (item.user?.employment_type === 'Contract of Service' ? 'warning' : 'secondary')"
            :text="item.user?.employment_type ?? '-'"
          />
        </template>
        <template #cell(status)="{ value }">
          <AppStatusBadge :status="value" />
        </template>
        <template #cell(created_at)="{ value }">
          {{ formatDate(value) }}
        </template>
        <template #cell(actions)="{ item }">
          <router-link :to="`/approvals/${item.id}`" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-eye me-1"></i>Review
          </router-link>
        </template>
      </AppTable>

      <template #footer>
        <div class="d-flex align-items-center justify-content-between px-3">
          <span class="text-muted small">Showing {{ items.length }} of {{ meta.total ?? 0 }} records</span>
          <AppPagination
            :meta="{ current_page: meta.currentPage, last_page: meta.lastPage, total: meta.total, per_page: meta.perPage }"
            @page-change="fetch"
          />
        </div>
      </template>
    </AppCard>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { usePagination } from '@/composables/usePagination'
import { useAdminContextStore } from '@/stores/adminContext'
import { useAuthStore } from '@/stores/auth'
import approvals from '@/services/approvals'
import AppLayout from '@/components/layout/AppLayout.vue'
import AppCard from '@/components/ui/AppCard.vue'
import AppTable from '@/components/ui/AppTable.vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import AppStatusBadge from '@/components/ui/AppStatusBadge.vue'
import AppPagination from '@/components/ui/AppPagination.vue'

const authStore = useAuthStore()
const adminContext = useAdminContextStore()
const { items, meta, loading, filters, fetch } = usePagination(approvals.getApprovals)

// State save/restore
const STORAGE_KEY = 'pmbf_approvals_state'
const saved = JSON.parse(sessionStorage.getItem(STORAGE_KEY) || '{}')

const activeTab = ref(saved.tab || 'pending')
const tabCounts = ref({})
const selectedYear = ref(saved.year || '')
const availableYears = ref([])
const searchQuery = ref(saved.search || '')
const perPage = ref(saved.perPage || '15')
let searchTimer = null

function saveState() {
  sessionStorage.setItem(STORAGE_KEY, JSON.stringify({
    tab: activeTab.value,
    year: selectedYear.value,
    search: searchQuery.value,
    perPage: perPage.value,
  }))
}

const tabs = [
  { key: 'pending', label: 'Pending', badgeClass: 'bg-warning text-dark' },
  { key: 'approved', label: 'Approved', badgeClass: 'bg-success' },
  { key: 'disapproved', label: 'Disapproved', badgeClass: 'bg-danger' },
  { key: 'cancelled', label: 'Cancelled', badgeClass: 'bg-dark' },
  { key: 'released', label: 'Released', badgeClass: 'bg-primary' },
  { key: 'all', label: 'All', badgeClass: 'bg-secondary' },
]

const emptyText = computed(() => {
  const msgs = {
    pending: 'No pending approvals.',
    approved: 'No approved loans.',
    disapproved: 'No disapproved loans.',
    cancelled: 'No cancelled loans.',
    released: 'No released loans.',
    all: 'No loan applications found.',
  }
  return msgs[activeTab.value] || 'No records found.'
})

const columns = [
  { key: 'index', label: '#', class: 'text-center' },
  { key: 'applicant', label: 'Applicant' },
  { key: 'loan_type', label: 'Type' },
  { key: 'amount', label: 'Amount' },
  { key: 'employment_type', label: 'Employment' },
  { key: 'status', label: 'Status' },
  { key: 'created_at', label: 'Applied' },
  { key: 'actions', label: '', class: 'text-end' },
]

function switchTab(tab) {
  activeTab.value = tab
  applyFilters()
}

function onYearChange() {
  applyFilters()
}

function onSearchInput() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => applyFilters(), 400)
}

function onPerPageChange() {
  applyFilters()
}

function applyFilters() {
  filters.status = activeTab.value
  filters.year = selectedYear.value || undefined
  filters.search = searchQuery.value || undefined
  filters.per_page = perPage.value
  // Admin context auto-filter
  if (authStore.isAdmin && adminContext.memberType && adminContext.memberType !== 'all') {
    filters.employment_type = adminContext.memberType
  } else {
    filters.employment_type = undefined
  }
  saveState()
  fetch(1)
}

// Re-fetch when admin switches context
watch(() => adminContext.memberType, () => applyFilters())

function formatLoanType(type) {
  if (!type) return '-'
  return type.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
}

function formatDate(dateStr) {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleDateString('en-PH', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })
}

onMounted(async () => {
  // Fetch available years
  try {
    const { data } = await approvals.getApprovals({ filters_only: true })
    const result = data.data ?? data
    availableYears.value = result.years ?? []
  } catch {
    availableYears.value = [new Date().getFullYear()]
  }
  switchTab(activeTab.value)
})
</script>

<style scoped>
.nav-tabs .nav-link {
  color: #6b7280;
  font-weight: 600;
  font-size: 0.875rem;
  border: 1px solid transparent;
  padding: 0.6rem 1.2rem;
}
.nav-tabs .nav-link.active {
  color: #1e40af;
  border-color: #dee2e6 #dee2e6 #fff;
}
.nav-tabs .nav-link:hover:not(.active) {
  border-color: transparent;
  color: #1e40af;
}
</style>
