<script setup lang="ts">
import { useAuth } from '@/composables/useAuth'
import { useI18n } from 'vue-i18n'

const { user } = useAuth()
const { t } = useI18n()

// Mock affiliate stats - in a real app, this would come from an API
const affiliateStats = ref({
  totalCommissions: 2450.75,
  pendingCommissions: 320.50,
  totalOrders: 156,
  conversionRate: 3.2,
  totalClicks: 4875,
  activeLinks: 12,
  monthlyEarnings: [
    { month: 'Jan', earnings: 180.50 },
    { month: 'Feb', earnings: 220.75 },
    { month: 'Mar', earnings: 195.25 },
    { month: 'Apr', earnings: 310.80 },
    { month: 'May', earnings: 275.40 },
    { month: 'Jun', earnings: 320.50 }
  ]
})

// Recent orders/commissions
const recentCommissions = ref([
  {
    id: 'ORD-001',
    product: 'Premium Package',
    commission: 45.50,
    status: 'paid',
    date: new Date(Date.now() - 86400000)
  },
  {
    id: 'ORD-002', 
    product: 'Basic Package',
    commission: 25.00,
    status: 'pending',
    date: new Date(Date.now() - 172800000)
  },
  {
    id: 'ORD-003',
    product: 'Enterprise Package',
    commission: 85.75,
    status: 'paid',
    date: new Date(Date.now() - 259200000)
  }
])

// Chart data for earnings
const chartData = computed(() => ({
  labels: affiliateStats.value.monthlyEarnings.map(item => item.month),
  datasets: [{
    label: t('monthly_earnings'),
    data: affiliateStats.value.monthlyEarnings.map(item => item.earnings),
    borderColor: 'rgb(var(--v-theme-primary))',
    backgroundColor: 'rgba(var(--v-theme-primary), 0.1)',
    tension: 0.4
  }]
}))

// Status color helper
const getStatusColor = (status: string) => {
  switch (status) {
    case 'paid': return 'success'
    case 'pending': return 'warning'
    case 'cancelled': return 'error'
    default: return 'secondary'
  }
}

// Format currency
const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD'
  }).format(amount)
}
</script>

