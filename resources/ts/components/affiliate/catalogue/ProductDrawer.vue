<script setup lang="ts">
import { ref, computed, watch, nextTick } from 'vue'
import { onBeforeRouteLeave } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useCatalogueStore } from '@/stores/affiliate/catalogue'
import { useNotifications } from '@/composables/useNotifications'

interface Props {
  modelValue: boolean
  productId: string | null
}

interface Emits {
  (e: 'update:modelValue', value: boolean): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const { t } = useI18n()
const store = useCatalogueStore()
const { showSuccess, showError } = useNotifications()

// Local state
const selectedImageIndex = ref(0)
const mainImageUrl = ref('')

// Computed
const isOpen = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
})

const product = computed(() => store.drawerProduct)
const isLoading = computed(() => store.detailLoading)
const isAddingToCart = computed(() => store.addingToCart)

const currentImage = computed(() => {
  if (mainImageUrl.value) return mainImageUrl.value
  if (product.value?.gallery.main) return product.value.gallery.main
  return ''
})

const maxQuantity = computed(() => {
  if (!product.value) return 1

  // If both size and color are selected, find specific variant stock
  if (store.selectedSize && store.selectedColor) {
    const variant = product.value.matrix.find(m =>
      m.size === store.selectedSize && m.color === store.selectedColor
    )
    return Math.min(variant?.stock || 0, 10)
  }

  // If only size selected
  if (store.selectedSize) {
    const sizeVariant = product.value.sizes.find(s => s.value === store.selectedSize)
    return Math.min(sizeVariant?.stock || 0, 10)
  }

  // If only color selected
  if (store.selectedColor) {
    const colorVariant = product.value.colors.find(c => c.name === store.selectedColor)
    return Math.min(colorVariant?.stock || 0, 10)
  }

  return Math.min(product.value.stock_total, 10)
})

const canAddToCart = computed(() => {
  if (!product.value || store.selectedQty < 1 || store.selectedQty > maxQuantity.value) {
    return false
  }

  const hasSizes = product.value.sizes.length > 0
  const hasColors = product.value.colors.length > 0

  // If product has both sizes and colors, both must be selected
  if (hasSizes && hasColors) {
    return store.selectedSize && store.selectedColor
  }

  // If product has only sizes, size must be selected
  if (hasSizes) {
    return store.selectedSize
  }

  // If product has only colors, color must be selected
  if (hasColors) {
    return store.selectedColor
  }

  // No variants required
  return true
})

const totalPrice = computed(() => {
  if (!product.value) return 0
  return product.value.prix_vente * store.selectedQty
})

const totalProfit = computed(() => {
  if (!product.value) return 0
  return product.value.prix_affilie * store.selectedQty
})

// Size-only stock table (aggregated by size across all colors)
const sizesTable = computed(() => {
  if (!product.value?.matrix) return []

  const sizeStockMap = new Map<string, number>()

  // Aggregate stock by size across all colors
  product.value.matrix.forEach(item => {
    if (item.size) {
      const currentQty = sizeStockMap.get(item.size) || 0
      sizeStockMap.set(item.size, currentQty + item.stock)
    }
  })

  // Convert to array format
  return Array.from(sizeStockMap.entries()).map(([size, qty]) => ({
    size,
    qty
  }))
})

// Methods
const handleImageSelect = (index: number) => {
  selectedImageIndex.value = index
  if (product.value?.gallery.thumbnails[index]) {
    mainImageUrl.value = product.value.gallery.thumbnails[index].url
  }
}

const handleColorSelect = (colorName: string) => {
  store.selectColor(colorName)

  // Update main image if color has specific image
  const color = product.value?.colors.find(c => c.name === colorName)
  if (color?.image_url) {
    mainImageUrl.value = color.image_url
  }
}

const handleSizeSelect = (sizeValue: string) => {
  store.selectSize(sizeValue)
}

const downloadFile = (url: string, filename: string) => {
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  link.target = '_blank'
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
}

