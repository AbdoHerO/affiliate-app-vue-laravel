import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type {
  AdminDashboardStats,
  FilterOptions,
  TimeSeriesData,
  RecentAffiliate,
  RecentPayoutRequest,
  RecentTicket,
  RecentActivity,
  DashboardApiResponse,
  PaginatedResponse,
} from '@/types/dashboard'
import { $api } from '@/utils/api'

export const useAdminDashboardStore = defineStore('adminDashboard', () => {
  // State
  const stats = ref<AdminDashboardStats | null>(null)
  const chartData = ref<Record<string, TimeSeriesData | any>>({})
  const tableData = ref<Record<string, any[]>>({})
  const loading = ref({
    stats: false,
    charts: false,
    tables: false,
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

  const totalAffiliates = computed(() => stats.value?.overview.totalAffiliates ?? 0)
  const totalRevenue = computed(() => stats.value?.overview.totalRevenue ?? 0)
  const totalCommissions = computed(() => stats.value?.overview.totalCommissions ?? 0)
  const pendingPayouts = computed(() => stats.value?.overview.pendingPayouts ?? 0)

  const signupsGrowth = computed(() => {
    if (!stats.value) return 0
    const { signupsLast7d, signupsMTD } = stats.value.overview
    return signupsMTD > 0 ? ((signupsLast7d / signupsMTD) * 100) : 0
  })

  const revenueGrowth = computed(() => stats.value?.revenue.growth ?? 0)
  const commissionGrowth = computed(() => stats.value?.commissions.growth ?? 0)

  // Actions
  const fetchStats = async (customFilters?: Partial<FilterOptions>) => {
    loading.value.stats = true
    error.value = null

    try {
      const params = { ...filters.value, ...customFilters }
      const response = await $api<DashboardApiResponse<AdminDashboardStats>>('/admin/dashboard/stats', {
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
      console.error('Error fetching admin dashboard stats:', err)
    } finally {
      loading.value.stats = false
    }
  }

  const fetchChartData = async (period: string = 'month', customFilters?: Partial<FilterOptions>) => {
    loading.value.charts = true
    error.value = null

    try {
      const params = { ...filters.value, ...customFilters, period }
      const response = await $api<DashboardApiResponse<Record<string, any>>>('/admin/dashboard/charts', {
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
      console.error('Error fetching admin dashboard charts:', err)
    } finally {
      loading.value.charts = false
    }
  }

  const fetchTableData = async (type: string, customFilters?: Partial<FilterOptions>) => {
    loading.value.tables = true
    error.value = null

    try {
      const params = { ...filters.value, ...customFilters, type }
      const response = await $api<DashboardApiResponse<any[]>>('/admin/dashboard/tables', {
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
      console.error(`Error fetching admin dashboard table data (${type}):`, err)
    } finally {
      loading.value.tables = false
    }
  }

  const updateFilters = (newFilters: Partial<FilterOptions>) => {
    filters.value = { ...filters.value, ...newFilters }
  }

  const refreshAll = async () => {
    await Promise.all([
      fetchStats(),
      fetchChartData(),
      fetchTableData('recent_affiliates'),
      fetchTableData('recent_payouts'),
      fetchTableData('recent_tickets'),
      fetchTableData('recent_activities'),
    ])
  }

  const clearData = () => {
    stats.value = null
    chartData.value = {}
    tableData.value = {}
    error.value = null
    lastUpdated.value = null
  }

  // Specific getters for chart data
  const signupsChartData = computed(() => chartData.value.signups_over_time as TimeSeriesData)
  const revenueChartData = computed(() => chartData.value.revenue_over_time as TimeSeriesData)
  const commissionsChartData = computed(() => chartData.value.commissions_over_time as TimeSeriesData)
  const topAffiliatesChart = computed(() => chartData.value.top_affiliates_commissions)
  const topAffiliatesSignupsChart = computed(() => chartData.value.top_affiliates_signups)
  const ordersByStatusChart = computed(() => chartData.value.orders_by_status)
  const conversionFunnelData = computed(() => chartData.value.conversion_funnel)

  // Specific getters for table data
  const recentAffiliates = computed(() => tableData.value.recent_affiliates as RecentAffiliate[] || [])
  const recentPayouts = computed(() => tableData.value.recent_payouts as RecentPayoutRequest[] || [])
  const recentTickets = computed(() => tableData.value.recent_tickets as RecentTicket[] || [])
  const recentActivities = computed(() => tableData.value.recent_activities as RecentActivity[] || [])

  return {
    // State
    stats,
    chartData,
    tableData,
    loading,
    error,
    lastUpdated,
    filters,

    // Getters
    isLoading,
    hasData,
    totalAffiliates,
    totalRevenue,
    totalCommissions,
    pendingPayouts,
    signupsGrowth,
    revenueGrowth,
    commissionGrowth,

    // Chart data getters
    signupsChartData,
    revenueChartData,
    commissionsChartData,
    topAffiliatesChart,
    topAffiliatesSignupsChart,
    ordersByStatusChart,
    conversionFunnelData,

    // Table data getters
    recentAffiliates,
    recentPayouts,
    recentTickets,
    recentActivities,

    // Actions
    fetchStats,
    fetchChartData,
    fetchTableData,
    updateFilters,
    refreshAll,
    clearData,
  }
})
