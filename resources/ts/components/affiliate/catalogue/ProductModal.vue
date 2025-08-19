<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { onBeforeRouteLeave } from 'vue-router'
import { useI18n } from 'vue-i18n'
import type { CatalogueProduct } from '@/stores/affiliate/catalogue'

interface Props {
  product: CatalogueProduct | null
  modelValue: boolean
}

interface Emits {
  (e: 'update:modelValue', value: boolean): void
  (e: 'addToCart', data: { 
    produit_id: string
    size_variant_id?: string
    color_variant_id?: string
    qty: number
    variants: { size?: string; color?: string }
  }): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const { t } = useI18n()

// Local state
const selectedImageIndex = ref(0)
const selectedSizeId = ref<string>('')
const selectedColorId = ref<string>('')
const quantity = ref(1)
const activeTab = ref('overview')
const currentImage = ref('')
const imageLoading = ref(false)
const imageError = ref(false)

// Computed
const isOpen = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
})

const images = computed(() => {
  if (!props.product?.images?.length) return []
  return props.product.images.sort((a, b) => a.ordre - b.ordre)
})

const videos = computed(() => {
  return props.product?.videos || []
})

const availableSizes = computed(() => {
  if (!props.product?.variants?.sizes) return []
  return props.product.variants.sizes.filter(size => size.stock > 0)
})

const availableColors = computed(() => {
  if (!props.product?.variants?.colors) return []
  return props.product.variants.colors.filter(color => color.stock > 0)
})

const maxQuantity = computed(() => {
  return Math.min(props.product?.stock_total || 0, 99)
})

const canAddToCart = computed(() => {
  if (!props.product || props.product.stock_total <= 0) return false
  
  // If product has both sizes and colors, both must be selected
  if (availableSizes.value.length > 0 && availableColors.value.length > 0) {
    return selectedSizeId.value && selectedColorId.value
  }
  
  // If product has only sizes, size must be selected
  if (availableSizes.value.length > 0) {
    return selectedSizeId.value
  }
  
  // If product has only colors, color must be selected
  if (availableColors.value.length > 0) {
    return selectedColorId.value
  }
  
  // If no variants, can add to cart
  return true
})

const stockStatus = computed(() => {
  if (!props.product) return { color: 'error', text: 'Indisponible' }
  
  const stock = props.product.stock_total
  if (stock === 0) return { color: 'error', text: 'Rupture de stock' }
  if (stock < 5) return { color: 'warning', text: `Stock faible (${stock} restants)` }
  return { color: 'success', text: `En stock (${stock} disponibles)` }
})

const profitAmount = computed(() => {
  if (!props.product) return 0
  return props.product.prix_vente - props.product.prix_achat
})

const profitPercentage = computed(() => {
  if (!props.product || props.product.prix_achat === 0) return 0
  return ((profitAmount.value / props.product.prix_achat) * 100)
})

// Methods
const handleSizeSelect = (sizeId: string) => {
  selectedSizeId.value = sizeId
}

const handleColorSelect = (colorId: string) => {
  selectedColorId.value = colorId
  
  // Update image if color has specific image
  const color = availableColors.value.find(c => c.id === colorId)
  if (color?.image_url) {
    currentImage.value = color.image_url
    selectedImageIndex.value = -1 // Indicate it's a variant image
  }
}

const handleImageSelect = (index: number) => {
  selectedImageIndex.value = index
  currentImage.value = images.value[index]?.url || ''
}

const handleQuantityChange = (delta: number) => {
  const newQty = quantity.value + delta
  if (newQty >= 1 && newQty <= maxQuantity.value) {
    quantity.value = newQty
  }
}

const handleAddToCart = () => {
  if (!canAddToCart.value || !props.product) return
  
  emit('addToCart', {
    produit_id: props.product.id,
    size_variant_id: selectedSizeId.value || undefined,
    color_variant_id: selectedColorId.value || undefined,
    qty: quantity.value,
    variants: {
      size: selectedSizeId.value,
      color: selectedColorId.value
    }
  })
}

const downloadImage = (url: string, filename: string) => {
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  link.target = '_blank'
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
}

const downloadVideo = (url: string, filename: string) => {
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  link.target = '_blank'
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
}

const closeModal = () => {
  isOpen.value = false
}

// Initialize current image when product changes
watch(() => props.product, (newProduct) => {
  if (newProduct && images.value.length > 0) {
    currentImage.value = images.value[0].url
    selectedImageIndex.value = 0
  }
  selectedSizeId.value = ''
  selectedColorId.value = ''
  quantity.value = 1
  activeTab.value = 'overview'
}, { immediate: true })

// Close modal before route navigation
onBeforeRouteLeave(() => {
  if (isOpen.value) {
    closeModal()
  }
})
</script>