<template>
  <div v-if="user?.roles?.includes('affiliate')">
    <!-- Stats Cards -->
    <VRow class="mb-6">
      <VCol cols="12" sm="6" lg="3">
        <VCard>
          <VCardText>
            <div class="d-flex align-center">
              <VAvatar
                color="primary"
                variant="tonal"
                rounded
                size="40"
                class="me-4"
              >
                <VIcon icon="tabler-currency-dollar" />
              </VAvatar>
              
              <div>
                <p class="text-body-2 mb-0">{{ t('total_commissions') }}</p>
                <h5 class="text-h5 text-primary">
                  {{ formatCurrency(affiliateStats.totalCommissions) }}
                </h5>
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" lg="3">
        <VCard>
          <VCardText>
            <div class="d-flex align-center">
              <VAvatar
                color="warning"
                variant="tonal"
                rounded
                size="40"
                class="me-4"
              >
                <VIcon icon="tabler-clock" />
              </VAvatar>
              
              <div>
                <p class="text-body-2 mb-0">{{ t('pending_commissions') }}</p>
                <h5 class="text-h5 text-warning">
                  {{ formatCurrency(affiliateStats.pendingCommissions) }}
                </h5>
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" lg="3">
        <VCard>
          <VCardText>
            <div class="d-flex align-center">
              <VAvatar
                color="success"
                variant="tonal"
                rounded
                size="40"
                class="me-4"
              >
                <VIcon icon="tabler-shopping-cart" />
              </VAvatar>
              
              <div>
                <p class="text-body-2 mb-0">{{ t('total_orders') }}</p>
                <h5 class="text-h5 text-success">
                  {{ affiliateStats.totalOrders }}
                </h5>
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" lg="3">
        <VCard>
          <VCardText>
            <div class="d-flex align-center">
              <VAvatar
                color="info"
                variant="tonal"
                rounded
                size="40"
                class="me-4"
              >
                <VIcon icon="tabler-percentage" />
              </VAvatar>
              
              <div>
                <p class="text-body-2 mb-0">{{ t('conversion_rate') }}</p>
                <h5 class="text-h5 text-info">
                  {{ affiliateStats.conversionRate }}%
                </h5>
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Earnings Chart -->
    <VRow class="mb-6">
      <VCol cols="12" lg="8">
        <VCard>
          <VCardItem>
            <VCardTitle>{{ t('monthly_earnings_trend') }}</VCardTitle>
            <VCardSubtitle>{{ t('last_6_months') }}</VCardSubtitle>
          </VCardItem>
          
          <VCardText>
            <div class="earnings-chart">
              <!-- Chart would go here - using a simple table for now -->
              <VTable>
                <thead>
                  <tr>
                    <th>{{ t('month') }}</th>
                    <th>{{ t('earnings') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="item in affiliateStats.monthlyEarnings"
                    :key="item.month"
                  >
                    <td>{{ item.month }}</td>
                    <td class="text-success font-weight-medium">
                      {{ formatCurrency(item.earnings) }}
                    </td>
                  </tr>
                </tbody>
              </VTable>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" lg="4">
        <VCard>
          <VCardItem>
            <VCardTitle>{{ t('quick_stats') }}</VCardTitle>
          </VCardItem>
          
          <VCardText>
            <VList>
              <VListItem>
                <template #prepend>
                  <VIcon icon="tabler-mouse" class="me-3" />
                </template>
                <VListItemTitle>{{ t('total_clicks') }}</VListItemTitle>
                <VListItemSubtitle>{{ affiliateStats.totalClicks.toLocaleString() }}</VListItemSubtitle>
              </VListItem>

              <VListItem>
                <template #prepend>
                  <VIcon icon="tabler-link" class="me-3" />
                </template>
                <VListItemTitle>{{ t('active_links') }}</VListItemTitle>
                <VListItemSubtitle>{{ affiliateStats.activeLinks }}</VListItemSubtitle>
              </VListItem>

              <VListItem>
                <template #prepend>
                  <VIcon icon="tabler-trending-up" class="me-3" />
                </template>
                <VListItemTitle>{{ t('conversion_rate') }}</VListItemTitle>
                <VListItemSubtitle>{{ affiliateStats.conversionRate }}%</VListItemSubtitle>
              </VListItem>
            </VList>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Recent Commissions -->
    <VCard>
      <VCardItem>
        <VCardTitle>{{ t('recent_commissions') }}</VCardTitle>
        <VCardSubtitle>{{ t('latest_commission_activity') }}</VCardSubtitle>
      </VCardItem>

      <VDataTable
        :headers="[
          { title: t('order_id'), key: 'id' },
          { title: t('product'), key: 'product' },
          { title: t('commission'), key: 'commission' },
          { title: t('status'), key: 'status' },
          { title: t('date'), key: 'date' }
        ]"
        :items="recentCommissions"
        class="text-no-wrap"
      >
        <template #item.commission="{ item }">
          <span class="text-success font-weight-medium">
            {{ formatCurrency(item.commission) }}
          </span>
        </template>

        <template #item.status="{ item }">
          <VChip
            :color="getStatusColor(item.status)"
            size="small"
            class="text-capitalize"
          >
            {{ t(item.status) }}
          </VChip>
        </template>

        <template #item.date="{ item }">
          {{ item.date.toLocaleDateString() }}
        </template>
      </VDataTable>

      <VCardActions>
        <VBtn
          variant="outlined"
          prepend-icon="tabler-download"
        >
          {{ t('download_report') }}
        </VBtn>
        
        <VSpacer />
        
        <VBtn
          variant="text"
          append-icon="tabler-arrow-right"
        >
          {{ t('view_all_commissions') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </div>
</template>

<style lang="scss" scoped>
.earnings-chart {
  min-height: 200px;
}
</style>
