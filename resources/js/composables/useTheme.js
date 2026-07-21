import { ref, computed, readonly } from 'vue'

/**
 * Light / dark / auto theming.
 *
 * Bootstrap 5.3 recolours itself from the `data-bs-theme` attribute on
 * <html>; `resources/css/app.css` covers the PMBF-specific surfaces.
 * The initial attribute is set by an inline script in app.blade.php so the
 * page never flashes white before Vue boots — this composable only takes
 * over once the user changes the setting.
 */

const STORAGE_KEY = 'pmbf_theme'
const MODES = ['light', 'dark', 'auto']

function stored() {
  const value = localStorage.getItem(STORAGE_KEY)
  return MODES.includes(value) ? value : 'auto'
}

// Module-level so every component shares one source of truth.
const mode = ref(stored())
const systemDark = ref(
  window.matchMedia?.('(prefers-color-scheme: dark)').matches ?? false
)

const isDark = computed(() =>
  mode.value === 'dark' || (mode.value === 'auto' && systemDark.value)
)

function apply() {
  document.documentElement.setAttribute('data-bs-theme', isDark.value ? 'dark' : 'light')
}

// Track the OS setting so 'auto' stays live without a reload.
window.matchMedia?.('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
  systemDark.value = e.matches
  if (mode.value === 'auto') apply()
})

export function useTheme() {
  function setMode(next) {
    if (!MODES.includes(next)) return
    mode.value = next
    localStorage.setItem(STORAGE_KEY, next)
    apply()
  }

  /** Cycle light → dark → auto, for a single-button toggle. */
  function cycle() {
    setMode(MODES[(MODES.indexOf(mode.value) + 1) % MODES.length])
  }

  const icon = computed(() => ({
    light: 'bi bi-sun-fill',
    dark: 'bi bi-moon-stars-fill',
    auto: 'bi bi-circle-half',
  }[mode.value]))

  const label = computed(() => ({
    light: 'Light',
    dark: 'Dark',
    auto: 'System',
  }[mode.value]))

  return { mode: readonly(mode), isDark, setMode, cycle, icon, label, MODES }
}

/** Called once at boot to reconcile Vue state with the pre-paint script. */
export function initTheme() {
  apply()
}
