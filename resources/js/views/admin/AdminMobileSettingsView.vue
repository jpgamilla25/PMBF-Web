<template>
  <AppLayout>
    <div class="d-flex align-items-center justify-content-between mb-4">
      <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-phone me-2"></i>Mobile App Settings</h4>
        <p class="text-muted small mb-0">Configure the PMBF mobile application</p>
      </div>
      <AppButton variant="primary" :loading="saving" @click="handleSave">
        <i class="bi bi-check-lg me-1"></i>Save Changes
      </AppButton>
    </div>

    <AppLoading :loading="loading" text="Loading settings..." />

    <template v-if="!loading">
      <!-- Logos & Assets -->
      <div class="card mb-4">
        <div class="card-header d-flex align-items-center">
          <i class="bi bi-image me-2 text-primary"></i>
          <h6 class="mb-0 fw-bold">Logos & Images</h6>
        </div>
        <div class="card-body">
          <div v-for="assetType in assetTypes" :key="assetType.key" class="mb-4">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <div>
                <h6 class="fw-semibold mb-0">{{ assetType.label }}</h6>
                <small class="text-muted">{{ assetType.hint }}</small>
              </div>
              <label :for="`upload-${assetType.key}`" class="btn btn-outline-primary btn-sm mb-0" style="cursor: pointer;">
                <i class="bi bi-plus-lg me-1"></i>Add
                <input :id="`upload-${assetType.key}`" type="file" accept="image/*" class="d-none" @change="(e) => uploadAsset(e, assetType.key)" />
              </label>
            </div>

            <div class="row g-3">
              <div v-for="asset in getAssetsByType(assetType.key)" :key="asset.id" class="col-auto">
                <div class="asset-card">
                  <img :src="asset.url" :alt="asset.label" class="asset-img" />
                  <div class="asset-overlay">
                    <span class="small text-white text-truncate d-block" style="max-width: 80px;">{{ asset.label }}</span>
                    <button class="btn btn-sm btn-danger py-0 px-1" @click="deleteAsset(asset.id)">
                      <i class="bi bi-trash small"></i>
                    </button>
                  </div>
                </div>
              </div>
              <div v-if="!getAssetsByType(assetType.key).length" class="col-12">
                <div class="text-muted small fst-italic py-2">No {{ assetType.label.toLowerCase() }} uploaded yet</div>
              </div>
            </div>
          </div>

          <div v-if="uploading" class="text-center py-2">
            <div class="spinner-border spinner-border-sm text-primary me-2"></div>
            <span class="small text-muted">Uploading...</span>
          </div>
        </div>
      </div>

      <!-- General -->
      <div class="card mb-4">
        <div class="card-header d-flex align-items-center">
          <i class="bi bi-app-indicator me-2 text-primary"></i>
          <h6 class="mb-0 fw-bold">General</h6>
        </div>
        <div class="card-body">
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label fw-medium small">App Name</label>
              <input v-model="values.app_name" type="text" class="form-control form-control-sm" />
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label fw-medium small">Tagline</label>
              <input v-model="values.app_tagline" type="text" class="form-control form-control-sm" />
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label fw-medium small">Minimum App Version</label>
              <input v-model="values.app_version_minimum" type="text" class="form-control form-control-sm" placeholder="e.g. 1.0.0" />
              <small class="text-muted">Users with older versions will be forced to update</small>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label fw-medium small">Support Email</label>
              <input v-model="values.app_support_email" type="email" class="form-control form-control-sm" />
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label fw-medium small">Support Phone</label>
              <input v-model="values.app_support_phone" type="text" class="form-control form-control-sm" placeholder="Optional" />
            </div>
          </div>
          <hr class="my-3">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <div>
              <div class="fw-medium small">Maintenance Mode</div>
              <div class="text-muted" style="font-size: 0.75rem;">When enabled, users see a maintenance message instead of login</div>
            </div>
            <div class="form-check form-switch">
              <input v-model="values.app_maintenance_mode" class="form-check-input" type="checkbox" true-value="1" false-value="0" />
            </div>
          </div>
          <div v-if="values.app_maintenance_mode === '1'" class="mt-2">
            <label class="form-label fw-medium small">Maintenance Message</label>
            <textarea v-model="values.app_maintenance_message" class="form-control form-control-sm" rows="2"></textarea>
          </div>
        </div>
      </div>

      <!-- Features -->
      <div class="card mb-4">
        <div class="card-header d-flex align-items-center">
          <i class="bi bi-toggles me-2 text-primary"></i>
          <h6 class="mb-0 fw-bold">Features</h6>
        </div>
        <div class="card-body">
          <div class="row">
            <div v-for="feat in featureToggles" :key="feat.key" class="col-md-6 mb-3">
              <div class="d-flex align-items-center justify-content-between p-2 border rounded">
                <div>
                  <div class="fw-medium small">{{ feat.label }}</div>
                  <div class="text-muted" style="font-size: 0.7rem;">{{ feat.hint }}</div>
                </div>
                <div class="form-check form-switch mb-0">
                  <input v-model="values[feat.key]" class="form-check-input" type="checkbox" true-value="1" false-value="0" />
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Security -->
      <div class="card mb-4">
        <div class="card-header d-flex align-items-center">
          <i class="bi bi-shield-lock me-2 text-primary"></i>
          <h6 class="mb-0 fw-bold">Security</h6>
        </div>
        <div class="card-body">
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label fw-medium small">Trust Device Duration</label>
              <div class="input-group input-group-sm">
                <input v-model="values.mobile_trust_device_days" type="number" min="1" class="form-control" />
                <span class="input-group-text">days</span>
              </div>
              <small class="text-muted">How long to skip OTP on a trusted device</small>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label fw-medium small">Session Timeout</label>
              <div class="input-group input-group-sm">
                <input v-model="values.mobile_session_timeout" type="number" min="5" class="form-control" />
                <span class="input-group-text">min</span>
              </div>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label fw-medium small">Max OTP Attempts</label>
              <div class="input-group input-group-sm">
                <input v-model="values.mobile_max_otp_attempts" type="number" min="1" class="form-control" />
                <span class="input-group-text">attempts</span>
              </div>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label fw-medium small">Lockout Duration</label>
              <div class="input-group input-group-sm">
                <input v-model="values.mobile_lockout_duration" type="number" min="1" class="form-control" />
                <span class="input-group-text">min</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- Sticky save bar -->
    <div v-if="hasChanges && !loading" class="position-fixed bottom-0 start-0 end-0 bg-white border-top p-3 shadow-lg" style="z-index: 100;">
      <div class="container-fluid">
        <div class="d-flex align-items-center justify-content-between">
          <span class="text-warning fw-medium"><i class="bi bi-exclamation-circle me-1"></i>You have unsaved changes</span>
          <div>
            <button class="btn btn-outline-secondary btn-sm me-2" @click="resetChanges">Discard</button>
            <AppButton variant="primary" size="sm" :loading="saving" @click="handleSave">
              <i class="bi bi-check-lg me-1"></i>Save
            </AppButton>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useLoading } from '@/composables/useLoading'
