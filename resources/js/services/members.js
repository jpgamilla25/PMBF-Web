import api from './api'

export default {
  getProfile() {
    return api.get('/member/profile')
  },

  getDependents() {
    return api.get('/member/dependents')
  },

  storeDependent(data) {
    return api.post('/member/dependents', data)
  },

  deleteDependent(id) {
    return api.delete(`/member/dependents/${id}`)
  },

  getClaims() {
    return api.get('/member/claims')
  },

  storeClaim(data) {
    return api.post('/member/claims', data)
  },

  getBenefits() {
    return api.get('/member/benefits')
  },

  /**
   * Payment statement (JSON) — used for the on-screen preview.
   * params: { from, to, loan_id, user_id }  (user_id is staff/admin only)
   */
  getStatement(params = {}) {
    return api.get('/member/statement', { params: cleanParams(params) })
  },

  /**
   * Payment statement (PDF) URL. The token is passed as a query param so the
   * PDF can be opened in a new browser tab — same pattern as the loan PDFs.
   */
  getStatementPdfUrl(params = {}) {
    const query = new URLSearchParams({
      ...cleanParams(params),
      token: localStorage.getItem('pmbf_token') ?? '',
    })
    return `/api/v1/member/statement/pdf?${query.toString()}`
  },
}

function cleanParams(params) {
  return Object.fromEntries(
    Object.entries(params).filter(([, v]) => v !== null && v !== undefined && v !== ''),
  )
}
