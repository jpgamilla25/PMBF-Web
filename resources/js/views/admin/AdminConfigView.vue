<template>
  <AppLayout>
    <!-- ── Page header ─────────────────────────────────────────── -->
    <div class="d-flex align-items-start justify-content-between flex-wrap gap-3 mb-4">
      <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-gear me-2" aria-hidden="true"></i>Configuration</h4>
        <p class="text-muted small mb-0">
          Settings for <strong>{{ adminContext.label }}</strong>
          <span v-if="!loading"> &middot; {{ totalVisible }} setting{{ totalVisible === 1 ? '' : 's' }}</span>
        </p>
      </div>
      <div class="d-flex align-items-center gap-3">
        <div class="form-check form-switch mb-0">
          <input
            id="cfg-show-keys"
            v-model="showKeys"
            class="form-check-input"
            type="checkbox"
            role="switch"
          />
          <label for="cfg-show-keys" class="form-check-label small text-muted">Show config keys</label>
        </div>
        <router-link to="/admin/config-history" class="btn btn-outline-secondary btn-sm">
          <i class="bi bi-clock-history me-1" aria-hidden="true"></i>History
        </router-link>
      </div>
    </div>

    <AppLoading :loading="loading" text="Loading configurations..." />

    <div v-if="!loading" class="row g-4" :class="{ 'cfg-has-bar': hasChanges }">
      <!-- ── Section navigation ────────────────────────────────── -->
      <div class="col-lg-3">
        <nav class="cfg-nav" aria-label="Settings sections">
          <div class="input-group input-group-sm mb-3">
            <span class="input-group-text bg-body"><i class="bi bi-search" aria-hidden="true"></i></span>
            <input
              ref="searchInput"
              v-model="search"
              type="search"
              class="form-control"
              aria-label="Filter settings"
              placeholder="Filter settings..."
            />
            <button
              v-if="search"
              class="btn btn-outline-secondary"
              type="button"
              aria-label="Clear filter"
              @click="clearSearch"
            >
              <i class="bi bi-x-lg" aria-hidden="true"></i>
            </button>
            <span v-else class="input-group-text bg-body text-muted d-none d-lg-inline">
              <kbd class="cfg-kbd">/</kbd>
            </span>
          </div>
          <p v-if="search" class="small text-muted mb-2">
            {{ matchCount }} setting{{ matchCount === 1 ? '' : 's' }} matched
          </p>

          <!-- Clustered so related settings are found together rather than by
               scrolling one flat stack of nine equal-weight cards. -->
          <div v-for="cluster in visibleClusters" :key="cluster.name" class="cfg-cluster">
            <p class="cfg-cluster-label">{{ cluster.name }}</p>
            <ul class="cfg-nav-list">
              <li v-for="group in cluster.groups" :key="group">
                <button
                  type="button"
                  class="cfg-nav-link"
                  :class="{ active: activeGroup === group }"
                  :aria-current="activeGroup === group ? 'true' : undefined"
                  @click="goToGroup(group)"
                >
                  <i :class="groupIcon(group)" aria-hidden="true"></i>
                  <span class="flex-grow-1">{{ formatGroupName(group) }}</span>
                  <span
                    v-if="changedInGroup(group)"
                    class="cfg-dot"
                    :title="`${changedInGroup(group)} unsaved change(s)`"
                  >
                    {{ changedInGroup(group) }}
                  </span>
                  <span v-else class="cfg-count">{{ searchedGroups[group].length }}</span>
                </button>
              </li>
            </ul>
          </div>
        </nav>
      </div>

      <!-- ── Settings ──────────────────────────────────────────── -->
      <div class="col-lg-9">
        <div v-if="search && !matchCount" class="text-center text-muted py-5">
          <i class="bi bi-search fs-1 d-block mb-2 opacity-50" aria-hidden="true"></i>
          No setting matches "<strong>{{ search }}</strong>" for {{ adminContext.label }}.
          <div class="mt-2">
            <button class="btn btn-sm btn-outline-secondary" @click="clearSearch">Clear filter</button>
          </div>
        </div>

        <section
          v-for="group in orderedVisibleGroups"
          :id="`cfg-${group}`"
          :key="group"
          :data-group="group"
          class="cfg-section"
          :aria-labelledby="`cfg-${group}-heading`"
        >
          <div class="cfg-section-head">
            <i :class="groupIcon(group)" class="text-primary" aria-hidden="true"></i>
            <h5
              :id="`cfg-${group}-heading`"
              class="mb-0 fw-bold cfg-section-title"
              tabindex="-1"
            >{{ formatGroupName(group) }}</h5>

            <!-- Rate Period governs every rate in the section, so it sits with
                 the heading rather than as one field among the rates. With
                 several member types on screen each one gets its own, inside
                 its panel below. -->
            <div
              v-for="pc in headerPeriodConfigs(group)"
              :key="pc.key"
              class="ms-auto d-flex align-items-center gap-2"
            >
              <label :for="pc.key" class="small text-muted mb-0">Rate Period</label>
              <select
                :id="pc.key"
                v-model="values[pc.key]"
                class="form-select form-select-sm w-auto"
              >
                <option v-for="opt in pc.options" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
              </select>
            </div>
          </div>

          <p v-if="groupBlurbs[group]" class="cfg-section-blurb">{{ groupBlurbs[group] }}</p>

          <div class="cfg-section-body">
            <!-- Interest rates for several member types at once: grouped per
                 type, so four Rate Period selectors don't pile into one
                 heading and one type's fields don't interleave with another's
                 (their seeded sort_order values collide). -->
            <template v-if="scopePanels(group).length">
              <div v-for="panel in scopePanels(group)" :key="panel.key" class="cfg-scope">
                <div class="cfg-scope-head">
                  <h6 class="mb-0 fw-semibold">{{ panel.label }}</h6>
                  <div v-if="panel.period" class="ms-auto d-flex align-items-center gap-2">
                    <label :for="panel.period.key" class="small text-muted mb-0">Rate Period</label>
                    <select
                      :id="panel.period.key"
                      v-model="values[panel.period.key]"
                      class="form-select form-select-sm w-auto"
                    >
                      <option v-for="opt in panel.period.options" :key="opt.value" :value="opt.value">
                        {{ opt.label }}
                      </option>
                    </select>
                  </div>
                </div>
                <div class="cfg-grid">
                  <ConfigField
                    v-for="config in panel.fields"
                    :key="config.key"
                    v-model="values[config.key]"
                    :config="config"
                    :label-override="scopedLabel(config)"
                    :changed="isChanged(config.key)"
                    :show-key="showKeys"
                    @revert="revert(config.key)"
                    @blur="formatDecimal(config.key)"
                  />
                </div>
              </div>
            </template>

            <!-- Inputs first, in a two-up grid... -->
            <div v-else-if="inputFields(group).length" class="cfg-grid">
              <ConfigField
                v-for="config in inputFields(group)"
                :key="config.key"
                v-model="values[config.key]"
                :config="config"
                :label-override="configLabel(config)"
                :changed="isChanged(config.key)"
                :show-key="showKeys"
                @revert="revert(config.key)"
                @blur="formatDecimal(config.key)"
              />
            </div>

            <!-- ...then switches as a full-width list, where a one-line control
                 and its explanation read properly. -->
            <div v-if="switchFields(group).length" class="cfg-switch-list">
              <ConfigField
                v-for="config in switchFields(group)"
                :key="config.key"
                v-model="values[config.key]"
                :config="config"
                :changed="isChanged(config.key)"
                :show-key="showKeys"
                @revert="revert(config.key)"
              />
            </div>
          </div>
        </section>
      </div>
    </div>

    <!-- ── Unsaved-changes bar ──────────────────────────────────── -->
    <div v-if="hasChanges && !loading" ref="saveBar" class="cfg-save-bar">
      <div class="cfg-save-inner">
        <div v-if="reviewing" class="cfg-review">
          <p class="fw-semibold small mb-2">
            {{ changedKeys.length }} setting{{ changedKeys.length === 1 ? '' : 's' }} will change:
          </p>
          <ul class="cfg-review-list">
            <li v-for="key in changedKeys" :key="key">
              <span class="cfg-review-name" :title="nameFor(key)">{{ nameFor(key) }}</span>
              <span class="cfg-review-move">
                <span class="cfg-old">{{ displayValue(key, originalValues[key]) }}</span>
                <i class="bi bi-arrow-right mx-1" aria-hidden="true"></i>
                <span class="cfg-new">{{ displayValue(key, values[key]) }}</span>
              </span>
            </li>
          </ul>
        </div>

        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
          <span class="text-warning fw-medium small" role="status">
            <i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>
            {{ changedKeys.length }} unsaved change{{ changedKeys.length === 1 ? '' : 's' }}
          </span>
          <div class="d-flex gap-2">
            <AppButton variant="outline-secondary" size="sm" @click="reviewing = !reviewing">
              {{ reviewing ? 'Hide' : 'Review' }}
            </AppButton>
            <AppButton variant="outline-secondary" size="sm" @click="discardChanges">Discard</AppButton>
            <AppButton variant="primary" size="sm" :loading="saving" @click="handleSave">
              <i class="bi bi-check-lg me-1" aria-hidden="true"></i>Save changes
            </AppButton>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted, nextTick, watch } from 'vue'
