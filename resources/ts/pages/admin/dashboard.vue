<script setup lang="ts">
import { onMounted, ref, computed, watch, onBeforeUnmount } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuth } from '@/composables/useAuth'
import { useAdminDashboardStore } from '@/stores/dashboard/adminDashboard'
import { useNotifications } from '@/composables/useNotifications'
import StatisticsCard from '@/components/dashboard/StatisticsCard.vue'
import Breadcrumbs from '@/components/common/Breadcrumbs.vue'

definePage({
  meta: {
    requiresAuth: true,
    requiresRole: 'admin',
  },
})

const { t } = useI18n()
const { user } = useAuth()
const dashboardStore = useAdminDashboardStore()
const { showSuccess, showError } = useNotifications()

// Local state
const selectedPeriod = ref('month')
const refreshInterval = ref<NodeJS.Timeout | null>(null)
const autoRefresh = ref(true)

// Remove unused date range picker

// Breadcrumbs
const breadcrumbs = computed(() => [
  { title: t('admin_breadcrumb'), disabled: false, href: '/admin' },
  { title: t('dashboard'), disabled: true, href: '/admin/dashboard' },
])

// Computed properties for KPI cards using new unified format
const kpiCards = computed(() => {
  if (!dashboardStore.stats?.cards) return []

  return dashboardStore.stats.cards.map(card => ({
    title: t(card.labelKey),
    value: typeof card.value === 'object' ? JSON.stringify(card.value) : card.value,
    icon: getCardIcon(card.key),
    color: getCardColor(card.key),
    prefix: ['total_revenue', 'total_commissions', 'pending_payments'].includes(card.key) ? 'DH' : undefined,
  }))
})

// Helper functions for card styling
const getCardIcon = (key: string) => {
  const icons: Record<string, string> = {
    active_affiliates: 'tabler-users',
    total_orders: 'tabler-shopping-cart',
    total_revenue: 'tabler-currency-dollar',
    total_commissions: 'tabler-chart-line',
    pending_payments: 'tabler-clock',
    pending_tickets: 'tabler-ticket',
  }
  return icons[key] || 'tabler-chart-bar'
}

const getCardColor = (key: string) => {
  const colors: Record<string, string> = {
    active_affiliates: 'primary',
    total_orders: 'secondary',
    total_revenue: 'success',
    total_commissions: 'info',
    pending_payments: 'warning',
    pending_tickets: 'error',
  }
  return colors[key] || 'primary'
}

// Chart configurations for the 4 specified charts
const chartConfigs = computed(() => [
  {
    id: 'orders_by_period',
    title: t('dashboard.admin.charts.orders_by_period.series.orders'),
    type: 'bar' as const,
    data: dashboardStore.ordersByPeriodChart,
    cols: { cols: 12, md: 6 },
    description: t('dashboard.admin.charts.orders_by_period.description'),
  },
  {
    id: 'monthly_revenue',
    title: t('dashboard.admin.charts.monthly_revenue.series.revenue'),
    type: 'line' as const,
    data: dashboardStore.monthlyRevenueChart,
    cols: { cols: 12, md: 6 },
    description: t('dashboard.admin.charts.monthly_revenue.description'),
  },
  {
    id: 'top_affiliates',
    title: t('dashboard.admin.charts.top_affiliates.title'),
    type: 'bar' as const,
    data: dashboardStore.topAffiliatesChart,
    cols: { cols: 12, md: 6 },
    description: t('dashboard.admin.charts.top_affiliates.description'),
  },
  {
    id: 'top_products',
    title: t('dashboard.admin.charts.top_products.title'),
    type: 'doughnut' as const,
    data: dashboardStore.topProductsChart,
    cols: { cols: 12, md: 6 },
    description: t('dashboard.admin.charts.top_products.description'),
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
      showSuccess(t('dashboard_data_refreshed'))
    }
  } catch (error) {
    showError(t('dashboard_refresh_failed'))
  }
}

