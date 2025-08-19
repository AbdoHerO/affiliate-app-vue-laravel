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
const currentImage = ref('')

// Computed
const isOpen = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
})

const images = computed(() => {
  if (!props.product?.images?.length) return []
  return props.product.images.sort((a, b) => a.ordre - b.ordre)
})

const availableSizes = computed(() => {
  if (!props.product?.variantes) return []
  return props.product.variantes
    .filter(variant =>
      ['taille', 'size'].includes(variant.attribut_principal.toLowerCase()) &&
      variant.stock > 0
    )
    .map(variant => ({
      id: variant.id,
      value: variant.valeur,
      stock: variant.stock
    }))
})

const availableColors = computed(() => {
  if (!props.product?.variantes) return []
  return props.product.variantes
    .filter(variant =>
      ['couleur', 'color'].includes(variant.attribut_principal.toLowerCase()) &&
      variant.stock > 0
    )
    .map(variant => ({
      id: variant.id,
      value: variant.valeur,
      stock: variant.stock,
      image_url: variant.image_url
    }))
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

// Methods
const handleImageSelect = (index: number) => {
  selectedImageIndex.value = index
  currentImage.value = images.value[index]?.url || ''
}

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

const closeDrawer = () => {
  isOpen.value = false
}

const openVideo = (url: string) => {
  window.open(url, '_blank')
}

// Format copywriting with line breaks and bold text
const formatCopywriting = (text: string): string => {
  if (!text) return ''

  return text
    // Convert line breaks to <br> tags
    .replace(/\n/g, '<br>')
    // Convert **text** to bold
    .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
    // Convert *text* to italic
    .replace(/\*(.*?)\*/g, '<em>$1</em>')
    // Preserve emojis and special characters
    .trim()
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
}, { immediate: true })

// Close drawer before route navigation
onBeforeRouteLeave(() => {
  if (isOpen.value) {
    closeDrawer()
  }
})
</script>

<template>
  <VNavigationDrawer
    v-model="isOpen"
    location="end"
    width="500"
    temporary
    class="product-drawer"
  >
    <template v-if="product">
      <!-- Header with Close Button -->
      <div class="product-drawer__header d-flex align-center justify-space-between pa-4 border-b">
        <div class="d-flex align-center">
          <span class="text-caption text-medium-emphasis me-2">REF:</span>
          <span class="text-caption font-weight-medium">{{ product.id.slice(-8) }}</span>
          <VDivider vertical class="mx-2" />
          <span class="text-caption">{{ product.titre }}</span>
        </div>
        <VBtn
          icon="tabler-x"
          variant="text"
          size="small"
          color="error"
          @click="closeDrawer"
        />
      </div>

      <!-- Stats Row -->
      <div class="d-flex justify-space-around pa-4 bg-grey-lighten-5">
        <div class="text-center">
          <div class="text-caption text-medium-emphasis">المبيعات</div>
          <div class="text-h6 font-weight-bold text-primary">90+</div>
        </div>
        <div class="text-center">
          <div class="text-caption text-medium-emphasis">المتجر السعودي</div>
          <div class="text-h6 font-weight-bold text-primary">220+</div>
        </div>
        <div class="text-center">
          <div class="text-caption text-medium-emphasis">المتجر</div>
          <div class="text-h6 font-weight-bold text-primary">130</div>
        </div>
        <div class="text-center">
          <div class="text-caption text-medium-emphasis">المتجرون الطلبات</div>
          <div class="text-h6 font-weight-bold text-primary">29</div>
        </div>
      </div>

      <!-- Main Content -->
      <div class="pa-4">
        <!-- Main Product Image -->
        <div class="product-drawer__main-image mb-4">
          <VImg
            :src="currentImage"
            :alt="product.titre"
            aspect-ratio="0.8"
            cover
            class="rounded-lg w-100"
            style="max-height: 400px;"
          />
        </div>

        <!-- Thumbnail Images -->
        <div v-if="images.length > 1" class="product-drawer__thumbnails mb-4">
          <div class="d-flex gap-2 justify-center">
            <VImg
              v-for="(image, index) in images.slice(0, 4)"
              :key="index"
              :src="image.url"
              :alt="`${product.titre} - Image ${index + 1}`"
              width="60"
              height="80"
              cover
              class="rounded cursor-pointer thumbnail"
              :class="{ 'thumbnail--active': selectedImageIndex === index }"
              @click="handleImageSelect(index)"
            />
          </div>
        </div>
        <!-- Variant Selection Section -->
        <div class="variant-selection mb-4">
          <!-- Size Selection -->
          <div v-if="availableSizes.length" class="mb-3">
            <div class="text-body-2 font-weight-medium mb-2 text-center">الأحجام المتوفرة</div>
            <div class="d-flex justify-center gap-2 flex-wrap">
              <VChip
                v-for="size in availableSizes"
                :key="size.id"
                :color="selectedSizeId === size.id ? 'primary' : 'default'"
                :variant="selectedSizeId === size.id ? 'flat' : 'outlined'"
                size="large"
                class="cursor-pointer"
                @click="handleSizeSelect(size.id)"
              >
                {{ size.value }}
              </VChip>
            </div>
          </div>

          <!-- Color Selection -->
          <div v-if="availableColors.length" class="mb-3">
            <div class="text-body-2 font-weight-medium mb-2 text-center">الألوان المتوفرة</div>
            <div class="d-flex justify-center gap-2 flex-wrap">
              <VChip
                v-for="color in availableColors"
                :key="color.id"
                :color="selectedColorId === color.id ? 'primary' : 'default'"
                :variant="selectedColorId === color.id ? 'flat' : 'outlined'"
                size="large"
                class="cursor-pointer"
                @click="handleColorSelect(color.id)"
              >
                {{ color.value }}
              </VChip>
            </div>
          </div>
        </div>

        <!-- Copywriting Section -->
        <div v-if="product.copywriting" class="copywriting-section mb-4 pa-3 bg-grey-lighten-5 rounded">
          <div class="text-body-2 copywriting-content" v-html="formatCopywriting(product.copywriting)"></div>
        </div>

        <!-- Rating Section -->
        <div v-if="product.rating_value" class="rating-section mb-4 text-center">
          <VRating
            :model-value="product.rating_value"
            readonly
            length="5"
            half-increments
            color="warning"
            size="large"
            class="mb-2"
          />
          <div class="text-caption text-medium-emphasis">
            {{ product.rating_value }} من 5 نجوم
          </div>
        </div>

        <!-- Videos Section -->
        <div v-if="product.videos && product.videos.length > 0" class="videos-section mb-4">
          <div class="text-body-2 font-weight-medium mb-2 text-center">فيديوهات المنتج</div>
          <div class="d-flex gap-2 justify-center flex-wrap">
            <VBtn
              v-for="(video, index) in product.videos.slice(0, 2)"
              :key="index"
              variant="outlined"
              color="primary"
              size="small"
              prepend-icon="tabler-play"
              @click="openVideo(video.url)"
            >
              فيديو {{ index + 1 }}
            </VBtn>
          </div>
        </div>

        <!-- Product Features -->
        <div class="product-features mb-4">
          <div class="d-flex align-center mb-2">
            <VIcon icon="tabler-check-circle" color="success" size="16" class="me-2" />
            <span class="text-body-2">الشحن مجاني لجميع المدن</span>
          </div>
          <div class="d-flex align-center mb-2">
            <VIcon icon="tabler-check-circle" color="success" size="16" class="me-2" />
            <span class="text-body-2">متوفر بجميع المقاسات</span>
          </div>
          <div class="d-flex align-center mb-2">
            <VIcon icon="tabler-check-circle" color="success" size="16" class="me-2" />
            <span class="text-body-2">جودة عالية ومضمونة</span>
          </div>
          <div class="d-flex align-center">
            <VIcon icon="tabler-currency-dirham" color="warning" size="16" class="me-2" />
            <span class="text-body-2 font-weight-medium">السعر: {{ product.prix_vente }} درهم</span>
          </div>
        </div>
        <!-- Order Button -->
        <div class="order-section pa-4 border-t">
          <VBtn
            color="primary"
            size="large"
            block
            :disabled="!canAddToCart"
            @click="handleAddToCart"
          >
            <VIcon icon="tabler-shopping-cart" class="me-2" />
            تحميل
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
              {{ selectedSizeId && selectedColorId ? 'تم اختيار المقاس واللون' : 'يرجى اختيار المقاس واللون' }}
            </div>
          </div>
        </div>
      </div>

    </template>

    <!-- Loading State -->
    <div v-else class="d-flex align-center justify-center pa-8">
      <VProgressCircular indeterminate color="primary" />
    </div>
  </VNavigationDrawer>
</template>


<style scoped>
.product-drawer {
  direction: rtl;
}

.product-drawer__header {
  position: sticky;
  top: 0;
  z-index: 2;
  background: rgb(var(--v-theme-surface));
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.product-drawer__main-image {
  position: relative;
  overflow: hidden;
}

.thumbnail {
  border: 2px solid transparent;
  transition: all 0.2s ease;
  cursor: pointer;
}

.thumbnail:hover {
  border-color: rgb(var(--v-theme-primary));
  transform: scale(1.05);
}

.thumbnail--active {
  border-color: rgb(var(--v-theme-primary));
  box-shadow: 0 0 0 2px rgba(var(--v-theme-primary), 0.2);
}

.variant-selection {
  background: rgba(var(--v-theme-surface), 0.5);
  border-radius: 12px;
  padding: 16px;
}

.copywriting-section {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.product-features {
  background: rgba(var(--v-theme-primary), 0.05);
  border-radius: 8px;
  padding: 12px;
}

.order-section {
  background: rgb(var(--v-theme-surface));
  position: sticky;
  bottom: 0;
  z-index: 1;
  box-shadow: 0 -2px 8px rgba(0, 0, 0, 0.1);
}

.cursor-pointer {
  cursor: pointer;
}

.copywriting-content {
  line-height: 1.6;
  word-wrap: break-word;
}

.copywriting-content strong {
  font-weight: 600;
  color: rgb(var(--v-theme-primary));
}

.copywriting-content em {
  font-style: italic;
  color: rgb(var(--v-theme-secondary));
}

</style>
