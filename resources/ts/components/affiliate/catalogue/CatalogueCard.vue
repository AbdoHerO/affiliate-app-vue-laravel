<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCatalogueStore } from '@/stores/affiliate/catalogue'
import { useVariantSelection } from '@/composables/useVariantSelection'
import type { NormalizedProduct } from '@/stores/affiliate/catalogue'

interface Props {
  product: NormalizedProduct
}

interface Emits {
  (e: 'open', productId: string): void
  (e: 'addToCart', data: { produit_id: string; variante_id?: string; qty: number }): void
    (e: 'variantChange', variantId: string): void
  (e: 'qtyChange', qty: number): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const { t } = useI18n()
const catalogueStore = useCatalogueStore()

// Local state
const isHovered = ref(false)
const selectedSizeId = ref<string>('')
const selectedColorId = ref<string>('')
const quantity = ref(1)

// Initialize quantity to minimum required
watch(() => props.product.quantite_min, (newMin) => {
  if (newMin && quantity.value < newMin) {
    quantity.value = newMin
  }
}, { immediate: true })
const currentImage = ref('')
const imageLoading = ref(true)
const imageError = ref(false)

// Computed
const stockBadgeColor = computed(() => {
  if (props.product.stock_total === 0) return 'error'
  if (props.product.stock_total < 10) return 'warning'
  return 'primary'
})

const stockBadgeText = computed(() => {
  if (props.product.stock_total === 0) {
    return t('catalogue.card.out_of_stock')
  }
  return props.product.stock_total.toString()
})

const profitText = computed(() => {
  const commission = Math.max(0, (props.product.prix_vente || 0) - (props.product.prix_achat || 0))
  return `+${commission.toFixed(2)} MAD`
})

// Color swatches from variants - normalize to consistent interface
const colorSwatches = computed(() => {
  const colors: Array<{ id: string; name: string; swatch?: string; stock: number }> = []
  
  console.log('🎨 [CatalogueCard] Computing color swatches for product:', props.product.id, {
    enhancedColors: props.product.colors,
    variantColors: props.product.variants?.colors
  })
  
  // Use the enhanced colors array from the store mapping
  if (props.product.colors && props.product.colors.length > 0) {
    props.product.colors.forEach(color => {
      // Find corresponding variant color for stock info
      const variantColor = props.product.variants?.colors?.find(c => c.value === color.name)
      if (variantColor && variantColor.stock > 0) {
        colors.push({
          id: variantColor.id,
          name: color.name,
          swatch: color.swatch || variantColor.color,
          stock: variantColor.stock
        })
      }
    })
  } else if (props.product.variants?.colors) {
    // Fallback to variants.colors if enhanced colors aren't available
    props.product.variants.colors.forEach(color => {
      if (color.stock > 0) {
        colors.push({
          id: color.id,
          name: color.value,
          swatch: color.color,
          stock: color.stock
        })
      }
    })
  }
  
  console.log('🎨 [CatalogueCard] Final color swatches:', colors)
  return colors
})

// Size chips from variants - normalize to consistent interface
const sizeChips = computed(() => {
  const sizes: Array<{ id: string; value: string; stock: number }> = []
  
  console.log('📏 [CatalogueCard] Computing size chips for product:', props.product.id, {
    enhancedSizes: props.product.sizes,
    variantSizes: props.product.variants?.sizes
  })
  
  // Use the enhanced sizes array from the store mapping
  if (props.product.sizes && props.product.sizes.length > 0) {
    props.product.sizes.forEach(size => {
      // Find corresponding variant size for stock info
      const variantSize = props.product.variants?.sizes?.find(s => s.value === size)
      if (variantSize && variantSize.stock > 0) {
        sizes.push({
          id: variantSize.id,
          value: size,
          stock: variantSize.stock
        })
      }
    })
  } else if (props.product.variants?.sizes) {
    // Fallback to variants.sizes if enhanced sizes aren't available
    props.product.variants.sizes.forEach(size => {
      if (size.stock > 0) {
        sizes.push(size)
      }
    })
  }
  
  console.log('📏 [CatalogueCard] Final size chips:', sizes)
  return sizes
})

// Initialize current image and reset loading states
watch(() => props.product.mainImage, (newImage) => {
  console.log('🖼️ [CatalogueCard] Image watch triggered:', {
    productId: props.product.id,
    newImage,
    hasColors: props.product.colors?.length,
    hasVariantColors: props.product.variants?.colors?.length,
    hasSizes: props.product.sizes?.length,
    hasVariantSizes: props.product.variants?.sizes?.length
  })

  if (newImage) {
    currentImage.value = newImage
    imageLoading.value = true
    imageError.value = false
  } else {
    // Use placeholder if no image
    currentImage.value = placeholderImage
    imageLoading.value = false
    imageError.value = false
  }
}, { immediate: true })



// Selected variant logic
const selectedVariant = computed(() => {
  if (selectedSizeId.value) {
    return sizeChips.value.find(s => s.id === selectedSizeId.value)
  }
  if (selectedColorId.value) {
    return colorSwatches.value.find(c => c.id === selectedColorId.value)
  }
  return null
})

const maxQuantity = computed(() => {
  let maxStock = props.product.stock_total

  // If we have a selected variant, use its specific stock
  if (selectedVariant.value) {
    maxStock = selectedVariant.value.stock
  }
  
  // Cap at 10 for UI purposes
  const finalMax = Math.min(maxStock, 10)
  console.log('📊 [CatalogueCard] Max quantity calculated:', {
    productId: props.product.id,
    totalStock: props.product.stock_total,
    selectedVariant: selectedVariant.value,
    variantStock: selectedVariant.value?.stock,
    finalMax
  })
  
  return finalMax
})

const canAddToCart = computed(() => {
  const hasStock = props.product.stock_total > 0 && quantity.value <= maxQuantity.value

  // If product has both sizes and colors, both must be selected
  if (sizeChips.value.length > 0 && colorSwatches.value.length > 0) {
    return hasStock && selectedSizeId.value && selectedColorId.value
  }

  // If product has only sizes, size must be selected
  if (sizeChips.value.length > 0) {
    return hasStock && selectedSizeId.value
  }

  // If product has only colors, color must be selected
  if (colorSwatches.value.length > 0) {
    return hasStock && selectedColorId.value
  }

  // If no variants, just check stock
  return hasStock
})

// Methods
const handleSizeSelect = (sizeId: string) => {
  selectedSizeId.value = sizeId
  emit('variantChange', sizeId)
}

const handleColorSelect = (colorId: string) => {
  selectedColorId.value = colorId
  console.log('🎨 [CatalogueCard] Color selected:', colorId)

  // Update image if color has specific image
  const color = colorSwatches.value.find(c => c.id === colorId)
  if (color) {
    console.log('🎨 [CatalogueCard] Found color:', color)
    
    // First try to find the color variant with image
    const colorVariant = props.product.variants?.colors?.find(c => c.id === colorId)
    if (colorVariant?.image_url) {
      console.log('🖼️ [CatalogueCard] Using variant image:', colorVariant.image_url)
      currentImage.value = colorVariant.image_url
      imageLoading.value = true
      imageError.value = false
    } else {
      // Use enhanced colors image if available
      const enhancedColor = props.product.colors?.find(c => c.name === color.name)
      if (enhancedColor?.image_url) {
        console.log('🖼️ [CatalogueCard] Using enhanced color image:', enhancedColor.image_url)
        currentImage.value = enhancedColor.image_url
        imageLoading.value = true
        imageError.value = false
      } else {
        console.log('🖼️ [CatalogueCard] No variant image, using main image')
        currentImage.value = props.product.mainImage
      }
    }
  }

  emit('variantChange', colorId)
}

const handleQuantityChange = (delta: number) => {
  const newQty = quantity.value + delta
  if (newQty >= 1 && newQty <= maxQuantity.value) {
    quantity.value = newQty
    emit('qtyChange', newQty)
  }
}

const handleAddToCart = () => {
  if (!canAddToCart.value) {
    console.log('⚠️ [CatalogueCard] Cannot add to cart:', {
      hasStock: props.product.stock_total > 0,
      quantityValid: quantity.value <= maxQuantity.value,
      sizeRequired: sizeChips.value.length > 0,
      colorRequired: colorSwatches.value.length > 0,
      sizeSelected: selectedSizeId.value,
      colorSelected: selectedColorId.value
    })
    return
  }

  // Find the variant ID based on selected size and color
  let variantId = undefined
  
  if (selectedSizeId.value && selectedColorId.value) {
    // For combined variants, prefer size variant if both are selected
    variantId = selectedSizeId.value
  } else if (selectedSizeId.value) {
    variantId = selectedSizeId.value
  } else if (selectedColorId.value) {
    variantId = selectedColorId.value
  }

  console.log('🛒 [CatalogueCard] Adding to cart:', {
    produit_id: props.product.id,
    variante_id: variantId,
    qty: quantity.value,
    selectedVariant: selectedVariant.value
  })

  emit('addToCart', {
    produit_id: props.product.id,
    variante_id: variantId,
    qty: quantity.value
  })
}

const handleViewDetails = () => {
  emit('open', props.product.id)
}

// Reset selections when product changes
watch(() => props.product.id, () => {
  selectedSizeId.value = ''
  selectedColorId.value = ''
  quantity.value = props.product.quantite_min || 1
  currentImage.value = props.product.mainImage
})

// Image loading handlers
const handleImageLoad = () => {
  imageLoading.value = false
  imageError.value = false
}

const placeholderImage = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAwIiBoZWlnaHQ9IjMwMCIgdmlld0JveD0iMCAwIDMwMCAzMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIzMDAiIGhlaWdodD0iMzAwIiBmaWxsPSIjRjVGNUY1Ii8+CjxwYXRoIGQ9Ik0xNTAgMTAwQzE2MS4wNDYgMTAwIDE3MCA5MC45NTQzIDE3MCA4MEM1NyA2OS4wNDU3IDE0Ny45NTQgNjAgMTM2IDYwQzEyNC45NTQgNjAgMTE2IDY5LjA0NTcgMTE2IDgwQzExNiA5MC45NTQzIDEyNC45NTQgMTAwIDEzNiAxMDBIMTUwWiIgZmlsbD0iI0NDQ0NDQyIvPgo8cGF0aCBkPSJNMTgwIDEyMEgxMjBDMTE2LjY4NiAxMjAgMTE0IDEyMi42ODYgMTE0IDEyNlYyMDBDMTE0IDIwMy4zMTQgMTE2LjY4NiAyMDYgMTIwIDIwNkgxODBDMTgzLjMxNCAyMDYgMTg2IDIwMy4zMTQgMTg2IDIwMFYxMjZDMTg2IDEyMi42ODYgMTgzLjMxNCAxMjAgMTgwIDEyMFoiIGZpbGw9IiNDQ0NDQ0MiLz4KPC9zdmc+'

const handleImageError = () => {
  imageLoading.value = false
  imageError.value = true
  console.log('❌ [CatalogueCard] Image failed to load:', currentImage.value)
  // Use fallback placeholder only if not already using it
  if (currentImage.value !== placeholderImage) {
    currentImage.value = placeholderImage
  }
}
</script>

<template>
  <VCard
    class="catalogue-card"
    elevation="2"
  >
    <!-- Product Image -->
    <div class="catalogue-card__image-container">
      <VImg
        :src="currentImage || placeholderImage"
        :alt="product.titre"
        aspect-ratio="1.2"
        cover
        class="catalogue-card__image"
        @load="handleImageLoad"
        @error="handleImageError"
      >
        <template #placeholder>
          <div class="d-flex align-center justify-center fill-height bg-grey-lighten-4">
            <VProgressCircular 
              v-if="imageLoading && !imageError" 
              indeterminate 
              color="primary" 
              size="40"
            />
            <VIcon 
              v-else-if="imageError"
              icon="tabler-photo-off" 
              size="40" 
              color="grey" 
            />
          </div>
        </template>
      </VImg>

