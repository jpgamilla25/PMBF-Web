<template>
  <div class="cost-page">
    <!-- Navbar -->
    <nav class="cost-nav">
      <div class="container-fluid d-flex align-items-center justify-content-between px-4">
        <span class="cost-nav-brand">
          <i class="bi bi-bank me-2"></i>PMBF
        </span>
        <span class="cost-nav-title d-none d-md-block">System Proposal — Cost Breakdown</span>
        <button class="btn btn-sm btn-outline-light" @click="goBack">
          <i class="bi bi-arrow-left me-1"></i>Back
        </button>
      </div>
    </nav>

    <div class="cost-body">
      <!-- Hero -->
      <div class="cost-hero">
        <div class="container">
          <div class="row align-items-center">
            <div class="col-lg-7">
              <div class="badge-pill mb-3">PMBF Financial Management System · v1.0</div>
              <h1 class="hero-title">Project Cost Breakdown</h1>
              <p class="hero-sub">Mobile Application (Android) &amp; Web-Based System</p>
              <div class="hero-meta d-flex flex-wrap gap-4 mt-4">
                <div>
                  <div class="meta-label">Date Prepared</div>
                  <div class="meta-value">March 30, 2026</div>
                </div>
                <div>
                  <div class="meta-label">Prepared By</div>
                  <div class="meta-value">Jayson P. Gamilla</div>
                </div>
                <div>
                  <div class="meta-label">Project Duration</div>
                  <div class="meta-value">~4.5 months</div>
                </div>
              </div>
            </div>
            <div class="col-lg-5 mt-4 mt-lg-0">
              <div class="total-card">
                <div class="total-label">Total Proposed Budget</div>
                <div class="total-amount">₱250,000<span class="total-dec">.00</span></div>
                <div class="total-sub">PHP Two Hundred Fifty Thousand</div>
                <div class="total-items mt-3 d-flex justify-content-around">
                  <div class="text-center">
                    <div class="fw-bold fs-5">{{ items.length }}</div>
                    <div style="font-size:0.75rem;opacity:.8">Line Items</div>
                  </div>
                  <div class="text-center">
                    <div class="fw-bold fs-5">2</div>
                    <div style="font-size:0.75rem;opacity:.8">Platforms</div>
                  </div>
                  <div class="text-center">
                    <div class="fw-bold fs-5">6</div>
                    <div style="font-size:0.75rem;opacity:.8">Months Est.</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Main Content -->
      <div class="container py-5">
        <div class="row g-5">
          <!-- Chart -->
          <div class="col-lg-4">
            <div class="chart-card">
              <h6 class="chart-title">Budget Distribution</h6>
              <div class="chart-wrap">
                <Doughnut :data="chartData" :options="chartOptions" />
              </div>
              <div class="chart-legend mt-3">
                <div v-for="item in items" :key="item.label" class="legend-row">
                  <span class="legend-dot" :style="{ background: item.color }"></span>
                  <span class="legend-label">{{ item.label }}</span>
                  <span class="legend-pct">{{ pct(item.amount) }}%</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Breakdown List -->
          <div class="col-lg-8">
            <!-- Category: Mobile -->
            <div class="cat-header" style="--cat-color: #3b82f6;">
              <i class="bi bi-phone-fill me-2"></i>Mobile Application (Android)
            </div>
            <div v-for="item in mobileItems" :key="item.label" class="breakdown-card" :style="{'--item-color': item.color}">
              <div class="d-flex align-items-start gap-3">
                <div class="item-icon" :style="{ background: item.color + '20', color: item.color }">
                  <i :class="item.icon"></i>
                </div>
                <div class="flex-grow-1">
                  <div class="d-flex justify-content-between align-items-start">
                    <div>
                      <div class="item-label">{{ item.label }}</div>
                      <div class="item-desc">{{ item.desc }}</div>
                    </div>
                    <div class="item-amount">₱{{ fmt(item.amount) }}</div>
                  </div>
                  <div class="progress-wrap mt-2">
                    <div class="progress-bar-bg">
                      <div class="progress-bar-fill" :style="{ width: pct(item.amount) + '%', background: item.color }"></div>
                    </div>
                    <span class="progress-pct">{{ pct(item.amount) }}%</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Category: Web -->
            <div class="cat-header mt-4" style="--cat-color: #6366f1;">
              <i class="bi bi-globe me-2"></i>Web-Based System
            </div>
            <div v-for="item in webItems" :key="item.label" class="breakdown-card" :style="{'--item-color': item.color}">
              <div class="d-flex align-items-start gap-3">
                <div class="item-icon" :style="{ background: item.color + '20', color: item.color }">
                  <i :class="item.icon"></i>
                </div>
                <div class="flex-grow-1">
                  <div class="d-flex justify-content-between align-items-start">
                    <div>
                      <div class="item-label">{{ item.label }}</div>
                      <div class="item-desc">{{ item.desc }}</div>
                    </div>
                    <div class="item-amount">₱{{ fmt(item.amount) }}</div>
                  </div>
                  <div class="progress-wrap mt-2">
                    <div class="progress-bar-bg">
                      <div class="progress-bar-fill" :style="{ width: pct(item.amount) + '%', background: item.color }"></div>
                    </div>
                    <span class="progress-pct">{{ pct(item.amount) }}%</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Category: Other Costs -->
            <div class="cat-header mt-4" style="--cat-color: #10b981;">
              <i class="bi bi-tools me-2"></i>Other Costs
            </div>
            <div v-for="item in otherItems" :key="item.label" class="breakdown-card" :style="{'--item-color': item.color}">
              <div class="d-flex align-items-start gap-3">
                <div class="item-icon" :style="{ background: item.color + '20', color: item.color }">
                  <i :class="item.icon"></i>
                </div>
                <div class="flex-grow-1">
                  <div class="d-flex justify-content-between align-items-start">
                    <div>
                      <div class="item-label">{{ item.label }}</div>
                      <div class="item-desc">{{ item.desc }}</div>
                    </div>
                    <div class="item-amount">₱{{ fmt(item.amount) }}</div>
                  </div>
                  <div class="progress-wrap mt-2">
                    <div class="progress-bar-bg">
                      <div class="progress-bar-fill" :style="{ width: pct(item.amount) + '%', background: item.color }"></div>
                    </div>
                    <span class="progress-pct">{{ pct(item.amount) }}%</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Total Row -->
            <div class="total-row">
              <span class="total-row-label">TOTAL BUDGET</span>
              <span class="total-row-amount">₱250,000.00</span>
            </div>
          </div>
        </div>

        <!-- Timeline Section -->
        <div class="mt-5">
          <h5 class="section-title">Project Timeline</h5>
          <div class="row g-3 mt-1">
            <div v-for="phase in phases" :key="phase.name" class="col-sm-6 col-lg-4">
              <div class="phase-card">
                <div class="phase-num" :style="{ background: phase.color }">{{ phase.num }}</div>
                <div class="phase-name">{{ phase.name }}</div>
                <div class="phase-desc">{{ phase.desc }}</div>
                <div class="phase-date"><i class="bi bi-calendar3 me-1"></i>{{ phase.date }}</div>
              </div>
            </div>
          </div>
          <div class="timeline-total mt-3">
            <i class="bi bi-clock me-2"></i>
            Estimated Total Duration: <strong>March 30 – August 15, 2026</strong> &nbsp;(approx. 4.5 months)
          </div>
        </div>

        <!-- Footer -->
        <div class="cost-footer">
          <div>PMBF Financial Management System Proposal &mdash; v1.0</div>
          <div class="mt-1 opacity-75">Prepared by Jayson P. Gamilla &nbsp;·&nbsp; March 30, 2026</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

