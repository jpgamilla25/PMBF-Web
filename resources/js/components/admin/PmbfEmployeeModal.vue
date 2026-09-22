<template>
  <AppModal
    :show="show"
    :title="modalTitle"
    size="lg"
    :dismissable="false"
    @close="requestClose"
  >
    <!-- ── Success: the issued member ID, kept on screen ── -->
    <div v-if="created">
      <div class="text-center py-2">
        <i class="bi bi-check-circle-fill text-success fs-1" aria-hidden="true"></i>
        <p class="fw-semibold mt-2 mb-1">{{ created.full_name }} was added.</p>
        <p class="text-muted small mb-3">
          Give them this member ID &mdash; they sign in with it, using a one-time code sent to
          {{ created.email }}.
        </p>
      </div>

      <label for="created-member-id" class="form-label small fw-semibold">Member ID</label>
      <div class="input-group mb-3">
        <input
          id="created-member-id"
          class="form-control font-monospace fw-bold"
          :value="created.employee_id"
          readonly
          @focus="$event.target.select()"
        />
        <AppButton variant="outline-secondary" @click="copyMemberId">
          <i class="bi me-1" :class="copied ? 'bi-check-lg' : 'bi-clipboard'" aria-hidden="true"></i>
          {{ copied ? 'Copied' : 'Copy' }}
        </AppButton>
      </div>
      <p aria-live="polite" class="visually-hidden">{{ copied ? 'Member ID copied to clipboard' : '' }}</p>

      <!-- Creating inside a narrowed context would otherwise look like the
           record vanished on save. -->
      <div v-if="hiddenByContext" class="alert alert-warning small mb-0 d-flex gap-2">
        <i class="bi bi-eye-slash mt-1" aria-hidden="true"></i>
        <div>
          You are viewing <strong>{{ contextType }}</strong>, so this member is not in the list
          behind this dialog.
          <button
            type="button"
            class="btn btn-link btn-sm p-0 align-baseline"
            @click="$emit('switch-context')"
          >
            Switch to PMBF Employee
          </button>
        </div>
      </div>
    </div>

    <!-- ── Create / edit form ── -->
    <form v-else :id="FORM_ID" ref="formEl" novalidate @submit.prevent="submitForm">
      <div v-if="!isEdit" class="alert alert-info small d-flex gap-2">
        <i class="bi bi-info-circle mt-1" aria-hidden="true"></i>
        <div>
          For <strong>staff employed by PMBF itself</strong>, who have no PhilRice HRIS record.
          The member ID is issued automatically, and they sign in with that ID using a one-time
          code sent to the email address below &mdash; so it must be one they can reach.
        </div>
      </div>
      <div v-else class="alert alert-secondary small d-flex gap-2">
        <i class="bi bi-person-vcard mt-1" aria-hidden="true"></i>
        <div>
          Editing <strong class="font-monospace">{{ member.employee_id }}</strong>. The member ID
          cannot change. Changing the email changes where their sign-in code is sent.
        </div>
      </div>

      <div class="row g-0 gx-3">
        <div class="col-md-4">
          <AppInput
            v-model="form.first_name"
            label="First Name"
            autocomplete="given-name"
            :error="errors.first_name"
            required
          />
        </div>
        <div class="col-md-3">
          <AppInput
            v-model="form.middle_name"
            label="Middle Name"
            autocomplete="additional-name"
            :error="errors.middle_name"
          />
        </div>
        <div class="col-md-3">
          <AppInput
            v-model="form.last_name"
            label="Last Name"
            autocomplete="family-name"
            :error="errors.last_name"
            required
          />
        </div>
        <div class="col-md-2">
          <AppInput
            v-model="form.suffix"
            label="Suffix"
            placeholder="Jr."
            autocomplete="honorific-suffix"
            :error="errors.suffix"
          />
        </div>

        <div class="col-md-6">
          <AppInput
            v-model="form.email"
            type="email"
            label="Email Address"
            placeholder="member@example.com"
            autocomplete="email"
            hint="Where their sign-in code is sent."
            :error="errors.email"
            required
          />
        </div>
        <div class="col-md-6">
          <AppInput
            v-model="form.mobile"
            type="tel"
            label="Mobile Number"
            placeholder="09xxxxxxxxx"
            autocomplete="tel"
            inputmode="tel"
            :error="errors.mobile"
          />
        </div>

        <div class="col-md-6">
          <AppInput
            v-model="form.position"
            label="Position"
            placeholder="e.g. Bookkeeper"
            autocomplete="organization-title"
            :error="errors.position"
          />
        </div>
        <div class="col-md-6">
          <AppInput
            v-model="form.department"
            label="Department"
            placeholder="e.g. PMBF Secretariat"
            autocomplete="organization"
            :error="errors.department"
          />
        </div>

        <div class="col-md-6">
          <AppInput
            v-model="form.status"
            type="select"
            label="Status"
            :options="statusOptions"
            :error="errors.status"
          />
        </div>
      </div>
    </form>

    <template #footer>
      <template v-if="created">
        <AppButton variant="outline-secondary" @click="startAnother">
          <i class="bi bi-person-plus me-1" aria-hidden="true"></i>Create another
        </AppButton>
        <AppButton variant="primary" @click="$emit('close')">Done</AppButton>
      </template>
      <template v-else>
        <AppButton variant="secondary" :disabled="processing" @click="requestClose">Cancel</AppButton>
        <AppButton type="submit" :form="FORM_ID" variant="primary" :loading="processing">
          <i class="bi bi-check-lg me-1" aria-hidden="true"></i>
          {{ isEdit ? 'Save Changes' : 'Create Member' }}
        </AppButton>
      </template>
    </template>
  </AppModal>
