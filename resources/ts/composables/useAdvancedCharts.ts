// Advanced Charts Composable
// Centralized configuration and management for beautiful chart components

import { computed, type ComputedRef } from 'vue'
import {
  transformSignupsToCarousel,
  transformRevenueToEarning,
  transformCommissionsToGrowth,
  transformSignupsToSession,
  transformProductsToRadial,
  transformTopAffiliatesToGrowth,
  transformOrdersToSession,
  transformToSalesOverview,
  transformToEarningReports,
  transformToSalesArea,
  transformToProfitLine,
  transformToAdvancedStats,
  transformToMixedChart,
} from '@/utils/chartDataTransformers'
import { safeNumber } from '@/utils/chartDataTransformers'

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

export interface ChartTheme {
  primary: string
  secondary: string
  success: string
  warning: string
  error: string
  info: string
}

export interface AdvancedChartConfig {
  type: 'carousel' | 'earning' | 'growth' | 'session' | 'radial' | 'sales-overview' | 'earning-reports' | 'sales-area' | 'profit-line' | 'advanced-stats' | 'mixed-chart'
  title: string
  component: string
  data: any
  loading: boolean
  cols: {
    cols: number
    md?: number
    lg?: number
    xl?: number
  }
  size?: 'small' | 'medium' | 'large'
}

/**
 * Advanced Charts Composable for Admin Dashboard
 */
