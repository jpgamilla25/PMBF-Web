<template>
  <AppLayout>
    <div class="d-flex align-items-center justify-content-between mb-4">
      <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-journal-text me-2"></i>Audit Trail</h4>
        <p class="text-muted small mb-0">Every recorded administrative action, filterable and shareable.</p>
      </div>
      <button class="btn btn-outline-secondary btn-sm" @click="resetFilters">
        <i class="bi bi-arrow-counterclockwise me-1"></i>Reset filters
      </button>
    </div>

    <AppCard class="mb-4">
      <div class="row g-2 align-items-end">
        <div class="col-md-3">
          <label class="form-label small fw-medium mb-1">User</label>
          <select v-model="form.admin_id" class="form-select form-select-sm" @change="applyFilters">
            <option value="">All users</option>
            <option v-for="a in options.admins" :key="a.id" :value="String(a.id)">
              {{ a.name }}<template v-if="a.employee_id"> ({{ a.employee_id }})</template>
            </option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label small fw-medium mb-1">Action</label>
          <select v-model="form.action" class="form-select form-select-sm" @change="applyFilters">
            <option value="">All actions</option>
            <option v-for="a in options.actions" :key="a" :value="a">{{ actionLabel(a) }}</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label small fw-medium mb-1">Target</label>
          <input
            v-model="form.subject"
            list="activity-log-subjects"
            type="text"
            class="form-control form-control-sm"
            placeholder="Config key, employee ID..."
            @change="applyFilters"
          />
          <datalist id="activity-log-subjects">
            <option v-for="s in options.subjects" :key="s" :value="s"></option>
          </datalist>
        </div>
        <div class="col-md-2">
          <label class="form-label small fw-medium mb-1">From</label>
          <input v-model="form.from" type="date" class="form-control form-control-sm" @change="applyFilters" />
        </div>
        <div class="col-md-2">
          <label class="form-label small fw-medium mb-1">To</label>
          <input v-model="form.to" type="date" class="form-control form-control-sm" @change="applyFilters" />
        </div>
        <div class="col-md-4">
          <label class="form-label small fw-medium mb-1">Search</label>
          <div class="input-group input-group-sm">
            <input
              v-model="form.search"
              type="text"
              class="form-control"
              placeholder="Subject or description..."
              @keyup.enter="applyFilters"
            />
            <button class="btn btn-primary" @click="applyFilters"><i class="bi bi-search"></i></button>
          </div>
        </div>
        <div class="col-md-3">
          <label class="form-label small fw-medium mb-1">Employment Type</label>
          <select v-model="form.employment_type" class="form-select form-select-sm" @change="applyFilters">
            <option value="">All types</option>
            <option value="Permanent">Permanent</option>
            <option value="Contract of Service">Contract of Service</option>
            <option value="Non-Member">Non-Member</option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label small fw-medium mb-1">Per page</label>
          <select v-model="form.per_page" class="form-select form-select-sm" @change="applyFilters">
            <option value="20">20</option>
            <option value="50">50</option>
            <option value="100">100</option>
          </select>
        </div>
      </div>
    </AppCard>

    <AppCard :padding="false">
      <AppTable
        :columns="columns"
        :items="items"
        :loading="loading"
        empty-text="No activity logs match these filters."
      >
        <template #cell(created_at)="{ value }">
          <span class="text-muted small">{{ formatDate(value) }}</span>
        </template>
        <template #cell(admin)="{ item }">
          <div class="fw-medium small">{{ adminName(item.admin) }}</div>
          <div v-if="item.admin?.employee_id" class="text-muted" style="font-size: 0.72rem;">
            {{ item.admin.employee_id }}
          </div>
        </template>
        <template #cell(action)="{ value }">
          <AppBadge :variant="actionVariant(value)" :text="actionLabel(value)" />
        </template>
        <template #cell(subject)="{ item }">
          <div class="fw-medium small font-monospace">{{ item.subject || '—' }}</div>
          <div v-if="item.description" class="text-muted" style="font-size: 0.72rem;">
            {{ item.description }}
          </div>
        </template>
        <template #cell(ip_address)="{ value }">
          <span class="font-monospace small text-muted">{{ value || '—' }}</span>
        </template>
        <template #cell(details)="{ item }">
          <button
            v-if="hasPayload(item)"
            class="btn btn-sm btn-outline-primary"
            @click="openDetail(item)"
          >
            <i class="bi bi-eye me-1"></i>Details
          </button>
          <span v-else class="text-muted small">—</span>
        </template>
      </AppTable>

      <template #footer>
        <div class="px-3">
          <AppPagination
            :meta="{ current_page: meta.currentPage, last_page: meta.lastPage, total: meta.total, per_page: meta.perPage }"
            @page-change="goPage"
          />
        </div>
      </template>
    </AppCard>

    <!-- ═══ Detail Modal ═══ -->
    <AppModal :show="!!activeLog" title="Activity Detail" size="lg" @close="activeLog = null">
      <template v-if="activeLog">
        <div class="row g-3 mb-3">
          <div class="col-sm-6">
            <div class="text-muted small">Timestamp</div>
            <div class="fw-semibold">{{ formatDate(activeLog.created_at) }}</div>
          </div>
          <div class="col-sm-6">
            <div class="text-muted small">User</div>
            <div class="fw-semibold">{{ adminName(activeLog.admin) }}</div>
          </div>
          <div class="col-sm-6">
            <div class="text-muted small">Action</div>
            <div><AppBadge :variant="actionVariant(activeLog.action)" :text="actionLabel(activeLog.action)" /></div>
          </div>
          <div class="col-sm-6">
            <div class="text-muted small">IP Address</div>
            <div class="fw-semibold font-monospace">{{ activeLog.ip_address || '—' }}</div>
          </div>
          <div class="col-sm-6">
            <div class="text-muted small">Target</div>
            <div class="fw-semibold font-monospace">{{ activeLog.subject || '—' }}</div>
          </div>
          <div class="col-sm-6">
            <div class="text-muted small">Employment Context</div>
            <div class="fw-semibold">{{ activeLog.employment_type || '—' }}</div>
          </div>
          <div v-if="activeLog.description" class="col-12">
            <div class="text-muted small">Description</div>
            <div class="fw-semibold">{{ activeLog.description }}</div>
          </div>
        </div>

        <div class="row g-3">
          <div class="col-sm-6">
            <div class="text-muted small mb-1">Old Value</div>
            <pre class="bg-light border rounded p-2 small mb-0 audit-pre">{{ display(activeLog.old_value) }}</pre>
          </div>
          <div class="col-sm-6">
            <div class="text-muted small mb-1">New Value</div>
            <pre class="bg-light border rounded p-2 small mb-0 audit-pre">{{ display(activeLog.new_value) }}</pre>
          </div>
          <div v-if="activeLog.meta" class="col-12">
            <div class="text-muted small mb-1">Meta</div>
            <pre class="bg-light border rounded p-2 small mb-0 audit-pre">{{ JSON.stringify(activeLog.meta, null, 2) }}</pre>
          </div>
        </div>
      </template>
      <template #footer>
        <button class="btn btn-secondary" @click="activeLog = null">Close</button>
      </template>
    </AppModal>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { usePagination } from '@/composables/usePagination'
