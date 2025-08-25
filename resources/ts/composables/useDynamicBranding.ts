import { computed, onMounted, onUnmounted, ref, h } from 'vue'
import { useSettingsStore } from '@/stores/settings'

// Use a fallback logo path that should exist
const defaultLogo = '/images/logo.svg'

export function useDynamicBranding() {
  const settingsStore = useSettingsStore()
  const forceUpdate = ref(0)

  // Computed logo VNode with fallback
  const dynamicLogo = computed(() => {
    try {
      // Force reactivity
      forceUpdate.value

      const logoSrc = settingsStore.appLogo || defaultLogo
      return h('img', {
        src: logoSrc,
        alt: settingsStore.appName || 'Affiliate Platform',
        style: 'height: 55px; width: auto; object-fit: contain;'
      })
    } catch (error) {
      console.error('Error creating dynamic logo:', error)
      return h('img', {
        src: defaultLogo,
        alt: 'Affiliate Platform',
        style: 'height: 55px; width: auto; object-fit: contain;'
      })
    }
  })

  // Computed app title with fallback
  const dynamicTitle = computed(() => {
    try {
      // Force reactivity
      forceUpdate.value

      return settingsStore.appName || 'Affiliate Platform'
    } catch (error) {
      console.error('Error getting dynamic title:', error)
      return 'Affiliate Platform'
    }
  })

  // Computed primary color
  const dynamicPrimaryColor = computed(() => {
    // Force reactivity
    forceUpdate.value
    
    return settingsStore.primaryColor || '#6366F1'
  })

  // Computed secondary color
  const dynamicSecondaryColor = computed(() => {
    // Force reactivity
    forceUpdate.value
    
    return settingsStore.secondaryColor || '#8B5CF6'
  })

  // Listen for settings updates
  const handleSettingsUpdate = () => {
    forceUpdate.value++
  }

  // Apply favicon dynamically
  const applyFavicon = () => {
    if (settingsStore.appFavicon) {
      const faviconLink = document.querySelector('link[rel="icon"]') as HTMLLinkElement
      if (faviconLink) {
        faviconLink.href = settingsStore.appFavicon
      } else {
        const newFaviconLink = document.createElement('link')
        newFaviconLink.rel = 'icon'
        newFaviconLink.href = settingsStore.appFavicon
        document.head.appendChild(newFaviconLink)
      }
    }
  }

  // Apply document title
  const applyDocumentTitle = () => {
    if (settingsStore.appName) {
      document.title = settingsStore.appName
    }
  }

  onMounted(() => {
    window.addEventListener('settings:updated', handleSettingsUpdate)
    applyFavicon()
    applyDocumentTitle()
  })

  onUnmounted(() => {
    window.removeEventListener('settings:updated', handleSettingsUpdate)
  })

  return {
    dynamicLogo,
    dynamicTitle,
    dynamicPrimaryColor,
    dynamicSecondaryColor,
    applyFavicon,
    applyDocumentTitle
  }
}
