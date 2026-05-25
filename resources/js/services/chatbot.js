import api from './api'

const BASE = '/chatbot'

export default {
  /**
   * Send message to chatbot.
   * Uses authenticated endpoint if token exists, otherwise public.
   */
  sendMessage(message, history = []) {
    const hasToken = !!localStorage.getItem('pmbf_token')
    const url = hasToken ? `${BASE}/message/auth` : `${BASE}/message`
    return api.post(url, { message, history })
  },

  /**
   * Get FAQ suggestions based on partial input.
   */
  getSuggestions(query = '') {
    const hasToken = !!localStorage.getItem('pmbf_token')
    const url = hasToken ? `${BASE}/suggestions/auth` : `${BASE}/suggestions`
    return api.get(url, { params: { q: query } })
  },
}