</template>

<script setup>
/**
 * Create or correct a PMBF Employee.
 *
 * Shared by the members list and the member detail view so the form, its
 * validation wiring and the post-create success panel exist once.
 */
import { ref, computed, nextTick, watch } from 'vue'
import { useForm } from '@/composables/useForm'
import { useConfirm } from '@/composables/useConfirm'
import { useNotificationStore } from '@/stores/notification'
import admin from '@/services/admin'
import AppModal from '@/components/ui/AppModal.vue'
import AppInput from '@/components/ui/AppInput.vue'
import AppButton from '@/components/ui/AppButton.vue'

const PMBF_EMPLOYEE = 'PMBF Employee'
const FORM_ID = 'pmbf-employee-form'

const props = defineProps({
  show: {
    type: Boolean,
    default: false,
  },
  /** The member being corrected; null or empty opens the create form. */
  member: {
    type: Object,
    default: null,
  },
  /** Active sidebar member-type context, if one is narrowing the list behind. */
  contextType: {
    type: String,
    default: null,
  },
})

const emit = defineEmits(['close', 'saved', 'switch-context'])

const notify = useNotificationStore()
const { confirm } = useConfirm()

const BLANK = {
  first_name: '',
  middle_name: '',
  last_name: '',
  suffix: '',
  email: '',
  mobile: '',
  position: '',
  department: '',
  status: 'active',
}

const statusOptions = [
  { value: 'active', label: 'Active - can sign in immediately' },
  { value: 'pending', label: 'Pending - record only, cannot sign in' },
  { value: 'inactive', label: 'Inactive - sign-in blocked' },
]

const { form, errors, reset, clearErrors, processing, submit } = useForm({ ...BLANK })

const created = ref(null)
const copied = ref(false)
const formEl = ref(null)

const isEdit = computed(() => !!props.member?.id)

const modalTitle = computed(() => {
  if (created.value) return 'Member created'
  return isEdit.value ? 'Edit PMBF Employee' : 'New PMBF Employee'
})

const hiddenByContext = computed(
  () => props.contextType && props.contextType !== PMBF_EMPLOYEE
)

function fillForm(values) {
  Object.keys(BLANK).forEach((key) => {
    form[key] = values?.[key] ?? BLANK[key]
  })
  clearErrors()
}

// Re-seed whenever the dialog opens, so a reopened form never shows the
// previous member's details.
watch(
  () => [props.show, props.member],
  () => {
    if (!props.show) return
    reset()
    fillForm(props.member ?? BLANK)
    created.value = null
    copied.value = false
  },
  { immediate: true, deep: true }
)

/** Anything the user changed from what the form opened with. */
function isDirty() {
  const baseline = isEdit.value ? props.member : BLANK

  return Object.keys(BLANK).some((key) => (form[key] ?? '') !== (baseline?.[key] ?? BLANK[key]))
}

async function requestClose() {
  if (processing.value) return

  if (!created.value && isDirty()) {
    const discard = await confirm(
      isEdit.value
        ? 'Discard your changes to this member?'
        : 'Discard this new member? Nothing has been saved yet.',
      { title: 'Discard changes', confirmLabel: 'Discard', variant: 'danger' }
    )
    if (!discard) return
  }

  emit('close')
}

/** Back to an empty create form without leaving the dialog. */
function startAnother() {
  reset()
  fillForm(BLANK)
  created.value = null
  copied.value = false
}

function submitForm() {
  return isEdit.value ? submitEdit() : submitCreate()
}

/** Put the user on the first field the server rejected. */
async function focusFirstError() {
  await nextTick()
  formEl.value?.querySelector('[aria-invalid="true"]')?.focus()
}

async function submitCreate() {
  try {
    const { data } = await submit((payload) => admin.createMember(payload))
    created.value = data.data ?? data
    copied.value = false
    emit('saved', { member: created.value, isNew: true })
  } catch (e) {
    if (e.response?.status === 422) {
      focusFirstError()
    } else {
      notify.error(e.response?.data?.message || 'Failed to create the member.')
    }
  }
}

async function submitEdit() {
  try {
    const { data } = await submit((payload) => admin.updateMember(props.member.id, payload))
    const updated = data.data ?? data
    notify.success(`${updated.full_name} updated.`)
    emit('saved', { member: updated, isNew: false })
    emit('close')
  } catch (e) {
    if (e.response?.status === 422) {
      focusFirstError()
      // A 422 with no field errors is a rule the form cannot show inline.
      if (!Object.keys(errors).length) {
        notify.error(e.response?.data?.message || 'That change could not be saved.')
      }
    } else {
      notify.error(e.response?.data?.message || 'Failed to update the member.')
    }
  }
}

async function copyMemberId() {
  const id = created.value.employee_id

  try {
    await navigator.clipboard.writeText(id)
    copied.value = true
    setTimeout(() => (copied.value = false), 2500)
  } catch {
    // Clipboard access can be refused; the field is selectable either way.
    notify.info(`Copy it manually: ${id}`)
  }
}
</script>
