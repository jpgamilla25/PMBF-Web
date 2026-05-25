import api from './api'

export default {
  // Member
  getMyRequests: () => api.get('/exemptions/my'),
  createRequest: (data) => api.post('/exemptions', data),

  // Admin
  getAll: (params = {}) => api.get('/admin/exemptions', { params }),
  approve: (id, data = {}) => api.post(`/admin/exemptions/${id}/approve`, data),
  reject: (id, data) => api.post(`/admin/exemptions/${id}/reject`, data),
}
