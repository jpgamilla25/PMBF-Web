import api from './api'

export default {
  // Member
  getMyShares: (params = {}) => api.get('/shares/my', { params }),
  requestUpdate: (data) => api.post('/shares/request-update', data),

  // Admin
  getAll: (params = {}) => api.get('/admin/shares', { params }),
  store: (data) => api.post('/admin/shares', data),
  bulkStore: (data) => api.post('/admin/shares/bulk', data),
  getPendingRequests: () => api.get('/admin/shares/pending-requests'),
  approveRequest: (id, data = {}) => api.post(`/admin/shares/requests/${id}/approve`, data),
  rejectRequest: (id, data) => api.post(`/admin/shares/requests/${id}/reject`, data),
}
