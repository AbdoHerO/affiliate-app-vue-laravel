import { ref, computed, onMounted } from 'vue'
import { settingsService, type AppSettings } from '@/services/settingsService'

const settings = ref<Partial<AppSettings>>({})
const loading = ref(false)
const initialized = ref(false)

export function useSettings() {
  /**
   * Initialize settings if not already done
   */
  const initialize = async () => {
    if (initialized.value) return

    loading.value = true
    try {
      await settingsService.initialize()
      settings.value = settingsService.getAll()
      initialized.value = true
    } catch (error) {
      console.error('Failed to initialize settings:', error)
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
    return settingsService.get(key, defaultValue)
  }

  /**
   * Update settings (admin only)
   */
  const updateSettings = async (category: string, data: Record<string, any>): Promise<boolean> => {
    const success = await settingsService.updateSettings(category, data)
    if (success && category === 'general') {
      // Update local settings
      settings.value = { ...settings.value, ...data }
    }
    return success
  }

  /**
   * Computed properties for commonly used settings
   */
  const appName = computed(() => getSetting('app_name', 'Affiliate Platform'))
  const appLogo = computed(() => getSetting('app_logo', ''))
  const primaryColor = computed(() => getSetting('primary_color', '#6366F1'))
  const secondaryColor = computed(() => getSetting('secondary_color', '#8B5CF6'))
  const defaultLanguage = computed(() => getSetting('default_language', 'fr'))
  const currency = computed(() => getSetting('currency', 'MAD'))
  const currencySymbol = computed(() => getSetting('currency_symbol', 'MAD'))
  const isMaintenanceMode = computed(() => getSetting('maintenance_mode', false))
  const isRegistrationEnabled = computed(() => getSetting('registration_enabled', true))
  const loginBackground = computed(() => getSetting('login_background_image', ''))
  const signupBackground = computed(() => getSetting('signup_background_image', ''))
  const appTheme = computed(() => getSetting('app_theme', 'light'))

  /**
   * Format currency using settings
   */
  const formatCurrency = (amount: number): string => {
    return settingsService.formatCurrency(amount)
  }

  /**
   * Format date using settings
   */
  const formatDate = (date: Date): string => {
    return settingsService.formatDate(date)
  }

  /**
   * Check if a feature is enabled
   */
  const isFeatureEnabled = (feature: keyof AppSettings): boolean => {
    return Boolean(getSetting(feature, false))
  }

  /**
   * Get company information
   */
  const companyInfo = computed(() => ({
    name: getSetting('company_name', ''),
    email: getSetting('company_email', ''),
    phone: getSetting('company_phone', ''),
    address: getSetting('company_address', ''),
    website: getSetting('company_website', ''),
    social: {
      facebook: getSetting('company_social_facebook', ''),
      instagram: getSetting('company_social_instagram', ''),
      twitter: getSetting('company_social_twitter', '')
    }
  }))

  /**
   * Get branding information
   */
  const brandingInfo = computed(() => ({
    logo: appLogo.value,
    favicon: getSetting('app_favicon', ''),
    primaryColor: primaryColor.value,
    secondaryColor: secondaryColor.value,
    theme: appTheme.value,
    loginBackground: loginBackground.value,
    signupBackground: signupBackground.value
  }))

  /**
   * Get localization settings
   */
  const localizationInfo = computed(() => ({
    defaultLanguage: defaultLanguage.value,
    timezone: getSetting('timezone', 'Africa/Casablanca'),
    currency: currency.value,
    currencySymbol: currencySymbol.value,
    dateFormat: getSetting('date_format', 'DD/MM/YYYY'),
    timeFormat: getSetting('time_format', '24'),
    numberFormat: getSetting('number_format', 'european')
  }))

  // Auto-initialize on first use
  onMounted(() => {
    if (!initialized.value) {
      initialize()
    }
  })

  return {
    // State
    settings: computed(() => settings.value),
    loading: computed(() => loading.value),
    initialized: computed(() => initialized.value),

    // Methods
    initialize,
    getSetting,
    updateSettings,
    formatCurrency,
    formatDate,
    isFeatureEnabled,

    // Computed properties
    appName,
    appLogo,
    primaryColor,
    secondaryColor,
    defaultLanguage,
    currency,
    currencySymbol,
    isMaintenanceMode,
    isRegistrationEnabled,
    loginBackground,
    signupBackground,
    appTheme,
    companyInfo,
    brandingInfo,
    localizationInfo
  }
}

// Create a global instance for app-wide access
export const globalSettings = useSettings()

export default useSettings
