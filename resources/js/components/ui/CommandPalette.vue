<template>
  <Teleport to="body">
    <div v-if="open" class="cp-backdrop" @mousedown.self="close">
      <div class="cp-panel shadow-lg" role="dialog" aria-modal="true" aria-label="Search">
        <!-- Query -->
        <div class="cp-search d-flex align-items-center gap-2 px-3">
          <i class="bi bi-search text-muted"></i>
          <input
            ref="inputEl"
            v-model="query"
            type="text"
            class="cp-input flex-grow-1"
            placeholder="Search pages, members, loans..."
            @keydown.down.prevent="move(1)"
            @keydown.up.prevent="move(-1)"
            @keydown.enter.prevent="choose(flat[cursor])"
            @keydown.esc.prevent="close"
          />
          <span v-if="loading" class="spinner-border spinner-border-sm text-muted"></span>
          <kbd class="cp-kbd">Esc</kbd>
        </div>

        <!-- Results -->
        <div ref="listEl" class="cp-results">
          <p v-if="!flat.length" class="text-muted small text-center py-4 mb-0">
            {{ query.length && query.length < 2 ? 'Keep typing...' : 'No matches.' }}
          </p>

          <template v-for="group in groups" :key="group.label">
            <div v-if="group.items.length" class="cp-group">
              <div class="cp-group-label">{{ group.label }}</div>
              <button
                v-for="item in group.items"
                :key="group.label + item.url"
                type="button"
                class="cp-item"
                :class="{ active: flat[cursor] === item }"
                :data-index="flat.indexOf(item)"
                @click="choose(item)"
                @mousemove="cursor = flat.indexOf(item)"
              >
                <i class="bi flex-shrink-0" :class="item.icon || group.icon"></i>
                <span class="flex-grow-1 min-w-0">
                  <span class="d-block text-truncate">{{ item.title }}</span>
                  <span v-if="item.subtitle" class="d-block cp-sub text-truncate">{{ item.subtitle }}</span>
                </span>
                <i class="bi bi-arrow-return-left cp-enter"></i>
              </button>
            </div>
          </template>
        </div>

        <div class="cp-footer d-flex align-items-center gap-3 px-3">
          <span><kbd class="cp-kbd">↑</kbd><kbd class="cp-kbd">↓</kbd> navigate</span>
          <span><kbd class="cp-kbd">↵</kbd> open</span>
          <span class="ms-auto"><kbd class="cp-kbd">Ctrl</kbd><kbd class="cp-kbd">K</kbd></span>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useInboxStore } from '@/stores/inbox'
import { useTheme } from '@/composables/useTheme'
import search from '@/services/search'

const router = useRouter()
const authStore = useAuthStore()
const inbox = useInboxStore()
const { isDark, setMode, label: themeLabel } = useTheme()

const open = ref(false)
const query = ref('')
const cursor = ref(0)
const loading = ref(false)
const inputEl = ref(null)
const listEl = ref(null)

const EMPTY_REMOTE = { members: [], loans: [], payments: [], claims: [], dependents: [] }

const remote = ref({ ...EMPTY_REMOTE })
let debounceTimer = null
let requestId = 0

// ── Pages ──────────────────────────────────────────────────
// Mirrors the sidebar so the palette can reach anything the nav can.

