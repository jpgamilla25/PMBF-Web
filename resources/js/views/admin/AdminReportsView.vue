<template>
  <AppLayout>
    <h4 class="fw-bold mb-1">Reports</h4>
    <p class="text-muted small mb-4">Generate, filter, and export data reports as PDF or CSV.</p>

    <div class="row g-4">
      <div v-for="r in reportTypes" :key="r.key" class="col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100 report-card" @click="$router.push(r.route)">
          <div class="card-body d-flex flex-column">
            <div class="report-icon mb-3" :style="{ background: r.bg }">
              <i :class="r.icon" :style="{ color: r.color }"></i>
            </div>
            <h6 class="fw-bold mb-1">{{ r.title }}</h6>
            <p class="text-muted small flex-grow-1 mb-3">{{ r.desc }}</p>
            <div class="d-flex gap-2">
              <template v-if="r.key === 'ledger'">
                <span class="badge" :style="{ background: r.bg, color: r.color }">
                  <i class="bi bi-window me-1"></i>Web
                </span>
                <span class="badge" :style="{ background: r.bg, color: r.color }">
                  <i class="bi bi-printer me-1"></i>Print
                </span>
              </template>
              <template v-else-if="r.key === 'notice-of-deduction'">
                <span class="badge" :style="{ background: r.bg, color: r.color }">
                  <i class="bi bi-filetype-pdf me-1"></i>PDF
                </span>
              </template>
              <template v-else>
                <span class="badge" :style="{ background: r.bg, color: r.color }">
                  <i class="bi bi-filetype-pdf me-1"></i>PDF
                </span>
                <span class="badge" :style="{ background: r.bg, color: r.color }">
                  <i class="bi bi-filetype-csv me-1"></i>CSV
                </span>
              </template>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { useRouter } from 'vue-router'
import AppLayout from '@/components/layout/AppLayout.vue'

const router = useRouter()

const reportTypes = [
  {
    key: 'loans',
    title: 'Loans Report',
    desc: 'All loan applications with status, amounts, amortization, and balances. Filter by type, status, date range.',
    icon: 'bi bi-cash-stack fs-4',
    route: '/admin/reports/loans',
    color: '#1e40af',
    bg: '#dbeafe',
  },
  {
    key: 'payments',
    title: 'Payments Report',
    desc: 'All payment transactions with OR numbers, methods, and borrower details. Filter by date and method.',
    icon: 'bi bi-credit-card fs-4',
    route: '/admin/reports/payments',
    color: '#065f46',
    bg: '#d1fae5',
  },
  {
    key: 'members',
    title: 'Members Report',
    desc: 'Member directory with employment type, department, loan activity, and status.',
    icon: 'bi bi-people-fill fs-4',
    route: '/admin/reports/members',
    color: '#6d28d9',
    bg: '#ede9fe',
  },
  {
    key: 'shares',
    title: 'Share Capital Report',
    desc: 'Monthly share capital per member for a given year with totals and summaries.',
    icon: 'bi bi-pie-chart-fill fs-4',
    route: '/admin/reports/shares',
    color: '#92400e',
    bg: '#fef3c7',
  },
  {
    key: 'ledger',
    title: 'Loan Ledger',
    desc: 'Per-member loan ledger (SC & Permanent) — each loan with principal, interest, total, amortization, and a payment schedule with running balance.',
    icon: 'bi bi-journal-text fs-4',
    route: '/admin/reports/ledger',
    color: '#0e7490',
    bg: '#cffafe',
  },
  {
    key: 'notice-of-deduction',
    title: 'Notice of Deduction',
    desc: 'Per-division, per-cutoff payroll deduction notice — one row per active salary loan. Printable form ready to send to payroll.',
    icon: 'bi bi-file-earmark-ruled fs-4',
    route: '/admin/reports/notice-of-deduction',
    color: '#7c2d12',
    bg: '#fed7aa',
  },
]
</script>

<style scoped>
.report-card {
  cursor: pointer;
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.report-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 8px 24px rgba(0,0,0,0.1) !important;
}
.report-icon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
}
</style>
