import api from './api'

export default {
  getAll: (params = {}) => api.get('/admin/loan-payments', { params }),
  getAnalytics: (params = {}) => api.get('/admin/loan-payments/analytics', { params }),
  getMemberPayments: (userId, params = {}) => api.get(`/admin/loan-payments/members/${userId}`, { params }),
  syncFromFmis: (data = {}) => api.post('/admin/loan-payments/sync-from-fmis', data, { timeout: 300000 }),
  getPending: (params = {}) => api.get('/admin/loan-payments/pending', { params }),
  applyPending: (fmisId, allocations) => api.post(`/admin/loan-payments/pending/${fmisId}/apply`, { allocations }),
  runLinker: () => api.post('/admin/loan-payments/link', {}, { timeout: 300000 }),
}
