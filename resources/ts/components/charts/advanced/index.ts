// Advanced Chart Components Library
// Beautiful, diverse chart components extracted from Vuexy full template

// Analytics Charts
export { default as WebsiteAnalyticsCarousel } from '../analytics/WebsiteAnalyticsCarousel.vue'
export { default as TotalEarningChart } from '../analytics/TotalEarningChart.vue'

// CRM Charts
export { default as RevenueGrowthChart } from '../crm/RevenueGrowthChart.vue'
export { default as SessionAnalyticsDonut } from '../crm/SessionAnalyticsDonut.vue'

// E-commerce Charts
export { default as ExpensesRadialChart } from '../ecommerce/ExpensesRadialChart.vue'

// Premium Charts
export { default as SalesOverviewCard } from '../premium/SalesOverviewCard.vue'
export { default as EarningReportsWeekly } from '../premium/EarningReportsWeekly.vue'
export { default as SalesAreaChart } from '../premium/SalesAreaChart.vue'
export { default as ProfitLineChart } from '../premium/ProfitLineChart.vue'
export { default as AdvancedStatsCard } from '../premium/AdvancedStatsCard.vue'
export { default as MixedChart } from '../premium/MixedChart.vue'

// Chart Types for TypeScript
export interface AnalyticsCarouselData {
  totalSignups: number
  verifiedSignups: number
  growthRate: number
  chartData: any
}

export interface TotalEarningData {
  revenue: number[]
  commissions: number[]
  labels: string[]
  totalRevenue: number
  totalCommissions: number
  growth: number
}

export interface RevenueGrowthData {
  values: number[]
  labels: string[]
  total: number
  growth: number
  period: string
}

export interface SessionAnalyticsData {
  verified: number
  pending: number
  centerMetric: number
  centerLabel: string
}

export interface ExpensesRadialData {
  percentage: number
  value: number
  label: string
  subtitle?: string
}

// Premium Chart Types
export interface SalesOverviewData {
  title: string
  amount: string
  growth: string
  orderPercentage: number
  orderCount: number
  visitPercentage: number
  visitCount: number
  progressValue: number
}

export interface EarningReportsData {
  totalAmount: string
  growth: string
  weeklyData: number[]
  reports: Array<{
    color: string
    icon: string
    title: string
    amount: string
    progress: number
  }>
}

export interface SalesAreaData {
  title: string
  subtitle: string
  value: string
  growth: string
  chartData: number[]
  color?: string
}

export interface ProfitLineData {
  title: string
  subtitle: string
  value: string
  growth: string
  chartData: number[]
  color?: string
}

export interface AdvancedStatsData {
  title: string
  value: string
  subtitle?: string
  icon: string
  color: string
  trend?: {
    value: string
    direction: 'up' | 'down'
    period: string
  }
  progress?: {
    value: number
    label: string
  }
  comparison?: {
    current: string
    previous: string
    label: string
  }
}

export interface MixedChartData {
  title: string
  subtitle: string
  lineData: number[]
  barData: number[]
  labels: string[]
  colors?: {
    line: string
    bar: string
  }
}