function goBack() {
  if (window.history.length > 1) window.history.back()
  else window.close()
}
import { Doughnut } from 'vue-chartjs'
import {
  Chart as ChartJS,
  ArcElement,
  Tooltip,
  Legend,
} from 'chart.js'

ChartJS.register(ArcElement, Tooltip, Legend)

const TOTAL = 250000

const mobileItems = [
  {
    label: 'Mobile App Development',
    desc: 'Registration, loan application, notifications, profile, dependents & claims',
    icon: 'bi bi-phone',
    amount: 73000,
    color: '#3b82f6',
  },
]

const webItems = [
  {
    label: 'Web System Development',
    desc: 'Loan application, member management, approval workflow, reports, payments, configuration',
    icon: 'bi bi-globe2',
    amount: 117000,
    color: '#6366f1',
  },
]

const otherItems = [
  {
    label: 'UI/UX Design',
    desc: 'Interface design for mobile and web platforms',
    icon: 'bi bi-palette',
    amount: 13000,
    color: '#f59e0b',
  },
  {
    label: 'Testing & QA',
    desc: 'System testing, quality assurance, and bug fixes',
    icon: 'bi bi-bug',
    amount: 13000,
    color: '#ef4444',
  },
  {
    label: 'Deployment & Documentation',
    desc: 'Server setup, app store deployment, and user documentation',
    icon: 'bi bi-cloud-upload',
    amount: 8000,
    color: '#10b981',
  },
  {
    label: 'Training',
    desc: 'User training and orientation sessions for administrators and staff',
    icon: 'bi bi-mortarboard',
    amount: 10000,
    color: '#8b5cf6',
  },
  {
    label: 'Contingency',
    desc: 'Buffer for revisions, change requests, and unexpected costs',
    icon: 'bi bi-shield-check',
    amount: 16000,
    color: '#6b7280',
  },
]