const changePeriod = async (period: string) => {
  selectedPeriod.value = period
  console.log('Admin Dashboard - Period changed to:', period)

  // Calculate date range based on period
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

  console.log('Admin Dashboard - Date range:', dateRange)

  // Update store filters with both dateRange and period
  dashboardStore.updateFilters({
    dateRange: dateRange,
    period: period, // Send the selected period to backend
  })

  // Refresh all data with new period
  console.log('Admin Dashboard - Refreshing data...')
  await Promise.all([
    dashboardStore.fetchStats(),
    dashboardStore.fetchChartData('orders_by_period'),
    dashboardStore.fetchChartData('monthly_revenue'),
    dashboardStore.fetchChartData('top_affiliates'),
    dashboardStore.fetchChartData('top_products'),
    dashboardStore.fetchTableData('recent_payments'),
    dashboardStore.fetchTableData('monthly_paid_commissions'),
  ])

  console.log('Admin Dashboard - Data refreshed')
}

const refreshCharts = async () => {
  try {
    await Promise.all([
      dashboardStore.fetchChartData('orders_by_period'),
      dashboardStore.fetchChartData('monthly_revenue'),
      dashboardStore.fetchChartData('top_affiliates'),
      dashboardStore.fetchChartData('top_products'),
    ])
    showSuccess(t('dashboard.charts.refresh_success'))
  } catch (error) {
    showError(t('dashboard.charts.refresh_error'))
  }
}