<template>
  <VDialog
    v-model="isOpen"
    max-width="1200"
    persistent
    class="product-modal"
  >
    <VCard v-if="product" class="product-modal__card">
      <!-- Header -->
      <VCardTitle class="product-modal__header d-flex align-center justify-space-between pa-6 border-b">
        <div>
          <h4 class="text-h4 font-weight-bold">{{ product.titre }}</h4>
          <div class="d-flex align-center mt-2">
            <VChip 
              v-if="product.categorie" 
              color="primary" 
              variant="tonal" 
              size="small"
              class="me-3"
            >
              {{ product.categorie.nom }}
            </VChip>
            <div v-if="product.rating_value" class="d-flex align-center">
              <VIcon icon="tabler-star-filled" size="16" color="warning" class="me-1" />
              <span class="text-body-1 font-weight-medium">{{ product.rating_value.toFixed(1) }}</span>
            </div>
          </div>
        </div>
        <VBtn
          icon="tabler-x"
          variant="text"
          size="large"
          @click="closeModal"
        />
      </VCardTitle>

      <!-- Content -->
      <VCardText class="pa-0">
        <VContainer fluid class="pa-6">
          <VRow>
            <!-- Left Column - Images & Videos -->
            <VCol cols="12" md="6">
              <!-- Main Image Display -->
              <div class="product-modal__main-image mb-4">
                <VImg
                  :src="currentImage"
                  :alt="product.titre"
                  aspect-ratio="1"
                  cover
                  class="rounded-lg"
                  @load="imageLoading = false"
                  @error="imageError = true"
                >
                  <template #placeholder>
                    <div class="d-flex align-center justify-center fill-height">
                      <VProgressCircular indeterminate color="primary" />
                    </div>
                  </template>
                </VImg>
                
                <!-- Download Button for Current Image -->
                <VBtn
                  v-if="currentImage"
                  icon="tabler-download"
                  size="small"
                  color="primary"
                  variant="elevated"
                  class="product-modal__download-btn"
                  @click="downloadImage(currentImage, `${product.titre}-image.jpg`)"
                />
              </div>

              <!-- Image Thumbnails -->
              <div v-if="images.length > 1" class="product-modal__thumbnails mb-4">
                <div class="d-flex gap-2 flex-wrap">
                  <VImg
                    v-for="(image, index) in images"
                    :key="index"
                    :src="image.url"
                    :alt="`${product.titre} - Image ${index + 1}`"
                    width="80"
                    height="80"
                    cover
                    class="rounded cursor-pointer thumbnail"
                    :class="{ 'thumbnail--active': selectedImageIndex === index }"
                    @click="handleImageSelect(index)"
                  />
                </div>
              </div>

              <!-- Videos Section -->
              <div v-if="videos.length" class="product-modal__videos">
                <h6 class="text-h6 mb-3">Vidéos du produit</h6>
                <div class="d-flex flex-column gap-3">
                  <div
                    v-for="(video, index) in videos"
                    :key="index"
                    class="video-item"
                  >
                    <video
                      v-if="video.url"
                      :src="video.url"
                      controls
                      class="w-100 rounded"
                      style="max-height: 300px;"
                    />
                    <div class="d-flex align-center justify-space-between mt-2">
                      <span class="text-body-2">{{ video.titre || `Vidéo ${index + 1}` }}</span>
                      <VBtn
                        icon="tabler-download"
                        size="small"
                        variant="outlined"
                        @click="downloadVideo(video.url, `${product.titre}-video-${index + 1}.mp4`)"
                      />
                    </div>
                  </div>
                </div>
              </div>
            </VCol>

            <!-- Right Column - Product Details -->
            <VCol cols="12" md="6">
              <!-- Stock Status -->
              <VAlert
                :color="stockStatus.color"
                variant="tonal"
                class="mb-4"
              >
                {{ stockStatus.text }}
              </VAlert>

              <!-- Pricing -->
              <div class="product-modal__pricing mb-4">
                <div class="d-flex align-center justify-space-between mb-2">
                  <span class="text-body-1">Prix d'achat:</span>
                  <span class="text-h6 font-weight-bold">{{ product.prix_achat }} درهم</span>
                </div>
                <div class="d-flex align-center justify-space-between mb-2">
                  <span class="text-body-1">Prix de vente:</span>
                  <span class="text-h6 font-weight-bold text-primary">{{ product.prix_vente }} درهم</span>
                </div>
                <div class="d-flex align-center justify-space-between">
                  <span class="text-body-1">Votre profit:</span>
                  <div class="text-end">
                    <div class="text-h6 font-weight-bold text-success">{{ profitAmount }} درهم</div>
                    <div class="text-caption text-success">({{ profitPercentage.toFixed(1) }}%)</div>
                  </div>
                </div>
              </div>

              <VDivider class="my-4" />

              <!-- Variants Selection -->
              <div class="product-modal__variants mb-4">
                <!-- Size Variants -->
                <div v-if="availableSizes.length" class="mb-4">
                  <h6 class="text-h6 mb-2">Tailles disponibles</h6>
                  <div class="d-flex gap-2 flex-wrap">
                    <VChip
                      v-for="size in availableSizes"
                      :key="size.id"
                      :color="selectedSizeId === size.id ? 'primary' : 'default'"
                      :variant="selectedSizeId === size.id ? 'flat' : 'outlined'"
                      class="cursor-pointer"
                      @click="handleSizeSelect(size.id)"
                    >
                      {{ size.value }}
                      <VChip
                        size="x-small"
                        color="success"
                        variant="flat"
                        class="ml-2"
                      >
                        {{ size.stock }}
                      </VChip>
                    </VChip>
                  </div>
                </div>

                <!-- Color Variants -->
                <div v-if="availableColors.length" class="mb-4">
                  <h6 class="text-h6 mb-2">Couleurs disponibles</h6>
                  <div class="d-flex gap-2 flex-wrap">
                    <VChip
                      v-for="color in availableColors"
                      :key="color.id"
                      :color="selectedColorId === color.id ? 'primary' : 'default'"
                      :variant="selectedColorId === color.id ? 'flat' : 'outlined'"
                      class="cursor-pointer"
                      @click="handleColorSelect(color.id)"
                    >
                      {{ color.value }}
                      <VChip
                        size="x-small"
                        color="success"
                        variant="flat"
                        class="ml-2"
                      >
                        {{ color.stock }}
                      </VChip>
                    </VChip>
                  </div>
                </div>
              </div>

              <!-- Quantity and Add to Cart -->
              <div class="product-modal__actions">
                <div class="d-flex align-center gap-4 mb-4">
                  <div class="d-flex align-center">
                    <span class="text-body-1 me-3">Quantité:</span>
                    <VBtn
                      icon="tabler-minus"
                      size="small"
                      variant="outlined"
                      :disabled="quantity <= 1"
                      @click="handleQuantityChange(-1)"
                    />
                    <span class="mx-3 text-h6 font-weight-medium">{{ quantity }}</span>
                    <VBtn
                      icon="tabler-plus"
                      size="small"
                      variant="outlined"
                      :disabled="quantity >= maxQuantity"
                      @click="handleQuantityChange(1)"
                    />
                  </div>
                </div>

                <VBtn
                  color="primary"
                  size="large"
                  block
                  :disabled="!canAddToCart"
                  @click="handleAddToCart"
                >
                  <VIcon icon="tabler-shopping-cart" class="me-2" />
                  Ajouter au panier
                </VBtn>

                <!-- Selection Status -->
                <div v-if="availableSizes.length > 0 && availableColors.length > 0" class="mt-3">
                  <div class="text-caption text-center">
                    <VIcon 
                      :icon="selectedSizeId && selectedColorId ? 'tabler-check-circle' : 'tabler-alert-circle'" 
                      :color="selectedSizeId && selectedColorId ? 'success' : 'warning'"
                      size="14" 
                      class="me-1"
                    />
                    {{ selectedSizeId && selectedColorId ? 'Variantes sélectionnées' : 'Veuillez sélectionner une taille et une couleur' }}
                  </div>
                </div>
              </div>

              <VDivider class="my-4" />

              <!-- Description -->
              <div v-if="product.description" class="product-modal__description">
                <h6 class="text-h6 mb-2">Description</h6>
                <div class="text-body-2" v-html="product.description"></div>
              </div>
            </VCol>
          </VRow>
        </VContainer>
      </VCardText>
    </VCard>

    <!-- Loading State -->
    <VCard v-else class="d-flex align-center justify-center" style="min-height: 400px;">
      <VProgressCircular indeterminate color="primary" size="64" />
    </VCard>
  </VDialog>
</template>

<style scoped>
.product-modal__card {
  max-height: 90vh;
  overflow-y: auto;
}

.product-modal__header {
  position: sticky;
  top: 0;
  z-index: 2;
  background: rgb(var(--v-theme-surface));
}

.product-modal__main-image {
  position: relative;
}

.product-modal__download-btn {
  position: absolute;
  top: 12px;
  right: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}

.thumbnail {
  border: 2px solid transparent;
  transition: all 0.2s ease;
}

.thumbnail:hover {
  border-color: rgb(var(--v-theme-primary));
  transform: scale(1.05);
}

.thumbnail--active {
  border-color: rgb(var(--v-theme-primary));
  box-shadow: 0 0 0 2px rgba(var(--v-theme-primary), 0.2);
}

.video-item {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
  padding: 12px;
}

.cursor-pointer {
  cursor: pointer;
}
</style>
