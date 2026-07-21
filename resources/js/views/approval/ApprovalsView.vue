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
        <template #header(select)>
          <input
            class="form-check-input mt-0"
            type="checkbox"
            :disabled="selectableItems.length === 0"
            :checked="allSelected"
            :indeterminate.prop="someSelected && !allSelected"
            title="Select all actionable rows on this page"
            @change="toggleSelectAll($event.target.checked)"
          />
        </template>
        <template #cell(select)="{ item }">
          <input
            v-if="item.can_act || item.can_release"
            class="form-check-input mt-0"
            type="checkbox"
            :checked="selectedIds.includes(item.id)"
            @change="toggleRow(item.id, $event.target.checked)"
          />
          <span v-else class="text-muted small" title="Nothing for you to do on this loan">&mdash;</span>
        </template>
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
            @page-change="goPage"
          />
        </div>
      </template>
    </AppCard>

    <!-- ═══ Sticky bulk action bar ═══ -->
    <div v-if="selectedIds.length > 0" class="bulk-action-bar shadow-lg">
      <div class="d-flex align-items-center justify-content-between gap-3 px-3 py-2">
        <div class="d-flex align-items-center gap-2">
          <span class="badge bg-primary rounded-pill">{{ selectedIds.length }}</span>
          <span class="fw-semibold small">loan{{ selectedIds.length === 1 ? '' : 's' }} selected</span>
          <span v-if="selectedIds.length >= MAX_BATCH" class="text-danger small">
            (max {{ MAX_BATCH }} per batch)
          </span>
        </div>
        <div class="d-flex align-items-center gap-2">
          <AppButton
            v-if="canBulkApprove"
            variant="success"
            size="sm"
            :loading="bulkProcessing"
            @click="handleBulkApprove"
          >
            <i class="bi bi-check2-all me-1"></i>Approve selected
          </AppButton>
          <AppButton
            v-if="canBulkApprove"
            variant="outline-danger"
            size="sm"
            :disabled="bulkProcessing"
            @click="openBulkDisapprove"
          >
            <i class="bi bi-x-circle me-1"></i>Disapprove selected
          </AppButton>
          <AppButton
            v-if="canBulkRelease"
            variant="primary"
            size="sm"
            :loading="bulkProcessing"
            @click="handleBulkRelease"
          >
            <i class="bi bi-cash-coin me-1"></i>Release selected
          </AppButton>
          <span v-if="!canBulkApprove && !canBulkRelease" class="text-muted small">
            Mixed selection — clear and pick loans at the same stage.
          </span>
          <button class="btn btn-sm btn-outline-secondary" :disabled="bulkProcessing" @click="clearSelection">
            <i class="bi bi-x-lg me-1"></i>Clear
          </button>
        </div>
      </div>
    </div>

    <!-- ═══ Bulk Disapprove Modal ═══ -->
    <AppModal :show="showBulkDisapproveModal" title="Disapprove Selected Loans" @close="showBulkDisapproveModal = false">
      <div class="text-center mb-4">
        <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-danger bg-opacity-10 mb-3" style="width: 72px; height: 72px;">
          <i class="bi bi-x-circle text-danger" style="font-size: 2.25rem;"></i>
        </div>
        <h5 class="fw-bold mb-1">Disapprove {{ selectedIds.length }} loan{{ selectedIds.length === 1 ? '' : 's' }}?</h5>
        <p class="text-muted small mb-0">
          Each applicant will be notified via email. This cannot be undone.
        </p>
      </div>
      <AppInput
        v-model="bulkRemarks"
        type="textarea"
        label="Reason for disapproval *"
        placeholder="Please provide a clear reason..."
        required
        :error="bulkRemarksError"
      />
      <template #footer>
        <button class="btn btn-secondary" :disabled="bulkProcessing" @click="showBulkDisapproveModal = false">Cancel</button>
        <AppButton variant="danger" :loading="bulkProcessing" @click="handleBulkDisapprove">
          <i class="bi bi-x-lg me-1"></i>Yes, Disapprove
        </AppButton>
      </template>
    </AppModal>

    <!-- ═══ Bulk Result Modal ═══ -->
    <AppModal :show="showResultModal" title="Bulk Action Result" @close="showResultModal = false">
      <p class="fw-semibold mb-3">{{ bulkResultMessage }}</p>
      <div v-if="bulkFailed.length > 0">
        <div class="text-muted small mb-2">Skipped loans:</div>
        <ul class="list-group list-group-flush small">
          <li v-for="f in bulkFailed" :key="f.loan_id" class="list-group-item px-0 d-flex justify-content-between">
            <span class="fw-medium">Loan #{{ f.loan_id }}</span>
            <span class="text-danger">{{ f.reason }}</span>
          </li>
        </ul>
      </div>
      <template #footer>
        <button class="btn btn-primary" @click="showResultModal = false">Close</button>
      </template>
    </AppModal>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { usePagination } from '@/composables/usePagination'
import { useConfirm } from '@/composables/useConfirm'
import { useAdminContextStore } from '@/stores/adminContext'
import { useAuthStore } from '@/stores/auth'
import { useNotificationStore } from '@/stores/notification'
import approvals from '@/services/approvals'
import AppLayout from '@/components/layout/AppLayout.vue'
import AppCard from '@/components/ui/AppCard.vue'
import AppTable from '@/components/ui/AppTable.vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppInput from '@/components/ui/AppInput.vue'
import AppModal from '@/components/ui/AppModal.vue'
import AppStatusBadge from '@/components/ui/AppStatusBadge.vue'
import AppPagination from '@/components/ui/AppPagination.vue'

