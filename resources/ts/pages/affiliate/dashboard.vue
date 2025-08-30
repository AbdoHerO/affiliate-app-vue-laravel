<script setup lang="ts">
import { onMounted, ref, computed, watch, onBeforeUnmount } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuth } from '@/composables/useAuth'
import { useAffiliateDashboardStore } from '@/stores/dashboard/affiliateDashboard'
import { useNotifications } from '@/composables/useNotifications'
import StatisticsCard from '@/components/dashboard/StatisticsCard.vue'
import DashboardChart from '@/components/charts/DashboardChart.vue'
import Breadcrumbs from '@/components/common/Breadcrumbs.vue'

definePage({
  meta: {
    requiresAuth: true,
    requiresRole: 'affiliate',
  },
})

const { t } = useI18n()
const { user } = useAuth()
const dashboardStore = useAffiliateDashboardStore()
const { showSuccess, showError } = useNotifications()

// Local state
const selectedPeriod = ref('month')
const refreshInterval = ref<NodeJS.Timeout | null>(null)
const autoRefresh = ref(true)
const showReferralLinkDialog = ref(false)

// Date range picker
const dateRange = ref([
  new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
  new Date().toISOString().split('T')[0],
])

// Breadcrumbs
const breadcrumbs = computed(() => [
  { title: t('affiliate'), disabled: false, href: '/affiliate' },
  { title: t('dashboard'), disabled: true, href: '/affiliate/dashboard' },
])

// KPI Cards - exactly 5 as specified
const kpiCards = computed(() => {
  if (!dashboardStore.stats?.cards) return []

  return dashboardStore.stats.cards.map(card => {
    let displayValue = card.value
    let prefix = undefined

    // Handle special formatting for different card types
    if (['total_commissions', 'monthly_earnings', 'received_payments', 'pending_payments'].includes(card.key)) {
      prefix = 'DH'
    }

    return {
      title: t(card.labelKey),
      value: displayValue,
      icon: getCardIcon(card.key),
      color: getCardColor(card.key),
      prefix: prefix,
    }
  })
})

// Helper functions for card styling
const getCardIcon = (key: string) => {
  const icons: Record<string, string> = {
    total_orders: 'tabler-shopping-cart',
    total_commissions: 'tabler-currency-dollar',
    monthly_earnings: 'tabler-calendar',
    received_payments: 'tabler-check-circle',
    pending_payments: 'tabler-clock',
    pending_tickets: 'tabler-ticket',
  }
  return icons[key] || 'tabler-chart-bar'
}

const getCardColor = (key: string) => {
  const colors: Record<string, string> = {
    total_orders: 'primary',
    total_commissions: 'success',
    monthly_earnings: 'info',
    received_payments: 'success',
    pending_payments: 'warning',
    pending_tickets: 'error',
  }
  return colors[key] || 'primary'
}

// Chart configurations for the 1 specified chart
const chartConfigs = computed(() => [
  {
    id: 'top_products_sold',
    title: t('dashboard.affiliate.charts.top_products_sold'),
    type: 'doughnut' as const,
    data: dashboardStore.topProductsSoldChart,
    cols: { cols: 12 },
    description: t('dashboard.affiliate.charts.top_products_sold.description'),
  },
])

// Period filter options
const periodOptions = [
  { value: 'month', label: t('dashboard.filters.period.month'), icon: 'tabler-calendar-month' },
  { value: 'quarter', label: t('dashboard.filters.period.quarter'), icon: 'tabler-calendar-stats' },
  { value: 'year', label: t('dashboard.filters.period.year'), icon: 'tabler-calendar-year' },
]

// Methods
const refreshData = async () => {
  try {
    await dashboardStore.refreshAll()
    if (autoRefresh.value) {
      showSuccess(t('affiliate_dashboard_refresh_success'))
    }
  } catch (error) {
    showError(t('errors.dashboard_refresh_failed'))
  }
}

const updateDateRange = () => {
  dashboardStore.updateFilters({
    dateRange: {
      start: dateRange.value[0],
      end: dateRange.value[1],
    },
  })
  refreshData()
}

const changePeriod = async (period: string) => {
  console.log('Affiliate Dashboard - Period changed to:', period)
  selectedPeriod.value = period

  // Update filters based on period
  const now = new Date()
  let startDate: Date

  switch (period) {
    case 'month':
      startDate = new Date(now.getFullYear(), now.getMonth(), 1)
      break
    case 'quarter':
      const quarter = Math.floor(now.getMonth() / 3)
      startDate = new Date(now.getFullYear(), quarter * 3, 1)
      break
    case 'year':
      startDate = new Date(now.getFullYear(), 0, 1)
      break
    default:
      startDate = new Date(now.getFullYear(), now.getMonth(), 1)
  }

  const dateRange = {
    start: startDate.toISOString().split('T')[0],
    end: now.toISOString().split('T')[0],
  }

  console.log('Affiliate Dashboard - Date range:', dateRange)

  // Update store filters with both dateRange and period
  dashboardStore.updateFilters({
    dateRange: dateRange,
    period: period, // Send the selected period to backend
  })

  // Refresh all data with new period
  console.log('Affiliate Dashboard - Refreshing data...')
  await Promise.all([
    dashboardStore.fetchStats(),
    dashboardStore.fetchChartData('top_products_sold'),
    dashboardStore.fetchTableData('my_recent_orders'),
    dashboardStore.fetchTableData('my_recent_payments'),
    dashboardStore.fetchTableData('my_active_referrals'),
  ])

  console.log('Affiliate Dashboard - Chart data after refresh:', dashboardStore.topProductsSoldChart)
}