      <!-- Stock Badge (Top Left) -->
      <VChip
        :color="stockBadgeColor"
        size="small"
        variant="elevated"
        class="catalogue-card__stock-badge"
      >
        {{ stockBadgeText }}
      </VChip>

      <!-- Eye Button (Top Right) -->
      <VBtn
        icon
        size="small"
        variant="elevated"
        color="primary"
        class="catalogue-card__eye-btn"
        @click.stop="handleViewDetails"
      >
        <VIcon icon="tabler-eye" />
      </VBtn>
    </div>

    <!-- Card Content -->
    <VCardText class="catalogue-card__content pa-3">
      <!-- Color Swatches (Row 1) -->
      <div v-if="colorSwatches.length" class="mb-2">
        <div class="d-flex align-center gap-1 flex-wrap">
          <VChip
            v-for="(color, index) in colorSwatches.slice(0, 3)"
            :key="color.id || index"
            :color="selectedColorId === color.id ? 'primary' : 'default'"
            :variant="selectedColorId === color.id ? 'flat' : 'outlined'"
            size="small"
            class="color-chip cursor-pointer"
            @click.stop="handleColorSelect(color.id)"
          >
            <VIcon
              v-if="color.swatch"
              :style="{ color: color.swatch }"
              icon="tabler-circle-filled"
              start
              size="14"
            />
            <span class="text-caption">{{ color.name }}</span>
          </VChip>
          <VTooltip
            v-if="colorSwatches.length > 3"
            location="top"
          >
            <template #activator="{ props: tooltipProps }">
              <VChip
                v-bind="tooltipProps"
                size="small"
                variant="outlined"
                class="text-caption"
              >
                +{{ colorSwatches.length - 3 }}
              </VChip>
            </template>
            <span>{{ colorSwatches.slice(3).map(c => c.name).join(', ') }}</span>
          </VTooltip>
        </div>
      </div>