const PAGES = [
  { title: 'Dashboard', url: '/dashboard', icon: 'bi-speedometer2' },
  { title: 'My Loans', url: '/loans', icon: 'bi-cash-stack' },
  { title: 'Apply for Loan', url: '/loans/new', icon: 'bi-plus-circle' },
  { title: 'My Shares', url: '/shares', icon: 'bi-pie-chart' },
  { title: 'Profile', url: '/profile', icon: 'bi-person-gear' },
  { title: 'Security & PIN', url: '/profile', icon: 'bi-shield-lock', subtitle: 'Change or remove your PIN' },
  { title: 'Dependents', url: '/dependents', icon: 'bi-people', permanentOnly: true },
  { title: 'Claims', url: '/claims', icon: 'bi-file-earmark-text', permanentOnly: true },
  { title: 'Benefits', url: '/benefits', icon: 'bi-gift', permanentOnly: true },

  { title: 'Approvals', url: '/approvals', icon: 'bi-clipboard-check', staff: true },

  { title: 'Members', url: '/admin/members', icon: 'bi-people-fill', admin: true },
  { title: 'All Loans', url: '/admin/loans', icon: 'bi-cash-coin', admin: true },
  { title: 'Payments', url: '/admin/payments', icon: 'bi-credit-card', admin: true },
  { title: 'Share Capital', url: '/admin/shares', icon: 'bi-pie-chart', admin: true },
  { title: 'User Types', url: '/admin/user-types', icon: 'bi-person-gear', admin: true },
  { title: 'Configuration', url: '/admin/config', icon: 'bi-gear', admin: true },
  { title: 'Special Approvals', url: '/admin/exemptions', icon: 'bi-envelope-paper', admin: true, subtitle: 'Review exemption requests' },
  { title: 'Audit Trail', url: '/admin/activity-logs', icon: 'bi-journal-text', admin: true },
  { title: 'Schedule Monitor', url: '/admin/schedule', icon: 'bi-clock-history', admin: true },
  { title: 'Import Data', url: '/admin/import', icon: 'bi-upload', admin: true },
  { title: 'Mobile App', url: '/admin/mobile-settings', icon: 'bi-phone', admin: true },

  { title: 'Reports', url: '/admin/reports', icon: 'bi-file-earmark-bar-graph', admin: true },
  { title: 'Report: Loans', url: '/admin/reports/loans', icon: 'bi-file-earmark-bar-graph', admin: true },
  { title: 'Report: Payments', url: '/admin/reports/payments', icon: 'bi-file-earmark-bar-graph', admin: true },
  { title: 'Report: Members', url: '/admin/reports/members', icon: 'bi-file-earmark-bar-graph', admin: true },
  { title: 'Report: Shares', url: '/admin/reports/shares', icon: 'bi-file-earmark-bar-graph', admin: true },
  { title: 'Report: Loan Ledger', url: '/admin/reports/ledger', icon: 'bi-file-earmark-bar-graph', admin: true },
]

const visiblePages = computed(() =>
  PAGES.filter((p) => {
    if (p.admin && !authStore.isAdmin) return false
    if (p.staff && !authStore.isStaff) return false
    if (p.permanentOnly && !authStore.isPermanent) return false
    return true
  })
)

/** Subsequence match, so "arep" finds "Report: Approvals". */
function matches(text, q) {
  const haystack = text.toLowerCase()
  const needle = q.toLowerCase()
  if (haystack.includes(needle)) return true

  let i = 0
  for (const ch of haystack) {
    if (ch === needle[i]) i++
    if (i === needle.length) return true
  }
  return false
}

const pageResults = computed(() => {
  if (!query.value.trim()) return visiblePages.value.slice(0, 6)
  return visiblePages.value.filter((p) => matches(p.title, query.value.trim())).slice(0, 8)
})

// ── Actions ────────────────────────────────────────────────
// Things you *do* rather than navigate to. `run` marks an item as an action
// so choose() invokes it instead of routing.