const authStore = useAuthStore()
const adminContext = useAdminContextStore()
const notification = useNotificationStore()
const { confirm } = useConfirm()
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
  { key: 'select', label: '', class: 'text-center col-select' },
  { key: 'index', label: '#', class: 'text-center' },
  { key: 'applicant', label: 'Applicant' },
  { key: 'loan_type', label: 'Type' },
  { key: 'amount', label: 'Amount' },
  { key: 'employment_type', label: 'Employment' },
  { key: 'status', label: 'Status' },
  { key: 'created_at', label: 'Applied' },
  { key: 'actions', label: '', class: 'text-end' },
]

// ── Bulk selection ────────────────────────────────────────
const MAX_BATCH = 50
const selectedIds = ref([])
const bulkProcessing = ref(false)
const showBulkDisapproveModal = ref(false)
const showResultModal = ref(false)
const bulkRemarks = ref('')
const bulkRemarksError = ref('')
const bulkResultMessage = ref('')
const bulkFailed = ref([])

// Only rows the current user can approve OR release are selectable.
const selectableItems = computed(() => items.value.filter((i) => i.can_act || i.can_release))

const selectedItems = computed(() =>
  items.value.filter((i) => selectedIds.value.includes(i.id))
)

// Approving and releasing are different stages, so a mixed selection offers
// neither — the buttons only appear when every selected loan supports them.
const canBulkApprove = computed(
  () => selectedItems.value.length > 0 && selectedItems.value.every((i) => i.can_act)
)
const canBulkRelease = computed(
  () => selectedItems.value.length > 0 && selectedItems.value.every((i) => i.can_release)
)
const allSelected = computed(
  () => selectableItems.value.length > 0 &&
    selectableItems.value.every((i) => selectedIds.value.includes(i.id))
)
const someSelected = computed(
  () => selectableItems.value.some((i) => selectedIds.value.includes(i.id))
)

function toggleRow(id, checked) {
  if (checked) {
    if (selectedIds.value.includes(id)) return
    if (selectedIds.value.length >= MAX_BATCH) {
      notification.error(`You can only select up to ${MAX_BATCH} loans at a time.`)
      return
    }
    selectedIds.value = [...selectedIds.value, id]
  } else {
    selectedIds.value = selectedIds.value.filter((x) => x !== id)
  }
}

function toggleSelectAll(checked) {
  if (!checked) {
    clearSelection()
    return
  }
  const ids = selectableItems.value.map((i) => i.id).slice(0, MAX_BATCH)
  if (selectableItems.value.length > MAX_BATCH) {
    notification.error(`Only the first ${MAX_BATCH} loans were selected (batch limit).`)
  }
  selectedIds.value = ids
}

function clearSelection() {
  selectedIds.value = []
}

function goPage(page) {
  clearSelection()
  fetch(page)
}

async function refreshAfterBulk(result, message) {
  bulkResultMessage.value = message
  bulkFailed.value = result?.failed ?? []
  showResultModal.value = true
  clearSelection()
  authStore.fetchPendingApprovalCount?.()
  await fetch(meta.currentPage)
}

async function handleBulkApprove() {
  const count = selectedIds.value.length
  if (count === 0) return
  const ok = await confirm(`Approve ${count} selected loan${count === 1 ? '' : 's'}?`)
  if (!ok) return

  bulkProcessing.value = true
  try {
    const response = await approvals.bulkApprove(selectedIds.value)
    const body = response.data
    await refreshAfterBulk(body.data ?? body, body.message ?? 'Bulk approval completed.')
    notification.success(body.message ?? 'Bulk approval completed.')
  } catch (error) {
    notification.error(error.response?.data?.message || 'Failed to approve selected loans.')
  } finally {
    bulkProcessing.value = false
  }
}

async function handleBulkRelease() {
  const count = selectedIds.value.length
  if (count === 0) return

  // Releasing moves money, so make the confirmation explicit about the total.
  const total = selectedItems.value.reduce((sum, i) => sum + Number(i.amount ?? 0), 0)
  const ok = await confirm(
    `Release ${count} loan${count === 1 ? '' : 's'} totalling ` +
    `₱${total.toLocaleString('en-PH', { minimumFractionDigits: 2 })}? This cannot be undone.`
  )
  if (!ok) return

  bulkProcessing.value = true
  try {
    const response = await approvals.bulkRelease(selectedIds.value)
    const body = response.data
    await refreshAfterBulk(body.data ?? body, body.message ?? 'Bulk release completed.')
    notification.success(body.message ?? 'Bulk release completed.')
  } catch (error) {
    notification.error(error.response?.data?.message || 'Failed to release selected loans.')
  } finally {
    bulkProcessing.value = false
  }
}

function openBulkDisapprove() {
  bulkRemarks.value = ''
  bulkRemarksError.value = ''
  showBulkDisapproveModal.value = true
}

async function handleBulkDisapprove() {
  if (!bulkRemarks.value.trim()) {
    bulkRemarksError.value = 'Remarks are required.'
    return
  }
  bulkRemarksError.value = ''

  bulkProcessing.value = true
  try {
    const response = await approvals.bulkDisapprove(selectedIds.value, bulkRemarks.value.trim())
    const body = response.data
    showBulkDisapproveModal.value = false
    await refreshAfterBulk(body.data ?? body, body.message ?? 'Bulk disapproval completed.')
    notification.success(body.message ?? 'Bulk disapproval completed.')
  } catch (error) {
    notification.error(error.response?.data?.message || 'Failed to disapprove selected loans.')
  } finally {
    bulkProcessing.value = false
  }
}

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
  clearSelection()
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

:deep(.col-select) {
  width: 42px;
}

.bulk-action-bar {
  position: sticky;
  bottom: 0;
  z-index: 5;
  margin-top: 1rem;
  background: #fff;
  border: 1px solid #dee2e6;
  border-radius: 0.5rem;
}
</style>