import { useLoading } from '@/composables/useLoading'
import { useConfirm } from '@/composables/useConfirm'
import { useNotificationStore } from '@/stores/notification'
import { useAdminContextStore } from '@/stores/adminContext'
import admin from '@/services/admin'
import AppLayout from '@/components/layout/AppLayout.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppLoading from '@/components/ui/AppLoading.vue'
import ConfigField from '@/components/admin/ConfigField.vue'

const notify = useNotificationStore()
const adminContext = useAdminContextStore()
const { confirm } = useConfirm()
const { loading, withLoading } = useLoading()
const saving = ref(false)
const reviewing = ref(false)

const allGroupedConfigs = ref({})
const values = reactive({})
const originalValues = ref({})

// Keys are for support and the audit trail, not for everyday reading, so they
// are off by default — but the choice sticks for admins who want them.
const showKeys = ref(localStorage.getItem('pmbf_cfg_show_keys') === '1')
watch(showKeys, (on) => localStorage.setItem('pmbf_cfg_show_keys', on ? '1' : '0'))

/**
 * Map: which config groups are visible for which member type.
 */
const groupVisibility = {
  // Type-specific groups
  sc_loan_rules: ['Contract of Service', 'all'],
  permanent_loan_rules: ['Permanent', 'all'],
  non_member_rules: ['Non-Member', 'all'],
  pmbf_employee_rules: ['PMBF Employee', 'all'],
  share_capital: ['Permanent', 'all'],

  // Only show the relevant interest rate keys per type (handled in filterConfigs)
  interest_rates: ['Permanent', 'Contract of Service', 'Non-Member', 'PMBF Employee', 'all'],

  // Common groups — always visible
  approval_workflow: ['Permanent', 'Contract of Service', 'Non-Member', 'PMBF Employee', 'all'],
  dependents_coverage: ['Permanent', 'Contract of Service', 'Non-Member', 'PMBF Employee', 'all'],
  notifications: ['Permanent', 'Contract of Service', 'Non-Member', 'PMBF Employee', 'all'],
  security: ['Permanent', 'Contract of Service', 'Non-Member', 'PMBF Employee', 'all'],
}

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
  'PMBF Employee': ['interest_rate_pmbf_employee', 'interest_method_pmbf_employee', 'interest_period_pmbf_employee'],
  all: [
    'interest_rate_sc', 'interest_rate_permanent', 'interest_rate_non_member', 'interest_rate_pmbf_employee',
    'interest_method_sc', 'interest_method_permanent', 'interest_method_non_member', 'interest_method_pmbf_employee',
    'interest_period_sc', 'interest_period_permanent', 'interest_period_non_member', 'interest_period_pmbf_employee',
  ],
}

