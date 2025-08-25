/**
 * Global authentication handler for 401 responses
 * Provides a centralized way to handle authentication failures
 */

let isHandling401 = false // Prevent multiple simultaneous redirects

export const handle401Unauthorized = async (): Promise<void> => {
  // Prevent multiple simultaneous 401 handling
  if (isHandling401) {
    console.log('🔄 [Auth Handler] 401 already being handled, skipping...')
    return
  }

  isHandling401 = true
  console.log('🚫 [Auth Handler] Handling 401 Unauthorized')

  try {
    // Clear all auth data from localStorage
    localStorage.removeItem('auth_token')
    localStorage.removeItem('auth_user')
    
    // Clear auth store if available
    try {
      const { useAuthStore } = await import('@/stores/auth')
      const authStore = useAuthStore()
      authStore.clearAuth()
      console.log('✅ [Auth Handler] Auth store cleared')
    } catch (e) {
      console.warn('⚠️ [Auth Handler] Could not access auth store:', e)
    }
    
    // Redirect to login using router if available
    try {
      const { useRouter } = await import('vue-router')
      const router = useRouter()
      
      // Only redirect if not already on login page
      if (router.currentRoute.value.name !== 'login') {
        console.log('🔄 [Auth Handler] Redirecting to login via router')
        await router.push({ name: 'login' })
      } else {
        console.log('ℹ️ [Auth Handler] Already on login page')
      }
    } catch (e) {
      // Fallback to window.location if router is not available
      console.warn('⚠️ [Auth Handler] Router not available, using window.location')
      if (!window.location.pathname.includes('/login')) {
        window.location.href = '/login'
      }
    }
  } catch (error) {
    console.error('❌ [Auth Handler] Error handling 401:', error)
    // Ultimate fallback
    window.location.href = '/login'
  } finally {
    // Reset the flag after a delay to allow for navigation
    setTimeout(() => {
      isHandling401 = false
    }, 1000)
  }
}

/**
 * Check if a response is a 401 Unauthorized error
 */
export const is401Error = (error: any): boolean => {
  return error?.response?.status === 401 || error?.status === 401
}

/**
 * Enhanced error handler that checks for 401 and handles it appropriately
 */
export const handleApiError = async (error: any): Promise<void> => {
  if (is401Error(error)) {
    await handle401Unauthorized()
  }
  
  // Re-throw the error for component-level handling
  throw error
}
