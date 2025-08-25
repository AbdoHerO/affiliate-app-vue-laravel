import { $api } from '@/utils/api'
import { useI18n } from 'vue-i18n'

export interface AppSettings {
  // App Information
  app_name: string
  app_description: string
  app_tagline: string
  app_version: string
  app_logo: string
  app_favicon: string
  
  // Company Information
  company_name: string
  company_email: string
  company_phone: string
  company_address: string
  company_website: string
  company_social_facebook: string
  company_social_instagram: string
  company_social_twitter: string
  
  // Branding & Appearance
  primary_color: string
  secondary_color: string
  login_background_image: string
  signup_background_image: string
  app_theme: string
  
  // Localization
  default_language: string
  available_languages: string[]
  timezone: string
  currency: string
  currency_symbol: string
  date_format: string
  time_format: string
  number_format: string
  
  // System Settings
  maintenance_mode: boolean
  registration_enabled: boolean
  email_verification_required: boolean
  terms_and_conditions_url: string
  privacy_policy_url: string
}

class SettingsService {
  private settings: Partial<AppSettings> = {}
  private initialized = false

  /**
   * Initialize settings service and load public settings
   */
  async initialize(): Promise<void> {
    if (this.initialized) return

    try {
      const response = await $api('/public/app-config', {
        method: 'GET'
      })

      if (response.success) {
        this.settings = response.data || {}
        this.applySettings()
        this.initialized = true
      }
    } catch (error) {
      console.error('Failed to load public settings:', error)
    }
  }

  /**
   * Get a specific setting value
   */
  get<K extends keyof AppSettings>(key: K, defaultValue?: AppSettings[K]): AppSettings[K] | undefined {
    return this.settings[key] ?? defaultValue
  }

  /**
   * Get all settings
   */
  getAll(): Partial<AppSettings> {
    return { ...this.settings }
  }

  /**
   * Update settings (admin only)
   */
  async updateSettings(category: string, data: Record<string, any>): Promise<boolean> {
    try {
      const response = await $api(`/admin/settings/${category}`, {
        method: 'PUT',
        body: data
      })

      if (response.success) {
        // Update local settings if it's general category
        if (category === 'general') {
          this.settings = { ...this.settings, ...data }
          this.applySettings()
        }
        return true
      }
      return false
    } catch (error) {
      console.error('Failed to update settings:', error)
      return false
    }
  }

  /**
   * Apply settings to the application
   */
  private applySettings(): void {
    this.applyTheme()
    this.applyColors()
    this.applyLogo()
    this.applyFavicon()
    this.applyLanguage()
    this.applyDocumentTitle()
    this.applyBackgroundImages()
  }

  /**
   * Apply theme settings
   */
  private applyTheme(): void {
    const theme = this.get('app_theme', 'light')
    const html = document.documentElement

    if (theme === 'dark') {
      html.classList.add('dark')
      html.classList.remove('light')
    } else if (theme === 'light') {
      html.classList.add('light')
      html.classList.remove('dark')
    } else if (theme === 'auto') {
      // Use system preference
      const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches
      if (prefersDark) {
        html.classList.add('dark')
        html.classList.remove('light')
      } else {
        html.classList.add('light')
        html.classList.remove('dark')
      }
    }
  }

  /**
   * Apply color scheme
   */
  private applyColors(): void {
    const primaryColor = this.get('primary_color', '#6366F1')
    const secondaryColor = this.get('secondary_color', '#8B5CF6')

    // Apply CSS custom properties
    const root = document.documentElement
    root.style.setProperty('--v-theme-primary', this.hexToRgb(primaryColor))
    root.style.setProperty('--v-theme-secondary', this.hexToRgb(secondaryColor))
  }

  /**
   * Apply logo
   */
  private applyLogo(): void {
    const logoUrl = this.get('app_logo')
    if (logoUrl) {
      // Update all logo elements
      const logoElements = document.querySelectorAll('[data-app-logo]')
      logoElements.forEach(element => {
        if (element instanceof HTMLImageElement) {
          element.src = logoUrl
        }
      })
    }
  }

  /**
   * Apply favicon
   */
  private applyFavicon(): void {
    const faviconUrl = this.get('app_favicon')
    if (faviconUrl) {
      let favicon = document.querySelector('link[rel="icon"]') as HTMLLinkElement
      if (!favicon) {
        favicon = document.createElement('link')
        favicon.rel = 'icon'
        document.head.appendChild(favicon)
      }
      favicon.href = faviconUrl
    }
  }

  /**
   * Apply language settings
   */
  private applyLanguage(): void {
    const defaultLanguage = this.get('default_language', 'fr')
    const { locale } = useI18n()
    
    if (locale.value !== defaultLanguage) {
      locale.value = defaultLanguage
    }

    // Update HTML lang attribute
    document.documentElement.lang = defaultLanguage
  }

  /**
   * Apply document title
   */
  private applyDocumentTitle(): void {
    const appName = this.get('app_name', 'Affiliate Platform')
    document.title = appName
  }

  /**
   * Apply background images to login/signup pages
   */
  private applyBackgroundImages(): void {
    const loginBg = this.get('login_background_image')
    const signupBg = this.get('signup_background_image')

    // Store in CSS custom properties for use in components
    const root = document.documentElement
    if (loginBg) {
      root.style.setProperty('--login-background-image', `url(${loginBg})`)
    }
    if (signupBg) {
      root.style.setProperty('--signup-background-image', `url(${signupBg})`)
    }
  }

  /**
   * Convert hex color to RGB values for CSS custom properties
   */
  private hexToRgb(hex: string): string {
    const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex)
    if (result) {
      const r = parseInt(result[1], 16)
      const g = parseInt(result[2], 16)
      const b = parseInt(result[3], 16)
      return `${r}, ${g}, ${b}`
    }
    return '99, 102, 241' // Default indigo
  }

  /**
   * Check if maintenance mode is enabled
   */
  isMaintenanceMode(): boolean {
    return this.get('maintenance_mode', false)
  }

  /**
   * Check if registration is enabled
   */
  isRegistrationEnabled(): boolean {
    return this.get('registration_enabled', true)
  }

  /**
   * Check if email verification is required
   */
  isEmailVerificationRequired(): boolean {
    return this.get('email_verification_required', true)
  }

  /**
   * Get formatted currency
   */
  formatCurrency(amount: number): string {
    const currency = this.get('currency', 'MAD')
    const symbol = this.get('currency_symbol', 'MAD')
    const numberFormat = this.get('number_format', 'european')

    let formattedAmount: string

    switch (numberFormat) {
      case 'american':
        formattedAmount = amount.toLocaleString('en-US', { minimumFractionDigits: 2 })
        break
      case 'arabic':
        formattedAmount = amount.toLocaleString('ar-MA', { minimumFractionDigits: 2 })
        break
      default: // european
        formattedAmount = amount.toLocaleString('fr-FR', { minimumFractionDigits: 2 })
    }

    return `${formattedAmount} ${symbol}`
  }

  /**
   * Get formatted date
   */
  formatDate(date: Date): string {
    const format = this.get('date_format', 'DD/MM/YYYY')
    const timeFormat = this.get('time_format', '24')

    const options: Intl.DateTimeFormatOptions = {
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour12: timeFormat === '12'
    }

    if (format.includes('YYYY-MM-DD')) {
      return date.toISOString().split('T')[0]
    } else {
      return date.toLocaleDateString('fr-FR', options)
    }
  }
}

// Create singleton instance
export const settingsService = new SettingsService()

// Auto-initialize on import
settingsService.initialize()

export default settingsService
