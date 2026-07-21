import api from './api'

export default {
  getLoans: (params = {}) => api.get('/loans', { params }),
  getLoan: (id) => api.get(`/loans/${id}`),
  getSchedule: (id) => api.get(`/loans/${id}/schedule`),
  createLoan: (data) => api.post('/loans', data),
  verifyOtp: (data) => api.post('/loans/verify-otp', data),
  verifyPin: (data) => api.post('/loans/verify-pin', data),
  resendOtp: () => api.post('/loans/resend-otp'),
  getTypes: () => api.get('/loans/types'),
  getStats: () => api.get('/loans/stats'),
  checkEligibility: (data) => api.post('/loans/check-eligibility', data),
  cancelLoan: (id) => api.post(`/loans/${id}/cancel`),
  getCoMakers: (params = {}) => api.get('/loans/co-makers', { params }),
  getLoanPdfUrl: (id) => `/api/v1/loans/${id}/pdf`,
  getPendingCoMaker: () => api.get('/co-maker/pending'),
  respondCoMaker: (id, action) => api.post(`/co-maker/${id}/respond`, { action }),
}
