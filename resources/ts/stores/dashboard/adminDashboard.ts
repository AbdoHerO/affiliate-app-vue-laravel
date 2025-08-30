import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type {
  AdminDashboardStats,
  FilterOptions,
  DashboardCard,
  DashboardChartData,
  DashboardTableData,
  DashboardApiResponse,
} from '@/types/dashboard'
import { $api } from '@/utils/api'

export const useAdminDashboardStore = defineStore('adminDashboard', () => {
  // State
  const stats = ref<AdminDashboardStats | null>(null)
  const chartData = ref<Record<string, DashboardChartData>>({})
  const tableData = ref<Record<string, DashboardTableData>>({})
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
    period: 'month', // default period
    page: 1,
    perPage: 15,
  })

  // Getters
  const isLoading = computed(() => 
    loading.value.stats || loading.value.charts || loading.value.tables
  )

  const hasData = computed(() => stats.value !== null)

  const cards = computed(() => stats.value?.cards ?? [])
  const getCardValue = (key: string) => {
    const card = cards.value.find(c => c.key === key)
    return card?.value ?? 0
  }
  const activeAffiliates = computed(() => getCardValue('active_affiliates'))
  const totalOrders = computed(() => getCardValue('total_orders'))
  const totalRevenue = computed(() => getCardValue('total_revenue'))
  const totalCommissions = computed(() => getCardValue('total_commissions'))
  const pendingPayments = computed(() => getCardValue('pending_payments'))
  const pendingTickets = computed(() => getCardValue('pending_tickets'))

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

  const fetchChartData = async (chartType?: string, customFilters?: Partial<FilterOptions>) => {
    loading.value.charts = true
    error.value = null

    try {
      // If no specific chart type, fetch all charts
      if (!chartType) {
        await Promise.all([
          fetchSingleChart('orders_by_period'),
          fetchSingleChart('monthly_revenue'),
          fetchSingleChart('top_affiliates'),
          fetchSingleChart('top_products'),
        ])
      } else {
        await fetchSingleChart(chartType, customFilters)
      }
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'An error occurred'
      console.error('Error fetching admin dashboard charts:', err)
    } finally {
      loading.value.charts = false
    }
  }

  const fetchSingleChart = async (type: string, customFilters?: Partial<FilterOptions>) => {
    const params = { ...filters.value, ...customFilters, type }
    const response = await $api<DashboardApiResponse<DashboardChartData>>('/admin/dashboard/charts', {
      method: 'GET',
      params,
    })

    if (response.success) {
      chartData.value[type] = response.data
    } else {
      throw new Error(response.message || `Failed to fetch ${type} chart data`)
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
        tableData.value[type] = response.data as any
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
      fetchTableData('recent_payments'),
      fetchTableData('monthly_paid_commissions'),
    ])
  }

  const clearData = () => {
    stats.value = null
    chartData.value = {}
    tableData.value = {}
    error.value = null
    lastUpdated.value = null
  }

  // Specific getters for chart data with format transformation
  const ordersByPeriodChart = computed(() => {
    const rawData = chartData.value.orders_by_period
    if (!rawData || !rawData.series || rawData.series.length === 0) {
      return null
    }

    // Transform series format to Chart.js format
    const series = rawData.series[0]
    return {
      labels: series.categories,
      datasets: [{
        label: 'Orders',
        data: series.data,
        backgroundColor: '#7367F0',
        borderColor: '#7367F0',
        borderWidth: 2,
      }]
    }
  })

  const monthlyRevenueChart = computed(() => {
    const rawData = chartData.value.monthly_revenue
    if (!rawData || !rawData.series || rawData.series.length === 0) {
      return null
    }

    // Transform series format to Chart.js format
    const series = rawData.series[0]
    return {
      labels: series.categories,
      datasets: [{
        label: 'Revenue',
        data: series.data,
        backgroundColor: 'rgba(40, 199, 111, 0.1)',
        borderColor: '#28C76F',
        borderWidth: 2,
        fill: true,
      }]
    }
  })

  const topAffiliatesChart = computed(() => {
    const rawData = chartData.value.top_affiliates
    if (!rawData || !rawData.items || rawData.items.length === 0) {
      return null
    }

    // Transform items format to Chart.js format
    const labels = rawData.items.map((item: any) => item.label)
    const data = rawData.items.map((item: any) => item.value)
    
    return {
      labels,
      datasets: [{
        label: 'Top Affiliates',
        data,
        backgroundColor: '#FF9F43',
        borderColor: '#FF9F43',
        borderWidth: 2,
      }]
    }
  })

  const topProductsChart = computed(() => {
    const rawData = chartData.value.top_products
    if (!rawData || !rawData.items || rawData.items.length === 0) {
      return null
    }

    // Transform items format to Chart.js format
    const labels = rawData.items.map((item: any) => item.label)
    const data = rawData.items.map((item: any) => item.value)
    
    return {
      labels,
      datasets: [{
        label: 'Top Products',
        data,
        backgroundColor: [
          '#7367F0',
          '#FF9F43', 
          '#28C76F',
          '#EA5455',
          '#00CFE8'
        ],
        borderWidth: 2,
      }]
    }
  })

  // Specific getters for table data
  const recentPayments = computed(() => {
    const data = tableData.value.recent_payments
    return Array.isArray(data) ? data : ((data as any)?.rows || [])
  })
  const monthlyPaidCommissions = computed(() => {
    const data = tableData.value.monthly_paid_commissions
    return Array.isArray(data) ? data : ((data as any)?.rows || [])
  })

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
    activeAffiliates,
    totalOrders,
    totalRevenue,
    totalCommissions,
    pendingPayments,
    pendingTickets,

    // Chart data getters
    ordersByPeriodChart,
    monthlyRevenueChart,
    topAffiliatesChart,
    topProductsChart,

    // Table data getters
    recentPayments,
    monthlyPaidCommissions,

    // Actions
    fetchStats,
    fetchChartData,
    fetchTableData,
    updateFilters,
    refreshAll,
    clearData,
  }
})
