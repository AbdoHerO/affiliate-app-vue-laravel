import { createApp } from 'vue'

import App from '@/App.vue'
import { registerPlugins } from '@core/utils/plugins'
import { useAuthStore } from '@/stores/auth'

// Styles
import '@core-scss/template/index.scss'
import '@styles/styles.scss'

// Create vue app
const app = createApp(App)

// Register plugins (including navigation safety)
registerPlugins(app)

// Mount app
app.mount('#app')

// Initialize auth store after app is mounted
const authStore = useAuthStore()
authStore.initializeAuth()