function isRateKeyVisible(key, type) {
  const scopes = interestRateScopes[type] || interestRateScopes.all

  // Exact base key, or a per-loan-type override beneath it. The scopes are
  // mutually exclusive prefixes, so no cross-scope leak is possible.
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

    if (group === 'interest_rates') {
      const filtered = configs.filter((c) => isRateKeyVisible(c.key, type))
      if (filtered.length) result[group] = filtered
    } else {
      result[group] = configs
    }
  }

  return result
})

// ── Search ─────────────────────────────────────────────────
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

const totalVisible = computed(() =>
  Object.values(filteredGroups.value).reduce((n, c) => n + c.length, 0)
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

// ── Grouping, ordering and labels ──────────────────────────
const groupIcons = {
  interest_rates: 'bi bi-percent',
  sc_loan_rules: 'bi bi-file-earmark-text',
  permanent_loan_rules: 'bi bi-building',
  non_member_rules: 'bi bi-person-x',
  pmbf_employee_rules: 'bi bi-person-badge',
  approval_workflow: 'bi bi-diagram-3',
  share_capital: 'bi bi-piggy-bank',
  dependents_coverage: 'bi bi-heart-pulse',
  notifications: 'bi bi-bell',
  security: 'bi bi-shield-lock',
}

const groupNames = {
  interest_rates: 'Interest Rates',
  sc_loan_rules: 'COS-Enrolled Loan Rules',
  permanent_loan_rules: 'Permanent Loan Rules',
  non_member_rules: 'COS-Non Enrolled Rules',
  pmbf_employee_rules: 'PMBF Employee Loan Rules',
  approval_workflow: 'Approval Workflow',
  share_capital: 'Share Capital Visibility',
  dependents_coverage: 'Dependents & Coverage',
  notifications: 'Notifications & Alerts',
  security: 'OTP & Security',
}

/** One line of orientation per section, so a heading is not the only context. */
const groupBlurbs = {
  interest_rates: 'What members are charged, and how the interest is computed.',
  permanent_loan_rules: 'Borrowing limits and terms for permanent employees.',
  sc_loan_rules: 'Borrowing limits and terms for enrolled Contract of Service employees.',
  non_member_rules: 'Borrowing limits and terms for non-enrolled Contract of Service employees.',
  pmbf_employee_rules: 'Borrowing limits and terms for staff employed by PMBF itself.',
  approval_workflow: 'Who signs off on an application, and in what order.',
  share_capital: 'What members can see and request about their share capital.',
  dependents_coverage: 'Who a member may enrol, and what those dependents are covered for.',
  notifications: 'When the system emails members and staff.',
  security: 'Sign-in codes, PIN rules and session limits.',
}

/**
 * Settings grouped by what an admin is trying to do, and ordered so the
 * money-affecting sections come first. A flat alphabetical stack made
 * "Notifications" look as consequential as "Interest Rates".
 */
const CLUSTERS = [
  {
    name: 'Lending',
    groups: [
      'interest_rates',
      'permanent_loan_rules',
      'sc_loan_rules',
      'non_member_rules',
      'pmbf_employee_rules',
      'approval_workflow',
    ],
  },
  { name: 'Membership', groups: ['share_capital', 'dependents_coverage'] },
  { name: 'System', groups: ['notifications', 'security'] },
]

const visibleClusters = computed(() => {
  const clustered = CLUSTERS
    .map((cluster) => ({
      name: cluster.name,
      groups: cluster.groups.filter((g) => searchedGroups.value[g]?.length),
    }))
    .filter((cluster) => cluster.groups.length)

  // A group added to groupVisibility but not to CLUSTERS would otherwise be
  // rendered nowhere — surface it rather than lose it silently.
  const placed = new Set(CLUSTERS.flatMap((c) => c.groups))
  const orphans = Object.keys(searchedGroups.value).filter(
    (g) => !placed.has(g) && searchedGroups.value[g]?.length
  )

  return orphans.length ? [...clustered, { name: 'Other', groups: orphans }] : clustered
})

const orderedVisibleGroups = computed(() => visibleClusters.value.flatMap((c) => c.groups))

function groupIcon(group) {
  return groupIcons[group] || 'bi bi-gear'
}

function formatGroupName(group) {
  return groupNames[group] || group.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
}

function isRateKey(key) {
  return String(key).startsWith('interest_rate')
}

/** Rate Period selectors, lifted out of the field grid into the section head. */
function periodConfigs(configs) {
  return (configs || []).filter((c) => String(c.key).startsWith('interest_period_'))
}

function fieldsFor(group) {
  // Period selectors live in the section head instead of the grid.
  return (searchedGroups.value[group] || []).filter(
    (c) => !(group === 'interest_rates' && String(c.key).startsWith('interest_period_'))
  )
}

function inputFields(group) {
  return fieldsFor(group).filter((c) => c.type !== 'boolean')
}

function switchFields(group) {
  return fieldsFor(group).filter((c) => c.type === 'boolean')
}

/**
 * The member types the interest_rates group covers, in the order they are
 * presented. Keys are the suffixes used by interest_rate_*, interest_method_*
 * and interest_period_*.
 */
const RATE_SCOPES = [
  { key: 'permanent', label: 'Permanent' },
  { key: 'sc', label: 'Contract of Service' },
  { key: 'non_member', label: 'Non-Members' },
  { key: 'pmbf_employee', label: 'PMBF Employees' },
]

/** Which member-type scope an interest_* key belongs to. */
function rateScopeOf(key) {
  const rest = String(key).replace(/^interest_(rate|method|period)_/, '')
  const hit = RATE_SCOPES.find((s) => rest === s.key || rest.startsWith(`${s.key}_`))

  return hit ? hit.key : null
}

/**
 * Interest settings split per member type.
 *
 * Returns [] unless more than one type is on screen — with a single type the
 * section heading already names it, so a panel repeating that name is noise
 * and the one Rate Period selector stays in the heading.
 */
function scopePanels(group) {
  if (group !== 'interest_rates') return []

  const configs = searchedGroups.value[group] || []
  const byScope = new Map()

  configs.forEach((config) => {
    const scope = rateScopeOf(config.key)
    if (!scope) return
    if (!byScope.has(scope)) byScope.set(scope, [])
    byScope.get(scope).push(config)
  })

  if (byScope.size < 2) return []

  return RATE_SCOPES.filter((s) => byScope.has(s.key)).map((s) => {
    const all = byScope.get(s.key)

    return {
      key: s.key,
      label: s.label,
      // The period selector sits in the panel header, not among the fields.
      period: all.find((c) => String(c.key).startsWith('interest_period_')) ?? null,
      fields: all.filter((c) => !String(c.key).startsWith('interest_period_')),
    }
  })
}

/** Period selectors shown beside the section heading (single-type view only). */
function headerPeriodConfigs(group) {
  return scopePanels(group).length ? [] : periodConfigs(searchedGroups.value[group])
}

/**
 * Inside a scope panel the heading already names the member type, so drop the
 * "Permanent — " prefix the seeded descriptions carry and leave just the field.
 */
function scopedLabel(config) {
  const label = configLabel(config)

  return label.includes('—') ? label.replace(/^[^—]*—\s*/, '') : label
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

// ── Change tracking ────────────────────────────────────────
const changedKeys = computed(() =>
  Object.keys(values).filter((key) => values[key] !== originalValues.value[key])
)

const hasChanges = computed(() => changedKeys.value.length > 0)

function isChanged(key) {
  return values[key] !== originalValues.value[key]
}

function changedInGroup(group) {
  return (searchedGroups.value[group] || []).filter((c) => isChanged(c.key)).length
}

function revert(key) {
  values[key] = originalValues.value[key]
}

/** The same short label the field itself shows, without its hint. */
function labelOf(config) {
  const raw = configLabel(config) || config.description || config.key
  const match = /^(.*?)\s*\(([^()]*)\)\s*$/.exec(raw)

  return match && match[1].trim().length > 3 ? match[1].trim() : raw
}

function nameFor(key) {
  for (const configs of Object.values(allGroupedConfigs.value)) {
    const hit = configs.find((c) => c.key === key)
    if (hit) return labelOf(hit)
  }
  return key
}

function displayValue(key, value) {
  if (value === '' || value === null || value === undefined) return 'not set'

  const config = Object.values(allGroupedConfigs.value)
    .flat()
    .find((c) => c.key === key)

  if (config?.type === 'boolean') return value === '1' ? 'On' : 'Off'
  if (config?.type === 'select') {
    return config.options?.find((o) => String(o.value) === String(value))?.label ?? value
  }
  return config?.suffix ? `${value} ${config.suffix}` : String(value)
}

async function discardChanges() {
  const ok = await confirm(
    `Discard ${changedKeys.value.length} unsaved change${changedKeys.value.length === 1 ? '' : 's'}?`,
    { title: 'Discard changes', confirmLabel: 'Discard', variant: 'danger' }
  )
  if (!ok) return

  Object.keys(originalValues.value).forEach((k) => {
    values[k] = originalValues.value[k]
  })
  reviewing.value = false
}

// ── Section nav ────────────────────────────────────────────
const activeGroup = ref('')
let observer = null
let scrollTick = 0

// ── Bottom bar height ──────────────────────────────────────
// Published as --pmbf-bottom-bar so the chat launcher and toasts lift above the
// bar rather than sitting on top of the Save button. It changes height when the
// review list expands and when the buttons wrap, so it is observed.
const saveBar = ref(null)
let barObserver = null

function publishBarHeight() {
  const height = saveBar.value ? Math.ceil(saveBar.value.getBoundingClientRect().height) : 0
  document.documentElement.style.setProperty('--pmbf-bottom-bar', `${height}px`)
}

watch(saveBar, (el) => {
  barObserver?.disconnect()
  barObserver = null

  if (!el) {
    publishBarHeight()
    return
  }

  barObserver = new ResizeObserver(publishBarHeight)
  barObserver.observe(el)
  publishBarHeight()
})

function goToGroup(group) {
  const el = document.getElementById(`cfg-${group}`)
  if (!el) return

  activeGroup.value = group
  el.scrollIntoView({ behavior: prefersReducedMotion() ? 'auto' : 'smooth', block: 'start' })
  // Move keyboard focus as well, so the nav is not mouse-only. The heading
  // carries tabindex="-1" purely to be a programmatic focus target.
  el.querySelector('.cfg-section-title')?.focus({ preventScroll: true })
}

function prefersReducedMotion() {
  return window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false
}

/**
 * How much fixed chrome sits above the content.
 *
 * The navbar is a known height, but the admin context bar below it is sticky,
 * only present for admins with a member-type context, and wraps on narrow
 * screens — so it is measured rather than assumed. Without this the settings
 * filter and every section heading scrolled to sat hidden behind it.
 */
function updateChromeOffset() {
  const bar = document.querySelector('.admin-context-bar')
  const barHeight = bar ? Math.round(bar.getBoundingClientRect().height) : 0

  document.documentElement.style.setProperty(
    '--cfg-chrome',
    `calc(var(--pmbf-navbar-height, 60px) + ${barHeight}px)`
  )
}

/**
 * Highlight whichever section the reader is actually looking at.
 *
 * The observer reports only what *changed*, so the set of on-screen sections is
 * tracked across callbacks rather than recomputed from each batch.
 */
const onScreen = new Set()

function updateActiveSection() {
  if (!onScreen.size) return

  const ordered = [...onScreen].sort(
    (a, b) => a.getBoundingClientRect().top - b.getBoundingClientRect().top
  )

  // At the end of the page the trailing sections can never reach the band near
  // the top, so the last one on screen is what is being read — without this,
  // clicking the final nav item highlighted the one above it.
  const atBottom =
    window.scrollY + window.innerHeight >= document.documentElement.scrollHeight - 4

  const target = atBottom ? ordered[ordered.length - 1] : ordered[0]
  activeGroup.value = target.dataset.group
}

function onScroll() {
  if (scrollTick) return
  scrollTick = requestAnimationFrame(() => {
    scrollTick = 0
    updateActiveSection()
  })
}

function observeSections() {
  observer?.disconnect()
  onScreen.clear()

  const sections = Array.from(document.querySelectorAll('.cfg-section'))
  if (!sections.length) return

  observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((e) => (e.isIntersecting ? onScreen.add(e.target) : onScreen.delete(e.target)))
      updateActiveSection()
    },
    // Bias the band towards the top of the viewport, under the fixed chrome.
    { rootMargin: '-120px 0px -65% 0px', threshold: 0 }
  )

  sections.forEach((el) => observer.observe(el))

  if (!activeGroup.value || !orderedVisibleGroups.value.includes(activeGroup.value)) {
    activeGroup.value = orderedVisibleGroups.value[0] ?? ''
  }
}

