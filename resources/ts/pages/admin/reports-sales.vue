<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { $api } from '@/utils/api'
// import { useAdvancedCharts } from '@/composables/useAdvancedCharts'
import { useSafeApexChart } from '@/composables/useSafeApexChart'
import {
  SalesAreaChart,
  ProfitLineChart,
  SessionAnalyticsDonut,
  AdvancedStatsCard,
} from '@/components/charts/advanced'
import {
  sanitizeReportData,
  sanitizeKPI,
  sanitizeChartData,
  sanitizeTableData,
  sanitizeAreaChartData,
  sanitizeDonutChartData,
  formatDisplayNumber,
  getTrendDisplay,
  safeNumber,
} from '@/utils/reportDataSanitizer'
import {
  exportSalesOrdersCSV,
  exportTopAffiliatesCSV,
  exportTopProductsCSV,
} from '@/utils/csvExporter'
import {
  exportApexChart,
  exportElementAsImage,
  exportDashboardSummary,
  isExportAvailable,
  getExportErrorMessage,
} from '@/utils/chartExporter'

const { t } = useI18n()

definePage({
  meta: {
    requiresAuth: true,
    requiresRole: 'admin',
  },
})

// Request cancellation - separate controllers for each request type
let summaryController: AbortController | null = null
let chartsController: AbortController | null = null
let tablesController: AbortController | null = null

// State
const loading = ref({
  summary: false,
  charts: false,
  tables: false,
})

const error = ref<string | null>(null)
const hasNoData = ref({
  summary: false,
  charts: false,
  tables: false,
})
const summary = ref<any>(null)
const chartData = ref<any>({})
const tableData = ref<any>({})

// Filters
const filters = ref({
  dateRange: {
    start: new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
    end: new Date().toISOString().split('T')[0],
  },
  period: 'day',
  status: '',
  affiliate_ids: [],
  product_ids: [],
  category_ids: [],
  boutique_ids: [],
  country: '',
  city: '',
  page: 1,
  per_page: 15,
})

// Quick date presets
const datePresets = computed(() => [
  {
    title: t('last_7_days'),
    value: 7,
  },
  {
    title: t('last_30_days'),
    value: 30,
  },
  {
    title: t('last_90_days'),
    value: 90,
  },
  {
    title: t('this_month'),
    value: 'month',
  },
  {
    title: t('custom'),
    value: 'custom',
  },
])

// Computed
const isLoading = computed(() =>
  loading.value.summary || loading.value.charts || loading.value.tables
)

const kpiCards = computed(() => {
  if (!summary.value) return []

  const cards = [
    {
      key: 'total_sales',
      title: t('total_sales'),
      icon: 'tabler-currency-dollar',
      color: 'success',
      currency: 'MAD',
    },
    {
      key: 'orders_count',
      title: t('orders_count'),
      icon: 'tabler-shopping-cart',
      color: 'primary',
    },
    {
      key: 'avg_order_value',
      title: t('avg_order_value'),
      icon: 'tabler-chart-line',
      color: 'info',
      currency: 'MAD',
    },
    {
      key: 'delivered_rate',
      title: t('delivered_rate'),
      icon: 'tabler-truck-delivery',
      color: 'success',
      unit: '%',
    },
    {
      key: 'return_rate',
      title: t('return_rate'),
      icon: 'tabler-arrow-back',
      color: 'warning',
      unit: '%',
    },
    {
      key: 'commissions_accrued',
      title: t('commissions_accrued'),
      icon: 'tabler-coins',
      color: 'secondary',
      currency: 'MAD',
    },
  ]

  return cards.map(card => {
    const kpiData = sanitizeKPI(summary.value[card.key])
    const trendData = getTrendDisplay(kpiData.delta)

    return {
      title: card.title,
      value: kpiData.value,
      displayValue: formatDisplayNumber(kpiData.value, {
        currency: card.currency,
        unit: card.unit,
        decimals: card.currency ? 0 : (card.unit === '%' ? 1 : 0),
      }),
      icon: card.icon,
      color: card.color,
      trend: kpiData.delta !== null ? {
        value: kpiData.delta,
        icon: trendData.icon,
        color: trendData.color,
        text: trendData.text,
        label: t('vs_previous_period'),
      } : null,
      isValid: kpiData.isValid,
    }
  })
})

// Methods
const cancelAllRequests = () => {
  if (summaryController) {
    summaryController.abort()
    summaryController = null
  }
  if (chartsController) {
    chartsController.abort()
    chartsController = null
  }
  if (tablesController) {
    tablesController.abort()
    tablesController = null
  }
}