import admin from '@/services/admin'
import AppLayout from '@/components/layout/AppLayout.vue'
import AppCard from '@/components/ui/AppCard.vue'
import AppTable from '@/components/ui/AppTable.vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import AppModal from '@/components/ui/AppModal.vue'
import AppPagination from '@/components/ui/AppPagination.vue'

const route = useRoute()
const router = useRouter()

/**
 * The endpoint returns a raw Laravel paginator inside `data`. Normalise it to
 * the { data, meta } shape usePagination expects.
 */
async function fetchLogs(params) {
  const response = await admin.getActivityLogs(params)
  const page = response.data.data ?? response.data

  return {
    data: {
      data: page.data ?? [],
      meta: {
        current_page: page.current_page ?? 1,
        last_page: page.last_page ?? 1,
        total: page.total ?? 0,
        per_page: page.per_page ?? 20,
      },
    },
  }
}

const { items, meta, loading, filters, fetch } = usePagination(fetchLogs)

const FILTER_KEYS = ['admin_id', 'action', 'subject', 'from', 'to', 'search', 'employment_type', 'per_page']

const form = reactive({
  admin_id: '',
  action: '',
  subject: '',
  from: '',
  to: '',
  search: '',
  employment_type: '',
  per_page: '20',
})

const options = reactive({ admins: [], actions: [], subjects: [] })
const activeLog = ref(null)

const columns = [
  { key: 'created_at', label: 'Timestamp' },
  { key: 'admin', label: 'User' },
  { key: 'action', label: 'Action' },
  { key: 'subject', label: 'Target' },
  { key: 'ip_address', label: 'IP' },
  { key: 'details', label: '', class: 'text-end' },
]

function hydrateFromQuery() {
  FILTER_KEYS.forEach((key) => {
    if (route.query[key] !== undefined) form[key] = String(route.query[key])
  })
  if (!form.per_page) form.per_page = '20'
}

function syncQuery(page) {
  const query = {}
  FILTER_KEYS.forEach((key) => {
    if (form[key] !== '' && form[key] !== null && form[key] !== undefined) query[key] = form[key]
  })
  if (page > 1) query.page = String(page)
  router.replace({ query }).catch(() => {})
}

function applyFilters(page = 1) {
  FILTER_KEYS.forEach((key) => {
    filters[key] = form[key] === '' ? undefined : form[key]
  })
  syncQuery(page)
  fetch(page)
}

function goPage(page) {
  applyFilters(page)
}

function resetFilters() {
  FILTER_KEYS.forEach((key) => { form[key] = '' })
  form.per_page = '20'
  applyFilters(1)
}

function formatDate(dt) {
  if (!dt) return '—'
  return new Date(dt).toLocaleString('en-PH', {
    year: 'numeric', month: 'short', day: '2-digit',
    hour: '2-digit', minute: '2-digit',
  })
}

function adminName(a) {
  if (!a) return 'System'
  return `${a.first_name ?? ''} ${a.last_name ?? ''}`.trim() || 'System'
}

function actionLabel(action) {
  if (!action) return '—'
  return String(action).replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
}

function actionVariant(action) {
  const map = {
    config_updated: 'primary',
    beginning_balance_updated: 'warning',
    role_assigned: 'info',
  }
  return map[action] ?? 'secondary'
}

function hasPayload(log) {
  return Boolean(log.old_value || log.new_value || log.meta || log.description)
}

function openDetail(log) {
  activeLog.value = log
}

function display(val) {
  if (val === null || val === undefined || val === '') return '(none)'
  return String(val)
}

onMounted(async () => {
  hydrateFromQuery()

  try {
    const { data } = await admin.getActivityLogFilters()
    const res = data.data ?? data
    options.admins = res.admins ?? []
    options.actions = res.actions ?? []
    options.subjects = res.subjects ?? []
  } catch {
    options.admins = []
    options.actions = []
    options.subjects = []
  }

  applyFilters(Number(route.query.page) || 1)
})
</script>

<style scoped>
.audit-pre {
  white-space: pre-wrap;
  word-break: break-word;
  max-height: 220px;
  overflow: auto;
}
</style>
