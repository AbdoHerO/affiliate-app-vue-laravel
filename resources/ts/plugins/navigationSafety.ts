import type { App } from 'vue'
import { createNavigationSafety } from '@/utils/navigationSafety'

export default function (app: App) {
  // Add navigation safety to app after router is available
  app.mixin({
    mounted() {
      // Only initialize once from the root component
      if (this.$el?.id === 'app' && this.$router) {
        try {
          createNavigationSafety(this.$router, {
            enableLogging: process.env.NODE_ENV === 'development',
            maxRetries: 3,
            retryDelay: 200,
            fallbackRoutes: {
              '/admin/stock': '/admin/dashboard',
              '/admin/support/tickets': '/admin/dashboard', 
              '/admin/withdrawals': '/admin/dashboard',
              '/admin/orders': '/admin/dashboard',
              '/admin/commissions': '/admin/dashboard',
              '/admin/affiliates': '/admin/dashboard',
              '/admin/boutiques': '/admin/dashboard',
              '/admin/categories': '/admin/dashboard',
              '/admin/produits': '/admin/dashboard',
              '/admin': '/admin/dashboard',
              '/affiliate': '/affiliate/dashboard',
              default: '/'
            }
          })
          console.log('✅ [Plugin] Navigation safety system initialized')
        } catch (error) {
          console.warn('⚠️ [Plugin] Failed to initialize navigation safety:', error)
        }
      }
    }
  })
}
