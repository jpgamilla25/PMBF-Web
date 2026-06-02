<template>
  <AppLayout>
    <div class="d-flex align-items-center justify-content-between mb-4">
      <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-clock-history me-2"></i>Config Change History</h4>
        <p class="text-muted small mb-0">All admin configuration and role changes</p>
      </div>
      <router-link to="/admin/config" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back to Config
      </router-link>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
      <div class="card-body">
        <div class="row g-2 align-items-end">
          <div class="col-md-3">
            <label class="form-label small fw-medium mb-1">Employment Type</label>
            <select v-model="filters.employment_type" class="form-select form-select-sm" @change="fetchLogs">
              <option value="all">All Types</option>
              <option value="Contract of Service">Contract of Service</option>
              <option value="Permanent">Permanent</option>
              <option value="Non-Member">Non-Member</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label small fw-medium mb-1">Action</label>
            <select v-model="filters.action" class="form-select form-select-sm" @change="fetchLogs">
              <option value="">All Actions</option>
              <option value="config_updated">Config Updated</option>
              <option value="beginning_balance_updated">Beginning Balance</option>
              <option value="role_assigned">Role Assigned</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label small fw-medium mb-1">From</label>
            <input v-model="filters.from" type="date" class="form-control form-control-sm" @change="fetchLogs" />
          </div>
          <div class="col-md-2">
            <label class="form-label small fw-medium mb-1">To</label>
            <input v-model="filters.to" type="date" class="form-control form-control-sm" @change="fetchLogs" />
          </div>
          <div class="col-md-3">
            <label class="form-label small fw-medium mb-1">Search</label>
            <div class="input-group input-group-sm">
              <input v-model="filters.search" type="text" class="form-control" placeholder="Key or description..." @keyup.enter="fetchLogs" />
              <button class="btn btn-primary btn-sm" @click="fetchLogs"><i class="bi bi-search"></i></button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <AppLoading :loading="loading" text="Loading activity logs..." />

    <template v-if="!loading">
      <div v-if="logs.length === 0" class="text-center text-muted py-5">
        <i class="bi bi-clock-history fs-1 d-block mb-2 opacity-25"></i>
        No activity logs found.
      </div>

      <div v-else class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
          <span class="small fw-medium text-muted">{{ meta.total ?? logs.length }} record(s)</span>
        </div>
        <div class="table-responsive">
          <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th class="ps-3" style="width:150px">Date & Time</th>
                <th style="width:130px">Admin</th>
                <th style="width:120px">Action</th>
                <th style="width:110px">Type</th>
                <th>Subject / Description</th>
                <th style="width:160px">Old Value</th>
                <th style="width:160px">New Value</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="log in logs" :key="log.id">
                <td class="ps-3 text-muted small">{{ formatDate(log.created_at) }}</td>
                <td class="small">
                  <div class="fw-medium">{{ adminName(log.admin) }}</div>
                  <div v-if="log.admin" class="text-muted" style="font-size:0.7rem">{{ log.admin.employee_id }}</div>
                </td>
                <td>
                  <span :class="actionBadge(log.action)" class="badge">{{ actionLabel(log.action) }}</span>
                </td>
                <td>
                  <span v-if="log.employment_type" :class="typeBadge(log.employment_type)" class="badge">
                    {{ log.employment_type }}
                  </span>
                  <span v-else class="text-muted small">—</span>
                </td>
                <td>
                  <div class="fw-medium small font-monospace">{{ log.subject }}</div>
                  <div v-if="log.description" class="text-muted" style="font-size:0.72rem">{{ log.description }}</div>
                </td>
                <td>
                  <span class="badge bg-light text-danger border border-danger-subtle font-monospace small">
                    {{ displayValue(log.old_value, log.action) }}
                  </span>
                </td>
                <td>
                  <span class="badge bg-light text-success border border-success-subtle font-monospace small">
                    {{ displayValue(log.new_value, log.action) }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="meta.last_page > 1" class="card-footer d-flex align-items-center justify-content-between">
          <span class="text-muted small">Page {{ meta.current_page }} of {{ meta.last_page }}</span>
          <div>
            <button class="btn btn-outline-secondary btn-sm me-1" :disabled="meta.current_page <= 1" @click="goPage(meta.current_page - 1)">
              <i class="bi bi-chevron-left"></i>
            </button>
            <button class="btn btn-outline-secondary btn-sm" :disabled="meta.current_page >= meta.last_page" @click="goPage(meta.current_page + 1)">
              <i class="bi bi-chevron-right"></i>
            </button>
          </div>
        </div>
      </div>
    </template>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useLoading } from '@/composables/useLoading'
import admin from '@/services/admin'
import AppLayout from '@/components/layout/AppLayout.vue'
import AppLoading from '@/components/ui/AppLoading.vue'

const { loading, withLoading } = useLoading()

const logs = ref([])
const meta = ref({})

const filters = reactive({
  employment_type: 'all',
  action: '',
  from: '',
  to: '',
  search: '',
})

let currentPage = 1

async function fetchLogs(page = 1) {
  currentPage = page
  await withLoading(async () => {
    const params = { ...filters, page, per_page: 20 }
    if (params.employment_type === 'all') delete params.employment_type
    Object.keys(params).forEach(k => { if (params[k] === '') delete params[k] })

    const { data } = await admin.getActivityLogs(params)
    const res = data.data ?? data
    logs.value = res.data ?? res
    meta.value = res.meta ?? {
      total: res.total,
      current_page: res.current_page,
      last_page: res.last_page,
    }
  })
}

function goPage(page) { fetchLogs(page) }

function formatDate(dt) {
  if (!dt) return '—'
  return new Date(dt).toLocaleString('en-PH', {
    month: 'short', day: '2-digit', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  })
}

function adminName(a) {
  if (!a) return 'System'
  return `${a.last_name ?? ''}, ${a.first_name ?? ''}`.trim().replace(/^,\s*/, '')
}

function actionLabel(action) {
  const map = {
    config_updated: 'Config Updated',
    beginning_balance_updated: 'Balance Updated',
    role_assigned: 'Role Assigned',
  }
  return map[action] ?? action
}

function actionBadge(action) {
  const map = {
    config_updated: 'bg-primary',
    beginning_balance_updated: 'bg-warning text-dark',
    role_assigned: 'bg-info text-dark',
  }
  return map[action] ?? 'bg-secondary'
}

function typeBadge(type) {
  const map = {
    SC: 'bg-success',
    Permanent: 'bg-primary',
    'Non-Member': 'bg-secondary',
  }
  return map[type] ?? 'bg-light text-dark'
}

function displayValue(val, action) {
  if (val === null || val === undefined || val === '') return '(none)'
  if (action === 'config_updated') {
    // Show boolean as Yes/No
    if (val === '1') return 'Yes'
    if (val === '0') return 'No'
  }
  return String(val).length > 30 ? String(val).slice(0, 30) + '…' : String(val)
}

onMounted(() => fetchLogs())
</script>
