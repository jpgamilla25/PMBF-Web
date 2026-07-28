<template>
  <AppLayout>
    <div class="d-flex align-items-center justify-content-between mb-4">
      <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-gear me-2"></i>Configuration</h4>
        <p class="text-muted small mb-0">Settings for <strong>{{ adminContext.label }}</strong></p>
      </div>
      <div class="d-flex gap-2">
        <router-link to="/admin/config-history" class="btn btn-outline-secondary btn-sm">
          <i class="bi bi-clock-history me-1"></i>History
        </router-link>
        <AppButton variant="primary" :loading="saving" @click="handleSave">
          <i class="bi bi-check-lg me-1"></i>Save All Changes
        </AppButton>
      </div>
    </div>

    <!-- Settings filter -->
    <div v-if="!loading" class="cfg-search-wrap mb-4">
      <div class="input-group">
        <span class="input-group-text bg-body"><i class="bi bi-search"></i></span>
        <input
          ref="searchInput"
          v-model="search"
          type="text"
          class="form-control"
          placeholder="Filter settings — name, key, or value (e.g. terms, sc_available_terms, interest)"
        />
        <button v-if="search" class="btn btn-outline-secondary" type="button" @click="clearSearch">
          <i class="bi bi-x-lg"></i>
        </button>
        <span class="input-group-text bg-body text-muted small d-none d-md-inline">
          <kbd class="cfg-kbd">/</kbd>
        </span>
      </div>
      <div v-if="search" class="small text-muted mt-1">
        {{ matchCount }} setting{{ matchCount === 1 ? '' : 's' }} matched
      </div>
    </div>

    <AppLoading :loading="loading" text="Loading configurations..." />

    <div v-if="!loading && search && !matchCount" class="text-center text-muted py-5">
      <i class="bi bi-search fs-1 d-block mb-2 opacity-50"></i>
      No setting matches "<strong>{{ search }}</strong>" for {{ adminContext.label }}.
      <div class="mt-2">
        <button class="btn btn-sm btn-outline-secondary" @click="clearSearch">Clear filter</button>
      </div>
    </div>

    <template v-if="!loading && Object.keys(searchedGroups).length">
      <div v-for="(configs, group) in searchedGroups" :key="group" class="card mb-4">
        <div class="card-header d-flex align-items-center flex-wrap gap-2">
          <i :class="groupIcon(group)" class="me-2 text-primary"></i>
          <h6 class="mb-0 fw-bold">{{ formatGroupName(group) }}</h6>
          <!-- Rate Period governs every rate below, so it sits in the header. -->
          <div
            v-for="pc in periodConfigs(configs)"
            :key="pc.key"
            class="ms-auto d-flex align-items-center gap-2"
          >
            <label :for="pc.key" class="small text-muted mb-0">{{ periodLabel(pc) }}</label>
            <select :id="pc.key" v-model="values[pc.key]" class="form-select form-select-sm w-auto">
              <option v-for="opt in pc.options" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
          </div>
        </div>
        <div class="card-body">
          <div class="row">
            <div
              v-for="config in visibleFields(configs, group)"
              :key="config.key"
              :class="config.type === 'boolean' ? 'col-md-6 col-lg-4' : 'col-md-6'"
              class="mb-3"
            >
              <!-- Boolean -->
              <div v-if="config.type === 'boolean'" class="form-check form-switch">
                <input :id="config.key" v-model="values[config.key]" class="form-check-input" type="checkbox" role="switch" :true-value="'1'" :false-value="'0'" />
                <label :for="config.key" class="form-check-label small">{{ configLabel(config) }}</label>
              </div>
              <!-- Select -->
              <div v-else-if="config.type === 'select'">
                <label :for="config.key" class="form-label fw-medium small mb-1">{{ configLabel(config) }}</label>
                <select :id="config.key" v-model="values[config.key]" class="form-select form-select-sm">
                  <option v-for="opt in config.options" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                </select>
              </div>
              <!-- Decimal -->
              <div v-else-if="config.type === 'decimal'">
                <label :for="config.key" class="form-label fw-medium small mb-1">{{ configLabel(config) }}</label>
                <div class="input-group input-group-sm">
                  <input :id="config.key" v-model="values[config.key]" type="number" :step="isRateKey(config.key) ? '0.0001' : '0.01'" min="0" class="form-control" @blur="formatDecimal(config.key)" />
                  <span v-if="config.suffix" class="input-group-text">{{ config.suffix }}</span>
                </div>
              </div>
              <!-- Number -->
              <div v-else-if="config.type === 'number'">
                <label :for="config.key" class="form-label fw-medium small mb-1">{{ configLabel(config) }}</label>
                <div class="input-group input-group-sm">
                  <input :id="config.key" v-model="values[config.key]" type="number" min="0" step="1" class="form-control" />
                  <span v-if="config.suffix" class="input-group-text">{{ config.suffix }}</span>
                </div>
              </div>
              <!-- Text with comma values → Tag Input -->
              <div v-else>
                <AppTagInput
                  v-model="values[config.key]"
                  :label="config.description"
                  :suffix="config.suffix"
                  :item-label="getItemLabel(config.key)"
                  :type="isNumericTags(config.key) ? 'number' : 'text'"
                  :placeholder="isNumericTags(config.key) ? 'Type a number and press Enter...' : 'Type and press Enter...'"
                />
              </div>
              <small v-if="config.type !== 'boolean'" class="text-muted" style="font-size: 0.7rem;">{{ config.key }}</small>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- Sticky save bar -->
    <div v-if="hasChanges && !loading" class="position-fixed bottom-0 start-0 end-0 bg-body border-top p-3 shadow-lg" style="z-index: 100;">
      <div class="container-fluid">
        <div class="d-flex align-items-center justify-content-between">
          <span class="text-warning fw-medium"><i class="bi bi-exclamation-circle me-1"></i>You have unsaved changes</span>
          <div>
            <button class="btn btn-outline-secondary btn-sm me-2" @click="resetChanges">Discard</button>
            <AppButton variant="primary" size="sm" :loading="saving" @click="handleSave">
              <i class="bi bi-check-lg me-1"></i>Save All
            </AppButton>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted, watch } from 'vue'
