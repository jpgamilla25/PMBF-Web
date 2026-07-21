<template>
  <AppLayout>
    <div class="d-flex align-items-center justify-content-between mb-4">
      <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-envelope-paper me-2"></i>Special Approval Requests</h4>
        <p class="text-muted small mb-0">
          Members who fall outside the standard loan rules and have asked for an exemption.
        </p>
      </div>
      <button class="btn btn-outline-secondary btn-sm" :disabled="loading" @click="fetch">
        <i class="bi bi-arrow-clockwise me-1"></i>Refresh
      </button>
    </div>

    <!-- Filters -->
    <AppCard class="mb-4">
      <div class="row g-2 align-items-end">
        <div class="col-sm-6 col-lg-3">
          <label class="form-label small fw-medium mb-1">Status</label>
          <select v-model="filters.status" class="form-select form-select-sm" @change="fetch">
            <option value="pending">Pending ({{ counts.pending }})</option>
            <option value="approved">Approved ({{ counts.approved }})</option>
            <option value="rejected">Rejected ({{ counts.rejected }})</option>
            <option value="all">All</option>
          </select>
        </div>
        <div class="col-sm-6 col-lg-3">
          <label class="form-label small fw-medium mb-1">Type</label>
          <select v-model="filters.type" class="form-select form-select-sm" @change="fetch">
            <option value="">All types</option>
            <option value="below_minimum_pay">Below minimum take-home pay</option>
            <option value="exceed_max_amount">Exceeds maximum amount</option>
            <option value="extend_term">Extend term beyond contract</option>
          </select>
        </div>
        <div class="col-lg-4">
          <label class="form-label small fw-medium mb-1">Member</label>
          <input
            v-model="filters.search"
            type="text"
            class="form-control form-control-sm"
            placeholder="Name or employee ID"
            @keyup.enter="fetch"
          />
        </div>
        <div class="col-lg-2 d-grid">
          <button class="btn btn-primary btn-sm" @click="fetch">
            <i class="bi bi-search me-1"></i>Filter
          </button>
        </div>
      </div>
    </AppCard>

    <AppLoading :loading="loading" text="Loading requests..." />

    <div v-if="!loading && !requests.length" class="text-center text-muted py-5">
      <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
      No {{ filters.status === 'all' ? '' : filters.status }} requests.
    </div>

    <!-- Requests -->
    <AppCard v-for="r in requests" :key="r.id" class="mb-3">
      <div class="d-flex flex-wrap justify-content-between gap-3">
        <div class="flex-grow-1">
          <div class="d-flex align-items-center gap-2 mb-1">
            <span class="fw-bold">{{ r.user?.first_name }} {{ r.user?.last_name }}</span>
            <span class="text-muted small font-monospace">{{ r.user?.employee_id }}</span>
            <AppBadge :variant="typeVariant(r.type)" :text="typeLabel(r.type)" />
            <AppStatusBadge :status="r.status" />
          </div>

          <div class="text-muted small mb-2">
            {{ r.user?.employment_type }}
            <span v-if="r.user?.department">&middot; {{ r.user.department }}</span>
            &middot; {{ r.loan_type }}
            &middot; requested {{ formatDate(r.created_at) }}
          </div>

          <!-- The numbers that triggered the request -->
          <div class="row g-2 mb-2">
            <div v-if="Number(r.current_value)" class="col-auto">
              <div class="cmp-box">
                <div class="cmp-label">Member's value</div>
                <div class="cmp-value text-danger">{{ money(r.current_value) }}</div>
              </div>
            </div>
            <div v-if="Number(r.required_value)" class="col-auto">
              <div class="cmp-box">
                <div class="cmp-label">Required</div>
                <div class="cmp-value">{{ money(r.required_value) }}</div>
              </div>
            </div>
            <div v-if="Number(r.requested_value)" class="col-auto">
              <div class="cmp-box">
                <div class="cmp-label">Requested</div>
                <div class="cmp-value text-primary">{{ money(r.requested_value) }}</div>
              </div>
            </div>
          </div>

          <div class="small">
            <span class="fw-semibold">Reason:</span>
            <span class="text-body-secondary">{{ r.reason }}</span>
          </div>

          <div v-if="r.status !== 'pending'" class="small mt-2 pt-2 border-top">
            <span class="fw-semibold">
              {{ r.status === 'approved' ? 'Approved' : 'Rejected' }} by
              {{ r.reviewer ? `${r.reviewer.first_name} ${r.reviewer.last_name}` : 'administrator' }}
            </span>
            <span class="text-muted">on {{ formatDate(r.reviewed_at) }}</span>
            <div v-if="r.reviewer_remarks" class="text-body-secondary">{{ r.reviewer_remarks }}</div>
            <div v-if="r.status === 'approved' && r.expires_at" class="text-muted">
              Exemption valid until {{ formatDate(r.expires_at) }}
            </div>
          </div>
        </div>

        <div v-if="r.status === 'pending'" class="d-flex flex-column gap-2 align-self-center">
          <AppButton variant="success" size="sm" :disabled="processing" @click="openAction(r, 'approve')">
            <i class="bi bi-check-lg me-1"></i>Approve
          </AppButton>
          <AppButton variant="outline-danger" size="sm" :disabled="processing" @click="openAction(r, 'reject')">
            <i class="bi bi-x-lg me-1"></i>Reject
          </AppButton>
        </div>
      </div>
    </AppCard>

    <!-- Approve / Reject modal -->
    <AppModal
      :show="showModal"
      :title="action === 'approve' ? 'Approve Request' : 'Reject Request'"
      @close="showModal = false"
    >
      <p class="small">
        {{ action === 'approve' ? 'Approve' : 'Reject' }} the request from
        <strong>{{ selected?.user?.first_name }} {{ selected?.user?.last_name }}</strong>
        ({{ typeLabel(selected?.type) }}).
      </p>

      <div v-if="action === 'approve'" class="alert alert-warning small">
        <i class="bi bi-exclamation-triangle me-1"></i>
        Approving lets this member apply despite the rule. The exemption is time-limited
        and applies only to <strong>{{ selected?.loan_type }}</strong>.
      </div>

      <label class="form-label fw-semibold small">
        Remarks <span v-if="action === 'reject'" class="text-danger">*</span>
      </label>
      <textarea
        v-model="remarks"
        class="form-control"
        rows="3"
        :class="{ 'is-invalid': remarksError }"
        :placeholder="action === 'approve' ? 'Optional note for the record' : 'Explain why this is rejected'"
      ></textarea>
      <div v-if="remarksError" class="invalid-feedback d-block">{{ remarksError }}</div>

      <template #footer>
        <button class="btn btn-secondary" :disabled="processing" @click="showModal = false">Cancel</button>
        <AppButton
          :variant="action === 'approve' ? 'success' : 'danger'"
          :loading="processing"
          @click="submitAction"
        >
          Confirm {{ action === 'approve' ? 'Approval' : 'Rejection' }}
        </AppButton>
      </template>
    </AppModal>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useLoading } from '@/composables/useLoading'
