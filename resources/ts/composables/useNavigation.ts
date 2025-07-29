import { computed } from 'vue'
import { useAuth } from '@/composables/useAuth'
import adminNavigation, { affiliateNavigation } from '@/navigation/vertical'
import type { VerticalNavItems } from '@layouts/types'

export function useNavigation() {
  const { hasRole, isAuthenticated, user, isLoading } = useAuth()

  const navItems = computed<VerticalNavItems>(() => {
    // Return empty array if not ready to prevent errors
    if (!isAuthenticated.value || !user.value || isLoading.value) {
      return []
    }

    // Additional safety check - ensure user has roles
    if (!user.value.roles || !Array.isArray(user.value.roles) || user.value.roles.length === 0) {
      return []
    }

    try {
      // Validate navigation items recursively
      const validateNavItem = (item: any): boolean => {
        if (!item || typeof item !== 'object') return false
        if (!item.title || typeof item.title !== 'string') return false

        // If it has children, validate them
        if (item.children) {
          return Array.isArray(item.children) && item.children.every(validateNavItem)
        }

        // If it has a route, validate it
        if (item.to) {
          return item.to.name && typeof item.to.name === 'string'
        }

        return true
      }

      // Return appropriate navigation based on user role
      if (hasRole('admin')) {
        return adminNavigation.filter(validateNavItem)
      } else if (hasRole('affiliate')) {
        return affiliateNavigation.filter(validateNavItem)
      }
    } catch (error) {
      console.error('Navigation error:', error)
      return []
    }

    // Default fallback
    return []
  })

  return {
    navItems
  }
}
