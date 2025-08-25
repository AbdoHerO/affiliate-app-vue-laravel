import { useTheme } from 'vuetify'
import { useConfigStore } from '@core/stores/config'
import { useSettingsStore } from '@/stores/settings'

class ThemeService {
  private vuetifyTheme: any
  private configStore: any
  private settingsStore: any

  constructor() {
    this.vuetifyTheme = null
    this.configStore = null
    this.settingsStore = null
  }

  /**
   * Initialize the theme service
   */
  initialize() {
    try {
      this.vuetifyTheme = useTheme()
      this.configStore = useConfigStore()
      this.settingsStore = useSettingsStore()

      // Listen for settings updates
      window.addEventListener('settings:updated', this.handleSettingsUpdate.bind(this))

      console.log('✅ Theme service initialized successfully')
    } catch (error) {
      console.error('❌ Theme service initialization failed:', error)
    }
  }

  /**
   * Handle settings update event
   */
  private handleSettingsUpdate() {
    this.applyThemeFromSettings()
  }

  /**
   * Apply theme settings from the settings store
   */
  applyThemeFromSettings() {
    if (!this.vuetifyTheme || !this.settingsStore) return

    // Apply theme mode
    const themeMode = this.settingsStore.appTheme
    if (themeMode && themeMode !== 'system') {
      this.configStore.theme = themeMode
    }

    // Apply primary and secondary colors
    this.applyColors()

    // Apply CSS custom properties
    this.applyCSSVariables()
  }

  /**
   * Apply primary and secondary colors to Vuetify themes
   */
  private applyColors() {
    const primaryColor = this.settingsStore.primaryColor
    const secondaryColor = this.settingsStore.secondaryColor

    if (primaryColor) {
      // Apply to light theme
      if (this.vuetifyTheme.themes.value.light) {
        this.vuetifyTheme.themes.value.light.colors.primary = primaryColor
      }

      // Apply to dark theme
      if (this.vuetifyTheme.themes.value.dark) {
        this.vuetifyTheme.themes.value.dark.colors.primary = primaryColor
      }
    }

    if (secondaryColor) {
      // Apply to light theme
      if (this.vuetifyTheme.themes.value.light) {
        this.vuetifyTheme.themes.value.light.colors.secondary = secondaryColor
      }

      // Apply to dark theme
      if (this.vuetifyTheme.themes.value.dark) {
        this.vuetifyTheme.themes.value.dark.colors.secondary = secondaryColor
      }
    }
  }

  /**
   * Apply CSS custom properties for colors
   */
  private applyCSSVariables() {
    const root = document.documentElement
    
    const primaryColor = this.settingsStore.primaryColor
    const secondaryColor = this.settingsStore.secondaryColor

    if (primaryColor) {
      root.style.setProperty('--settings-primary-color', primaryColor)
      root.style.setProperty('--v-theme-primary', primaryColor)
    }
    
    if (secondaryColor) {
      root.style.setProperty('--settings-secondary-color', secondaryColor)
      root.style.setProperty('--v-theme-secondary', secondaryColor)
    }
  }

  /**
   * Set a specific color
   */
  setColor(colorName: 'primary' | 'secondary', colorValue: string) {
    if (!this.vuetifyTheme) return

    // Apply to both light and dark themes
    if (this.vuetifyTheme.themes.value.light) {
      this.vuetifyTheme.themes.value.light.colors[colorName] = colorValue
    }

    if (this.vuetifyTheme.themes.value.dark) {
      this.vuetifyTheme.themes.value.dark.colors[colorName] = colorValue
    }

    // Apply CSS variable
    const root = document.documentElement
    root.style.setProperty(`--v-theme-${colorName}`, colorValue)
    root.style.setProperty(`--settings-${colorName}-color`, colorValue)
  }

  /**
   * Set theme mode
   */
  setThemeMode(mode: 'light' | 'dark' | 'system') {
    if (!this.configStore) return

    this.configStore.theme = mode
  }

  /**
   * Get current theme colors
   */
  getCurrentColors() {
    if (!this.vuetifyTheme) return {}

    const currentTheme = this.vuetifyTheme.current.value
    return {
      primary: currentTheme.colors.primary,
      secondary: currentTheme.colors.secondary,
      surface: currentTheme.colors.surface,
      background: currentTheme.colors.background
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
export const themeService = new ThemeService()

export default themeService
