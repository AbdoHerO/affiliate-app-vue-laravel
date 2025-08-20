import { ref } from 'vue'

/**
 * Global cart UI state management
 * Provides shared state for cart drawer visibility across components
 */

// Global reactive state
const cartDrawerOpen = ref(false)

export function useAffiliateCartUi() {
  // Methods to control cart drawer
  const openCartDrawer = () => {
    cartDrawerOpen.value = true
  }

  const closeCartDrawer = () => {
    cartDrawerOpen.value = false
  }

  const toggleCartDrawer = () => {
    cartDrawerOpen.value = !cartDrawerOpen.value
  }

  return {
    // State
    cartDrawerOpen,
    
    // Actions
    openCartDrawer,
    closeCartDrawer,
    toggleCartDrawer
  }
}