const copyToClipboard = async (text: string) => {
  try {
    await navigator.clipboard.writeText(text)
    showSuccess('Texte copié dans le presse-papiers')
  } catch (err) {
    showError('Erreur lors de la copie')
  }
}

const handleAddToCart = async () => {
  if (!product.value || !canAddToCart.value) return

  // Find the variant ID based on selections
  let variantId: string | undefined

  if (store.selectedSize && store.selectedColor) {
    const variant = product.value.matrix.find(m =>
      m.size === store.selectedSize && m.color === store.selectedColor
    )
    variantId = variant?.variant_id
  } else if (store.selectedSize) {
    const sizeVariant = product.value.sizes.find(s => s.value === store.selectedSize)
    variantId = sizeVariant?.id
  } else if (store.selectedColor) {
    const colorVariant = product.value.colors.find(c => c.name === store.selectedColor)
    variantId = colorVariant?.id
  }

  const success = await store.addToCartFromDrawer({
    produit_id: product.value.id,
    variante_id: variantId,
    qty: store.selectedQty
  })

  if (success) {
    // Reset selections
    store.selectedQty = 1
  }
}

// Watchers
watch(() => props.productId, async (newId) => {
  if (newId && props.modelValue) {
    await store.fetchOneForDrawer(newId)
    // Reset image selection
    selectedImageIndex.value = 0
    mainImageUrl.value = ''
  }
}, { immediate: true })

watch(() => props.modelValue, (isOpen) => {
  if (isOpen && props.productId) {
    store.fetchOneForDrawer(props.productId)
  } else if (!isOpen) {
    // Reset state when closing
    store.selectedColor = ''
    store.selectedSize = ''
    store.selectedQty = 1
    selectedImageIndex.value = 0
    mainImageUrl.value = ''
  }
})

// Route guard to close drawer
onBeforeRouteLeave(() => {
  if (isOpen.value) {
    isOpen.value = false
  }
})

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

// Utility methods

</script>

