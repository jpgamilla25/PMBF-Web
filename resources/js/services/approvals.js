import api from './api'

export default {
  getApprovals(params = {}) {
    return api.get('/approvals', { params })
  },

  getApproval(id) {
    return api.get(`/approvals/${id}`)
  },

  approve(id, data = {}) {
    return api.post(`/approvals/${id}/approve`, data)
  },

  disapprove(id, data = {}) {
    return api.post(`/approvals/${id}/disapprove`, data)
  },

  release(id) {
    return api.post(`/approvals/${id}/release`)
  },

  bulkApprove(loanIds, remarks = null) {
    return api.post('/approvals/bulk-approve', { loan_ids: loanIds, remarks })
  },

  bulkDisapprove(loanIds, remarks) {
    return api.post('/approvals/bulk-disapprove', { loan_ids: loanIds, remarks })
  },

  bulkRelease(loanIds, remarks = null) {
    return api.post('/approvals/bulk-release', { loan_ids: loanIds, remarks })
  },
}
