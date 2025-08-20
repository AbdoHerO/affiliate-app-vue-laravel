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
    .filter(variant => {
      // Use catalog system for consistent identification
      const attributCode = variant.attribut?.code || variant.attribut_principal?.toLowerCase()
      return ['taille', 'size'].includes(attributCode) && variant.stock > 0
    })
    .map(variant => ({
      id: variant.id,
      value: variant.valeur,
      stock: variant.stock
    }))
})

const availableColors = computed(() => {
  if (!props.product?.variantes) return []
  return props.product.variantes
    .filter(variant => {
      // Use catalog system for consistent identification
      const attributCode = variant.attribut?.code || variant.attribut_principal?.toLowerCase()
      return ['couleur', 'color'].includes(attributCode) && variant.stock > 0
    })
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

const downloadImage = (url: string, filename: string) => {
  const link = document.createElement('a')
  link.href = url
  link.download = `${filename}.jpg`
  link.target = '_blank'
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
}

const downloadVideo = (url: string, filename: string) => {
  const link = document.createElement('a')
  link.href = url
  link.download = `${filename}.mp4`
  link.target = '_blank'
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
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
      <VContainer fluid class="pa-0">
        <!-- Product Images Section -->
        <div class="pa-4 border-b">
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

          <!-- Thumbnail Images with Download -->
          <div v-if="images.length > 1" class="product-drawer__thumbnails mb-4">
            <div class="d-flex gap-2 justify-center flex-wrap">
              <div
                v-for="(image, index) in images"
                :key="index"
                class="position-relative"
              >
                <VImg
                  :src="image.url"
                  :alt="`${product.titre} - Image ${index + 1}`"
                  width="60"
                  height="80"
                  cover
                  class="rounded cursor-pointer thumbnail"
                  :class="{ 'thumbnail--active': selectedImageIndex === index }"
                  @click="handleImageSelect(index)"
                />
                <VBtn
                  icon="tabler-download"
                  size="x-small"
                  variant="elevated"
                  color="primary"
                  class="position-absolute download-btn"
                  style="top: 2px; right: 2px;"
                  @click.stop="downloadImage(image.url, `${product.titre}_image_${index + 1}`)"
                />
              </div>
            </div>
          </div>

          <!-- Videos Section -->
          <div v-if="product.videos && product.videos.length" class="mb-4">
            <h4 class="text-subtitle-2 mb-2">فيديوهات المنتج</h4>
            <div class="d-flex gap-2 flex-wrap">
              <VCard
                v-for="(video, index) in product.videos"
                :key="index"
                variant="outlined"
                class="video-card"
                width="120"
              >
                <VCardText class="pa-2 text-center">
                  <VIcon icon="tabler-video" size="24" class="mb-1" />
                  <div class="text-caption">{{ video.titre || `Video ${index + 1}` }}</div>
                  <div class="d-flex gap-1 mt-2">
                    <VBtn
                      icon="tabler-eye"
                      size="x-small"
                      variant="outlined"
                      @click="openVideo(video.url)"
                    />
                    <VBtn
                      icon="tabler-download"
                      size="x-small"
                      variant="outlined"
                      @click="downloadVideo(video.url, video.titre || `${product.titre}_video_${index + 1}`)"
                    />
                  </div>
                </VCardText>
              </VCard>
            </div>
          </div>
        </div>
        <!-- Product Information Section -->
        <div class="pa-4 border-b">
          <h3 class="text-h6 mb-3">معلومات المنتج</h3>

          <VRow dense>
            <VCol cols="6">
              <div class="text-caption text-medium-emphasis">الفئة</div>
              <div class="text-body-2 font-weight-medium">{{ product.categorie?.nom || 'غير محدد' }}</div>
            </VCol>
            <VCol cols="6">
              <div class="text-caption text-medium-emphasis">المتجر</div>
              <div class="text-body-2 font-weight-medium">{{ product.boutique?.nom || 'غير محدد' }}</div>
            </VCol>
            <VCol cols="6">
              <div class="text-caption text-medium-emphasis">سعر الشراء</div>
              <div class="text-body-2 font-weight-medium">{{ product.prix_achat }} MAD</div>
            </VCol>
            <VCol cols="6">
              <div class="text-caption text-medium-emphasis">سعر البيع</div>
              <div class="text-body-2 font-weight-medium">{{ product.prix_vente }} MAD</div>
            </VCol>
            <VCol cols="6">
              <div class="text-caption text-medium-emphasis">عمولة الشريك</div>
              <div class="text-body-2 font-weight-medium text-success">{{ product.prix_affilie }} MAD</div>
            </VCol>
            <VCol cols="6">
              <div class="text-caption text-medium-emphasis">المخزون الإجمالي</div>
              <div class="text-body-2 font-weight-medium">{{ product.stock_total }}</div>
            </VCol>
            <VCol v-if="product.quantite_min" cols="6">
              <div class="text-caption text-medium-emphasis">الحد الأدنى للطلب</div>
              <div class="text-body-2 font-weight-medium">{{ product.quantite_min }}</div>
            </VCol>
            <VCol v-if="product.rating_value" cols="6">
              <div class="text-caption text-medium-emphasis">التقييم</div>
              <div class="d-flex align-center">
                <VRating
                  :model-value="product.rating_value"
                  readonly
                  density="compact"
                  size="small"
                  color="warning"
                />
                <span class="text-caption ms-1">({{ product.rating_value }}/{{ product.rating_max || 5 }})</span>
              </div>
            </VCol>
          </VRow>

          <!-- Product Description -->
          <div v-if="product.description" class="mt-4">
            <div class="text-caption text-medium-emphasis mb-1">الوصف</div>
            <div class="text-body-2">{{ product.description }}</div>
          </div>

          <!-- Admin Notes -->
          <div v-if="product.notes_admin" class="mt-4">
            <div class="text-caption text-medium-emphasis mb-1">ملاحظات الإدارة</div>
            <VAlert
              type="info"
              variant="tonal"
              density="compact"
              class="text-body-2"
            >
              {{ product.notes_admin }}
            </VAlert>
          </div>
        </div>

        <!-- Variants Table Section -->
        <div v-if="product.variantes && product.variantes.length" class="pa-4 border-b">
          <h3 class="text-h6 mb-3">جدول المتغيرات</h3>

          <!-- Size Variants Table -->
          <div v-if="availableSizes.length" class="mb-4">
            <h4 class="text-subtitle-2 mb-2">الأحجام المتوفرة</h4>
            <VTable density="compact">
              <thead>
                <tr>
                  <th>الحجم</th>
                  <th>المخزون</th>
                  <th>الحالة</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="size in availableSizes" :key="size.id">
                  <td class="font-weight-medium">{{ size.value }}</td>
                  <td>{{ size.stock }}</td>
                  <td>
                    <VChip
                      :color="size.stock > 0 ? 'success' : 'error'"
                      size="small"
                      variant="tonal"
                    >
                      {{ size.stock > 0 ? 'متوفر' : 'غير متوفر' }}
                    </VChip>
                  </td>
                </tr>
              </tbody>
            </VTable>
          </div>

          <!-- Color Variants Table -->
          <div v-if="availableColors.length" class="mb-4">
            <h4 class="text-subtitle-2 mb-2">الألوان المتوفرة</h4>
            <VTable density="compact">
              <thead>
                <tr>
                  <th>اللون</th>
                  <th>الصورة</th>
                  <th>المخزون</th>
                  <th>الحالة</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="color in availableColors" :key="color.id">
                  <td class="font-weight-medium">{{ color.value }}</td>
                  <td>
                    <VImg
                      v-if="color.image_url"
                      :src="color.image_url"
                      width="30"
                      height="30"
                      cover
                      class="rounded"
                    />
                    <span v-else class="text-caption text-medium-emphasis">لا توجد صورة</span>
                  </td>
                  <td>{{ color.stock }}</td>
                  <td>
                    <VChip
                      :color="color.stock > 0 ? 'success' : 'error'"
                      size="small"
                      variant="tonal"
                    >
                      {{ color.stock > 0 ? 'متوفر' : 'غير متوفر' }}
                    </VChip>
                  </td>
                </tr>
              </tbody>
            </VTable>
          </div>
        </div>

        <!-- Copywriting Section -->
        <div v-if="product.copywriting" class="pa-4 border-b">
          <h3 class="text-h6 mb-3">النص التسويقي</h3>
          <div class="copywriting-section pa-3 bg-grey-lighten-5 rounded">
            <div class="text-body-2 copywriting-content" v-html="formatCopywriting(product.copywriting)"></div>
          </div>
        </div>

        <!-- Variant Selection Section -->
        <div class="pa-4 border-b">
          <h3 class="text-h6 mb-3">اختيار المتغيرات</h3>

          <!-- Size Selection -->
          <div v-if="availableSizes.length" class="mb-3">
            <div class="text-body-2 font-weight-medium mb-2">الأحجام المتوفرة</div>
            <div class="d-flex gap-2 flex-wrap">
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
            <div class="text-body-2 font-weight-medium mb-2">الألوان المتوفرة</div>
            <div class="d-flex gap-2 flex-wrap">
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

        <!-- Order Section -->
        <div class="pa-4">
          <h3 class="text-h6 mb-3">إضافة إلى السلة</h3>

          <!-- Quantity Selection -->
          <div class="mb-4">
            <div class="text-body-2 font-weight-medium mb-2">الكمية</div>
            <div class="d-flex align-center gap-2">
              <VBtn
                icon="tabler-minus"
                size="small"
                variant="outlined"
                :disabled="quantity <= 1"
                @click="quantity--"
              />
              <VTextField
                v-model.number="quantity"
                type="number"
                variant="outlined"
                density="compact"
                style="width: 80px;"
                min="1"
                :max="maxQuantity"
                hide-details
              />
              <VBtn
                icon="tabler-plus"
                size="small"
                variant="outlined"
                :disabled="quantity >= maxQuantity"
                @click="quantity++"
              />
              <span class="text-caption text-medium-emphasis">الحد الأقصى: {{ maxQuantity }}</span>
            </div>
          </div>

          <!-- Price Summary -->
          <div class="mb-4 pa-3 bg-grey-lighten-5 rounded">
            <div class="d-flex justify-space-between mb-1">
              <span class="text-body-2">سعر الوحدة:</span>
              <span class="text-body-2 font-weight-medium">{{ product.prix_vente }} MAD</span>
            </div>
            <div class="d-flex justify-space-between mb-1">
              <span class="text-body-2">الكمية:</span>
              <span class="text-body-2 font-weight-medium">{{ quantity }}</span>
            </div>
            <VDivider class="my-2" />
            <div class="d-flex justify-space-between">
              <span class="text-body-1 font-weight-medium">المجموع:</span>
              <span class="text-body-1 font-weight-bold text-primary">{{ (product.prix_vente * quantity).toFixed(2) }} MAD</span>
            </div>
            <div class="d-flex justify-space-between mt-1">
              <span class="text-caption text-success">عمولتك:</span>
              <span class="text-caption font-weight-medium text-success">{{ (product.prix_affilie * quantity).toFixed(2) }} MAD</span>
            </div>
          </div>
        </div>
        <!-- Order Button -->
        <div class="pa-4 border-t">
          <VBtn
            color="primary"
            size="large"
            block
            :disabled="!canAddToCart"
            @click="handleAddToCart"
          >
            <VIcon icon="tabler-shopping-cart" class="me-2" />
            إضافة إلى السلة
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
      </VContainer>

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

.download-btn {
  opacity: 0;
  transition: opacity 0.2s ease;
}

.thumbnail:hover .download-btn {
  opacity: 1;
}

.video-card {
  transition: all 0.2s ease;
}

.video-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.border-b {
  border-bottom: 1px solid rgba(var(--v-border-color), 0.12);
}

.border-t {
  border-top: 1px solid rgba(var(--v-border-color), 0.12);
}

/* Table styling */
.v-table {
  background: transparent;
}

.v-table th {
  font-weight: 600;
  color: rgb(var(--v-theme-on-surface));
  background: rgba(var(--v-theme-surface-variant), 0.3);
}

.v-table td {
  border-bottom: 1px solid rgba(var(--v-border-color), 0.08);
}
</style>
