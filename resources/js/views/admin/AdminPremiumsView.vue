<template>
  <AppLayout>
    <div class="d-flex align-items-center justify-content-between mb-4">
      <div>
        <h4 class="fw-bold mb-0">Premium Contributions</h4>
        <p class="text-muted small mb-0">Contract of Service members' benefit-fund coverage payments.</p>
      </div>
      <div class="d-flex gap-2">
        <AppButton variant="primary" size="sm" @click="openAddModal">
          <i class="bi bi-plus-lg me-1"></i>Set Premium
        </AppButton>
      </div>
    </div>

    <!-- Analytics -->
    <div class="row g-3 mb-4">
      <div class="col-sm-6 col-lg-3">
        <AppCard padding>
          <div class="text-muted small">Registered COS Members</div>
          <div class="fs-4 fw-bold text-success">
            {{ Number(analytics.registered?.employees ?? 0).toLocaleString() }}
          </div>
          <div class="small text-muted">
            &#8369;{{ Number(analytics.registered?.total_amount ?? 0).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }} this {{ filterYear }}
          </div>
        </AppCard>
      </div>
      <div class="col-sm-6 col-lg-3">
        <AppCard padding>
          <div class="text-muted small">Unregistered Employees</div>
          <div class="fs-4 fw-bold text-warning">
            {{ Number(analytics.unregistered?.employees ?? 0).toLocaleString() }}
          </div>
          <div class="small text-muted">
            &#8369;{{ Number(analytics.unregistered?.total_amount ?? 0).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }} held in FMIS only
          </div>
        </AppCard>
      </div>
      <div class="col-sm-6 col-lg-3">
        <AppCard padding>
          <div class="text-muted small">FMIS Total ({{ filterYear }})</div>
          <div class="fs-4 fw-bold text-primary">
            &#8369;{{ Number(fmisGrandTotal).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}
          </div>
          <div class="small text-muted">
            {{ Number((analytics.registered?.employees ?? 0) + (analytics.unregistered?.employees ?? 0)).toLocaleString() }} unique employees
          </div>
        </AppCard>
      </div>
      <div class="col-sm-6 col-lg-3">
        <AppCard padding>
          <div class="text-muted small">Last FMIS Sync</div>
          <div class="fs-6 fw-semibold">
            {{ analytics.last_synced_at ? formatSyncTime(analytics.last_synced_at) : 'Never' }}
          </div>
          <div class="small text-muted">Sync from the Share Capital page</div>
        </AppCard>
      </div>
    </div>

    <!-- Filters -->
    <AppCard class="mb-4">
      <div class="row g-3 align-items-end">
        <div class="col-md-3">
          <label class="form-label fw-semibold small">Year</label>
          <select v-model="filterYear" class="form-select form-select-sm" @change="fetchPremiums">
            <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold small">Search</label>
          <input v-model="filterSearch" type="text" class="form-control form-control-sm" placeholder="Name or Employee ID..." @input="debouncedFetch" />
        </div>
        <div class="col-md-3">
          <div class="fw-bold text-success">
            Total: &#8369;{{ Number(totalAmount).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}
          </div>
          <div class="small text-muted">{{ memberCount }} members</div>
        </div>
      </div>
    </AppCard>

    <!-- Members Table -->
    <AppCard :padding="false">
      <AppLoading :loading="loading" text="Loading premiums..." />
      <table v-if="!loading" class="table table-hover mb-0">
        <thead>
          <tr>
            <th>#</th>
            <th>Employee</th>
            <th>Department</th>
            <th>Total ({{ filterYear }})</th>
            <th>Latest Monthly</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!members.length">
            <td colspan="6" class="text-center text-muted py-3">No premium records found.</td>
          </tr>
          <tr v-for="(m, i) in members" :key="m.user_id">
            <td>{{ i + 1 }}</td>
            <td>
              <div class="fw-semibold">{{ m.user?.last_name }}, {{ m.user?.first_name }}</div>
              <small class="text-muted">{{ m.user?.employee_id }}</small>
            </td>
            <td>{{ m.user?.department ?? '-' }}</td>
            <td class="fw-semibold">&#8369;{{ Number(m.total ?? 0).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}</td>
            <td>&#8369;{{ Number(m.latest_amount ?? 0).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}</td>
            <td class="text-end">
              <button class="btn btn-sm btn-outline-secondary me-1" @click="openViewModal(m)" title="View per-month premiums">
                <i class="bi bi-eye"></i>
              </button>
              <button class="btn btn-sm btn-outline-primary" @click="openEditModal(m)">
                <i class="bi bi-pencil me-1"></i>Edit
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </AppCard>

    <!-- Add/Edit Premium Modal -->
    <AppModal :show="showAddModal" :title="editingUser ? 'Update Premium' : 'Set Premium'" @close="showAddModal = false">
      <div v-if="!editingUser" class="mb-3">
        <AppSearchSelect v-model="addForm.user_id" label="Employee *" :options="memberOptions" placeholder="Search Contract of Service employee..." remote @search="searchMembers" />
      </div>
      <div v-else class="alert alert-light small mb-3">
        <strong>{{ editingUser.last_name }}, {{ editingUser.first_name }}</strong> ({{ editingUser.employee_id }})
      </div>

      <div class="row">
        <div class="col-md-6">
          <AppInput v-model="addForm.amount" type="number" label="Monthly Amount (₱)" placeholder="0.00" required />
        </div>
        <div class="col-md-6">
          <AppInput v-model="addForm.month" type="select" label="From Month" :options="monthOptions" required />
        </div>
      </div>

      <div class="alert alert-info small mt-2">
        <i class="bi bi-info-circle me-1"></i>
        This will set the premium amount for <strong>{{ addForm.month ? monthNames[addForm.month - 1] : '...' }}</strong> through <strong>December {{ filterYear }}</strong>.
      </div>

      <AppInput v-model="addForm.remarks" type="textarea" label="Remarks" placeholder="Optional..." />

      <template #footer>
        <AppButton variant="secondary" @click="showAddModal = false">Cancel</AppButton>
        <AppButton variant="primary" :loading="saving" @click="handleSave">
          <i class="bi bi-check-lg me-1"></i>Save
        </AppButton>
      </template>
    </AppModal>

    <!-- View Member Premiums Modal -->
    <MemberPremiumsModal :show="showViewModal" :user-id="viewUserId" :initial-year="filterYear" @close="closeViewModal" />
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useNotificationStore } from '@/stores/notification'
import premiumsService from '@/services/premiums'
import admin from '@/services/admin'
import AppLayout from '@/components/layout/AppLayout.vue'
import AppCard from '@/components/ui/AppCard.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppInput from '@/components/ui/AppInput.vue'
import AppModal from '@/components/ui/AppModal.vue'
import AppSearchSelect from '@/components/ui/AppSearchSelect.vue'
import MemberPremiumsModal from '@/components/ui/MemberPremiumsModal.vue'
import AppLoading from '@/components/ui/AppLoading.vue'

