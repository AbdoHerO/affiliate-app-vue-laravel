import { h } from 'vue'
import { themeConfig } from '@themeConfig'
import { layoutConfig } from '@layouts'
import defaultLogo from '@images/logo.png'

export class ThemeConfigService {
  /**
   * Update the app title in themeConfig
   */
  static updateAppTitle(newTitle: string) {
    try {
      // Update themeConfig title
      themeConfig.app.title = newTitle as any

      // Update layoutConfig title (used by sidebar)
      layoutConfig.app.title = newTitle as any

      // Also update document title
      document.title = newTitle

      // Update sidebar title element
      this.updateSidebarTitle(newTitle)

      console.log('App title updated to:', newTitle)
    } catch (error) {
      console.error('Failed to update app title:', error)
    }
  }

  /**
   * Update sidebar title in the DOM
   */
  private static updateSidebarTitle(newTitle: string) {
    try {
      const sidebarTitle = document.getElementById('app-title-sidebar')
      if (sidebarTitle) {
        sidebarTitle.textContent = newTitle
      }
    } catch (error) {
      console.error('Failed to update sidebar title:', error)
    }
  }

  /**
   * Update the app logo in themeConfig
   */
  static updateAppLogo(logoUrl: string, appName: string = 'App') {
    try {
      // Create new logo VNode
      const newLogo = h('img', {
        src: logoUrl || defaultLogo,
        alt: appName,
        style: 'height: 55px; width: auto; object-fit: contain;',
        id: 'app-logo-main',
        onError: (e: Event) => {
          // Fallback to default logo if custom logo fails
          const target = e.target as HTMLImageElement
          if (target.src !== defaultLogo) {
            target.src = defaultLogo
          }
        }
      })

      // Update themeConfig logo
      themeConfig.app.logo = newLogo

      // Update layoutConfig logo (used by sidebar)
      layoutConfig.app.logo = newLogo

      // Also update any existing logo elements in the DOM
      this.updateDOMLogos(logoUrl || defaultLogo, appName)

      console.log('App logo updated to:', logoUrl)
    } catch (error) {
      console.error('Failed to update app logo:', error)
    }
  }

  /**
   * Update logo elements in the DOM
   */
  private static updateDOMLogos(logoUrl: string, appName: string) {
    try {
      // Update main app logo
      const mainLogo = document.getElementById('app-logo-main') as HTMLImageElement
      if (mainLogo) {
        mainLogo.src = logoUrl
        mainLogo.alt = appName
      }

      // Update any other logo elements with data-app-logo attribute
      const logoElements = document.querySelectorAll('[data-app-logo]')
      logoElements.forEach(element => {
        if (element instanceof HTMLImageElement) {
          element.src = logoUrl
          element.alt = appName
        }
      })
    } catch (error) {
      console.error('Failed to update DOM logos:', error)
    }
  }

  /**
   * Update both title and logo from settings
   */
  static updateFromSettings(settings: { app_name?: string; company_logo?: string }) {
    if (settings.app_name) {
      this.updateAppTitle(settings.app_name)
    }

    if (settings.company_logo) {
      const logoUrl = settings.company_logo.startsWith('http') || settings.company_logo.startsWith('/storage/') 
        ? settings.company_logo 
        : `/storage/settings/${settings.company_logo}`
      
      this.updateAppLogo(logoUrl, settings.app_name || 'App')
    }
  }

  /**
   * Reset to default values
   */
  static resetToDefaults() {
    this.updateAppTitle('Arif Style')
    this.updateAppLogo(defaultLogo, 'Arif Style')
    console.log('Theme config reset to defaults')
  }
}

// Export for global use
export default ThemeConfigService
