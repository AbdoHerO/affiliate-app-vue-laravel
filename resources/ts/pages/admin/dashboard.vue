<script setup lang="ts">
import { onMounted, ref, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuth } from '@/composables/useAuth'
import { useAdminDashboardStore } from '@/stores/dashboard/adminDashboard'
import { useNotifications } from '@/composables/useNotifications'
import { useAdminAdvancedCharts } from '@/composables/useAdvancedCharts'
import StatisticsCard from '@/components/dashboard/StatisticsCard.vue'
import DashboardChart from '@/components/charts/DashboardChart.vue'
import Breadcrumbs from '@/components/common/Breadcrumbs.vue'

// Import advanced chart components
import {
  WebsiteAnalyticsCarousel,
  TotalEarningChart,
  RevenueGrowthChart,
  SessionAnalyticsDonut,
  SalesOverviewCard,
  EarningReportsWeekly,
  SalesAreaChart,
  ProfitLineChart,
  AdvancedStatsCard,
  MixedChart,
  ExpensesRadialChart,
} from '@/components/charts/advanced'

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
const { chartConfigs: advancedChartConfigs } = useAdminAdvancedCharts(dashboardStore)

// Local state
const selectedPeriod = ref('month')
const refreshInterval = ref<NodeJS.Timeout | null>(null)
const autoRefresh = ref(true)

// Date range picker
const dateRange = ref([
  new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
  new Date().toISOString().split('T')[0],
])

// Breadcrumbs
const breadcrumbs = computed(() => [
  { title: t('admin_breadcrumb'), disabled: false, href: '/admin' },
  { title: t('dashboard'), disabled: true, href: '/admin/dashboard' },
])

// Computed properties
const kpiCards = computed(() => {
  if (!dashboardStore.stats) return []
  const { overview, revenue, commissions, payouts } = dashboardStore.stats

  return [
    {
      title: t('total_affiliates'),
      value: overview.totalAffiliates,
      icon: 'tabler-users',
      color: 'primary',
      trend: {
        value: dashboardStore.signupsGrowth,
        label: t('vs_last_month'),
      },
    },
    {
      title: t('total_revenue'),
      value: overview.totalRevenue,
      prefix: '$',
      icon: 'tabler-currency-dollar',
      color: 'success',
      trend: {
        value: revenue.growth,
        label: t('vs_last_month'),
      },
    },
    {
      title: t('total_commissions'),
      value: overview.totalCommissions,
      prefix: '$',
      icon: 'tabler-chart-line',
      color: 'info',
      trend: {
        value: commissions.growth,
        label: t('vs_last_month'),
      },
    },
    {
      title: t('pending_payouts'),
      value: payouts.pending.amount,
      prefix: '$',
      subtitle: `${payouts.pending.count} ${t('requests')}`,
      icon: 'tabler-clock',
      color: 'warning',
    },
    {
      title: t('verified_signups'),
      value: overview.verifiedSignups,
      subtitle: `${overview.verificationRate.toFixed(1)}% ${t('conversion_rate')}`,
      icon: 'tabler-user-check',
      color: 'success',
    },
    {
      title: t('total_orders'),
      value: overview.totalOrders,
      icon: 'tabler-shopping-cart',
      color: 'secondary',
    },
  ]
})

// Keep old chart configs for fallback
const legacyChartConfigs = computed(() => [
  {
    title: t('signups_over_time'),
    type: 'line' as const,
    data: dashboardStore.signupsChartData,
    cols: { cols: 12, md: 6 },
  },
  {
    title: t('revenue_commissions'),
    type: 'area' as const,
    data: dashboardStore.revenueChartData,
    cols: { cols: 12, md: 6 },
  },
  {
    title: t('top_affiliates_by_commissions'),
    type: 'bar' as const,
    data: dashboardStore.topAffiliatesChart,
    cols: { cols: 12, md: 6 },
  },
  {
    title: t('orders_by_status'),
    type: 'doughnut' as const,
    data: dashboardStore.ordersByStatusChart,
    cols: { cols: 12, md: 6 },
  },
])

// Use advanced charts by default
const useAdvancedCharts = ref(true)

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

const updateDateRange = () => {
  dashboardStore.updateFilters({
    dateRange: {
      start: dateRange.value[0],
      end: dateRange.value[1],
    },
  })
  refreshData()
}

const changePeriod = (period: string) => {
  selectedPeriod.value = period
  dashboardStore.fetchChartData(period)
}

