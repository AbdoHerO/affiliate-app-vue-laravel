import type { VerticalNavItems } from '@layouts/types'

// Admin Navigation (all `to` are string paths now)
const adminNavigation = [
  {
    title: 'nav_dashboard',
    to: '/admin/dashboard',
    icon: { icon: 'tabler-dashboard' },
  },
  {
    title: 'nav_user_management',
    icon: { icon: 'tabler-users' },
    children: [
      {
        title: 'nav_all_users',
        to: '/admin/users',
        icon: { icon: 'tabler-user-circle' },
      },
      {
        title: 'nav_roles_permissions',
        to: '/admin/roles',
        icon: { icon: 'tabler-shield-lock' },
      },
      {
        title: 'nav_kyc_documents',
        to: '/admin/kyc-documents',
        icon: { icon: 'tabler-file-certificate' },
      },
    ],
  },
  {
    title: 'nav_affiliate_management',
    icon: { icon: 'tabler-user-star' },
    children: [
      {
        title: 'nav_all_affiliates',
        to: '/admin/affiliates',
        icon: { icon: 'tabler-users-group' },
      },
      {
        title: 'nav_affiliate_tiers',
        to: '/admin/affiliate-tiers',
        icon: { icon: 'tabler-medal' },
      },
    ],
  },
  {
    title: 'nav_order_management',
    icon: { icon: 'tabler-shopping-cart' },
    children: [
      {
        title: 'nav_all_orders',
        to: '/admin/orders',
        icon: { icon: 'tabler-list-details' },
      },
      {
        title: 'nav_order_conflicts',
        to: '/admin/order-conflicts',
        icon: { icon: 'tabler-alert-triangle' },
      },
    ],
  },
  {
    title: 'nav_product_management',
    icon: { icon: 'tabler-package' },
    children: [
      {
        title: 'nav_products',
        to: '/admin/products',
        icon: { icon: 'tabler-box' },
      },
      {
        title: 'nav_categories',
        to: '/admin/categories',
        icon: { icon: 'tabler-category' },
      },
      {
        title: 'nav_boutiques',
        to: '/admin/boutiques',
        icon: { icon: 'tabler-building-store' },
      },
    ],
  },
  {
    title: 'nav_financial_management',
    icon: { icon: 'tabler-currency-dollar' },
    children: [
      {
        title: 'nav_commissions',
        to: '/admin/commissions',
        icon: { icon: 'tabler-percentage' },
      },
      {
        title: 'nav_payments',
        to: '/admin/payments',
        icon: { icon: 'tabler-credit-card' },
      },
    ],
  },
  {
    title: 'nav_reports_analytics',
    icon: { icon: 'tabler-chart-bar' },
    children: [
      {
        title: 'nav_sales_reports',
        to: '/admin/reports/sales',
        icon: { icon: 'tabler-chart-line' },
      },
      {
        title: 'nav_affiliate_performance',
        to: '/admin/reports/affiliates',
        icon: { icon: 'tabler-chart-pie' },
      },
    ],
  },
] satisfies VerticalNavItems

// Affiliate Navigation (string paths)
const affiliateNavigation = [
  {
    title: 'nav_dashboard',
    to: '/affiliate/dashboard',
    icon: { icon: 'tabler-dashboard' },
  },
  {
    title: 'nav_my_orders',
    to: '/affiliate/orders',
    icon: { icon: 'tabler-shopping-cart' },
  },
  {
    title: 'nav_my_commissions',
    to: '/affiliate/commissions',
    icon: { icon: 'tabler-currency-dollar' },
  },
  {
    title: 'nav_marketing_materials',
    to: '/affiliate/marketing',
    icon: { icon: 'tabler-photo' },
  },
] satisfies VerticalNavItems

export default adminNavigation
export { affiliateNavigation }
