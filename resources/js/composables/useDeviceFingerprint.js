/**
 * Generate a stable device fingerprint from browser properties.
 * Stored in localStorage so it persists across sessions.
 */
export function useDeviceFingerprint() {
  const STORAGE_KEY = 'pmbf_device_fp'

  function generate() {
    const existing = localStorage.getItem(STORAGE_KEY)
    if (existing) return existing

    const components = [
      navigator.userAgent,
      navigator.language,
      screen.width + 'x' + screen.height,
      screen.colorDepth,
      Intl.DateTimeFormat().resolvedOptions().timeZone,
      navigator.hardwareConcurrency || '',
      navigator.platform || '',
    ]

    const raw = components.join('|')
    // Simple hash
    let hash = 0
    for (let i = 0; i < raw.length; i++) {
      const char = raw.charCodeAt(i)
      hash = ((hash << 5) - hash) + char
      hash = hash & hash // Convert to 32-bit int
    }

    const fp = 'fp_' + Math.abs(hash).toString(36) + '_' + Date.now().toString(36)
    localStorage.setItem(STORAGE_KEY, fp)
    return fp
  }

  function get() {
    return localStorage.getItem(STORAGE_KEY) || generate()
  }

  function getDeviceName() {
    const ua = navigator.userAgent
    let browser = 'Unknown'
    let os = 'Unknown'

    if (ua.includes('Firefox/')) browser = 'Firefox'
    else if (ua.includes('Edg/')) browser = 'Edge'
    else if (ua.includes('Chrome/')) browser = 'Chrome'
    else if (ua.includes('Safari/')) browser = 'Safari'

    if (ua.includes('Windows')) os = 'Windows'
    else if (ua.includes('Mac OS')) os = 'macOS'
    else if (ua.includes('Linux')) os = 'Linux'
    else if (ua.includes('Android')) os = 'Android'
    else if (ua.includes('iPhone') || ua.includes('iPad')) os = 'iOS'

    return `${browser} on ${os}`
  }

  return { get, generate, getDeviceName }
}