import { useLoading } from '@/composables/useLoading'
import { useNotificationStore } from '@/stores/notification'
import { useAdminContextStore } from '@/stores/adminContext'
import admin from '@/services/admin'
import AppLayout from '@/components/layout/AppLayout.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppLoading from '@/components/ui/AppLoading.vue'
import AppTagInput from '@/components/ui/AppTagInput.vue'

const notify = useNotificationStore()
const adminContext = useAdminContextStore()
const { loading, withLoading } = useLoading()
const saving = ref(false)

const allGroupedConfigs = ref({})
const values = reactive({})
const originalValues = ref({})

/**
 * Map: which config groups are visible for which member type.
 * 'common' groups show for all types.
 */
const groupVisibility = {
  // Type-specific groups
  sc_loan_rules: ['Contract of Service', 'all'],
  permanent_loan_rules: ['Permanent', 'all'],
  non_member_rules: ['Non-Member', 'all'],
  share_capital: ['Permanent', 'all'],

  // Only show the relevant interest rate keys per type (handled in filterConfigs)
  interest_rates: ['Permanent', 'Contract of Service', 'Non-Member', 'all'],

  // Common groups — always visible
  approval_workflow: ['Permanent', 'Contract of Service', 'Non-Member', 'all'],
  dependents_coverage: ['Permanent', 'Contract of Service', 'Non-Member', 'all'],
  notifications: ['Permanent', 'Contract of Service', 'Non-Member', 'all'],
  security: ['Permanent', 'Contract of Service', 'Non-Member', 'all'],
}

/**
 * Filter individual config keys within interest_rates group by type.
 */
/**
 * Which interest-rate scope each member-type context may edit.
 *
 * Matched by prefix so the per-loan-type override keys
 * (interest_rate_permanent_emergency, ...) show up alongside the base rate
 * without having to be listed individually here.
 *
 * Note the context value is 'Contract of Service', not 'SC' — keying this on
 * 'SC' previously meant the SC context fell through to showing every rate.
 */
const interestRateScopes = {
  Permanent: ['interest_rate_permanent', 'interest_method_permanent', 'interest_period_permanent'],
  'Contract of Service': ['interest_rate_sc', 'interest_method_sc', 'interest_period_sc'],
  'Non-Member': ['interest_rate_non_member', 'interest_method_non_member', 'interest_period_non_member'],
  all: [
    'interest_rate_sc', 'interest_rate_permanent', 'interest_rate_non_member',
    'interest_method_sc', 'interest_method_permanent', 'interest_method_non_member',
    'interest_period_sc', 'interest_period_permanent', 'interest_period_non_member',
  ],
}