      <!-- Size Chips (Row 2) -->
      <div v-if="sizeChips.length" class="mb-3">
        <div class="d-flex align-center gap-1 flex-wrap">
          <VChip
            v-for="size in sizeChips.slice(0, 5)"
            :key="size.id"
            :color="selectedSizeId === size.id ? 'primary' : 'default'"
            :variant="selectedSizeId === size.id ? 'flat' : 'outlined'"
            size="small"
            class="size-chip"
            @click.stop="handleSizeSelect(size.id)"
          >
            {{ size.value }}
          </VChip>
        </div>
      </div>

      <!-- Selection Status (for products with both size and color) -->
      <div
        v-if="sizeChips.length > 0 && colorSwatches.length > 0"
        class="mb-2"
      >
        <div class="text-caption d-flex align-center">
          <VIcon
            :icon="selectedSizeId && selectedColorId ? 'tabler-check-circle' : 'tabler-circle'"
            :color="selectedSizeId && selectedColorId ? 'success' : 'default'"
            size="14"
            class="me-1"
          />
          <span :class="selectedSizeId && selectedColorId ? 'text-success' : 'text-medium-emphasis'">
            {{ selectedSizeId && selectedColorId ? 'Variantes sélectionnées' : 'Sélectionnez taille et couleur' }}
          </span>
        </div>
      </div>