const items = [...mobileItems, ...webItems, ...otherItems]

function pct(amount) {
  return ((amount / TOTAL) * 100).toFixed(1)
}

function fmt(amount) {
  return Number(amount).toLocaleString('en-PH')
}

const chartData = computed(() => ({
  labels: items.map(i => i.label),
  datasets: [{
    data: items.map(i => i.amount),
    backgroundColor: items.map(i => i.color),
    borderColor: '#fff',
    borderWidth: 3,
    hoverOffset: 8,
  }],
}))

const chartOptions = {
  responsive: true,
  maintainAspectRatio: true,
  cutout: '65%',
  plugins: {
    legend: { display: false },
    tooltip: {
      callbacks: {
        label: (ctx) => ` ₱${Number(ctx.raw).toLocaleString('en-PH')} (${pct(ctx.raw)}%)`,
      },
    },
  },
}

const phases = [
  { num: '01', name: 'Planning & Design', desc: 'Requirements, UI/UX design, system setup', date: 'Mar 30 – Apr 12', color: '#3b82f6' },
  { num: '02', name: 'Registration & Profiling', desc: 'Login, biometrics/PIN, profiling, HRIS integration', date: 'Apr 13 – Apr 30', color: '#6366f1' },
  { num: '03', name: 'Loan Module', desc: 'Loan application, approval workflow, OTP, notifications', date: 'May 1 – May 31', color: '#8b5cf6' },
  { num: '04', name: 'Supporting Features', desc: 'Dependents, claims, payments, data import, config', date: 'Jun 1 – Jun 30', color: '#f59e0b' },
  { num: '05', name: 'Reports & Testing', desc: 'Reports module, full system testing, bug fixes, UAT', date: 'Jul 1 – Jul 31', color: '#ef4444' },
  { num: '06', name: 'Deployment & Training', desc: 'Server setup, app deployment, user training, documentation', date: 'Aug 1 – Aug 15', color: '#10b981' },
]
</script>

<style scoped>
* { box-sizing: border-box; }

.cost-page {
  min-height: 100vh;
  background: #f8fafc;
  font-family: 'Segoe UI', system-ui, sans-serif;
}

/* Nav */
.cost-nav {
  background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
  height: 60px;
  display: flex;
  align-items: center;
  position: sticky;
  top: 0;
  z-index: 100;
  box-shadow: 0 2px 8px rgba(30,64,175,.3);
}
.cost-nav-brand {
  color: #fff;
  font-size: 1.1rem;
  font-weight: 700;
  text-decoration: none;
}
.cost-nav-title {
  color: rgba(255,255,255,.85);
  font-size: 0.85rem;
  letter-spacing: 0.02em;
}

/* Hero */
.cost-hero {
  background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 50%, #2563eb 100%);
  padding: 60px 0 80px;
  color: #fff;
  position: relative;
  overflow: hidden;
}
.cost-hero::before {
  content: '';
  position: absolute;
  top: -50%;
  right: -10%;
  width: 600px;
  height: 600px;
  background: rgba(255,255,255,.04);
  border-radius: 50%;
}
.cost-hero::after {
  content: '';
  position: absolute;
  bottom: -30%;
  left: -5%;
  width: 400px;
  height: 400px;
  background: rgba(255,255,255,.03);
  border-radius: 50%;
}

.badge-pill {
  display: inline-block;
  background: rgba(255,255,255,.15);
  color: rgba(255,255,255,.9);
  padding: 5px 14px;
  border-radius: 20px;
  font-size: 0.75rem;
  letter-spacing: 0.03em;
  font-weight: 500;
  backdrop-filter: blur(4px);
}
.hero-title {
  font-size: clamp(1.8rem, 4vw, 2.8rem);
  font-weight: 800;
  line-height: 1.2;
  margin: 8px 0 4px;
}
.hero-sub {
  font-size: 1rem;
  opacity: .8;
  margin: 0;
}
.meta-label { font-size: 0.7rem; opacity: .65; text-transform: uppercase; letter-spacing: .05em; }
.meta-value { font-size: 0.9rem; font-weight: 600; margin-top: 2px; }