const exportDashboard = () => {
  try {
    // Create export data
    const exportData = {
      timestamp: new Date().toISOString(),
      period: selectedPeriod.value,
      stats: dashboardStore.stats,
      charts: 'standard',
    }

    // Create and download file
    const blob = new Blob([JSON.stringify(exportData, null, 2)], { type: 'application/json' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `admin-dashboard-${selectedPeriod.value}-${new Date().toISOString().split('T')[0]}.json`
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
  <div class="admin-dashboard">
    <!-- Breadcrumbs -->
    <Breadcrumbs :items="breadcrumbs" />

    <!-- Header -->
    <div class="d-flex align-center justify-space-between mb-6">
      <div>
        <h1 class="text-h4 font-weight-bold mb-1">
          {{ t('dashboard.admin.title') }}
        </h1>
        <p class="text-body-1 text-medium-emphasis">
          {{ t('dashboard.admin.welcome', { name: user?.nom_complet }) }}
        </p>
      </div>

      <div class="d-flex align-center gap-3">
        <!-- Refresh Button -->
        <VBtn
          color="primary"
          variant="outlined"
          :loading="dashboardStore.isLoading"
          @click="refreshData"
        >
          <VIcon start icon="tabler-refresh" />
          {{ t('dashboard.admin.refresh') }}
        </VBtn>

        <!-- Export Button -->
        <VBtn
          color="secondary"
          variant="outlined"
          @click="exportDashboard"
        >
          <VIcon start icon="tabler-download" />
          {{ t('dashboard.admin.export') }}
        </VBtn>

        <!-- Period Filter -->
        <VSelect
          v-model="selectedPeriod"
          :items="periodOptions"
          item-title="label"
          item-value="value"
          :label="t('dashboard.admin.period_filter')"
          variant="outlined"
          density="compact"
          style="min-width: 150px;"
          @update:model-value="changePeriod"
        >
          <template #prepend-inner>
            <VIcon icon="tabler-calendar" size="16" />
          </template>
        </VSelect>
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

    <!-- KPI Cards - 6 indicators as specified -->
    <VRow class="mb-6">
      <VCol
        v-for="(card, index) in kpiCards"
        :key="index"
        cols="12"
        sm="6"
        lg="4"
        xl="2"
      >
        <StatisticsCard
          :title="card.title"
          :value="card.value"
          :icon="card.icon"
          :color="card.color"
          :prefix="card.prefix"
          :loading="dashboardStore.loading.stats"
          :error="dashboardStore.error || undefined"
          size="medium"
        />
      </VCol>
    </VRow>

    <!-- Charts Section -->
    <VRow class="mb-6">
      <VCol cols="12">
        <div class="d-flex align-center justify-space-between mb-4 flex-wrap gap-4">
          <div>
            <h2 class="text-h5 font-weight-bold">
              {{ t('dashboard.admin.charts.title') }}
            </h2>
            <p class="text-body-2 text-disabled mb-0">
              {{ t('dashboard.charts.explanation') }}
            </p>
          </div>

          <div class="d-flex align-center gap-3 flex-wrap dashboard-header-actions">
            <!-- Action Buttons Group -->
            <div class="d-flex align-center gap-2">
              <!-- Refresh Charts Button -->
              <VBtn
                icon="tabler-refresh"
                variant="outlined"
                size="small"
                :loading="dashboardStore.loading.charts"
                @click="refreshCharts"
              />

            </div>

            <!-- Period Selector - Responsive -->
            <div class="period-selector-wrapper">
              <!-- Desktop/Tablet View (768px and up) -->
              <VBtnToggle
                v-model="selectedPeriod"
                color="primary"
                variant="outlined"
                divided
                mandatory
                class="d-none d-md-flex"
                style="border-radius: 6px;"
                @update:model-value="changePeriod"
              >
                <VBtn 
                  value="month" 
                  size="small"
                  style="min-width: 80px;"
                >
                  <VIcon icon="tabler-calendar-month" class="me-1" size="16" />
                  {{ t('dashboard.filters.period.month') }}
                </VBtn>
                <VBtn 
                  value="quarter" 
                  size="small"
                  style="min-width: 80px;"
                >
                  <VIcon icon="tabler-calendar-stats" class="me-1" size="16" />
                  {{ t('dashboard.filters.period.quarter') }}
                </VBtn>
                <VBtn 
                  value="year" 
                  size="small"
                  style="min-width: 80px;"
                >
                  <VIcon icon="tabler-calendar-year" class="me-1" size="16" />
                  {{ t('dashboard.filters.period.year') }}
                </VBtn>
              </VBtnToggle>

              <!-- Mobile/Tablet View (below 768px) -->
              <VSelect
                v-model="selectedPeriod"
                :items="periodOptions"
                item-title="label"
                item-value="value"
                variant="outlined"
                density="compact"
                class="d-flex d-md-none period-select-mobile"
                style="min-width: 120px; max-width: 140px;"
                hide-details
                @update:model-value="changePeriod"
              >
                <template #prepend-inner>
                  <VIcon size="16" class="me-1">
                    {{ selectedPeriod === 'month' ? 'tabler-calendar-month' :
                        selectedPeriod === 'quarter' ? 'tabler-calendar-stats' :
                        'tabler-calendar-year' }}
                  </VIcon>
                </template>
              </VSelect>
            </div>
          </div>
        </div>
      </VCol>

      <!-- Charts - 4 specified charts -->
      <VCol
        v-for="chart in chartConfigs"
        :key="chart.id"
        v-bind="chart.cols"
      >
        <VCard>
          <VCardItem>
            <VCardTitle class="d-flex align-center justify-space-between">
              <span>{{ chart.title }}</span>
              <VTooltip :text="chart.description">
                <template #activator="{ props }">
                  <VIcon
                    v-bind="props"
                    icon="tabler-info-circle"
                    size="16"
                    class="text-medium-emphasis"
                  />
                </template>
              </VTooltip>
            </VCardTitle>
            <VCardSubtitle class="text-body-2 text-medium-emphasis">
              {{ chart.description }}
            </VCardSubtitle>
          </VCardItem>

          <VCardText>
            <DashboardChart
              :type="chart.type"
              :data="chart.data as any"
              :loading="dashboardStore.loading.charts"
              :error="dashboardStore.error || undefined"
              @load="loadChartData(chart.id)"
            />

            <!-- Chart explanation for beginners -->
            <VAlert
              v-if="!chart.data || (chart.data.series?.length === 0 && chart.data.items?.length === 0)"
              type="info"
              variant="tonal"
              class="mt-3"
            >
              <template #prepend>
                <VIcon icon="tabler-lightbulb" />
              </template>
              {{ t('dashboard.charts.explanation') }}
            </VAlert>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Tables Section - 2 tables as specified -->
    <VRow>
      <VCol cols="12" md="6">
        <VCard>
          <VCardTitle class="d-flex align-center justify-space-between">
            <span>{{ t('dashboard.admin.tables.recent_payments') }}</span>
            <VBtn
              variant="text"
              size="small"
              @click="dashboardStore.fetchTableData('recent_payments')"
            >
              <VIcon icon="tabler-refresh" />
            </VBtn>
          </VCardTitle>
          <VCardText>
            <VDataTable
              :items="dashboardStore.recentPayments"
              :headers="[
                { title: t('dashboard.admin.tables.columns.affiliate'), key: 'affiliate' },
                { title: t('dashboard.admin.tables.columns.amount'), key: 'amount' },
                { title: t('dashboard.admin.tables.columns.status'), key: 'status' },
                { title: t('dashboard.admin.tables.columns.date'), key: 'date' },
              ]"
              :loading="dashboardStore.loading.tables"
              density="compact"
              hide-default-footer
            >
              <template #item.amount="{ item }">
                {{ item.amount.toLocaleString() }} DH
              </template>
              <template #item.date="{ item }">
                {{ new Date(item.date).toLocaleDateString() }}
              </template>
              <template #item.status="{ item }">
                <VChip
                  :color="item.status === 'paid' ? 'success' : item.status === 'pending' ? 'warning' : 'error'"
                  size="small"
                  variant="tonal"
                >
                  {{ item.status }}
                </VChip>
              </template>
            </VDataTable>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" md="6">
        <VCard>
          <VCardTitle class="d-flex align-center justify-space-between">
            <span>{{ t('dashboard.admin.tables.monthly_paid_commissions') }}</span>
            <VBtn
              variant="text"
              size="small"
              @click="dashboardStore.fetchTableData('monthly_paid_commissions')"
            >
              <VIcon icon="tabler-refresh" />
            </VBtn>
          </VCardTitle>
          <VCardText>
            <VDataTable
              :items="dashboardStore.monthlyPaidCommissions"
              :headers="[
                { title: t('dashboard.admin.tables.columns.affiliate'), key: 'affiliate' },
                { title: t('dashboard.admin.tables.columns.amount'), key: 'amount' },
                { title: t('dashboard.admin.tables.columns.date'), key: 'date' },
              ]"
              :loading="dashboardStore.loading.tables"
              density="compact"
              hide-default-footer
            >
              <template #item.amount="{ item }">
                {{ item.amount.toLocaleString() }} DH
              </template>
              <template #item.date="{ item }">
                {{ new Date(item.date).toLocaleDateString() }}
              </template>
            </VDataTable>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>

<style lang="scss" scoped>
.dashboard-header-actions {
  gap: 1rem !important;
  
  .period-selector-wrapper {
    // Fix for VBtnToggle spacing
    :deep(.v-btn-toggle) {
      border-radius: 6px;
      overflow: hidden;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
      
      .v-btn {
        border-radius: 0 !important;
        border-right: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
        min-width: auto;
        padding: 8px 16px;
        font-size: 0.875rem;
        
        &:first-child {
          border-top-left-radius: 6px !important;
          border-bottom-left-radius: 6px !important;
        }
        
        &:last-child {
          border-top-right-radius: 6px !important;
          border-bottom-right-radius: 6px !important;
          border-right: none;
        }
        
        .v-icon {
          margin-right: 6px;
        }
        
        // Ensure proper spacing and no overlap
        &:not(.v-btn--selected) {
          background-color: transparent;
        }
        
        &.v-btn--selected {
          z-index: 1;
        }
      }
    }
    
    .period-select-mobile {
      .v-input__control {
        min-height: 32px;
      }
      
      .v-field {
        font-size: 0.875rem;
      }
      
      .v-field__input {
        padding: 0 8px;
      }
    }
  }
}

// Admin-specific responsive adjustments
@media (max-width: 768px) {
  .dashboard-header-actions {
    gap: 0.5rem !important;
    flex-wrap: wrap;
    
    .period-selector-wrapper {
      flex: 1;
      min-width: 120px;
      max-width: 140px;
    }
    
    // Ensure the select is properly displayed
    .period-select-mobile {
      display: flex !important;
    }
  }
}

// Fine-tune for smaller screens
@media (max-width: 600px) {
  .dashboard-header-actions {
    .period-selector-wrapper {
      min-width: 100px;
      max-width: 120px;
    }
  }
}
</style>