const actions = computed(() => {
  const list = [
    {
      title: 'Apply for a loan',
      subtitle: 'Start a new loan application',
      icon: 'bi-plus-circle',
      url: '/loans/new',
    },
    {
      title: authStore.hasPin ? 'Change your PIN' : 'Set up a sign-in PIN',
      subtitle: authStore.hasPin ? 'Update your 4-digit PIN' : 'Skip the email OTP next time',
      icon: 'bi-shield-lock',
      url: '/pin-setup',
    },
    {
      title: 'Download payment statement',
      subtitle: 'Your payment history as a PDF',
      icon: 'bi-file-earmark-pdf',
      url: '/loans',
    },
    {
      title: 'Mark all notifications read',
      subtitle: inbox.unreadCount ? `${inbox.unreadCount} unread` : 'Nothing unread',
      icon: 'bi-bell-slash',
      run: () => inbox.markAllAsRead(),
    },
    {
      title: `Switch to ${isDark.value ? 'light' : 'dark'} mode`,
      subtitle: `Currently ${themeLabel.value.toLowerCase()}`,
      icon: isDark.value ? 'bi-sun' : 'bi-moon-stars',
      run: () => setMode(isDark.value ? 'light' : 'dark'),
    },
    {
      title: 'Match system theme',
      subtitle: 'Follow your device appearance setting',
      icon: 'bi-circle-half',
      run: () => setMode('auto'),
    },
    {
      title: 'Log out',
      subtitle: 'End this session',
      icon: 'bi-box-arrow-right',
      run: async () => {
        await authStore.logout()
        router.push('/login')
      },
    },
  ]

  if (authStore.isStaff) {
    list.unshift({
      title: 'Review pending approvals',
      subtitle: authStore.pendingApprovalCount
        ? `${authStore.pendingApprovalCount} waiting on you`
        : 'Nothing pending',
      icon: 'bi-clipboard-check',
      url: '/approvals',
    })
  }

  if (authStore.isAdmin) {
    list.unshift({
      title: 'Switch member type',
      subtitle: 'Change the admin viewing context',
      icon: 'bi-toggles',
      url: '/admin/select-type',
    })
  }

  return list
})

const actionResults = computed(() => {
  const q = query.value.trim()
  if (!q) return []
  return actions.value.filter((a) => matches(a.title + ' ' + (a.subtitle ?? ''), q)).slice(0, 5)
})

// ── Recent ─────────────────────────────────────────────────
// Only shown on an empty query, as a jump-back-to-what-I-was-doing list.

const RECENT_KEY = 'pmbf_palette_recent'
const recent = ref(readRecent())

function readRecent() {
  try {
    // localStorage is user-writable and may hold anything from an older
    // build — only accept a genuine array of entries.
    const parsed = JSON.parse(localStorage.getItem(RECENT_KEY))
    return Array.isArray(parsed) ? parsed.filter((r) => r && r.url) : []
  } catch {
    return []
  }
}

function rememberRecent(item) {
  if (!item.url || item.run) return

  const entry = { title: item.title, subtitle: item.subtitle, icon: item.icon, url: item.url }
  const next = [entry, ...recent.value.filter((r) => r.url !== entry.url)].slice(0, 5)

  recent.value = next
  localStorage.setItem(RECENT_KEY, JSON.stringify(next))
}

const recentResults = computed(() => (query.value.trim() ? [] : recent.value))

/** Anything that reaches the template as a group must be a real array. */
function asList(value) {
  return Array.isArray(value) ? value : []
}

const groups = computed(() => [
  { label: 'Recent', icon: 'bi-clock-history', items: asList(recentResults.value) },
  { label: 'Actions', icon: 'bi-lightning-charge', items: asList(actionResults.value) },
  { label: 'Pages', icon: 'bi-arrow-right-short', items: asList(pageResults.value) },
  { label: 'Members', icon: 'bi-person', items: asList(remote.value?.members) },
  { label: 'Loans', icon: 'bi-cash-stack', items: asList(remote.value?.loans) },
  { label: 'Payments', icon: 'bi-credit-card', items: asList(remote.value?.payments) },
  { label: 'Claims', icon: 'bi-file-earmark-text', items: asList(remote.value?.claims) },
  { label: 'Dependents', icon: 'bi-people', items: asList(remote.value?.dependents) },
])

const flat = computed(() => groups.value.flatMap((g) => g.items))

// ── Remote search ──────────────────────────────────────────