const exportDashboard = () => {
  try {
    // Create export data
    const exportData = {
      timestamp: new Date().toISOString(),
      period: selectedPeriod.value,
      stats: dashboardStore.stats,
      charts: useAdvancedCharts.value ? 'advanced' : 'basic',
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

    showSuccess(t('admin_dashboard_export_success'))
  } catch (error) {
    console.error('Error exporting dashboard:', error)
    showError(t('admin_dashboard_export_error'))
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
          {{ t('admin_dashboard_title') }}
        </h1>
        <p class="text-body-1 text-medium-emphasis">
          {{ t('admin_dashboard_welcome', { name: user?.nom_complet }) }}
        </p>
      </div>

      <div class="d-flex align-center gap-3">
        <!-- Auto Refresh Toggle -->
        <VTooltip :text="t('admin_dashboard_auto_refresh_tooltip')">
          <template #activator="{ props }">
            <VBtn
              v-bind="props"
              :color="autoRefresh ? 'success' : 'default'"
              :variant="autoRefresh ? 'tonal' : 'outlined'"
              icon
              @click="autoRefresh = !autoRefresh"
            >
              <VIcon :icon="autoRefresh ? 'tabler-refresh' : 'tabler-refresh-off'" />
            </VBtn>
          </template>
        </VTooltip>

        <!-- Refresh Button -->
        <VBtn
          color="primary"
          variant="outlined"
          :loading="dashboardStore.isLoading"
          @click="refreshData"
        >
          <VIcon start icon="tabler-refresh" />
          {{ t('admin_dashboard_refresh') }}
        </VBtn>

        <!-- Date Range Picker -->
        <VMenu>
          <template #activator="{ props }">
            <VBtn
              v-bind="props"
              variant="outlined"
              prepend-icon="tabler-calendar"
            >
              {{ dateRange[0] }} - {{ dateRange[1] }}
            </VBtn>
          </template>

          <VCard min-width="300">
            <VCardText>
              <VTextField
                v-model="dateRange[0]"
                :label="t('admin_dashboard_start_date')"
                type="date"
                variant="outlined"
                density="compact"
                class="mb-3"
              />
              <VTextField
                v-model="dateRange[1]"
                :label="t('admin_dashboard_end_date')"
                type="date"
                variant="outlined"
                density="compact"
                class="mb-3"
              />
              <VBtn
                color="primary"
                block
                @click="updateDateRange"
              >
                {{ t('admin_dashboard_apply') }}
              </VBtn>
            </VCardText>
          </VCard>
        </VMenu>
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

    <!-- KPI Cards -->
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
          :subtitle="card.subtitle"
          :icon="card.icon"
          :color="card.color"
          :trend="card.trend"
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
              {{ t('dashboard_analytics') }}
            </h2>
            <p class="text-body-2 text-disabled mb-0">
              {{ t('admin_dashboard_analytics_subtitle') }}
            </p>
          </div>

          <div class="d-flex align-center gap-3 flex-wrap">
            <!-- Action Buttons Group -->
            <div class="d-flex align-center gap-2">
              <!-- Refresh Button -->
              <VTooltip :text="t('admin_dashboard_refresh_data_tooltip')">
                <template #activator="{ props: tooltipProps }">
                  <VBtn
                    v-bind="tooltipProps"
                    icon="tabler-refresh"
                    variant="outlined"
                    size="small"
                    :loading="dashboardStore.loading.stats"
                    @click="refreshData"
                  />
                </template>
              </VTooltip>

              <!-- Export Button -->
              <VTooltip :text="t('admin_dashboard_export_tooltip')">
                <template #activator="{ props: tooltipProps }">
                  <VBtn
                    v-bind="tooltipProps"
                    icon="tabler-download"
                    variant="outlined"
                    size="small"
                    @click="exportDashboard"
                  />
                </template>
              </VTooltip>

              <!-- Chart Style Toggle -->
              <VTooltip :text="useAdvancedCharts ? t('admin_dashboard_switch_to_basic') : t('admin_dashboard_switch_to_advanced')">
                <template #activator="{ props: tooltipProps }">
                  <VBtn
                    v-bind="tooltipProps"
                    :icon="useAdvancedCharts ? 'tabler-chart-dots-3' : 'tabler-chart-line'"
                    :color="useAdvancedCharts ? 'primary' : 'default'"
                    variant="outlined"
                    size="small"
                    @click="useAdvancedCharts = !useAdvancedCharts"
                  >
                    <VIcon :icon="useAdvancedCharts ? 'tabler-chart-dots-3' : 'tabler-chart-line'" />
                    <VTooltip
                      activator="parent"
                      location="bottom"
                    >
                      {{ useAdvancedCharts ? t('admin_dashboard_advanced_charts') : t('admin_dashboard_basic_charts') }}
                    </VTooltip>
                  </VBtn>
                </template>
              </VTooltip>
            </div>

            <!-- Period Selector - Responsive -->
            <div class="period-selector-wrapper">
              <!-- Desktop/Tablet View (768px and up) -->
              <VBtnToggle
                v-model="selectedPeriod"
                color="primary"
                variant="outlined"
                divided
                class="d-none d-md-flex"
                @update:model-value="changePeriod"
              >
                <VBtn value="day" size="small">
                  <VIcon icon="tabler-calendar-day" class="me-1" size="16" />
                  {{ t('admin_dashboard_period_day') }}
                </VBtn>
                <VBtn value="week" size="small">
                  <VIcon icon="tabler-calendar-week" class="me-1" size="16" />
                  {{ t('admin_dashboard_period_week') }}
                </VBtn>
                <VBtn value="month" size="small">
                  <VIcon icon="tabler-calendar-month" class="me-1" size="16" />
                  {{ t('admin_dashboard_period_month') }}
                </VBtn>
                <VBtn value="year" size="small">
                  <VIcon icon="tabler-calendar-year" class="me-1" size="16" />
                  {{ t('admin_dashboard_period_year') }}
                </VBtn>
              </VBtnToggle>

              <!-- Mobile/Tablet View (below 768px) -->
              <VSelect
                v-model="selectedPeriod"
                :items="[
                  { title: t('admin_dashboard_period_day'), value: 'day', prepend: 'tabler-calendar-day' },
                  { title: t('admin_dashboard_period_week'), value: 'week', prepend: 'tabler-calendar-week' },
                  { title: t('admin_dashboard_period_month'), value: 'month', prepend: 'tabler-calendar-month' },
                  { title: t('admin_dashboard_period_year'), value: 'year', prepend: 'tabler-calendar-year' }
                ]"
                variant="outlined"
                density="compact"
                class="d-flex d-md-none period-select-mobile"
                style="min-width: 120px; max-width: 140px;"
                hide-details
                @update:model-value="changePeriod"
              >
                <template #prepend-inner>
                  <VIcon size="16" class="me-1">
                    {{ selectedPeriod === 'day' ? 'tabler-calendar-day' : 
                        selectedPeriod === 'week' ? 'tabler-calendar-week' :
                        selectedPeriod === 'month' ? 'tabler-calendar-month' : 'tabler-calendar-year' }}
                  </VIcon>
                </template>
              </VSelect>
            </div>
          </div>
        </div>
      </VCol>

      <!-- Advanced Charts -->
      <template v-if="useAdvancedCharts">
        <VCol
          v-for="(chart, index) in advancedChartConfigs"
          :key="`advanced-${index}`"
          v-bind="chart.cols"
        >
          <!-- Advanced Stats Card -->
          <AdvancedStatsCard
            v-if="chart.component === 'AdvancedStatsCard'"
            :data="chart.data"
            :loading="chart.loading"
            :size="chart.size"
          />

          <!-- Sales Overview Card -->
          <SalesOverviewCard
            v-else-if="chart.component === 'SalesOverviewCard'"
            :data="chart.data"
            :loading="chart.loading"
          />

          <!-- Earning Reports Weekly -->
          <EarningReportsWeekly
            v-else-if="chart.component === 'EarningReportsWeekly'"
            :data="chart.data"
            :loading="chart.loading"
          />

          <!-- Website Analytics Carousel -->
          <WebsiteAnalyticsCarousel
            v-else-if="chart.component === 'WebsiteAnalyticsCarousel'"
            :data="chart.data"
            :loading="chart.loading"
          />

          <!-- Mixed Chart -->
          <MixedChart
            v-else-if="chart.component === 'MixedChart'"
            :data="chart.data"
            :loading="chart.loading"
          />

          <!-- Sales Area Chart -->
          <SalesAreaChart
            v-else-if="chart.component === 'SalesAreaChart'"
            :data="chart.data"
            :loading="chart.loading"
          />

          <!-- Profit Line Chart -->
          <ProfitLineChart
            v-else-if="chart.component === 'ProfitLineChart'"
            :data="chart.data"
            :loading="chart.loading"
          />

          <!-- Total Earning Chart -->
          <TotalEarningChart
            v-else-if="chart.component === 'TotalEarningChart'"
            :data="chart.data"
            :loading="chart.loading"
          />

          <!-- Revenue Growth Chart -->
          <RevenueGrowthChart
            v-else-if="chart.component === 'RevenueGrowthChart'"
            :data="chart.data"
            :loading="chart.loading"
          />

          <!-- Session Analytics Donut -->
          <SessionAnalyticsDonut
            v-else-if="chart.component === 'SessionAnalyticsDonut'"
            :data="chart.data"
            :loading="chart.loading"
          />

          <!-- Expenses Radial Chart -->
          <ExpensesRadialChart
            v-else-if="chart.component === 'ExpensesRadialChart'"
            :data="chart.data"
            :loading="chart.loading"
          />
        </VCol>
      </template>

      <!-- Legacy Charts (fallback) -->
      <template v-else>
        <VCol
          v-for="(chart, index) in legacyChartConfigs"
          :key="`legacy-${index}`"
          v-bind="chart.cols"
        >
          <DashboardChart
            :type="chart.type"
            :data="chart.data"
            :title="chart.title"
            :loading="dashboardStore.loading.charts"
            :error="dashboardStore.error || undefined"
            :height="350"
          />
        </VCol>
      </template>
    </VRow>

    <!-- Recent Data Tables -->
    <VRow>
      <VCol cols="12" md="6">
        <VCard>
          <VCardTitle class="d-flex align-center justify-space-between">
            <span>{{ t('admin_dashboard_recent_affiliates') }}</span>
            <VBtn
              variant="text"
              size="small"
              @click="dashboardStore.fetchTableData('recent_affiliates')"
            >
              <VIcon icon="tabler-refresh" />
            </VBtn>
          </VCardTitle>
          <VCardText>
            <VDataTable
              :items="dashboardStore.recentAffiliates"
              :headers="[
                { title: t('admin_dashboard_table_name'), key: 'name' },
                { title: t('admin_dashboard_table_email'), key: 'email' },
                { title: t('admin_dashboard_table_joined'), key: 'joinedAt' },
                { title: t('admin_dashboard_table_status'), key: 'status' },
                { title: t('admin_dashboard_table_commissions'), key: 'totalCommissions' },
              ]"
              :loading="dashboardStore.loading.tables"
              density="compact"
              hide-default-footer
            >
              <template #item.joinedAt="{ item }">
                {{ new Date(item.joinedAt).toLocaleDateString() }}
              </template>
              <template #item.totalCommissions="{ item }">
                ${{ item.totalCommissions.toLocaleString() }}
              </template>
              <template #item.status="{ item }">
                <VChip
                  :color="item.status === 'actif' ? 'success' : 'warning'"
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
            <span>{{ t('admin_dashboard_recent_payouts') }}</span>
            <VBtn
              variant="text"
              size="small"
              @click="dashboardStore.fetchTableData('recent_payouts')"
            >
              <VIcon icon="tabler-refresh" />
            </VBtn>
          </VCardTitle>
          <VCardText>
            <VDataTable
              :items="dashboardStore.recentPayouts"
              :headers="[
                { title: t('admin_dashboard_table_affiliate'), key: 'affiliateName' },
                { title: t('admin_dashboard_table_amount'), key: 'amount' },
                { title: t('admin_dashboard_table_status'), key: 'status' },
                { title: t('admin_dashboard_table_requested'), key: 'requestedAt' },
              ]"
              :loading="dashboardStore.loading.tables"
              density="compact"
              hide-default-footer
            >
              <template #item.amount="{ item }">
                ${{ item.amount.toLocaleString() }}
              </template>
              <template #item.requestedAt="{ item }">
                {{ new Date(item.requestedAt).toLocaleDateString() }}
              </template>
              <template #item.status="{ item }">
                <VChip
                  :color="item.status === 'pending' ? 'warning' : item.status === 'paid' ? 'success' : 'error'"
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
    </VRow>
  </div>
</template>

<style lang="scss" scoped>
.dashboard-header-actions {
  .period-selector-wrapper {
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
    .d-flex {
      gap: 0.5rem !important;
      flex-wrap: wrap;
    }
    
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
