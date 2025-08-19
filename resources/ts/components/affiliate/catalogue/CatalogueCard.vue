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
  if (!props.product.variants || !props.product.variants.sizes) {
    return []
  }
  return props.product.variants.sizes.filter(size => size.stock > 0)
})

const availableColors = computed(() => {
  if (!props.product.variants || !props.product.variants.colors) {
    return []
  }
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
    elevation="2"
    @click="handleViewDetails"
  >
    <!-- Product Image -->
    <div class="catalogue-card__image-container">
      <VImg
        :src="imageError ? 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAwIiBoZWlnaHQ9IjMwMCIgdmlld0JveD0iMCAwIDMwMCAzMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIzMDAiIGhlaWdodD0iMzAwIiBmaWxsPSIjRjVGNUY1Ii8+CjxwYXRoIGQ9Ik0xNTAgMTAwQzE2MS4wNDYgMTAwIDE3MCA5MC45NTQzIDE3MCA4MEM1NyA2OS4wNDU3IDE0Ny45NTQgNjAgMTM2IDYwQzEyNC45NTQgNjAgMTE2IDY5LjA0NTcgMTE2IDgwQzExNiA5MC45NTQzIDEyNC45NTQgMTAwIDEzNiAxMDBIMTUwWiIgZmlsbD0iI0NDQ0NDQyIvPgo8cGF0aCBkPSJNMTgwIDEyMEgxMjBDMTE2LjY4NiAxMjAgMTE0IDEyMi42ODYgMTE0IDEyNlYyMDBDMTE0IDIwMy4zMTQgMTE2LjY4NiAyMDYgMTIwIDIwNkgxODBDMTgzLjMxNCAyMDYgMTg2IDIwMy4zMTQgMTg2IDIwMFYxMjZDMTg2IDEyMi42ODYgMTgzLjMxNCAxMjAgMTgwIDEyMFoiIGZpbGw9IiNDQ0NDQ0MiLz4KPC9zdmc+' : currentImage"
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
        <VIcon icon="tabler-message-circle" />
      </VBtn>
    </div>

    <!-- Card Content -->
    <VCardText class="catalogue-card__content pa-3">
      <!-- Size Variants -->
      <div v-if="availableSizes.length" class="mb-3">
        <div class="text-caption text-medium-emphasis mb-1">Tailles:</div>
        <VChip
          v-for="size in availableSizes.slice(0, 4)"
          :key="size.id"
          :color="selectedSizeId === size.id ? 'primary' : 'default'"
          :variant="selectedSizeId === size.id ? 'flat' : 'outlined'"
          size="small"
          class="me-1 mb-1"
          @click.stop="handleSizeSelect(size.id)"
        >
          {{ size.value }}
        </VChip>
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

      <!-- Color Variants -->
      <div v-if="availableColors.length" class="mb-3">
        <div class="text-caption text-medium-emphasis mb-1">Couleurs:</div>
        <VChip
          v-for="color in availableColors.slice(0, 3)"
          :key="color.id"
          :color="selectedColorId === color.id ? 'primary' : 'default'"
          :variant="selectedColorId === color.id ? 'flat' : 'outlined'"
          size="small"
          class="me-1"
          @click.stop="handleColorSelect(color.id)"
        >
          {{ color.value }}
        </VChip>
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

      <!-- Category -->
      <div class="text-caption text-medium-emphasis">
        {{ product.categorie?.nom || 'Sans catégorie' }}
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