watch(orderedVisibleGroups, () => nextTick(observeSections))

// ── Data ───────────────────────────────────────────────────
async function fetchConfigs() {
  await withLoading(async () => {
    const { data } = await admin.getConfigurations()
    const configs = data.data ?? data
    if (!Array.isArray(configs)) return

    const grouped = {}
    configs.forEach((config) => {
      const group = config.group ?? 'general'
      if (!grouped[group]) grouped[group] = []
      grouped[group].push(config)
      values[config.key] = config.value ?? ''
      originalValues.value[config.key] = config.value ?? ''
    })

    Object.values(grouped).forEach((g) => g.sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0)))
    allGroupedConfigs.value = grouped
  })

  await nextTick()
  observeSections()
}

async function handleSave() {
  // Only what changed — a full dump would also rewrite values another admin
  // may have edited since this page loaded.
  const payload = {}
  changedKeys.value.forEach((key) => {
    payload[key] = values[key]
  })

  if (!Object.keys(payload).length) return

  saving.value = true
  try {
    await admin.updateConfigurations({
      configurations: payload,
      employment_type: adminContext.memberType || null,
    })
    Object.keys(payload).forEach((k) => {
      originalValues.value[k] = values[k]
    })
    reviewing.value = false
    notify.success(
      `${Object.keys(payload).length} setting${Object.keys(payload).length === 1 ? '' : 's'} saved.`
    )
  } catch (e) {
    notify.error(e.response?.data?.message || 'Failed to save configurations.')
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  window.addEventListener('keydown', onKeydown)
  window.addEventListener('resize', updateChromeOffset)
  window.addEventListener('scroll', onScroll, { passive: true })
  updateChromeOffset()
  fetchConfigs()
})

