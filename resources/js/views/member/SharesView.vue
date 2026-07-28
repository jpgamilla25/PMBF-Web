<template>
  <AppLayout>
    <h4 class="fw-bold mb-4">My {{ term }}</h4>

    <AppLoading :loading="loading" text="Loading shares..." />

    <template v-if="!loading">
      <!-- Summary Cards -->
      <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-4">
          <AppStatCard :title="`Current Monthly ${term === 'Medical Premium' ? 'Premium' : 'Share'}`" :value="data.current_monthly ?? 0" icon="bi bi-calendar-check" color="primary" prefix="₱" />
        </div>
        <div class="col-sm-6 col-lg-4">
          <AppStatCard :title="`Total ${term === 'Medical Premium' ? 'Premium' : 'Shares'}`" :value="data.total_shares ?? 0" icon="bi bi-pie-chart" color="success" prefix="₱" />
        </div>
        <div class="col-sm-6 col-lg-4">
          <div class="card shadow-sm border-0 h-100">
            <div class="card-body d-flex align-items-center justify-content-between">
              <div>
                <div class="text-muted small text-uppercase fw-semibold">Update Share Amount</div>
                <div class="small text-muted">Changes reflect next month</div>
              </div>
              <button class="btn btn-sm btn-outline-primary" :disabled="!!data.pending_request" @click="showRequestModal = true">
                <i class="bi bi-pencil me-1"></i>{{ data.pending_request ? 'Pending...' : 'Request Update' }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Pending Request -->
      <div v-if="data.pending_request" class="alert alert-warning small mb-4">
        <i class="bi bi-hourglass-split me-2"></i>
        <strong>Pending Update Request:</strong>
        You requested to change your monthly share from
        <strong>&#8369;{{ Number(data.pending_request.current_amount).toLocaleString() }}</strong>
        to <strong>&#8369;{{ Number(data.pending_request.requested_amount).toLocaleString() }}</strong>.
        Waiting for admin approval.
      </div>

      <!-- Year Filter -->
      <div class="d-flex align-items-center gap-3 mb-3">
        <label class="fw-semibold small">Year:</label>
        <select v-model="selectedYear" class="form-select form-select-sm" style="width: 120px;" @change="fetchData">
          <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
        </select>
      </div>

      <!-- Monthly History -->
      <AppCard title="Monthly Share History">
        <table class="table table-sm mb-0">
          <thead>
            <tr>
              <th>Month</th>
              <th>Amount</th>
              <th>Remarks</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="m in 12" :key="m">
              <td>{{ monthNames[m - 1] }} {{ selectedYear }}</td>
              <td>
                <strong v-if="getMonthData(m)">&#8369;{{ Number(getMonthData(m).amount).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}</strong>
                <span v-else class="text-muted">-</span>
              </td>
              <td class="text-muted small">{{ getMonthData(m)?.remarks ?? '' }}</td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="fw-bold">
              <td>Total</td>
              <td>&#8369;{{ yearTotal.toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}</td>
              <td></td>
            </tr>
          </tfoot>
        </table>
      </AppCard>
    </template>

    <!-- Request Update Modal -->
    <AppModal :show="showRequestModal" :title="`Request ${term} Update`" @close="showRequestModal = false">
      <div class="alert alert-info small">
        <i class="bi bi-info-circle me-1"></i>
        Changes will take effect <strong>next month</strong> after admin approval.
      </div>
      <AppInput v-model="requestForm.requested_amount" type="number" label="New Monthly Amount (₱)" placeholder="0.00" required />
      <AppInput v-model="requestForm.reason" type="textarea" label="Reason (optional)" placeholder="Why do you want to change your share amount?" />

      <template #footer>
        <AppButton variant="secondary" @click="showRequestModal = false">Cancel</AppButton>
        <AppButton variant="primary" :loading="submitting" @click="submitRequest">
          <i class="bi bi-send me-1"></i>Submit Request
        </AppButton>
      </template>
    </AppModal>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useNotificationStore } from '@/stores/notification'
import { useAuthStore } from '@/stores/auth'
import shares from '@/services/shares'
import AppLayout from '@/components/layout/AppLayout.vue'
import AppStatCard from '@/components/ui/AppStatCard.vue'
import AppCard from '@/components/ui/AppCard.vue'
import AppInput from '@/components/ui/AppInput.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppModal from '@/components/ui/AppModal.vue'
import AppLoading from '@/components/ui/AppLoading.vue'

const notify = useNotificationStore()
const authStore = useAuthStore()
// COS-Enrolled call this "Medical Premium"; everyone else "Share Capital".
const term = computed(() => authStore.sharesTerm)
const loading = ref(true)
const data = ref({})
const selectedYear = ref(new Date().getFullYear())
const showRequestModal = ref(false)
const submitting = ref(false)
const requestForm = ref({ requested_amount: '', reason: '' })

const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']
const yearOptions = Array.from({ length: 5 }, (_, i) => new Date().getFullYear() - i)

function getMonthData(month) {
  return (data.value.history ?? []).find(h => h.month === month)
}

const yearTotal = computed(() => {
  return (data.value.history ?? []).reduce((sum, h) => sum + Number(h.amount ?? 0), 0)
})

async function fetchData() {
  loading.value = true
  try {
    const { data: res } = await shares.getMyShares({ year: selectedYear.value })
    data.value = res.data ?? res
  } catch { /* ignore */ }
  finally { loading.value = false }
}

async function submitRequest() {
  if (!requestForm.value.requested_amount) {
    notify.error('Please enter an amount.')
    return
  }
  submitting.value = true
  try {
    await shares.requestUpdate(requestForm.value)
    notify.success('Share update request submitted. Changes will reflect next month after approval.')
    showRequestModal.value = false
    requestForm.value = { requested_amount: '', reason: '' }
    await fetchData()
  } catch (e) {
    notify.error(e.response?.data?.message || 'Failed to submit request.')
  } finally { submitting.value = false }
}

onMounted(fetchData)
</script>
