import api from './api'

export default {
  get: () => api.get('/admin/schedule'),
  run: (command) => api.post('/admin/schedule/run', { command }, { timeout: 300000 }),
}
