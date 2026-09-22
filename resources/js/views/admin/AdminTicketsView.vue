<template>
  <AppLayout>
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
      <div>
        <h4 class="fw-bold mb-0">Support Tickets</h4>
        <p class="text-muted small mb-0">Concerns raised by members through the PMBF Assistant.</p>
      </div>
      <div class="d-flex gap-2">
        <div class="stat-chip stat-open">{{ counts.open }} open</div>
        <div class="stat-chip stat-resolved">{{ counts.resolved }} resolved</div>
      </div>
    </div>

    <!-- Filters -->
    <AppCard class="mb-3">
      <div class="row g-2 align-items-end">
        <div class="col-md-4">
          <label class="form-label small fw-semibold mb-1">Search</label>
          <input
            v-model="filters.search"
            type="search"
            class="form-control form-control-sm"
            placeholder="Ticket no., name, employee ID, email or text..."
            @keyup.enter="applyFilters"
          />
        </div>
        <div class="col-md-2">
          <label class="form-label small fw-semibold mb-1">Status</label>
          <select v-model="filters.status" class="form-select form-select-sm" @change="applyFilters">
            <option value="">All</option>
            <option value="open">Open</option>
            <option value="resolved">Resolved</option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label small fw-semibold mb-1">From</label>
          <input v-model="filters.from" type="date" class="form-control form-control-sm" @change="applyFilters" />
        </div>
        <div class="col-md-2">
          <label class="form-label small fw-semibold mb-1">To</label>
          <input v-model="filters.to" type="date" class="form-control form-control-sm" @change="applyFilters" />
        </div>
        <div class="col-md-2 d-flex gap-2">
          <AppButton variant="primary" size="sm" class="w-100" @click="applyFilters">
            <i class="bi bi-search me-1"></i>Filter
          </AppButton>
          <AppButton variant="outline-secondary" size="sm" @click="resetFilters">Clear</AppButton>
        </div>
      </div>
    </AppCard>

    <!-- Bulk bar — only while something is selected, so it never nags. -->
    <div v-if="selected.length > 0" class="bulk-bar mb-3">
      <span class="fw-semibold">{{ selected.length }} selected</span>
      <div class="d-flex gap-2">
        <AppButton variant="success" size="sm" @click="openResolve(null)">
          <i class="bi bi-check2-all me-1"></i>Resolve selected
        </AppButton>
        <AppButton variant="outline-secondary" size="sm" @click="selected = []">Clear selection</AppButton>
      </div>
    </div>

    <AppCard>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width: 36px">
                <input
                  class="form-check-input"
                  type="checkbox"
                  :checked="allOpenSelected"
                  :disabled="selectableIds.length === 0"
                  @change="toggleAll"
                />
              </th>
              <th style="width: 130px">Ticket</th>
              <th>Member</th>
              <th>Concern</th>
              <th style="width: 150px">Filed</th>
              <th style="width: 110px">Status</th>
              <th style="width: 150px"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="7" class="text-center text-muted py-4">Loading...</td>
            </tr>
            <tr v-else-if="tickets.length === 0">
              <td colspan="7" class="text-center text-muted py-4">No tickets match these filters.</td>
            </tr>
            <tr v-for="ticket in tickets" v-else :key="ticket.id">
              <td>
                <input
                  class="form-check-input"
                  type="checkbox"
                  :value="ticket.id"
                  :disabled="ticket.status === 'resolved'"
                  :checked="selected.includes(ticket.id)"
                  @change="toggleOne(ticket.id)"
                />
              </td>
              <td>
                <code class="fw-semibold">{{ ticket.ticket_number }}</code>
              </td>
              <td>
                <div class="fw-semibold small">{{ ticket.member_name }}</div>
                <div class="text-muted small">
                  <code>{{ ticket.employee_id }}</code>
                  <span v-if="!ticket.is_member" class="badge bg-warning text-dark ms-1">unmatched</span>
                </div>
                <div class="text-muted small">{{ ticket.email }}</div>
              </td>
              <td>
                <div class="fw-semibold small">{{ ticket.subject }}</div>
                <div class="text-muted small ticket-excerpt">{{ ticket.message }}</div>
                <div v-if="ticket.resolution_notes" class="small text-success mt-1">
                  <i class="bi bi-chat-left-text me-1"></i>{{ ticket.resolution_notes }}
                </div>
              </td>
              <td class="small">{{ formatDate(ticket.created_at) }}</td>
              <td>
                <span v-if="ticket.status === 'open'" class="badge bg-primary">Open</span>
                <template v-else>
                  <span class="badge bg-success">Resolved</span>
                  <!-- A resolved ticket whose email bounced is not really
                       finished, so it is called out rather than hidden. -->
                  <div v-if="!ticket.resolution_emailed" class="small text-danger mt-1">
                    <i class="bi bi-exclamation-triangle me-1"></i>email failed
                  </div>
                  <div v-if="ticket.resolved_by" class="text-muted small mt-1">
                    by {{ ticket.resolved_by }}
                  </div>
                </template>
              </td>
              <td class="text-end">
                <AppButton
                  v-if="ticket.status === 'open'"
                  variant="success"
                  size="sm"
                  @click="openResolve(ticket)"
                >
                  Resolve
                </AppButton>
                <AppButton v-else variant="outline-secondary" size="sm" @click="reopen(ticket)">
                  Reopen
                </AppButton>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <AppPagination v-if="meta.last_page > 1" :meta="meta" class="mt-3" @page-change="load" />
    </AppCard>

    <!-- Resolve modal -->
    <AppModal :show="resolveOpen" :title="resolveTitle" @close="resolveOpen = false">
      <p class="small text-muted">
        The member will be emailed that this is resolved. Anything you write below is included in that email.
      </p>

      <label class="form-label fw-semibold small">Resolution notes <span class="text-muted">(optional)</span></label>
      <textarea
        v-model.trim="resolveNotes"
        class="form-control"
        rows="4"
        maxlength="2000"
        placeholder="What was done about it?"
      ></textarea>

      <div v-if="resolveTargets.length > 1" class="alert alert-warning small mt-3 mb-0">
        <i class="bi bi-info-circle me-1"></i>
        These notes will be sent to all {{ resolveTargets.length }} selected members.
      </div>

      <template #footer>
        <AppButton variant="secondary" @click="resolveOpen = false">Cancel</AppButton>
        <AppButton variant="success" :loading="resolving" @click="confirmResolve">
          <i class="bi bi-check2-circle me-1"></i>Resolve &amp; email
        </AppButton>
      </template>
    </AppModal>
  </AppLayout>
