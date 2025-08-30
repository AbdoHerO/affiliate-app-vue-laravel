import { ref, computed } from 'vue'
import { useSettingsStore } from '@/stores/settings'

// Default logo fallback
import defaultLogo from '@images/logo.png'

// Reactive logo state
const currentLogo = ref<string>(defaultLogo)
const appTitle = ref<string>('Arif Style')

export const useAppLogo = () => {
  const settingsStore = useSettingsStore()

  // Computed logo URL that prioritizes settings logo over default
  const logoUrl = computed(() => {
    // Check if we have a custom logo from settings
    const settingsLogo = settingsStore.getSetting('general.company_logo')
    if (settingsLogo) {
      return `/storage/settings/${settingsLogo}`
    }
    
    // Fallback to current logo or default
    return currentLogo.value || defaultLogo
  })

  // Computed app title from settings
  const logoTitle = computed(() => {
    const settingsTitle = settingsStore.getSetting('general.app_name')
    return settingsTitle || appTitle.value
  })

  // Update logo programmatically
  const updateLogo = (newLogoUrl: string) => {
    currentLogo.value = newLogoUrl
  }

  // Update app title programmatically
  const updateTitle = (newTitle: string) => {
    appTitle.value = newTitle
  }

  // Get logo as Vue h() function for themeConfig
  const getLogoComponent = () => {
    return h('img', {
      src: logoUrl.value,
      alt: logoTitle.value,
      style: 'height: 55px; width: auto; object-fit: contain;',
      onError: (e: Event) => {
        // Fallback to default logo if custom logo fails to load
        const target = e.target as HTMLImageElement
        if (target.src !== defaultLogo) {
          target.src = defaultLogo
        }
      }
    })
  }

  // Initialize from settings
  const initializeFromSettings = async () => {
    try {
      // Load settings if not already loaded
      if (!settingsStore.isLoaded) {
        await settingsStore.loadSettings()
      }

      // Update logo and title from settings
      const settingsLogo = settingsStore.getSetting('general.company_logo')
      const settingsTitle = settingsStore.getSetting('general.app_name')

      if (settingsLogo) {
        updateLogo(`/storage/settings/${settingsLogo}`)
      }
      
      if (settingsTitle) {
        updateTitle(settingsTitle)
      }
    } catch (error) {
      console.warn('Failed to initialize logo from settings:', error)
    }
  }

  return {
    logoUrl,
    logoTitle,
    currentLogo,
    appTitle,
    updateLogo,
    updateTitle,
    getLogoComponent,
    initializeFromSettings
  }
}
