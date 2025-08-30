// Dashboard Types and Interfaces
export interface DateRange {
  start: string
  end: string
}

export interface FilterOptions {
  dateRange?: DateRange
  period?: string // 'month' | 'quarter' | 'year'
  affiliate?: string
  status?: string
  country?: string
  city?: string
  page?: number
  perPage?: number
}

// Unified Dashboard Response Types
export interface DashboardCard {
  key: string
  labelKey: string
  value: number | object
}

export interface DashboardChartSeries {
  nameKey: string
  data: number[]
  categories: string[]
  period: string
}

export interface DashboardChartData {
  series?: DashboardChartSeries[]
  items?: DashboardChartItem[]
}

export interface DashboardChartItem {
  label: string
  labelKey: string | null
  value: number
}

export interface DashboardTableData {
  rows: any[]
  pagination: {
    page: number
    perPage: number
    total: number
  }
}

// Admin Dashboard Types
export interface AdminDashboardStats {
  cards: DashboardCard[]
}

export interface OverviewStats {
  totalAffiliates: number
  totalSignups: number
  signupsLast24h: number
  signupsLast7d: number
  signupsMTD: number
  verifiedSignups: number
  verificationRate: number
  totalOrders: number
  totalRevenue: number
  totalCommissions: number
  pendingPayouts: number
}

export interface AffiliateStats {
  total: number
  active: number
  suspended: number
  newThisMonth: number
  topPerformers: TopAffiliate[]
  statusDistribution: Record<string, number>
  conversionRates: {
    clicksToSignups: number
    signupsToVerified: number
    verifiedToOrders: number
  }
}

export interface TopAffiliate {
  id: string
  name: string
  email: string
  totalCommissions: number
  ordersCount: number
  verifiedSignups: number
  conversionRate: number
  joinedAt: string
}

export interface OrderStats {
  total: number
  thisMonth: number
  lastMonth: number
  growth: number
  statusDistribution: Record<string, number>
  averageOrderValue: number
  topProducts: TopProduct[]
}

export interface TopProduct {
  id: string
  title: string
  ordersCount: number
  revenue: number
  commissions: number
}

export interface RevenueStats {
  total: number
  thisMonth: number
  lastMonth: number
  growth: number
  averagePerOrder: number
  averagePerAffiliate: number
}

export interface CommissionStats {
  total: number
  thisMonth: number
  lastMonth: number
  growth: number
  pending: number
  approved: number
  paid: number
  averageRate: number
}

export interface PayoutStats {
  pending: {
    count: number
    amount: number
  }
  approved: {
    count: number
    amount: number
  }
  paid: {
    count: number
    amount: number
  }
  rejected: {
    count: number
    amount: number
  }
  averageProcessingTime: number
}

export interface PointsStats {
  totalEarned: number
  totalDispensed: number
  earnedThisMonth: number
  dispensedThisMonth: number
  balance: number
  topEarners: TopPointsEarner[]
}

export interface TopPointsEarner {
  id: string
  name: string
  pointsEarned: number
  pointsDispensed: number
  balance: number
}

export interface TicketStats {
  total: number
  open: number
  inProgress: number
  resolved: number
  averageResponseTime: number
  averageResolutionTime: number
  priorityDistribution: Record<string, number>
}

// Chart Data Types
export interface ChartDataPoint {
  x: string | number
  y: number
  label?: string
}

export interface TimeSeriesData {
  labels: string[]
  datasets: ChartDataset[]
}

export interface ChartDataset {
  label: string
  data: number[]
  backgroundColor?: string | string[]
  borderColor?: string
  borderWidth?: number
  fill?: boolean
}

export interface SignupsChartData extends TimeSeriesData {
  totalSignups: ChartDataset
  verifiedSignups: ChartDataset
}

export interface RevenueChartData extends TimeSeriesData {
  revenue: ChartDataset
  commissions: ChartDataset
}

export interface ConversionFunnelData {
  clicks: number
  signups: number
  verified: number
  orders: number
  rates: {
    clickToSignup: number
    signupToVerified: number
    verifiedToOrder: number
  }
}

// Table Data Types
export interface RecentAffiliate {
  id: string
  name: string
  email: string
  joinedAt: string
  status: string
  totalCommissions: number
  ordersCount: number
  verifiedSignups: number
  lastActivity: string
}

export interface RecentPayoutRequest {
  id: string
  affiliateId: string
  affiliateName: string
  amount: number
  status: string
  method: string
  requestedAt: string
  processedAt?: string
  notes?: string
}

export interface RecentTicket {
  id: string
  subject: string
  priority: 'low' | 'medium' | 'high' | 'urgent'
  status: 'open' | 'in_progress' | 'resolved' | 'closed'
  requesterName: string
  assigneeName?: string
  createdAt: string
  lastActivity: string
}