const copyReferralLink = async () => {
  try {
    await dashboardStore.copyReferralLink()
    showSuccess(t('affiliate_dashboard_link_copied'))
  } catch (error) {
    showError(t('errors.link_copy_failed'))
  }
}

const shareReferralLink = () => {
  if (navigator.share && dashboardStore.referralLink) {
    navigator.share({
      title: t('join_affiliate_program'),
      text: t('join_affiliate_program_description'),
      url: dashboardStore.referralLink.link,
    })
  } else {
    showReferralLinkDialog.value = true
  }
}

const exportDashboard = () => {
  try {
    // Create export data
    const exportData = {
      timestamp: new Date().toISOString(),
      period: selectedPeriod.value,
      stats: dashboardStore.stats,
      commissions: dashboardStore.totalCommissions,
      charts: 'standard',
    }

    // Create and download file
    const blob = new Blob([JSON.stringify(exportData, null, 2)], { type: 'application/json' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `affiliate-performance-${selectedPeriod.value}-${new Date().toISOString().split('T')[0]}.json`
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    URL.revokeObjectURL(url)

    showSuccess(t('dashboard.export.success'))
  } catch (error) {
    console.error('Error exporting dashboard:', error)
    showError(t('dashboard.export.error'))
  }
}

// Load chart data for specific chart type
const loadChartData = async (chartId: string) => {
  try {
    await dashboardStore.fetchChartData(chartId)
  } catch (error) {
    showError(t('dashboard.charts.load_error'))
  }
}

// Refresh charts data
const refreshCharts = async () => {
  try {
    await dashboardStore.fetchChartData()
  } catch (error) {
    showError(t('dashboard.charts.load_error'))
  }
}

const setupAutoRefresh = () => {
  if (refreshInterval.value) {
    clearInterval(refreshInterval.value)
  }

  if (autoRefresh.value) {
    refreshInterval.value = setInterval(() => {
      refreshData()
    }, 5 * 60 * 1000) // Refresh every 5 minutes
  }
}

// Lifecycle
onMounted(async () => {
  console.log('Dashboard mounted with period:', selectedPeriod.value)
  await refreshData()
  setupAutoRefresh()
})

onBeforeUnmount(() => {
  if (refreshInterval.value) {
    clearInterval(refreshInterval.value)
  }
})

// Watchers
watch(autoRefresh, setupAutoRefresh)
</script>

<template>
  <div class="affiliate-dashboard">
    <!-- Breadcrumbs -->
    <Breadcrumbs :items="breadcrumbs" />

    <!-- Header -->
    <div class="d-flex align-center justify-space-between mb-6">
      <div>
        <h1 class="text-h4 font-weight-bold mb-1">
          {{ t('dashboard.affiliate.title') }}
        </h1>
        <p class="text-body-1 text-medium-emphasis">
          {{ t('dashboard.affiliate.welcome', { name: user?.nom_complet }) }}
        </p>
      </div>

      <div class="d-flex align-center gap-3">
        <!-- Period Filter -->
        <VSelect
          v-model="selectedPeriod"
          :items="periodOptions"
          item-title="label"
          item-value="value"
          :label="t('dashboard.filters.period.label')"
          variant="outlined"
          density="compact"
          style="min-width: 150px;"
          @update:model-value="changePeriod"
        >
          <template #prepend-inner>
            <VIcon icon="tabler-calendar" size="16" />
          </template>
        </VSelect>

        <!-- Refresh Button -->
        <VBtn
          color="primary"
          variant="outlined"
          :loading="dashboardStore.isLoading"
          @click="refreshData"
        >
          <VIcon start icon="tabler-refresh" />
          {{ t('dashboard.common.refresh') }}
        </VBtn>
      </div>
    </div>

    <!-- Error Alert -->
    <VAlert
      v-if="dashboardStore.error"
      type="error"
      variant="tonal"
      class="mb-6"
      closable
      @click:close="dashboardStore.error = null"
    >
      {{ dashboardStore.error }}
    </VAlert>

    <!-- KPI Cards - 5 cards as specified -->
    <VRow class="mb-6">
      <VCol
        v-for="card in kpiCards"
        :key="card.title"
        cols="12"
        sm="6"
        md="4"
        lg="4"
      >
        <StatisticsCard
          :title="card.title"
          :value="card.value"
          :icon="card.icon"
          :color="card.color"
          :prefix="card.prefix"
          :loading="dashboardStore.loading.stats"
        />
      </VCol>
    </VRow>


    <!-- Charts Section -->
    <VRow class="mb-6">
      <VCol cols="6">
        <VCard>
          <VCardItem>
            <VCardTitle>{{ t('dashboard.affiliate.charts.top_products_sold') }}</VCardTitle>
            <VCardSubtitle>{{ t('dashboard.affiliate.charts.top_products_sold.description') }}</VCardSubtitle>
          </VCardItem>
          <VCardText>
            <DashboardChart
              type="doughnut"
              :data="dashboardStore.topProductsSoldChart"
              :loading="dashboardStore.loading.charts"
              :error="dashboardStore.error"
            />
          </VCardText>
        </VCard>
      </VCol>


    <!-- Tables Section - 3 tables as specified -->
      <VCol cols="12" md="6">
        <VCard>
          <VCardTitle>{{ t('dashboard.affiliate.tables.my_recent_orders') }}</VCardTitle>
          <VCardText>
            <VDataTable
              :items="dashboardStore.myRecentOrders"
              :headers="[
                { title: t('dashboard.affiliate.tables.columns.product'), key: 'product' },
                { title: t('dashboard.affiliate.tables.columns.amount'), key: 'amount' },
                { title: t('dashboard.affiliate.tables.columns.status'), key: 'status' },
                { title: t('dashboard.affiliate.tables.columns.date'), key: 'date' },
              ]"
              :loading="dashboardStore.loading.tables"
              density="compact"
              hide-default-footer
              items-per-page="5"
            >
              <template #item.amount="{ item }">
                {{ (item as any).amount?.toLocaleString() }} DH
              </template>
              <template #item.date="{ item }">
                {{ new Date((item as any).date).toLocaleDateString() }}
              </template>
              <template #item.status="{ item }">
                <VChip
                  :color="(item as any).status === 'delivered' ? 'success' : (item as any).status === 'pending' ? 'warning' : 'error'"
                  size="small"
                  variant="tonal"
                >
                  {{ (item as any).status }}
                </VChip>
              </template>
            </VDataTable>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VRow>
      <VCol cols="12" md="6">
        <VCard>
          <VCardTitle>{{ t('dashboard.affiliate.tables.my_recent_payments') }}</VCardTitle>
          <VCardText>
            <VDataTable
              :items="dashboardStore.myRecentPayments"
              :headers="[
                { title: t('dashboard.affiliate.tables.columns.amount'), key: 'amount' },
                { title: t('dashboard.affiliate.tables.columns.status'), key: 'status' },
                { title: t('dashboard.affiliate.tables.columns.date'), key: 'date' },
              ]"
              :loading="dashboardStore.loading.tables"
              density="compact"
              hide-default-footer
              items-per-page="5"
            >
              <template #item.amount="{ item }">
                {{ (item as any).amount?.toLocaleString() }} DH
              </template>
              <template #item.date="{ item }">
                {{ new Date((item as any).date).toLocaleDateString() }}
              </template>
              <template #item.status="{ item }">
                <VChip
                  :color="(item as any).status === 'paid' ? 'success' : (item as any).status === 'pending' ? 'warning' : 'error'"
                  size="small"
                  variant="tonal"
                >
                  {{ (item as any).status }}
                </VChip>
              </template>
            </VDataTable>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" md="6">
        <VCard>
          <VCardTitle>{{ t('dashboard.affiliate.tables.my_active_referrals') }}</VCardTitle>
          <VCardText>
            <VDataTable
              :items="dashboardStore.myActiveReferrals"
              :headers="[
                { title: t('dashboard.affiliate.tables.columns.name'), key: 'name' },
                { title: t('dashboard.affiliate.tables.columns.email'), key: 'email' },
                { title: t('dashboard.affiliate.tables.columns.signup_date'), key: 'signup_date' },
                { title: t('dashboard.affiliate.tables.columns.status'), key: 'status' },
              ]"
              :loading="dashboardStore.loading.tables"
              density="compact"
              hide-default-footer
              items-per-page="5"
            >
              <template #item.signup_date="{ item }">
                {{ new Date((item as any).signup_date).toLocaleDateString() }}
              </template>
              <template #item.status="{ item }">
                <VChip
                  :color="(item as any).status === 'verified' ? 'success' : 'pending'"
                  size="small"
                  variant="tonal"
                >
                  {{ (item as any).status }}
                </VChip>
              </template>
            </VDataTable>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>


<style lang="scss" scoped>
.affiliate-dashboard {
  // Responsive adjustments for mobile and tablet
  @media (max-width: 768px) {
    .d-flex.align-center.justify-space-between {
      flex-direction: column;
      align-items: flex-start !important;
      gap: 1rem;

      > div:last-child {
        width: 100%;
        justify-content: flex-end;
      }
    }
  }

  // Ensure charts are responsive
  :deep(.chart-container) {
    width: 100% !important;
    height: auto !important;
  }

  // Make data tables responsive
  :deep(.v-data-table) {
    @media (max-width: 768px) {
      font-size: 0.875rem;
    }
  }
}
</style>
