<script setup lang="ts">
import { onMounted, ref, computed, watch } from 'vue'
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
const breadcrumbs = [
  { title: 'Affiliate', disabled: false, href: '/affiliate' },
  { title: 'Dashboard', disabled: true, href: '/affiliate/dashboard' },
]

// Computed properties
const kpiCards = computed(() => {
  if (!dashboardStore.stats) return []

  const { overview, performance, commissions, orders } = dashboardStore.stats

  return [
    {
      title: 'Current Points',
      value: overview.currentPoints,
      icon: 'tabler-star',
      color: 'warning',
      subtitle: 'Available points',
    },
    {
      title: 'Total Commissions',
      value: overview.totalCommissions,
      prefix: '$',
      icon: 'tabler-currency-dollar',
      color: 'success',
      trend: {
        value: commissions.growth,
        label: 'vs last month',
      },
    },
    {
      title: 'This Month',
      value: overview.totalCommissionsMTD,
      prefix: '$',
      icon: 'tabler-calendar',
      color: 'info',
      subtitle: 'Monthly earnings',
    },
    {
      title: 'Verified Signups',
      value: overview.verifiedSignups,
      icon: 'tabler-user-check',
      color: 'primary',
      subtitle: `${overview.conversionRate.toFixed(1)}% conversion`,
    },
    {
      title: 'Total Orders',
      value: overview.totalOrders,
      icon: 'tabler-shopping-cart',
      color: 'secondary',
      trend: {
        value: orders.growth,
        label: 'vs last month',
      },
    },
    {
      title: 'Click Rate',
      value: `${overview.clickThroughRate.toFixed(1)}%`,
      icon: 'tabler-click',
      color: 'purple',
      subtitle: 'Click-through rate',
    },
  ]
})

const chartConfigs = computed(() => [
  {
    title: 'My Signups Over Time',
    type: 'line' as const,
    data: dashboardStore.signupsChartData,
    cols: { cols: 12, md: 6 },
  },
  {
    title: 'My Commissions Over Time',
    type: 'area' as const,
    data: dashboardStore.commissionsChartData,
    cols: { cols: 12, md: 6 },
  },
  {
    title: 'Top Performing Products',
    type: 'bar' as const,
    data: dashboardStore.topProductsChart,
    cols: { cols: 12, md: 6 },
  },
  {
    title: 'Referral Performance',
    type: 'doughnut' as const,
    data: dashboardStore.referralPerformanceChart,
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

const copyReferralLink = async () => {
  try {
    await dashboardStore.copyReferralLink()
    showSuccess('Referral link copied to clipboard!')
  } catch (error) {
    showError('Failed to copy referral link')
  }
}

const shareReferralLink = () => {
  if (navigator.share && dashboardStore.referralLink) {
    navigator.share({
      title: 'Join our affiliate program',
      text: 'Join our affiliate program and start earning commissions!',
      url: dashboardStore.referralLink.link,
    })
  } else {
    showReferralLinkDialog.value = true
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
  <div class="affiliate-dashboard">
    <!-- Breadcrumbs -->
    <Breadcrumbs :items="breadcrumbs" />

    <!-- Header -->
    <div class="d-flex align-center justify-space-between mb-6">
      <div>
        <h1 class="text-h4 font-weight-bold mb-1">
          My Dashboard
        </h1>
        <p class="text-body-1 text-medium-emphasis">
          Welcome back, {{ user?.nom_complet }}! Track your performance and earnings.
        </p>
      </div>

      <div class="d-flex align-center gap-3">
        <!-- Referral Link Actions -->
        <VBtn
          color="primary"
          variant="elevated"
          prepend-icon="tabler-share"
          @click="shareReferralLink"
        >
          Share Link
        </VBtn>

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

    <!-- Referral Link Card -->
    <VCard
      v-if="dashboardStore.referralLink"
      class="mb-6"
      color="primary"
      variant="tonal"
    >
      <VCardText>
        <div class="d-flex align-center justify-space-between">
          <div>
            <h6 class="text-h6 mb-2">
              Your Referral Link
            </h6>
            <p class="text-body-2 mb-0 font-family-monospace">
              {{ dashboardStore.referralLink.link }}
            </p>
          </div>
          <div class="d-flex gap-2">
            <VBtn
              variant="elevated"
              size="small"
              @click="copyReferralLink"
            >
              <VIcon start icon="tabler-copy" />
              Copy
            </VBtn>
            <VBtn
              variant="elevated"
              size="small"
              @click="shareReferralLink"
            >
              <VIcon start icon="tabler-share" />
              Share
            </VBtn>
          </div>
        </div>
      </VCardText>
    </VCard>

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
            Performance Analytics
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
            <span>My Recent Leads</span>
            <VBtn
              variant="text"
              size="small"
              @click="dashboardStore.fetchTableData('my_leads')"
            >
              <VIcon icon="tabler-refresh" />
            </VBtn>
          </VCardTitle>
          <VCardText>
            <VDataTable
              :items="dashboardStore.myLeads"
              :headers="[
                { title: 'Name', key: 'name' },
                { title: 'Email', key: 'email' },
                { title: 'Status', key: 'status' },
                { title: 'Signup Date', key: 'signupDate' },
              ]"
              :loading="dashboardStore.loading.tables"
              density="compact"
              hide-default-footer
            >
              <template #item.signupDate="{ item }">
                {{ new Date(item.signupDate).toLocaleDateString() }}
              </template>
              <template #item.status="{ item }">
                <VChip
                  :color="item.status === 'verified' ? 'success' : 'warning'"
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
            <span>My Recent Orders</span>
            <VBtn
              variant="text"
              size="small"
              @click="dashboardStore.fetchTableData('my_orders')"
            >
              <VIcon icon="tabler-refresh" />
            </VBtn>
          </VCardTitle>
          <VCardText>
            <VDataTable
              :items="dashboardStore.myOrders"
              :headers="[
                { title: 'Product', key: 'productTitle' },
                { title: 'Amount', key: 'amount' },
                { title: 'Commission', key: 'commission' },
                { title: 'Status', key: 'status' },
              ]"
              :loading="dashboardStore.loading.tables"
              density="compact"
              hide-default-footer
            >
              <template #item.amount="{ item }">
                ${{ item.amount.toLocaleString() }}
              </template>
              <template #item.commission="{ item }">
                ${{ item.commission.toLocaleString() }}
              </template>
              <template #item.status="{ item }">
                <VChip
                  :color="item.status === 'delivered' ? 'success' : item.status === 'shipped' ? 'info' : 'warning'"
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

    <!-- Referral Link Dialog -->
    <VDialog
      v-model="showReferralLinkDialog"
      max-width="500"
    >
      <VCard>
        <VCardTitle>Share Your Referral Link</VCardTitle>
        <VCardText>
          <VTextField
            :model-value="dashboardStore.referralLink?.link"
            label="Referral Link"
            readonly
            variant="outlined"
            append-inner-icon="tabler-copy"
            @click:append-inner="copyReferralLink"
          />
          <div class="mt-4">
            <p class="text-body-2 text-medium-emphasis">
              Share this link with potential customers to earn commissions on their purchases.
            </p>
          </div>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="text"
            @click="showReferralLinkDialog = false"
          >
            Close
          </VBtn>
          <VBtn
            color="primary"
            @click="copyReferralLink"
          >
            Copy Link
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
