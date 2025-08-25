<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount, watch, nextTick } from 'vue'
import { useI18n } from 'vue-i18n'
import { $api } from '@/utils/api'
// import { useAdvancedCharts } from '@/composables/useAdvancedCharts'
import { useSafeApexChart } from '@/composables/useSafeApexChart'
import {
  SalesAreaChart,
  ProfitLineChart,
  SessionAnalyticsDonut,
  AdvancedStatsCard,
  EarningReportsWeekly,
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
  exportAffiliateLeaderboardCSV,
  exportCommissionLedgerCSV,
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
let leaderboardController: AbortController | null = null
let ledgerController: AbortController | null = null
let segmentsController: AbortController | null = null

// Flag to prevent watchers from triggering during reset
const isResetting = ref(false)

// State
const loading = ref({
  summary: false,
  charts: false,
  leaderboard: false,
  ledger: false,
  segments: false,
})

const error = ref<string | null>(null)
const hasNoData = ref({
  summary: false,
  charts: false,
  leaderboard: false,
  ledger: false,
  segments: false,
})
const summary = ref<any>(null)
const chartData = ref<any>({})
const leaderboard = ref<any[]>([])
const ledger = ref<any>({})
const segments = ref<any>({})

// Filters
const filters = ref({
  dateRange: {
    start: new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
    end: new Date().toISOString().split('T')[0],
  },
  period: 'day',
  affiliate_status: '',
  country: '',
  city: '',
  min_orders: 0,
  min_commission: 0,
  sort_by: 'commission',
  page: 1,
  per_page: 15,
})

// Computed
const isLoading = computed(() =>
  Object.values(loading.value).some(Boolean)
)

const kpiCards = computed(() => {
  if (!summary.value) return []

  const cards = [
    {
      key: 'active_affiliates',
      title: t('active_affiliates'),
      icon: 'tabler-users',
      color: 'primary',
    },
    {
      key: 'new_affiliates',
      title: t('new_affiliates'),
      icon: 'tabler-user-plus',
      color: 'success',
    },
    {
      key: 'total_commissions',
      title: t('total_commissions'),
      icon: 'tabler-coins',
      color: 'warning',
      currency: 'MAD',
    },
    {
      key: 'total_payouts',
      title: t('total_payouts'),
      icon: 'tabler-credit-card',
      color: 'info',
      currency: 'MAD',
    },
    {
      key: 'conversion_rate',
      title: t('conversion_rate'),
      icon: 'tabler-trending-up',
      color: 'secondary',
      unit: '%',
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

const performanceSegments = computed(() => {
  if (!segments.value) return []

  return [
    {
      title: t('top_earners'),
      count: segments.value.top_earners?.count || 0,
      description: segments.value.top_earners?.description || '',
      color: 'warning',
      icon: 'tabler-trophy',
    },
    {
      title: t('rising_stars'),
      count: segments.value.rising?.count || 0,
      description: segments.value.rising?.description || '',
      color: 'success',
      icon: 'tabler-trending-up',
    },
    {
      title: t('at_risk'),
      count: segments.value.at_risk?.count || 0,
      description: segments.value.at_risk?.description || '',
      color: 'error',
      icon: 'tabler-alert-triangle',
    },
    {
      title: t('dormant'),
      count: segments.value.dormant?.count || 0,
      description: segments.value.dormant?.description || '',
      color: 'secondary',
      icon: 'tabler-sleep',
    },
  ]
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
  if (leaderboardController) {
    leaderboardController.abort()
    leaderboardController = null
  }
  if (ledgerController) {
    ledgerController.abort()
    ledgerController = null
  }
  if (segmentsController) {
    segmentsController.abort()
    segmentsController = null
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

    const response = await $api('/admin/reports/affiliates/summary', {
      method: 'GET',
      signal: summaryController.signal,
      params: {
        date_start: filters.value.dateRange.start,
        date_end: filters.value.dateRange.end,
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
    // Don't set error if request was aborted
    if (err.name !== 'AbortError') {
      error.value = err instanceof Error ? err.message : 'An error occurred'
      console.error('Error fetching affiliate summary:', err)
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

    const response = await $api('/admin/reports/affiliates/series', {
      method: 'GET',
      signal: chartsController.signal,
      params: {
        date_start: filters.value.dateRange.start,
        date_end: filters.value.dateRange.end,
        period: filters.value.period,
      },
    })

    if (response.success) {
      // Sanitize chart data
      chartData.value = {}
      let hasAnyData = false

      for (const [key, value] of Object.entries(response.data)) {
        const sanitized = sanitizeChartData(value)
        chartData.value[key] = sanitized
        if (!sanitized.isEmpty) hasAnyData = true
      }

      hasNoData.value.charts = !hasAnyData
    } else {
      hasNoData.value.charts = true
    }
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

const fetchLeaderboard = async () => {
  loading.value.leaderboard = true

  try {
    const response = await $api('/admin/reports/affiliates/leaderboard', {
      method: 'GET',
      params: {
        date_start: filters.value.dateRange.start,
        date_end: filters.value.dateRange.end,
        sort_by: filters.value.sort_by,
        limit: 20,
        ...filters.value,
      },
    })

    if (response.success) {
      leaderboard.value = sanitizeTableData(response.data || [])
    }
  } catch (err) {
    console.error('Error fetching leaderboard:', err)
  } finally {
    loading.value.leaderboard = false
  }
}

const fetchLedger = async () => {
  loading.value.ledger = true

  try {
    const response = await $api('/admin/reports/affiliates/ledger', {
      method: 'GET',
      params: {
        date_start: filters.value.dateRange.start,
        date_end: filters.value.dateRange.end,
        page: filters.value.page,
        per_page: filters.value.per_page,
        ...filters.value,
      },
    })

    if (response.success) {
      // Sanitize ledger data
      ledger.value = {
        ...response.data,
        data: sanitizeTableData(response.data.data || []),
      }
    }
  } catch (err) {
    console.error('Error fetching ledger:', err)
  } finally {
    loading.value.ledger = false
  }
}

const fetchSegments = async () => {
  loading.value.segments = true

  try {
    const response = await $api('/admin/reports/affiliates/segments', {
      method: 'GET',
      params: {
        date_start: filters.value.dateRange.start,
        date_end: filters.value.dateRange.end,
        ...filters.value,
      },
    })

    if (response.success) {
      segments.value = response.data
    }
  } catch (err) {
    console.error('Error fetching segments:', err)
  } finally {
    loading.value.segments = false
  }
}

const refreshAll = async () => {
  await Promise.all([
    fetchSummary(),
    fetchChartData(),
    fetchLeaderboard(),
    fetchLedger(),
    fetchSegments(),
  ])
}

const resetFilters = async () => {
  // Cancel all pending requests first
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
  if (leaderboardTimeout) {
    clearTimeout(leaderboardTimeout)
    leaderboardTimeout = null
  }

  // Set resetting flag to prevent watchers from triggering
  isResetting.value = true

  // Reset filters
  filters.value = {
    dateRange: {
      start: new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
      end: new Date().toISOString().split('T')[0],
    },
    period: 'day',
    affiliate_status: '',
    country: '',
    city: '',
    min_orders: 0,
    min_commission: 0,
    sort_by: 'commission',
    page: 1,
    per_page: 15,
  }

  // Wait a tick to ensure watchers don't trigger
  await nextTick()
  
  // Reset the flag
  isResetting.value = false

  // Now refresh data
  refreshAll()
}

const exportData = () => {
  try {
    const exportData = {
      timestamp: new Date().toISOString(),
      filters: filters.value,
      summary: summary.value,
      charts: chartData.value,
      leaderboard: leaderboard.value,
      ledger: ledger.value,
      segments: segments.value,
    }

    const blob = new Blob([JSON.stringify(exportData, null, 2)], { type: 'application/json' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `affiliate-performance-${filters.value.dateRange.start}-to-${filters.value.dateRange.end}.json`
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    URL.revokeObjectURL(url)
  } catch (error) {
    console.error('Error exporting data:', error)
  }
}

// Export methods
const exportLeaderboardCSV = () => {
  if (leaderboard.value) {
    exportAffiliateLeaderboardCSV(
      leaderboard.value,
      `affiliate-leaderboard-${filters.value.dateRange.start}-to-${filters.value.dateRange.end}`
    )
  }
}

const exportLedgerCSV = () => {
  if (ledger.value?.data) {
    exportCommissionLedgerCSV(
      ledger.value.data,
      `commission-ledger-${filters.value.dateRange.start}-to-${filters.value.dateRange.end}`
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
      filename: `affiliate-performance-${filters.value.dateRange.start}-to-${filters.value.dateRange.end}`,
      title: t('affiliate_performance'),
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
const getCommissionStatusColor = (status: string) => {
  const statusColors: Record<string, string> = {
    pending: 'warning',
    approved: 'info',
    paid: 'success',
    rejected: 'error',
  }
  return statusColors[status] || 'default'
}

// Debounced refresh to prevent recursive calls
let refreshTimeout: NodeJS.Timeout | null = null
const debouncedRefresh = () => {
  if (isResetting.value) return // Skip if we're resetting
  
  if (refreshTimeout) {
    clearTimeout(refreshTimeout)
  }
  refreshTimeout = setTimeout(() => {
    refreshAll()
  }, 300)
}

let chartTimeout: NodeJS.Timeout | null = null
const debouncedChartRefresh = () => {
  if (isResetting.value) return // Skip if we're resetting
  
  if (chartTimeout) {
    clearTimeout(chartTimeout)
  }
  chartTimeout = setTimeout(() => {
    fetchChartData()
  }, 300)
}

let leaderboardTimeout: NodeJS.Timeout | null = null
const debouncedLeaderboardRefresh = () => {
  if (isResetting.value) return // Skip if we're resetting
  
  if (leaderboardTimeout) {
    clearTimeout(leaderboardTimeout)
  }
  leaderboardTimeout = setTimeout(() => {
    fetchLeaderboard()
  }, 300)
}

// Watchers with debouncing to prevent recursive calls
watch(() => filters.value.dateRange, debouncedRefresh, { deep: true })
watch(() => filters.value.period, debouncedChartRefresh)
watch(() => filters.value.sort_by, debouncedLeaderboardRefresh)

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
  if (leaderboardTimeout) {
    clearTimeout(leaderboardTimeout)
    leaderboardTimeout = null
  }

  // Force clear all reactive data to prevent vnode errors
  summary.value = null
  chartData.value = {}
  leaderboard.value = []
  ledger.value = {}
  segments.value = {}

  // Reset loading states
  loading.value = {
    summary: false,
    charts: false,
    leaderboard: false,
    ledger: false,
    segments: false,
  }

  // Reset error states
  error.value = null
  hasNoData.value = {
    summary: false,
    charts: false,
    leaderboard: false,
    ledger: false,
    segments: false,
  }
})
</script>

<template>
  <div>
    <!-- Page Header -->
    <div class="d-flex align-center justify-space-between mb-6">
      <div>
        <h1 class="text-h4 font-weight-bold mb-1">
          {{ t('affiliate_performance') }}
        </h1>
        <p class="text-body-1 mb-0">
          {{ t('view_affiliate_performance_analytics') }}
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

            <VListItem @click="exportLeaderboardCSV">
              <template #prepend>
                <VIcon icon="tabler-file-spreadsheet" />
              </template>
              <VListItemTitle>{{ t('export_leaderboard_csv') }}</VListItemTitle>
            </VListItem>

            <VListItem @click="exportLedgerCSV">
              <template #prepend>
                <VIcon icon="tabler-file-spreadsheet" />
              </template>
              <VListItemTitle>{{ t('export_ledger_csv') }}</VListItemTitle>
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
            <p class="text-body-2 text-medium-emphasis mb-0">{{ t('filter_your_affiliate_data') }}</p>
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
            <!-- Date Range with Icon -->
            <div class="filter-group">
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
          </div>

          <!-- Secondary Filters Section -->
          <div class="secondary-filters">
            <!-- Sort By -->
            <div class="filter-group">
              <div class="filter-header">
                <VIcon icon="tabler-sort-descending" size="16" class="me-2 text-primary" />
                <span class="filter-title">{{ t('sort_by') }}</span>
              </div>
              <VSelect
                v-model="filters.sort_by"
                :items="[
                  { title: t('commission'), value: 'commission' },
                  { title: t('sales'), value: 'sales' },
                  { title: t('orders'), value: 'orders' },
                  { title: t('payouts'), value: 'payouts' },
                ]"
                item-title="title"
                item-value="value"
                variant="outlined"
                density="comfortable"
                hide-details
                prepend-inner-icon="tabler-list"
                class="modern-select"
              />
            </div>

            <!-- Min Orders -->
            <div class="filter-group">
              <div class="filter-header">
                <VIcon icon="tabler-shopping-cart" size="16" class="me-2 text-primary" />
                <span class="filter-title">{{ t('min_orders') }}</span>
              </div>
              <VTextField
                v-model.number="filters.min_orders"
                type="number"
                min="0"
                variant="outlined"
                density="comfortable"
                hide-details
                prepend-inner-icon="tabler-hash"
                class="modern-input"
                :placeholder="t('enter_minimum_orders')"
              />
            </div>

            <!-- Min Commission -->
            <div class="filter-group">
              <div class="filter-header">
                <VIcon icon="tabler-coins" size="16" class="me-2 text-primary" />
                <span class="filter-title">{{ t('min_commission') }}</span>
              </div>
              <VTextField
                v-model.number="filters.min_commission"
                type="number"
                min="0"
                step="0.01"
                variant="outlined"
                density="comfortable"
                hide-details
                prepend-inner-icon="tabler-currency-dollar"
                class="modern-input"
                :placeholder="t('enter_minimum_commission')"
                suffix="MAD"
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
    <VRow class="mb-6">
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

    <!-- Performance Segments -->
    <VRow class="mb-6">
      <VCol
        v-for="(segment, index) in performanceSegments"
        :key="index"
        cols="12"
        sm="6"
        lg="3"
      >
        <VCard>
          <VCardText>
            <div class="d-flex align-center justify-space-between">
              <div>
                <p class="text-sm text-medium-emphasis mb-1">
                  {{ segment.title }}
                </p>
                <h3 class="text-h4 font-weight-bold">
                  {{ segment.count }}
                </h3>
                <p class="text-xs text-medium-emphasis mt-1">
                  {{ segment.description }}
                </p>
              </div>

              <VAvatar
                :color="segment.color"
                variant="tonal"
                size="48"
              >
                <VIcon :icon="segment.icon" size="24" />
              </VAvatar>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Charts Section -->
    <VRow class="mb-6">
      <!-- Commissions Over Time -->
      <VCol cols="12" lg="6">
        <VCard>
          <VCardTitle>
            <VIcon icon="tabler-chart-area" class="me-2" />
            {{ t('commissions_over_time') }}
          </VCardTitle>

          <VCardText>
            <div v-if="loading.charts" class="text-center py-8">
              <VProgressCircular indeterminate color="primary" />
              <p class="mt-2">{{ t('loading_charts') }}</p>
            </div>

            <SalesAreaChart
              v-else-if="chartData.commissions_over_time && !chartData.commissions_over_time.isEmpty"
              :data="sanitizeAreaChartData(
                chartData.commissions_over_time.data,
                t('commissions_mad'),
                t('earned_commissions'),
                (summary?.total_commissions?.value || 0) + ' MAD',
                summary?.total_commissions?.delta || '+0%',
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

      <!-- Payouts Over Time -->
      <VCol cols="12" lg="6">
        <VCard>
          <VCardTitle>
            <VIcon icon="tabler-chart-bar" class="me-2" />
            {{ t('payouts_over_time') }}
          </VCardTitle>

          <VCardText>
            <div v-if="loading.charts" class="text-center py-8">
              <VProgressCircular indeterminate color="primary" />
            </div>

            <ProfitLineChart
              v-else-if="chartData.payouts_over_time && !chartData.payouts_over_time.isEmpty"
              :data="sanitizeAreaChartData(
                chartData.payouts_over_time.data,
                t('payouts_mad'),
                t('paid_withdrawals'),
                (summary?.total_payouts?.value || 0) + ' MAD',
                summary?.total_payouts?.delta || '+0%',
                'success'
              )"
              :loading="loading.charts"
            />

            <div v-else class="text-center py-8 text-medium-emphasis">
              {{ t('no_data_available') }}
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Data Tables -->
    <VRow>
      <!-- Affiliate Leaderboard -->
      <VCol cols="12" lg="8">
        <VCard>
          <VCardTitle class="d-flex align-center justify-space-between">
            <div class="d-flex align-center">
              <VIcon icon="tabler-trophy" class="me-2" />
              {{ t('affiliate_leaderboard') }}
            </div>

            <VBtn
              variant="text"
              size="small"
              prepend-icon="tabler-refresh"
              :loading="loading.leaderboard"
              @click="fetchLeaderboard"
            >
              {{ t('refresh') }}
            </VBtn>
          </VCardTitle>

          <VCardText>
            <VDataTable
              :items="leaderboard"
              :headers="[
                { title: t('rank'), key: 'rank', width: '60px' },
                { title: t('affiliate'), key: 'name' },
                { title: t('email'), key: 'email' },
                { title: t('orders'), key: 'orders_count', align: 'center' },
                { title: t('delivered_rate'), key: 'delivered_rate', align: 'center' },
                { title: t('sales_mad'), key: 'total_sales', align: 'end' },
                { title: t('commission_mad'), key: 'total_commission', align: 'end' },
                { title: t('payouts_mad'), key: 'total_payouts', align: 'end' },
                { title: t('aov_mad'), key: 'avg_order_value', align: 'end' },
                { title: t('return_rate'), key: 'return_rate', align: 'center' },
              ]"
              :loading="loading.leaderboard"
              density="compact"
              hide-default-footer
            >
              <template #item.rank="{ index }">
                <VAvatar
                  :color="index < 3 ? ['warning', 'secondary', 'success'][index] : 'grey'"
                  size="24"
                  variant="tonal"
                >
                  <span class="text-xs font-weight-bold">{{ index + 1 }}</span>
                </VAvatar>
              </template>

              <template #item.delivered_rate="{ item }">
                <VChip
                  :color="item.delivered_rate >= 80 ? 'success' : item.delivered_rate >= 60 ? 'warning' : 'error'"
                  size="small"
                  variant="tonal"
                >
                  {{ item.delivered_rate }}%
                </VChip>
              </template>

              <template #item.total_sales="{ item }">
                {{ item.total_sales.toFixed(0) }} MAD
              </template>

              <template #item.total_commission="{ item }">
                {{ item.total_commission.toFixed(0) }} MAD
              </template>

              <template #item.total_payouts="{ item }">
                {{ item.total_payouts.toFixed(0) }} MAD
              </template>

              <template #item.avg_order_value="{ item }">
                {{ item.avg_order_value.toFixed(0) }} MAD
              </template>

              <template #item.return_rate="{ item }">
                <VChip
                  :color="item.return_rate <= 5 ? 'success' : item.return_rate <= 15 ? 'warning' : 'error'"
                  size="small"
                  variant="tonal"
                >
                  {{ item.return_rate }}%
                </VChip>
              </template>
            </VDataTable>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Commission Ledger -->
      <VCol cols="12" lg="4">
        <VCard>
          <VCardTitle>
            <VIcon icon="tabler-list-details" class="me-2" />
            {{ t('commission_ledger') }}
          </VCardTitle>

          <VCardText>
            <VList v-if="ledger.data?.length">
              <VListItem
                v-for="commission in ledger.data"
                :key="commission.id"
                class="px-0"
              >
                <VListItemTitle class="text-sm font-weight-medium">
                  {{ commission.affiliate_name }}
                </VListItemTitle>

                <VListItemSubtitle class="text-xs">
                  {{ commission.order_ref }} • {{ commission.product_name }}
                </VListItemSubtitle>

                <VListItemSubtitle class="text-xs text-medium-emphasis">
                  {{ commission.date }}
                </VListItemSubtitle>

                <template #append>
                  <div class="text-end">
                    <div class="text-sm font-weight-bold">
                      {{ commission.commission.toFixed(2) }} MAD
                    </div>
                    <VChip
                      :color="getCommissionStatusColor(commission.status)"
                      size="x-small"
                      variant="tonal"
                    >
                      {{ commission.status }}
                    </VChip>
                  </div>
                </template>
              </VListItem>
            </VList>

            <div v-else-if="loading.ledger" class="text-center py-8">
              <VProgressCircular indeterminate color="primary" />
            </div>

            <div v-else class="text-center py-8 text-medium-emphasis">
              {{ t('no_data_available') }}
            </div>

            <!-- Pagination -->
            <div v-if="ledger.pagination" class="d-flex justify-center mt-4">
              <VPagination
                v-model="filters.page"
                :length="ledger.pagination.last_page"
                :total-visible="5"
                size="small"
                @update:model-value="fetchLedger"
              />
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

/* Modern Input and Select Styles */
.modern-input,
.modern-select {
  width: 100%;
}

.modern-input :deep(.v-field),
.modern-select :deep(.v-field) {
  border-radius: 12px;
  box-shadow: 0 1px 6px rgba(0, 0, 0, 0.08);
  transition: all 0.2s ease;
}

.modern-input :deep(.v-field:hover),
.modern-select :deep(.v-field:hover) {
  box-shadow: 0 2px 12px rgba(var(--v-theme-primary), 0.15);
  border-color: rgba(var(--v-theme-primary), 0.5);
}

.modern-input :deep(.v-field--focused),
.modern-select :deep(.v-field--focused) {
  box-shadow: 0 2px 12px rgba(var(--v-theme-primary), 0.25);
}

.modern-input :deep(.v-field__prepend-inner),
.modern-select :deep(.v-field__prepend-inner) {
  padding-inline-end: 0.75rem;
}

.modern-input :deep(.v-field__prepend-inner .v-icon),
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
  
  .filter-group {
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
.modern-input :deep(.v-field--focused .v-field__prepend-inner .v-icon),
.modern-select :deep(.v-field--focused .v-field__prepend-inner .v-icon) {
  color: rgb(var(--v-theme-primary));
}

/* Suffix Styling */
.modern-input :deep(.v-field__suffix) {
  color: rgba(var(--v-theme-on-surface), 0.6);
  font-weight: 500;
  padding-inline-start: 0.5rem;
}
</style>
