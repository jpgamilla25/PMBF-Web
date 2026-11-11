<template>
  <AppCard title="Personal Details" class="mt-4">
    <template v-if="!editing">
      <ul class="list-group list-group-flush small">
        <li class="list-group-item d-flex justify-content-between px-0">
          <span class="text-muted">Birthday</span>
          <span class="fw-medium">{{ birthDate ? formatDate(birthDate) : '-' }}</span>
        </li>
        <li class="list-group-item d-flex justify-content-between gap-3 px-0">
          <span class="text-muted text-nowrap">Address</span>
          <span class="fw-medium text-end">{{ address || '-' }}</span>
        </li>
      </ul>
      <AppButton variant="outline-primary" size="sm" class="mt-2" @click="startEdit">
        <i class="bi bi-pencil me-1" aria-hidden="true"></i>Edit
      </AppButton>
    </template>

    <form v-else @submit.prevent="save">
      <div class="mb-2">
        <label class="form-label small fw-semibold" :for="`${uid}-bday`">Birthday</label>
        <input :id="`${uid}-bday`" v-model="form.birth_date" type="date" class="form-control form-control-sm" :max="today" />
      </div>
      <div class="mb-3">
        <label class="form-label small fw-semibold" :for="`${uid}-addr`">Address</label>
        <textarea :id="`${uid}-addr`" v-model="form.address" rows="2" maxlength="500" class="form-control form-control-sm"></textarea>
      </div>
      <div class="d-flex gap-2">
        <AppButton type="submit" variant="primary" size="sm" :loading="saving">Save</AppButton>
        <AppButton variant="outline-secondary" size="sm" :disabled="saving" @click="editing = false">Cancel</AppButton>
      </div>
    </form>
  </AppCard>
</template>

<script setup>
import { ref } from 'vue'
import { useNotificationStore } from '@/stores/notification'
import AppCard from '@/components/ui/AppCard.vue'
import AppButton from '@/components/ui/AppButton.vue'

/**
 * Birthday + address — kept locally because HRIS doesn't send them.
 * `submit` performs the request (member self-service or admin endpoint).
 */
const props = defineProps({
  birthDate: { type: String, default: null },
  address: { type: String, default: null },
  submit: { type: Function, required: true },
})
const emit = defineEmits(['saved'])

const notify = useNotificationStore()
const uid = `pd-${Math.random().toString(36).slice(2, 8)}`
const today = new Date().toISOString().slice(0, 10)

const editing = ref(false)
const saving = ref(false)
const form = ref({ birth_date: '', address: '' })

function startEdit() {
  form.value = { birth_date: props.birthDate || '', address: props.address || '' }
  editing.value = true
}

async function save() {
  saving.value = true
  try {
    const payload = { birth_date: form.value.birth_date || null, address: form.value.address?.trim() || null }
    const { data: res } = await props.submit(payload)
    notify.success(res.message || 'Personal details saved.')
    emit('saved', res.data ?? payload)
    editing.value = false
  } catch (e) {
    notify.error(e.response?.data?.message || 'Failed to save personal details.')
  } finally {
    saving.value = false
  }
}

function formatDate(d) {
  return new Date(d + 'T00:00:00').toLocaleDateString('en-PH', { month: 'long', day: 'numeric', year: 'numeric' })
}
</script>
