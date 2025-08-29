import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type {
  AffiliateDashboardStats,
  FilterOptions,
  DashboardCard,
  DashboardChartData,
  DashboardTableData,
  DashboardApiResponse,
  TimeSeriesData,
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

  const cards = computed(() => stats.value?.cards ?? [])
  const getCardValue = (key: string) => {
    const card = cards.value.find(c => c.key === key)
    return card?.value ?? 0
  }
  const totalOrders = computed(() => getCardValue('total_orders'))
  const totalCommissions = computed(() => getCardValue('total_commissions'))
  const monthlyEarnings = computed(() => getCardValue('monthly_earnings'))
  const paymentsStatus = computed(() => getCardValue('payments_status'))
  const pendingTickets = computed(() => getCardValue('pending_tickets'))

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

  const fetchChartData = async (chartType?: string, customFilters?: Partial<FilterOptions>) => {
    loading.value.charts = true
    error.value = null

    try {
      // If no specific chart type, fetch all charts
      if (!chartType) {
        await fetchSingleChart('top_products_sold')
      } else {
        await fetchSingleChart(chartType, customFilters)
      }
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'An error occurred'
      console.error('Error fetching affiliate dashboard charts:', err)
    } finally {
      loading.value.charts = false
    }
  }

  const fetchSingleChart = async (type: string, customFilters?: Partial<FilterOptions>) => {
    const params = { ...filters.value, ...customFilters, type }
    const response = await $api<DashboardApiResponse<DashboardChartData>>('/affiliate/dashboard/charts', {
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
      fetchTableData('my_recent_orders'),
      fetchTableData('my_recent_payments'),
      fetchTableData('my_active_referrals'),
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
  const topProductsSoldChart = computed(() => {
    const rawData = chartData.value.top_products_sold
    if (!rawData || !rawData.items || rawData.items.length === 0) {
      return null
    }

    // Transform backend format { items: [...] } to Chart.js format { labels: [...], datasets: [...] }
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
  const myRecentOrders = computed(() => {
    const data = tableData.value.my_recent_orders
    return Array.isArray(data) ? data : ((data as any)?.rows || [])
  })
  const myRecentPayments = computed(() => {
    const data = tableData.value.my_recent_payments
    return Array.isArray(data) ? data : ((data as any)?.rows || [])
  })
  const myActiveReferrals = computed(() => {
    const data = tableData.value.my_active_referrals
    return Array.isArray(data) ? data : ((data as any)?.rows || [])
  })

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
    totalOrders,
    totalCommissions,
    monthlyEarnings,
    paymentsStatus,
    pendingTickets,

    // Chart data getters
    topProductsSoldChart,

    // Table data getters
    myRecentOrders,
    myRecentPayments,
    myActiveReferrals,

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