const fetchSummary = async () => {
  loading.value.summary = true
  error.value = null
  hasNoData.value.summary = false

  try {
    // Cancel previous summary request only
    if (summaryController) {
      summaryController.abort()
    }
    summaryController = new AbortController()

    const response = await $api('/admin/reports/sales/summary', {
      method: 'GET',
      signal: summaryController.signal,
      params: {
        date_start: filters.value.dateRange.start,
        date_end: filters.value.dateRange.end,
        ...filters.value,
      },
    })

    if (response.success) {
      // Check if data is empty
      const hasData = response.data && Object.keys(response.data).length > 0
      hasNoData.value.summary = !hasData

      if (hasData) {
        // Sanitize the summary data before storing
        summary.value = {}
        for (const [key, value] of Object.entries(response.data)) {
          summary.value[key] = sanitizeKPI(value)
        }
      } else {
        summary.value = null
      }
    } else {
      throw new Error(response.message || 'Failed to fetch summary')
    }
  } catch (err: any) {
    // Don't set error if request was aborted (user navigated away)
    if (err.name !== 'AbortError') {
      error.value = err instanceof Error ? err.message : 'An error occurred'
      console.error('Error fetching sales summary:', err)
    }
  } finally {
    loading.value.summary = false
  }
}

const fetchChartData = async () => {
  loading.value.charts = true
  hasNoData.value.charts = false

  try {
    // Cancel previous charts request only
    if (chartsController) {
      chartsController.abort()
    }
    chartsController = new AbortController()

    // Fetch all chart data in parallel with proper error handling
    const [seriesResponse, statusResponse, productsResponse] = await Promise.allSettled([
      $api('/admin/reports/sales/series', {
        method: 'GET',
        signal: chartsController.signal,
        params: {
          date_start: filters.value.dateRange.start,
          date_end: filters.value.dateRange.end,
          period: filters.value.period,
        },
      }),
      $api('/admin/reports/sales/status-breakdown', {
        method: 'GET',
        signal: chartsController.signal,
        params: {
          date_start: filters.value.dateRange.start,
          date_end: filters.value.dateRange.end,
        },
      }),
      $api('/admin/reports/sales/top-products', {
        method: 'GET',
        signal: chartsController.signal,
        params: {
          date_start: filters.value.dateRange.start,
          date_end: filters.value.dateRange.end,
          limit: 10,
        },
      }),
    ])

    // Initialize chart data
    chartData.value = {}
    let hasAnyData = false

    // Process series data
    if (seriesResponse.status === 'fulfilled' && seriesResponse.value.success) {
      for (const [key, value] of Object.entries(seriesResponse.value.data)) {
        const sanitized = sanitizeChartData(value)
        chartData.value[key] = sanitized
        if (!sanitized.isEmpty) hasAnyData = true
      }
    } else if (seriesResponse.status === 'rejected') {
      console.error('Error fetching series data:', seriesResponse.reason)
    }

    // Process status breakdown
    if (statusResponse.status === 'fulfilled' && statusResponse.value.success) {
      const sanitized = sanitizeChartData(statusResponse.value.data)
      chartData.value.status_breakdown = sanitized
      if (!sanitized.isEmpty) hasAnyData = true
    } else if (statusResponse.status === 'rejected') {
      console.error('Error fetching status breakdown:', statusResponse.reason)
    }

    // Process top products
    if (productsResponse.status === 'fulfilled' && productsResponse.value.success) {
      const sanitized = sanitizeChartData(productsResponse.value.data)
      chartData.value.top_products = sanitized
      if (!sanitized.isEmpty) hasAnyData = true
    } else if (productsResponse.status === 'rejected') {
      console.error('Error fetching top products:', productsResponse.reason)
    }

    hasNoData.value.charts = !hasAnyData

  } catch (err: any) {
    // Don't set error if request was aborted
    if (err.name !== 'AbortError') {
      console.error('Error fetching chart data:', err)
      hasNoData.value.charts = true
    }
  } finally {
    loading.value.charts = false
  }
}

