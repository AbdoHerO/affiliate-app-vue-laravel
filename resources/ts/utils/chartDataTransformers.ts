// Chart Data Transformation Utilities
// Transform existing API responses to match new chart component formats

import type { TimeSeriesData } from '@/types/dashboard'
import type {
  AnalyticsCarouselData,
  TotalEarningData,
  RevenueGrowthData,
  SessionAnalyticsData,
  ExpensesRadialData,
  SalesOverviewData,
  EarningReportsData,
  SalesAreaData,
  ProfitLineData,
  AdvancedStatsData,
  MixedChartData,
} from '@/components/charts/advanced'

// Safe helpers -------------------------------------------------------------
export function safeNumber(value: any, defaultValue: number = 0): number {
  const num = Number(value)
  return isNaN(num) || !isFinite(num) ? defaultValue : num
}

export function safeArray<T = any>(value: any, defaultValue: T[] = []): T[] {
  if (Array.isArray(value)) return value.filter(v => v !== null && v !== undefined) as T[]
  return defaultValue
}

/**
 * Transform signups chart data for Analytics Carousel
 */
export function transformSignupsToCarousel(
  chartData: TimeSeriesData | null | undefined,
  stats: {
    totalSignups: number
    verifiedSignups: number
    signupsGrowth: number
  }
): AnalyticsCarouselData {
  return {
    totalSignups: safeNumber(stats.totalSignups),
    verifiedSignups: safeNumber(stats.verifiedSignups),
    growthRate: safeNumber(stats.signupsGrowth),
    chartData: chartData || { labels: [], datasets: [] },
  }
}

/**
 * Transform revenue chart data for Total Earning component
 */
export function transformRevenueToEarning(
  chartData: TimeSeriesData | null | undefined,
  stats: {
    totalRevenue: number
    totalCommissions: number
    revenueGrowth: number
  }
): TotalEarningData {
  // Provide safe defaults if chartData is null/undefined
  const safeChartData = chartData || { labels: [], datasets: [] }
  const datasets = safeChartData.datasets || []

  const revenueDataset = datasets.find(d => d.label?.toLowerCase().includes('revenue'))
  const commissionsDataset = datasets.find(d => d.label?.toLowerCase().includes('commission'))

  return {
    revenue: safeArray(revenueDataset?.data).map(v => safeNumber(v)),
    commissions: safeArray(commissionsDataset?.data).map(v => safeNumber(v)),
    labels: safeArray(safeChartData.labels),
    totalRevenue: safeNumber(stats.totalRevenue),
    totalCommissions: safeNumber(stats.totalCommissions),
    growth: safeNumber(stats.revenueGrowth),
  }
}

/**
 * Transform commission data for Revenue Growth chart
 */
export function transformCommissionsToGrowth(
  chartData: TimeSeriesData | null | undefined,
  stats: {
    totalCommissions: number
    commissionsGrowth: number
  },
  period: string = 'Weekly'
): RevenueGrowthData {
  const safeChartData = chartData || { labels: [], datasets: [] }
  const datasets = safeChartData.datasets || []
  const commissionsDataset = datasets.find(d => d.label?.toLowerCase().includes('commission'))

  return {
    values: safeArray(commissionsDataset?.data, []).map(v => safeNumber(v)),
  labels: safeArray<any>(safeChartData.labels, []).map((label: any) => {
      // Convert full date labels to short format (e.g., "2024-08-01" -> "Aug")
      if (typeof label === 'string' && label.includes('-')) {
        const date = new Date(label)
        return date.toLocaleDateString('en', { month: 'short' })
      }
      return String(label)
    }),
    total: safeNumber(stats.totalCommissions),
    growth: safeNumber(stats.commissionsGrowth),
    period,
  }
}

/**
 * Transform affiliate signup data for Session Analytics Donut
 */
export function transformSignupsToSession(
  stats: {
    totalReferrals: number
    verifiedSignups: number
    conversionRate: number
  }
): SessionAnalyticsData {
  const totalReferrals = safeNumber(stats.totalReferrals)
  const verifiedSignups = safeNumber(stats.verifiedSignups)
  const pending = totalReferrals - verifiedSignups

  return {
    verified: verifiedSignups,
    pending: pending > 0 ? pending : 0,
    centerMetric: Math.round(stats.conversionRate || 0),
    centerLabel: 'Conversion Rate',
  }
}

/**
 * Transform product performance data for Expenses Radial chart
 */
export function transformProductsToRadial(
  data: {
    totalProducts: number
    activeProducts: number
    label?: string
  }
): ExpensesRadialData {
  const totalProducts = safeNumber(data.totalProducts)
  const activeProducts = safeNumber(data.activeProducts)
  const percentage = totalProducts > 0 
    ? Math.round((activeProducts / totalProducts) * 100)
    : 0

  return {
    percentage,
    value: data.activeProducts,
    label: data.label || 'Active Products',
    subtitle: `${percentage}% of total products are active`,
  }
}