      <!-- Quantity and Add to Cart Row -->
      <div class="d-flex align-center justify-space-between mb-3">
        <!-- Quantity Selector -->
        <div class="d-flex align-center">
          <VBtn
            icon
            size="x-small"
            variant="outlined"
            :disabled="quantity <= 1"
            @click.stop="handleQuantityChange(-1)"
          >
            <VIcon icon="tabler-minus" size="14" />
          </VBtn>
          <span class="mx-2 text-body-2 font-weight-medium">{{ quantity }}</span>
          <VBtn
            icon
            size="x-small"
            variant="outlined"
            :disabled="quantity >= maxQuantity"
            @click.stop="handleQuantityChange(1)"
          >
            <VIcon icon="tabler-plus" size="14" />
          </VBtn>
          
          <!-- Max quantity indicator -->
          <VTooltip v-if="maxQuantity < 10" location="top">
            <template #activator="{ props: tooltipProps }">
              <VChip
                v-bind="tooltipProps"
                size="x-small"
                color="warning"
                variant="outlined"
                class="ml-2"
              >
                Max: {{ maxQuantity }}
              </VChip>
            </template>
            <span>Stock disponible: {{ maxQuantity }} unité{{ maxQuantity > 1 ? 's' : '' }}</span>
          </VTooltip>
        </div>