const fetchTableData = async () => {
  loading.value.tables = true
  hasNoData.value.tables = false

  try {
    // Cancel previous tables request only
    if (tablesController) {
      tablesController.abort()
    }
    tablesController = new AbortController()

    const [ordersResponse, affiliatesResponse] = await Promise.allSettled([
      $api('/admin/reports/sales/orders', {
        method: 'GET',
        signal: tablesController.signal,
        params: {
          date_start: filters.value.dateRange.start,
          date_end: filters.value.dateRange.end,
          page: filters.value.page,
          per_page: filters.value.per_page,
        },
      }),
      $api('/admin/reports/sales/top-affiliates', {
        method: 'GET',
        signal: tablesController.signal,
        params: {
          date_start: filters.value.dateRange.start,
          date_end: filters.value.dateRange.end,
          limit: 10,
        },
      }),
    ])

    // Initialize table data
    tableData.value = {}
    let hasAnyData = false

    // Process orders data
    if (ordersResponse.status === 'fulfilled' && ordersResponse.value.success) {
      const ordersData = ordersResponse.value.data
      tableData.value.orders = {
        ...ordersData,
        data: sanitizeTableData(ordersData.data || []),
      }
      if (ordersData.data && ordersData.data.length > 0) hasAnyData = true
    } else if (ordersResponse.status === 'rejected') {
      console.error('Error fetching orders data:', ordersResponse.reason)
    }

    // Process affiliates data
    if (affiliatesResponse.status === 'fulfilled' && affiliatesResponse.value.success) {
      const affiliatesData = sanitizeTableData(affiliatesResponse.value.data || [])
      tableData.value.affiliates = affiliatesData
      if (affiliatesData.length > 0) hasAnyData = true
    } else if (affiliatesResponse.status === 'rejected') {
      console.error('Error fetching affiliates data:', affiliatesResponse.reason)
    }

    hasNoData.value.tables = !hasAnyData

  } catch (err: any) {
    // Don't set error if request was aborted
    if (err.name !== 'AbortError') {
      console.error('Error fetching table data:', err)
      hasNoData.value.tables = true
    }
  } finally {
    loading.value.tables = false
  }
}

const applyDatePreset = (preset: number | string) => {
  const now = new Date()

  if (typeof preset === 'number') {
    filters.value.dateRange.start = new Date(now.getTime() - preset * 24 * 60 * 60 * 1000).toISOString().split('T')[0]
    filters.value.dateRange.end = now.toISOString().split('T')[0]
  } else if (preset === 'month') {
    const startOfMonth = new Date(now.getFullYear(), now.getMonth(), 1)
    filters.value.dateRange.start = startOfMonth.toISOString().split('T')[0]
    filters.value.dateRange.end = now.toISOString().split('T')[0]
  }
  // 'custom' doesn't change the dates
}

const refreshAll = async () => {
  await Promise.all([
    fetchSummary(),
    fetchChartData(),
    fetchTableData(),
  ])
}

const resetFilters = () => {
  filters.value = {
    dateRange: {
      start: new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
      end: new Date().toISOString().split('T')[0],
    },
    period: 'day',
    status: '',
    affiliate_ids: [],
    product_ids: [],
    category_ids: [],
    boutique_ids: [],
    country: '',
    city: '',
    page: 1,
    per_page: 15,
  }
  refreshAll()
}

