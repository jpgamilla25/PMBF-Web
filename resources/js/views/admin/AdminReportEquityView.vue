<template>
  <AppLayout>
    <div class="d-flex align-items-center gap-2 mb-1">
      <button class="btn btn-sm btn-outline-secondary" @click="$router.push('/admin/reports')">
        <i class="bi bi-arrow-left"></i>
      </button>
      <h4 class="fw-bold mb-0">Year-End Equity Report</h4>
    </div>
    <p class="text-muted small mb-4 ms-5">
      Permanent members — equity &amp; loans, dependents' medical premiums, and declared dependents &amp; beneficiaries.
    </p>

    <!-- Filters + exports -->
    <AppCard class="mb-4">
      <div class="d-flex flex-wrap gap-3 align-items-end">
        <div>
          <label class="form-label fw-semibold small">Year Ending</label>
          <select v-model.number="year" class="form-select form-select-sm" style="min-width: 120px;" @change="fetchReport">
            <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
          </select>
        </div>
        <div class="ms-auto d-flex gap-2">
          <AppButton variant="outline-success" size="sm" :loading="exporting === 'excel'" :disabled="!report" @click="download('excel')">
            <i class="bi bi-file-earmark-excel me-1"></i>Export Excel
          </AppButton>
          <AppButton variant="outline-danger" size="sm" :loading="exporting === 'pdf'" :disabled="!report" @click="download('pdf')">
            <i class="bi bi-filetype-pdf me-1"></i>Export PDF
          </AppButton>
        </div>
      </div>
    </AppCard>

    <AppLoading :loading="loading" text="Generating report..." />

    <template v-if="!loading && report">
      <ul class="nav nav-tabs mb-3">
        <li v-for="t in tabs" :key="t.key" class="nav-item">
          <button class="nav-link" :class="{ active: tab === t.key }" @click="tab = t.key">
            <i :class="t.icon" class="me-1"></i>{{ t.label }}
          </button>
        </li>
      </ul>

      <!-- ── 1. Equity & Loans ── -->
      <template v-if="tab === 'equity'">
        <div class="row g-3 mb-3">
          <div class="col-sm-6 col-xl-3"><AppStatCard title="Total Equity" :value="equity.totals.total_equity" icon="bi bi-pie-chart-fill" color="success" prefix="₱" /></div>
          <div class="col-sm-6 col-xl-3"><AppStatCard title="Total Loans" :value="equity.totals.total_loans" icon="bi bi-cash-stack" color="warning" prefix="₱" /></div>
          <div class="col-sm-6 col-xl-3"><AppStatCard title="Net Equity (Obligation)" :value="equity.totals.net_equity" icon="bi bi-scale" color="primary" prefix="₱" /></div>
          <div class="col-sm-6 col-xl-3"><AppStatCard title="Members" :value="equity.rows.length" icon="bi bi-people" color="primary" /></div>
        </div>
        <p class="text-muted small mb-2">
          <i class="bi bi-info-circle me-1"></i>
          Equity is cumulative up to Dec 31, {{ year }}. Loans are balances still owed at that date.
          PhilRice Counterpart is entered with the shares (Share Capital page or Import Shares).
        </p>
        <AppCard :padding="false">
          <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0 report-table">
              <thead class="table-light">
                <tr>
                  <th rowspan="2" class="text-center">#</th>
                  <th rowspan="2">Name</th>
                  <th rowspan="2" class="text-end">Personal Contribution</th>
                  <th rowspan="2" class="text-end">PhilRice Counterpart</th>
                  <th rowspan="2" class="text-end">Total Equity</th>
                  <th :colspan="loanKeys.length + 1" class="text-center">Loans</th>
                  <th rowspan="2" class="text-end">Net Equity (Obligation)</th>
                </tr>
                <tr>
                  <th v-for="k in loanKeys" :key="k" class="text-end">{{ report.loan_columns[k] }}</th>
                  <th class="text-end">Total</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="!equity.rows.length"><td :colspan="7 + loanKeys.length" class="text-center text-muted py-3">No Permanent members found.</td></tr>
                <tr v-for="r in equity.rows" :key="r.user_id">
                  <td class="text-center text-muted">{{ r.no }}</td>
                  <td class="text-nowrap">{{ r.name }}</td>
                  <td class="text-end">{{ money(r.personal) }}</td>
                  <td class="text-end">{{ money(r.counterpart) }}</td>
                  <td class="text-end fw-semibold">{{ money(r.total_equity) }}</td>
                  <td v-for="k in loanKeys" :key="k" class="text-end">{{ money(r.loans[k]) }}</td>
                  <td class="text-end fw-semibold">{{ money(r.total_loans) }}</td>
                  <td class="text-end fw-bold" :class="r.net_equity < 0 ? 'text-danger' : 'text-success'">{{ signed(r.net_equity) }}</td>
                </tr>
              </tbody>
              <tfoot v-if="equity.rows.length">
                <tr class="table-success fw-bold">
                  <td colspan="2">TOTAL</td>
                  <td class="text-end">{{ money(equity.totals.personal) }}</td>
                  <td class="text-end">{{ money(equity.totals.counterpart) }}</td>
                  <td class="text-end">{{ money(equity.totals.total_equity) }}</td>
                  <td v-for="k in loanKeys" :key="k" class="text-end">{{ money(equity.totals.loans[k]) }}</td>
                  <td class="text-end">{{ money(equity.totals.total_loans) }}</td>
                  <td class="text-end">{{ signed(equity.totals.net_equity) }}</td>
                </tr>
              </tfoot>
            </table>
          </div>
        </AppCard>
      </template>

      <!-- ── 2. Medical for Dependents ── -->
      <template v-if="tab === 'medical'">
        <div class="d-flex flex-wrap align-items-end gap-2 mb-3">
          <p class="text-muted small mb-0 me-auto">
            <i class="bi bi-info-circle me-1"></i>
            Default premium ₱{{ money(medical.default_premium) }} per dependent, deducted over {{ medical.installments }} months.
            Click a premium or month to edit it.
          </p>
          <div>
            <label class="form-label small fw-semibold mb-1">Post deductions for</label>
            <select v-model.number="postMonthValue" class="form-select form-select-sm">
              <option v-for="(m, i) in monthNames" :key="m" :value="i + 1">{{ m }} {{ year }}</option>
            </select>
          </div>
          <AppButton variant="primary" size="sm" :loading="posting" :disabled="!medical.rows.length" @click="postMonth">
            <i class="bi bi-check2-all me-1"></i>Post Month
          </AppButton>
        </div>
        <AppCard :padding="false">
          <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0 report-table">
              <thead class="table-light">
                <tr>
                  <th>PMBF Member</th>
                  <th>Dependent</th>
                  <th>Relationship</th>
                  <th class="text-end">Premium</th>
                  <th class="text-end">Monthly</th>
                  <th v-for="m in monthNames" :key="m" class="text-end">{{ m }}</th>
                  <th class="text-end">Total</th>
                  <th class="text-end">Balance</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="!medical.rows.length"><td colspan="19" class="text-center text-muted py-3">No covered dependents of Permanent members.</td></tr>
                <tr v-for="(r, i) in medical.rows" :key="r.dependent_id" :class="{ 'member-start': isFirstOfMember(i) }">
                  <td class="text-nowrap">{{ isFirstOfMember(i) ? r.member : '' }}</td>
                  <td class="text-nowrap">{{ r.dependent }}</td>
                  <td class="text-capitalize">{{ r.relationship }}</td>
                  <td class="text-end editable">
                    <input
                      v-if="editing === `pr-${r.dependent_id}`"
                      v-model="editValue"
                      type="number" min="0" step="0.01"
                      class="form-control form-control-sm text-end cell-input"
                      :disabled="saving"
                      @keyup.enter="savePremium(r)"
                      @keyup.esc="editing = null"
                      @blur="savePremium(r)"
                      v-focus
                    />
                    <span v-else class="d-block" @click="startEdit(`pr-${r.dependent_id}`, r.premium)">{{ money(r.premium) }}</span>
                  </td>
                  <td class="text-end">{{ money(r.monthly_deduction) }}</td>
                  <td v-for="m in 12" :key="m" class="text-end editable month-cell">
                    <input
                      v-if="editing === `pm-${r.dependent_id}-${m}`"
                      v-model="editValue"
                      type="number" min="0" step="0.01"
                      class="form-control form-control-sm text-end cell-input"
                      :disabled="saving"
                      @keyup.enter="savePayment(r, m)"
                      @keyup.esc="editing = null"
                      @blur="savePayment(r, m)"
                      v-focus
                    />
                    <span v-else class="d-block" @click="startEdit(`pm-${r.dependent_id}-${m}`, r.months[m] || r.monthly_deduction)">{{ money(r.months[m]) }}</span>
                  </td>
                  <td class="text-end fw-semibold">{{ money(r.total) }}</td>
                  <td class="text-end fw-semibold" :class="r.balance > 0 ? 'text-warning' : 'text-success'">{{ signed(r.balance) }}</td>
                </tr>
              </tbody>
              <tfoot v-if="medical.rows.length">
                <tr class="table-success fw-bold">
                  <td colspan="3">TOTAL</td>
                  <td class="text-end">{{ money(medical.totals.premium) }}</td>
                  <td class="text-end">{{ money(medical.totals.monthly_deduction) }}</td>
                  <td v-for="m in 12" :key="m" class="text-end">{{ money(medical.totals.months[m]) }}</td>
                  <td class="text-end">{{ money(medical.totals.total) }}</td>
                  <td class="text-end">{{ signed(medical.totals.balance) }}</td>
                </tr>
              </tfoot>
            </table>
          </div>
        </AppCard>
      </template>

      <!-- ── 3. Dependents & Beneficiaries ── -->
      <template v-if="tab === 'dependents'">
        <p class="text-muted small mb-2">
          <i class="bi bi-info-circle me-1"></i>
          Birthday and address come from the member profile; Date Started is the HRIS appointment date.
        </p>
        <AppCard :padding="false">
          <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0 report-table">
              <thead class="table-light">
                <tr>
                  <th class="text-center">No.</th>
                  <th>Name</th>
                  <th>Birthday</th>
                  <th>Address</th>
                  <th>Date Started</th>
                  <th>Dependents' Name</th>
                  <th>Relationship</th>
                  <th>Beneficiaries</th>
                  <th>Relationship</th>
                  <th>Updated</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="!report.dependents.length"><td colspan="10" class="text-center text-muted py-3">No Permanent members found.</td></tr>
                <template v-for="m in report.dependents" :key="m.user_id">
                  <tr v-for="i in span(m)" :key="`${m.user_id}-${i}`" :class="{ 'member-start': i === 1 }">
                    <template v-if="i === 1">
                      <td :rowspan="span(m)" class="text-center text-muted">{{ m.no }}</td>
                      <td :rowspan="span(m)" class="fw-semibold text-nowrap">
                        <router-link :to="`/admin/members/${m.user_id}`" class="text-reset">{{ m.name }}</router-link>
                      </td>
                      <td :rowspan="span(m)" class="text-nowrap">{{ fmtDate(m.birth_date) }}</td>
                      <td :rowspan="span(m)">{{ m.address || '' }}</td>
                      <td :rowspan="span(m)" class="text-nowrap">{{ fmtDate(m.date_started) }}</td>
                    </template>
                    <td>{{ m.dependents[i - 1]?.name || '' }}</td>
                    <td class="text-capitalize">{{ m.dependents[i - 1]?.relationship || '' }}</td>
                    <td>{{ m.beneficiaries[i - 1]?.name || '' }}</td>
                    <td class="text-capitalize">{{ m.beneficiaries[i - 1]?.relationship || '' }}</td>
                    <td v-if="i === 1" :rowspan="span(m)" class="text-nowrap">{{ fmtDate(m.updated) }}</td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>
        </AppCard>
      </template>
    </template>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useNotificationStore } from '@/stores/notification'
