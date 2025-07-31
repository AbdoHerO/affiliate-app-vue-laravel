import { computed } from 'vue'
import { useAuth } from '@/composables/useAuth'
import adminNavigation, { affiliateNavigation } from '@/navigation/vertical'
import type { VerticalNavItems } from '@layouts/types'

export function useNavigation() {
  const { hasRole, isAuthenticated, user, isLoading } = useAuth()

  const navItems = computed<VerticalNavItems>(() => {
    // Return empty array if not ready to prevent errors
    if (!isAuthenticated || !user || isLoading) {
      return []
    }

    // Additional safety check - ensure user has roles
    if (!user.roles || !Array.isArray(user.roles) || user.roles.length === 0) {
      return []
    }

    try {

      // Return appropriate navigation based on user role
      if (hasRole('admin')) {
        return [
          {
            title: 'Dashboard',
            to: 'admin-dashboard',
            icon: { icon: 'tabler-dashboard' },
          },
          {
            title: 'User Management',
            icon: { icon: 'tabler-users' },
            children: [
              {
                title: 'All Users',
                to: 'admin-users',
              },
              {
                title: 'Roles & Permissions',
                to: 'admin-roles',
              },
              {
                title: 'KYC Documents',
                to: 'admin-kyc-documents',
              },
            ],
          },
          {
            title: 'Affiliate Management',
            icon: { icon: 'tabler-user-star' },
            children: [
              {
                title: 'All Affiliates',
                to: 'admin-affiliates',
              },
              {
                title: 'Affiliate Tiers',
                to: 'admin-affiliate-tiers',
              },
            ],
          },
          {
            title: 'Order Management',
            icon: { icon: 'tabler-shopping-cart' },
            children: [
              {
                title: 'All Orders',
                to: 'admin-orders',
              },
              {
                title: 'Order Conflicts',
                to: 'admin-order-conflicts',
              },
            ],
          },
          {
            title: 'Product Management',
            icon: { icon: 'tabler-package' },
            children: [
              {
                title: 'Products',
                to: 'admin-products',
              },
              {
                title: 'Categories',
                to: 'admin-categories',
              },
              {
                title: 'Boutiques',
                to: 'admin-boutiques',
              },
            ],
          },
          {
            title: 'Financial Management',
            icon: { icon: 'tabler-currency-dollar' },
            children: [
              {
                title: 'Commissions',
                to: 'admin-commissions',
              },
              {
                title: 'Payments',
                to: 'admin-payments',
              },
            ],
          },
          {
            title: 'Reports & Analytics',
            icon: { icon: 'tabler-chart-bar' },
            children: [
              {
                title: 'Sales Reports',
                to: 'admin-reports-sales',
              },
              {
                title: 'Affiliate Performance',
                to: 'admin-reports-affiliates',
              },
            ],
          },
        ]
      } else if (hasRole('affiliate')) {
        const affiliateNav = [
          {
            title: 'Dashboard',
            to: 'affiliate-dashboard',
            icon: { icon: 'tabler-dashboard' },
          },
          {
            title: 'My Orders',
            to: 'affiliate-orders',
            icon: { icon: 'tabler-shopping-cart' },
          },
          {
            title: 'My Commissions',
            to: 'affiliate-commissions',
            icon: { icon: 'tabler-currency-dollar' },
          },
          {
            title: 'Marketing Materials',
            to: 'affiliate-marketing',
            icon: { icon: 'tabler-photo' },
          },
        ]
        return affiliateNav
      }
    } catch (error) {
      console.error('Navigation error:', error)
      return []
    }

    // Default fallback
    console.log('No role matched, returning empty navigation')
    return []
  })

  return {
    navItems
  }
}
