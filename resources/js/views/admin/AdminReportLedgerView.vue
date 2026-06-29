<template>
  <AppLayout>
    <!-- Header -->
    <div class="d-flex align-items-center gap-2 mb-1">
      <button class="btn btn-sm btn-outline-secondary" @click="$router.push('/admin/reports')">
        <i class="bi bi-arrow-left"></i>
      </button>
      <h4 class="fw-bold mb-0">Loan Ledger</h4>
    </div>
    <p class="text-muted small mb-4 ms-5">Outstanding loan balance per member. Click a row to view the full payment ledger.</p>

    <!-- Filters -->
    <AppCard class="mb-4">
      <div class="row g-3 align-items-end">
        <div class="col-md-3">
          <label class="form-label fw-semibold small">Employment Type</label>
          <select v-model="filters.employment_type" class="form-select form-select-sm">
            <option value="Contract of Service">Contract of Service (SC)</option>
            <option value="Permanent">Permanent</option>
            <option value="">All</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold small">Search Member (name or ID)</label>
          <input v-model="filters.search" type="text" class="form-control form-control-sm" placeholder="Optional…" @keyup.enter="fetchReport" />
        </div>
        <div class="col-md-3 d-flex gap-2">
          <AppButton variant="primary" size="sm" :loading="loading" class="w-100" @click="fetchReport">
            <i class="bi bi-search me-1"></i>Generate
          </AppButton>
          <AppButton variant="outline-secondary" size="sm" @click="resetFilters">
            <i class="bi bi-x"></i>
          </AppButton>
        </div>
      </div>
    </AppCard>

    <AppLoading :loading="loading" text="Generating ledger…" />

    <template v-if="!loading && data">
      <!-- Summary -->
      <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-3">
          <AppStatCard title="Members" :value="summary.member_count" icon="bi bi-people-fill" color="primary" />
        </div>
        <div class="col-sm-6 col-lg-3">
          <AppStatCard title="Previous Loan Bal." :value="summary.total_previous" icon="bi bi-clock-history" color="warning" prefix="₱" />
        </div>
        <div class="col-sm-6 col-lg-3">
          <AppStatCard title="Total Balance" :value="summary.total_balance" icon="bi bi-wallet2" color="danger" prefix="₱" />
        </div>
        <div class="col-sm-6 col-lg-3">
          <AppStatCard title="Total Collected" :value="summary.total_paid" icon="bi bi-check-circle" color="success" prefix="₱" />
        </div>
      </div>

      <div class="d-flex justify-content-end mb-3">
        <AppButton variant="outline-primary" size="sm" @click="printLedger">
          <i class="bi bi-printer me-1"></i>Print
        </AppButton>
      </div>

      <!-- AA SUMMARY table -->
      <AppCard :padding="false">
        <div class="table-responsive">
          <table class="table table-hover table-sm mb-0 align-middle">
            <thead class="table-light">
              <tr>
                <th style="width:50px;">NO.</th>
                <th>NAME</th>
                <th>DIVISION</th>
                <th class="text-end">PREVIOUS LOAN BAL</th>
                <th class="text-end">BALANCE</th>
                <th style="width:40px;"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!members.length">
                <td colspan="6" class="text-center text-muted py-3">No loan ledger records found.</td>
              </tr>
              <template v-for="(m, idx) in members" :key="m.employee_id">
                <tr style="cursor:pointer;" @click="toggle(m.employee_id)">
                  <td>{{ idx + 1 }}</td>
                  <td>
                    <span class="fw-semibold text-primary">{{ m.full_name }}</span>
                    <span class="text-muted small ms-1">({{ m.employee_id }})</span>
                  </td>
                  <td>{{ m.division || '-' }}</td>
                  <td class="text-end">{{ m.previous_loan_balance > 0 ? '₱' + fmt(m.previous_loan_balance) : '-' }}</td>
                  <td class="text-end fw-semibold">{{ m.balance > 0 ? '₱' + fmt(m.balance) : '-' }}</td>
                  <td class="text-center text-muted">
                    <i class="bi" :class="expanded.has(m.employee_id) ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                  </td>
                </tr>

                <!-- Expandable per-member loan detail -->
                <tr v-if="expanded.has(m.employee_id)">
                  <td colspan="6" class="bg-light p-3">
                    <div v-for="l in m.loans" :key="l.loan_no" class="mb-3">
                      <div class="d-flex flex-wrap justify-content-between align-items-center bg-white border rounded px-3 py-2 mb-2">
                        <div class="fw-semibold">
                          <span class="font-monospace text-primary">{{ l.reference_no }}</span>
                          <span class="text-muted mx-2">|</span>{{ l.loan_type }}
                          <AppStatusBadge :status="l.status" class="ms-2" />
                        </div>
                        <div class="small text-muted">{{ l.term_months }} mo &bull; {{ l.interest_rate }}%/mo</div>
                      </div>

                      <div class="table-responsive mb-2">
                        <table class="table table-bordered table-sm mb-0 bg-white">
                          <thead class="table-light">
                            <tr class="text-end">
                              <th>Principal</th><th>Interest</th><th>Total Payable</th>
                              <th>Monthly</th><th>Per Payday (½)</th><th>Co-Maker</th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr class="text-end">
                              <td>₱{{ fmt(l.principal) }}</td>
                              <td>₱{{ fmt(l.interest) }}</td>
                              <td class="fw-bold">₱{{ fmt(l.total) }}</td>
                              <td>₱{{ fmt(l.monthly_amortization) }}</td>
                              <td>₱{{ fmt(l.semi_monthly) }}</td>
                              <td class="text-start">{{ l.co_maker || '—' }}</td>
                            </tr>
                          </tbody>
                        </table>
                      </div>

                      <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0 border bg-white">
                          <thead class="table-light">
                            <tr>
                              <th style="width:40px;">#</th>
                              <th>OR Date</th>
                              <th>OR #</th>
                              <th class="text-end">Amount</th>
                              <th class="text-end">Balance</th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr v-if="!l.payments.length">
                              <td colspan="5" class="text-center text-muted py-2">No payments recorded. Opening balance ₱{{ fmt(l.total) }}.</td>
                            </tr>
                            <tr v-for="(p, i) in l.payments" :key="i">
                              <td>{{ i + 1 }}</td>
                              <td>{{ p.date ?? '—' }}</td>
                              <td>{{ p.or_number ?? '—' }}</td>
                              <td class="text-end">₱{{ fmt(p.amount) }}</td>
                              <td class="text-end">₱{{ fmt(p.balance) }}</td>
                            </tr>
                          </tbody>
                          <tfoot>
                            <tr class="table-light fw-semibold">
                              <td colspan="3" class="text-end">Total Paid</td>
                              <td class="text-end text-success">₱{{ fmt(l.total_paid) }}</td>
                              <td class="text-end text-danger">₱{{ fmt(l.remaining) }}</td>
                            </tr>
                          </tfoot>
                        </table>
                      </div>
                    </div>
                  </td>
                </tr>
              </template>
            </tbody>
            <tfoot v-if="members.length">
              <tr class="table-light fw-bold">
                <td colspan="3" class="text-end">TOTAL</td>
                <td class="text-end">₱{{ fmt(summary.total_previous) }}</td>
                <td class="text-end">₱{{ fmt(summary.total_balance) }}</td>
                <td></td>
              </tr>
            </tfoot>
          </table>
        </div>
      </AppCard>
    </template>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import { useNotificationStore } from '@/stores/notification'