import admin from '@/services/admin'
import AppLayout from '@/components/layout/AppLayout.vue'
import AppCard from '@/components/ui/AppCard.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppStatCard from '@/components/ui/AppStatCard.vue'
import AppLoading from '@/components/ui/AppLoading.vue'

const notify = useNotificationStore()

const vFocus = { mounted: (el) => { el.focus(); el.select?.() } }

const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
const currentYear = new Date().getFullYear()
const yearOptions = Array.from({ length: 6 }, (_, i) => currentYear - i)

const tabs = [
  { key: 'equity', label: 'Equity & Loans', icon: 'bi bi-pie-chart' },
  { key: 'medical', label: 'Medical for Dependents', icon: 'bi bi-heart-pulse' },
  { key: 'dependents', label: 'Dependents & Beneficiaries', icon: 'bi bi-people' },
]

const year = ref(currentYear)
const tab = ref('equity')
const loading = ref(false)
const exporting = ref(null)
const report = ref(null)

const editing = ref(null)
const editValue = ref('')
const saving = ref(false)
const posting = ref(false)
const postMonthValue = ref(new Date().getMonth() + 1)

const equity = computed(() => report.value?.equity ?? { rows: [], totals: {} })
const medical = computed(() => report.value?.medical ?? { rows: [], totals: {} })
const loanKeys = computed(() => Object.keys(report.value?.loan_columns ?? {}))

