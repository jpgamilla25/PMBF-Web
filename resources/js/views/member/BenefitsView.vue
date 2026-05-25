<template>
  <AppLayout>
    <div class="d-flex align-items-center justify-content-between mb-4">
      <h4 class="fw-bold mb-0">Benefits</h4>
    </div>

    <AppLoading :loading="loading" text="Loading benefits..." />

    <template v-if="!loading">
      <!-- Share Capital (Permanent members) -->
      <div v-if="benefitsData.share_capital != null" class="mb-4">
        <AppCard>
          <div class="text-center py-3">
            <div class="text-muted small text-uppercase fw-semibold mb-1">Share Capital</div>
            <div class="display-4 fw-bold text-primary">
              &#8369;{{ Number(benefitsData.share_capital ?? 0).toLocaleString() }}
            </div>
            <div class="text-muted small mt-1">Your current share capital balance</div>
          </div>
        </AppCard>
      </div>

      <!-- Benefits Table -->
      <AppCard title="Benefits Summary" :padding="false">
        <AppTable
          :columns="columns"
          :items="benefitsData.benefits ?? []"
          empty-text="No benefits available."
        >
          <template #cell(type)="{ value }">
            <span class="fw-semibold">{{ formatType(value) }}</span>
          </template>
          <template #cell(amount)="{ value }">
            <span v-if="value != null" class="fw-medium">&#8369;{{ Number(value).toLocaleString() }}</span>
            <span v-else class="text-muted">-</span>
          </template>
        </AppTable>
      </AppCard>
    </template>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useLoading } from '@/composables/useLoading'
import membersService from '@/services/members'
import AppLayout from '@/components/layout/AppLayout.vue'
import AppCard from '@/components/ui/AppCard.vue'
import AppTable from '@/components/ui/AppTable.vue'
import AppLoading from '@/components/ui/AppLoading.vue'

const { loading, withLoading } = useLoading()

const benefitsData = ref({
  share_capital: null,
  benefits: [],
})

const columns = [
  { key: 'type', label: 'Type' },
  { key: 'description', label: 'Description' },
  { key: 'amount', label: 'Amount' },
]

function formatType(type) {
  if (!type) return '-'
  return type.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
}

onMounted(() => {
  withLoading(async () => {
    const response = await membersService.getBenefits()
    const data = response.data.data ?? response.data
    if (Array.isArray(data)) {
      benefitsData.value.benefits = data
    } else {
      benefitsData.value = {
        share_capital: data.share_capital ?? null,
        benefits: data.benefits ?? [],
      }
    }
  })
})
</script>
