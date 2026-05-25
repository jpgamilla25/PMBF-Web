<template>
  <AppLayout>
    <div class="d-flex align-items-center justify-content-between mb-4">
      <h4 class="fw-bold mb-0">Claims</h4>
      <AppButton variant="primary" @click="openModal">
        <i class="bi bi-plus-lg me-1"></i>File Claim
      </AppButton>
    </div>

    <!-- Claims Table -->
    <AppCard :padding="false">
      <AppTable :columns="columns" :items="claims" :loading="loading" empty-text="No claims filed yet.">
        <template #cell(index)="{ item }">
          {{ claims.indexOf(item) + 1 }}
        </template>
        <template #cell(claim_type)="{ value }">
          {{ formatType(value) }}
        </template>
        <template #cell(dependent)="{ item }">
          {{ item.dependent?.full_name ?? item.dependent_name ?? 'Self' }}
        </template>
        <template #cell(amount)="{ value }">
          <span class="fw-medium">&#8369;{{ Number(value ?? 0).toLocaleString() }}</span>
        </template>
        <template #cell(status)="{ value }">
          <AppStatusBadge :status="value" />
        </template>
        <template #cell(created_at)="{ value }">
          {{ formatDate(value) }}
        </template>
      </AppTable>
    </AppCard>

    <!-- File Claim Modal -->
    <AppModal :show="showModal" title="File a Claim" size="lg" @close="closeModal">
      <div class="row">
        <div class="col-md-6">
          <AppInput
            v-model="claimForm.form.claim_type"
            type="select"
            label="Claim Type"
            required
            :options="claimTypeOptions"
            :error="claimForm.errors.claim_type"
          />
        </div>
        <div class="col-md-6">
          <AppInput
            v-model="claimForm.form.dependent_id"
            type="select"
            label="Dependent"
            :options="dependentOptions"
            :error="claimForm.errors.dependent_id"
          />
        </div>
        <div class="col-md-6">
          <AppInput
            v-model="claimForm.form.amount"
            type="number"
            label="Amount"
            required
            placeholder="0.00"
            :error="claimForm.errors.amount"
          />
        </div>
        <div class="col-md-6">
          <div class="mb-3">
            <label class="form-label">Attachments</label>
            <input
              type="file"
              class="form-control"
              multiple
              @change="handleFileChange"
            />
            <small class="text-muted">Upload supporting documents</small>
          </div>
        </div>
        <div class="col-12">
          <AppInput
            v-model="claimForm.form.description"
            type="textarea"
            label="Description"
            placeholder="Describe your claim..."
            :error="claimForm.errors.description"
          />
        </div>
      </div>

      <template #footer>
        <AppButton variant="secondary" @click="closeModal">Cancel</AppButton>
        <AppButton variant="primary" :loading="claimForm.processing.value" @click="handleSubmit">
          <i class="bi bi-check-lg me-1"></i>Submit Claim
        </AppButton>
      </template>
    </AppModal>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useLoading } from '@/composables/useLoading'
import { useForm } from '@/composables/useForm'
import { useNotificationStore } from '@/stores/notification'
import membersService from '@/services/members'
import AppLayout from '@/components/layout/AppLayout.vue'
import AppCard from '@/components/ui/AppCard.vue'
import AppTable from '@/components/ui/AppTable.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppInput from '@/components/ui/AppInput.vue'
import AppModal from '@/components/ui/AppModal.vue'
import AppStatusBadge from '@/components/ui/AppStatusBadge.vue'
import AppLoading from '@/components/ui/AppLoading.vue'

const notification = useNotificationStore()
const { loading, withLoading } = useLoading()

const claims = ref([])
const dependentOptions = ref([])
const showModal = ref(false)
const attachments = ref([])

const claimForm = useForm({
  claim_type: '',
  dependent_id: '',
  amount: '',
  description: '',
})

const claimTypeOptions = [
  { value: 'dental', label: 'Dental' },
  { value: 'hospitalization', label: 'Hospitalization' },
  { value: 'other', label: 'Other' },
]

const columns = [
  { key: 'index', label: '#', class: 'text-center' },
  { key: 'claim_type', label: 'Type' },
  { key: 'dependent', label: 'Dependent' },
  { key: 'amount', label: 'Amount' },
  { key: 'status', label: 'Status' },
  { key: 'created_at', label: 'Filed' },
]

function formatType(type) {
  if (!type) return '-'
  return type.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
}

function formatDate(dateStr) {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleDateString('en-PH', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })
}

function handleFileChange(event) {
  attachments.value = Array.from(event.target.files)
}

async function fetchClaims() {
  await withLoading(async () => {
    const response = await membersService.getClaims()
    const data = response.data.data ?? response.data
    claims.value = Array.isArray(data) ? data : []
  })
}

async function fetchDependents() {
  try {
    const response = await membersService.getDependents()
    const data = response.data.data ?? response.data
    const deps = Array.isArray(data) ? data : []
    dependentOptions.value = [
      { value: '', label: 'Self' },
      ...deps.map((d) => ({
        value: d.id,
        label: d.full_name ?? `${d.first_name ?? ''} ${d.last_name ?? ''}`.trim(),
      })),
    ]
  } catch {
    dependentOptions.value = [{ value: '', label: 'Self' }]
  }
}

function openModal() {
  claimForm.reset()
  attachments.value = []
  fetchDependents()
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  claimForm.reset()
  attachments.value = []
}

async function handleSubmit() {
  try {
    await claimForm.submit(async (data) => {
      const formData = new FormData()
      Object.keys(data).forEach((key) => {
        if (data[key]) formData.append(key, data[key])
      })
      attachments.value.forEach((file) => {
        formData.append('attachments[]', file)
      })
      await membersService.storeClaim(formData)
    })
    notification.success('Claim submitted successfully.')
    closeModal()
    await fetchClaims()
  } catch {
    notification.error('Failed to submit claim.')
  }
}

onMounted(fetchClaims)
</script>
