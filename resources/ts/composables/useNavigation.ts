import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuth } from '@/composables/useAuth'
import adminNavigation, { affiliateNavigation } from '@/navigation/vertical'
import type { VerticalNavItems } from '@layouts/types'

export function useNavigation() {
  const { hasRole, isAuthenticated, user, isLoading } = useAuth()
  const { t } = useI18n()

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
            title: t('nav_dashboard'),
            to: 'admin-dashboard',
            icon: { icon: 'tabler-dashboard' },
          },
          {
            title: t('nav_user_management'),
            icon: { icon: 'tabler-users' },
            children: [
              {
                title: t('nav_all_users'),
                to: 'admin-users',
              },
              {
                title: t('nav_roles_permissions'),
                to: 'admin-roles',
              },
              {
                title: t('nav_kyc_documents'),
                to: 'admin-kyc-documents',
              },
            ],
          },
          {
            title: t('nav_affiliate_management'),
            icon: { icon: 'tabler-user-star' },
            children: [
              {
                title: 'File d\'Attente d\'Approbation',
                to: 'admin-affiliates',
                icon: { icon: 'tabler-user-check' },
              },
              {
                title: t('nav_affiliate_tiers'),
                to: 'admin-affiliate-tiers',
              },
            ],
          },
          {
            title: t('nav_order_management'),
            icon: { icon: 'tabler-shopping-cart' },
            children: [
              {
                title: 'Pré-commandes',
                to: 'admin-orders-pre',
                icon: { icon: 'tabler-package' },
              },
              {
                title: 'Expéditions',
                to: 'admin-orders-shipping',
                icon: { icon: 'tabler-truck' },
              },
              {
                title: t('nav_order_conflicts'),
                to: 'admin-order-conflicts',
              },
            ],
          },
          {
            title: t('nav_product_management'),
            icon: { icon: 'tabler-package' },
            children: [
              {
                title: t('nav_products'),
                to: 'admin-products',
              },
              {
                title: t('nav_categories'),
                to: 'admin-categories',
              },
              {
                title: t('nav_boutiques'),
                to: 'admin-boutiques',
              },
              {
                title: t('nav_variant_catalog'),
                to: 'admin-variants-attributs',
                icon: { icon: 'tabler-palette' },
              },
            ],
          },
          {
            title: t('nav_financial_management'),
            icon: { icon: 'tabler-currency-dollar' },
            children: [
              {
                title: t('nav_commissions'),
                to: 'admin-commissions',
              },
              {
                title: t('nav_payments'),
                to: 'admin-payments',
              },
            ],
          },
          {
            title: t('nav_reports_analytics'),
            icon: { icon: 'tabler-chart-bar' },
            children: [
              {
                title: t('nav_sales_reports'),
                to: 'admin-reports-sales',
              },
              {
                title: t('nav_affiliate_performance'),
                to: 'admin-reports-affiliates',
              },
            ],
          },
          {
            title: t('profile'),
            to: 'profile',
            icon: { icon: 'tabler-user' },
          },
        ]
      } else if (hasRole('affiliate')) {
        const affiliateNav = [
          {
            title: t('nav_dashboard'),
            to: 'affiliate-dashboard',
            icon: { icon: 'tabler-dashboard' },
          },
          {
            title: t('nav_my_orders'),
            to: 'affiliate-orders',
            icon: { icon: 'tabler-shopping-cart' },
          },
          {
            title: t('nav_my_commissions'),
            to: 'affiliate-commissions',
            icon: { icon: 'tabler-currency-dollar' },
          },
          {
            title: t('nav_marketing_materials'),
            to: 'affiliate-marketing',
            icon: { icon: 'tabler-photo' },
          },
          {
            title: t('profile'),
            to: 'profile',
            icon: { icon: 'tabler-user' },
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
