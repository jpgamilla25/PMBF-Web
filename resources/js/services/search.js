import api from './api'

export default {
  query: (q) => api.get('search', { params: { q } }),
}