const exportData = () => {
  try {
    const exportData = {
      timestamp: new Date().toISOString(),
      filters: filters.value,
      summary: summary.value,
      charts: chartData.value,
      tables: tableData.value,
    }

    const blob = new Blob([JSON.stringify(exportData, null, 2)], { type: 'application/json' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `sales-report-${filters.value.dateRange.start}-to-${filters.value.dateRange.end}.json`
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    URL.revokeObjectURL(url)
  } catch (error) {
    console.error('Error exporting data:', error)
  }
}

// Export methods
const exportOrdersCSV = () => {
  if (tableData.value.orders?.data) {
    exportSalesOrdersCSV(
      tableData.value.orders.data,
      `sales-orders-${filters.value.dateRange.start}-to-${filters.value.dateRange.end}`
    )
  }
}

const exportAffiliatesCSV = () => {
  if (tableData.value.affiliates) {
    exportTopAffiliatesCSV(
      tableData.value.affiliates,
      `top-affiliates-${filters.value.dateRange.start}-to-${filters.value.dateRange.end}`
    )
  }
}

const exportProductsCSV = () => {
  if (chartData.value.top_products?.table_data) {
    exportTopProductsCSV(
      chartData.value.top_products.table_data,
      `top-products-${filters.value.dateRange.start}-to-${filters.value.dateRange.end}`
    )
  }
}

const exportDashboard = async () => {
  // Check if export is available
  const exportCheck = isExportAvailable()
  if (!exportCheck.available) {
    error.value = exportCheck.reason || 'Export not available'
    return
  }

  try {
    await exportDashboardSummary({
      filename: `sales-dashboard-${filters.value.dateRange.start}-to-${filters.value.dateRange.end}`,
      title: t('sales_reports'),
      includeKPIs: true,
      includeCharts: true,
    })
  } catch (err) {
    const exportError = err instanceof Error ? err : new Error('Export failed')
    error.value = getExportErrorMessage(exportError)
    console.error('Error exporting dashboard:', exportError)
  }
}

// Helper methods
const getStatusColor = (status: string) => {
  const statusColors: Record<string, string> = {
    livre: 'success',
    echec: 'error',
    retour: 'warning',
    annule: 'secondary',
    confirme: 'primary',
    en_attente: 'info',
  }
  return statusColors[status] || 'default'
}

const getStatusLabel = (status: string) => {
  const statusLabels: Record<string, string> = {
    livre: t('delivered'),
    echec: t('failed'),
    retour: t('returned'),
    annule: t('canceled'),
    confirme: t('confirmed'),
    en_attente: t('pending'),
  }
  return statusLabels[status] || status
}

// Debounced refresh to prevent recursive calls
let refreshTimeout: NodeJS.Timeout | null = null
const debouncedRefresh = () => {
  if (refreshTimeout) {
    clearTimeout(refreshTimeout)
  }
  refreshTimeout = setTimeout(() => {
    refreshAll()
  }, 300)
}

let chartTimeout: NodeJS.Timeout | null = null
const debouncedChartRefresh = () => {
  if (chartTimeout) {
    clearTimeout(chartTimeout)
  }
  chartTimeout = setTimeout(() => {
    fetchChartData()
  }, 300)
}

// Watchers with debouncing to prevent recursive calls
watch(() => filters.value.dateRange, debouncedRefresh, { deep: true })
watch(() => filters.value.period, debouncedChartRefresh)

// Lifecycle
onMounted(() => {
  refreshAll()
})

onBeforeUnmount(() => {
  // Cancel any pending requests
  cancelAllRequests()

  // Clear any pending timeouts
  if (refreshTimeout) {
    clearTimeout(refreshTimeout)
    refreshTimeout = null
  }
  if (chartTimeout) {
    clearTimeout(chartTimeout)
    chartTimeout = null
  }

  // Force clear all reactive data to prevent vnode errors
  summary.value = null
  chartData.value = {}
  tableData.value = {}

  // Reset loading states
  loading.value = {
    summary: false,
    charts: false,
    tables: false,
  }

  // Reset error states
  error.value = null
  hasNoData.value = {
    summary: false,
    charts: false,
    tables: false,
  }
})
</script>

<template>
  <div>
    <!-- Page Header -->
    <div class="d-flex align-center justify-space-between mb-6">
      <div>
        <h1 class="text-h4 font-weight-bold mb-1">
          {{ t('sales_reports') }}
        </h1>
        <p class="text-body-1 mb-0">
          {{ t('view_detailed_sales_analytics') }}
        </p>
      </div>

      <div class="d-flex gap-3">
        <VBtn
          variant="outlined"
          prepend-icon="tabler-refresh"
          :loading="isLoading"
          @click="refreshAll"
        >
          {{ t('refresh') }}
        </VBtn>

        <VMenu>
          <template #activator="{ props }">
            <VBtn
              color="primary"
              prepend-icon="tabler-download"
              v-bind="props"
            >
              {{ t('export') }}
              <VIcon icon="tabler-chevron-down" class="ms-1" />
            </VBtn>
          </template>

          <VList>
            <VListItem @click="exportData">
              <template #prepend>
                <VIcon icon="tabler-file-type-json" />
              </template>
              <VListItemTitle>{{ t('export_json') }}</VListItemTitle>
            </VListItem>

            <VListItem @click="exportDashboard">
              <template #prepend>
                <VIcon icon="tabler-photo" />
              </template>
              <VListItemTitle>{{ t('export_dashboard_image') }}</VListItemTitle>
            </VListItem>

            <VDivider />

            <VListItem @click="exportOrdersCSV">
              <template #prepend>
                <VIcon icon="tabler-file-spreadsheet" />
              </template>
              <VListItemTitle>{{ t('export_orders_csv') }}</VListItemTitle>
            </VListItem>

            <VListItem @click="exportAffiliatesCSV">
              <template #prepend>
                <VIcon icon="tabler-file-spreadsheet" />
              </template>
              <VListItemTitle>{{ t('export_affiliates_csv') }}</VListItemTitle>
            </VListItem>

            <VListItem @click="exportProductsCSV">
              <template #prepend>
                <VIcon icon="tabler-file-spreadsheet" />
              </template>
              <VListItemTitle>{{ t('export_products_csv') }}</VListItemTitle>
            </VListItem>
          </VList>
        </VMenu>
      </div>
    </div>

    <!-- Filters Card -->
    <VCard class="mb-6 filters-card" elevation="2">
      <VCardTitle class="d-flex align-center justify-space-between pa-6 pb-4">
        <div class="d-flex align-center">
          <VAvatar size="32" color="primary" variant="tonal" class="me-3">
            <VIcon icon="tabler-filter" size="18" />
          </VAvatar>
          <div>
            <h3 class="text-h6 font-weight-semibold mb-0">{{ t('filters') }}</h3>
            <p class="text-body-2 text-medium-emphasis mb-0">{{ t('filter_your_sales_data') }}</p>
          </div>
        </div>
        <div class="d-flex gap-2">
          <VBtn
            variant="outlined"
            color="primary"
            prepend-icon="tabler-refresh"
            :loading="isLoading"
            @click="refreshAll"
            class="filter-action-btn"
          >
            {{ t('apply') }}
          </VBtn>
          <VBtn
            variant="text"
            color="secondary"
            prepend-icon="tabler-x"
            @click="resetFilters"
            class="filter-action-btn"
          >
            {{ t('reset') }}
          </VBtn>
        </div>
      </VCardTitle>

      <VDivider />

      <VCardText class="pa-6">
        <!-- Main Filters Row -->
        <div class="filters-layout">
          <!-- Primary Filters Section -->
          <div class="primary-filters">
            <!-- Period Toggle with Icon -->
            <div class="filter-group">
              <div class="filter-header">
                <VIcon icon="tabler-clock" size="16" class="me-2 text-primary" />
                <span class="filter-title">{{ t('period') }}</span>
              </div>
              <VBtnToggle
                v-model="filters.period"
                variant="outlined"
                color="primary"
                divided
                mandatory
                class="period-toggle"
              >
                <VBtn value="day" class="period-btn">
                  <VIcon icon="tabler-calendar-day" size="16" class="me-1" />
                  {{ t('day') }}
                </VBtn>
                <VBtn value="week" class="period-btn">
                  <VIcon icon="tabler-calendar-week" size="16" class="me-1" />
                  {{ t('week') }}
                </VBtn>
                <VBtn value="month" class="period-btn">
                  <VIcon icon="tabler-calendar-month" size="16" class="me-1" />
                  {{ t('month') }}
                </VBtn>
              </VBtnToggle>
            </div>

            <!-- Date Range with Icon -->
            <div class="filter-group filter-group-wide">
              <div class="filter-header">
                <VIcon icon="tabler-calendar" size="16" class="me-2 text-primary" />
                <span class="filter-title">{{ t('date_range') }}</span>
              </div>
              <div class="date-range-container">
                <VTextField
                  v-model="filters.dateRange.start"
                  type="date"
                  variant="outlined"
                  density="comfortable"
                  hide-details
                  prepend-inner-icon="tabler-calendar-event"
                  class="date-input"
                  :placeholder="t('start_date')"
                />
                <div class="date-separator">
                  <VIcon icon="tabler-arrow-right" size="16" class="text-medium-emphasis" />
                </div>
                <VTextField
                  v-model="filters.dateRange.end"
                  type="date"
                  variant="outlined"
                  density="comfortable"
                  hide-details
                  prepend-inner-icon="tabler-calendar-event"
                  class="date-input"
                  :placeholder="t('end_date')"
                />
              </div>
            </div>
          </div>

          <!-- Secondary Filters Section -->
          <div class="secondary-filters">
            <!-- Quick Date Presets -->
            <div class="filter-group">
              <div class="filter-header">
                <VIcon icon="tabler-clock-hour-3" size="16" class="me-2 text-primary" />
                <span class="filter-title">{{ t('quick_select') }}</span>
              </div>
              <VSelect
                :items="datePresets"
                item-title="title"
                item-value="value"
                variant="outlined"
                density="comfortable"
                hide-details
                prepend-inner-icon="tabler-calendar-time"
                class="modern-select"
                @update:model-value="applyDatePreset"
              />
            </div>

            <!-- Status Filter -->
            <div class="filter-group">
              <div class="filter-header">
                <VIcon icon="tabler-flag" size="16" class="me-2 text-primary" />
                <span class="filter-title">{{ t('status') }}</span>
              </div>
              <VSelect
                v-model="filters.status"
                :items="[
                  { title: t('all'), value: '' },
                  { title: t('delivered'), value: 'livre' },
                  { title: t('failed'), value: 'echec' },
                  { title: t('returned'), value: 'retour' },
                  { title: t('canceled'), value: 'annule' },
                  { title: t('confirmed'), value: 'confirme' },
                  { title: t('pending'), value: 'en_attente' },
                ]"
                item-title="title"
                item-value="value"
                variant="outlined"
                density="comfortable"
                hide-details
                prepend-inner-icon="tabler-list-check"
                class="modern-select"
              />
            </div>
          </div>
        </div>
      </VCardText>
    </VCard>

    <!-- Error Alert -->
    <VAlert
      v-if="error"
      type="error"
      variant="tonal"
      class="mb-6"
      closable
      @click:close="error = null"
    >
      {{ error }}
    </VAlert>

    <!-- KPI Cards -->
    <VRow class="mb-6" data-export="kpi-cards">
      <VCol
        v-for="(card, index) in kpiCards"
        :key="index"
        cols="12"
        sm="6"
        lg="4"
        xl="2"
      >
        <VCard>
          <VCardText>
            <div class="d-flex align-center justify-space-between">
              <div>
                <p class="text-sm text-medium-emphasis mb-1">
                  {{ card.title }}
                </p>
                <h3 class="text-h5 font-weight-bold" :class="{ 'text-error': !card.isValid }">
                  {{ card.displayValue }}
                </h3>
                <div
                  v-if="card.trend"
                  class="d-flex align-center mt-1"
                >
                  <VIcon
                    :icon="card.trend.icon"
                    :color="card.trend.color"
                    size="16"
                    class="me-1"
                  />
                  <span
                    class="text-sm"
                    :class="`text-${card.trend.color}`"
                  >
                    {{ card.trend.text }}
                  </span>
                  <span class="text-sm text-medium-emphasis ms-1">
                    {{ card.trend.label }}
                  </span>
                </div>
                <div v-else-if="!card.isValid" class="text-xs text-error mt-1">
                  {{ t('invalid_data') }}
                </div>
              </div>

              <VAvatar
                :color="card.color"
                variant="tonal"
                size="40"
              >
                <VIcon :icon="card.icon" />
              </VAvatar>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Charts Section -->
    <VRow class="mb-6">
      <!-- Sales Over Time -->
      <VCol cols="12" lg="8">
        <VCard data-export="chart">
          <VCardTitle>
            <VIcon icon="tabler-chart-line" class="me-2" />
            {{ t('sales_over_time') }}
          </VCardTitle>

          <VCardText>
            <div v-if="loading.charts" class="text-center py-8">
              <VProgressCircular indeterminate color="primary" />
              <p class="mt-2">{{ t('loading_charts') }}</p>
            </div>

            <div v-else-if="chartData.sales_over_time && !chartData.sales_over_time.isEmpty">
              <SalesAreaChart
                :data="sanitizeAreaChartData(
                  chartData.sales_over_time.data,
                  t('sales_mad'),
                  t('delivered_orders_only'),
                  (summary?.total_sales?.value || 0) + ' MAD',
                  chartData.sales_over_time.growth || '+0%',
                  'success'
                )"
                :loading="loading.charts"
              />
            </div>

            <div v-else-if="hasNoData.charts || error" class="text-center py-8 text-medium-emphasis">
              <VIcon icon="tabler-chart-line" size="48" class="mb-2 opacity-50" />
              <p v-if="error">{{ error }}</p>
              <p v-else>{{ t('no_sales_data') }}</p>
              <VBtn
                v-if="error"
                variant="outlined"
                size="small"
                class="mt-2"
                @click="fetchChartData"
              >
                {{ t('retry') }}
              </VBtn>
            </div>

            <div v-else class="text-center py-8 text-medium-emphasis">
              <VIcon icon="tabler-chart-line" size="48" class="mb-2 opacity-50" />
              <p>{{ t('no_data_available') }}</p>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Orders by Status -->
      <VCol cols="12" lg="4">
        <VCard data-export="chart">
          <VCardTitle>
            <VIcon icon="tabler-chart-donut" class="me-2" />
            {{ t('orders_by_status') }}
          </VCardTitle>

          <VCardText>
            <div v-if="loading.charts" class="text-center py-8">
              <VProgressCircular indeterminate color="primary" />
            </div>

            <SessionAnalyticsDonut
              v-else-if="chartData.status_breakdown && !chartData.status_breakdown.isEmpty"
              :data="sanitizeDonutChartData(
                chartData.status_breakdown.delivered || 0,
                chartData.status_breakdown.pending || 0,
                t('delivery_rate')
              )"
              :loading="loading.charts"
            />

            <div v-else class="text-center py-8 text-medium-emphasis">
              {{ t('no_data_available') }}
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Top Products -->
      <VCol cols="12" lg="6">
        <VCard>
          <VCardTitle>
            <VIcon icon="tabler-trophy" class="me-2" />
            {{ t('top_products_by_revenue') }}
          </VCardTitle>

          <VCardText>
            <div v-if="loading.charts" class="text-center py-8">
              <VProgressCircular indeterminate color="primary" />
            </div>

            <ProfitLineChart
              v-else-if="chartData.top_products && !chartData.top_products.isEmpty"
              :data="sanitizeAreaChartData(
                chartData.top_products.data,
                t('revenue_mad'),
                t('top_10_products'),
                (summary?.total_sales?.value || 0) + ' MAD',
                summary?.total_sales?.delta || '+0%',
                'primary'
              )"
              :loading="loading.charts"
            />

            <div v-else class="text-center py-8 text-medium-emphasis">
              {{ t('no_data_available') }}
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Advanced Stats Card -->
      <VCol cols="12" lg="6">
        <AdvancedStatsCard
          :data="{
            title: t('total_revenue'),
            value: String(summary?.total_sales?.value || 0) + ' MAD',
            subtitle: t('platform_earnings'),
            icon: 'tabler-currency-dollar',
            color: 'success',
            trend: summary?.total_sales?.delta ? {
              value: String(Math.abs(summary.total_sales.delta)) + '%',
              direction: summary.total_sales.delta >= 0 ? 'up' : 'down',
              period: t('vs_previous_period')
            } : undefined
          }"
          :loading="loading.summary"
        />
      </VCol>
    </VRow>

    <!-- Data Tables -->
    <VRow>
      <!-- Orders Table -->
      <VCol cols="12" lg="8">
        <VCard>
          <VCardTitle class="d-flex align-center justify-space-between">
            <div class="d-flex align-center">
              <VIcon icon="tabler-list" class="me-2" />
              {{ t('recent_orders') }}
            </div>

            <VBtn
              variant="text"
              size="small"
              prepend-icon="tabler-refresh"
              :loading="loading.tables"
              @click="fetchTableData"
            >
              {{ t('refresh') }}
            </VBtn>
          </VCardTitle>

          <VCardText>
            <VDataTable
              :items="tableData.orders?.data || []"
              :headers="[
                { title: t('order_ref'), key: 'order_ref' },
                { title: t('date'), key: 'date' },
                { title: t('affiliate'), key: 'affiliate_name' },
                { title: t('customer'), key: 'customer_name' },
                { title: t('status'), key: 'status' },
                { title: t('items'), key: 'items_count' },
                { title: t('total_mad'), key: 'total', align: 'end' },
                { title: t('commission_mad'), key: 'commission', align: 'end' },
              ]"
              :loading="loading.tables"
              density="compact"
              :items-per-page="filters.per_page"
              hide-default-footer
            >
              <template #item.status="{ item }">
                <VChip
                  :color="getStatusColor(item.status)"
                  size="small"
                  variant="tonal"
                >
                  {{ getStatusLabel(item.status) }}
                </VChip>
              </template>

              <template #item.total="{ item }">
                {{ item.total.toFixed(2) }} MAD
              </template>

              <template #item.commission="{ item }">
                {{ item.commission.toFixed(2) }} MAD
              </template>
            </VDataTable>

            <!-- Pagination -->
            <div v-if="tableData.orders?.pagination" class="d-flex justify-center mt-4">
              <VPagination
                v-model="filters.page"
                :length="tableData.orders.pagination.last_page"
                :total-visible="7"
                @update:model-value="fetchTableData"
              />
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Top Affiliates -->
      <VCol cols="12" lg="4">
        <VCard>
          <VCardTitle>
            <VIcon icon="tabler-users" class="me-2" />
            {{ t('top_affiliates_by_sales') }}
          </VCardTitle>

          <VCardText>
            <VList v-if="tableData.affiliates?.length">
              <VListItem
                v-for="(affiliate, index) in tableData.affiliates"
                :key="affiliate.id"
                class="px-0"
              >
                <template #prepend>
                  <VAvatar
                    :color="index < 3 ? ['warning', 'secondary', 'success'][index] : 'grey'"
                    size="32"
                    variant="tonal"
                  >
                    {{ index + 1 }}
                  </VAvatar>
                </template>

                <VListItemTitle class="text-sm font-weight-medium">
                  {{ affiliate.name }}
                </VListItemTitle>

                <VListItemSubtitle class="text-xs">
                  {{ affiliate.orders_count }} {{ t('orders') }} •
                  {{ affiliate.delivered_rate }}% {{ t('delivered') }}
                </VListItemSubtitle>

                <template #append>
                  <div class="text-end">
                    <div class="text-sm font-weight-bold">
                      {{ affiliate.total_sales.toFixed(0) }} MAD
                    </div>
                    <div class="text-xs text-medium-emphasis">
                      {{ affiliate.total_commission.toFixed(0) }} {{ t('commission') }}
                    </div>
                  </div>
                </template>
              </VListItem>
            </VList>

            <div v-else-if="loading.tables" class="text-center py-8">
              <VProgressCircular indeterminate color="primary" />
            </div>

            <div v-else class="text-center py-8 text-medium-emphasis">
              {{ t('no_data_available') }}
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>