// The context bar appears, disappears and changes height with the context.
watch(() => adminContext.memberType, () => nextTick(updateChromeOffset))

onUnmounted(() => {
  window.removeEventListener('keydown', onKeydown)
  window.removeEventListener('resize', updateChromeOffset)
  window.removeEventListener('scroll', onScroll)
  if (scrollTick) cancelAnimationFrame(scrollTick)
  observer?.disconnect()
  barObserver?.disconnect()
  // Leaving the page must not leave the chat button floating in mid-air.
  document.documentElement.style.removeProperty('--pmbf-bottom-bar')
})
</script>

<style scoped>
/* ── Section navigation ─────────────────────────────────────── */
.cfg-nav {
  position: sticky;
  /* --cfg-chrome is measured in updateChromeOffset(); the fallback covers the
     first paint before it is set. */
  top: calc(var(--cfg-chrome, 113px) + 12px);
}

.cfg-grid {
  display: grid;
  /* auto-fit rather than col-md-6: the fields reflow by available width, and
     there are no negative row margins to overflow the section. */
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: 1rem 1.5rem;
}

.cfg-cluster + .cfg-cluster {
  margin-top: 1.1rem;
}

.cfg-cluster-label {
  font-size: 0.68rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--bs-secondary-color);
  margin: 0 0 0.35rem 0.6rem;
}

