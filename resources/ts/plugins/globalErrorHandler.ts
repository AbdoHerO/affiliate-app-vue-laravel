/**
 * Global error handler plugin for Vue app
 * Handles unhandled errors and 401 responses
 */

import type { App } from 'vue'
import { handle401Unauthorized, is401Error } from '@/utils/authHandler'

export function setupGlobalErrorHandler(app: App) {
  // Handle Vue errors
  app.config.errorHandler = async (error: any, instance, info) => {
    console.error('🚫 [Global Error Handler] Vue error:', error, info)
    
    // Check if it's a 401 error
    if (is401Error(error)) {
      await handle401Unauthorized()
      return
    }
    
    // Log other errors for debugging
    console.error('Vue error details:', {
      error: error?.message || error,
      component: instance?.$options.name || 'Unknown',
      info
    })
  }

  // Handle unhandled promise rejections
  window.addEventListener('unhandledrejection', async (event) => {
    const error = event.reason
    console.error('🚫 [Global Error Handler] Unhandled promise rejection:', error)
    
    // Check if it's a 401 error
    if (is401Error(error)) {
      event.preventDefault() // Prevent default browser handling
      await handle401Unauthorized()
      return
    }
    
    // Log other promise rejections
    console.error('Unhandled promise rejection details:', {
      error: error?.message || error,
      stack: error?.stack
    })
  })

  // Intercept fetch requests globally (as a fallback)
  const originalFetch = window.fetch
  window.fetch = async (...args) => {
    try {
      const response = await originalFetch(...args)
      
      // Check for 401 responses
      if (response.status === 401) {
        console.log('🚫 [Global Error Handler] 401 detected in fetch response')
        await handle401Unauthorized()
      }
      
      return response
    } catch (error) {
      // Check if it's a 401 error
      if (is401Error(error)) {
        await handle401Unauthorized()
      }
      throw error
    }
  }

  console.log('✅ [Global Error Handler] Initialized')
}
