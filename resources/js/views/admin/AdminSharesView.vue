<template>
  <AppLayout>
    <div class="d-flex align-items-center justify-content-between mb-4">
      <h4 class="fw-bold mb-0">Share Capital Management</h4>
      <div class="d-flex gap-2">
        <span v-if="pendingRequests.length" class="badge bg-danger rounded-pill align-self-center">
          {{ pendingRequests.length }} pending request(s)
        </span>
        <AppButton variant="outline-secondary" size="sm" :loading="syncing" :disabled="syncing" @click="syncFromFmis">
          <i v-if="!syncing" class="bi bi-arrow-repeat me-1"></i>
          {{ syncing ? 'Syncing…' : `Sync ${filterYear} from FMIS` }}
        </AppButton>
        <AppButton variant="primary" size="sm" @click="openAddModal">
          <i class="bi bi-plus-lg me-1"></i>Set Share
        </AppButton>
      </div>
    </div>

    <!-- Pending Requests -->
    <AppCard v-if="pendingRequests.length" title="Pending Update Requests" class="mb-4">
      <div v-for="req in pendingRequests" :key="req.id" class="d-flex align-items-center justify-content-between py-2 border-bottom">
        <div>
          <strong>{{ req.user?.last_name }}, {{ req.user?.first_name }}</strong>
          <span class="text-muted small ms-2">({{ req.user?.employee_id }})</span>
          <div class="small text-muted">
            Current: &#8369;{{ Number(req.current_amount).toLocaleString() }}
            → Requested: <strong class="text-primary">&#8369;{{ Number(req.requested_amount).toLocaleString() }}</strong>
            <span v-if="req.reason" class="fst-italic ms-2">"{{ req.reason }}"</span>
          </div>
        </div>
        <div class="d-flex gap-1">
          <button class="btn btn-sm btn-success" @click="approveReq(req.id)"><i class="bi bi-check-lg"></i></button>
          <button class="btn btn-sm btn-outline-danger" @click="rejectReqId = req.id; showRejectModal = true"><i class="bi bi-x-lg"></i></button>
        </div>
      </div>
    </AppCard>

    <!-- Filters -->
    <AppCard class="mb-4">
      <div class="row g-3 align-items-end">
        <div class="col-md-3">
          <label class="form-label fw-semibold small">Year</label>
          <select v-model="filterYear" class="form-select form-select-sm" @change="fetchShares">
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
      <AppLoading :loading="loading" text="Loading shares..." />
      <table v-if="!loading" class="table table-hover mb-0">
        <thead>
          <tr>
            <th>#</th>
            <th>Employee</th>
            <th>Type</th>
            <th>Total ({{ filterYear }})</th>
            <th>Latest Monthly</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!members.length">
            <td colspan="6" class="text-center text-muted py-3">No share records found.</td>
          </tr>
          <tr v-for="(m, i) in members" :key="m.user_id">
            <td>{{ i + 1 }}</td>
            <td>
              <div class="fw-semibold">{{ m.user?.last_name }}, {{ m.user?.first_name }}</div>
              <small class="text-muted">{{ m.user?.employee_id }}</small>
            </td>
            <td><AppStatusBadge :status="m.user?.employment_type ?? '-'" /></td>
            <td class="fw-semibold">&#8369;{{ Number(m.total ?? 0).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}</td>
            <td>&#8369;{{ Number(m.latest_amount ?? 0).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}</td>
            <td class="text-end">
              <button class="btn btn-sm btn-outline-primary" @click="openEditModal(m)">
                <i class="bi bi-pencil me-1"></i>Edit
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </AppCard>

    <!-- Add/Edit Share Modal -->
    <AppModal :show="showAddModal" :title="editingUser ? 'Update Share Capital' : 'Set Share Capital'" @close="showAddModal = false">
      <div v-if="!editingUser" class="mb-3">
        <AppSearchSelect v-model="addForm.user_id" label="Employee *" :options="memberOptions" placeholder="Search employee..." remote @search="searchMembers" />
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
        This will set the share amount for <strong>{{ addForm.month ? monthNames[addForm.month - 1] : '...' }}</strong> through <strong>December {{ filterYear }}</strong>.
      </div>

      <AppInput v-model="addForm.remarks" type="textarea" label="Remarks" placeholder="Optional..." />

      <template #footer>
        <AppButton variant="secondary" @click="showAddModal = false">Cancel</AppButton>
        <AppButton variant="primary" :loading="saving" @click="handleSave">
          <i class="bi bi-check-lg me-1"></i>Save
        </AppButton>
      </template>
    </AppModal>

    <!-- Reject Modal -->
    <AppModal :show="showRejectModal" title="Reject Share Update" @close="showRejectModal = false">
      <AppInput v-model="rejectRemarks" type="textarea" label="Reason for rejection *" placeholder="Why are you rejecting this request?" required />
      <template #footer>
        <AppButton variant="secondary" @click="showRejectModal = false">Cancel</AppButton>
        <AppButton variant="danger" :loading="rejecting" @click="rejectReq">Reject</AppButton>
      </template>
    </AppModal>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import { useNotificationStore } from '@/stores/notification'