function isRateKeyVisible(key, type) {
  const scopes = interestRateScopes[type] || interestRateScopes.all

  // Exact base key, or a per-loan-type override beneath it. The three scopes
  // are mutually exclusive prefixes, so no cross-scope leak is possible.
  return scopes.some((scope) => key === scope || key.startsWith(`${scope}_`))
}

const filteredGroups = computed(() => {
  const type = adminContext.memberType || 'all'
  const result = {}

  const mobileGroups = ['mobile_app_general', 'mobile_app_features', 'mobile_app_security']

  for (const [group, configs] of Object.entries(allGroupedConfigs.value)) {
    // Skip mobile groups — they have their own page
    if (mobileGroups.includes(group)) continue

    const allowed = groupVisibility[group]
    if (!allowed || !allowed.includes(type)) continue

    // For interest_rates, filter individual keys
    if (group === 'interest_rates') {
      const filtered = configs.filter(c => isRateKeyVisible(c.key, type))
      if (filtered.length) result[group] = filtered
    } else {
      result[group] = configs
    }
  }

  return result
})

// ── Search ─────────────────────────────────────────────────
// Narrows the visible settings by key, description, group name or current
// value, so an admin can jump to one setting without scrolling every card.

const search = ref('')
const searchInput = ref(null)

/** Subsequence match, so "sctrm" finds "sc_available_terms". */
function fuzzyMatch(text, q) {
  const haystack = (text ?? '').toLowerCase()
  if (haystack.includes(q)) return true

  let i = 0
  for (const ch of haystack) {
    if (ch === q[i]) i++
    if (i === q.length) return true
  }
  return false
}

const searchedGroups = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return filteredGroups.value

  const result = {}
  for (const [group, configs] of Object.entries(filteredGroups.value)) {
    // A group-name hit keeps the whole group, so "security" shows its section.
    const groupHit = fuzzyMatch(formatGroupName(group), q) || fuzzyMatch(group, q)

    const hits = groupHit
      ? configs
      : configs.filter((c) =>
        fuzzyMatch(c.key, q) ||
        fuzzyMatch(c.description, q) ||
        fuzzyMatch(String(values[c.key] ?? ''), q)
      )

    if (hits.length) result[group] = hits
  }
  return result
})

const matchCount = computed(() =>
  Object.values(searchedGroups.value).reduce((n, c) => n + c.length, 0)
)

function clearSearch() {
  search.value = ''
  searchInput.value?.focus()
}

/** Ctrl+F / "/" focuses the settings filter while on this page. */
function onKeydown(e) {
  const typingElsewhere = ['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName)

  if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'f') {
    e.preventDefault()
    searchInput.value?.focus()
    return
  }

  if (e.key === '/' && !typingElsewhere) {
    e.preventDefault()
    searchInput.value?.focus()
    return
  }

  if (e.key === 'Escape' && e.target === searchInput.value && search.value) {
    clearSearch()
  }
}

onMounted(() => window.addEventListener('keydown', onKeydown))
onUnmounted(() => window.removeEventListener('keydown', onKeydown))

const groupIcons = {
  interest_rates: 'bi bi-percent',
  sc_loan_rules: 'bi bi-file-earmark-text',
  permanent_loan_rules: 'bi bi-building',
  non_member_rules: 'bi bi-person-x',
  approval_workflow: 'bi bi-diagram-3',
  share_capital: 'bi bi-piggy-bank',
  dependents_coverage: 'bi bi-heart-pulse',
  notifications: 'bi bi-bell',
  security: 'bi bi-shield-lock',
}

const groupNames = {
  interest_rates: 'Interest Rates',
  sc_loan_rules: 'SC (Service Contract) Loan Rules',
  permanent_loan_rules: 'Permanent Employee Loan Rules',
  non_member_rules: 'Non-Member Rules',
  approval_workflow: 'Approval Workflow',
  share_capital: 'Share Capital Visibility',
  dependents_coverage: 'Dependents & Coverage',
  notifications: 'Notifications & Alerts',
  security: 'OTP & Security',
}

