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

// Use shared variant selection logic
const variantSelection = useVariantSelection(computed(() => props.product))

// Local state
const isHovered = ref(false)
const quantity = ref(1)
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

// Enhanced color swatches from normalized data
const colorSwatches = computed(() => {
  return props.product.colors || []
})

// Enhanced size chips from normalized data
const sizeChips = computed(() => {
  return props.product.sizes || []
})

// Display image - combines variant selection and color selection logic
const displayImage = computed(() => {
  // Priority 1: Use variant selection active image if available
  if (variantSelection.activeImageUrl) {
    return variantSelection.activeImageUrl
  }

  // Priority 2: Use color-specific image if color is selected
  if (selectedColor.value) {
    const colorImage = catalogueStore.imageForColor(props.product, selectedColor.value)
    if (colorImage) {
      return colorImage
    }
  }

  // Priority 3: Fallback to main product image
  return props.product.mainImage
})

const maxQuantity = computed(() => {
  return Math.min(variantSelection.maxQty, 10)
})

const canAddToCart = computed(() => {
  return variantSelection.canAddToCart(quantity.value)
})

// Methods - Updated to use variant selection composable
const handleColorSwatchSelect = (colorName: string) => {
  variantSelection.selectColor(colorName)
}

const handleSizeChipSelect = (sizeName: string) => {
  variantSelection.selectSize(sizeName)
}

const handleQuantityChange = (delta: number) => {
  const newQty = quantity.value + delta
  if (newQty >= 1 && newQty <= maxQuantity.value) {
    quantity.value = newQty
    emit('qtyChange', newQty)
  }
}

const handleAddToCart = () => {
  if (!canAddToCart.value) return

  emit('addToCart', {
    produit_id: props.product.id,
    variante_id: variantSelection.selectedVariantId || undefined,
    qty: quantity.value
  })
}

const handleViewDetails = () => {
  emit('open', props.product.id)
}

const handleImageLoad = () => {
  imageLoading.value = false
}

const handleImageError = () => {
  imageLoading.value = false
  imageError.value = true
}

// Reset selections when product changes
watch(() => props.product.id, () => {
  variantSelection.reset()
  quantity.value = 1
  imageError.value = false
  imageLoading.value = true
})
</script>

<template>
  <VCard
    class="catalogue-card"
    elevation="2"
  >
    <!-- Product Image -->
    <div class="catalogue-card__image-container">
      <VImg
        :src="imageError ? 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAwIiBoZWlnaHQ9IjMwMCIgdmlld0JveD0iMCAwIDMwMCAzMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIzMDAiIGhlaWdodD0iMzAwIiBmaWxsPSIjRjVGNUY1Ii8+CjxwYXRoIGQ9Ik0xNTAgMTAwQzE2MS4wNDYgMTAwIDE3MCA5MC45NTQzIDE3MCA4MEM1NyA2OS4wNDU3IDE0Ny45NTQgNjAgMTM2IDYwQzEyNC45NTQgNjAgMTE2IDY5LjA0NTcgMTE2IDgwQzExNiA5MC45NTQzIDEyNC45NTQgMTAwIDEzNiAxMDBIMTUwWiIgZmlsbD0iI0NDQ0NDQyIvPgo8cGF0aCBkPSJNMTgwIDEyMEgxMjBDMTE2LjY4NiAxMjAgMTE0IDEyMi42ODYgMTE0IDEyNlYyMDBDMTE0IDIwMy4zMTQgMTE2LjY4NiAyMDYgMTIwIDIwNkgxODBDMTgzLjMxNCAyMDYgMTg2IDIwMy4zMTQgMTg2IDIwMFYxMjZDMTg2IDEyMi42ODYgMTgzLjMxNCAxMjAgMTgwIDEyMFoiIGZpbGw9IiNDQ0NDQ0MiLz4KPC9zdmc+' : displayImage"
        :alt="product.titre"
        aspect-ratio="1.2"
        cover
        class="catalogue-card__image"
        @load="handleImageLoad"
        @error="handleImageError"
      >
        <template #placeholder>
          <div class="d-flex align-center justify-center fill-height">
            <VProgressCircular indeterminate color="primary" />
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
            v-for="color in colorSwatches.slice(0, 3)"
            :key="color.name"
            :color="variantSelection.selectedColor === color.name ? 'primary' : 'default'"
            :variant="variantSelection.selectedColor === color.name ? 'flat' : 'outlined'"
            size="small"
            class="color-chip cursor-pointer"
            @click.stop="handleColorSwatchSelect(color.name)"
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
            :key="size"
            :color="variantSelection.selectedSize === size ? 'primary' : 'default'"
            :variant="variantSelection.selectedSize === size ? 'flat' : 'outlined'"
            size="small"
            class="size-chip"
            @click.stop="handleSizeChipSelect(size)"
          >
            {{ size }}
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
            :icon="variantSelection.selectedSize && variantSelection.selectedColor ? 'tabler-check-circle' : 'tabler-circle'"
            :color="variantSelection.selectedSize && variantSelection.selectedColor ? 'success' : 'default'"
            size="14"
            class="me-1"
          />
          <span :class="variantSelection.selectedSize && variantSelection.selectedColor ? 'text-success' : 'text-medium-emphasis'">
            {{ variantSelection.selectedSize && variantSelection.selectedColor ? 'Variantes sélectionnées' : 'Sélectionnez taille et couleur' }}
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
