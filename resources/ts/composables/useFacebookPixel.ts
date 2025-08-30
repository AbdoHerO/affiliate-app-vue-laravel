import { ref, computed } from 'vue'
import { useSettingsStore } from '@/stores/settings'

declare global {
  interface Window {
    fbq?: (...args: any[]) => void
    _fbq?: (...args: any[]) => void
  }
}

export function useFacebookPixel() {
  const settingsStore = useSettingsStore()
  const isPixelInjected = ref(false)

  // Get Facebook Pixel API key from settings
  const pixelApiKey = computed(() => settingsStore.facebookPxmApiKey)

  /**
   * Check if pixel is available and initialized
   */
  const isPixelAvailable = computed(() => {
    return !!window.fbq && !!pixelApiKey.value && isPixelInjected.value
  })

  /**
   * Inject Facebook Pixel script into the document head
   */
  const injectPixelScript = async (): Promise<void> => {
    // Don't inject if already injected or no API key
    if (isPixelInjected.value || !pixelApiKey.value) {
      return
    }

    // Check if script already exists
    const existingScript = document.querySelector('script[src*="connect.facebook.net"]')
    if (existingScript) {
      isPixelInjected.value = true
      return
    }

    try {
      // Create and inject the Facebook Pixel script
      const script = document.createElement('script')
      script.async = true
      script.src = 'https://connect.facebook.net/en_US/fbevents.js'
      
      // Add the script to head
      document.head.appendChild(script)

      // Wait for script to load
      await new Promise((resolve, reject) => {
        script.onload = resolve
        script.onerror = reject
      })

      // Initialize the pixel
      if (window.fbq) {
        window.fbq('init', pixelApiKey.value)
        window.fbq('track', 'PageView')
        isPixelInjected.value = true
        console.log('✅ Facebook Pixel initialized successfully:', pixelApiKey.value)
      }
    } catch (error) {
      console.error('❌ Failed to inject Facebook Pixel script:', error)
    }
  }

  /**
   * Track a Facebook Pixel event
   */
  const trackEvent = (eventName: string, parameters: Record<string, any> = {}): void => {
    if (!isPixelAvailable.value) {
      console.warn('Facebook Pixel not available, skipping event:', eventName)
      return
    }

    try {
      // Filter out any PII data for privacy
      const sanitizedParams = sanitizeParameters(parameters)
      
      window.fbq?.('track', eventName, sanitizedParams)
      console.log(`📊 Facebook Pixel event tracked: ${eventName}`, sanitizedParams)
    } catch (error) {
      console.error('Failed to track Facebook Pixel event:', error)
    }
  }

  /**
   * Track ViewContent event
   */
  const trackViewContent = (parameters: Record<string, any> = {}): void => {
    trackEvent('ViewContent', parameters)
  }

  /**
   * Track CompleteRegistration event
   */
  const trackCompleteRegistration = (value: number = 0, currency: string = 'MAD'): void => {
    trackEvent('CompleteRegistration', {
      value,
      currency
    })
  }

  /**
   * Track Lead event
   */
  const trackLead = (parameters: Record<string, any> = {}): void => {
    trackEvent('Lead', parameters)
  }

  /**
   * Track custom event
   */
  const trackCustomEvent = (eventName: string, parameters: Record<string, any> = {}): void => {
    trackEvent(eventName, parameters)
  }

  /**
   * Sanitize parameters to remove PII data
   */
  const sanitizeParameters = (parameters: Record<string, any>): Record<string, any> => {
    const sanitized = { ...parameters }
    
    // Remove common PII fields
    const piiFields = ['email', 'phone', 'telephone', 'address', 'nom_complet', 'name', 'firstName', 'lastName']
    
    piiFields.forEach(field => {
      if (sanitized[field]) {
        delete sanitized[field]
      }
    })

    return sanitized
  }

  /**
   * Initialize pixel when settings are loaded
   */
  const initializePixel = async (): Promise<void> => {
    // Wait for settings to be loaded
    if (!settingsStore.initialized) {
      await settingsStore.initialize()
    }

    // Inject pixel script if API key is available
    if (pixelApiKey.value) {
      await injectPixelScript()
    }
  }

  return {
    // State
    isPixelInjected: computed(() => isPixelInjected.value),
    isPixelAvailable,
    pixelApiKey,

    // Methods
    injectPixelScript,
    initializePixel,
    trackEvent,
    trackViewContent,
    trackCompleteRegistration,
    trackLead,
    trackCustomEvent
  }
}
