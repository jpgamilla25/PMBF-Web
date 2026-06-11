<template>
  <AppLayout>
    <div class="d-flex align-items-center justify-content-between mb-4">
      <div>
        <h4 class="fw-bold mb-0">Schedule Monitor</h4>
        <div class="text-muted small">
          Server time: {{ formatTime(state.server_time) }}
        </div>
      </div>
      <AppButton variant="outline-secondary" size="sm" @click="refresh">
        <i class="bi bi-arrow-clockwise me-1"></i>Refresh
      </AppButton>
    </div>

    <AppLoading :loading="loading" text="Loading schedule..." />

    <template v-if="!loading">
      <!-- Defined tasks -->
      <AppCard title="Defined Tasks" class="mb-4" :padding="false">
        <div class="table-responsive">
          <table class="table table-hover mb-0 align-middle">
            <thead>
              <tr>
                <th>Command</th>
                <th>Cron</th>
                <th>Next Run</th>
                <th>Last Run</th>
                <th>Last Status</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!state.tasks?.length">
                <td colspan="6" class="text-center text-muted py-3">No scheduled tasks defined.</td>
              </tr>
              <tr v-for="t in state.tasks" :key="t.command">
                <td>
                  <div class="fw-semibold font-monospace">{{ t.command }}</div>
                  <div v-if="t.description && t.description !== t.command" class="text-muted small">
                    {{ t.description }}
                  </div>
                  <div v-if="t.without_overlapping" class="small text-info">
                    <i class="bi bi-shield-check me-1"></i>withoutOverlapping
                  </div>
                </td>
                <td>
                  <code class="small">{{ t.expression ?? '-' }}</code>
                </td>
                <td>{{ formatTime(t.next_run_at) }}</td>
                <td>
                  <div>{{ formatTime(t.last_run?.started_at) }}</div>
                  <div v-if="t.last_run?.duration_ms != null" class="text-muted small">
                    {{ formatDuration(t.last_run.duration_ms) }}
                  </div>
                </td>
                <td>
                  <span v-if="t.last_run" class="badge" :class="statusBadge(t.last_run.status)">
                    {{ t.last_run.status }}
                  </span>
                  <span v-else class="text-muted">never run</span>
                </td>
                <td class="text-end">
                  <AppButton
                    variant="primary"
                    size="sm"
                    :loading="runningCommand === t.command"
                    :disabled="!!runningCommand"
                    @click="runNow(t.command)"
                  >
                    <i v-if="runningCommand !== t.command" class="bi bi-play-fill me-1"></i>
                    {{ runningCommand === t.command ? 'Running…' : 'Run now' }}
                  </AppButton>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </AppCard>

      <!-- Recent history -->
      <AppCard title="Recent Runs (last 50)" :padding="false">
        <div class="table-responsive">
          <table class="table table-hover mb-0 align-middle">
            <thead>
              <tr>
                <th>Command</th>
                <th>Status</th>
                <th>Started</th>
                <th>Duration</th>
                <th>Trigger</th>
                <th class="text-end">Output</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!state.recent_runs?.length">
                <td colspan="6" class="text-center text-muted py-3">No runs recorded yet.</td>
              </tr>
              <tr v-for="run in state.recent_runs" :key="run.id">
                <td class="font-monospace small">{{ run.command }}</td>
                <td>
                  <span class="badge" :class="statusBadge(run.status)">{{ run.status }}</span>
                  <span v-if="run.exit_code != null" class="ms-1 small text-muted">exit {{ run.exit_code }}</span>
                </td>
                <td class="small">{{ formatTime(run.started_at) }}</td>
                <td class="small">{{ formatDuration(run.duration_ms) }}</td>
                <td>
                  <span v-if="run.manual" class="badge bg-secondary">manual</span>
                  <span v-else class="badge bg-light text-dark border">scheduler</span>
                </td>
                <td class="text-end">
                  <button
                    v-if="run.output_excerpt"
                    class="btn btn-sm btn-outline-secondary"
                    @click="viewLog = run"
                  >
                    <i class="bi bi-file-text"></i>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </AppCard>
    </template>

    <AppModal :show="!!viewLog" :title="`Output — ${viewLog?.command}`" size="lg" @close="viewLog = null">
      <pre class="mb-0" style="white-space: pre-wrap; max-height: 60vh; overflow: auto; font-size: 0.78rem;">{{ viewLog?.output_excerpt }}</pre>
      <template #footer>
        <AppButton variant="secondary" @click="viewLog = null">Close</AppButton>
      </template>
    </AppModal>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { useNotificationStore } from '@/stores/notification'
import scheduleService from '@/services/schedule'
import AppLayout from '@/components/layout/AppLayout.vue'
import AppCard from '@/components/ui/AppCard.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppModal from '@/components/ui/AppModal.vue'
import AppLoading from '@/components/ui/AppLoading.vue'

const notify = useNotificationStore()
const loading = ref(false)
const runningCommand = ref(null)
const state = ref({ tasks: [], recent_runs: [], server_time: null })
const viewLog = ref(null)
let pollTimer = null

async function refresh() {
  loading.value = state.value.tasks.length === 0
  try {
    const { data } = await scheduleService.get()
    state.value = data.data ?? data
  } catch (e) {
    notify.error(e.response?.data?.message || 'Failed to load schedule.')
  } finally {
    loading.value = false
  }
}

async function runNow(command) {
  if (runningCommand.value) return
  if (!confirm(`Run "${command}" now?`)) return
  runningCommand.value = command
  try {
    const { data } = await scheduleService.run(command)
    notify.success(data.message || `Ran ${command}.`)
    await refresh()
  } catch (e) {
    notify.error(e.response?.data?.message || 'Run failed.')
  } finally {
    runningCommand.value = null
  }
}

function formatTime(iso) {
  if (!iso) return '—'
  try { return new Date(iso).toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' }) }
  catch { return iso }
}

function formatDuration(ms) {
  if (ms == null) return '—'
  if (ms < 1000) return `${ms} ms`
  const s = ms / 1000
  if (s < 60) return `${s.toFixed(1)} s`
  const m = Math.floor(s / 60)
  return `${m}m ${Math.round(s - m * 60)}s`
}

function statusBadge(status) {
  return {
    success: 'bg-success',
    failed: 'bg-danger',
    running: 'bg-primary',
    skipped: 'bg-warning text-dark',
  }[status] || 'bg-secondary'
}

onMounted(() => {
  refresh()
  // Light polling so the page reflects scheduler activity without manual refresh.
  pollTimer = setInterval(refresh, 30000)
})

onBeforeUnmount(() => {
  if (pollTimer) clearInterval(pollTimer)
})
</script>