import { useAdminContextStore } from '@/stores/adminContext'
import admin from '@/services/admin'
import AppLayout from '@/components/layout/AppLayout.vue'
import AppCard from '@/components/ui/AppCard.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppStatCard from '@/components/ui/AppStatCard.vue'
import AppStatusBadge from '@/components/ui/AppStatusBadge.vue'
import AppLoading from '@/components/ui/AppLoading.vue'

const notify = useNotificationStore()
const adminContext = useAdminContextStore()
const loading = ref(false)
const data = ref(null)
const members = ref([])
const summary = ref({})
const expanded = ref(new Set())

const filters = ref({ employment_type: 'Contract of Service', search: '' })

function activeFilters() {
  const manual = Object.fromEntries(Object.entries(filters.value).filter(([, v]) => v !== ''))
  return { ...adminContext.filterParam, ...manual }
}

function fmt(val) {
  return Number(val ?? 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function toggle(id) {
  const next = new Set(expanded.value)
  next.has(id) ? next.delete(id) : next.add(id)
  expanded.value = next
}

async function fetchReport() {
  loading.value = true
  try {
    const { data: res } = await admin.getReportLedger(activeFilters())
    data.value = res.data ?? res
    members.value = data.value.members ?? []
    summary.value = data.value.summary ?? {}
    expanded.value = new Set()
  } catch {
    notify.error('Failed to generate ledger.')
  } finally {
    loading.value = false
  }
}

function resetFilters() {
  filters.value = { employment_type: 'Contract of Service', search: '' }
  data.value = null
  members.value = []
}

function printLedger() {
  window.print()
}
</script>
