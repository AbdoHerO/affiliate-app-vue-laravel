import { useRouter } from 'vue-router'
import type { RouteLocationRaw } from 'vue-router'
import { ref, nextTick, readonly } from 'vue'

/**
 * Safe navigation composable that validates routes before navigation
 */
export function useSafeNavigation() {
  const router = useRouter()
  const isNavigating = ref(false)
  const navigationQueue = ref<Array<{ to: RouteLocationRaw; type: 'push' | 'replace' }>>([])
  const errorCount = ref(0)
  const lastErrorTime = ref(0)

  /**
   * Process navigation queue to prevent concurrent navigations
   */
  const processNavigationQueue = async () => {
    if (isNavigating.value || navigationQueue.value.length === 0) {
      return
    }

    isNavigating.value = true
    const { to, type } = navigationQueue.value.shift()!

    try {
      if (type === 'push') {
        await router.push(to)
      } else {
        await router.replace(to)
      }
    } catch (error) {
      console.error('🚫 [Navigation Queue] Navigation failed:', error)
      recordError()
    } finally {
      isNavigating.value = false
      // Process next item in queue
      if (navigationQueue.value.length > 0) {
        setTimeout(processNavigationQueue, 50)
      }
    }
  }

  /**
   * Record navigation error for monitoring
   */
  const recordError = () => {
    errorCount.value++
    lastErrorTime.value = Date.now()
  }

  /**
   * Check if emergency reset is needed
   */
  const checkEmergencyReset = () => {
    const now = Date.now()
    const recentErrors = errorCount.value > 5 && (now - lastErrorTime.value) < 30000 // 5 errors in 30 seconds

    if (recentErrors) {
      console.warn('🚨 [Safe Navigation] Too many errors detected, performing emergency reset')
      emergencyReset()
      return true
    }
    return false
  }

  /**
   * Emergency reset - clear state and navigate to safe route
   */
  const emergencyReset = (safeRoute: string = '/admin/dashboard') => {
    console.warn('🚨 [Safe Navigation] Emergency reset triggered')
    
    // Clear all navigation state
    clearNavigationQueue()
    errorCount.value = 0
    lastErrorTime.value = 0
    
    // Force navigate to safe route after a delay
    setTimeout(() => {
      forceNavigate(safeRoute)
    }, 100)
  }

  /**
   * Add navigation to queue
   */
  const queueNavigation = (to: RouteLocationRaw, type: 'push' | 'replace' = 'push') => {
    navigationQueue.value.push({ to, type })
    processNavigationQueue()
  }

  /**
   * Safely navigate to a route with validation and error handling
   */
  const safePush = async (to: RouteLocationRaw, options: { 
    useQueue?: boolean
    maxRetries?: number
    fallback?: RouteLocationRaw
    skipSafetyCheck?: boolean
  } = {}) => {
    const { useQueue = false, maxRetries = 2, fallback, skipSafetyCheck = false } = options

    try {
      // Validate the route object
      if (!to) {
        console.warn('⚠️ [Navigation] Invalid route:', to)
        recordError()
        return Promise.reject(new Error('Invalid route'))
      }

      // If navigation is in progress and queue is requested, use queue
      if (useQueue && isNavigating.value) {
        console.log('🔄 [Navigation] Queueing navigation:', to)
        queueNavigation(to, 'push')
        return Promise.resolve()
      }

      // Check if we should perform emergency reset
      if (!skipSafetyCheck && checkEmergencyReset()) {
        return Promise.resolve()
      }

      // If it's a route object with params, validate params
      if (typeof to === 'object' && 'params' in to && to.params) {
        for (const [key, value] of Object.entries(to.params)) {
          if (value === undefined || value === null || value === '') {
            console.warn(`⚠️ [Navigation] Invalid param "${key}":`, value, 'for route:', to)
            recordError()
            if (fallback) {
              console.log('🔄 [Navigation] Using fallback route:', fallback)
              return safePush(fallback, { ...options, fallback: undefined })
            }
            return Promise.reject(new Error(`Invalid route parameter: ${key}`))
          }
        }
      }

      // Try to resolve the route first to catch issues early
      const resolved = router.resolve(to)
      if (!resolved || !resolved.name) {
        console.warn('⚠️ [Navigation] Could not resolve route:', to)
        recordError()
        if (fallback) {
          console.log('🔄 [Navigation] Using fallback route:', fallback)
          return safePush(fallback, { ...options, fallback: undefined })
        }
        return Promise.reject(new Error('Route not found'))
      }

      isNavigating.value = true

      // Wait for next tick to ensure component cleanup
      await nextTick()

      // Navigate with retry logic
      return await navigateWithRetry(to, 'push', maxRetries, fallback)

    } catch (error) {
      console.error('🚫 [Navigation] Error during navigation setup:', error, 'to:', to)
      recordError()
      
      if (fallback) {
        console.log('🔄 [Navigation] Attempting fallback navigation:', fallback)
        return safePush(fallback, { ...options, fallback: undefined })
      }
      
      return Promise.reject(error)
    } finally {
      // Reset navigation state after a delay
      setTimeout(() => {
        isNavigating.value = false
      }, 100)
    }
  }

  /**
   * Navigate with retry logic for handling component lifecycle errors
   */
  const navigateWithRetry = async (
    to: RouteLocationRaw, 
    type: 'push' | 'replace', 
    maxRetries: number = 2,
    fallback?: RouteLocationRaw
  ): Promise<any> => {
    let attempts = 0

    while (attempts <= maxRetries) {
      try {
        const result = type === 'push' ? await router.push(to) : await router.replace(to)
        console.log('✅ [Navigation] Navigation successful:', to)
        // Reset error count on successful navigation
        errorCount.value = Math.max(0, errorCount.value - 1)
        return result
      } catch (error: any) {
        attempts++
        recordError()
        const errorMessage = error?.message || ''

        console.error(`🚫 [Navigation] Attempt ${attempts}/${maxRetries + 1} failed:`, error)

        // Handle specific navigation errors
        if (errorMessage.includes('startsWith') || errorMessage.includes('Cannot read properties of undefined')) {
          console.error('🚫 [Navigation] Path/property access error detected')
          
          if (attempts <= maxRetries) {
            console.log(`🔄 [Navigation] Retrying navigation in ${attempts * 100}ms...`)
            await new Promise(resolve => setTimeout(resolve, attempts * 100))
            continue
          }
          
          // Use fallback or safe route
          const safeRoute = determineSafeRoute(to, fallback)
          if (safeRoute && safeRoute !== to) {
            console.log('🔄 [Navigation] Using safe route:', safeRoute)
            return navigateWithRetry(safeRoute, type, 0) // No retries for fallback
          }

        } else if (errorMessage.includes('emitsOptions') || errorMessage.includes('Cannot read properties of null')) {
          console.error('🚫 [Navigation] Component lifecycle error detected')
          
          if (attempts <= maxRetries) {
            // Wait longer for component cleanup
            const delay = attempts * 200
            console.log(`🔄 [Navigation] Waiting ${delay}ms for component cleanup...`)
            await new Promise(resolve => setTimeout(resolve, delay))
            await nextTick() // Ensure Vue has processed any pending updates
            continue
          }

          // Force page reload as last resort for lifecycle errors
          if (attempts > maxRetries) {
            console.warn('🔄 [Navigation] Force reloading page due to persistent lifecycle errors')
            const targetPath = typeof to === 'string' ? to : (to as any).path || '/admin/dashboard'
            window.location.href = targetPath
            return
          }

        } else if (errorMessage.includes('NavigationDuplicated') || errorMessage.includes('redundant navigation')) {
          // Navigation to same route - not really an error
          console.log('ℹ️ [Navigation] Redundant navigation ignored:', to)
          return Promise.resolve()
        }

        // If this is the last attempt, try fallback or throw
        if (attempts > maxRetries) {
          if (fallback && fallback !== to) {
            console.log('🔄 [Navigation] Final fallback attempt:', fallback)
            return navigateWithRetry(fallback, type, 0)
          }
          throw error
        }
      }
    }
  }

  /**
   * Determine a safe route based on the failed route
   */
  const determineSafeRoute = (failedRoute: RouteLocationRaw, fallback?: RouteLocationRaw): RouteLocationRaw | null => {
    if (fallback) return fallback

    const routeString = typeof failedRoute === 'string' ? failedRoute : (failedRoute as any).path || ''

    if (routeString.includes('/admin/stock')) {
      return '/admin/dashboard'
    } else if (routeString.includes('/admin/support/tickets')) {
      return '/admin/dashboard'
    } else if (routeString.includes('/admin/withdrawals')) {
      return '/admin/dashboard'
    } else if (routeString.includes('/admin')) {
      return '/admin/dashboard'
    } else {
      return '/'
    }
  }

  /**
   * Safely replace current route
   */
  const safeReplace = async (to: RouteLocationRaw, options: {
    maxRetries?: number
    fallback?: RouteLocationRaw
    skipSafetyCheck?: boolean
  } = {}) => {
    const { maxRetries = 2, fallback, skipSafetyCheck = false } = options

    try {
      if (!to) {
        console.warn('⚠️ [Navigation] Invalid route for replace:', to)
        recordError()
        return Promise.reject(new Error('Invalid route'))
      }

      if (!skipSafetyCheck && checkEmergencyReset()) {
        return Promise.resolve()
      }

      isNavigating.value = true
      await nextTick()

      return await navigateWithRetry(to, 'replace', maxRetries, fallback)
    } catch (error) {
      console.error('🚫 [Navigation] Error during replace:', error, 'to:', to)
      recordError()
      
      if (fallback) {
        console.log('🔄 [Navigation] Attempting fallback replace:', fallback)
        return safeReplace(fallback, { ...options, fallback: undefined })
      }
      
      return Promise.reject(error)
    } finally {
      setTimeout(() => {
        isNavigating.value = false
      }, 100)
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
        return safePush({ name: 'admin-dashboard' })
      }
    } catch (error) {
      console.error('🚫 [Navigation] Error going back:', error)
      recordError()
      if (fallback) {
        return safePush(fallback)
      }
      return safePush('/admin/dashboard')
    }
  }

  /**
   * Force navigation using window.location (last resort)
   */
  const forceNavigate = (path: string) => {
    console.warn('🔄 [Navigation] Force navigating to:', path)
    try {
      window.location.href = path
    } catch (error) {
      console.error('🚨 [Navigation] Force navigation failed:', error)
      window.location.reload()
    }
  }

  /**
   * Check if navigation is safe (no ongoing navigation)
   */
  const isNavigationSafe = () => !isNavigating.value

  /**
   * Clear navigation queue
   */
  const clearNavigationQueue = () => {
    navigationQueue.value = []
    isNavigating.value = false
  }

  /**
   * Get navigation statistics
   */
  const getNavigationStats = () => ({
    errorCount: errorCount.value,
    lastErrorTime: lastErrorTime.value,
    queueLength: navigationQueue.value.length,
    isNavigating: isNavigating.value
  })

  return {
    safePush,
    safeReplace,
    safeBack,
    forceNavigate,
    isNavigationSafe,
    clearNavigationQueue,
    checkEmergencyReset,
    emergencyReset,
    getNavigationStats,
    isNavigating: readonly(isNavigating),
    router
  }
}
