<template>
  <AppLayout>
    <div class="d-flex align-items-center mb-4">
      <router-link to="/admin/members" class="btn btn-outline-secondary btn-sm me-3">
        <i class="bi bi-arrow-left me-1"></i>Back
      </router-link>
      <h4 class="fw-bold mb-0">Member Details</h4>
    </div>

    <AppLoading :loading="loading" text="Loading member details..." />

    <template v-if="!loading && member">
      <div class="row g-4">
        <!-- Left Column - Profile -->
        <div class="col-lg-4">
          <AppCard title="Profile">
            <div class="text-center mb-3">
              <div
                class="rounded-circle bg-primary bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-2"
                style="width: 72px; height: 72px;"
              >
                <i class="bi bi-person-fill fs-2 text-primary"></i>
              </div>
              <h5 class="fw-bold mb-1">{{ member.full_name ?? `${member.first_name ?? ''} ${member.last_name ?? ''}`.trim() }}</h5>
              <span class="font-monospace text-muted">{{ member.employee_id }}</span>
            </div>

            <ul class="list-group list-group-flush small">
              <li class="list-group-item d-flex justify-content-between px-0">
                <span class="text-muted">Employment Type</span>
                <AppBadge
                  :variant="member.employment_type === 'permanent' ? 'success' : 'info'"
                  :text="member.employment_type ?? '-'"
                />
              </li>
              <li class="list-group-item d-flex justify-content-between px-0">
                <span class="text-muted">Position</span>
                <span class="fw-medium">{{ member.position ?? '-' }}</span>
              </li>
              <li class="list-group-item d-flex justify-content-between px-0">
                <span class="text-muted">Department</span>
                <span class="fw-medium">{{ member.department ?? '-' }}</span>
              </li>
              <li class="list-group-item d-flex justify-content-between px-0">
                <span class="text-muted">Email</span>
                <span class="fw-medium">{{ member.email ?? '-' }}</span>
              </li>
              <li class="list-group-item d-flex justify-content-between px-0">
                <span class="text-muted">Mobile</span>
                <span class="fw-medium">{{ member.mobile ?? '-' }}</span>
              </li>
              <li class="list-group-item d-flex justify-content-between px-0">
                <span class="text-muted">Base Pay</span>
                <span class="fw-medium">&#8369;{{ Number(member.base_pay ?? 0).toLocaleString() }}</span>
              </li>
              <li class="list-group-item d-flex justify-content-between px-0">
                <span class="text-muted">Take-Home Pay</span>
                <span class="fw-medium">&#8369;{{ Number(member.take_home_pay ?? 0).toLocaleString() }}</span>
              </li>
            </ul>
          </AppCard>
        </div>

        <!-- Right Column -->
        <div class="col-lg-8">
          <!-- Loan History -->
          <AppCard title="Loan History" :padding="false" class="mb-4">
            <AppTable :columns="loanColumns" :items="member.loans ?? []" empty-text="No loan history.">
              <template #cell(loan_type)="{ value }">
                {{ formatLoanType(value) }}
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

          <!-- Payment statement for this member -->
          <PaymentStatementCard v-if="member.id" :user-id="member.id" class="mb-4" />

          <!-- Dependents -->
          <AppCard title="Dependents" :padding="false">
            <AppTable :columns="dependentColumns" :items="member.dependents ?? []" empty-text="No dependents listed.">
              <template #cell(full_name)="{ item }">
                {{ item.full_name ?? `${item.first_name ?? ''} ${item.last_name ?? ''}`.trim() }}
              </template>
              <template #cell(coverage_type)="{ value }">
                <AppBadge
                  :variant="value && value !== 'none' ? 'success' : 'secondary'"
                  :text="value ? value.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) : 'None'"
                />
              </template>
              <template #cell(is_beneficiary)="{ value }">
                <span v-if="value" class="badge bg-info"><i class="bi bi-star-fill me-1"></i>Yes</span>
                <span v-else class="text-muted">—</span>
              </template>
            </AppTable>
          </AppCard>
        </div>
      </div>
    </template>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useLoading } from '@/composables/useLoading'
import admin from '@/services/admin'
import AppLayout from '@/components/layout/AppLayout.vue'
import AppCard from '@/components/ui/AppCard.vue'
import AppTable from '@/components/ui/AppTable.vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import AppStatusBadge from '@/components/ui/AppStatusBadge.vue'
import AppLoading from '@/components/ui/AppLoading.vue'
import PaymentStatementCard from '@/components/member/PaymentStatementCard.vue'

const route = useRoute()
const { loading, withLoading } = useLoading()
const member = ref(null)

const loanColumns = [
  { key: 'loan_type', label: 'Type' },
  { key: 'amount', label: 'Amount' },
  { key: 'status', label: 'Status' },
  { key: 'created_at', label: 'Date' },
]

const dependentColumns = [
  { key: 'full_name', label: 'Name' },
  { key: 'relationship', label: 'Relationship' },
  { key: 'coverage_type', label: 'Coverage' },
  { key: 'is_beneficiary', label: 'Beneficiary' },
]

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

onMounted(() => {
  withLoading(async () => {
    const response = await admin.getMember(route.params.id)
    member.value = response.data.data ?? response.data
  })
})
</script>