const notify = useNotificationStore()

const loading = ref(false)
const members = ref([])
const totalAmount = ref(0)
const memberCount = ref(0)
const filterYear = ref(new Date().getFullYear())
const filterSearch = ref('')
const yearOptions = Array.from({ length: 5 }, (_, i) => new Date().getFullYear() - i)
const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']
const monthOptions = monthNames.map((n, i) => ({ value: i + 1, label: n }))

const showAddModal = ref(false)
const editingUser = ref(null)
const saving = ref(false)
const addForm = ref({ user_id: '', amount: '', month: new Date().getMonth() + 1, remarks: '' })
const memberOptions = ref([])

const showViewModal = ref(false)
const viewUserId = ref(null)

function openViewModal(m) {
  viewUserId.value = m.user_id
  showViewModal.value = true
}
function closeViewModal() {
  showViewModal.value = false
  viewUserId.value = null
}

const analytics = ref({})
const fmisGrandTotal = computed(() =>
  Number(analytics.value.registered?.total_amount ?? 0) + Number(analytics.value.unregistered?.total_amount ?? 0)
)

function formatSyncTime(iso) {
  try {
    return new Date(iso).toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' })
  } catch { return iso }
}

async function fetchAnalytics() {
  try {
    const { data } = await premiumsService.getAnalytics({ year: filterYear.value })
    analytics.value = data.data ?? data
  } catch { /* ignore */ }
}

let debounceTimer = null
function debouncedFetch() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(fetchPremiums, 400)
}

async function fetchPremiums() {
  loading.value = true
  try {
    const params = { year: filterYear.value }
    if (filterSearch.value) params.search = filterSearch.value
    const { data } = await premiumsService.getAll(params)
    const res = data.data ?? data
    members.value = res.members ?? []
    totalAmount.value = res.total_amount ?? 0
    memberCount.value = res.member_count ?? 0
  } catch { /* ignore */ }
  finally { loading.value = false }
}

async function searchMembers(query) {
  try {
    const { data } = await admin.getMembers({ search: query, employment_type: 'Contract of Service', per_page: 20 })
    const list = data.data ?? []
    memberOptions.value = list.map(u => ({
      value: u.id,
      label: `${u.last_name}, ${u.first_name} (${u.employee_id})`,
      sublabel: u.employment_type + ' — ' + (u.department ?? ''),
    }))
  } catch { /* ignore */ }
}

function openAddModal() {
  editingUser.value = null
  addForm.value = { user_id: '', amount: '', month: new Date().getMonth() + 1, remarks: '' }
  showAddModal.value = true
}
function openEditModal(m) {
  editingUser.value = m.user
  addForm.value = { user_id: m.user_id, amount: m.latest_amount, month: new Date().getMonth() + 1, remarks: '' }
  showAddModal.value = true
}

async function handleSave() {
  if (!addForm.value.user_id || !addForm.value.amount) {
    notify.error('Please fill required fields.')
    return
  }
  saving.value = true
  try {
    await premiumsService.bulkStore({
      user_id: addForm.value.user_id,
      amount: addForm.value.amount,
      year: filterYear.value,
      from_month: addForm.value.month,
    })
    notify.success('Premium updated.')
    showAddModal.value = false
    await fetchPremiums()
  } catch (e) {
    notify.error(e.response?.data?.message || 'Failed to save.')
  } finally { saving.value = false }
}

watch(filterYear, fetchAnalytics)

onMounted(() => {
  fetchPremiums()
  fetchAnalytics()
})
</script>
