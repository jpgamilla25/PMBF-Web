import api from './api'

export default {
  // Registration
  registerLookup: (data) => api.post('register/lookup', data),
  registerComplete: (data) => api.post('register/complete', data),
  registerResendOtp: (data) => api.post('register/resend-otp', data),

  // Login via Email OTP
  loginRequestOtp: (data) => api.post('login/request-otp', data),
  loginVerifyOtp: (data) => api.post('login/verify-otp', data),

  // Login via PIN (device-scoped)
  pinStatus: (data) => api.post('login/pin-status', data),
  loginWithPin: (data) => api.post('login/pin', data),
  setPin: (data) => api.post('pin', data),
  removePin: () => api.delete('pin'),
  pinResetRequest: (data) => api.post('pin/reset/request', data),
  pinResetConfirm: (data) => api.post('pin/reset/confirm', data),

  // Login via QR Code
  qrGenerate: () => api.post('login/qr-generate'),
  qrStatus: (sessionToken) => api.get(`login/qr-status/${sessionToken}`),
  qrApprove: (data) => api.post('login/qr-approve', data),

  // Session & Devices
  logout: () => api.post('logout'),
  logoutAll: () => api.post('logout-all'),
  getMe: () => api.get('me'),
  syncHris: () => api.post('me/sync-hris'),
  getTrustedDevices: () => api.get('trusted-devices'),
  revokeTrust: (data) => api.post('revoke-trust', data),
}
