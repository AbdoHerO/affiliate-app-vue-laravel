<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
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

// Local state
const isHovered = ref(false)
const selectedSizeId = ref<string>('')
const selectedColorId = ref<string>('')
const quantity = ref(1)
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
  return `+${props.product.prix_affilie} MAD`
})

const availableSizes = computed(() => {
  return props.product.variants.sizes.filter(size => size.stock > 0)
})

const availableColors = computed(() => {
  return props.product.variants.colors.filter(color => color.stock > 0)
})

const selectedVariant = computed(() => {
  if (selectedSizeId.value) {
    return props.product.variants.sizes.find(s => s.id === selectedSizeId.value)
  }
  if (selectedColorId.value) {
    return props.product.variants.colors.find(c => c.id === selectedColorId.value)
  }
  return null
})

const maxQuantity = computed(() => {
  if (selectedVariant.value) {
    return Math.min(selectedVariant.value.stock, 10)
  }
  return Math.min(props.product.stock_total, 10)
})

const canAddToCart = computed(() => {
  return props.product.stock_total > 0 && quantity.value <= maxQuantity.value
})

// Initialize current image
watch(() => props.product.mainImage, (newImage) => {
  currentImage.value = newImage
  imageLoading.value = true
  imageError.value = false
}, { immediate: true })

// Methods
const handleSizeSelect = (sizeId: string) => {
  selectedSizeId.value = sizeId
  selectedColorId.value = '' // Clear color selection when size changes
  emit('variantChange', sizeId)
}

