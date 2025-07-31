import type { Router } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

/**
 * Router Guards Plugin
 * Handles authentication and authorization for routes
 */
export function setupRouterGuards(router: Router) {
  // Global before guard - runs before every route navigation
  router.beforeEach(async (to, from, next) => {
    console.log('🛡️ [Route Guard] Navigating to:', to.name, to.path)
    
    const authStore = useAuthStore()
    
    // Initialize auth if not already done
    if (!authStore.isInitialized) {
      console.log('🔄 [Route Guard] Initializing auth store...')
      authStore.initializeAuth()
    }

    // Check if route requires authentication
    const requiresAuth = to.meta.requiresAuth === true
    const requiresRole = to.meta.requiresRole
    const requiresPermission = to.meta.requiresPermission
    const isPublic = to.meta.public === true

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
  })

  // Global after guard - runs after every successful navigation
  router.afterEach((to, from) => {
    console.log('✅ [Route Guard] Navigation completed:', from.name, '→', to.name)
  })

  // Global error handler
  router.onError((error) => {
    console.error('🚫 [Route Guard] Navigation error:', error)
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