<style scoped>
/* Modern Filters Design */
.filters-card {
  border-radius: 16px !important;
  border: 1px solid rgba(var(--v-theme-primary), 0.12);
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08) !important;
  overflow: hidden;
}

.filters-card :deep(.v-card-title) {
  background: linear-gradient(135deg, rgba(var(--v-theme-primary), 0.02) 0%, rgba(var(--v-theme-primary), 0.06) 100%);
}

.filters-layout {
  display: flex;
  flex-direction: column;
  gap: 2rem;
}

.primary-filters,
.secondary-filters {
  display: flex;
  flex-wrap: wrap;
  gap: 1.5rem;
  align-items: flex-start;
}

.filter-group {
  display: flex;
  flex-direction: column;
  min-width: 240px;
  flex: 1;
}

.filter-group-wide {
  flex: 2;
  min-width: 480px;
}

.filter-header {
  display: flex;
  align-items: center;
  margin-bottom: 0.75rem;
  padding-bottom: 0.25rem;
  border-bottom: 2px solid rgba(var(--v-theme-primary), 0.1);
}

.filter-title {
  font-size: 0.875rem;
  font-weight: 600;
  color: rgb(var(--v-theme-on-surface));
  letter-spacing: 0.025em;
}

/* Date Range Specific Styles */
.date-range-container {
  display: flex;
  align-items: center;
  gap: 1rem;
  width: 100%;
}