watch(query, (q) => {
  cursor.value = 0
  clearTimeout(debounceTimer)

  if (q.trim().length < 2) {
    remote.value = { ...EMPTY_REMOTE }
    loading.value = false
    return
  }

  loading.value = true
  debounceTimer = setTimeout(async () => {
    // Guard against a slow earlier request overwriting a newer one.
    const id = ++requestId
    try {
      const { data } = await search.query(q.trim())
      if (id !== requestId) return
      const result = data.data ?? data
      remote.value = Object.fromEntries(
        Object.keys(EMPTY_REMOTE).map((k) => [k, result[k] ?? []])
      )
    } catch {
      if (id === requestId) remote.value = { members: [], loans: [] }
    } finally {
      if (id === requestId) loading.value = false
    }
  }, 220)
})

// ── Interaction ────────────────────────────────────────────

function move(step) {
  if (!flat.value.length) return
  cursor.value = (cursor.value + step + flat.value.length) % flat.value.length

  nextTick(() => {
    listEl.value
      ?.querySelector(`[data-index="${cursor.value}"]`)
      ?.scrollIntoView({ block: 'nearest' })
  })
}

function choose(item) {
  if (!item) return
  close()

  if (item.run) {
    item.run()
    return
  }

  rememberRecent(item)
  router.push(item.url)
}

async function show() {
  open.value = true
  query.value = ''
  cursor.value = 0
  remote.value = { members: [], loans: [] }
  await nextTick()
  inputEl.value?.focus()
}

function close() {
  open.value = false
  clearTimeout(debounceTimer)
  loading.value = false
}

function onKeydown(e) {
  const isPaletteKey = (e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k'
  if (!isPaletteKey) return

  e.preventDefault()
  open.value ? close() : show()
}

onMounted(() => window.addEventListener('keydown', onKeydown))
onUnmounted(() => {
  window.removeEventListener('keydown', onKeydown)
  clearTimeout(debounceTimer)
})

defineExpose({ show, close })
</script>

<style scoped>
.cp-backdrop {
  position: fixed;
  inset: 0;
  z-index: 2000;
  background: rgba(15, 23, 42, .45);
  backdrop-filter: blur(2px);
  display: flex;
  justify-content: center;
  padding: 10vh 16px 16px;
}

.cp-panel {
  width: 100%;
  max-width: 620px;
  max-height: 70vh;
  background: var(--bs-body-bg);
  color: var(--bs-body-color);
  border: 1px solid var(--bs-border-color);
  border-radius: 14px;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.cp-search {
  height: 54px;
  border-bottom: 1px solid var(--bs-border-color);
  flex-shrink: 0;
}

.cp-input {
  border: 0;
  outline: 0;
  background: transparent;
  color: inherit;
  font-size: 1rem;
  min-width: 0;
}

.cp-results {
  overflow-y: auto;
  padding: 6px 0;
}

.cp-group-label {
  padding: 6px 14px 2px;
  font-size: .65rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .05em;
  color: var(--bs-secondary-color, #6b7280);
}

.cp-item {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  padding: 8px 14px;
  border: 0;
  background: transparent;
  color: inherit;
  text-align: left;
  font-size: .875rem;
  cursor: pointer;
}

.cp-item.active {
  background: rgba(59, 130, 246, .14);
}

.cp-item .bi:first-child { color: #3b82f6; }

.cp-sub {
  font-size: .75rem;
  color: var(--bs-secondary-color, #6b7280);
}

.cp-enter { opacity: 0; font-size: .8rem; }
.cp-item.active .cp-enter { opacity: .5; }

.cp-footer {
  height: 36px;
  border-top: 1px solid var(--bs-border-color);
  font-size: .7rem;
  color: var(--bs-secondary-color, #6b7280);
  flex-shrink: 0;
}

.cp-kbd {
  background: var(--bs-tertiary-bg, #f3f4f6);
  border: 1px solid var(--bs-border-color);
  border-radius: 4px;
  padding: 1px 5px;
  font-size: .68rem;
  color: inherit;
  margin-left: 2px;
}

.min-w-0 { min-width: 0; }

@media (max-width: 575.98px) {
  .cp-backdrop { padding-top: 4vh; }
  .cp-footer { display: none !important; }
}
</style>
