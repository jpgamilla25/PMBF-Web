import api from './api'

export default {
  // Member (Contract of Service)
  getMyPremiums: (params = {}) => api.get('/premiums/my', { params }),

  // Admin
  getAll: (params = {}) => api.get('/admin/premiums', { params }),
  store: (data) => api.post('/admin/premiums', data),
  bulkStore: (data) => api.post('/admin/premiums/bulk', data),
  getAnalytics: (params = {}) => api.get('/admin/premiums/analytics', { params }),
  getMemberPremiums: (userId, params = {}) => api.get(`/admin/premiums/members/${userId}`, { params }),
}
