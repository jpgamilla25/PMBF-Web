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
}
