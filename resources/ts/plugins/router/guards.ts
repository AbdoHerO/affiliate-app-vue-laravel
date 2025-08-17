import type { Router } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

/**
 * Router Guards Plugin
 * Handles authentication and authorization for routes
 */
export function setupRouterGuards(router: Router) {
  // Global before guard - runs before every route navigation
  router.beforeEach(async (to, from, next) => {
    try {
      // Add safety checks for route object
      if (!to || !to.path || to.path === undefined) {
        console.error('🚫 [Route Guard] Invalid route object:', to)
        return next('/')
      }

      // Safe string operations
      const routePath = to.path || ''
      const routeName = to.name?.toString() || ''

      console.log('🛡️ [Route Guard] Navigating to:', routeName, routePath)

      const authStore = useAuthStore()

      // Initialize auth if not already done
      if (!authStore.isInitialized) {
        console.log('🔄 [Route Guard] Initializing auth store...')
        authStore.initializeAuth()
      }

      // Check if route requires authentication
      const requiresAuth = to.meta?.requiresAuth === true
      const requiresRole = to.meta?.requiresRole
      const requiresPermission = to.meta?.requiresPermission
      const isPublic = to.meta?.public === true

      console.log('🛡️ [Route Guard] Route meta:', {
        requiresAuth,
        requiresRole,
        requiresPermission,
        isPublic,
        isAuthenticated: authStore.isAuthenticated
      })

      // Public routes - allow access without authentication
      if (isPublic) {
        console.log('✅ [Route Guard] Public route - allowing access')
        return next()
      }

      // If route requires authentication
      if (requiresAuth) {
        if (!authStore.isAuthenticated) {
          console.log('🚫 [Route Guard] Authentication required - redirecting to login')
          return next({
            name: 'login',
            query: { redirect: to.fullPath }
          })
        }

        // Check role requirements
        if (requiresRole) {
          const hasRole = Array.isArray(requiresRole)
            ? authStore.hasAnyRole(requiresRole)
            : authStore.hasRole(requiresRole)

          if (!hasRole) {
            console.log('🚫 [Route Guard] Insufficient role - access denied')
            return next({ name: 'unauthorized' })
          }
        }

        // Check permission requirements
        if (requiresPermission) {
          const hasPermission = Array.isArray(requiresPermission)
            ? authStore.hasAnyPermission(requiresPermission)
            : authStore.hasPermission(requiresPermission)

          if (!hasPermission) {
            console.log('🚫 [Route Guard] Insufficient permission - access denied')
            return next({ name: 'unauthorized' })
          }
        }
      }

      // If user is authenticated and trying to access login page, redirect to dashboard
      if (authStore.isAuthenticated && to.name === 'login') {
        console.log('🔄 [Route Guard] Already authenticated - redirecting to dashboard')
        const redirectTo = authStore.hasRole('admin') ? 'admin-dashboard' : 'affiliate-dashboard'
        return next({ name: redirectTo })
      }

      console.log('✅ [Route Guard] Access granted')
      next()
    } catch (error) {
      console.error('🚫 [Route Guard] Error in navigation guard:', error)
      console.error('🚫 [Route Guard] Target route:', to)
      console.error('🚫 [Route Guard] Source route:', from)

      // Fallback navigation
      if (to?.path?.includes('/admin/withdrawals')) {
        return next('/admin/withdrawals')
      } else if (to?.path?.includes('/admin')) {
        return next('/admin/dashboard')
      } else {
        return next('/')
      }
    }
  })

  // Global after guard - runs after every successful navigation
  router.afterEach((to, from) => {
    try {
      const toName = to?.name?.toString() || 'unknown'
      const fromName = from?.name?.toString() || 'unknown'
      console.log('✅ [Route Guard] Navigation completed:', fromName, '→', toName)
    } catch (error) {
      console.error('🚫 [Route Guard] Error in afterEach guard:', error)
    }
  })

  // Global error handler
  router.onError((error, to, from) => {
    console.error('🚫 [Route Guard] Navigation error:', error)
    console.error('🚫 [Route Guard] Target route:', to)
    console.error('🚫 [Route Guard] Source route:', from)

    // Handle specific navigation errors
    if (error.message?.includes('startsWith')) {
      console.error('🚫 [Route Guard] startsWith error detected - route path is undefined')

      // Attempt to recover by redirecting to a safe route
      if (to?.path?.includes('/admin/withdrawals')) {
        router.push('/admin/withdrawals').catch(console.error)
      } else if (to?.path?.includes('/admin')) {
        router.push('/admin/dashboard').catch(console.error)
      } else {
        router.push('/').catch(console.error)
      }
    }
  })
}

/**
 * Route Meta Types
 * Extend the existing route meta interface
 */
declare module 'vue-router' {
  interface RouteMeta {
    requiresAuth?: boolean
    requiresRole?: string | string[]
    requiresPermission?: string | string[]
    public?: boolean
    layout?: string
  }
}
