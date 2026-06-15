<template>
  <AppCard class="mb-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <h6 class="fw-bold mb-0">
          <i class="bi bi-hourglass-split me-1 text-warning"></i>
          Pending FMIS Allocations
          <span v-if="meta.total" class="badge bg-warning text-dark ms-2">{{ meta.total }}</span>
        </h6>
        <div class="text-muted small">FMIS payroll deductions awaiting allocation to specific loans.</div>
      </div>
      <div class="d-flex gap-2">
        <AppButton variant="outline-secondary" size="sm" :loading="refreshing" @click="fetch(1)">
          <i v-if="!refreshing" class="bi bi-arrow-clockwise me-1"></i>Refresh
        </AppButton>
        <AppButton variant="primary" size="sm" :loading="linking" @click="runLinker">
          <i v-if="!linking" class="bi bi-magic me-1"></i>
          {{ linking ? 'Linking…' : 'Run Linker' }}
        </AppButton>
      </div>
    </div>

    <div class="row g-2 mb-3">
      <div class="col-md-3">
        <select v-model.number="filterYear" class="form-select form-select-sm" @change="fetch(1)">
          <option :value="null">All years</option>
          <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
        </select>
      </div>
      <div class="col-md-5">
        <input
          v-model="filterSearch"
          type="text"
          class="form-control form-control-sm"
          placeholder="Employee ID or DV number..."
          @keyup.enter="fetch(1)"
        />
      </div>
    </div>

    <AppLoading :loading="loading && !items.length" text="Loading pending allocations..." />

    <div v-if="!loading && !items.length" class="text-center text-muted py-3">
      <i class="bi bi-check-circle text-success me-1"></i>
      Nothing pending — every FMIS row has been matched.
    </div>

    <div v-for="row in items" :key="row.fmis_id" class="border rounded mb-2">
      <div class="d-flex align-items-center justify-content-between px-3 py-2 bg-light">
        <div class="flex-grow-1">
          <div class="d-flex align-items-center gap-2">
            <span v-if="row.member" class="fw-semibold">
              {{ row.member.full_name }}
            </span>
            <span v-else class="text-warning fw-semibold">
              <i class="bi bi-exclamation-triangle me-1"></i>Unregistered: {{ row.employee_id }}
            </span>
            <span class="text-muted small font-monospace">{{ row.employee_id }}</span>
            <span class="badge bg-primary">&#8369;{{ formatPeso(row.amount) }}</span>
            <span class="badge bg-light text-dark border">{{ monthNames[row.month - 1] }} {{ row.year }}</span>
          </div>
          <div class="small text-muted mt-1">
            DV {{ row.dv_number }} · {{ row.dv_date }} · {{ row.fund }}
          </div>
          <div class="small mt-1" :class="row.suggestion ? 'text-success' : 'text-warning'">
            <i :class="row.suggestion ? 'bi bi-check2-circle' : 'bi bi-exclamation-circle'" class="me-1"></i>
            {{ row.reason }}
          </div>
        </div>
        <div class="d-flex flex-column gap-1">
          <AppButton
            v-if="row.suggestion"
            variant="success"
            size="sm"
            :loading="applyingId === row.fmis_id"
            :disabled="applyingId !== null"
            @click="applySuggestion(row)"
          >
            <i class="bi bi-check-lg me-1"></i>Apply Suggestion
          </AppButton>
          <AppButton
            v-if="row.active_loans?.length"
            variant="outline-primary"
            size="sm"
            :disabled="applyingId !== null"
            @click="openCustom(row)"
          >
            <i class="bi bi-sliders me-1"></i>Custom split
          </AppButton>
        </div>
      </div>
      <div v-if="row.suggestion" class="px-3 py-2 small">
        <div class="text-muted mb-1">Suggested split:</div>
        <div v-for="alloc in row.suggestion" :key="alloc.loan_id" class="d-flex justify-content-between border-bottom py-1">
          <span>
            <span class="badge me-1" :class="alloc.loan_type === 'Emergency' ? 'bg-danger' : 'bg-info text-dark'">
              {{ alloc.loan_type }}
            </span>
            Loan #{{ alloc.loan_id }}
          </span>
          <span class="fw-semibold">&#8369;{{ formatPeso(alloc.amount) }}</span>
        </div>
      </div>
      <div v-else-if="row.active_loans?.length" class="px-3 py-2 small">
        <div class="text-muted mb-1">Active loans for this member:</div>
        <div v-for="loan in row.active_loans" :key="loan.id" class="d-flex justify-content-between border-bottom py-1">
          <span>
            <span class="badge me-1" :class="loan.category === 'emergency' ? 'bg-danger' : 'bg-info text-dark'">
              {{ loan.loan_type }}
            </span>
            Loan #{{ loan.id }} · monthly &#8369;{{ formatPeso(loan.monthly_amortization) }} · remaining &#8369;{{ formatPeso(loan.remaining) }}
          </span>
        </div>
      </div>
    </div>

    <AppPagination
      v-if="items.length"
      :meta="{ current_page: meta.current_page, last_page: meta.last_page, total: meta.total, per_page: meta.per_page }"
      @page-change="fetch"
    />

    <!-- Custom split modal -->
    <AppModal :show="!!customRow" :title="customRow ? `Allocate ₱${formatPeso(customRow.amount)} from DV ${customRow.dv_number}` : 'Allocate'" size="lg" @close="closeCustom">
      <div v-if="customRow" class="mb-3 small">
        <div class="text-muted">
          Employee: <strong>{{ customRow.member?.full_name ?? customRow.employee_id }}</strong>
          · Period: <strong>{{ monthNames[customRow.month - 1] }} {{ customRow.year }}</strong>
        </div>
      </div>
      <table v-if="customRow" class="table table-sm align-middle">
        <thead>
          <tr>
            <th>Loan</th>
            <th>Type</th>
            <th class="text-end">Monthly</th>
            <th class="text-end">Remaining</th>
            <th class="text-end">Apply (₱)</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="loan in customRow.active_loans" :key="loan.id">
            <td class="font-monospace small">#{{ loan.id }}</td>
            <td>
              <span class="badge" :class="loan.category === 'emergency' ? 'bg-danger' : 'bg-info text-dark'">
                {{ loan.loan_type }}
              </span>
            </td>
            <td class="text-end">&#8369;{{ formatPeso(loan.monthly_amortization) }}</td>
            <td class="text-end">&#8369;{{ formatPeso(loan.remaining) }}</td>
            <td class="text-end" style="width: 140px;">
              <input
                v-model.number="customAmounts[loan.id]"
                type="number"
                min="0"
                step="0.01"
                class="form-control form-control-sm text-end"
              />
            </td>
          </tr>
        </tbody>
        <tfoot>
          <tr class="fw-bold">
            <td colspan="4" class="text-end">Total</td>
            <td class="text-end" :class="customTotalValid ? 'text-success' : 'text-danger'">
              &#8369;{{ formatPeso(customTotal) }} / &#8369;{{ formatPeso(customRow.amount) }}
            </td>
          </tr>
        </tfoot>
      </table>

      <template #footer>
        <AppButton variant="secondary" @click="closeCustom">Cancel</AppButton>
        <AppButton variant="primary" :loading="applyingId === customRow?.fmis_id" :disabled="!customAnyPositive" @click="applyCustom">
          <i class="bi bi-check-lg me-1"></i>Apply Allocation
        </AppButton>
      </template>
    </AppModal>
  </AppCard>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useNotificationStore } from '@/stores/notification'
