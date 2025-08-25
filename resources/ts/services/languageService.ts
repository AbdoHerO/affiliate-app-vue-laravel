import { useI18n } from 'vue-i18n'
import { useSettingsStore } from '@/stores/settings'
import { cookieRef } from '@layouts/stores/config'

class LanguageService {
  private i18n: any
  private settingsStore: any
  private languageCookie: any

  constructor() {
    this.i18n = null
    this.settingsStore = null
    this.languageCookie = null
  }

  /**
   * Initialize the language service
   */
  initialize() {
    try {
      this.i18n = useI18n({ useScope: 'global' })
      this.settingsStore = useSettingsStore()
      this.languageCookie = cookieRef<string | null>('language', null)

      // Listen for settings updates
      window.addEventListener('settings:updated', this.handleSettingsUpdate.bind(this))

      // Apply initial language from settings
      this.applyLanguageFromSettings()

      console.log('✅ Language service initialized successfully')
    } catch (error) {
      console.error('❌ Language service initialization failed:', error)
    }
  }

  /**
   * Handle settings update event
   */
  private handleSettingsUpdate() {
    this.applyLanguageFromSettings()
  }

  /**
   * Apply language settings from the settings store
   */
  applyLanguageFromSettings() {
    if (!this.i18n || !this.settingsStore) return

    const defaultLanguage = this.settingsStore.defaultLanguage
    
    // If no user preference is stored, use the default language from settings
    if (!this.languageCookie.value && defaultLanguage) {
      this.setLanguage(defaultLanguage)
    }
  }

  /**
   * Set the current language
   */
  setLanguage(language: string) {
    if (!this.i18n) return

    // Update i18n locale
    this.i18n.locale.value = language

    // Store user preference in cookie
    this.languageCookie.value = language

    // Update document language attribute
    document.documentElement.lang = language

    // Update document direction for RTL languages
    const rtlLanguages = ['ar', 'he', 'fa']
    document.documentElement.dir = rtlLanguages.includes(language) ? 'rtl' : 'ltr'
  }

  /**
   * Get the current language
   */
  getCurrentLanguage(): string {
    return this.i18n?.locale.value || 'fr'
  }

  /**
   * Get available languages
   */
  getAvailableLanguages() {
    return [
      { code: 'fr', name: 'Français', flag: '🇫🇷', isRTL: false },
      { code: 'en', name: 'English', flag: '🇺🇸', isRTL: false },
      { code: 'ar', name: 'العربية', flag: '🇲🇦', isRTL: true }
    ]
  }

  /**
   * Check if current language is RTL
   */
  isRTL(): boolean {
    const currentLang = this.getCurrentLanguage()
    const rtlLanguages = ['ar', 'he', 'fa']
    return rtlLanguages.includes(currentLang)
  }

  /**
   * Get default language from settings
   */
  getDefaultLanguage(): string {
    return this.settingsStore?.defaultLanguage || 'fr'
  }

  /**
   * Reset to default language
   */
  resetToDefault() {
    const defaultLanguage = this.getDefaultLanguage()
    this.setLanguage(defaultLanguage)
  }

  /**
   * Get user's preferred language (from cookie or default)
   */
  getUserPreferredLanguage(): string {
    return this.languageCookie?.value || this.getDefaultLanguage()
  }

  /**
   * Initialize language for new sessions
   */
  initializeForNewSession() {
    const userPreferred = this.languageCookie?.value
    const defaultLanguage = this.getDefaultLanguage()

    // If user has no preference, use default from settings
    if (!userPreferred) {
      this.setLanguage(defaultLanguage)
    } else {
      // Use user preference but ensure it's applied
      this.setLanguage(userPreferred)
    }
  }

  /**
   * Cleanup
   */
  destroy() {
    window.removeEventListener('settings:updated', this.handleSettingsUpdate.bind(this))
  }
}

// Create a singleton instance
export const languageService = new LanguageService()

export default languageService
