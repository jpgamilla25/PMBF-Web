<template>
  <AppLayout>
    <div class="d-flex align-items-center justify-content-between mb-4">
      <h4 class="fw-bold mb-0">Dependents</h4>
      <AppButton variant="primary" @click="openModal">
        <i class="bi bi-plus-lg me-1"></i>Add Dependent
      </AppButton>
    </div>

    <AppLoading :loading="loading" text="Loading dependents..." />

    <template v-if="!loading">
      <div v-if="dependents.length === 0" class="text-center text-muted py-5">
        <i class="bi bi-people fs-1 d-block mb-3"></i>
        <p class="mb-1">No dependents added yet.</p>
        <p class="small">Click "Add Dependent" to register your first dependent.</p>
      </div>

      <div class="row g-3">
        <div v-for="dep in dependents" :key="dep.id" class="col-md-4">
          <AppCard>
            <div class="d-flex justify-content-between align-items-start mb-2">
              <div>
                <h6 class="fw-bold mb-0">
                  {{ dep.full_name ?? `${dep.first_name ?? ''} ${dep.last_name ?? ''}`.trim() }}
                </h6>
                <small class="text-muted">{{ dep.relationship ?? '-' }}</small>
              </div>
              <button
                class="btn btn-sm btn-outline-danger"
                :disabled="deleting === dep.id"
                @click="handleDelete(dep)"
              >
                <span v-if="deleting === dep.id" class="spinner-border spinner-border-sm" role="status"></span>
                <i v-else class="bi bi-trash"></i>
              </button>
            </div>

            <ul class="list-group list-group-flush small">
              <li v-if="dep.birth_date" class="list-group-item d-flex justify-content-between px-0">
                <span class="text-muted">Birth Date</span>
                <span>{{ formatDate(dep.birth_date) }}</span>
              </li>
              <li class="list-group-item d-flex justify-content-between px-0">
                <span class="text-muted">Coverage</span>
                <AppBadge
                  :variant="dep.coverage_type && dep.coverage_type !== 'none' ? 'success' : 'secondary'"
                  :text="formatCoverage(dep.coverage_type)"
                />
              </li>
              <li v-if="dep.attachments_count != null" class="list-group-item d-flex justify-content-between px-0">
                <span class="text-muted">Attachments</span>
                <span>{{ dep.attachments_count ?? 0 }}</span>
              </li>
            </ul>
          </AppCard>
        </div>
      </div>
    </template>

    <!-- Add Dependent Modal -->
    <AppModal :show="showModal" title="Add Dependent" size="lg" @close="closeModal">
      <div class="row">
        <div class="col-md-4">
          <AppInput
            v-model="depForm.form.first_name"
            label="First Name"
            required
            :error="depForm.errors.first_name"
          />
        </div>
        <div class="col-md-4">
          <AppInput
            v-model="depForm.form.middle_name"
            label="Middle Name"
            :error="depForm.errors.middle_name"
          />
        </div>
        <div class="col-md-4">
          <AppInput
            v-model="depForm.form.last_name"
            label="Last Name"
            required
            :error="depForm.errors.last_name"
          />
        </div>
        <div class="col-md-6">
          <AppInput
            v-model="depForm.form.relationship"
            type="select"
            label="Relationship"
            required
            :options="relationshipOptions"
            :error="depForm.errors.relationship"
          />
        </div>
        <div class="col-md-6">
          <AppInput
            v-model="depForm.form.birth_date"
            type="date"
            label="Birth Date"
            :error="depForm.errors.birth_date"
          />
        </div>
        <div class="col-md-6">
          <AppInput
            v-model="depForm.form.coverage_type"
            type="select"
            label="Coverage Type"
            :options="coverageOptions"
            :error="depForm.errors.coverage_type"
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
      </div>

      <template #footer>
        <AppButton variant="secondary" @click="closeModal">Cancel</AppButton>
        <AppButton variant="primary" :loading="depForm.processing.value" @click="handleSubmit">
          <i class="bi bi-plus-lg me-1"></i>Add Dependent
        </AppButton>
      </template>
    </AppModal>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useLoading } from '@/composables/useLoading'
import { useForm } from '@/composables/useForm'
import { useConfirm } from '@/composables/useConfirm'
import { useNotificationStore } from '@/stores/notification'
import membersService from '@/services/members'
import AppLayout from '@/components/layout/AppLayout.vue'
import AppCard from '@/components/ui/AppCard.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppInput from '@/components/ui/AppInput.vue'
import AppModal from '@/components/ui/AppModal.vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import AppLoading from '@/components/ui/AppLoading.vue'

const notification = useNotificationStore()
const { confirm } = useConfirm()
const { loading, withLoading } = useLoading()

const dependents = ref([])
const showModal = ref(false)
const deleting = ref(null)
const attachments = ref([])

const depForm = useForm({
  first_name: '',
  middle_name: '',
  last_name: '',
  relationship: '',
  birth_date: '',
  coverage_type: '',
})

const relationshipOptions = [
  { value: 'Spouse', label: 'Spouse' },
  { value: 'Child', label: 'Child' },
  { value: 'Parent', label: 'Parent' },
  { value: 'Sibling', label: 'Sibling' },
  { value: 'Other', label: 'Other' },
]

const coverageOptions = [
  { value: 'none', label: 'None' },
  { value: 'dental', label: 'Dental' },
  { value: 'hospitalization', label: 'Hospitalization' },
  { value: 'both', label: 'Both' },
]

function formatDate(dateStr) {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleDateString('en-PH', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })
}

function formatCoverage(type) {
  if (!type || type === 'none') return 'None'
  return type.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
}

function handleFileChange(event) {
  attachments.value = Array.from(event.target.files)
}

async function fetchDependents() {
  await withLoading(async () => {
    const response = await membersService.getDependents()
    const data = response.data.data ?? response.data
    dependents.value = Array.isArray(data) ? data : []
  })
}

function openModal() {
  depForm.reset()
  attachments.value = []
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  depForm.reset()
  attachments.value = []
}

async function handleSubmit() {
  try {
    await depForm.submit(async (data) => {
      const formData = new FormData()
      Object.keys(data).forEach((key) => {
        if (data[key]) formData.append(key, data[key])
      })
      attachments.value.forEach((file) => {
        formData.append('attachments[]', file)
      })
      await membersService.storeDependent(formData)
    })
    notification.success('Dependent added successfully.')
    closeModal()
    await fetchDependents()
  } catch {
    notification.error('Failed to add dependent.')
  }
}

async function handleDelete(dep) {
  const name = dep.full_name ?? `${dep.first_name ?? ''} ${dep.last_name ?? ''}`.trim()
  const ok = await confirm(`Are you sure you want to remove ${name} as a dependent?`)
  if (!ok) return

  deleting.value = dep.id
  try {
    await membersService.deleteDependent(dep.id)
    notification.success('Dependent removed successfully.')
    await fetchDependents()
  } catch {
    notification.error('Failed to remove dependent.')
  } finally {
    deleting.value = null
  }
}

onMounted(fetchDependents)
</script>