import { useAdminContextStore } from '@/stores/adminContext'
import sharesService from '@/services/shares'
import admin from '@/services/admin'
import AppLayout from '@/components/layout/AppLayout.vue'
import AppCard from '@/components/ui/AppCard.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppInput from '@/components/ui/AppInput.vue'
import AppModal from '@/components/ui/AppModal.vue'
import AppSearchSelect from '@/components/ui/AppSearchSelect.vue'
import AppStatusBadge from '@/components/ui/AppStatusBadge.vue'
import AppLoading from '@/components/ui/AppLoading.vue'

const notify = useNotificationStore()
const adminContext = useAdminContextStore()

const loading = ref(false)
const members = ref([])
const totalAmount = ref(0)
const memberCount = ref(0)
const filterYear = ref(new Date().getFullYear())
const filterSearch = ref('')
const pendingRequests = ref([])
const yearOptions = Array.from({ length: 5 }, (_, i) => new Date().getFullYear() - i)
const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']
const monthOptions = monthNames.map((n, i) => ({ value: i + 1, label: n }))

// Add/Edit
const showAddModal = ref(false)
const editingUser = ref(null)
const saving = ref(false)
const addForm = ref({ user_id: '', amount: '', month: new Date().getMonth() + 1, remarks: '' })
const memberOptions = ref([])

// Reject
const showRejectModal = ref(false)
const rejectReqId = ref(null)
const rejectRemarks = ref('')
const rejecting = ref(false)

// FMIS sync
const syncing = ref(false)

let debounceTimer = null
function debouncedFetch() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(fetchShares, 400)
}

async function fetchShares() {
  loading.value = true
  try {
    const params = { year: filterYear.value }
    if (filterSearch.value) params.search = filterSearch.value
    if (adminContext.memberType && adminContext.memberType !== 'all') params.employment_type = adminContext.memberType
    const { data } = await sharesService.getAll(params)
    const res = data.data ?? data
    members.value = res.members ?? []
    totalAmount.value = res.total_amount ?? 0
    memberCount.value = res.member_count ?? 0
  } catch { /* ignore */ }
  finally { loading.value = false }
}

async function fetchPendingRequests() {
  try {
    const { data } = await sharesService.getPendingRequests()
    pendingRequests.value = data.data ?? data
  } catch { /* ignore */ }
}

async function searchMembers(query) {
  try {
    const { data } = await admin.getMembers({ search: query, per_page: 20 })
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
    await sharesService.bulkStore({
      user_id: addForm.value.user_id,
      amount: addForm.value.amount,
      year: filterYear.value,
      from_month: addForm.value.month,
    })
    notify.success('Share capital updated.')
    showAddModal.value = false
    await fetchShares()
  } catch (e) {
    notify.error(e.response?.data?.message || 'Failed to save.')
  } finally { saving.value = false }
}

async function syncFromFmis() {
  if (syncing.value) return
  if (!confirm(`Pull share-capital contributions for ${filterYear.value} from the FMIS api-center? This may take 1-2 minutes.`)) return
  syncing.value = true
  try {
    const { data } = await sharesService.syncFromFmis({ year: filterYear.value })
    const msg = data.data?.summary || data.message || 'FMIS sync complete.'
    notify.success(msg)
    await fetchShares()
  } catch (e) {
    notify.error(e.response?.data?.message || 'FMIS sync failed.')
  } finally { syncing.value = false }
}

async function approveReq(id) {
  try {
    await sharesService.approveRequest(id)
    notify.success('Share update approved. Will reflect next month.')
    pendingRequests.value = pendingRequests.value.filter(r => r.id !== id)
    await fetchShares()
  } catch (e) {
    notify.error(e.response?.data?.message || 'Failed.')
  }
}

async function rejectReq() {
  if (!rejectRemarks.value) { notify.error('Remarks required.'); return }
  rejecting.value = true
  try {
    await sharesService.rejectRequest(rejectReqId.value, { remarks: rejectRemarks.value })
    notify.success('Request rejected.')
    showRejectModal.value = false
    rejectRemarks.value = ''
    pendingRequests.value = pendingRequests.value.filter(r => r.id !== rejectReqId.value)
  } catch (e) {
    notify.error(e.response?.data?.message || 'Failed.')
  } finally { rejecting.value = false }
}

watch(() => adminContext.memberType, fetchShares)

onMounted(() => {
  fetchShares()
  fetchPendingRequests()
})
</script>
