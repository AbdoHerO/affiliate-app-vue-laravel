import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useSettingsStore } from '@/stores/settings'

export function useDynamicBackground(type: 'login' | 'signup') {
  const settingsStore = useSettingsStore()
  const forceUpdate = ref(0)

  // Computed background image with fallback
  const backgroundImage = computed(() => {
    // Force reactivity
    forceUpdate.value
    
    if (type === 'login') {
      return settingsStore.loginBackground || ''
    } else {
      return settingsStore.signupBackground || ''
    }
  })

  // Computed background styles
  const backgroundStyles = computed(() => {
    const bgImage = backgroundImage.value
    
    if (!bgImage) {
      return {}
    }

    return {
      backgroundImage: `url(${bgImage})`,
      backgroundSize: 'cover',
      backgroundPosition: 'center',
      backgroundRepeat: 'no-repeat'
    }
  })

  // Apply background to a specific element
  const applyBackgroundToElement = (element: HTMLElement) => {
    const bgImage = backgroundImage.value
    
    if (bgImage) {
      element.style.backgroundImage = `url(${bgImage})`
      element.style.backgroundSize = 'cover'
      element.style.backgroundPosition = 'center'
      element.style.backgroundRepeat = 'no-repeat'
    } else {
      // Reset to default
      element.style.backgroundImage = ''
      element.style.backgroundSize = ''
      element.style.backgroundPosition = ''
      element.style.backgroundRepeat = ''
    }
  }

  // Listen for settings updates
  const handleSettingsUpdate = () => {
    forceUpdate.value++
  }

  onMounted(() => {
    window.addEventListener('settings:updated', handleSettingsUpdate)
  })

  onUnmounted(() => {
    window.removeEventListener('settings:updated', handleSettingsUpdate)
  })

  return {
    backgroundImage,
    backgroundStyles,
    applyBackgroundToElement
  }
}
