import type { VerticalNavItems } from '@layouts/types'

// Admin Navigation (all `to` are string paths now)
const adminNavigation = [
  {
    title: 'Dashboard',
    to: '/admin/dashboard',
    icon: { icon: 'tabler-dashboard' },
  },
  {
    title: 'User Management',
    icon: { icon: 'tabler-users' },
    children: [
      {
        title: 'All Users',
        to: '/admin/users',
        icon: { icon: 'tabler-user-circle' },
      },
      {
        title: 'Roles & Permissions',
        to: '/admin/roles',
        icon: { icon: 'tabler-shield-lock' },
      },
    ],
  },
  {
    title: 'Affiliate Management',
    icon: { icon: 'tabler-user-star' },
    children: [
      {
        title: 'All Affiliates',
        to: '/admin/affiliates',
        icon: { icon: 'tabler-users-group' },
      },
      {
        title: 'Affiliate Tiers',
        to: '/admin/affiliate-tiers',
        icon: { icon: 'tabler-medal' },
      },
    ],
  },
  {
    title: 'Order Management',
    icon: { icon: 'tabler-shopping-cart' },
    children: [
      {
        title: 'All Orders',
        to: '/admin/orders',
        icon: { icon: 'tabler-list-details' },
      },
      {
        title: 'Order Conflicts',
        to: '/admin/order-conflicts',
        icon: { icon: 'tabler-alert-triangle' },
      },
    ],
  },
  {
    title: 'Product Management',
    icon: { icon: 'tabler-package' },
    children: [
      {
        title: 'Products',
        to: '/admin/products',
        icon: { icon: 'tabler-box' },
      },
      {
        title: 'Categories',
        to: '/admin/categories',
        icon: { icon: 'tabler-category' },
      },
      {
        title: 'Boutiques',
        to: '/admin/boutiques',
        icon: { icon: 'tabler-building-store' },
      },
    ],
  },
  {
    title: 'Financial Management',
    icon: { icon: 'tabler-currency-dollar' },
    children: [
      {
        title: 'Commissions',
        to: '/admin/commissions',
        icon: { icon: 'tabler-percentage' },
      },
      {
        title: 'Payments',
        to: '/admin/payments',
        icon: { icon: 'tabler-credit-card' },
      },
    ],
  },
  {
    title: 'Reports & Analytics',
    icon: { icon: 'tabler-chart-bar' },
    children: [
      {
        title: 'Sales Reports',
        to: '/admin/reports/sales',
        icon: { icon: 'tabler-chart-line' },
      },
      {
        title: 'Affiliate Performance',
        to: '/admin/reports/affiliates',
        icon: { icon: 'tabler-chart-pie' },
      },
    ],
  },
] satisfies VerticalNavItems

// Affiliate Navigation (string paths)
const affiliateNavigation = [
  {
    title: 'Dashboard',
    to: '/affiliate/dashboard',
    icon: { icon: 'tabler-dashboard' },
  },
  {
    title: 'My Orders',
    to: '/affiliate/orders',
    icon: { icon: 'tabler-shopping-cart' },
  },
  {
    title: 'My Commissions',
    to: '/affiliate/commissions',
    icon: { icon: 'tabler-currency-dollar' },
  },
  {
    title: 'Marketing Materials',
    to: '/affiliate/marketing',
    icon: { icon: 'tabler-photo' },
  },
] satisfies VerticalNavItems

export default adminNavigation
export { affiliateNavigation }
