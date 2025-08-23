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
        title: 'File d\'Attente d\'Approbation',
        to: '/admin/affiliates',
        icon: { icon: 'tabler-user-check' },
      },
      {
        title: 'nav_affiliate_tiers',
        to: '/admin/affiliate-tiers',
        icon: { icon: 'tabler-medal' },
      },
    ],
  },
  {
    title: 'nav_referral_management',
    icon: { icon: 'tabler-share' },
    children: [
      {
        title: 'nav_referral_dashboard',
        to: '/admin/referrals/dashboard',
        icon: { icon: 'tabler-dashboard' },
      },
      {
        title: 'nav_referred_users',
        to: '/admin/referrals/referred-users',
        icon: { icon: 'tabler-users' },
      },
      {
        title: 'nav_dispensations',
        to: '/admin/referrals/dispensations',
        icon: { icon: 'tabler-gift' },
      },
    ],
  },
  {
    title: 'nav_gestion_stock',
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
      {
        title: 'nav_stock_management',
        to: '/admin/stock',
        icon: { icon: 'tabler-package-import' },
      },
    ],
  },
  {
    title: 'nav_order_management',
    icon: { icon: 'tabler-shopping-cart' },
    children: [
      {
        title: 'Pré-commandes',
        to: '/admin/orders/pre',
        icon: { icon: 'tabler-package' },
      },
      {
        title: 'Expéditions',
        to: '/admin/orders/shipping',
        icon: { icon: 'tabler-truck' },
      },
      // {
      //   title: 'nav_order_conflicts',
      //   to: '/admin/order-conflicts',
      //   icon: { icon: 'tabler-alert-triangle' },
      // },
      {
        title: 'Debug OzonExpress',
        to: '/admin/debug/ozonexpress',
        icon: { icon: 'tabler-bug' },
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
        title: 'nav_withdrawals',
        to: '/admin/withdrawals',
        icon: { icon: 'tabler-wallet' },
      },
      // {
      //   title: 'nav_payments',
      //   to: '/admin/payments',
      //   icon: { icon: 'tabler-credit-card' },
      // },
    ],
  },
  {
    title: 'nav_parametres',
    icon: { icon: 'tabler-settings' },
    children: [
      {
        title: 'nav_variant_catalog',
        to: '/admin/variants/attributs',
        icon: { icon: 'tabler-palette' },
      },
      {
        title: 'nav_ozonexpress_credentials',
        to: '/admin/integrations/ozon/credentials',
        icon: { icon: 'tabler-key' },
      },
      {
        title: 'nav_ozonexpress_cities',
        to: '/admin/integrations/ozon/cities',
        icon: { icon: 'tabler-map-pin' },
      },
    ],
  },
  {
    title: 'nav_support',
    icon: { icon: 'tabler-headset' },
    children: [
      {
        title: 'nav_support_tickets',
        to: '/admin/support/tickets',
        icon: { icon: 'tabler-ticket' },
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
    title: 'nav_catalogue',
    to: '/affiliate/catalogue',
    icon: { icon: 'tabler-package' },
  },
  {
    title: 'nav_my_orders',
    to: '/affiliate/orders',
    icon: { icon: 'tabler-shopping-cart' },
  },
  {
    title: 'nav_my_payments',
    to: '/affiliate/payments',
    icon: { icon: 'tabler-currency-dollar' },
  },
  {
    title: 'nav_my_referrals',
    to: '/affiliate/referrals',
    icon: { icon: 'tabler-share' },
  },
  {
    title: 'nav_support',
    to: '/affiliate/tickets',
    icon: { icon: 'tabler-headset' },
  },
  {
    title: 'nav_marketing_materials',
    to: '/affiliate/marketing',
    icon: { icon: 'tabler-photo' },
  },
] satisfies VerticalNavItems

export default adminNavigation
export { affiliateNavigation }
