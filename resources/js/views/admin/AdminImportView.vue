<template>
  <AppLayout>
    <div class="d-flex align-items-center justify-content-between mb-4">
      <h4 class="fw-bold mb-0">Import Data</h4>
    </div>

    <!-- Import Existing Loans -->
    <AppCard class="mb-4">
      <template #header>
        <div class="d-flex align-items-center justify-content-between">
          <h5 class="mb-0 fw-semibold">
            <i class="bi bi-cash-coin me-2 text-primary"></i>Import Existing Loans
          </h5>
          <AppButton variant="outline-primary" size="sm" @click="downloadTemplate('loans')" :loading="downloadingLoans">
            <i class="bi bi-download me-1"></i>Download Template
          </AppButton>
        </div>
      </template>

      <p class="text-muted small mb-3">
        Upload a CSV file with existing loan records. Required columns: employee_id, loan_type, amount, interest_rate, term_months, monthly_amortization.
        Optional: status, applied_at, remarks.
      </p>

      <!-- Dropzone -->
      <div
        class="upload-dropzone mb-3"
        :class="{ 'dropzone-active': loansDragOver, 'dropzone-has-file': loansFile }"
        @dragover.prevent="loansDragOver = true"
        @dragleave.prevent="loansDragOver = false"
        @drop.prevent="handleDrop($event, 'loans')"
        @click="$refs.loansFileInput.click()"
      >
        <input
          ref="loansFileInput"
          type="file"
          accept=".csv,.xlsx,.xls"
          class="d-none"
          @change="handleFileSelect($event, 'loans')"
        />
        <div v-if="!loansFile" class="text-center">
          <i class="bi bi-cloud-arrow-up fs-1 text-muted"></i>
          <p class="mb-0 mt-2 text-muted">Drag and drop your CSV file here, or click to browse</p>
          <p class="mb-0 small text-muted">Accepted formats: .csv, .xlsx, .xls (max 10MB)</p>
        </div>
        <div v-else class="text-center">
          <i class="bi bi-file-earmark-check fs-1 text-success"></i>
          <p class="mb-0 mt-2 fw-semibold">{{ loansFile.name }}</p>
          <p class="mb-0 small text-muted">{{ formatFileSize(loansFile.size) }}</p>
          <a href="#" class="small text-danger" @click.stop="loansFile = null">Remove</a>
        </div>
      </div>

      <!-- Progress -->
      <div v-if="loansUploading" class="mb-3">
        <div class="progress" style="height: 6px;">
          <div
            class="progress-bar progress-bar-striped progress-bar-animated"
            role="progressbar"
            :style="{ width: loansProgress + '%' }"
          ></div>
        </div>
        <p class="small text-muted mt-1 mb-0">Processing file...</p>
      </div>

      <AppButton
        variant="primary"
        :loading="loansUploading"
        :disabled="!loansFile || loansUploading"
        @click="importFile('loans')"
      >
        <i class="bi bi-upload me-1"></i>Import Loans
      </AppButton>

      <!-- Results -->
      <div v-if="loansResult" class="mt-4">
        <div class="d-flex gap-3 mb-3">
          <div class="result-badge result-success">
            <i class="bi bi-check-circle me-1"></i>{{ loansResult.imported }} imported
          </div>
          <div v-if="loansResult.failed > 0" class="result-badge result-error">
            <i class="bi bi-exclamation-circle me-1"></i>{{ loansResult.failed }} failed
          </div>
        </div>

        <div v-if="loansResult.errors && loansResult.errors.length > 0">
          <h6 class="fw-semibold text-danger mb-2">Error Details</h6>
          <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0">
              <thead class="table-light">
                <tr>
                  <th style="width: 70px;">Row</th>
                  <th style="width: 140px;">Employee ID</th>
                  <th>Error</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(err, i) in loansResult.errors" :key="i">
                  <td>{{ err.row }}</td>
                  <td><code>{{ err.employee_id }}</code></td>
                  <td class="text-danger small">{{ err.error }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </AppCard>

    <!-- Import Benefits -->
    <AppCard>
      <template #header>
        <div class="d-flex align-items-center justify-content-between">
          <h5 class="mb-0 fw-semibold">
            <i class="bi bi-gift me-2 text-primary"></i>Import Benefits
          </h5>
          <AppButton variant="outline-primary" size="sm" @click="downloadTemplate('benefits')" :loading="downloadingBenefits">
            <i class="bi bi-download me-1"></i>Download Template
          </AppButton>
        </div>
      </template>

      <p class="text-muted small mb-3">
        Upload a CSV file with benefit records. Required columns: employee_id, benefit_type, amount.
        Optional: description, share_capital.
      </p>

      <!-- Dropzone -->
      <div
        class="upload-dropzone mb-3"
        :class="{ 'dropzone-active': benefitsDragOver, 'dropzone-has-file': benefitsFile }"
        @dragover.prevent="benefitsDragOver = true"
        @dragleave.prevent="benefitsDragOver = false"
        @drop.prevent="handleDrop($event, 'benefits')"
        @click="$refs.benefitsFileInput.click()"
      >
        <input
          ref="benefitsFileInput"
          type="file"
          accept=".csv,.xlsx,.xls"
          class="d-none"
          @change="handleFileSelect($event, 'benefits')"
        />
        <div v-if="!benefitsFile" class="text-center">
          <i class="bi bi-cloud-arrow-up fs-1 text-muted"></i>
          <p class="mb-0 mt-2 text-muted">Drag and drop your CSV file here, or click to browse</p>
          <p class="mb-0 small text-muted">Accepted formats: .csv, .xlsx, .xls (max 10MB)</p>
        </div>
        <div v-else class="text-center">
          <i class="bi bi-file-earmark-check fs-1 text-success"></i>
          <p class="mb-0 mt-2 fw-semibold">{{ benefitsFile.name }}</p>
          <p class="mb-0 small text-muted">{{ formatFileSize(benefitsFile.size) }}</p>
          <a href="#" class="small text-danger" @click.stop="benefitsFile = null">Remove</a>
        </div>
      </div>

      <!-- Progress -->
      <div v-if="benefitsUploading" class="mb-3">
        <div class="progress" style="height: 6px;">
          <div
            class="progress-bar progress-bar-striped progress-bar-animated"
            role="progressbar"
            :style="{ width: benefitsProgress + '%' }"
          ></div>
        </div>
        <p class="small text-muted mt-1 mb-0">Processing file...</p>
      </div>

      <AppButton
        variant="primary"
        :loading="benefitsUploading"
        :disabled="!benefitsFile || benefitsUploading"
        @click="importFile('benefits')"
      >
        <i class="bi bi-upload me-1"></i>Import Benefits
      </AppButton>

      <!-- Results -->
      <div v-if="benefitsResult" class="mt-4">
        <div class="d-flex gap-3 mb-3">
          <div class="result-badge result-success">
            <i class="bi bi-check-circle me-1"></i>{{ benefitsResult.imported }} imported
          </div>
          <div v-if="benefitsResult.failed > 0" class="result-badge result-error">
            <i class="bi bi-exclamation-circle me-1"></i>{{ benefitsResult.failed }} failed
          </div>
        </div>

        <div v-if="benefitsResult.errors && benefitsResult.errors.length > 0">
          <h6 class="fw-semibold text-danger mb-2">Error Details</h6>
          <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0">
              <thead class="table-light">
                <tr>
                  <th style="width: 70px;">Row</th>
                  <th style="width: 140px;">Employee ID</th>
                  <th>Error</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(err, i) in benefitsResult.errors" :key="i">
                  <td>{{ err.row }}</td>
                  <td><code>{{ err.employee_id }}</code></td>
                  <td class="text-danger small">{{ err.error }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </AppCard>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import admin from '@/services/admin'
import AppLayout from '@/components/layout/AppLayout.vue'
import AppCard from '@/components/ui/AppCard.vue'
import AppButton from '@/components/ui/AppButton.vue'

// Loans state
const loansFile = ref(null)
const loansDragOver = ref(false)
const loansUploading = ref(false)
const loansProgress = ref(0)
const loansResult = ref(null)
const downloadingLoans = ref(false)

// Benefits state
const benefitsFile = ref(null)
const benefitsDragOver = ref(false)
const benefitsUploading = ref(false)
const benefitsProgress = ref(0)
const benefitsResult = ref(null)
const downloadingBenefits = ref(false)

function handleDrop(event, type) {
  if (type === 'loans') {
    loansDragOver.value = false
    const file = event.dataTransfer.files[0]
    if (file && isValidFile(file)) {
      loansFile.value = file
    }
  } else {
    benefitsDragOver.value = false
    const file = event.dataTransfer.files[0]
    if (file && isValidFile(file)) {
      benefitsFile.value = file
    }
  }
}

function handleFileSelect(event, type) {
  const file = event.target.files[0]
  if (file && isValidFile(file)) {
    if (type === 'loans') {
      loansFile.value = file
    } else {
      benefitsFile.value = file
    }
  }
  event.target.value = ''
}

function isValidFile(file) {
  const validTypes = [
    'text/csv',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'text/plain',
  ]
  const validExtensions = ['.csv', '.xlsx', '.xls']
  const hasValidExtension = validExtensions.some((ext) => file.name.toLowerCase().endsWith(ext))
  const hasValidType = validTypes.includes(file.type) || file.type === ''
  return (hasValidType || hasValidExtension) && file.size <= 10 * 1024 * 1024
}

function formatFileSize(bytes) {
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
}

async function importFile(type) {
  const file = type === 'loans' ? loansFile.value : benefitsFile.value
  if (!file) return

  const formData = new FormData()
  formData.append('file', file)

  if (type === 'loans') {
    loansUploading.value = true
    loansProgress.value = 30
    loansResult.value = null
  } else {
    benefitsUploading.value = true
    benefitsProgress.value = 30
    benefitsResult.value = null
  }

  try {
    if (type === 'loans') {
      loansProgress.value = 60
    } else {
      benefitsProgress.value = 60
    }

    const serviceFn = type === 'loans' ? admin.importLoans : admin.importBenefits
    const response = await serviceFn(formData)

    if (type === 'loans') {
      loansProgress.value = 100
      loansResult.value = response.data.data
      loansFile.value = null
    } else {
      benefitsProgress.value = 100
      benefitsResult.value = response.data.data
      benefitsFile.value = null
    }
  } catch (error) {
    const message = error.response?.data?.message || 'Import failed. Please check your file and try again.'
    if (type === 'loans') {
      loansResult.value = { imported: 0, failed: 0, errors: [{ row: '-', employee_id: '-', error: message }] }
    } else {
      benefitsResult.value = { imported: 0, failed: 0, errors: [{ row: '-', employee_id: '-', error: message }] }
    }
  } finally {
    if (type === 'loans') {
      loansUploading.value = false
    } else {
      benefitsUploading.value = false
    }
  }
}

async function downloadTemplate(type) {
  try {
    if (type === 'loans') {
      downloadingLoans.value = true
      const response = await admin.downloadLoanTemplate()
      triggerDownload(response.data, 'loan_import_template.csv')
    } else {
      downloadingBenefits.value = true
      const response = await admin.downloadBenefitTemplate()
      triggerDownload(response.data, 'benefit_import_template.csv')
    }
  } catch (error) {
    console.error('Template download failed:', error)
  } finally {
    downloadingLoans.value = false
    downloadingBenefits.value = false
  }
}

function triggerDownload(blob, filename) {
  const url = window.URL.createObjectURL(new Blob([blob]))
  const link = document.createElement('a')
  link.href = url
  link.setAttribute('download', filename)
  document.body.appendChild(link)
  link.click()
  link.remove()
  window.URL.revokeObjectURL(url)
}
</script>

<style scoped>
.upload-dropzone {
  border: 2px dashed #d1d5db;
  border-radius: 12px;
  padding: 2rem;
  cursor: pointer;
  transition: all 0.2s ease;
  background: #fafafa;
}

.upload-dropzone:hover {
  border-color: #93c5fd;
  background: #eff6ff;
}

.dropzone-active {
  border-color: #3b82f6;
  background: #dbeafe;
}

.dropzone-has-file {
  border-color: #22c55e;
  border-style: solid;
  background: #f0fdf4;
}

.result-badge {
  display: inline-flex;
  align-items: center;
  padding: 0.4rem 0.75rem;
  border-radius: 8px;
  font-size: 0.875rem;
  font-weight: 600;
}

.result-success {
  background: #dcfce7;
  color: #166534;
}

.result-error {
  background: #fee2e2;
  color: #991b1b;
}
</style>