.cfg-nav-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.cfg-nav-link {
  display: flex;
  align-items: center;
  gap: 0.55rem;
  width: 100%;
  padding: 0.4rem 0.6rem;
  border: 0;
  border-radius: 6px;
  background: none;
  color: var(--bs-body-color);
  font-size: 0.84rem;
  text-align: left;
  cursor: pointer;
}

.cfg-nav-link:hover {
  background: var(--bs-tertiary-bg);
}

.cfg-nav-link.active {
  background: var(--bs-primary-bg-subtle);
  color: var(--bs-primary-text-emphasis);
  font-weight: 600;
}

.cfg-nav-link:focus-visible {
  outline: 2px solid var(--bs-primary);
  outline-offset: 1px;
}

.cfg-count,
.cfg-dot {
  flex: none;
  min-width: 1.35rem;
  text-align: center;
  font-size: 0.7rem;
  border-radius: 10px;
  padding: 0.05rem 0.35rem;
}

.cfg-count {
  color: var(--bs-secondary-color);
}

.cfg-dot {
  background: var(--bs-warning);
  color: #000;
  font-weight: 700;
}

.cfg-kbd {
  background: var(--bs-tertiary-bg);
  border: 1px solid var(--bs-border-color);
  border-radius: 4px;
  padding: 0 5px;
  font-size: 0.7rem;
  color: inherit;
}

