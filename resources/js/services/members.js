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
}
