import { createApp } from 'vue'

import App from '@/App.vue'
import { registerPlugins } from '@core/utils/plugins'
import { useAuthStore } from '@/stores/auth'

// Styles
import '@core-scss/template/index.scss'
import '@styles/styles.scss'
console.log('=== MAIN.TS DEBUG START ===')

// Create vue app
const app = createApp(App)
console.log('=== ABOUT TO CREATE APP ===')

// Register plugins
registerPlugins(app)
console.log('=== ABOUT TO MOUNT APP ===')

// Initialize authentication after plugins are registered
app.mount('#app')

// Initialize auth store after app is mounted
const authStore = useAuthStore()
authStore.initializeAuth()
console.log('Main.ts is loading...')