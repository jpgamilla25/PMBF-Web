<template>
  <AppLayout>
    <div class="d-flex align-items-center justify-content-between mb-1">
      <h4 class="fw-bold mb-0">User Type Management</h4>
      <span class="badge bg-primary-subtle text-primary border border-primary-subtle">Admin Only</span>
    </div>
    <p class="text-muted small mb-4">Search HRIS employees and assign system roles.</p>

    <!-- Search Bar -->
    <AppCard class="mb-4">
      <div class="row g-3 align-items-end">
        <div class="col-md-6">
          <label class="form-label fw-semibold small">Search by ID or Name</label>
          <div class="input-group">
            <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
            <input
              v-model="search"
              type="text"
              class="form-control"
              placeholder="e.g. 15-0312 or Gamilla..."
              @keyup.enter="fetchUsers"
            />
            <AppButton variant="primary" :loading="loading" @click="fetchUsers">Search</AppButton>
            <AppButton v-if="search" variant="outline-secondary" @click="clearSearch">
              <i class="bi bi-x"></i>
            </AppButton>
          </div>
        </div>
        <div class="col-md-3">
          <label class="form-label fw-semibold small">Filter by Role</label>
          <select v-model="filterRole" class="form-select">
            <option value="">All Roles</option>
            <option v-for="r in roles" :key="r.value" :value="r.value">{{ r.label }}</option>
          </select>
        </div>
        <div class="col-md-3 d-flex align-items-end gap-2">
          <div class="small text-muted">
            <span v-if="!loading && employees.length">
              <strong>{{ filteredEmployees.length }}</strong> result(s)
            </span>
          </div>
        </div>
      </div>

      <!-- Role Summary Chips -->
      <div v-if="employees.length" class="d-flex flex-wrap gap-2 mt-3 pt-3 border-top">
        <span
          v-for="r in roleSummary"
          :key="r.role"
          class="badge rounded-pill px-3 py-2"
          :style="{ background: roleColor(r.role) + '20', color: roleColor(r.role), border: '1px solid ' + roleColor(r.role) + '40', cursor: 'pointer' }"
          @click="filterRole = filterRole === r.role ? '' : r.role"
        >
          <i class="bi bi-circle-fill me-1" style="font-size:0.5rem;vertical-align:middle;"></i>
          {{ roleLabel(r.role) }}: {{ r.count }}
        </span>
        <span
          class="badge rounded-pill px-3 py-2"
          style="background:#f1f5f9;color:#64748b;border:1px solid #e2e8f0;cursor:pointer;"
          @click="filterRole = filterRole === '__unregistered' ? '' : '__unregistered'"
        >
          <i class="bi bi-circle me-1" style="font-size:0.5rem;vertical-align:middle;"></i>
          Not Registered: {{ unregisteredCount }}
        </span>
      </div>
    </AppCard>

    <!-- Results -->
    <AppLoading :loading="loading" text="Searching HRIS..." />

    <template v-if="!loading">
      <!-- Empty state -->
      <div v-if="employees.length === 0 && hasSearched" class="text-center py-5 text-muted">
        <i class="bi bi-people fs-1 d-block mb-2 opacity-25"></i>
        No employees found matching "{{ search }}".
      </div>
      <div v-else-if="!hasSearched" class="text-center py-5 text-muted">
        <i class="bi bi-search fs-1 d-block mb-2 opacity-25"></i>
        Enter an Employee ID or name to search.
      </div>

      <!-- Table -->
      <AppCard v-if="filteredEmployees.length" :padding="false">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Employee</th>
                <th>Position / Department</th>
                <th>Type</th>
                <th>Status</th>
                <th>Current Role</th>
                <th class="text-end">Action</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="emp in filteredEmployees" :key="emp.employee_id">
                <td>
                  <div class="fw-semibold">{{ emp.last_name }}, {{ emp.first_name }}</div>
                  <small class="text-muted">{{ emp.employee_id }}</small>
                </td>
                <td>
                  <div class="small">{{ emp.position ?? '-' }}</div>
                  <small class="text-muted">{{ emp.department ?? '-' }}</small>
                </td>
                <td>
                  <AppStatusBadge :status="emp.employment_type ?? '-'" />
                </td>
                <td>
                  <span v-if="emp.is_registered" class="badge bg-success-subtle text-success border border-success-subtle">
                    <i class="bi bi-check-circle me-1"></i>Registered
                  </span>
                  <span v-else class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">
                    <i class="bi bi-circle me-1"></i>Not Registered
                  </span>
                </td>
                <td>
                  <span v-if="emp.role" class="badge rounded-pill px-3 py-1 fw-semibold" :style="{ background: roleColor(emp.role) + '20', color: roleColor(emp.role), border: '1px solid ' + roleColor(emp.role) + '40' }">
                    {{ roleLabel(emp.role) }}
                  </span>
                  <span v-else class="text-muted small">—</span>
                </td>
                <td class="text-end">
                  <button class="btn btn-sm btn-outline-primary" @click="openAssign(emp)">
                    <i class="bi bi-person-gear me-1"></i>Assign Role
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </AppCard>
    </template>

    <!-- Assign Role Modal -->
    <AppModal :show="!!selected" title="Assign Role" @close="selected = null">
      <div v-if="selected">
        <!-- Employee Info -->
        <div class="d-flex align-items-center gap-3 mb-4 p-3 bg-light rounded-3">
          <div class="avatar-circle">
            {{ selected.first_name?.[0] }}{{ selected.last_name?.[0] }}
          </div>
          <div>
            <div class="fw-bold">{{ selected.last_name }}, {{ selected.first_name }}</div>
            <div class="text-muted small">{{ selected.employee_id }} &nbsp;·&nbsp; {{ selected.position }}</div>
            <div class="small text-muted">{{ selected.department }} &nbsp;·&nbsp; {{ selected.employment_type }}</div>
          </div>
        </div>

        <div v-if="!selected.is_registered" class="alert alert-warning small mb-3">
          <i class="bi bi-info-circle me-1"></i>
          This employee is <strong>not yet registered</strong>. Assigning a role will create a system account. They can complete their profile by registering normally.
        </div>

        <label class="form-label fw-semibold">Select Role</label>
        <div class="d-flex flex-column gap-2">
          <label
            v-for="r in roles"
            :key="r.value"
            class="role-option"
            :class="{ selected: assignForm.role === r.value }"
            :style="assignForm.role === r.value ? { borderColor: r.color, background: r.color + '10' } : {}"
          >
            <input v-model="assignForm.role" type="radio" :value="r.value" class="d-none" />
            <div class="role-dot" :style="{ background: r.color }"></div>
            <div class="flex-grow-1">
              <div class="fw-semibold small">{{ r.label }}</div>
              <div class="text-muted" style="font-size:0.75rem;">{{ r.desc }}</div>
            </div>
            <i v-if="assignForm.role === r.value" class="bi bi-check-circle-fill" :style="{ color: r.color }"></i>
          </label>
        </div>
      </div>

      <template #footer>
        <AppButton variant="secondary" @click="selected = null">Cancel</AppButton>
        <AppButton variant="primary" :loading="assigning" :disabled="!assignForm.role" @click="submitAssign">
          <i class="bi bi-check-lg me-1"></i>Save Role
        </AppButton>
      </template>
    </AppModal>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useNotificationStore } from '@/stores/notification'