export function useAdminAdvancedCharts(
  dashboardStore: any
): {
  chartConfigs: ComputedRef<AdvancedChartConfig[]>
  isLoading: ComputedRef<boolean>
} {
  const chartConfigs = computed<AdvancedChartConfig[]>(() => {
    // Ensure we have safe access to dashboard store data
    const stats = dashboardStore.stats || {}
    const overview = stats.overview || {}
    // Safe aggregated stats to guard against undefined
    const safeStats = {
      totalRevenue: safeNumber(dashboardStore.totalRevenue),
      totalAffiliates: safeNumber(dashboardStore.totalAffiliates),
      totalOrders: safeNumber(dashboardStore.totalOrders),
      totalCommissions: safeNumber(dashboardStore.totalCommissions),
      revenueGrowth: safeNumber(dashboardStore.revenueGrowth),
      signupsGrowth: safeNumber(dashboardStore.signupsGrowth),
    }

    return [
      // Row 1: Main KPI Cards (4 cards)
      {
        type: 'advanced-stats',
        title: 'Total Revenue',
        component: 'AdvancedStatsCard',
        data: transformToAdvancedStats({
          title: 'Total Revenue',
          value: safeStats.totalRevenue,
          subtitle: 'Platform earnings',
          icon: 'tabler-currency-dollar',
          color: 'primary',
          previousValue: safeStats.totalRevenue * 0.85,
          period: 'vs last month',
          progressValue: 75,
          progressLabel: 'Monthly target',
        }),
        loading: dashboardStore.loading?.charts || false,
        cols: { cols: 12, sm: 6, md: 3 },
        size: 'medium',
      },
      {
        type: 'advanced-stats',
        title: 'Total Affiliates',
        component: 'AdvancedStatsCard',
        data: transformToAdvancedStats({
          title: 'Total Affiliates',
          value: safeStats.totalAffiliates,
          subtitle: 'Active partners',
          icon: 'tabler-users',
          color: 'success',
          previousValue: safeStats.totalAffiliates * 0.92,
          period: 'vs last month',
          progressValue: 85,
          progressLabel: 'Growth target',
        }),
        loading: dashboardStore.loading?.charts || false,
        cols: { cols: 12, sm: 6, md: 3 },
        size: 'medium',
      },
      {
        type: 'advanced-stats',
        title: 'Total Orders',
        component: 'AdvancedStatsCard',
        data: transformToAdvancedStats({
          title: 'Total Orders',
          value: safeStats.totalOrders,
          subtitle: 'Platform orders',
          icon: 'tabler-shopping-cart',
          color: 'info',
          previousValue: safeStats.totalOrders * 0.88,
          period: 'vs last month',
          progressValue: 68,
          progressLabel: 'Monthly goal',
        }),
        loading: dashboardStore.loading?.charts || false,
        cols: { cols: 12, sm: 6, md: 3 },
        size: 'medium',
      },
      {
        type: 'advanced-stats',
        title: 'Conversion Rate',
        component: 'AdvancedStatsCard',
        data: transformToAdvancedStats({
          title: 'Conversion Rate',
          value: Math.round(safeNumber(overview.verifiedSignups) / Math.max(safeStats.totalAffiliates || 1, 1) * 100),
          subtitle: 'Signup conversion',
          icon: 'tabler-trending-up',
          color: 'warning',
          previousValue: 12,
          period: 'vs last month',
          progressValue: 82,
          progressLabel: 'Target rate',
        }),
        loading: dashboardStore.loading?.charts || false,
        cols: { cols: 12, sm: 6, md: 3 },
        size: 'medium',
      },

      // Row 2: Sales Overview & Earning Reports
      {
        type: 'sales-overview',
        title: 'Sales Overview',
        component: 'SalesOverviewCard',
        data: transformToSalesOverview({
          totalRevenue: safeStats.totalRevenue,
          revenueGrowth: safeStats.revenueGrowth,
          totalOrders: safeStats.totalOrders,
          totalVisits: 12749,
          conversionRate: 72,
        }),
        loading: dashboardStore.loading?.charts || false,
        cols: { cols: 12, md: 6 },
      },
      {
        type: 'earning-reports',
        title: 'Earning Reports',
        component: 'EarningReportsWeekly',
        data: transformToEarningReports({
          weeklyTotal: safeStats.totalRevenue / 4,
          weeklyGrowth: 4.2,
          weeklyData: [40, 65, 50, 45, 90, 55, 70],
          earnings: safeStats.totalRevenue * 0.6,
          profit: safeStats.totalRevenue * 0.3,
          expenses: safeStats.totalRevenue * 0.1,
        }),
        loading: dashboardStore.loading?.charts || false,
        cols: { cols: 12, md: 6 },
      },

      // Row 3: Analytics Carousel & Mixed Chart
      {
        type: 'carousel',
        title: 'Affiliate Analytics',
        component: 'WebsiteAnalyticsCarousel',
        data: transformSignupsToCarousel(
          dashboardStore.signupsChartData,
          {
            totalSignups: safeStats.totalAffiliates,
            verifiedSignups: overview.verifiedSignups || 0,
            signupsGrowth: safeStats.signupsGrowth,
          }
        ),
        loading: dashboardStore.loading?.charts || false,
        cols: { cols: 12, md: 6 },
      },
      {
        type: 'mixed-chart',
        title: 'Revenue & Growth Analysis',
        component: 'MixedChart',
        data: transformToMixedChart(
          dashboardStore.revenueChartData,
          dashboardStore.signupsChartData,
          {
            title: 'Revenue & Growth Analysis',
            subtitle: 'Monthly Performance Overview',
            colors: {
              bar: 'rgba(var(--v-theme-primary), 1)',
              line: 'rgba(var(--v-theme-success), 1)',
            },
          }
        ),
        loading: dashboardStore.loading?.charts || false,
        cols: { cols: 12, md: 6 },
      },

      // Row 4: Area Charts & Line Charts
      {
        type: 'sales-area',
        title: 'Sales Performance',
        component: 'SalesAreaChart',
        data: transformToSalesArea(
          dashboardStore.signupsChartData,
          {
            totalSales: 175000,
            salesGrowth: -16.2,
            title: 'Sales',
            subtitle: 'Last Year',
            color: 'success',
          }
        ),
        loading: dashboardStore.loading?.charts || false,
        cols: { cols: 12, sm: 6, md: 3 },
      },
      {
        type: 'profit-line',
        title: 'Profit Analysis',
        component: 'ProfitLineChart',
        data: transformToProfitLine(
          dashboardStore.revenueChartData,
          {
            totalProfit: 624000,
            profitGrowth: 8.24,
            title: 'Profit',
            subtitle: 'Last Month',
            color: 'info',
          }
        ),
        loading: dashboardStore.loading?.charts || false,
        cols: { cols: 12, sm: 6, md: 3 },
      },
      {
        type: 'growth',
        title: 'Top Affiliates',
        component: 'RevenueGrowthChart',
        data: transformTopAffiliatesToGrowth(
          dashboardStore.topAffiliatesChart,
          'Monthly'
        ),
        loading: dashboardStore.loading?.charts || false,
        cols: { cols: 12, sm: 6, md: 3 },
      },
      {
        type: 'session',
        title: 'Order Analytics',
        component: 'SessionAnalyticsDonut',
        data: transformOrdersToSession(dashboardStore.ordersByStatusChart),
        loading: dashboardStore.loading?.charts || false,
        cols: { cols: 12, sm: 6, md: 3 },
      },

      // Row 5: Total Earning & Radial Charts
      {
        type: 'earning',
        title: 'Total Earning',
        component: 'TotalEarningChart',
        data: transformRevenueToEarning(
          dashboardStore.revenueChartData,
          {
            totalRevenue: safeStats.totalRevenue,
            totalCommissions: safeStats.totalCommissions,
            revenueGrowth: safeStats.revenueGrowth,
          }
        ),
        loading: dashboardStore.loading?.charts || false,
        cols: { cols: 12, md: 8 },
      },
      {
        type: 'radial',
        title: 'Performance Metrics',
        component: 'ExpensesRadialChart',
        data: {
          percentage: 78,
          value: 892,
          label: 'Active Rate',
          subtitle: '78% of affiliates are active this month',
        },
        loading: dashboardStore.loading?.charts || false,
        cols: { cols: 12, md: 4 },
      },
    ]
  })

  const isLoading = computed(() => dashboardStore.loading?.charts || false)

  return {
    chartConfigs,
    isLoading,
  }
}

