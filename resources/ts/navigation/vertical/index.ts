import type { VerticalNavItems } from '@layouts/types'

export default [
  {
    title: 'Home',
    to: { name: 'root' },
    icon: { icon: 'tabler-smart-home' },
  },
  {
    title: 'Login',
    to: { name: 'login' },
    icon: { icon: 'tabler-login' },
  },

  // Admin Section
  {
    title: 'Admin',
    icon: { icon: 'tabler-shield' },
    children: [
      {
        title: 'Dashboard',
        to: { name: 'admin-dashboard' },
        icon: { icon: 'tabler-dashboard' },
      },
    ],
  },

  // Affiliate Section
  {
    title: 'Affiliate',
    icon: { icon: 'tabler-user-star' },
    children: [
      {
        title: 'Dashboard',
        to: { name: 'affiliate-dashboard' },
        icon: { icon: 'tabler-dashboard' },
      },
    ],
  },

  {
    title: 'Second page',
    to: { name: 'second-page' },
    icon: { icon: 'tabler-file' },
  },
] satisfies VerticalNavItems
