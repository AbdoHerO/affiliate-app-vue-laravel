<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { $api } from '@/utils/api'
import { useAdvancedCharts } from '@/composables/useAdvancedCharts'
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

// State
const loading = ref({
  summary: false,
  charts: false,
  leaderboard: false,
  ledger: false,
  segments: false,
})

const error = ref<string | null>(null)
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
const fetchSummary = async () => {
  loading.value.summary = true
  error.value = null

  try {
    const response = await $api('/admin/reports/affiliates/summary', {
      method: 'GET',
      params: {
        date_start: filters.value.dateRange.start,
        date_end: filters.value.dateRange.end,
        ...filters.value,
      },
    })

    if (response.success) {
      // Sanitize the summary data before storing
      summary.value = {}
      for (const [key, value] of Object.entries(response.data)) {
        summary.value[key] = sanitizeKPI(value)
      }
    } else {
      throw new Error(response.message || 'Failed to fetch summary')
    }
  } catch (err) {
    error.value = err instanceof Error ? err.message : 'An error occurred'
    console.error('Error fetching affiliate summary:', err)
  } finally {
    loading.value.summary = false
  }
}

const fetchChartData = async () => {
  loading.value.charts = true

  try {
    const response = await $api('/admin/reports/affiliates/series', {
      method: 'GET',
      params: {
        date_start: filters.value.dateRange.start,
        date_end: filters.value.dateRange.end,
        period: filters.value.period,
        ...filters.value,
      },
    })

    if (response.success) {
      // Sanitize chart data
      chartData.value = {}
      for (const [key, value] of Object.entries(response.data)) {
        chartData.value[key] = sanitizeChartData(value)
      }
    }
  } catch (err) {
    console.error('Error fetching chart data:', err)
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

// Watchers
watch(() => filters.value.dateRange, refreshAll, { deep: true })
watch(() => filters.value.period, fetchChartData)
watch(() => filters.value.sort_by, fetchLeaderboard)

// Lifecycle
onMounted(() => {
  refreshAll()
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
    <VCard class="mb-6">
      <VCardTitle>
        <VIcon icon="tabler-filter" class="me-2" />
        {{ t('filters') }}
      </VCardTitle>

      <VCardText>
        <VRow>
          <!-- Date Range -->
          <VCol cols="12" md="4">
            <VLabel class="mb-2">{{ t('date_range') }}</VLabel>
            <div class="d-flex gap-2">
              <VTextField
                v-model="filters.dateRange.start"
                type="date"
                density="compact"
                variant="outlined"
                hide-details
              />
              <VTextField
                v-model="filters.dateRange.end"
                type="date"
                density="compact"
                variant="outlined"
                hide-details
              />
            </div>
          </VCol>

          <!-- Sort By -->
          <VCol cols="12" md="2">
            <VLabel class="mb-2">{{ t('sort_by') }}</VLabel>
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
              density="compact"
              variant="outlined"
              hide-details
            />
          </VCol>

          <!-- Min Orders -->
          <VCol cols="12" md="2">
            <VLabel class="mb-2">{{ t('min_orders') }}</VLabel>
            <VTextField
              v-model.number="filters.min_orders"
              type="number"
              min="0"
              density="compact"
              variant="outlined"
              hide-details
            />
          </VCol>

          <!-- Min Commission -->
          <VCol cols="12" md="2">
            <VLabel class="mb-2">{{ t('min_commission') }}</VLabel>
            <VTextField
              v-model.number="filters.min_commission"
              type="number"
              min="0"
              step="0.01"
              density="compact"
              variant="outlined"
              hide-details
            />
          </VCol>

          <!-- Period -->
          <VCol cols="12" md="2">
            <VLabel class="mb-2">{{ t('period') }}</VLabel>
            <VBtnToggle
              v-model="filters.period"
              variant="outlined"
              divided
              mandatory
              class="w-100"
            >
              <VBtn value="day" size="small">{{ t('day') }}</VBtn>
              <VBtn value="week" size="small">{{ t('week') }}</VBtn>
              <VBtn value="month" size="small">{{ t('month') }}</VBtn>
            </VBtnToggle>
          </VCol>
        </VRow>
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
              v-else-if="chartData.commissions_over_time"
              :data="{
                chartData: chartData.commissions_over_time,
                title: t('commissions_mad'),
                subtitle: t('earned_commissions'),
                growth: summary?.total_commissions?.delta || 0,
              }"
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
              v-else-if="chartData.payouts_over_time"
              :data="{
                chartData: chartData.payouts_over_time,
                title: t('payouts_mad'),
                subtitle: t('paid_withdrawals'),
                color: 'success',
              }"
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
