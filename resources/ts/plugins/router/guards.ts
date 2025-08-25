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
      // Enhanced safety checks for route object
      if (!to || typeof to !== 'object') {
        console.error('🚫 [Route Guard] Invalid route object:', to)
        return next('/')
      }

      // Ensure path exists and is a string
      const routePath = to.path?.toString() || ''
      const routeName = to.name?.toString() || ''
      const fromPath = from?.path?.toString() || ''
      const fromName = from?.name?.toString() || ''

      // Extra validation for route path
      if (!routePath || typeof routePath !== 'string') {
        console.error('🚫 [Route Guard] Invalid route path:', routePath)
        return next('/')
      }

      console.log('🛡️ [Route Guard] Navigating:', fromName, fromPath, '→', routeName, routePath)

      const authStore = useAuthStore()

      // Initialize auth if not already done
      if (!authStore.isInitialized) {
        console.log('🔄 [Route Guard] Initializing auth store...')
        try {
          authStore.initializeAuth()
        } catch (authError) {
          console.error('🚫 [Route Guard] Auth initialization failed:', authError)
          return next('/login')
        }
      }

      // Check if route requires authentication
      const requiresAuth = to.meta?.requiresAuth === true
      const requiresRole = to.meta?.requiresRole
      const requiresPermission = to.meta?.requiresPermission
      const isPublic = to.meta?.public === true

      // Default to requiring authentication unless explicitly marked as public
      const needsAuth = requiresAuth || requiresRole || requiresPermission || (!isPublic && !routePath.includes('/login') && !routePath.includes('/affiliate-signup') && !routePath.includes('/affiliate-verified'))

      console.log('🛡️ [Route Guard] Route meta:', {
        requiresAuth,
        requiresRole,
        requiresPermission,
        isPublic,
        needsAuth,
        isAuthenticated: authStore.isAuthenticated
      })

      // Public routes - allow access without authentication
      if (isPublic) {
        console.log('✅ [Route Guard] Public route - allowing access')
        return next()
      }

      // If route needs authentication (either explicitly or by default)
      if (needsAuth) {
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

      // Enhanced error handling for specific route patterns
      const targetPath = to?.path?.toString() || ''
      
      // Handle tickets routes specifically
      if (targetPath.includes('/admin/support/tickets')) {
        console.log('🔄 [Route Guard] Handling tickets route error')
        return next('/admin/support/tickets')
      } else if (targetPath.includes('/admin')) {
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

  // Enhanced global error handler
  router.onError((error, to, from) => {
    console.error('🚫 [Route Guard] Navigation error:', error)
    console.error('🚫 [Route Guard] Target route:', to)
    console.error('🚫 [Route Guard] Source route:', from)

    const errorMessage = error?.message || ''
    const targetPath = to?.path?.toString() || ''

    // Handle specific navigation errors
    if (errorMessage.includes('startsWith') || errorMessage.includes('Cannot read properties of undefined')) {
      console.error('🚫 [Route Guard] Path/property access error detected')

      // Determine safe fallback route
      let fallbackRoute = '/admin/dashboard'
      
      if (targetPath.includes('/admin/support/tickets')) {
        fallbackRoute = '/admin/support/tickets'
      } else if (targetPath.includes('/admin/withdrawals')) {
        fallbackRoute = '/admin/withdrawals'
      } else if (targetPath.includes('/admin')) {
        fallbackRoute = '/admin/dashboard'
      } else {
        fallbackRoute = '/'
      }

      // Navigate with delay to prevent immediate re-error
      setTimeout(() => {
        console.log('🔄 [Route Guard] Attempting fallback navigation to:', fallbackRoute)
        router.push(fallbackRoute).catch((retryError) => {
          console.error('🚫 [Route Guard] Fallback navigation failed:', retryError)
          // Ultimate fallback
          window.location.href = '/admin/dashboard'
        })
      }, 100)
      
    } else if (errorMessage.includes('emitsOptions')) {
      console.warn('🔧 [Route Guard] Component lifecycle error detected - emitsOptions is null')
      // Don't interfere with Vue component lifecycle errors
      // These usually resolve themselves during the normal Vue cleanup process
      return
      
    } else if (errorMessage.includes('Cannot read properties of null')) {
      console.warn('🔧 [Route Guard] Null property access error')
      // Let Vue handle null property errors naturally  
      return
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
    layout?: 'default' | 'blank'
  }
}