/**
 * Transform top affiliates data for Revenue Growth chart
 */
export function transformTopAffiliatesToGrowth(
  chartData: any,
  period: string = 'Monthly'
): RevenueGrowthData {
  // Extract data from top affiliates chart with safe defaults
  const values = Array.isArray(chartData?.data) ? chartData.data :
                 Array.isArray(chartData?.datasets?.[0]?.data) ? chartData.datasets[0].data :
                 [10, 20, 15, 25, 30, 18, 22]

  const labels = Array.isArray(chartData?.labels) ? chartData.labels :
                 ['Affiliate A', 'Affiliate B', 'Affiliate C', 'Affiliate D', 'Affiliate E', 'Affiliate F', 'Affiliate G']

  return {
    values: safeArray(values).map(v => safeNumber(v)),
    labels: labels.map((label: any) => {
      const labelStr = String(label || 'Unknown')
      // Truncate long affiliate names
      return labelStr.length > 8 ? `${labelStr.substring(0, 8)}...` : labelStr
    }),
    total: values.reduce((sum: number, val: number) => sum + (val || 0), 0),
    growth: 15.2, // Default growth rate
    period,
  }
}

/**
 * Transform orders by status for Session Analytics Donut
 */
export function transformOrdersToSession(
  chartData: any
): SessionAnalyticsData {
  const datasets = safeArray(chartData?.datasets?.[0]?.data)
  const labels = safeArray(chartData?.labels)

  // Find completed and pending orders with safe string operations
  const completedIndex = labels.findIndex((label: any) => {
    const labelStr = String(label || '').toLowerCase()
    return labelStr.includes('completed') || labelStr.includes('delivered')
  })
  const pendingIndex = labels.findIndex((label: any) => {
    const labelStr = String(label || '').toLowerCase()
    return labelStr.includes('pending') || labelStr.includes('processing')
  })

  const completed = safeNumber(completedIndex >= 0 ? (datasets[completedIndex] || 0) : 45)
  const pending = safeNumber(pendingIndex >= 0 ? (datasets[pendingIndex] || 0) : 23)
  const total = completed + pending

  return {
    verified: completed,
    pending,
    centerMetric: total > 0 ? Math.round((completed / total) * 100) : 66,
    centerLabel: 'Completion Rate',
  }
}

/**
 * Calculate growth rate from time series data
 */
export function calculateGrowthRate(chartData: TimeSeriesData | null | undefined): number {
  if (!chartData?.datasets?.[0]?.data) return 0

  const dataset = chartData.datasets[0]
  if (!dataset?.data || dataset.data.length < 2) return 0

  const current = safeNumber(dataset.data[dataset.data.length - 1])
  const previous = safeNumber(dataset.data[dataset.data.length - 2])

  if (previous === 0) return current > 0 ? 100 : 0
  return Math.round(((current - previous) / previous) * 100)
}

/**
 * Format currency values for display
 */
export function formatCurrency(value: number): string {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(value)
}

/**
 * Format percentage values for display
 */
export function formatPercentage(value: number): string {
  return `${value >= 0 ? '+' : ''}${value}%`
}

/**
 * Transform data for Sales Overview Card
 */
export function transformToSalesOverview(
  stats: {
    totalRevenue: number
    revenueGrowth: number
    totalOrders: number
    totalVisits: number
    conversionRate: number
  }
): SalesOverviewData {
  const totalOrders = safeNumber(stats.totalOrders)
  const totalVisits = safeNumber(stats.totalVisits)
  const orderPercentage = (totalOrders + totalVisits) > 0 ? Math.round((totalOrders / (totalOrders + totalVisits)) * 100) : 0
  const visitPercentage = 100 - orderPercentage

  return {
    title: 'Sales Overview',
  amount: formatCurrency(safeNumber(stats.totalRevenue)),
  growth: formatPercentage(safeNumber(stats.revenueGrowth)),
    orderPercentage,
  orderCount: totalOrders,
    visitPercentage,
  visitCount: totalVisits,
  progressValue: Math.round(safeNumber(stats.conversionRate)),
  }
}

/**
 * Transform data for Earning Reports Weekly
 */
