import { ref, computed, watch } from 'vue'
import type { NormalizedProduct } from '@/stores/affiliate/catalogue'

export interface VariantSelectionState {
  selectedColor: string | null
  selectedSize: string | null
  selectedVariantId: string | null
  maxQty: number
  activeImageUrl: string
}

export interface VariantSelectionMethods {
  selectColor: (colorName: string) => void
  selectSize: (sizeName: string) => void
  imageForColor: (colorName: string) => string
  computeMaxQty: () => number
  reset: () => void
}

export interface UseVariantSelectionReturn extends VariantSelectionState, VariantSelectionMethods {
  canAddToCart: (qty: number) => boolean
}

/**
 * Shared composable for variant selection logic
 * Used by both ProductDrawer and CatalogueCard for consistent behavior
 */
export function useVariantSelection(product: NormalizedProduct | null): UseVariantSelectionReturn {
  // State
  const selectedColor = ref<string | null>(null)
  const selectedSize = ref<string | null>(null)
  const selectedVariantId = ref<string | null>(null)
  const maxQty = ref(0)
  const activeImageUrl = ref('')

  // Helper function to create combo key for variant lookup
  const createComboKey = (color: string | null, size: string | null): string => {
    if (color && size) {
      return `couleur|${color}+taille|${size}`
    }
    if (color) {
      return `couleur|${color}`
    }
    if (size) {
      return `taille|${size}`
    }
    return 'default'
  }

  // Computed properties
  const availableColors = computed(() => {
    return product?.colors || []
  })

  const availableSizes = computed(() => {
    return product?.sizes || []
  })

  const selectedVariant = computed(() => {
    if (!product) return null

    // Try to find variant based on current selections
    if (selectedColor.value && selectedSize.value) {
      // Look for combined variant
      const comboKey = createComboKey(selectedColor.value, selectedSize.value)
      const variant = product.variantsByCombo?.[comboKey]
      if (variant) return variant
    }

    if (selectedColor.value) {
      const comboKey = createComboKey(selectedColor.value, null)
      const variant = product.variantsByCombo?.[comboKey]
      if (variant) return variant
    }

    if (selectedSize.value) {
      const comboKey = createComboKey(null, selectedSize.value)
      const variant = product.variantsByCombo?.[comboKey]
      if (variant) return variant
    }

    return null
  })

  // Methods
  const selectColor = (colorName: string) => {
    selectedColor.value = colorName
    resolveVariantAndStock()
    updateActiveImage()
  }

  const selectSize = (sizeName: string) => {
    selectedSize.value = sizeName
    resolveVariantAndStock()
  }

  const resolveVariantAndStock = () => {
    if (!product) {
      selectedVariantId.value = null
      maxQty.value = 0
      return
    }

    const variant = selectedVariant.value
    if (variant) {
      selectedVariantId.value = variant.id
      maxQty.value = variant.stock_available || 0
    } else {
      selectedVariantId.value = null
      maxQty.value = product.stock_total || 0
    }
  }

  const updateActiveImage = () => {
    if (!product) {
      activeImageUrl.value = ''
      return
    }

    // If a color is selected, try to use its specific image
    if (selectedColor.value) {
      const color = availableColors.value.find(c => c.name === selectedColor.value)
      if (color?.image_url) {
        activeImageUrl.value = color.image_url
        return
      }
    }

    // Fallback to main product image
    activeImageUrl.value = product.mainImage || ''
  }

  const imageForColor = (colorName: string): string => {
    if (!product) return ''
    
    const color = availableColors.value.find(c => c.name === colorName)
    if (color?.image_url) {
      return color.image_url
    }
    
    return product.mainImage || ''
  }

  const computeMaxQty = (): number => {
    return maxQty.value
  }

  const canAddToCart = (qty: number): boolean => {
    if (!product || qty < 1 || qty > maxQty.value) {
      return false
    }

    const hasColors = availableColors.value.length > 0
    const hasSizes = availableSizes.value.length > 0

    // If product has both colors and sizes, both must be selected
    if (hasColors && hasSizes) {
      return selectedColor.value !== null && selectedSize.value !== null && maxQty.value > 0
    }

    // If product has only colors, color must be selected
    if (hasColors) {
      return selectedColor.value !== null && maxQty.value > 0
    }

    // If product has only sizes, size must be selected
    if (hasSizes) {
      return selectedSize.value !== null && maxQty.value > 0
    }

    // No variants required, just check stock
    return maxQty.value > 0
  }

  const reset = () => {
    selectedColor.value = null
    selectedSize.value = null
    selectedVariantId.value = null
    maxQty.value = 0
    activeImageUrl.value = ''
  }

  // Watch for product changes to reset state and initialize
  watch(() => product, (newProduct) => {
    reset()
    if (newProduct) {
      maxQty.value = newProduct.stock_total || 0
      activeImageUrl.value = newProduct.mainImage || ''
    }
  }, { immediate: true })

  // Watch for selection changes to update variant and stock
  watch([selectedColor, selectedSize], () => {
    resolveVariantAndStock()
  })

  // Watch for color changes to update image
  watch(selectedColor, () => {
    updateActiveImage()
  })

  return {
    // State
    selectedColor,
    selectedSize,
    selectedVariantId,
    maxQty,
    activeImageUrl,

    // Methods
    selectColor,
    selectSize,
    imageForColor,
    computeMaxQty,
    canAddToCart,
    reset
  }
}