/**
 * Advanced Charts Composable for Affiliate Dashboard
 */
export function useAffiliateAdvancedCharts(
  dashboardStore: any
): {
  chartConfigs: ComputedRef<AdvancedChartConfig[]>
  isLoading: ComputedRef<boolean>
} {
  const chartConfigs = computed<AdvancedChartConfig[]>(() => {
    // Ensure we have safe access to dashboard store data
    const stats = dashboardStore.stats || {}
    const referrals = stats.referrals || {}

    return [
      // Row 1: Personal KPI Cards (4 cards)
      {
        type: 'advanced-stats',
        title: 'My Commissions',
        component: 'AdvancedStatsCard',
        data: transformToAdvancedStats({
          title: 'My Commissions',
          value: dashboardStore.totalCommissions || 0,
          subtitle: 'Total earnings',
          icon: 'tabler-currency-dollar',
          color: 'primary',
          previousValue: (dashboardStore.totalCommissions || 0) * 0.82,
          period: 'vs last month',
          progressValue: 68,
          progressLabel: 'Monthly goal',
        }),
        loading: dashboardStore.loading?.charts || false,
        cols: { cols: 12, sm: 6, md: 3 },
        size: 'medium',
      },
      {
        type: 'advanced-stats',
        title: 'My Referrals',
        component: 'AdvancedStatsCard',
        data: transformToAdvancedStats({
          title: 'My Referrals',
          value: referrals.totalReferrals || 0,
          subtitle: 'Total signups',
          icon: 'tabler-users',
          color: 'success',
          previousValue: (referrals.totalReferrals || 0) * 0.91,
          period: 'vs last month',
          progressValue: 85,
          progressLabel: 'Referral target',
        }),
        loading: dashboardStore.loading?.charts || false,
        cols: { cols: 12, sm: 6, md: 3 },
        size: 'medium',
      },
      {
        type: 'advanced-stats',
        title: 'Verified Signups',
        component: 'AdvancedStatsCard',
        data: transformToAdvancedStats({
          title: 'Verified Signups',
          value: dashboardStore.verifiedSignups || 0,
          subtitle: 'Confirmed referrals',
          icon: 'tabler-check-circle',
          color: 'info',
          previousValue: (dashboardStore.verifiedSignups || 0) * 0.87,
          period: 'vs last month',
          progressValue: Math.round((dashboardStore.verifiedSignups || 0) / Math.max(referrals.totalReferrals || 1, 1) * 100),
          progressLabel: 'Conversion rate',
        }),
        loading: dashboardStore.loading?.charts || false,
        cols: { cols: 12, sm: 6, md: 3 },
        size: 'medium',
      },
      {
        type: 'advanced-stats',
        title: 'Avg Commission',
        component: 'AdvancedStatsCard',
        data: transformToAdvancedStats({
          title: 'Avg Commission',
          value: Math.round(safeNumber(dashboardStore.totalCommissions) / Math.max(safeNumber(dashboardStore.verifiedSignups) || 1, 1)),
          subtitle: 'Per verified signup',
          icon: 'tabler-trending-up',
          color: 'warning',
          previousValue: 45,
          period: 'vs last month',
          progressValue: 72,
          progressLabel: 'Performance',
        }),
        loading: dashboardStore.loading?.charts || false,
        cols: { cols: 12, sm: 6, md: 3 },
        size: 'medium',
      },

      // Row 2: Commission Analytics & Performance Overview
      {
        type: 'earning-reports',
        title: 'Commission Reports',
        component: 'EarningReportsWeekly',
        data: transformToEarningReports({
          weeklyTotal: (dashboardStore.totalCommissions || 0) / 4,
          weeklyGrowth: dashboardStore.commissionsGrowth || 0,
          weeklyData: [25, 45, 32, 38, 55, 42, 48],
          earnings: (dashboardStore.totalCommissions || 0) * 0.7,
          profit: (dashboardStore.totalCommissions || 0) * 0.2,
          expenses: (dashboardStore.totalCommissions || 0) * 0.1,
        }),
        loading: dashboardStore.loading?.charts || false,
        cols: { cols: 12, md: 8 },
      },
      {
        type: 'session',
        title: 'Signup Analytics',
        component: 'SessionAnalyticsDonut',
        data: transformSignupsToSession({
          totalReferrals: referrals.totalReferrals || 0,
          verifiedSignups: dashboardStore.verifiedSignups || 0,
          conversionRate: dashboardStore.conversionRate || 0,
        }),
        loading: dashboardStore.loading?.charts || false,
        cols: { cols: 12, md: 4 },
      },

      // Row 3: Performance Charts
      {
        type: 'mixed-chart',
        title: 'Commission & Referral Trends',
        component: 'MixedChart',
        data: transformToMixedChart(
          dashboardStore.commissionsChartData,
          dashboardStore.signupsChartData,
          {
            title: 'Commission & Referral Trends',
            subtitle: 'Personal Performance Analysis',
            colors: {
              bar: 'rgba(var(--v-theme-primary), 1)',
              line: 'rgba(var(--v-theme-success), 1)',
            },
          }
        ),
        loading: dashboardStore.loading?.charts || false,
        cols: { cols: 12, md: 8 },
      },
      {
        type: 'radial',
        title: 'Goal Progress',
        component: 'ExpensesRadialChart',
        data: {
          percentage: Math.round(dashboardStore.conversionRate || 0),
          value: dashboardStore.verifiedSignups || 0,
          label: 'Monthly Goal',
          subtitle: `${dashboardStore.verifiedSignups || 0} of 100 target signups`,
        },
        loading: dashboardStore.loading?.charts || false,
        cols: { cols: 12, md: 4 },
      },

      // Row 4: Detailed Analytics
      {
        type: 'sales-area',
        title: 'Referral Performance',
        component: 'SalesAreaChart',
        data: transformToSalesArea(
          dashboardStore.signupsChartData,
          {
            totalSales: referrals.totalReferrals || 0,
            salesGrowth: 12.5,
            title: 'Referrals',
            subtitle: 'Last 6 Months',
            color: 'success',
          }
        ),
        loading: dashboardStore.loading?.charts || false,
        cols: { cols: 12, sm: 6, md: 4 },
      },
      {
        type: 'profit-line',
        title: 'Commission Trends',
        component: 'ProfitLineChart',
        data: transformToProfitLine(
          dashboardStore.commissionsChartData,
          {
            totalProfit: dashboardStore.totalCommissions || 0,
            profitGrowth: dashboardStore.commissionsGrowth || 0,
            title: 'Commissions',
            subtitle: 'Last 3 Months',
            color: 'primary',
          }
        ),
        loading: dashboardStore.loading?.charts || false,
        cols: { cols: 12, sm: 6, md: 4 },
      },
      {
        type: 'growth',
        title: 'Top Products',
        component: 'RevenueGrowthChart',
        data: transformTopAffiliatesToGrowth(
          dashboardStore.topProductsChart,
          'Weekly'
        ),
        loading: dashboardStore.loading?.charts || false,
        cols: { cols: 12, sm: 6, md: 4 },
      },
    ]
  })

  const isLoading = computed(() => dashboardStore.loading?.charts || false)

  return {
    chartConfigs,
    isLoading,
  }
}

/**
 * Chart Theme Configuration
 */
export function useChartTheme(): ChartTheme {
  return {
    primary: 'rgba(var(--v-theme-primary), 1)',
    secondary: 'rgba(var(--v-theme-secondary), 1)',
    success: 'rgba(var(--v-theme-success), 1)',
    warning: 'rgba(var(--v-theme-warning), 1)',
    error: 'rgba(var(--v-theme-error), 1)',
    info: 'rgba(var(--v-theme-info), 1)',
  }
}

/**
 * Chart Animation Configuration
 */
export function useChartAnimations() {
  return {
    duration: 800,
    easing: 'easeInOutQuart',
    delay: 100,
    stagger: 200,
  }
}

/**
 * Responsive Chart Configuration
 */
export function useChartResponsive() {
  return {
    breakpoints: {
      mobile: 600,
      tablet: 960,
      desktop: 1280,
      wide: 1920,
    },
    heights: {
      mobile: 200,
      tablet: 250,
      desktop: 300,
    },
  }
}