export function transformToEarningReports(
  stats: {
    weeklyTotal: number
    weeklyGrowth: number
    weeklyData: number[]
    earnings: number
    profit: number
    expenses: number
  }
): EarningReportsData {
  return {
  totalAmount: formatCurrency(safeNumber(stats.weeklyTotal)),
  growth: formatPercentage(safeNumber(stats.weeklyGrowth)),
  weeklyData: safeArray(stats.weeklyData).map(v => safeNumber(v)),
    reports: [
      {
        color: 'primary',
        icon: 'tabler-currency-dollar',
    title: 'Earnings',
    amount: formatCurrency(safeNumber(stats.earnings)),
        progress: 55,
      },
      {
        color: 'info',
        icon: 'tabler-chart-pie-2',
    title: 'Profit',
    amount: formatCurrency(safeNumber(stats.profit)),
        progress: 25,
      },
      {
        color: 'error',
        icon: 'tabler-brand-paypal',
    title: 'Expenses',
    amount: formatCurrency(safeNumber(stats.expenses)),
        progress: 65,
      },
    ],
  }
}

/**
 * Transform data for Sales Area Chart
 */
export function transformToSalesArea(
  chartData: TimeSeriesData | null | undefined,
  stats: {
    totalSales: number
    salesGrowth: number
    title?: string
    subtitle?: string
    color?: string
  }
): SalesAreaData {
  const safeChartData = chartData || { labels: [], datasets: [] }
  const salesDataset = safeChartData.datasets?.[0]

  return {
    title: stats.title || 'Sales',
    subtitle: stats.subtitle || 'Last Year',
  value: `${Math.round(safeNumber(stats.totalSales) / 1000)}k`,
  growth: formatPercentage(safeNumber(stats.salesGrowth)),
  chartData: safeArray(salesDataset?.data, [200, 55, 400, 250]).map(v => safeNumber(v)),
    color: stats.color || 'success',
  }
}

/**
 * Transform data for Profit Line Chart
 */
export function transformToProfitLine(
  chartData: TimeSeriesData | null | undefined,
  stats: {
    totalProfit: number
    profitGrowth: number
    title?: string
    subtitle?: string
    color?: string
  }
): ProfitLineData {
  const safeChartData = chartData || { labels: [], datasets: [] }
  const profitDataset = safeChartData.datasets?.[0]

  return {
    title: stats.title || 'Profit',
    subtitle: stats.subtitle || 'Last Month',
  value: `${Math.round(safeNumber(stats.totalProfit) / 1000)}k`,
  growth: formatPercentage(safeNumber(stats.profitGrowth)),
  chartData: safeArray(profitDataset?.data, [0, 25, 10, 40, 25, 55]).map(v => safeNumber(v)),
    color: stats.color || 'info',
  }
}

/**
 * Transform data for Advanced Stats Card
 */
export function transformToAdvancedStats(
  stats: {
    title: string
    value: number
    subtitle?: string
    icon: string
    color: string
    previousValue?: number
    period?: string
    progressValue?: number
    progressLabel?: string
    comparisonCurrent?: string
    comparisonPrevious?: string
    comparisonLabel?: string
  }
): AdvancedStatsData {
  const valueNum = safeNumber(stats.value)
  const previousNum = stats.previousValue !== undefined ? safeNumber(stats.previousValue) : undefined
  const growth = (previousNum !== undefined && previousNum !== 0)
    ? Math.round(((valueNum - previousNum) / previousNum) * 100)
    : 0

  return {
    title: stats.title,
  value: valueNum.toLocaleString(),
    subtitle: stats.subtitle,
    icon: stats.icon,
    color: stats.color,
    trend: previousNum !== undefined ? {
      value: formatPercentage(Math.abs(growth)),
      direction: growth >= 0 ? 'up' : 'down',
      period: stats.period || 'vs last month',
    } : undefined,
    progress: stats.progressValue ? {
      value: stats.progressValue,
      label: stats.progressLabel || 'Progress',
    } : undefined,
  comparison: stats.comparisonCurrent ? {
      current: stats.comparisonCurrent,
      previous: stats.comparisonPrevious || '0',
      label: stats.comparisonLabel || 'This period',
    } : undefined,
  }
}

/**
 * Transform data for Mixed Chart
 */
export function transformToMixedChart(
  revenueData: TimeSeriesData | null | undefined,
  growthData: TimeSeriesData | null | undefined,
  options: {
    title?: string
    subtitle?: string
    colors?: { line: string; bar: string }
  } = {}
): MixedChartData {
  const safeRevenueData = revenueData || { labels: [], datasets: [] }
  const safeGrowthData = growthData || { labels: [], datasets: [] }

  return {
    title: options.title || 'Revenue & Growth Analysis',
    subtitle: options.subtitle || 'Monthly Performance',
  barData: safeArray(safeRevenueData.datasets?.[0]?.data, [44, 55, 57, 56, 61, 58, 63]).map(v => safeNumber(v)),
  lineData: safeArray(safeGrowthData.datasets?.[0]?.data, [76, 85, 101, 98, 87, 105, 91]).map(v => safeNumber(v)),
  labels: safeArray(safeRevenueData.labels, ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul']).map(l => String(l)),
    colors: options.colors,
  }
}