import loanPaymentsService from '@/services/loanPayments'
import AppCard from '@/components/ui/AppCard.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppModal from '@/components/ui/AppModal.vue'
import AppLoading from '@/components/ui/AppLoading.vue'
import AppPagination from '@/components/ui/AppPagination.vue'

const emit = defineEmits(['after-apply'])
const notify = useNotificationStore()

const items = ref([])
const meta = reactive({ current_page: 1, last_page: 1, total: 0, per_page: 20 })
const loading = ref(false)
const refreshing = ref(false)
const linking = ref(false)
const applyingId = ref(null)

const filterYear = ref(null)
const filterSearch = ref('')
const yearOptions = Array.from({ length: 6 }, (_, i) => new Date().getFullYear() - i)

const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']

const customRow = ref(null)
const customAmounts = reactive({})
const customTotal = computed(() =>
  Object.values(customAmounts).reduce((s, v) => s + (Number(v) || 0), 0)
)
const customTotalValid = computed(() =>
  customRow.value ? Math.abs(customTotal.value - Number(customRow.value.amount)) < 0.01 : false
)
const customAnyPositive = computed(() => customTotal.value > 0)

function formatPeso(n) {
  return Number(n ?? 0).toLocaleString('en-PH', { minimumFractionDigits: 2 })
}

