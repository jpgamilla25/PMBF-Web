<template>
  <AppLayout>
    <div class="d-flex align-items-center justify-content-between mb-4">
      <h4 class="fw-bold mb-0">All Loans</h4>
      <span v-if="meta.total" class="text-muted small">{{ meta.total }} loans</span>
    </div>

    <!-- Filter Bar -->
    <AppCard class="mb-4">
      <div class="row g-3 align-items-end">
        <div class="col-md-4">
          <AppInput
            v-model="statusFilter"
            type="select"
            label="Status"
            :options="statusOptions"
          />
        </div>
        <div class="col-md-4">
          <AppInput
            v-model="typeFilter"
            type="select"
            label="Loan Type"
            :options="typeOptions"
          />
        </div>
        <div class="col-md-4">
          <AppButton variant="primary" block @click="applyFilters">
            <i class="bi bi-funnel me-1"></i>Filter
          </AppButton>
        </div>
      </div>
    </AppCard>

    <!-- Loans Table -->
    <AppCard :padding="false">
      <AppTable :columns="columns" :items="items" :loading="loading" empty-text="No loans found.">
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
          <span class="fw-medium">&#8369;{{ Number(value ?? 0).toLocaleString() }}</span>
        </template>
        <template #cell(monthly_amortization)="{ value }">
          &#8369;{{ Number(value ?? 0).toLocaleString() }}
        </template>
        <template #cell(total_paid)="{ item }">
          <span class="text-success fw-medium">&#8369;{{ peso(item.total_paid) }}</span>
        </template>
        <template #cell(remaining_balance)="{ item }">
          <!-- Fully settled reads better as a tick than as a zero. -->
          <span v-if="isSettled(item)" class="text-success">
            <i class="bi bi-check-circle-fill me-1"></i>Settled
          </span>
          <span v-else class="fw-medium">&#8369;{{ peso(item.remaining_balance) }}</span>
        </template>
        <template #cell(status)="{ value }">
          <AppStatusBadge :status="value" />
        </template>
        <template #cell(created_at)="{ value }">
          {{ formatDate(value) }}
        </template>
        <template #cell(actions)="{ item }">
          <div class="d-flex gap-1 justify-content-end">
            <router-link :to="`/approvals/${item.id}`" class="btn btn-sm btn-outline-primary" title="View loan">
              <i class="bi bi-eye"></i>
            </router-link>

            <!-- Open in a new tab: these stream a PDF, so routing to them
                 would replace the list the admin is working through. -->
            <a
              :href="pdfUrl(item.id)"
              target="_blank"
              rel="noopener"
              class="btn btn-sm btn-outline-danger"
              title="Loan document (PDF)"
            >
              <i class="bi bi-file-earmark-pdf"></i>
            </a>
            <a
              v-if="item.status !== 'cancelled'"
              :href="breakdownUrl(item.id)"
              target="_blank"
              rel="noopener"
              class="btn btn-sm btn-outline-secondary"
              title="Payment breakdown (PDF)"
            >
              <i class="bi bi-list-columns-reverse"></i>
            </a>
            <a
              v-if="item.status !== 'cancelled' && item.user?.employment_type === 'Contract of Service'"
              :href="agreementUrl(item.id)"
              target="_blank"
              rel="noopener"
              class="btn btn-sm btn-outline-primary"
              title="Loan agreement (PDF)"
            >
              <i class="bi bi-file-earmark-text"></i>
            </a>

            <router-link
              v-if="item.status === 'released'"
              to="/admin/payments"
              class="btn btn-sm btn-outline-success"
              title="Record payment"
            >
              <i class="bi bi-cash"></i>
            </router-link>
          </div>
        </template>
      </AppTable>

      <template #footer>
        <AppPagination
          :meta="{ current_page: meta.currentPage, last_page: meta.lastPage, total: meta.total, per_page: meta.perPage }"
          @page-change="fetch"
        />
      </template>
    </AppCard>
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
import AppStatusBadge from '@/components/ui/AppStatusBadge.vue'
import AppPagination from '@/components/ui/AppPagination.vue'

const adminContext = useAdminContextStore()
const { items, meta, loading, filters, fetch } = usePagination(admin.getLoans)

const statusFilter = ref('')
const typeFilter = ref('')

const statusOptions = [
  { value: '', label: 'All Statuses' },
  { value: 'pending', label: 'Pending' },
  { value: 'receiver_approved', label: 'Receiver Approved' },
  { value: 'committee_approved', label: 'Committee Approved' },
  { value: 'approved', label: 'Approved' },
  { value: 'released', label: 'Released' },
  { value: 'disapproved', label: 'Disapproved' },
  { value: 'completed', label: 'Completed' },
]

const typeOptions = [
  { value: '', label: 'All Loan Types' },
  { value: 'Salary Loan', label: 'Salary Loan' },
  { value: 'Consolidated', label: 'Consolidated' },
  { value: 'Multi-Purpose', label: 'Multi-Purpose' },
  { value: 'Emergency', label: 'Emergency' },
  { value: 'Hospitalization', label: 'Hospitalization' },
]

const columns = [
  { key: 'index', label: '#', class: 'text-center' },
  { key: 'applicant', label: 'Applicant' },
  { key: 'loan_type', label: 'Type' },
  { key: 'amount', label: 'Amount' },
  { key: 'monthly_amortization', label: 'Monthly' },
  { key: 'total_paid', label: 'Paid', class: 'text-end' },
  { key: 'remaining_balance', label: 'Remaining', class: 'text-end' },
  { key: 'status', label: 'Status' },
  { key: 'created_at', label: 'Date' },
  { key: 'actions', label: '', class: 'text-end' },
]

function peso(value) {
  return Number(value ?? 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

/** Tolerates the odd centavo of rounding drift in historical payments. */
function isSettled(loan) {
  return Number(loan.remaining_balance ?? 0) <= 0.01
}

/**
 * PDF routes are public so they can be opened in a new tab, and take the
 * token on the query string because a new tab carries no Authorization header.
 */
function pdfUrl(id) {
  return `/api/v1/loans/${id}/pdf?token=${localStorage.getItem('pmbf_token') ?? ''}`
}

function breakdownUrl(id) {
  return `/api/v1/loans/${id}/breakdown/pdf?token=${localStorage.getItem('pmbf_token') ?? ''}`
}

function agreementUrl(id) {
  return `/api/v1/loans/${id}/agreement/pdf?token=${localStorage.getItem('pmbf_token') ?? ''}`
}

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

function applyFilters() {
  filters.status = statusFilter.value || undefined
  filters.loan_type = typeFilter.value || undefined
  // Apply admin context filter
  if (adminContext.memberType !== 'all') {
    filters.employment_type = adminContext.memberType
  } else {
    filters.employment_type = undefined
  }
  fetch(1)
}

// Re-fetch when admin switches member type in sidebar
watch(() => adminContext.memberType, () => applyFilters())

onMounted(() => applyFilters())
</script>
