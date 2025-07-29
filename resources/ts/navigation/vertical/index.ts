import type { VerticalNavItems } from '@layouts/types'

// Admin Navigation
const adminNavigation = [
  {
    title: 'Dashboard',
    to: { name: 'admin-dashboard' },
    icon: { icon: 'tabler-dashboard' },
  },
  {
    title: 'User Management',
    icon: { icon: 'tabler-users' },
    children: [
      {
        title: 'All Users',
        to: { name: 'admin-users' },
        icon: { icon: 'tabler-user-circle' },
      },
      {
        title: 'Roles & Permissions',
        to: { name: 'admin-roles' },
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
        to: { name: 'admin-affiliates' },
        icon: { icon: 'tabler-users-group' },
      },
      {
        title: 'Affiliate Tiers',
        to: { name: 'admin-affiliate-tiers' },
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
        to: { name: 'admin-orders' },
        icon: { icon: 'tabler-list-details' },
      },
      {
        title: 'Order Conflicts',
        to: { name: 'admin-order-conflicts' },
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
        to: { name: 'admin-products' },
        icon: { icon: 'tabler-box' },
      },
      {
        title: 'Categories',
        to: { name: 'admin-categories' },
        icon: { icon: 'tabler-category' },
      },
      {
        title: 'Boutiques',
        to: { name: 'admin-boutiques' },
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
        to: { name: 'admin-commissions' },
        icon: { icon: 'tabler-percentage' },
      },
      {
        title: 'Payments',
        to: { name: 'admin-payments' },
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
        to: { name: 'admin-reports-sales' },
        icon: { icon: 'tabler-chart-line' },
      },
      {
        title: 'Affiliate Performance',
        to: { name: 'admin-reports-affiliates' },
        icon: { icon: 'tabler-chart-pie' },
      },
    ],
  },
] satisfies VerticalNavItems

// Affiliate Navigation
const affiliateNavigation = [
  {
    title: 'Dashboard',
    to: { name: 'affiliate-dashboard' },
    icon: { icon: 'tabler-dashboard' },
  },
  {
    title: 'My Orders',
    to: { name: 'affiliate-orders' },
    icon: { icon: 'tabler-shopping-cart' },
  },
  {
    title: 'My Commissions',
    to: { name: 'affiliate-commissions' },
    icon: { icon: 'tabler-currency-dollar' },
  },
  {
    title: 'Marketing Materials',
    to: { name: 'affiliate-marketing' },
    icon: { icon: 'tabler-photo' },
  },
] satisfies VerticalNavItems

// Export admin navigation by default (will be dynamically switched based on user role)
export default adminNavigation
export { affiliateNavigation }