async function fetch(page = 1) {
  refreshing.value = true
  loading.value = items.value.length === 0
  try {
    const { data } = await loanPaymentsService.getPending({
      page,
      year: filterYear.value ?? undefined,
      search: filterSearch.value || undefined,
    })
    items.value = data.data ?? []
    Object.assign(meta, data.meta ?? {})
  } catch (e) {
    notify.error(e.response?.data?.message || 'Failed to load pending allocations.')
  } finally {
    loading.value = false
    refreshing.value = false
  }
}

async function runLinker() {
  if (linking.value) return
  if (!confirm('Run the FMIS linker now? It walks every unlinked row and applies auto-match rules.')) return
  linking.value = true
  try {
    const { data } = await loanPaymentsService.runLinker()
    notify.success(data.message || 'Linker complete.')
    await fetch(1)
    emit('after-apply')
  } catch (e) {
    notify.error(e.response?.data?.message || 'Linker failed.')
  } finally { linking.value = false }
}

async function applySuggestion(row) {
  if (!row.suggestion?.length) return
  applyingId.value = row.fmis_id
  try {
    await loanPaymentsService.applyPending(row.fmis_id, row.suggestion.map(s => ({ loan_id: s.loan_id, amount: s.amount })))
    notify.success(`Applied DV ${row.dv_number} to ${row.suggestion.length} loan(s).`)
    await fetch(meta.current_page)
    emit('after-apply')
  } catch (e) {
    notify.error(e.response?.data?.message || 'Failed to apply.')
  } finally { applyingId.value = null }
}

function openCustom(row) {
  customRow.value = row
  Object.keys(customAmounts).forEach(k => delete customAmounts[k])
  // Pre-fill with the loan's monthly amortization
  for (const loan of row.active_loans) {
    customAmounts[loan.id] = Number(loan.monthly_amortization)
  }
}

function closeCustom() {
  customRow.value = null
  Object.keys(customAmounts).forEach(k => delete customAmounts[k])
}

async function applyCustom() {
  if (!customRow.value) return
  const allocations = Object.entries(customAmounts)
    .filter(([, amount]) => Number(amount) > 0)
    .map(([loanId, amount]) => ({ loan_id: Number(loanId), amount: Number(amount) }))

  if (!allocations.length) {
    notify.error('Enter at least one positive amount.')
    return
  }

  applyingId.value = customRow.value.fmis_id
  try {
    await loanPaymentsService.applyPending(customRow.value.fmis_id, allocations)
    notify.success('Allocation saved.')
    closeCustom()
    await fetch(meta.current_page)
    emit('after-apply')
  } catch (e) {
    notify.error(e.response?.data?.message || 'Failed to apply.')
  } finally { applyingId.value = null }
}

onMounted(() => fetch(1))
defineExpose({ refresh: () => fetch(meta.current_page) })
</script>
