<template>
  <AppLayout>
    <h4 class="fw-bold mb-1">My Premium Contributions</h4>
    <p class="text-muted small mb-4">
      As a Contract of Service member, your monthly payroll deduction goes toward the PMBF
      coverage premium — non-refundable benefit-fund coverage rather than share capital equity.
    </p>

    <AppLoading :loading="loading" text="Loading premiums..." />

    <template v-if="!loading">
      <!-- Summary Cards -->
      <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-6">
          <AppStatCard title="Current Monthly Premium" :value="data.current_monthly ?? 0" icon="bi bi-calendar-check" color="primary" prefix="₱" />
        </div>
        <div class="col-sm-6 col-lg-6">
          <AppStatCard title="Total Premium Contributions" :value="data.total_premium ?? 0" icon="bi bi-shield-check" color="success" prefix="₱" />
        </div>
      </div>

      <!-- Year Filter -->
      <div class="d-flex align-items-center gap-3 mb-3">
        <label class="fw-semibold small">Year:</label>
        <select v-model="selectedYear" class="form-select form-select-sm" style="width: 120px;" @change="fetchData">
          <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
        </select>
      </div>

      <!-- Monthly History -->
      <AppCard title="Monthly Premium History">
        <table class="table table-sm mb-0">
          <thead>
            <tr>
              <th>Month</th>
              <th>Amount</th>
              <th>Remarks</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="m in 12" :key="m">
              <td>{{ monthNames[m - 1] }} {{ selectedYear }}</td>
              <td>
                <strong v-if="getMonthData(m)">&#8369;{{ Number(getMonthData(m).amount).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}</strong>
                <span v-else class="text-muted">-</span>
              </td>
              <td class="text-muted small">{{ getMonthData(m)?.remarks ?? '' }}</td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="fw-bold">
              <td>Total</td>
              <td>&#8369;{{ yearTotal.toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}</td>
              <td></td>
            </tr>
          </tfoot>
        </table>
      </AppCard>
    </template>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import premiums from '@/services/premiums'
import AppLayout from '@/components/layout/AppLayout.vue'
import AppStatCard from '@/components/ui/AppStatCard.vue'
import AppCard from '@/components/ui/AppCard.vue'
import AppLoading from '@/components/ui/AppLoading.vue'

const loading = ref(true)
const data = ref({})
const selectedYear = ref(new Date().getFullYear())

const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']
const yearOptions = Array.from({ length: 5 }, (_, i) => new Date().getFullYear() - i)

function getMonthData(month) {
  return (data.value.history ?? []).find(h => h.month === month)
}

const yearTotal = computed(() => {
  return (data.value.history ?? []).reduce((sum, h) => sum + Number(h.amount ?? 0), 0)
})

async function fetchData() {
  loading.value = true
  try {
    const { data: res } = await premiums.getMyPremiums({ year: selectedYear.value })
    data.value = res.data ?? res
  } catch { /* ignore */ }
  finally { loading.value = false }
}

onMounted(fetchData)
</script>