</template>

<script setup>
/**
 * Admin view of tickets filed from the chat assistant.
 *
 * Resolving always goes through one endpoint, single or bulk, so the notes
 * and the member's email cannot drift apart between the two paths.
 */
import { computed, onMounted, reactive, ref } from 'vue'
import admin from '@/services/admin'
import { useNotificationStore } from '@/stores/notification'
import { useAuthStore } from '@/stores/auth'
import AppLayout from '@/components/layout/AppLayout.vue'
import AppCard from '@/components/ui/AppCard.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppModal from '@/components/ui/AppModal.vue'
import AppPagination from '@/components/ui/AppPagination.vue'

const notification = useNotificationStore()
const authStore = useAuthStore()

const tickets = ref([])
const counts = ref({ open: 0, resolved: 0 })
const meta = ref({ current_page: 1, last_page: 1 })
const loading = ref(false)
const selected = ref([])

const filters = reactive({ search: '', status: 'open', from: '', to: '' })

const resolveOpen = ref(false)
const resolving = ref(false)
const resolveNotes = ref('')
const resolveTargets = ref([])

const selectableIds = computed(() => tickets.value.filter((t) => t.status === 'open').map((t) => t.id))

const allOpenSelected = computed(
  () => selectableIds.value.length > 0 && selectableIds.value.every((id) => selected.value.includes(id))
)

const resolveTitle = computed(() =>
  resolveTargets.value.length > 1 ? `Resolve ${resolveTargets.value.length} tickets` : 'Resolve ticket'
)

onMounted(() => load(1))

async function load(page = 1) {
  loading.value = true
  try {
    const { data } = await admin.getTickets({ ...filters, page })
    tickets.value = data.data.tickets.data ?? []
    meta.value = {
      current_page: data.data.tickets.current_page ?? 1,
      last_page: data.data.tickets.last_page ?? 1,
    }
    counts.value = data.data.counts ?? { open: 0, resolved: 0 }
    // Keep the sidebar badge honest without a second request.
    authStore.openTicketCount = counts.value.open
    // Drop selections that are no longer on screen, so a bulk resolve can
    // never hit a ticket the admin can't see.
    selected.value = selected.value.filter((id) => selectableIds.value.includes(id))
  } catch {
    notification.error('Could not load tickets.')
  } finally {
    loading.value = false
  }
}

function applyFilters() {
  load(1)
}

function resetFilters() {
  filters.search = ''
  filters.status = ''
  filters.from = ''
  filters.to = ''
  load(1)
}

function toggleOne(id) {
  selected.value = selected.value.includes(id)
    ? selected.value.filter((x) => x !== id)
    : [...selected.value, id]
}

function toggleAll(event) {
  selected.value = event.target.checked ? [...selectableIds.value] : []
}

/** Pass a ticket to resolve one; pass null to resolve the selection. */
function openResolve(ticket) {
  resolveTargets.value = ticket ? [ticket.id] : [...selected.value]
  resolveNotes.value = ''
  resolveOpen.value = true
}

async function confirmResolve() {
  if (resolveTargets.value.length === 0) return

  resolving.value = true
  try {
    const { data } = await admin.resolveTickets(resolveTargets.value, resolveNotes.value)
    notification.success(data.message)
    resolveOpen.value = false
    selected.value = []
    await load(meta.value.current_page)
  } catch (error) {
    notification.error(error.response?.data?.message || 'Could not resolve those tickets.')
  } finally {
    resolving.value = false
  }
}

async function reopen(ticket) {
  try {
    const { data } = await admin.reopenTicket(ticket.id)
    notification.success(data.message)
    await load(meta.value.current_page)
  } catch (error) {
    notification.error(error.response?.data?.message || 'Could not reopen that ticket.')
  }
}

function formatDate(value) {
  return value
    ? new Date(value).toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' })
    : '—'
}
</script>

<style scoped>
.stat-chip {
  display: inline-flex;
  align-items: center;
  padding: 0.4rem 0.75rem;
  border-radius: 8px;
  font-size: 0.85rem;
  font-weight: 600;
}

.stat-open {
  background: #dbeafe;
  color: #1e40af;
}

.stat-resolved {
  background: #dcfce7;
  color: #166534;
}

.bulk-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 0.6rem 1rem;
  border-radius: 8px;
  background: #eff6ff;
  border: 1px solid #bfdbfe;
}

/* Keep long concerns from stretching the row; the full text is in the email
   and the resolve modal. */
.ticket-excerpt {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
