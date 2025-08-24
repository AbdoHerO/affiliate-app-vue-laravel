import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type {
  AffiliateDashboardStats,
  FilterOptions,
  TimeSeriesData,
  MyLead,
  MyOrder,
  MyCommission,
  ReferralClick,
  DashboardApiResponse,
} from '@/types/dashboard'
import { $api } from '@/utils/api'

export const useAffiliateDashboardStore = defineStore('affiliateDashboard', () => {
  // State
  const stats = ref<AffiliateDashboardStats | null>(null)
  const chartData = ref<Record<string, TimeSeriesData | any>>({})
  const tableData = ref<Record<string, any[]>>({})
  const referralLink = ref<{
    code: string
    link: string
    active: boolean
    created_at: string
  } | null>(null)
  const loading = ref({
    stats: false,
    charts: false,
    tables: false,
    referralLink: false,
  })
  const error = ref<string | null>(null)
  const lastUpdated = ref<Date | null>(null)
  
  // Filters
  const filters = ref<FilterOptions>({
    dateRange: {
      start: new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0], // 30 days ago
      end: new Date().toISOString().split('T')[0], // today
    },
    page: 1,
    perPage: 15,
  })

  // Getters
  const isLoading = computed(() => 
    loading.value.stats || loading.value.charts || loading.value.tables
  )

  const hasData = computed(() => stats.value !== null)

  const currentPoints = computed(() => stats.value?.overview.currentPoints ?? 0)
  const totalCommissions = computed(() => stats.value?.overview.totalCommissions ?? 0)
  const totalCommissionsMTD = computed(() => stats.value?.overview.totalCommissionsMTD ?? 0)
  const verifiedSignups = computed(() => stats.value?.overview.verifiedSignups ?? 0)
  const conversionRate = computed(() => stats.value?.overview.conversionRate ?? 0)
  const clickThroughRate = computed(() => stats.value?.overview.clickThroughRate ?? 0)

  const commissionsGrowth = computed(() => stats.value?.commissions.growth ?? 0)
  const ordersGrowth = computed(() => stats.value?.orders.growth ?? 0)
  const performanceTrends = computed(() => stats.value?.performance.trends ?? {
    clicks: 0,
    signups: 0,
    commissions: 0,
  })

  const nextPayoutAmount = computed(() => stats.value?.commissions.nextPayoutAmount ?? 0)
  const nextPayoutDate = computed(() => stats.value?.commissions.nextPayoutDate)

  // Actions
  const fetchStats = async (customFilters?: Partial<FilterOptions>) => {
    loading.value.stats = true
    error.value = null

    try {
      const params = { ...filters.value, ...customFilters }
      const response = await $api<DashboardApiResponse<AffiliateDashboardStats>>('/affiliate/dashboard/stats', {
        method: 'GET',
        params,
      })

      if (response.success) {
        stats.value = response.data
        lastUpdated.value = new Date()
      } else {
        throw new Error(response.message || 'Failed to fetch dashboard stats')
      }
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'An error occurred'
      console.error('Error fetching affiliate dashboard stats:', err)
    } finally {
      loading.value.stats = false
    }
  }

  const fetchChartData = async (period: string = 'month', customFilters?: Partial<FilterOptions>) => {
    loading.value.charts = true
    error.value = null

    try {
      const params = { ...filters.value, ...customFilters, period }
      const response = await $api<DashboardApiResponse<Record<string, any>>>('/affiliate/dashboard/charts', {
        method: 'GET',
        params,
      })

      if (response.success) {
        chartData.value = response.data
      } else {
        throw new Error(response.message || 'Failed to fetch chart data')
      }
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'An error occurred'
      console.error('Error fetching affiliate dashboard charts:', err)
    } finally {
      loading.value.charts = false
    }
  }

  const fetchTableData = async (type: string, customFilters?: Partial<FilterOptions>) => {
    loading.value.tables = true
    error.value = null

    try {
      const params = { ...filters.value, ...customFilters, type }
      const response = await $api<DashboardApiResponse<any[]>>('/affiliate/dashboard/tables', {
        method: 'GET',
        params,
      })

      if (response.success) {
        tableData.value[type] = response.data
      } else {
        throw new Error(response.message || 'Failed to fetch table data')
      }
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'An error occurred'
      console.error(`Error fetching affiliate dashboard table data (${type}):`, err)
    } finally {
      loading.value.tables = false
    }
  }

  const fetchReferralLink = async () => {
    loading.value.referralLink = true
    error.value = null

    try {
      const response = await $api<DashboardApiResponse<{
        code: string
        link: string
        active: boolean
        created_at: string
      }>>('/affiliate/dashboard/referral-link', {
        method: 'GET',
      })

      if (response.success) {
        referralLink.value = response.data
      } else {
        throw new Error(response.message || 'Failed to fetch referral link')
      }
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'An error occurred'
      console.error('Error fetching referral link:', err)
    } finally {
      loading.value.referralLink = false
    }
  }

  const updateFilters = (newFilters: Partial<FilterOptions>) => {
    filters.value = { ...filters.value, ...newFilters }
  }

  const refreshAll = async () => {
    await Promise.all([
      fetchStats(),
      fetchChartData(),
      fetchTableData('my_leads'),
      fetchTableData('my_orders'),
      fetchTableData('my_commissions'),
      fetchTableData('referral_clicks'),
      fetchReferralLink(),
    ])
  }

  const clearData = () => {
    stats.value = null
    chartData.value = {}
    tableData.value = {}
    referralLink.value = null
    error.value = null
    lastUpdated.value = null
  }

  // Copy referral link to clipboard
  const copyReferralLink = async () => {
    if (!referralLink.value?.link) {
      throw new Error('No referral link available')
    }

    try {
      await navigator.clipboard.writeText(referralLink.value.link)
      return true
    } catch (err) {
      console.error('Failed to copy referral link:', err)
      throw new Error('Failed to copy referral link')
    }
  }

  // Specific getters for chart data
  const signupsChartData = computed(() => chartData.value.signups_over_time as TimeSeriesData)
  const commissionsChartData = computed(() => chartData.value.commissions_over_time as TimeSeriesData)
  const pointsChartData = computed(() => chartData.value.points_over_time as TimeSeriesData)
  const topProductsChart = computed(() => chartData.value.top_products)
  const referralPerformanceChart = computed(() => chartData.value.referral_performance)

  // Specific getters for table data
  const myLeads = computed(() => tableData.value.my_leads as MyLead[] || [])
  const myOrders = computed(() => tableData.value.my_orders as MyOrder[] || [])
  const myCommissions = computed(() => tableData.value.my_commissions as MyCommission[] || [])
  const referralClicks = computed(() => tableData.value.referral_clicks as ReferralClick[] || [])

  return {
    // State
    stats,
    chartData,
    tableData,
    referralLink,
    loading,
    error,
    lastUpdated,
    filters,

    // Getters
    isLoading,
    hasData,
    currentPoints,
    totalCommissions,
    totalCommissionsMTD,
    verifiedSignups,
    conversionRate,
    clickThroughRate,
    commissionsGrowth,
    ordersGrowth,
    performanceTrends,
    nextPayoutAmount,
    nextPayoutDate,

    // Chart data getters
    signupsChartData,
    commissionsChartData,
    pointsChartData,
    topProductsChart,
    referralPerformanceChart,

    // Table data getters
    myLeads,
    myOrders,
    myCommissions,
    referralClicks,

    // Actions
    fetchStats,
    fetchChartData,
    fetchTableData,
    fetchReferralLink,
    updateFilters,
    refreshAll,
    clearData,
    copyReferralLink,
  }
})