.date-input {
  flex: 1;
  min-width: 140px;
}

.date-input :deep(.v-field) {
  border-radius: 12px;
  box-shadow: 0 1px 6px rgba(0, 0, 0, 0.08);
  transition: all 0.2s ease;
}

.date-input :deep(.v-field:hover) {
  box-shadow: 0 2px 12px rgba(var(--v-theme-primary), 0.15);
  border-color: rgba(var(--v-theme-primary), 0.5);
}

.date-input :deep(.v-field--focused) {
  box-shadow: 0 2px 12px rgba(var(--v-theme-primary), 0.25);
}

.date-separator {
  display: flex;
  align-items: center;
  justify-content: center;
  min-width: 32px;
  height: 32px;
  background: rgba(var(--v-theme-primary), 0.08);
  border-radius: 50%;
  flex-shrink: 0;
}

/* Period Toggle Styles */
.period-toggle {
  width: 100%;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 1px 6px rgba(0, 0, 0, 0.08);
}

.period-toggle :deep(.v-btn-group) {
  width: 100%;
  border-radius: 12px;
}

.period-btn {
  flex: 1;
  min-height: 44px;
  font-weight: 500;
  transition: all 0.2s ease;
}

.period-btn :deep(.v-btn__content) {
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.period-toggle :deep(.v-btn--active) {
  background: rgba(var(--v-theme-primary), 0.12) !important;
  color: rgb(var(--v-theme-primary)) !important;
  box-shadow: inset 0 2px 4px rgba(var(--v-theme-primary), 0.2);
}

/* Modern Select Styles */
.modern-select {
  width: 100%;
}

.modern-select :deep(.v-field) {
  border-radius: 12px;
  box-shadow: 0 1px 6px rgba(0, 0, 0, 0.08);
  transition: all 0.2s ease;
}

.modern-select :deep(.v-field:hover) {
  box-shadow: 0 2px 12px rgba(var(--v-theme-primary), 0.15);
  border-color: rgba(var(--v-theme-primary), 0.5);
}

.modern-select :deep(.v-field--focused) {
  box-shadow: 0 2px 12px rgba(var(--v-theme-primary), 0.25);
}

.modern-select :deep(.v-field__prepend-inner) {
  padding-inline-end: 0.75rem;
}

.modern-select :deep(.v-field__prepend-inner .v-icon) {
  color: rgba(var(--v-theme-primary), 0.7);
}

/* Action Button Styles */
.filter-action-btn {
  min-height: 40px;
  border-radius: 10px;
  font-weight: 500;
  text-transform: none;
  letter-spacing: 0.025em;
  transition: all 0.2s ease;
}

.filter-action-btn:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

/* Responsive Design */
@media (max-width: 1200px) {
  .primary-filters,
  .secondary-filters {
    gap: 1.25rem;
  }
  
  .filter-group {
    min-width: 200px;
  }
  
  .filter-group-wide {
    min-width: 400px;
  }
}

@media (max-width: 960px) {
  .filters-layout {
    gap: 1.5rem;
  }
  
  .primary-filters,
  .secondary-filters {
    flex-direction: column;
    gap: 1rem;
  }
  
  .filter-group,
  .filter-group-wide {
    min-width: 100%;
  }
  
  .date-range-container {
    flex-direction: column;
    align-items: stretch;
    gap: 0.75rem;
  }
  
  .date-separator {
    transform: rotate(90deg);
    align-self: center;
    margin: 0.25rem 0;
  }
}

@media (max-width: 768px) {
  .filters-card :deep(.v-card-title) {
    flex-direction: column;
    align-items: stretch;
    gap: 1rem;
  }
  
  .period-toggle {
    margin-top: 0.5rem;
  }
  
  .period-btn {
    min-height: 40px;
    font-size: 0.875rem;
  }
  
  .filter-action-btn {
    min-height: 36px;
    font-size: 0.875rem;
  }
}

@media (max-width: 600px) {
  .filters-card :deep(.v-card-title),
  .filters-card :deep(.v-card-text) {
    padding: 1rem;
  }
  
  .filter-header {
    margin-bottom: 0.5rem;
  }
  
  .filter-title {
    font-size: 0.8rem;
  }
  
  .period-btn {
    min-height: 36px;
    font-size: 0.8rem;
  }
  
  .period-btn :deep(.v-icon) {
    display: none;
  }
}

/* Loading and Animation States */
.filter-group {
  animation: fadeInUp 0.3s ease;
}

@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* Focus States */
.modern-select :deep(.v-field--focused .v-field__prepend-inner .v-icon) {
  color: rgb(var(--v-theme-primary));
}
</style>