.total-card {
  background: rgba(255,255,255,.12);
  backdrop-filter: blur(12px);
  border: 1px solid rgba(255,255,255,.2);
  border-radius: 20px;
  padding: 28px;
  text-align: center;
}
.total-label { font-size: 0.75rem; opacity: .7; text-transform: uppercase; letter-spacing: .08em; }
.total-amount {
  font-size: 2.8rem;
  font-weight: 900;
  line-height: 1.1;
  margin: 6px 0 4px;
  letter-spacing: -0.02em;
}
.total-dec { font-size: 1.4rem; opacity: .7; }
.total-sub { font-size: 0.72rem; opacity: .65; font-style: italic; }

/* Chart Card */
.chart-card {
  background: #fff;
  border-radius: 16px;
  padding: 24px;
  box-shadow: 0 2px 12px rgba(0,0,0,.06);
  position: sticky;
  top: 80px;
}
.chart-title { font-weight: 700; color: #1e293b; margin-bottom: 16px; font-size: 0.9rem; text-transform: uppercase; letter-spacing: .05em; }
.chart-wrap { max-width: 240px; margin: 0 auto; }
.chart-legend { margin-top: 16px; }
.legend-row {
  display: flex;
  align-items: center;
  padding: 5px 0;
  border-bottom: 1px solid #f1f5f9;
  gap: 8px;
}
.legend-row:last-child { border-bottom: none; }
.legend-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
.legend-label { font-size: 0.78rem; color: #475569; flex-grow: 1; line-height: 1.3; }
.legend-pct { font-size: 0.75rem; font-weight: 700; color: #1e293b; }

/* Category header */
.cat-header {
  display: flex;
  align-items: center;
  font-size: 0.78rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .08em;
  color: var(--cat-color);
  margin-bottom: 12px;
  padding-left: 2px;
}

/* Breakdown Card */
.breakdown-card {
  background: #fff;
  border-radius: 14px;
  padding: 18px 20px;
  margin-bottom: 10px;
  box-shadow: 0 1px 6px rgba(0,0,0,.05);
  border-left: 3px solid var(--item-color);
  transition: box-shadow .15s ease, transform .15s ease;
}
.breakdown-card:hover {
  box-shadow: 0 4px 16px rgba(0,0,0,.1);
  transform: translateX(2px);
}
.item-icon {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1rem;
  flex-shrink: 0;
}
.item-label { font-weight: 700; color: #1e293b; font-size: 0.92rem; }
.item-desc { font-size: 0.78rem; color: #64748b; margin-top: 2px; line-height: 1.4; }
.item-amount {
  font-weight: 800;
  font-size: 1rem;
  color: #1e293b;
  white-space: nowrap;
  margin-left: 16px;
}

.progress-wrap { display: flex; align-items: center; gap: 8px; }
.progress-bar-bg {
  flex-grow: 1;
  height: 6px;
  background: #f1f5f9;
  border-radius: 3px;
  overflow: hidden;
}
.progress-bar-fill {
  height: 100%;
  border-radius: 3px;
  transition: width .6s ease;
}
.progress-pct { font-size: 0.72rem; color: #94a3b8; font-weight: 600; white-space: nowrap; }

/* Total Row */
.total-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: linear-gradient(135deg, #1e40af, #2563eb);
  color: #fff;
  border-radius: 14px;
  padding: 18px 24px;
  margin-top: 16px;
}
.total-row-label { font-size: 0.78rem; font-weight: 700; letter-spacing: .08em; opacity: .85; }
.total-row-amount { font-size: 1.5rem; font-weight: 900; letter-spacing: -0.01em; }

/* Section Title */
.section-title {
  font-weight: 800;
  color: #1e293b;
  font-size: 1.05rem;
  padding-bottom: 10px;
  border-bottom: 2px solid #e2e8f0;
}

/* Phase cards */
.phase-card {
  background: #fff;
  border-radius: 14px;
  padding: 20px;
  box-shadow: 0 1px 6px rgba(0,0,0,.06);
  height: 100%;
  transition: box-shadow .15s ease;
}
.phase-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,.1); }
.phase-num {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border-radius: 8px;
  color: #fff;
  font-weight: 800;
  font-size: 0.8rem;
  margin-bottom: 10px;
}
.phase-name { font-weight: 700; color: #1e293b; font-size: 0.88rem; margin-bottom: 4px; }
.phase-desc { font-size: 0.76rem; color: #64748b; line-height: 1.4; margin-bottom: 10px; }
.phase-date { font-size: 0.72rem; color: #94a3b8; font-weight: 600; }

.timeline-total {
  background: #f1f5f9;
  border-radius: 10px;
  padding: 12px 18px;
  font-size: 0.85rem;
  color: #475569;
}

/* Footer */
.cost-footer {
  text-align: center;
  margin-top: 60px;
  padding: 30px;
  border-top: 1px solid #e2e8f0;
  color: #94a3b8;
  font-size: 0.8rem;
}
</style>
