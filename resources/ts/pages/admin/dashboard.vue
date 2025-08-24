<script setup lang="ts">
import { onMounted, ref, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuth } from '@/composables/useAuth'
import { useAdminDashboardStore } from '@/stores/dashboard/adminDashboard'
import { useNotifications } from '@/composables/useNotifications'
import StatisticsCard from '@/components/dashboard/StatisticsCard.vue'
import DashboardChart from '@/components/charts/DashboardChart.vue'
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

// Date range picker
const dateRange = ref([
  new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
  new Date().toISOString().split('T')[0],
])

// Breadcrumbs
const breadcrumbs = computed(() => [
  { title: 'Admin', disabled: false, href: '/admin' },
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

const chartConfigs = computed(() => [
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

// Methods
const refreshData = async () => {
  try {
    await dashboardStore.refreshAll()
    if (autoRefresh.value) {
      showSuccess('Dashboard data refreshed')
    }
  } catch (error) {
    showError('Failed to refresh dashboard data')
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
          Admin Dashboard
        </h1>
        <p class="text-body-1 text-medium-emphasis">
          Welcome back, {{ user?.nom_complet }}! Here's what's happening with your platform.
        </p>
      </div>

      <div class="d-flex align-center gap-3">
        <!-- Auto Refresh Toggle -->
        <VTooltip text="Auto refresh every 5 minutes">
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
          Refresh
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
                label="Start Date"
                type="date"
                variant="outlined"
                density="compact"
                class="mb-3"
              />
              <VTextField
                v-model="dateRange[1]"
                label="End Date"
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
                Apply
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
          :error="dashboardStore.error"
          size="medium"
        />
      </VCol>
    </VRow>

    <!-- Charts Section -->
    <VRow class="mb-6">
      <VCol cols="12">
        <div class="d-flex align-center justify-space-between mb-4">
          <h2 class="text-h5 font-weight-bold">
            {{ t('dashboard_analytics') }}
          </h2>

          <!-- Period Selector -->
          <VBtnToggle
            v-model="selectedPeriod"
            color="primary"
            variant="outlined"
            divided
            @update:model-value="changePeriod"
          >
            <VBtn value="day" size="small">
              Day
            </VBtn>
            <VBtn value="week" size="small">
              Week
            </VBtn>
            <VBtn value="month" size="small">
              Month
            </VBtn>
            <VBtn value="year" size="small">
              Year
            </VBtn>
          </VBtnToggle>
        </div>
      </VCol>

      <VCol
        v-for="(chart, index) in chartConfigs"
        :key="index"
        v-bind="chart.cols"
      >
        <DashboardChart
          :type="chart.type"
          :data="chart.data"
          :title="chart.title"
          :loading="dashboardStore.loading.charts"
          :error="dashboardStore.error"
          height="350"
        />
      </VCol>
    </VRow>

    <!-- Recent Data Tables -->
    <VRow>
      <VCol cols="12" md="6">
        <VCard>
          <VCardTitle class="d-flex align-center justify-space-between">
            <span>Recent Affiliates</span>
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
                { title: 'Name', key: 'name' },
                { title: 'Email', key: 'email' },
                { title: 'Joined', key: 'joinedAt' },
                { title: 'Status', key: 'status' },
                { title: 'Commissions', key: 'totalCommissions' },
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
            <span>Recent Payout Requests</span>
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
                { title: 'Affiliate', key: 'affiliateName' },
                { title: 'Amount', key: 'amount' },
                { title: 'Status', key: 'status' },
                { title: 'Requested', key: 'requestedAt' },
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