function money(v) {
  const n = Number(v ?? 0)
  return n === 0 ? '-' : n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function signed(v) {
  const n = Number(v ?? 0)
  return n < 0 ? `(${money(Math.abs(n))})` : money(n)
}

function fmtDate(d) {
  if (!d) return ''
  return new Date(d + 'T00:00:00').toLocaleDateString('en-PH', { day: '2-digit', month: 'short', year: 'numeric' })
}

function span(m) {
  return Math.max(1, m.dependents.length, m.beneficiaries.length)
}

function isFirstOfMember(i) {
  return i === 0 || medical.value.rows[i - 1].user_id !== medical.value.rows[i].user_id
}

async function fetchReport({ quiet = false } = {}) {
  if (!quiet) loading.value = true
  try {
    const { data: res } = await admin.getReportEquity({ year: year.value })
    report.value = res.data ?? res
  } catch {
    notify.error('Failed to generate report.')
  } finally {
    loading.value = false
  }
}

function startEdit(key, value) {
  if (saving.value) return
  editing.value = key
  editValue.value = Number(value || 0) ? Number(value).toFixed(2) : ''
}

/** Shared save flow: skip when unchanged, refresh totals quietly after. */
async function commit(key, unchanged, request, message) {
  if (editing.value !== key || saving.value) return
  if (unchanged) { editing.value = null; return }
  saving.value = true
  try {
    await request()
    notify.success(message)
    editing.value = null
    await fetchReport({ quiet: true })
  } catch (e) {
    notify.error(e.response?.data?.message || 'Failed to save.')
  } finally {
    saving.value = false
  }
}

function savePremium(r) {
  const premium = Number(editValue.value || 0)
  return commit(`pr-${r.dependent_id}`, premium === Number(r.premium) && r.has_record,
    () => admin.saveDependentPremium({ dependent_id: r.dependent_id, year: year.value, premium }),
    'Premium saved.')
}

function savePayment(r, month) {
  const amount = Number(editValue.value || 0)
  return commit(`pm-${r.dependent_id}-${month}`, amount === Number(r.months[month]),
    () => admin.saveDependentPremiumPayment({ dependent_id: r.dependent_id, year: year.value, month, amount }),
    amount ? 'Deduction saved.' : 'Deduction cleared.')
}

async function postMonth() {
  const label = `${monthNames[postMonthValue.value - 1]} ${year.value}`
  if (!confirm(`Record ${label} deductions for every covered dependent with a remaining balance? Months already filled are left as they are.`)) return
  posting.value = true
  try {
    const { data: res } = await admin.postDependentPremiumMonth({ year: year.value, month: postMonthValue.value })
    notify.success(res.message || 'Deductions posted.')
    await fetchReport({ quiet: true })
  } catch (e) {
    notify.error(e.response?.data?.message || 'Failed to post deductions.')
  } finally {
    posting.value = false
  }
}

async function download(format) {
  exporting.value = format
  try {
    const response = await admin.downloadReportEquity(format, { year: year.value })
    const ext = format === 'excel' ? 'xlsx' : 'pdf'
    const url = URL.createObjectURL(new Blob([response.data]))
    const a = document.createElement('a')
    a.href = url
    a.download = `pmbf-year-end-report-${year.value}.${ext}`
    a.click()
    URL.revokeObjectURL(url)
  } catch {
    notify.error(`Failed to export ${format === 'excel' ? 'Excel' : 'PDF'}.`)
  } finally {
    exporting.value = null
  }
}

onMounted(fetchReport)
</script>

<style scoped>
.report-table { font-size: 0.8rem; }
.report-table th { white-space: nowrap; vertical-align: middle; font-size: 0.75rem; }
.report-table td { vertical-align: middle; }
.editable { cursor: pointer; min-width: 90px; }
.editable:hover { background: var(--bs-primary-bg-subtle, #e7f1ff); }
.month-cell { min-width: 78px; }
.cell-input { min-width: 80px; padding: 0.1rem 0.3rem; font-size: 0.8rem; }
tr.member-start td { border-top: 2px solid var(--bs-border-color-translucent, #adb5bd); }
</style>