/* ── Sections ───────────────────────────────────────────────── */
.cfg-section {
  /* Clears the navbar and the admin context bar when scrolled to from the nav. */
  scroll-margin-top: calc(var(--cfg-chrome, 113px) + 20px);
  padding-bottom: 1.75rem;
  margin-bottom: 1.75rem;
  border-bottom: 1px solid var(--bs-border-color);
}

.cfg-section:last-child {
  border-bottom: 0;
  margin-bottom: 0;
}

.cfg-section-title:focus {
  outline: none;
}

.cfg-section-title:focus-visible {
  outline: 2px solid var(--bs-primary);
  outline-offset: 3px;
  border-radius: 3px;
}

.cfg-section-head {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  flex-wrap: wrap;
  margin-bottom: 0.25rem;
}

.cfg-section-blurb {
  color: var(--bs-secondary-color);
  font-size: 0.85rem;
  margin: 0 0 1.1rem;
}

.cfg-section-body {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.cfg-scope {
  border: 1px solid var(--bs-border-color);
  border-radius: 8px;
  padding: 0.9rem 1rem 1rem;
}

.cfg-scope-head {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex-wrap: wrap;
  margin-bottom: 0.9rem;
  padding-bottom: 0.6rem;
  border-bottom: 1px solid var(--bs-border-color-translucent);
}

.cfg-scope-head h6 {
  font-size: 0.95rem;
}

.cfg-switch-list {
  display: flex;
  flex-direction: column;
  border: 1px solid var(--bs-border-color);
  border-radius: 8px;
  padding: 0.25rem 0.85rem;
}

.cfg-switch-list > * + * {
  border-top: 1px solid var(--bs-border-color-translucent);
}

/* ── Unsaved-changes bar ────────────────────────────────────── */
.cfg-save-bar {
  position: fixed;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 100;
  background: var(--bs-body-bg);
  border-top: 1px solid var(--bs-border-color);
  box-shadow: 0 -4px 16px rgba(0, 0, 0, 0.12);
}

.cfg-save-inner {
  max-width: 1320px;
  margin: 0 auto;
  padding: 0.75rem 1.5rem;
}

/* Room to scroll past the bar instead of it covering the last section. */
.cfg-has-bar {
  padding-bottom: calc(var(--pmbf-bottom-bar, 0px) + 2rem);
}

.cfg-review {
  max-height: 34vh;
  overflow-y: auto;
  border-bottom: 1px solid var(--bs-border-color);
  margin-bottom: 0.65rem;
  padding-bottom: 0.5rem;
}

.cfg-review-list {
  list-style: none;
  margin: 0;
  padding: 0;
  font-size: 0.82rem;
}

.cfg-review-list li {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  align-items: baseline;
  gap: 1rem;
  padding: 0.2rem 0;
}

/* One row per change: a long setting name truncates rather than wrapping and
   shunting its values onto another line. */
.cfg-review-name {
  color: var(--bs-secondary-color);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.cfg-review-move {
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

.cfg-old {
  color: var(--bs-secondary-color);
  text-decoration: line-through;
}

.cfg-new {
  font-weight: 600;
}

@media (max-width: 991.98px) {
  .cfg-nav {
    position: static;
  }

  /* Sections become a horizontally scrollable strip rather than a tall list
     pushing the settings themselves off the first screen. */
  .cfg-cluster {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    min-width: 0;
  }

  .cfg-cluster + .cfg-cluster {
    margin-top: 0.4rem;
  }

  .cfg-cluster-label {
    margin: 0;
    flex: none;
    width: 5.5rem;
  }

  .cfg-nav-list {
    flex-direction: row;
    overflow-x: auto;
    padding-bottom: 0.25rem;
    /* Without this the list is a flex item at min-width:auto, so it refuses to
       shrink below its content, overflow-x never engages, and the whole admin
       column is stretched past the viewport. */
    min-width: 0;
  }

  .cfg-nav-link {
    white-space: nowrap;
  }
}
</style>
