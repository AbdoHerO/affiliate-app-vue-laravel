import { useRouter } from 'vue-router'
import type { RouteLocationRaw } from 'vue-router'

/**
 * Safe navigation composable that validates routes before navigation
 */
export function useSafeNavigation() {
  const router = useRouter()

  /**
   * Safely navigate to a route with validation
   */
  const safePush = (to: RouteLocationRaw) => {
    try {
      // Validate the route object
      if (!to) {
        console.warn('⚠️ [Navigation] Invalid route:', to)
        return Promise.reject(new Error('Invalid route'))
      }

      // If it's a route object with params, validate params
      if (typeof to === 'object' && 'params' in to && to.params) {
        for (const [key, value] of Object.entries(to.params)) {
          if (value === undefined || value === null || value === '') {
            console.warn(`⚠️ [Navigation] Invalid param "${key}":`, value, 'for route:', to)
            return Promise.reject(new Error(`Invalid route parameter: ${key}`))
          }
        }
      }

      // Try to resolve the route first to catch issues early
      const resolved = router.resolve(to)
      if (!resolved || !resolved.name) {
        console.warn('⚠️ [Navigation] Could not resolve route:', to)
        return Promise.reject(new Error('Route not found'))
      }

      // Navigate safely
      return router.push(to).catch((error) => {
        // Handle specific navigation errors
        if (error.message?.includes('startsWith')) {
          console.error('🚫 [Navigation] startsWith error detected:', error)
          // Try to navigate to a safe fallback
          if (typeof to === 'string' && to.includes('/admin/withdrawals')) {
            return router.push('/admin/withdrawals')
          } else if (typeof to === 'string' && to.includes('/admin')) {
            return router.push('/admin/dashboard')
          } else {
            return router.push('/')
          }
        }
        throw error
      })
    } catch (error) {
      console.error('🚫 [Navigation] Error during navigation:', error, 'to:', to)
      return Promise.reject(error)
    }
  }

  /**
   * Safely replace current route
   */
  const safeReplace = (to: RouteLocationRaw) => {
    try {
      if (!to) {
        console.warn('⚠️ [Navigation] Invalid route for replace:', to)
        return Promise.reject(new Error('Invalid route'))
      }

      return router.replace(to)
    } catch (error) {
      console.error('🚫 [Navigation] Error during replace:', error, 'to:', to)
      return Promise.reject(error)
    }
  }

  /**
   * Go back with fallback
   */
  const safeBack = (fallback?: RouteLocationRaw) => {
    try {
      if (window.history.length > 1) {
        router.back()
      } else if (fallback) {
        return safePush(fallback)
      } else {
        return safePush({ name: 'root' })
      }
    } catch (error) {
      console.error('🚫 [Navigation] Error going back:', error)
      if (fallback) {
        return safePush(fallback)
      }
    }
  }

  return {
    safePush,
    safeReplace,
    safeBack,
    router
  }
}