// Admin Table Types
export interface RecentPayment {
  id: string
  affiliate: string
  amount: number
  status: string
  date: string
}

export interface MonthlyPaidCommission {
  id: string
  affiliate: string
  amount: number
  date: string
}

// Affiliate Table Types
export interface MyRecentOrder {
  id: string
  product: string
  amount: number
  status: string
  date: string
}

export interface MyRecentPayment {
  id: string
  amount: number
  status: string
  date: string
}

export interface MyActiveReferral {
  id: string
  name: string
  email: string
  signup_date: string
  status: string
}

// Admin KPI Cards
export interface AdminKpiCards {
  active_affiliates: number
  total_orders: number
  total_revenue: number
  total_commissions: number
  pending_payments: number
  pending_tickets: number
}

// Affiliate Dashboard Types
export interface AffiliateDashboardStats {
  cards: DashboardCard[]
}

export interface AffiliateOverviewStats {
  currentPoints: number
  totalCommissions: number
  totalCommissionsMTD: number
  verifiedSignups: number
  totalOrders: number
  conversionRate: number
  clickThroughRate: number
  averageOrderValue: number
  rank: number
  tier: string
}

export interface AffiliatePerformanceStats {
  clicksThisMonth: number
  signupsThisMonth: number
  verifiedSignupsThisMonth: number
  ordersThisMonth: number
  commissionsThisMonth: number
  conversionRates: {
    clickToSignup: number
    signupToVerified: number
    verifiedToOrder: number
  }
  trends: {
    clicks: number
    signups: number
    commissions: number
  }
}

export interface AffiliateReferralStats {
  totalReferrals: number
  activeReferrals: number
  referralCode: string
  referralLink: string
  clicksTotal: number
  clicksThisMonth: number
  topPerformingProducts: TopReferralProduct[]
  recentClicks: ReferralClick[]
}

export interface TopReferralProduct {
  id: string
  title: string
  clicks: number
  conversions: number
  conversionRate: number
  commissions: number
}

export interface ReferralClick {
  id: string
  timestamp: string
  source: string
  converted: boolean
  orderId?: string
  commission?: number
}

export interface AffiliateCommissionStats {
  totalEarned: number
  totalPaid: number
  pending: number
  approved: number
  thisMonth: number
  lastMonth: number
  growth: number
  averagePerOrder: number
  nextPayoutDate?: string
  nextPayoutAmount?: number
}

export interface AffiliateOrderStats {
  total: number
  thisMonth: number
  lastMonth: number
  growth: number
  averageValue: number
  statusDistribution: Record<string, number>
  topProducts: TopOrderProduct[]
}

export interface TopOrderProduct {
  id: string
  title: string
  ordersCount: number
  revenue: number
  commission: number
}

export interface AffiliatePointsStats {
  current: number
  earned: number
  dispensed: number
  earnedThisMonth: number
  dispensedThisMonth: number
  history: PointsTransaction[]
}

export interface PointsTransaction {
  id: string
  type: 'earned' | 'dispensed'
  amount: number
  reason: string
  timestamp: string
  orderId?: string
  referralId?: string
}

// Affiliate Table Data Types
export interface MyLead {
  id: string
  name: string
  email: string
  signupDate: string
  status: 'pending' | 'verified' | 'active'
  source: string
  orders: number
  totalSpent: number
  commissionEarned: number
}

export interface MyOrder {
  id: string
  productTitle: string
  customerName: string
  orderDate: string
  status: string
  amount: number
  commission: number
  commissionStatus: string
}

export interface MyCommission {
  id: string
  orderId: string
  productTitle: string
  amount: number
  rate: number
  status: string
  earnedDate: string
  paidDate?: string
  withdrawalId?: string
}

// API Response Types
export interface DashboardApiResponse<T> {
  success: boolean
  data: T
  message?: string
  errors?: Record<string, string[]>
}

export interface PaginatedResponse<T> {
  data: T[]
  pagination: {
    current_page: number
    last_page: number
    per_page: number
    total: number
    from: number
    to: number
  }
}

// Chart Configuration Types
export interface ChartConfig {
  type: 'line' | 'bar' | 'area' | 'pie' | 'doughnut'
  responsive: boolean
  maintainAspectRatio: boolean
  plugins?: Record<string, any>
  scales?: Record<string, any>
}

export interface DashboardWidget {
  id: string
  title: string
  type: 'kpi' | 'chart' | 'table' | 'list'
  size: 'small' | 'medium' | 'large' | 'full'
  loading: boolean
  error?: string
  data?: any
  config?: Record<string, any>
}
