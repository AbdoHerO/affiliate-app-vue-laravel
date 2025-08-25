import { createApp } from 'vue'

import App from '@/App.vue'
import { registerPlugins } from '@core/utils/plugins'
import { useAuthStore } from '@/stores/auth'
import { setupGlobalErrorHandler } from '@/plugins/globalErrorHandler'

// Styles
import '@core-scss/template/index.scss'
import '@styles/styles.scss'

// Create vue app
const app = createApp(App)

// Global error handler for component errors
app.config.errorHandler = (err, instance, info) => {
  console.error('🚫 [Vue Error Handler] Component error:', err)
  console.error('🚫 [Vue Error Handler] Component instance:', instance)
  console.error('🚫 [Vue Error Handler] Error info:', info)

  // Handle vnode-related errors specifically
  if (err instanceof Error && err.message.includes('vnode')) {
    console.warn('🔧 [Vue Error Handler] VNode error detected - preventing crash')
    // Don't throw the error, just log it
    return
  }

  // Handle chart-related errors
  if (err instanceof Error && (err.message.includes('toLocaleString') || err.message.includes('startsWith'))) {
    console.warn('🔧 [Vue Error Handler] Chart data format error detected')
    // Don't throw the error, just log it
    return
  }

  // For other errors, log but don't crash the app
  console.error('🚫 [Vue Error Handler] Unhandled error:', err)
}

// Register plugins (including navigation safety)
registerPlugins(app)

// Setup global error handler for 401 responses
setupGlobalErrorHandler(app)

// Mount app
app.mount('#app')

// Hide loading screen after app is mounted
const loadingElement = document.getElementById('loading-bg')
if (loadingElement) {
  loadingElement.style.display = 'none'
  console.log('✅ Loading screen hidden')
}

// Initialize auth store after app is mounted (non-blocking)
setTimeout(async () => {
  try {
    const authStore = useAuthStore()
    await authStore.initializeAuth()
    console.log('✅ Auth store initialized')
  } catch (error) {
    console.error('❌ Auth store initialization failed:', error)
    // Don't block the app if auth fails
  }
}, 100)