const handleColorSelect = (colorId: string) => {
  selectedColorId.value = colorId
  selectedSizeId.value = '' // Clear size selection when color changes
  
  // Update image if color has specific image
  const color = props.product.variants.colors.find(c => c.id === colorId)
  if (color?.image_url) {
    currentImage.value = color.image_url
  } else {
    currentImage.value = props.product.mainImage
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
  if (!canAddToCart.value) return
  
  const variantId = selectedSizeId.value || selectedColorId.value || undefined
  
  emit('addToCart', {
    produit_id: props.product.id,
    variante_id: variantId,
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
  selectedSizeId.value = ''
  selectedColorId.value = ''
  quantity.value = 1
  currentImage.value = props.product.mainImage
})
</script>

<template>
  <VCard
    class="catalogue-card"
    :class="{ 'catalogue-card--hovered': isHovered }"
    @mouseenter="isHovered = true"
    @mouseleave="isHovered = false"
  >
    <!-- Product Image -->
    <div class="catalogue-card__image-container">
      <VImg
        :src="imageError ? 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAwIiBoZWlnaHQ9IjMwMCIgdmlld0JveD0iMCAwIDMwMCAzMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIzMDAiIGhlaWdodD0iMzAwIiBmaWxsPSIjRjVGNUY1Ii8+CjxwYXRoIGQ9Ik0xNTAgMTAwQzE2MS4wNDYgMTAwIDE3MCA5MC45NTQzIDE3MCA4MEM1NyA2OS4wNDU3IDE0Ny45NTQgNjAgMTM2IDYwQzEyNC45NTQgNjAgMTE2IDY5LjA0NTcgMTE2IDgwQzExNiA5MC45NTQzIDEyNC45NTQgMTAwIDEzNiAxMDBIMTUwWiIgZmlsbD0iI0NDQ0NDQyIvPgo8cGF0aCBkPSJNMTgwIDEyMEgxMjBDMTE2LjY4NiAxMjAgMTE0IDEyMi42ODYgMTE0IDEyNlYyMDBDMTE0IDIwMy4zMTQgMTE2LjY4NiAyMDYgMTIwIDIwNkgxODBDMTgzLjMxNCAyMDYgMTg2IDIwMy4zMTQgMTg2IDIwMFYxMjZDMTg2IDEyMi42ODYgMTgzLjMxNCAxMjAgMTgwIDEyMFoiIGZpbGw9IiNDQ0NDQ0MiLz4KPC9zdmc+' : currentImage"
        :alt="product.titre"
        aspect-ratio="1"
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
        size="x-small"
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
        color="white"
        class="catalogue-card__eye-btn"
        @click="handleViewDetails"
      >
        <VIcon icon="tabler-eye" />
        <VTooltip activator="parent" location="bottom">
          {{ t('catalogue.view_details') }}
        </VTooltip>
      </VBtn>
      
      <!-- Hover Overlay -->
      <Transition name="fade">
        <div
          v-if="isHovered"
          class="catalogue-card__overlay"
        >
          <!-- Top Section: Sizes and Quantity/Add to Cart -->
          <div class="catalogue-card__overlay-top">
            <!-- Sizes (Left) -->
            <div v-if="availableSizes.length" class="catalogue-card__sizes">
              <VChip
                v-for="size in availableSizes"
                :key="size.id"
                :color="selectedSizeId === size.id ? 'primary' : 'default'"
                :variant="selectedSizeId === size.id ? 'elevated' : 'tonal'"
                size="small"
                class="me-1 mb-1"
                @click="handleSizeSelect(size.id)"
              >
                {{ size.value }}
              </VChip>
            </div>
            
            <!-- Quantity and Add to Cart (Right) -->
            <div class="catalogue-card__actions">
              <!-- Quantity Selector -->
              <div class="catalogue-card__qty-selector">
                <VBtn
                  icon
                  size="x-small"
                  variant="outlined"
                  :disabled="quantity <= 1"
                  @click="handleQuantityChange(-1)"
                >
                  <VIcon icon="tabler-minus" size="16" />
                </VBtn>
                <span class="catalogue-card__qty-display">{{ quantity }}</span>
                <VBtn
                  icon
                  size="x-small"
                  variant="outlined"
                  :disabled="quantity >= maxQuantity"
                  @click="handleQuantityChange(1)"
                >
                  <VIcon icon="tabler-plus" size="16" />
                </VBtn>
              </div>
              
              <!-- Add to Cart Button -->
              <VBtn
                color="primary"
                size="small"
                :disabled="!canAddToCart"
                @click="handleAddToCart"
              >
                <VIcon start icon="tabler-shopping-cart" />
                {{ t('catalogue.add_to_cart') }}
              </VBtn>
            </div>
          </div>
          
          <!-- Bottom Section: Pricing and Colors -->
          <div class="catalogue-card__overlay-bottom">
            <!-- Pricing (Left) -->
            <div class="catalogue-card__pricing">
              <div class="text-caption text-medium-emphasis">
                {{ t('catalogue.buy') }}: {{ product.prix_achat }} MAD
              </div>
              <div class="text-caption text-medium-emphasis">
                {{ t('catalogue.sell') }}: {{ product.prix_vente }} MAD
              </div>
            </div>
            
            <!-- Colors (Right) -->
            <div v-if="availableColors.length" class="catalogue-card__colors">
              <VBtn
                v-for="color in availableColors"
                :key="color.id"
                icon
                size="small"
                :variant="selectedColorId === color.id ? 'elevated' : 'outlined'"
                :color="selectedColorId === color.id ? 'primary' : 'default'"
                class="me-1"
                @click="handleColorSelect(color.id)"
              >
                <div
                  v-if="color.color"
                  class="catalogue-card__color-swatch"
                  :style="{ backgroundColor: color.color }"
                />
                <span v-else class="text-caption">{{ color.value.charAt(0) }}</span>
              </VBtn>
            </div>
          </div>
        </div>
      </Transition>
    </div>
    
    <!-- Card Footer -->
    <VCardText class="catalogue-card__footer">
      <!-- Product Name -->
      <h6 class="text-h6 catalogue-card__title">
        {{ product.titre }}
      </h6>
      
      <!-- Profit -->
      <div class="text-primary font-weight-medium mb-1">
        {{ profitText }}
      </div>
      
      <!-- Category -->
      <div class="text-caption text-medium-emphasis mb-2">
        {{ product.categorie?.nom || 'Sans catégorie' }}
      </div>
      
      <!-- Rating -->
      <VRating
        v-if="product.rating_value > 0"
        :model-value="product.rating_value"
        readonly
        length="5"
        half-increments
        size="small"
        density="compact"
      />
    </VCardText>
  </VCard>
</template>

<style scoped>
.catalogue-card {
  position: relative;
  transition: all 0.3s ease;
  height: 100%;
}

.catalogue-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

.catalogue-card__image-container {
  position: relative;
  overflow: hidden;
}

.catalogue-card__stock-badge {
  position: absolute;
  top: 8px;
  left: 8px;
  z-index: 2;
}

.catalogue-card__eye-btn {
  position: absolute;
  top: 8px;
  right: 8px;
  z-index: 2;
}

.catalogue-card__overlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.8);
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  padding: 16px;
  z-index: 1;
}

.catalogue-card__overlay-top {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
}

.catalogue-card__overlay-bottom {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
}

.catalogue-card__sizes {
  flex: 1;
  margin-right: 8px;
}

.catalogue-card__actions {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 8px;
}

.catalogue-card__qty-selector {
  display: flex;
  align-items: center;
  gap: 8px;
  background: rgba(255, 255, 255, 0.9);
  border-radius: 4px;
  padding: 4px 8px;
}

.catalogue-card__qty-display {
  min-width: 20px;
  text-align: center;
  font-weight: 500;
}

.catalogue-card__pricing {
  color: white;
}

.catalogue-card__colors {
  display: flex;
  align-items: center;
}

.catalogue-card__color-swatch {
  width: 16px;
  height: 16px;
  border-radius: 50%;
  border: 1px solid rgba(255, 255, 255, 0.3);
}

.catalogue-card__footer {
  padding: 16px;
}

.catalogue-card__title {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  line-height: 1.2;
  margin-bottom: 8px;
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