        <!-- Add to Cart Button -->
        <VBtn
          color="primary"
          size="small"
          :disabled="!canAddToCart"
          @click.stop="handleAddToCart"
        >
          <VIcon icon="tabler-shopping-cart" size="16" />
        </VBtn>
      </div>



      <!-- Pricing Row -->
      <div class="d-flex align-center justify-space-between mb-2">
        <div class="text-body-2 text-medium-emphasis">
          {{ t('catalogue.buy') }}: <span class="font-weight-medium">{{ product.prix_achat }} درهم</span>
        </div>
        <div class="text-body-2 text-medium-emphasis">
          {{ t('catalogue.sell') }}: <span class="font-weight-medium">{{ product.prix_vente }} درهم</span>
        </div>
      </div>

      <!-- Profit -->
      <div class="text-primary font-weight-bold mb-2">
        {{ profitText }} {{ t('catalogue.profit') }}
      </div>

      <!-- Minimum Quantity Info -->
      <div v-if="product.quantite_min && product.quantite_min > 1" class="text-caption text-warning mb-2">
        <VIcon icon="tabler-info-circle" size="14" class="me-1" />
        Quantité min: {{ product.quantite_min }}
      </div>

      <!-- Product Name -->
      <h6 class="text-body-1 font-weight-medium catalogue-card__title mb-1">
        {{ product.titre }}
      </h6>

      <!-- Category and Rating -->
      <div class="d-flex align-center justify-space-between mb-2">
        <div class="text-caption text-medium-emphasis">
          {{ product.categorie?.nom || 'Sans catégorie' }}
        </div>
        <div v-if="product.rating_value" class="d-flex align-center">
          <VIcon icon="tabler-star-filled" size="14" color="warning" class="me-1" />
          <span class="text-caption font-weight-medium">{{ product.rating_value.toFixed(1) }}</span>
        </div>
      </div>
    </VCardText>
  </VCard>
</template>

<style scoped>
.catalogue-card {
  position: relative;
  transition: all 0.2s ease;
  height: 100%;
  border-radius: 12px;
  overflow: hidden;
  cursor: pointer;
}

.catalogue-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
}

.catalogue-card__image-container {
  position: relative;
  overflow: hidden;
}

.catalogue-card__image {
  transition: transform 0.3s ease;
}

.catalogue-card:hover .catalogue-card__image {
  transform: scale(1.05);
}

.catalogue-card__stock-badge {
  position: absolute;
  top: 8px;
  left: 8px;
  z-index: 2;
  font-weight: 600;
}

.catalogue-card__eye-btn {
  position: absolute;
  top: 8px;
  right: 8px;
  z-index: 2;
}

.color-chip {
  max-width: 80px;
  font-size: 0.7rem;
}

.color-chip .v-icon {
  margin-right: 2px;
}

.size-chip {
  min-width: 32px;
  font-size: 0.75rem;
}

.catalogue-card__content {
  background: white;
}

.catalogue-card__title {
  display: -webkit-box;
  -webkit-line-clamp: 1;
  -webkit-box-orient: vertical;
  line-clamp: 1;
  overflow: hidden;
  line-height: 1.3;
}

/* Responsive adjustments */
@media (max-width: 768px) {
  .catalogue-card__content {
    padding: 8px !important;
  }

  .catalogue-card__title {
    font-size: 0.875rem;
  }
}
</style>