import { useNotificationStore } from '@/stores/notification'
import exemptionsService from '@/services/exemptions'
import AppLayout from '@/components/layout/AppLayout.vue'
import AppCard from '@/components/ui/AppCard.vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import AppStatusBadge from '@/components/ui/AppStatusBadge.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppModal from '@/components/ui/AppModal.vue'
import AppLoading from '@/components/ui/AppLoading.vue'

const notify = useNotificationStore()
const { loading, withLoading } = useLoading()

const requests = ref([])
const counts = reactive({ pending: 0, approved: 0, rejected: 0 })
const filters = reactive({ status: 'pending', type: '', search: '' })

const showModal = ref(false)
const selected = ref(null)
const action = ref('approve')
const remarks = ref('')
const remarksError = ref('')
const processing = ref(false)

const TYPE_LABELS = {
  below_minimum_pay: 'Below minimum pay',
  exceed_max_amount: 'Exceeds max amount',
  extend_term: 'Extended term',
}

function typeLabel(type) {
  return TYPE_LABELS[type] ?? type ?? '-'
}

function typeVariant(type) {
  return { below_minimum_pay: 'danger', exceed_max_amount: 'warning', extend_term: 'info' }[type] ?? 'secondary'
}

function money(value) {
  return '₱' + Number(value ?? 0).toLocaleString('en-PH', { minimumFractionDigits: 2 })
}

function formatDate(value) {
  if (!value) return '-'
  return new Date(value).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
}

async function fetch() {
  await withLoading(async () => {
    const params = { status: filters.status }
    if (filters.type) params.type = filters.type
    if (filters.search) params.search = filters.search

    const { data } = await exemptionsService.getAll(params)
    const result = data.data ?? data

    requests.value = result.requests ?? result ?? []
    Object.assign(counts, result.counts ?? {})
  })
}

function openAction(request, kind) {
  selected.value = request
  action.value = kind
  remarks.value = ''
  remarksError.value = ''
  showModal.value = true
}

async function submitAction() {
  remarksError.value = ''

  // Rejection remarks are required — the member is told why.
  if (action.value === 'reject' && !remarks.value.trim()) {
    remarksError.value = 'Please explain why this request is rejected.'
    return
  }

  processing.value = true
  try {
    const payload = { remarks: remarks.value.trim() || undefined }

    if (action.value === 'approve') {
      await exemptionsService.approve(selected.value.id, payload)
      notify.success('Request approved. The member can now apply.')
    } else {
      await exemptionsService.reject(selected.value.id, { remarks: remarks.value.trim() })
      notify.success('Request rejected.')
    }

    showModal.value = false
    await fetch()
  } catch (error) {
    notify.error(error.response?.data?.message || 'Could not process this request.')
  } finally {
    processing.value = false
  }
}

onMounted(fetch)
</script>

<style scoped>
.cmp-box {
  border: 1px solid var(--bs-border-color);
  border-radius: 8px;
  padding: 6px 12px;
  min-width: 120px;
}

.cmp-label {
  font-size: 0.65rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--bs-secondary-color);
  font-weight: 700;
}

.cmp-value {
  font-weight: 700;
  font-size: 0.95rem;
}
</style>
