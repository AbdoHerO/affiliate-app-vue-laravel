console.log('🔄 Starting ultra-safe app initialization...')

import { createApp } from 'vue'

import App from '@/App.vue'
import { registerPlugins } from '@core/utils/plugins'
import { useAuthStore } from '@/stores/auth'
import { setupGlobalErrorHandler } from '@/plugins/globalErrorHandler'
import AppInitService from '@/services/appInitService'
import { useFontManager } from '@/composables/useFontManager'

// Styles
import '@core-scss/template/index.scss'
import '@styles/styles.scss'

// Create vue app with enhanced error handling
const app = createApp(App)

// Global error handler for component errors
app.config.errorHandler = (err, instance, info) => {
  console.error('🚫 [Vue Error Handler] Component error:', err)
  console.error('🚫 [Vue Error Handler] Component instance:', instance)
  console.error('🚫 [Vue Error Handler] Error info:', info)

  // Handle vnode-related errors specifically
  if (err instanceof Error && err.message.includes('vnode')) {
    console.warn('🔧 [Vue Error Handler] VNode error detected - preventing crash')
    return
  }

  // Handle chart-related errors
  if (err instanceof Error && (err.message.includes('toLocaleString') || err.message.includes('startsWith'))) {
    console.warn('🔧 [Vue Error Handler] Chart data format error detected')
    return
  }

  // Handle syntax errors in production
  if (err instanceof SyntaxError) {
    console.error('🚫 [Vue Error Handler] Syntax error detected:', err.message)
    // Don't crash the app, just log it
    return
  }

  // For other errors, log but don't crash the app
  console.error('🚫 [Vue Error Handler] Unhandled error:', err)
}

// Register plugins with error handling
try {
  registerPlugins(app)
  console.log('✅ Plugins registered successfully')
} catch (pluginError) {
  console.error('❌ Plugin registration failed:', pluginError)
}

// Setup global error handler for 401 responses
try {
  setupGlobalErrorHandler(app)
  console.log('✅ Global error handler setup')
} catch (handlerError) {
  console.error('❌ Global error handler setup failed:', handlerError)
}

// Mount app with error handling
try {
  app.mount('#app')
  console.log('✅ App mounted successfully')
} catch (mountError) {
  console.error('❌ App mount failed:', mountError)
}

// Initialize font management safely
try {
  const { initFontManager } = useFontManager()
  initFontManager()
  console.log('✅ Font manager initialized')
} catch (fontError) {
  console.error('❌ Font manager initialization failed:', fontError)
}

// Hide loading screen after app is mounted
try {
  const loadingElement = document.getElementById('loading-bg')
  if (loadingElement) {
    loadingElement.style.display = 'none'
    console.log('✅ Loading screen hidden')
  }
} catch (loadingError) {
  console.error('❌ Loading screen hiding failed:', loadingError)
}

// Initialize app services with enhanced error handling and delays
setTimeout(async () => {
  try {
    console.log('🔄 Starting app initialization...')
    
    // Initialize app settings first
    try {
      await AppInitService.initialize()
      console.log('✅ App settings initialized')
    } catch (appInitError) {
      console.error('❌ App settings initialization failed:', appInitError)
    }

    // Then initialize auth store with additional safety
    try {
      const authStore = useAuthStore()
      authStore.initializeAuth()
      console.log('✅ Auth store initialized')
    } catch (authError) {
      console.error('❌ Auth store initialization failed:', authError)
    }
    
    console.log('✅ App initialization completed (safe mode)')
  } catch (error) {
    console.error('❌ Critical initialization failed:', error)
    // Don't block the app if initialization fails
  }
}, 200)