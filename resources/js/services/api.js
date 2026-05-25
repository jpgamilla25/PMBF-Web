import axios from 'axios'

const api = axios.create({
  baseURL: '/api/v1',
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
})

// Attach token to every request
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('pmbf_token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

// Handle 401 (token revoked / another session took over)
let isRedirecting = false

api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401 && !isRedirecting) {
      isRedirecting = true
      localStorage.removeItem('pmbf_token')

      // Don't redirect if already on login/register
      const path = window.location.pathname
      if (!path.startsWith('/login') && !path.startsWith('/register')) {
        // Show a message that session was ended
        const wasLoggedIn = !!localStorage.getItem('pmbf_token') || path !== '/login'
        if (wasLoggedIn) {
          sessionStorage.setItem('pmbf_session_ended', '1')
        }
        window.location.href = '/login'
      }

      setTimeout(() => { isRedirecting = false }, 2000)
    }
    return Promise.reject(error)
  }
)

export default api