const hasChanges = computed(() => {
  return Object.keys(values).some(key => values[key] !== originalValues.value[key])
})

function groupIcon(group) { return groupIcons[group] || 'bi bi-gear' }
function formatGroupName(group) { return groupNames[group] || group.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) }
// Interest rates may need finer precision (e.g. 8% p.a. → 0.6667%/month), so
// they keep up to 4 decimals; other decimals (amounts) stay at 2.
function isRateKey(key) { return String(key).startsWith('interest_rate') }

/** Rate Period selectors, lifted out of the field grid into the card header. */
function periodConfigs(configs) {
  return (configs || []).filter(c => String(c.key).startsWith('interest_period_'))
}

/** Fields shown in the grid — period selectors live in the header instead. */
function visibleFields(configs, group) {
  if (group !== 'interest_rates') return configs
  return (configs || []).filter(c => !String(c.key).startsWith('interest_period_'))
}

/** Header label for a period selector: just "Rate Period", or scope-prefixed
 *  when several show at once (the "all members" view). */
function periodLabel(config) {
  return periodConfigs(searchedGroups.value?.interest_rates || []).length > 1
    ? config.description
    : 'Rate Period'
}

/** Which member-type scope an interest_rate_* key belongs to. */
function rateScopeOf(key) {
  if (String(key).startsWith('interest_rate_sc')) return 'sc'
  if (String(key).startsWith('interest_rate_non_member')) return 'non_member'
  if (String(key).startsWith('interest_rate_permanent')) return 'permanent'
  return null
}

// The rate labels were seeded as "… Monthly Interest Rate", but the period is
// now a per-scope setting, so drop the baked-in "Monthly" and show the real
// unit instead of implying the rate is always monthly.
function configLabel(config) {
  let label = config.description || ''
  const scope = rateScopeOf(config.key)
  if (!scope) return label

  label = label.replace(/\bMonthly\s+/i, '')
  if (/Interest Rate$/i.test(label)) {
    label += values[`interest_period_${scope}`] === 'per_annum' ? ' (per year)' : ' (per month)'
  }
  return label
}
function formatDecimal(key) {
  const v = parseFloat(values[key])
  if (isNaN(v)) return
  values[key] = isRateKey(key) ? String(parseFloat(v.toFixed(4))) : v.toFixed(2)
}
function resetChanges() { Object.keys(originalValues.value).forEach(k => { values[k] = originalValues.value[k] }) }

/** Determine the label for tag items based on config key */
function getItemLabel(key) {
  if (key.includes('term')) return 'Term (months)'
  if (key.includes('coverage')) return 'Coverage Option'
  return 'Value'
}

/** Check if this text config expects numeric tags */
function isNumericTags(key) {
  return key.includes('term')
}

async function fetchConfigs() {
  await withLoading(async () => {
    const { data } = await admin.getConfigurations()
    const configs = data.data ?? data
    if (!Array.isArray(configs)) return

    const grouped = {}
    configs.forEach(config => {
      const group = config.group ?? 'general'
      if (!grouped[group]) grouped[group] = []
      grouped[group].push(config)
      values[config.key] = config.value ?? ''
      originalValues.value[config.key] = config.value ?? ''
    })

    Object.values(grouped).forEach(g => g.sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0)))
    allGroupedConfigs.value = grouped
  })
}

async function handleSave() {
  saving.value = true
  try {
    await admin.updateConfigurations({
      configurations: { ...values },
      employment_type: adminContext.memberType || null,
    })
    Object.keys(values).forEach(k => { originalValues.value[k] = values[k] })
    notify.success('Configurations saved successfully.')
  } catch {
    notify.error('Failed to save configurations.')
  } finally {
    saving.value = false
  }
}

onMounted(fetchConfigs)
</script>

<style scoped>
/* Keeps the filter reachable while scrolling a long settings page. */
.cfg-search-wrap {
  position: sticky;
  top: calc(var(--pmbf-navbar-height, 60px) + 8px);
  z-index: 20;
  background: var(--bs-body-bg);
  padding: 8px 0;
}

.cfg-kbd {
  background: var(--bs-tertiary-bg);
  border: 1px solid var(--bs-border-color);
  border-radius: 4px;
  padding: 0 5px;
  font-size: .7rem;
  color: inherit;
}
</style>
