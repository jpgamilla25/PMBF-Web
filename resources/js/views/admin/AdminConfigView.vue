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

    <AppLoading :loading="loading" text="Loading configurations..." />

    <template v-if="!loading && Object.keys(filteredGroups).length">
      <div v-for="(configs, group) in filteredGroups" :key="group" class="card mb-4">
        <div class="card-header d-flex align-items-center">
          <i :class="groupIcon(group)" class="me-2 text-primary"></i>
          <h6 class="mb-0 fw-bold">{{ formatGroupName(group) }}</h6>
        </div>
        <div class="card-body">
          <div class="row">
            <div
              v-for="config in configs"
              :key="config.key"
              :class="config.type === 'boolean' ? 'col-md-6 col-lg-4' : 'col-md-6'"
              class="mb-3"
            >
              <!-- Boolean -->
              <div v-if="config.type === 'boolean'" class="form-check form-switch">
                <input :id="config.key" v-model="values[config.key]" class="form-check-input" type="checkbox" role="switch" :true-value="'1'" :false-value="'0'" />
                <label :for="config.key" class="form-check-label small">{{ config.description }}</label>
              </div>
              <!-- Select -->
              <div v-else-if="config.type === 'select'">
                <label :for="config.key" class="form-label fw-medium small mb-1">{{ config.description }}</label>
                <select :id="config.key" v-model="values[config.key]" class="form-select form-select-sm">
                  <option v-for="opt in config.options" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                </select>
              </div>
              <!-- Decimal -->
              <div v-else-if="config.type === 'decimal'">
                <label :for="config.key" class="form-label fw-medium small mb-1">{{ config.description }}</label>
                <div class="input-group input-group-sm">
                  <input :id="config.key" v-model="values[config.key]" type="number" step="0.01" min="0" class="form-control" @blur="formatDecimal(config.key)" />
                  <span v-if="config.suffix" class="input-group-text">{{ config.suffix }}</span>
                </div>
              </div>
              <!-- Number -->
              <div v-else-if="config.type === 'number'">
                <label :for="config.key" class="form-label fw-medium small mb-1">{{ config.description }}</label>
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
    <div v-if="hasChanges && !loading" class="position-fixed bottom-0 start-0 end-0 bg-white border-top p-3 shadow-lg" style="z-index: 100;">
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
import { ref, reactive, computed, onMounted, watch } from 'vue'
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
  sc_loan_rules: ['SC', 'all'],
  permanent_loan_rules: ['Permanent', 'all'],
  non_member_rules: ['Non-Member', 'all'],
  share_capital: ['Permanent', 'all'],

  // Only show the relevant interest rate keys per type (handled in filterConfigs)
  interest_rates: ['Permanent', 'SC', 'Non-Member', 'all'],

  // Common groups — always visible
  approval_workflow: ['Permanent', 'SC', 'Non-Member', 'all'],
  dependents_coverage: ['Permanent', 'SC', 'Non-Member', 'all'],
  notifications: ['Permanent', 'SC', 'Non-Member', 'all'],
  security: ['Permanent', 'SC', 'Non-Member', 'all'],
}

/**
 * Filter individual config keys within interest_rates group by type.
 */
const interestRateKeys = {
  Permanent: ['interest_rate_permanent'],
  SC: ['interest_rate_sc'],
  'Non-Member': ['interest_rate_non_member'],
  all: ['interest_rate_sc', 'interest_rate_permanent', 'interest_rate_non_member'],
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
      const allowedKeys = interestRateKeys[type] || interestRateKeys.all
      const filtered = configs.filter(c => allowedKeys.includes(c.key))
      if (filtered.length) result[group] = filtered
    } else {
      result[group] = configs
    }
  }

  return result
})

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
function formatDecimal(key) { const v = parseFloat(values[key]); if (!isNaN(v)) values[key] = v.toFixed(2) }
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
