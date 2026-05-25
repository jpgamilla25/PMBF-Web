import api from './api'

export default {
  getDashboard(params = {}) {
    return api.get('/admin/dashboard', { params })
  },

  setBeginningBalance(data) {
    return api.post('/admin/beginning-balance', data)
  },

  getMembers(params = {}) {
    return api.get('/admin/members', { params })
  },

  getMember(id) {
    return api.get(`/admin/members/${id}`)
  },

  getLoans(params = {}) {
    return api.get('/admin/loans', { params })
  },

  getPayments(params = {}) {
    return api.get('/admin/payments', { params })
  },

  storePayment(data) {
    const isFormData = data instanceof FormData
    return api.post('/admin/payments', data, isFormData ? {
      headers: { 'Content-Type': 'multipart/form-data' },
    } : {})
  },

  getConfigurations() {
    return api.get('/admin/configurations')
  },

  updateConfigurations(data) {
    return api.post('/admin/configurations', data)
  },

  getReports(params = {}) {
    return api.get('/admin/reports', { params })
  },

  // ── New granular reports ──
  getReportLoans(params = {}) {
    return api.get('/admin/reports/loans', { params })
  },
  getReportPayments(params = {}) {
    return api.get('/admin/reports/payments', { params })
  },
  getReportMembers(params = {}) {
    return api.get('/admin/reports/members', { params })
  },
  getReportShares(params = {}) {
    return api.get('/admin/reports/shares', { params })
  },
  downloadReportCsv(type, params = {}) {
    return api.get(`/admin/reports/${type}/csv`, { params, responseType: 'blob' })
  },

  importLoans(formData) {
    return api.post('/admin/import/loans', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
  },

  importBenefits(formData) {
    return api.post('/admin/import/benefits', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
  },

  downloadLoanTemplate() {
    return api.get('/admin/import/template/loans', { responseType: 'blob' })
  },

  downloadBenefitTemplate() {
    return api.get('/admin/import/template/benefits', { responseType: 'blob' })
  },

  importPayments(formData) {
    return api.post('/admin/import/payments', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
  },

  downloadPaymentTemplate() {
    return api.get('/admin/import/template/payments', { responseType: 'blob' })
  },

  // User Type Management
  getUserTypes(params = {}) {
    return api.get('/admin/user-types', { params })
  },
  assignRole(data) {
    return api.post('/admin/user-types/assign', data)
  },

  // Activity Logs
  getActivityLogs(params = {}) {
    return api.get('/admin/activity-logs', { params })
  },
}