<template>
  <VNavigationDrawer
    v-model="isOpen"
    location="end"
    width="1000"
    temporary
    class="product-drawer"
  >
    <!-- Loading State -->
    <template v-if="isLoading">
      <div class="d-flex justify-center align-center" style="height: 100vh;">
        <VProgressCircular
          indeterminate
          color="primary"
          size="64"
        />
      </div>
    </template>

    <!-- Product Content -->
    <template v-else-if="product">
      <!-- Header (Sticky) -->
      <div class="product-drawer__header pa-4 border-b bg-surface position-sticky" style="top: 0; z-index: 10;">
        <div class="d-flex justify-space-between align-center mb-3">
          <div class="flex-grow-1">
            <h2 class="text-h5 font-weight-bold mb-1">{{ product.titre }}</h2>
            <div class="text-body-2 text-medium-emphasis">{{ product.categorie?.nom }}</div>
          </div>
          <VBtn
            icon="tabler-x"
            variant="text"
            size="small"
            @click="isOpen = false"
          />
        </div>

        <!-- KPIs Row -->
        <div class="d-flex gap-3 flex-wrap">
          <VChip
            color="primary"
            variant="tonal"
            size="small"
          >
            <VIcon icon="tabler-package" start />
            {{ t('catalogue.kpis.stock') }}: {{ product.stock_total }}
          </VChip>
          <VChip
            color="success"
            variant="tonal"
            size="small"
          >
            <VIcon icon="tabler-coins" start />
            {{ t('catalogue.kpis.profit') }}: +{{ product.prix_affilie }} MAD
          </VChip>
          <VChip
            color="info"
            variant="tonal"
            size="small"
          >
            {{ t('catalogue.kpis.buy') }}: {{ product.prix_achat }} MAD
          </VChip>
          <VChip
            color="warning"
            variant="tonal"
            size="small"
          >
            {{ t('catalogue.kpis.sell') }}: {{ product.prix_vente }} MAD
          </VChip>
        </div>
      </div>

      <!-- Scrollable Content -->
      <div class="drawer-content" style="height: calc(100vh - 140px); overflow-y: auto;">

        <!-- Zone 1: Media + Variant Selectors -->
        <div class="pa-6 border-b">
          <VRow>
            <!-- Left: Main Image + Thumbnails -->
            <VCol cols="6">
              <!-- Main Image -->
              <div class="main-image-container mb-4">
                <VImg
                  :src="currentImage"
                  :alt="product.titre"
                  aspect-ratio="1"
                  cover
                  class="rounded-lg main-image"
                  style="max-height: 350px;"
                />
              </div>

              <!-- Thumbnail Rail (Vertical) -->
              <div v-if="product.gallery.thumbnails.length > 1" class="thumbnails-rail">
                <div class="d-flex gap-2 flex-wrap justify-center">
                  <div
                    v-for="(image, index) in product.gallery.thumbnails"
                    :key="index"
                    class="position-relative thumbnail-container"
                  >
                    <VImg
                      :src="image.url"
                      :alt="`${product.titre} - Image ${index + 1}`"
                      width="60"
                      height="60"
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
                      class="download-btn"
                      @click.stop="downloadFile(image.url, `${product.titre}_image_${index + 1}.jpg`)"
                    />
                  </div>
                </div>
              </div>
            </VCol>

            <!-- Right: Variant Selectors + Quantity + CTA -->
            <VCol cols="6">
              <!-- Colors -->
              <div v-if="product.colors.length" class="mb-4">
                <h4 class="text-subtitle-1 mb-3">{{ t('catalogue.variants.color') }}</h4>
                <div class="d-flex gap-2 flex-wrap">
                  <VChip
                    v-for="color in product.colors"
                    :key="color.id"
                    :color="store.selectedColor === color.name ? 'primary' : 'default'"
                    :variant="store.selectedColor === color.name ? 'flat' : 'outlined'"
                    size="large"
                    class="cursor-pointer color-chip"
                    @click="handleColorSelect(color.name)"
                  >
                    <VIcon
                      v-if="color.swatch"
                      :style="{ color: color.swatch }"
                      icon="tabler-circle-filled"
                      start
                    />
                    {{ color.name }}
                  </VChip>
                </div>
              </div>

              <!-- Sizes -->
              <div v-if="product.sizes.length" class="mb-4">
                <h4 class="text-subtitle-1 mb-3">{{ t('catalogue.variants.size') }}</h4>
                <div class="d-flex gap-2 flex-wrap">
                  <VChip
                    v-for="size in product.sizes"
                    :key="size.id"
                    :color="store.selectedSize === size.value ? 'primary' : 'default'"
                    :variant="store.selectedSize === size.value ? 'flat' : 'outlined'"
                    size="large"
                    class="cursor-pointer"
                    @click="handleSizeSelect(size.value)"
                  >
                    {{ size.value }}
                  </VChip>
                </div>
              </div>

              <!-- Quantity Selector -->
              <div class="mb-4">
                <h4 class="text-subtitle-1 mb-3">{{ t('catalogue.detail.quantity') }}</h4>
                <div class="d-flex align-center gap-3">
                  <VBtn
                    icon="tabler-minus"
                    size="small"
                    variant="outlined"
                    :disabled="store.selectedQty <= 1"
                    @click="store.selectedQty--"
                  />
                  <VTextField
                    v-model.number="store.selectedQty"
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
                    :disabled="store.selectedQty >= maxQuantity"
                    @click="store.selectedQty++"
                  />
                  <span class="text-caption text-medium-emphasis">Max: {{ maxQuantity }}</span>
                </div>
              </div>

              <!-- Primary CTA -->
              <VBtn
                color="primary"
                size="large"
                block
                :disabled="!canAddToCart"
                :loading="isAddingToCart"
                @click="handleAddToCart"
              >
                <VIcon icon="tabler-shopping-cart" start />
                {{ t('catalogue.actions.add_to_cart') }}
              </VBtn>
            </VCol>
          </VRow>
        </div>

        <!-- Zone 2: Info & Copy -->
        <div class="pa-6 border-b">
          <!-- Description -->
          <div v-if="product.description" class="mb-6">
            <h3 class="text-h6 mb-3">{{ t('catalogue.detail.description') }}</h3>
            <div class="text-body-1" v-html="formatCopywriting(product.description)"></div>
          </div>

          <!-- Admin Note -->
          <div v-if="product.notes_admin" class="mb-6">
            <h3 class="text-h6 mb-3">{{ t('catalogue.admin_note') }}</h3>
            <VAlert
              type="info"
              variant="tonal"
              class="text-body-1"
            >
              <VIcon icon="tabler-info-circle" start />
              {{ product.notes_admin }}
            </VAlert>
          </div>

          <!-- Copywriting -->
          <div v-if="product.copywriting" class="mb-6">
            <div class="d-flex justify-space-between align-center mb-3">
              <h3 class="text-h6">{{ t('catalogue.copywriting') }}</h3>
              <VBtn
                variant="outlined"
                size="small"
                prepend-icon="tabler-copy"
                @click="copyToClipboard(product.copywriting)"
              >
                Copier
              </VBtn>
            </div>
            <VCard variant="outlined" class="pa-4">
              <div class="text-body-1 copywriting-content" v-html="formatCopywriting(product.copywriting)"></div>
            </VCard>
          </div>
        </div>

        <!-- Zone 3: Assets & Variants Tables -->
        <div class="pa-6">
          <!-- Size Stock Table (Simplified) -->
          <div v-if="sizesTable.length" class="mb-6">
            <h3 class="text-h6 mb-3">{{ t('drawer.table.size') }} - Stock disponible</h3>
            <VTable density="compact">
              <thead>
                <tr>
                  <th>{{ t('drawer.table.size') }}</th>
                  <th>{{ t('drawer.table.qty') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(item, index) in sizesTable" :key="index">
                  <td class="font-weight-medium">{{ item.size }}</td>
                  <td>
                    <VChip
                      :color="item.qty > 0 ? 'success' : 'error'"
                      size="small"
                      variant="tonal"
                    >
                      {{ item.qty }}
                    </VChip>
                  </td>
                </tr>
              </tbody>
            </VTable>
          </div>

          <!-- Images Grid -->
          <div v-if="product.images.length" class="mb-6">
            <h3 class="text-h6 mb-3">{{ t('catalogue.assets.images') }}</h3>
            <div class="d-flex gap-3 flex-wrap">
              <div
                v-for="(image, index) in product.images"
                :key="index"
                class="position-relative image-asset"
              >
                <VImg
                  :src="image.url"
                  width="100"
                  height="100"
                  cover
                  class="rounded"
                />
                <VTooltip
                  activator="parent"
                  location="top"
                >
                  {{ t('catalogue.actions.download') }}
                </VTooltip>
                <VBtn
                  icon="tabler-download"
                  size="small"
                  variant="elevated"
                  color="primary"
                  class="download-overlay"
                  @click="downloadFile(image.url, `${product.titre}_image_${index + 1}.jpg`)"
                />
              </div>
            </div>
          </div>

          <!-- Videos List -->
          <div v-if="product.videos.length" class="mb-6">
            <h3 class="text-h6 mb-3">{{ t('catalogue.assets.videos') }}</h3>
            <div class="d-flex gap-3 flex-wrap">
              <VCard
                v-for="(video, index) in product.videos"
                :key="index"
                variant="outlined"
                width="200"
                class="video-asset"
              >
                <VCardText class="pa-3">
                  <div class="d-flex align-center mb-2">
                    <VIcon icon="tabler-video" class="me-2" />
                    <span class="text-body-2 font-weight-medium">{{ video.title || `Video ${index + 1}` }}</span>
                  </div>
                  <div class="d-flex gap-2">
                    <VBtn
                      size="small"
                      variant="outlined"
                      prepend-icon="tabler-eye"
                      @click="() => window.open(video.url, '_blank')"
                    >
                      Voir
                    </VBtn>
                    <VBtn
                      size="small"
                      variant="outlined"
                      prepend-icon="tabler-download"
                      @click="downloadFile(video.url, `${product.titre}_video_${index + 1}.mp4`)"
                    >
                      {{ t('catalogue.actions.download') }}
                    </VBtn>
                  </div>
                </VCardText>
              </VCard>
            </div>
          </div>
        </div>
      </div>

      <!-- Footer (Sticky) -->
      <div class="drawer-footer pa-4 border-t bg-surface position-sticky" style="bottom: 0; z-index: 10;">
        <!-- Quick Recap -->
        <div class="d-flex justify-space-between align-center mb-3">
          <div class="text-body-2">
            <span v-if="store.selectedColor" class="me-2">{{ store.selectedColor }}</span>
            <span v-if="store.selectedSize" class="me-2">{{ store.selectedSize }}</span>
            <span>Qty: {{ store.selectedQty }}</span>
          </div>
          <div class="text-h6 font-weight-bold text-success">
            +{{ totalProfit.toFixed(2) }} MAD
          </div>
        </div>

        <!-- CTA -->
        <VBtn
          color="primary"
          size="large"
          block
          :disabled="!canAddToCart"
          :loading="isAddingToCart"
          @click="handleAddToCart"
        >
          <VIcon icon="tabler-shopping-cart" start />
          {{ t('catalogue.actions.add_to_cart') }} - {{ totalPrice.toFixed(2) }} MAD
        </VBtn>
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
  direction: ltr;
}

.product-drawer__header {
  backdrop-filter: blur(8px);
  border-bottom: 1px solid rgba(var(--v-border-color), 0.12);
}

.drawer-content {
  scrollbar-width: thin;
  scrollbar-color: rgba(var(--v-theme-primary), 0.3) transparent;
}

.drawer-content::-webkit-scrollbar {
  width: 6px;
}

.drawer-content::-webkit-scrollbar-track {
  background: transparent;
}

.drawer-content::-webkit-scrollbar-thumb {
  background: rgba(var(--v-theme-primary), 0.3);
  border-radius: 3px;
}

.drawer-content::-webkit-scrollbar-thumb:hover {
  background: rgba(var(--v-theme-primary), 0.5);
}

.main-image {
  transition: all 0.3s ease;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.thumbnail-container {
  position: relative;
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
  box-shadow: 0 0 0 1px rgb(var(--v-theme-primary));
}

.download-btn {
  position: absolute;
  top: 2px;
  right: 2px;
  opacity: 0;
  transition: opacity 0.2s ease;
}

.thumbnail-container:hover .download-btn {
  opacity: 1;
}

.color-chip {
  transition: all 0.2s ease;
}

.color-chip:hover {
  transform: translateY(-1px);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}

.image-asset {
  position: relative;
  overflow: hidden;
  border-radius: 8px;
}

.download-overlay {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  opacity: 0;
  transition: opacity 0.2s ease;
}

.image-asset:hover .download-overlay {
  opacity: 1;
}

.video-asset {
  transition: all 0.2s ease;
}

.video-asset:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.copywriting-content {
  line-height: 1.6;
}

.copywriting-content strong {
  font-weight: 600;
  color: rgb(var(--v-theme-primary));
}

.copywriting-content em {
  font-style: italic;
  color: rgb(var(--v-theme-secondary));
}

.border-b {
  border-bottom: 1px solid rgba(var(--v-border-color), 0.12);
}

.border-t {
  border-top: 1px solid rgba(var(--v-border-color), 0.12);
}

.drawer-footer {
  backdrop-filter: blur(8px);
  box-shadow: 0 -2px 8px rgba(0, 0, 0, 0.1);
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
