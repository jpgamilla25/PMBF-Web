<template>
  <AppLayout>
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
      <h4 class="fw-bold mb-0">Member Management</h4>
      <div class="d-flex align-items-center gap-3">
        <!-- Shown even at zero: an empty table with no count reads as a failed
             load rather than an honest "nothing matched". -->
        <span v-if="!loading" class="text-muted small">
          {{ meta.total }} {{ meta.total === 1 ? 'member' : 'members' }}
        </span>
        <AppButton variant="primary" @click="openCreate">
          <i class="bi bi-person-plus me-1" aria-hidden="true"></i>New PMBF Employee
        </AppButton>
      </div>
    </div>

    <!-- Filter Bar -->
    <AppCard class="mb-4">
      <div class="row g-3 align-items-end">
        <div class="col-md-5">
          <AppInput
            v-model="searchQuery"
            type="search"
            label="Search"
            placeholder="Search by name, member ID or email..."
            @keyup.enter="applyFilters"
          />
        </div>
        <div class="col-md-4">
          <!-- When the sidebar context is set it wins, so show that rather than
               a dropdown whose value would be silently discarded. Mirrors the
               pattern already used in AdminReportMembersView. -->
          <template v-if="contextType">
            <label class="form-label">Employment Type</label>
            <div
              class="form-control bg-light text-muted d-flex align-items-center gap-2 mb-3"
              title="Set by the member-type context in the sidebar"
            >
              <i class="bi bi-lock-fill small" aria-hidden="true"></i>
              <span>{{ contextType }}</span>
            </div>
          </template>
          <AppInput
            v-else
            v-model="employmentFilter"
            type="select"
            label="Employment Type"
            :options="employmentOptions"
          />
        </div>
        <div class="col-md-3">
          <div class="d-flex gap-2 mb-3">
            <AppButton variant="primary" block @click="applyFilters">
              <i class="bi bi-funnel me-1" aria-hidden="true"></i>Filter
            </AppButton>
            <AppButton
              v-if="hasActiveFilters"
              variant="outline-secondary"
              title="Clear search and filters"
              @click="resetFilters"
            >
              <i class="bi bi-x-lg" aria-hidden="true"></i>
              <span class="visually-hidden">Clear filters</span>
            </AppButton>
          </div>
        </div>
      </div>
    </AppCard>

    <!-- Members Table -->
    <AppCard :padding="false">
      <AppTable
        :columns="columns"
        :items="items"
        :loading="loading"
        :sort-key="sortKey"
        :sort-direction="sortDirection"
        :empty-text="emptyText"
        @sort-change="onSortChange"
      >
        <template #cell(employee_id)="{ value }">
          <span class="fw-medium font-monospace">{{ value }}</span>
        </template>
        <template #cell(name)="{ item }">
          <div class="fw-semibold">{{ displayName(item) }}</div>
          <small class="text-muted">{{ item.email ?? '' }}</small>
        </template>
        <template #cell(employment_type)="{ value }">
          <AppBadge :variant="typeVariant(value)" :text="value ?? '-'" />
        </template>
        <template #cell(base_pay)="{ item, value }">
          <!-- Absent and zero must not look alike: members who are not on the
               PhilRice payroll have no figure, they do not earn nothing. -->
          <span v-if="!hasPayroll(item)" class="text-muted" title="Not on PhilRice payroll">&mdash;</span>
          <span v-else>&#8369;{{ Number(value ?? 0).toLocaleString() }}</span>
        </template>
        <template #cell(status)="{ value }">
          <AppStatusBadge :status="value" />
        </template>
        <template #cell(actions)="{ item }">
          <button
            v-if="isEditable(item)"
            class="btn btn-sm btn-outline-secondary me-1"
            :title="`Edit ${displayName(item)}`"
            :aria-label="`Edit ${displayName(item)}`"
            @click="openEdit(item)"
          >
            <i class="bi bi-pencil" aria-hidden="true"></i>
          </button>
          <button
            class="btn btn-sm btn-outline-secondary me-1"
            :title="`View shares for ${displayName(item)}`"
            :aria-label="`View shares for ${displayName(item)}`"
            @click="openShares(item.id)"
          >
            <i class="bi bi-pie-chart" aria-hidden="true"></i>
          </button>
          <router-link
            :to="`/admin/members/${item.id}`"
            class="btn btn-sm btn-outline-primary"
            :aria-label="`View ${displayName(item)}`"
          >
            <i class="bi bi-eye me-1" aria-hidden="true"></i>View
          </router-link>
        </template>
      </AppTable>

      <template #footer>
        <AppPagination
          :meta="{ current_page: meta.currentPage, last_page: meta.lastPage, total: meta.total, per_page: meta.perPage }"
          @page-change="fetch"
        />
      </template>
    </AppCard>

    <MemberSharesModal :show="showSharesModal" :user-id="sharesUserId" @close="closeShares" />

    <!-- Manual creation and correction are for PMBF Employees only. Permanent
         and Contract of Service members come from PhilRice HRIS, either by
         registering against their employee ID or through User Type Management. -->
    <PmbfEmployeeModal
      :show="showFormModal"
      :member="editing"
      :context-type="contextType"
      @close="showFormModal = false"
      @saved="applyFilters"
      @switch-context="switchToPmbfContext"
    />
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { usePagination } from '@/composables/usePagination'
import { useAdminContextStore } from '@/stores/adminContext'
import admin from '@/services/admin'
import AppLayout from '@/components/layout/AppLayout.vue'
import AppCard from '@/components/ui/AppCard.vue'
import AppTable from '@/components/ui/AppTable.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppInput from '@/components/ui/AppInput.vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import AppStatusBadge from '@/components/ui/AppStatusBadge.vue'
import AppPagination from '@/components/ui/AppPagination.vue'
import MemberSharesModal from '@/components/ui/MemberSharesModal.vue'
import PmbfEmployeeModal from '@/components/admin/PmbfEmployeeModal.vue'