import admin from '@/services/admin'
import AppLayout from '@/components/layout/AppLayout.vue'
import AppCard from '@/components/ui/AppCard.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppModal from '@/components/ui/AppModal.vue'
import AppLoading from '@/components/ui/AppLoading.vue'
import AppStatusBadge from '@/components/ui/AppStatusBadge.vue'

const notify = useNotificationStore()
const loading = ref(false)
const assigning = ref(false)
const hasSearched = ref(false)
const search = ref('')
const filterRole = ref('')
const employees = ref([])
const selected = ref(null)
const assignForm = ref({ role: '' })

const roles = [
  { value: 'member',        label: 'Member',        desc: 'Regular member — can apply for loans and view their account', color: '#6b7280' },
  { value: 'receiver',      label: 'Receiver',       desc: 'Can receive and review loan applications, record payments', color: '#0ea5e9' },
  { value: 'loan_committee',label: 'Loan Committee', desc: 'Can evaluate and approve/disapprove loan applications', color: '#8b5cf6' },
  { value: 'chairperson',   label: 'Chairperson',    desc: 'Final approval authority for loan applications', color: '#f59e0b' },
  { value: 'admin',         label: 'Administrator',  desc: 'Full system access — manage members, config, reports', color: '#ef4444' },
]

function roleLabel(role) {
  return roles.find(r => r.value === role)?.label ?? role
}

function roleColor(role) {
  return roles.find(r => r.value === role)?.color ?? '#6b7280'
}

const filteredEmployees = computed(() => {
  if (!filterRole.value) return employees.value
  if (filterRole.value === '__unregistered') return employees.value.filter(e => !e.is_registered)
  return employees.value.filter(e => e.role === filterRole.value)
})

const roleSummary = computed(() => {
  const counts = {}
  employees.value.forEach(e => {
    if (e.role) counts[e.role] = (counts[e.role] ?? 0) + 1
  })
  return Object.entries(counts).map(([role, count]) => ({ role, count }))
})

const unregisteredCount = computed(() => employees.value.filter(e => !e.is_registered).length)

async function fetchUsers() {
  if (!search.value.trim()) return
  loading.value = true
  hasSearched.value = true
  try {
    const { data } = await admin.getUserTypes({ search: search.value.trim() })
    employees.value = data.data ?? data
    filterRole.value = ''
  } catch {
    notify.error('Failed to search employees.')
  } finally {
    loading.value = false
  }
}

function clearSearch() {
  search.value = ''
  employees.value = []
  hasSearched.value = false
  filterRole.value = ''
}

function openAssign(emp) {
  selected.value = emp
  assignForm.value = { role: emp.role ?? 'member' }
}

async function submitAssign() {
  assigning.value = true
  try {
    await admin.assignRole({
      employee_id: selected.value.employee_id,
      role: assignForm.value.role,
    })
    notify.success(`Role assigned: ${roleLabel(assignForm.value.role)}`)

    // Update local list
    const emp = employees.value.find(e => e.employee_id === selected.value.employee_id)
    if (emp) {
      emp.role = assignForm.value.role
      emp.is_registered = true
    }

    selected.value = null
  } catch (e) {
    notify.error(e.response?.data?.message || 'Failed to assign role.')
  } finally {
    assigning.value = false
  }
}
</script>

<style scoped>
.role-option {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  border: 2px solid #e2e8f0;
  border-radius: 10px;
  cursor: pointer;
  transition: all 0.15s ease;
}
.role-option:hover {
  border-color: #94a3b8;
  background: #f8fafc;
}
.role-option.selected {
  font-weight: 600;
}
.role-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  flex-shrink: 0;
}
.avatar-circle {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  background: linear-gradient(135deg, #1e40af, #3b82f6);
  color: #fff;
  font-weight: 700;
  font-size: 1rem;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
</style>
