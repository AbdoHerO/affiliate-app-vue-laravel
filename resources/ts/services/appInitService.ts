import { $api } from '@/utils/api'
import ThemeConfigService from './themeConfigService'

export class AppInitService {
  private static initialized = false

  /**
   * Initialize the app with settings from the backend
   */
  static async initialize() {
    if (this.initialized) return

    try {
      console.log('Initializing app with settings...')

      // Check if we're on a public page (no authentication required)
      const currentPath = window.location.pathname
      const isPublicPage = [
        '/login',
        '/register',
        '/affiliate-signup',
        '/forgot-password',
        '/reset-password'
      ].some(path => currentPath.includes(path))

      if (isPublicPage) {
        console.log('Public page detected, skipping admin settings load')
        this.initialized = true
        return
      }

      // Only load admin settings for authenticated pages
      try {
        const response = await $api('/admin/settings/general', {
          method: 'GET'
        })

        if (response.success && response.data) {
          // Update theme config with settings
          ThemeConfigService.updateFromSettings(response.data)

          // Update document meta tags
          this.updateDocumentMeta(response.data)

          console.log('App initialized successfully with settings')
        }
      } catch (settingsError: any) {
        // If it's a 401 error, we're probably on a public page
        if (settingsError.response?.status === 401) {
          console.log('Unauthorized access to settings - likely on public page')
        } else {
          console.error('Failed to load settings:', settingsError)
        }
      }

      this.initialized = true
    } catch (error) {
      console.error('Failed to initialize app with settings:', error)
      // Continue with defaults
      this.initialized = true
    }
  }

  /**
   * Update document meta tags
   */
  private static updateDocumentMeta(settings: any) {
    try {
      // Update document title
      if (settings.app_name) {
        document.title = settings.app_name
      }

      // Update meta description
      if (settings.app_description) {
        const metaDescription = document.querySelector('meta[name="description"]')
        if (metaDescription) {
          metaDescription.setAttribute('content', settings.app_description)
        }
      }

      // Update meta keywords
      if (settings.app_keywords) {
        let metaKeywords = document.querySelector('meta[name="keywords"]')
        if (!metaKeywords) {
          metaKeywords = document.createElement('meta')
          metaKeywords.setAttribute('name', 'keywords')
          document.head.appendChild(metaKeywords)
        }
        metaKeywords.setAttribute('content', settings.app_keywords)
      }

      // Update favicon
      if (settings.favicon) {
        const faviconUrl = settings.favicon.startsWith('http') || settings.favicon.startsWith('/storage/') 
          ? settings.favicon 
          : `/storage/settings/${settings.favicon}`
        
        let faviconLink = document.querySelector('link[rel="icon"]') as HTMLLinkElement
        if (!faviconLink) {
          faviconLink = document.createElement('link')
          faviconLink.rel = 'icon'
          document.head.appendChild(faviconLink)
        }
        faviconLink.href = faviconUrl
      }
    } catch (error) {
      console.error('Failed to update document meta:', error)
    }
  }

  /**
   * Force re-initialization (useful after settings changes)
   */
  static async reinitialize() {
    this.initialized = false
    await this.initialize()
  }
}

export default AppInitService
