<template>
  <div class="select-type-page">
    <div class="select-type-container">
      <div class="text-center mb-5">
        <i class="bi bi-bank2" style="font-size: 3rem; color: #fff;"></i>
        <h2 class="text-white fw-bold mt-2">PMBF Admin</h2>
        <p class="text-white-50">Select which group you want to manage</p>
      </div>

      <div class="row g-4 justify-content-center">
        <div class="col-sm-6 col-lg-3" v-for="opt in options" :key="opt.value">
          <!-- The card stays a div (it holds a heading and a paragraph, which
               a <button> may not contain) and the Manage button is stretched
               over it. That makes the whole card clickable while the control is
               a real, focusable, Enter/Space-operable button — as plain divs
               these could not be reached from a keyboard at all, and they gate
               the entire admin area. -->
          <div class="type-card" :class="opt.cardClass">
            <div class="type-icon-wrap" :class="opt.iconBg">
              <i :class="opt.icon"></i>
            </div>
            <h4 class="fw-bold mt-3 mb-1">{{ opt.label }}</h4>
            <p class="text-muted small mb-3">{{ opt.description }}</p>
            <div class="type-stats" v-if="stats">
              <span class="badge bg-light text-dark">
                <i class="bi bi-people me-1"></i>{{ opt.count(stats) }} members
              </span>
            </div>
            <div class="mt-3">
              <button
                type="button"
                class="btn btn-sm stretched-link"
                :class="opt.btnClass"
                :aria-label="`Manage ${opt.label}`"
                @click="selectType(opt.value)"
              >
                Manage <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i>
              </button>
            </div>
          </div>
        </div>
      </div>

      <div class="text-center mt-4">
        <button class="btn btn-outline-light btn-sm" @click="selectType('all')">
          <i class="bi bi-grid-3x3-gap me-1"></i>View All Members
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAdminContextStore } from '@/stores/adminContext'
import admin from '@/services/admin'

const router = useRouter()
const adminContext = useAdminContextStore()
const stats = ref(null)

const options = [
  {
    value: 'Permanent',
    label: 'Permanent',
    description: 'Manage permanent employees, consolidated loans, multi-purpose, emergency & hospitalization',
    icon: 'bi bi-building fs-1',
    iconBg: 'icon-green',
    cardClass: 'card-green',
    btnClass: 'btn-success',
    count: (s) => s?.by_type?.Permanent ?? 0,
  },
  {
    value: 'Contract of Service',
    label: 'COS-Enrolled',
    description: 'Manage enrolled Contract of Service employees, salary loans, co-maker requirements & contract-based terms',
    icon: 'bi bi-file-earmark-text fs-1',
    iconBg: 'icon-orange',
    cardClass: 'card-orange',
    count: (s) => s?.by_type?.['Contract of Service'] ?? 0,
  },
  {
    value: 'Non-Member',
    label: 'COS-Non Enrolled',
    description: 'Manage non-enrolled Contract of Service registrations, separate interest rates & loan limits',
    icon: 'bi bi-person-dash fs-1',
    iconBg: 'icon-gray',
    cardClass: 'card-gray',
    btnClass: 'btn-secondary',
    count: (s) => s?.by_type?.['Non-Member'] ?? 0,
  },
  {
    value: 'PMBF Employee',
    label: 'PMBF Employee',
    description: 'Manage staff employed by PMBF itself rather than PhilRice — added by hand, no HRIS record',
    icon: 'bi bi-person-badge fs-1',
    iconBg: 'icon-purple',
    cardClass: 'card-purple',
    btnClass: 'btn-primary',
    count: (s) => s?.by_type?.['PMBF Employee'] ?? 0,
  },
]

function selectType(type) {
  adminContext.setType(type)
  router.push('/dashboard')
}

onMounted(async () => {
  try {
    const { data } = await admin.getDashboard()
    const d = data.data ?? data
    // Build counts by type from members
    const members = d.members ?? {}
    stats.value = {
      by_type: members.by_type ?? {},
      total: members.total ?? 0,
    }
  } catch {
    // ignore — stats are optional
  }
})
</script>

<style scoped>
.select-type-page {
  min-height: 100vh;
  background: linear-gradient(135deg, #1e3a5f 0%, #1e40af 50%, #3b82f6 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 40px 20px;
}

.select-type-container {
  max-width: 1120px;
  width: 100%;
}

.type-card {
  /* Token-driven so the card follows the active theme — a hardcoded #fff
     rendered light-on-light in dark mode. */
  background: var(--pmbf-surface, #fff);
  color: var(--pmbf-text, inherit);
  border-radius: 16px;
  padding: 26px 20px;
  text-align: center;
  cursor: pointer;
  transition: all 0.2s ease;
  border: 3px solid transparent;
  height: 100%;
  /* Anchors the stretched Manage button to the whole card. */
  position: relative;
}

/* The focus ring belongs on the card, since the button covers it. */
.type-card:has(:focus-visible) {
  outline: 3px solid #fff;
  outline-offset: 3px;
}

.type-card:hover {
  transform: translateY(-6px);
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
}

@media (prefers-reduced-motion: reduce) {
  .type-card { transition: none; }
  .type-card:hover { transform: none; }
}

.type-card.card-green:hover { border-color: #059669; }
.type-card.card-orange:hover { border-color: #d97706; }
.type-card.card-gray:hover { border-color: #6b7280; }
.type-card.card-purple:hover { border-color: #7c3aed; }

.type-icon-wrap {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto;
  color: #fff;
}

.icon-green { background: linear-gradient(135deg, #059669, #10b981); }
.icon-orange { background: linear-gradient(135deg, #d97706, #f59e0b); }
.icon-gray { background: linear-gradient(135deg, #4b5563, #6b7280); }
.icon-purple { background: linear-gradient(135deg, #6d28d9, #8b5cf6); }
</style>
