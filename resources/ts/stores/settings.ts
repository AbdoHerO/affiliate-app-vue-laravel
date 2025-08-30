import { defineStore } from 'pinia'
import { ref, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useTheme } from 'vuetify'
import { useConfigStore } from '@core/stores/config'
import { $api } from '@/utils/api'
import type { AppSettings } from '@/types/settings'

export const useSettingsStore = defineStore('settings', () => {
  // State
  const settings = ref<Partial<AppSettings>>({})
  const loading = ref(false)
  const initialized = ref(false)
  const lastUpdated = ref<Date | null>(null)

  // Composables (moved to functions where needed to avoid setup context issues)
  // const { locale } = useI18n() // Moved to applySettings function
  // const vuetifyTheme = useTheme() // Moved to applySettings function
  // const configStore = useConfigStore() // Moved to applySettings function

  // Computed properties for commonly used settings
  const appName = computed(() => settings.value.app_name || 'Affiliate Platform')
  const appLogo = computed(() => settings.value.app_logo || settings.value.company_logo || '')
  const appFavicon = computed(() => settings.value.app_favicon || settings.value.favicon || '')
  const primaryColor = computed(() => settings.value.primary_color || '#6366F1')
  const secondaryColor = computed(() => settings.value.secondary_color || '#8B5CF6')
  const defaultLanguage = computed(() => settings.value.default_language || 'fr')
  const appTheme = computed(() => settings.value.app_theme || 'light')
  const loginBackground = computed(() => settings.value.login_background_image || '')
  const signupBackground = computed(() => settings.value.signup_background_image || '')
  const facebookPxmApiKey = computed(() => settings.value.facebook_pxm_api_key || '')
  
  // Company information
  const companyInfo = computed(() => ({
    name: settings.value.company_name || '',
    email: settings.value.company_email || '',
    phone: settings.value.company_phone || '',
    address: settings.value.company_address || '',
    website: settings.value.company_website || ''
  }))

  // Social media links
  const socialLinks = computed(() => ({
    facebook: settings.value.company_social_facebook || '',
    instagram: settings.value.company_social_instagram || '',
    twitter: settings.value.company_social_twitter || ''
  }))

  // Branding info
  const brandingInfo = computed(() => ({
    logo: appLogo.value,
    favicon: appFavicon.value,
    primaryColor: primaryColor.value,
    secondaryColor: secondaryColor.value,
    theme: appTheme.value
  }))

  // Localization info
  const localizationInfo = computed(() => ({
    defaultLanguage: defaultLanguage.value,
    timezone: settings.value.timezone || 'Africa/Casablanca',
    currency: settings.value.currency || 'MAD',
    currencySymbol: settings.value.currency_symbol || 'MAD',
    dateFormat: settings.value.date_format || 'DD/MM/YYYY',
    timeFormat: settings.value.time_format || '24'
  }))

  /**
   * Initialize settings store
   */
  const initialize = async (): Promise<void> => {
    if (initialized.value) return

    loading.value = true
    try {
      console.log('🔄 Initializing settings store...')
      await fetchSettings()
      applySettings()
      initialized.value = true
      console.log('✅ Settings store initialized successfully')
    } catch (error) {
      console.error('❌ Settings store initialization failed:', error)
      // Set default settings to ensure app can still function
      settings.value = {
        app_name: 'Affiliate Platform',
        app_description: 'Advanced Affiliate Marketing Platform',
        primary_color: '#6366F1',
        secondary_color: '#8B5CF6',
        app_theme: 'light',
        default_language: 'fr',
        currency: 'MAD',
        currency_symbol: 'MAD',
        facebook_pxm_api_key: ''
      }
      // Set initialized to true to prevent infinite retries
      initialized.value = true
      console.log('⚠️ Using default settings due to initialization failure')
    } finally {
      loading.value = false
    }
  }

  /**
   * Fetch settings from API using simple fetch
   */
  const fetchSettings = async (): Promise<void> => {
    try {
      console.log('🔄 Fetching settings from API...')

      // Use simple fetch instead of $api to avoid potential issues
      const response = await fetch('/api/public/app-config', {
        method: 'GET',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json'
        }
      })

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`)
      }

      const data = await response.json()

      if (data && data.success) {
        settings.value = data.data || {}
        lastUpdated.value = new Date()
        console.log('✅ Settings fetched successfully:', data.data)
      } else {
        console.warn('⚠️ Settings API returned unsuccessful response:', data)
        throw new Error('Settings API returned unsuccessful response')
      }
    } catch (error) {
      console.error('❌ Failed to fetch settings:', error)
      // Don't throw error, just use empty settings to prevent app crash
      settings.value = {}
      throw error // Re-throw to handle in initialize method
    }
  }

  /**
   * Update settings
   */
  const updateSettings = async (category: string, data: Record<string, any>): Promise<void> => {
    loading.value = true
    try {
      const response = await $api(`/admin/settings/${category}`, {
        method: 'PUT',
        body: data
      })

      if (response.success) {
        // Re-fetch settings to get updated values
        await fetchSettings()
        applySettings()
        
        // Emit settings updated event
        emitSettingsUpdated()
      } else {
        throw new Error(response.message || 'Failed to update settings')
      }
    } catch (error) {
      console.error('Failed to update settings:', error)
      throw error
    } finally {
      loading.value = false
    }
  }

  /**
   * Get a specific setting value
   */
  const getSetting = <K extends keyof AppSettings>(
    key: K,
    defaultValue?: AppSettings[K]
  ): AppSettings[K] | undefined => {
    return (settings.value[key] as AppSettings[K]) ?? defaultValue
  }

  /**
   * Apply settings to the application
   */
  const applySettings = (): void => {
    // Apply theme settings
    applyThemeSettings()
    
    // Apply language settings
    applyLanguageSettings()
    
    // Apply favicon
    applyFavicon()
    
    // Apply logo
    applyLogo()
    
    // Apply app name
    applyAppName()
    
    // Apply CSS custom properties for colors
    applyCSSVariables()
  }

  /**
   * Apply theme settings
   */
  const applyThemeSettings = (): void => {
    try {
      const configStore = useConfigStore()
      const vuetifyTheme = useTheme()

      if (appTheme.value && appTheme.value !== 'system') {
        configStore.theme = appTheme.value as 'light' | 'dark'
      }

      // Apply primary and secondary colors to Vuetify theme
      if (primaryColor.value) {
        const lightTheme = vuetifyTheme.themes.value.light
        const darkTheme = vuetifyTheme.themes.value.dark

        if (lightTheme) {
          lightTheme.colors.primary = primaryColor.value
        }
        if (darkTheme) {
          darkTheme.colors.primary = primaryColor.value
        }
      }
    } catch (error) {
      console.warn('Failed to apply theme settings:', error)
    }
  }

  /**
   * Apply language settings
   */
  const applyLanguageSettings = (): void => {
    try {
      const { locale } = useI18n()
      if (defaultLanguage.value && locale.value !== defaultLanguage.value) {
        locale.value = defaultLanguage.value
      }
    } catch (error) {
      console.warn('Failed to apply language settings:', error)
    }
  }

  /**
   * Apply favicon
   */
  const applyFavicon = (): void => {
    const faviconUrl = appFavicon.value || settings.value.favicon
    if (faviconUrl) {
      const faviconLink = document.querySelector('link[rel="icon"]') as HTMLLinkElement
      if (faviconLink) {
        faviconLink.href = faviconUrl
      } else {
        const newFaviconLink = document.createElement('link')
        newFaviconLink.rel = 'icon'
        newFaviconLink.href = faviconUrl
        document.head.appendChild(newFaviconLink)
      }
      console.log('✅ Favicon updated:', faviconUrl)
    }
  }

  /**
   * Apply logo changes throughout the app
   */
  const applyLogo = (): void => {
    const logoUrl = appLogo.value || settings.value.company_logo
    if (logoUrl) {
      // Emit event for components to update logo
      window.dispatchEvent(new CustomEvent('logo:updated', {
        detail: { logoUrl }
      }))
      console.log('✅ Logo updated:', logoUrl)
    }
  }

  /**
   * Apply app name changes throughout the app
   */
  const applyAppName = (): void => {
    const name = appName.value
    if (name) {
      // Update document title
      document.title = name
      
      // Emit event for components to update app name
      window.dispatchEvent(new CustomEvent('appName:updated', {
        detail: { appName: name }
      }))
      console.log('✅ App name updated:', name)
    }
  }

  /**
   * Apply CSS custom properties
   */
  const applyCSSVariables = (): void => {
    const root = document.documentElement
    
    if (primaryColor.value) {
      root.style.setProperty('--settings-primary-color', primaryColor.value)
    }
    
    if (secondaryColor.value) {
      root.style.setProperty('--settings-secondary-color', secondaryColor.value)
    }
  }

  /**
   * Emit settings updated event for app-wide consumption
   */
  const emitSettingsUpdated = (): void => {
    window.dispatchEvent(new CustomEvent('settings:updated', {
      detail: { settings: settings.value }
    }))
  }

  // Watch for settings changes and apply them
  watch(
    () => settings.value,
    () => {
      if (initialized.value) {
        applySettings()
      }
    },
    { deep: true }
  )

  return {
    // State
    settings: computed(() => settings.value),
    loading: computed(() => loading.value),
    initialized: computed(() => initialized.value),
    lastUpdated: computed(() => lastUpdated.value),

    // Computed properties
    appName,
    appLogo,
    appFavicon,
    primaryColor,
    secondaryColor,
    defaultLanguage,
    appTheme,
    loginBackground,
    signupBackground,
    facebookPxmApiKey,
    companyInfo,
    socialLinks,
    brandingInfo,
    localizationInfo,

    // Methods
    initialize,
    fetchSettings,
    updateSettings,
    getSetting,
    applySettings
  }
})

// Create a global instance for app-wide access
export const globalSettings = useSettingsStore()