const PMBF_EMPLOYEE = 'PMBF Employee'

const adminContext = useAdminContextStore()
const { items, meta, loading, filters, fetch } = usePagination(admin.getMembers)

const showSharesModal = ref(false)
const sharesUserId = ref(null)

function openShares(userId) {
  sharesUserId.value = userId
  showSharesModal.value = true
}

function closeShares() {
  showSharesModal.value = false
  sharesUserId.value = null
}

// ── Filters ──
const searchQuery = ref('')
const employmentFilter = ref('')
const sortKey = ref('')
const sortDirection = ref('asc')

/** The sidebar context, when it is narrowing the list. */
const contextType = computed(() =>
  adminContext.memberType && adminContext.memberType !== 'all' ? adminContext.memberType : null
)

const hasActiveFilters = computed(() => !!searchQuery.value || !!employmentFilter.value)

const emptyText = computed(() =>
  hasActiveFilters.value || contextType.value
    ? 'No members match these filters.'
    : 'No members found.'
)

const employmentOptions = [
  { value: '', label: 'All Types' },
  { value: 'Contract of Service', label: 'Contract of Service' },
  { value: 'Permanent', label: 'Permanent' },
  { value: 'Non-Member', label: 'Non-Member' },
  { value: PMBF_EMPLOYEE, label: PMBF_EMPLOYEE },
]

const typeVariants = {
  Permanent: 'success',
  'Contract of Service': 'warning',
  'Non-Member': 'secondary',
  [PMBF_EMPLOYEE]: 'info',
}

function typeVariant(value) {
  return typeVariants[value] ?? 'secondary'
}

const columns = [
  { key: 'employee_id', label: 'Member ID', sortable: true },
  { key: 'name', label: 'Name', sortable: true },
  { key: 'employment_type', label: 'Type', sortable: true },
  { key: 'department', label: 'Department', sortable: true },
  { key: 'base_pay', label: 'Base Pay', sortable: true },
  { key: 'status', label: 'Status', sortable: true },
  { key: 'actions', label: '', srLabel: 'Actions', class: 'text-end' },
]

function displayName(item) {
  return item.full_name ?? `${item.first_name ?? ''} ${item.last_name ?? ''}`.trim()
}

/** Only PhilRice employment types draw a PhilRice salary. */
function hasPayroll(item) {
  return item.employment_type && item.employment_type !== PMBF_EMPLOYEE
}

/** Only locally-created members are editable; HRIS owns everyone else. */
function isEditable(item) {
  return item.employment_type === PMBF_EMPLOYEE
}

function onSortChange({ key, direction }) {
  sortKey.value = key
  sortDirection.value = direction
  applyFilters()
}

function applyFilters() {
  filters.search = searchQuery.value || undefined
  // The sidebar context wins — which is why the dropdown is replaced by a
  // locked field above rather than left looking operable.
  filters.employment_type = contextType.value || employmentFilter.value || undefined
  filters.sort = sortKey.value || undefined
  filters.direction = sortKey.value ? sortDirection.value : undefined
  fetch(1)
}

function resetFilters() {
  searchQuery.value = ''
  employmentFilter.value = ''
  applyFilters()
}

watch(() => adminContext.memberType, () => applyFilters())
// A dropdown that needs a second click to take effect reads as broken.
watch(employmentFilter, () => applyFilters())

// ── Create / edit ──
const showFormModal = ref(false)
const editing = ref(null)

function openCreate() {
  editing.value = null
  showFormModal.value = true
}

function openEdit(item) {
  editing.value = item
  showFormModal.value = true
}

function switchToPmbfContext() {
  adminContext.setType(PMBF_EMPLOYEE)
  showFormModal.value = false
}

onMounted(() => applyFilters())
</script>