import { useNotificationStore } from '@/stores/notification'
import admin from '@/services/admin'
import api from '@/services/api'
import AppLayout from '@/components/layout/AppLayout.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppLoading from '@/components/ui/AppLoading.vue'

const notify = useNotificationStore()
const { loading, withLoading } = useLoading()
const saving = ref(false)
const uploading = ref(false)

const values = reactive({})
const originalValues = ref({})
const assets = ref([])

const assetTypes = [
  { key: 'logo', label: 'Logo', hint: 'App logo for login, splash, navigation. Upload multiple variants (light, dark, icon-only).' },
  { key: 'icon', label: 'App Icon', hint: 'Launcher icon for the home screen.' },
  { key: 'splash', label: 'Splash Screen', hint: 'Full-screen image shown while app loads.' },
  { key: 'banner', label: 'Banner', hint: 'Dashboard banners or announcements.' },
]

const featureToggles = [
  { key: 'mobile_login_otp_enabled', label: 'Email OTP Login', hint: 'Allow login via Email OTP' },
  { key: 'mobile_qr_login_enabled', label: 'QR Code Login', hint: 'Allow scanning web QR to login' },
  { key: 'mobile_biometric_enabled', label: 'Biometric Login', hint: 'Face recognition / fingerprint for quick login' },
  { key: 'mobile_loan_apply_enabled', label: 'Loan Applications', hint: 'Allow applying for loans from mobile' },
  { key: 'mobile_claims_enabled', label: 'File Claims', hint: 'Allow filing claims from mobile' },
]

const hasChanges = computed(() => Object.keys(values).some(k => values[k] !== originalValues.value[k]))
function resetChanges() { Object.keys(originalValues.value).forEach(k => { values[k] = originalValues.value[k] }) }
function getAssetsByType(type) { return assets.value.filter(a => a.type === type) }

async function fetchSettings() {
  await withLoading(async () => {
    const { data } = await admin.getConfigurations()
    const configs = data.data ?? data
    if (Array.isArray(configs)) {
      const groups = ['mobile_app_general', 'mobile_app_features', 'mobile_app_security']
      configs.filter(c => groups.includes(c.group)).forEach(c => {
        values[c.key] = c.value ?? ''
        originalValues.value[c.key] = c.value ?? ''
      })
    }
    try {
      const res = await api.get('/admin/app-assets')
      assets.value = res.data.data ?? []
    } catch { assets.value = [] }
  })
}

async function uploadAsset(event, type) {
  const file = event.target.files?.[0]
  if (!file) return

  const label = prompt(`Label for this ${type}:`, file.name.replace(/\.[^/.]+$/, ''))
  if (label === null) { event.target.value = ''; return }

  uploading.value = true
  try {
    const fd = new FormData()
    fd.append('file', file)
    fd.append('type', type)
    fd.append('label', label || file.name)
    const { data } = await api.post('/admin/app-assets/upload', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    assets.value.push(data.data)
    notify.success('Image uploaded.')
  } catch (err) {
    notify.error(err.response?.data?.message || 'Upload failed.')
  } finally {
    uploading.value = false
    event.target.value = ''
  }
}

async function deleteAsset(id) {
  if (!confirm('Delete this image?')) return
  try {
    await api.delete(`/admin/app-assets/${id}`)
    assets.value = assets.value.filter(a => a.id !== id)
    notify.success('Image deleted.')
  } catch { notify.error('Failed to delete.') }
}

async function handleSave() {
  saving.value = true
  try {
    await admin.updateConfigurations({ configurations: { ...values } })
    Object.keys(values).forEach(k => { originalValues.value[k] = values[k] })
    notify.success('Mobile app settings saved.')
  } catch { notify.error('Failed to save.') }
  finally { saving.value = false }
}

onMounted(fetchSettings)
</script>

<style scoped>
.asset-card {
  width: 120px;
  height: 120px;
  border-radius: 10px;
  overflow: hidden;
  position: relative;
  border: 2px solid #e5e7eb;
  background: #f9fafb;
}
.asset-img {
  width: 100%;
  height: 100%;
  object-fit: contain;
  padding: 8px;
}
.asset-overlay {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  background: rgba(0, 0, 0, 0.7);
  padding: 4px 8px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  opacity: 0;
  transition: opacity 0.2s;
}
.asset-card:hover .asset-overlay {
  opacity: 1;
}
</style>
