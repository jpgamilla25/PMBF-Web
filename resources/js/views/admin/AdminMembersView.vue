<template>
  <AppLayout>
    <div class="d-flex align-items-center justify-content-between mb-4">
      <h4 class="fw-bold mb-0">Member Management</h4>
      <span v-if="meta.total" class="text-muted small">{{ meta.total }} members</span>
    </div>

    <!-- Filter Bar -->
    <AppCard class="mb-4">
      <div class="row g-3 align-items-end">
        <div class="col-md-5">
          <AppInput
            v-model="searchQuery"
            label="Search"
            placeholder="Search by name or employee ID..."
            @keyup.enter="applyFilters"
          />
        </div>
        <div class="col-md-4">
          <AppInput
            v-model="employmentFilter"
            type="select"
            label="Employment Type"
            :options="employmentOptions"
          />
        </div>
        <div class="col-md-3">
          <AppButton variant="primary" block @click="applyFilters">
            <i class="bi bi-funnel me-1"></i>Filter
          </AppButton>
        </div>
      </div>
    </AppCard>

    <!-- Members Table -->
    <AppCard :padding="false">
      <AppTable :columns="columns" :items="items" :loading="loading" empty-text="No members found.">
        <template #cell(employee_id)="{ value }">
          <span class="fw-medium font-monospace">{{ value }}</span>
        </template>
        <template #cell(name)="{ item }">
          <div class="fw-semibold">{{ item.full_name ?? `${item.first_name ?? ''} ${item.last_name ?? ''}`.trim() }}</div>
          <small class="text-muted">{{ item.email ?? '' }}</small>
        </template>
        <template #cell(employment_type)="{ value }">
          <AppBadge
            :variant="value === 'permanent' ? 'success' : value === 'sc' ? 'primary' : 'secondary'"
            :text="value ?? '-'"
          />
        </template>
        <template #cell(base_pay)="{ value }">
          &#8369;{{ Number(value ?? 0).toLocaleString() }}
        </template>
        <template #cell(status)="{ value }">
          <AppStatusBadge :status="value" />
        </template>
        <template #cell(actions)="{ item }">
          <button
            class="btn btn-sm btn-outline-secondary me-1"
            title="View shares"
            @click="openShares(item.id)"
          >
            <i class="bi bi-pie-chart"></i>
          </button>
          <router-link :to="`/admin/members/${item.id}`" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-eye me-1"></i>View
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
  </AppLayout>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
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

const searchQuery = ref('')
const employmentFilter = ref('')

const employmentOptions = [
  { value: '', label: 'All Types' },
  { value: 'Contract of Service', label: 'Contract of Service' },
  { value: 'Permanent', label: 'Permanent' },
  { value: 'Non-Member', label: 'Non-Member' },
]

const columns = [
  { key: 'employee_id', label: 'Employee ID' },
  { key: 'name', label: 'Name' },
  { key: 'employment_type', label: 'Type' },
  { key: 'department', label: 'Department' },
  { key: 'base_pay', label: 'Base Pay' },
  { key: 'status', label: 'Status' },
  { key: 'actions', label: '', class: 'text-end' },
]

function applyFilters() {
  filters.search = searchQuery.value || undefined
  // Sidebar context overrides dropdown when set
  const ctxType = adminContext.memberType !== 'all' ? adminContext.memberType : null
  filters.employment_type = ctxType || employmentFilter.value || undefined
  fetch(1)
}

// Re-fetch when admin switches member type in sidebar
watch(() => adminContext.memberType, () => applyFilters())

onMounted(() => applyFilters())
</script>
