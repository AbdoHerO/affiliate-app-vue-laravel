import { useAuthStore } from '@/stores/auth'
import type { Router } from 'vue-router'

// Flag to prevent infinite loops
let isNavigating = false
let guardInitialized = false

/**
 * Production-Safe Router Guards Plugin
 * Handles authentication and authorization for routes with enhanced error handling
 */
export function setupRouterGuards(router: Router) {
  if (guardInitialized) {
    console.warn('⚠️ [Route Guard] Guards already initialized, skipping...')
    return
  }

  guardInitialized = true
  console.log('🛡️ [Route Guard] Setting up route guards...')

  // Global before guard - runs before every route navigation
  router.beforeEach(async (to, from, next) => {
    // Prevent multiple simultaneous navigations
    if (isNavigating) {
      console.log('🔄 [Route Guard] Navigation in progress, waiting...')
      return next(false)
    }

    isNavigating = true

    try {
      // Safe route name extraction with fallbacks
      const routeName = (to?.name?.toString?.() || to?.name || 'unknown').toString()
      const routePath = (to?.path?.toString?.() || to?.path || '/').toString()
      const fromName = (from?.name?.toString?.() || from?.name || 'root').toString()
      const fromPath = (from?.path?.toString?.() || from?.path || '/').toString()

      console.log('🛡️ [Route Guard] Navigating:', fromName, fromPath, '→', routeName, routePath)

      // Get auth store with error handling
      let authStore
      try {
        authStore = useAuthStore()
      } catch (error) {
        console.error('🚫 [Route Guard] Failed to access auth store:', error)
        return next('/login')
      }

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
      const isPublic = to.meta?.public === true

      // Default to requiring authentication unless explicitly marked as public
      const needsAuth = !isPublic && (requiresAuth !== false)

      console.log('🛡️ [Route Guard] Route meta:', {
        requiresAuth,
        isPublic,
        needsAuth,
        isAuthenticated: authStore.isAuthenticated
      })

      if (needsAuth && !authStore.isAuthenticated) {
        console.log('🚫 [Route Guard] Authentication required - redirecting to login')
        return next('/login')
      }

      // If user is authenticated and trying to access login page, redirect to dashboard
      if (authStore.isAuthenticated && routeName === 'login') {
        console.log('🔄 [Route Guard] Already authenticated - redirecting to dashboard')
        const redirectTo = authStore.hasRole('admin') ? '/admin/dashboard' : '/affiliate/dashboard'
        return next(redirectTo)
      }

      console.log('✅ [Route Guard] Access granted')
      next()
    } catch (error) {
      console.error('🚫 [Route Guard] Navigation error:', error)
      
      // Safe fallback navigation
      const targetPath = (to?.path?.toString?.() || '/').toString()
      let fallbackRoute = '/'
      
      if (targetPath.includes('/admin')) {
        fallbackRoute = '/admin/dashboard'
      } else if (targetPath.includes('/affiliate')) {
        fallbackRoute = '/affiliate/dashboard'
      }

      // Navigate with delay to prevent immediate re-error
      setTimeout(() => {
        console.log('🔄 [Route Guard] Attempting fallback navigation to:', fallbackRoute)
        router.push(fallbackRoute).catch((retryError) => {
          console.error('🚫 [Route Guard] Fallback navigation failed:', retryError)
          // Ultimate fallback
          window.location.href = fallbackRoute
        })
      }, 100)
    } finally {
      // Reset navigation flag after a delay
      setTimeout(() => {
        isNavigating = false
      }, 100)
    }
  })

  // Global after guard - runs after every successful navigation
  router.afterEach((to, from) => {
    try {
      const toName = (to?.name?.toString?.() || 'unknown').toString()
      const fromName = (from?.name?.toString?.() || 'unknown').toString()
      console.log('✅ [Route Guard] Navigation completed:', fromName, '→', toName)
    } catch (error) {
      console.error('🚫 [Route Guard] After navigation error:', error)
    }

    // Ensure navigation flag is reset
    isNavigating = false
  })

  // Global error handler for navigation errors
  router.onError((error) => {
    console.error('🚫 [Route Guard] Router error:', error)
    isNavigating = false

    // Handle router errors gracefully
    if (error.message?.includes('NavigationDuplicated')) {
      console.log('ℹ️ [Route Guard] Duplicate navigation prevented')
      return
    }

    // For other errors, redirect to safe page
    setTimeout(() => {
      console.log('🔄 [Route Guard] Redirecting to safe page due to router error')
      window.location.href = '/'
    }, 100)
  })

  console.log('✅ [Route Guard] Route guards initialized successfully')
}
