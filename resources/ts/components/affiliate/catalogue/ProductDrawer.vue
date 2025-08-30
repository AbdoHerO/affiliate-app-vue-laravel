<script setup lang="ts">
import { ref, computed, watch, nextTick } from 'vue'
import { onBeforeRouteLeave } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useCatalogueStore } from '@/stores/affiliate/catalogue'
import { useAffiliateCartStore } from '@/stores/affiliate/cart'
import { useNotifications } from '@/composables/useNotifications'
import { useAffiliateCartUi } from '@/composables/useAffiliateCartUi'
import ImageZoomModal from '@/components/common/ImageZoomModal.vue'

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
const cartStore = useAffiliateCartStore()
const { showSuccess, showError } = useNotifications()
const { openCartDrawer } = useAffiliateCartUi()

// Local state
const selectedImageIndex = ref(0)
const activeImageUrl = ref('')

// Image zoom modal state
const isZoomModalOpen = ref(false)
const zoomModalImages = computed(() => {
  if (!product.value?.gallery.thumbnails) return []
  return product.value.gallery.thumbnails.map(img => ({
    url: img.url,
    alt: `${product.value?.titre} - Image`
  }))
})

// Custom pricing
const customSellPrice = ref<number | null>(null)
const selectedColor = ref('')
const selectedSize = ref('')

// Command type
const selectedCommandType = ref<'order_sample' | 'exchange'>('order_sample')

// Computed
const isOpen = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
})

const product = computed(() => store.drawerProduct)
const isLoading = computed(() => store.detailLoading)
const isAddingToCart = computed(() => store.addingToCart)

const currentImage = computed(() => {
  // activeImageUrl is the source of truth
  if (activeImageUrl.value) return activeImageUrl.value
  if (product.value?.gallery.main) return product.value.gallery.main
  return ''
})

const maxQuantity = computed(() => {
  return store.maxQty
})

const canAddToCart = computed(() => {
  const minQty = product.value?.quantite_min || 1
  if (!product.value || store.selectedQty < minQty || store.selectedQty > maxQuantity.value) {
    return false
  }

  // Check price validation
  if (!isPriceValid.value) {
    return false
  }

  const hasSizes = store.sizes.length > 0
  const hasColors = store.colors.length > 0

  // If product has both sizes and colors, both must be selected
  if (hasSizes && hasColors) {
    return store.selectedSize && store.selectedColor && store.selectedVariantId && maxQuantity.value > 0
  }

  // If product has only sizes, size must be selected
  if (hasSizes) {
    return store.selectedSize && store.selectedVariantId && maxQuantity.value > 0
  }

  // If product has only colors, color must be selected
  if (hasColors) {
    return store.selectedColor && store.selectedVariantId && maxQuantity.value > 0
  }

  // No variants required
  return maxQuantity.value > 0
})

const totalPrice = computed(() => {
  if (!product.value) return 0
  return product.value.prix_vente * store.selectedQty
})

const totalProfit = computed(() => {
  if (!product.value) return 0
  return product.value.prix_affilie * store.selectedQty
})

// Computed pricing with custom sell price
const currentSellPrice = computed(() => {
  return customSellPrice.value || product.value?.prix_vente || 0
})

// Minimum price calculation: ashaPrice + 50 (delivery estimation)
const minimumPrice = computed(() => {
  if (!product.value) return 0
  return (product.value.prix_achat || 0) + 50
})

// Price validation for command type
const isPriceValid = computed(() => {
  if (selectedCommandType.value === 'exchange') {
    return true // No price validation for exchange
  }
  // For order_sample, price must be >= minimumPrice
  return currentSellPrice.value >= minimumPrice.value
})

const computedCommission = computed(() => {
  if (!product.value) return 0

  // For exchange orders, commission is always 0
  if (selectedCommandType.value === 'exchange') {
    return 0
  }

  return Math.max(0, currentSellPrice.value - (product.value.prix_achat || 0))
})

const totalCommission = computed(() => {
  return computedCommission.value * store.selectedQty
})

// Size × Color matrix table with columns [Quantité | Couleur | Taille]
const matrixTable = computed(() => {
  return store.matrixRows
})

// Methods
const handleImageSelect = (index: number) => {
  selectedImageIndex.value = index
  if (product.value?.gallery.thumbnails[index]) {
    // Explicitly set activeImageUrl when user clicks gallery thumb
    activeImageUrl.value = product.value.gallery.thumbnails[index].url
  }
}

const handleColorSelect = (colorName: string) => {
  selectedColor.value = colorName
  store.selectColor(colorName)

  // Always update activeImageUrl when color changes
  if (product.value) {
    const color = store.colors.find(c => c.name === colorName)
    if (color?.image_url) {
      // Color has specific image - use it
      activeImageUrl.value = color.image_url
    } else {
      // No specific image for this color - fallback to main product image
      activeImageUrl.value = product.value.gallery.main || ''
    }
  }
}

const handleSizeSelect = (sizeValue: string) => {
  selectedSize.value = sizeValue
  store.selectSize(sizeValue)
}

const downloadFile = async (url: string, filename: string) => {
  try {
    // Fetch the file as blob to ensure proper download
    const response = await fetch(url, {
      method: 'GET',
      headers: {
        'Accept': '*/*',
      },
      // Include credentials if needed for auth
      credentials: 'same-origin'
    })

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`)
    }

    // Get the blob
    const blob = await response.blob()

    // Create blob URL
    const blobUrl = window.URL.createObjectURL(blob)

    // Create download link
    const link = document.createElement('a')
    link.href = blobUrl
    link.download = filename
    link.style.display = 'none'

    // Append to body, click, and remove
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)

    // Clean up blob URL
    window.URL.revokeObjectURL(blobUrl)

    showSuccess(`Téléchargement de "${filename}" démarré avec succès`)

  } catch (error) {
    console.error('Download error:', error)
    showError(`Erreur lors du téléchargement: ${error instanceof Error ? error.message : 'Fichier introuvable'}`)
  }
}

const openZoomModal = (imageIndex?: number) => {
  if (imageIndex !== undefined) {
    selectedImageIndex.value = imageIndex
  }
  isZoomModalOpen.value = true
}

// Get selected variant stock
const selectedVariantStock = computed(() => {
  if (!product.value) return null

  let stock = null

  // Check selected color stock
  if (selectedColor.value && product.value.colors) {
    const selectedColorVariant = product.value.colors.find(color => color.name === selectedColor.value)
    if (selectedColorVariant) {
      stock = selectedColorVariant.stock
    }
  }

  // Check selected size stock
  if (selectedSize.value && product.value.sizes) {
    const selectedSizeVariant = product.value.sizes.find(size => size.value === selectedSize.value)
    if (selectedSizeVariant) {
      // If we already have color stock, take the minimum (most restrictive)
      stock = stock !== null ? Math.min(stock, selectedSizeVariant.stock) : selectedSizeVariant.stock
    }
  }

  return stock
})

const copyToClipboard = async (text: string) => {
  try {
    await navigator.clipboard.writeText(text)
    showSuccess('Texte copié dans le presse-papiers')
  } catch (err) {
    showError('Erreur lors de la copie')
  }
}

const openVideo = (url: string) => {
  window.open(url, '_blank')
}

const handleAddToCart = async () => {
  if (!product.value || !canAddToCart.value) return

  try {
    // Use cart store directly instead of catalogue store
    await cartStore.addItem({
      produit_id: product.value.id,
      variante_id: store.selectedVariantId || undefined,
      qty: store.selectedQty,
      sell_price: customSellPrice.value || undefined,
      type_command: selectedCommandType.value
    })

    // If we get here, the add was successful
    showSuccess(t('catalogue.cart.added_success'))

    // Reset selections
    store.selectedQty = 1

    // Auto-close the ProductDrawer
    isOpen.value = false

    // Optionally open cart drawer after a short delay
    setTimeout(() => {
      openCartDrawer()
    }, 300)
  } catch (error) {
    // Error was already handled in cart store, just keep drawer open
    console.error('Add to cart error:', error)
  }
}

// Watchers
watch(() => props.productId, async (newId) => {
  if (newId && props.modelValue) {
    await store.fetchOneForDrawer(newId)
    // Reset image selection and set initial activeImageUrl
    selectedImageIndex.value = 0
    activeImageUrl.value = product.value?.gallery.main || ''
    selectedColor.value = ''
    selectedSize.value = ''
  }
}, { immediate: true })

watch(() => props.modelValue, (isOpen) => {
  if (isOpen && props.productId) {
    store.fetchOneForDrawer(props.productId)
    // Initialize activeImageUrl when opening
    nextTick(() => {
      if (!activeImageUrl.value && product.value?.gallery.main) {
        activeImageUrl.value = product.value.gallery.main
      }
    })
  } else if (!isOpen) {
    // Reset state when closing
    store.selectedColor = null
    store.selectedSize = null
    store.selectedVariantId = null
    store.selectedQty = 1
    store.maxQty = 0
    selectedImageIndex.value = 0
    activeImageUrl.value = ''
    selectedColor.value = ''
    selectedSize.value = ''
  }
})

// Watch for color/size changes to update variant ID and max quantity
watch([selectedColor, selectedSize], () => {
  // Update store state
  store.selectedColor = selectedColor.value || null
  store.selectedSize = selectedSize.value || null

  // This will trigger the store's computed properties to update selectedVariantId and maxQty
}, { immediate: true })

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
            v-if="product.sku"
            color="secondary"
            variant="tonal"
            size="small"
          >
            <VIcon icon="tabler-barcode" start />
            SKU: {{ product.sku }}
          </VChip>
          <VChip
            color="primary"
            variant="tonal"
            size="small"
          >
            <VIcon icon="tabler-package" start />
            {{ t('catalogue.kpis.stock') }}: {{ product.stock_fake ?? product.stock_total }}
          </VChip>
          <VChip
            color="success"
            variant="tonal"
            size="small"
          >
            <VIcon icon="tabler-coins" start />
            {{ t('catalogue.kpis.profit') }}: +{{ computedCommission.toFixed(2) }} MAD
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
      <div class="drawer-content" style="height: calc(100vh - 140px); overflow-y: auto; padding-bottom: 80px;">

        <!-- Zone 1: Media + Variant Selectors -->
        <div class="pa-6 border-b">
          <VRow>
            <!-- Left: Main Image + Thumbnails -->
            <VCol cols="6">
              <!-- Main Image -->
              <div class="main-image-container mb-4 position-relative">
                <VImg
                  :src="currentImage"
                  :alt="product.titre"
                  aspect-ratio="1"
                  cover
                  class="rounded-lg main-image cursor-pointer"
                  style="max-height: 350px;"
                  @click="openZoomModal(selectedImageIndex)"
                />
                <!-- Zoom indicator -->
                <VBtn
                  icon="tabler-zoom-in"
                  size="small"
                  variant="elevated"
                  color="primary"
                  class="zoom-btn"
                  @click="openZoomModal(selectedImageIndex)"
                />
              </div>

              <!-- Thumbnail Rail (Vertical) -->
              <div v-if="product.gallery.thumbnails.length > 1" class="thumbnails-rail">
                <div class="d-flex gap-2 flex-wrap justify-center">
                  <div
                    v-for="(image, index) in product.gallery.thumbnails"
                    :key="index"
                    class="thumbnail-container"
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
                  </div>
                </div>
              </div>
            </VCol>

            <!-- Right: Variant Selectors + Quantity + CTA -->
            <VCol cols="6">
              <!-- Colors -->
              <div v-if="store.colors.length" class="mb-4">
                <h4 class="text-subtitle-1 mb-3">{{ t('catalogue.variants.color') }}</h4>
                <div class="d-flex gap-2 flex-wrap">
                  <VTooltip
                    v-for="color in store.colors"
                    :key="color.name"
                    location="top"
                  >
                    <template #activator="{ props: tooltipProps }">
                      <VChip
                        v-bind="tooltipProps"
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
                    </template>
                    <span>{{ color.name }}</span>
                  </VTooltip>
                </div>
                <div v-if="!store.selectedColor && store.colors.length" class="text-caption text-medium-emphasis mt-1">
                  {{ t('catalogue.select.color') }}
                </div>
              </div>

              <!-- Sizes -->
              <div v-if="store.sizes.length" class="mb-4">
                <h4 class="text-subtitle-1 mb-3">{{ t('catalogue.variants.size') }}</h4>
                <div class="d-flex gap-2 flex-wrap">
                  <VChip
                    v-for="size in store.sizes"
                    :key="size"
                    :color="store.selectedSize === size ? 'primary' : 'default'"
                    :variant="store.selectedSize === size ? 'flat' : 'outlined'"
                    size="large"
                    class="cursor-pointer"
                    @click="handleSizeSelect(size)"
                  >
                    {{ size }}
                  </VChip>
                </div>
                <div v-if="!store.selectedSize && store.sizes.length" class="text-caption text-medium-emphasis mt-1">
                  {{ t('catalogue.select.size') }}
                </div>
              </div>

              <!-- Command Type Selector -->
              <div class="mb-4">
                <h4 class="text-subtitle-1 mb-3">{{ t('order_type') }}</h4>
                <VRadioGroup
                  v-model="selectedCommandType"
                  inline
                  density="compact"
                >
                  <VRadio
                    value="order_sample"
                    :label="t('order_sample')"
                    color="primary"
                  />
                  <VRadio
                    value="exchange"
                    :label="t('exchange')"
                    color="warning"
                  />
                </VRadioGroup>

                <!-- Command Type Info -->
                <VAlert
                  v-if="selectedCommandType === 'exchange'"
                  type="warning"
                  variant="tonal"
                  density="compact"
                  class="mt-2"
                >
                  <VIcon icon="tabler-info-circle" start />
                  {{ t('catalogue.exchange_info') }}
                </VAlert>
                <VAlert
                  v-else
                  type="info"
                  variant="tonal"
                  density="compact"
                  class="mt-2"
                >
                  <VIcon icon="tabler-info-circle" start />
                  <div>
                    {{ t('catalogue.minimum_price_required', { price: minimumPrice }) }}
                    <div v-if="maxQuantity > 0" class="mt-1">
                      <strong>{{ t('catalogue.max_stock_available', { quantity: maxQuantity }) }}</strong>
                    </div>
                  </div>
                </VAlert>
              </div>

              <!-- Custom Pricing Section -->
              <div class="mb-4">
                <h4 class="text-subtitle-1 mb-3">{{ t('catalogue.price_and_commission') }}</h4>
                <VCard variant="outlined" class="pa-4">
                  <div class="d-flex justify-space-between align-center mb-3">
                    <div>
                      <div class="text-caption text-medium-emphasis">{{ t('catalogue.purchase_price') }}</div>
                      <div class="text-body-1">{{ product?.prix_achat || 0 }} MAD</div>
                    </div>
                    <div class="text-center">
                      <div class="text-caption text-medium-emphasis">{{ t('catalogue.recommended_price') }}</div>
                      <div class="text-h6 text-primary">{{ product?.prix_vente || 0 }} MAD</div>
                    </div>
                    <div class="text-end">
                      <div class="text-caption text-medium-emphasis">{{ t('catalogue.unit_commission') }}</div>
                      <div class="text-h6 text-success">+{{ computedCommission.toFixed(2) }} MAD</div>
                    </div>
                  </div>

                  <!-- Variant Stock Information -->
                  <div v-if="(product.colors && product.colors.length > 0) || (product.sizes && product.sizes.length > 0)" class="mb-4">
                    <div class="d-flex align-center mb-3">
                      <VIcon icon="tabler-package" class="me-2" color="primary" size="20" />
                      <span class="text-subtitle-1 font-weight-bold text-primary">Stock par Variante</span>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                      <!-- Color variants -->
                      <VChip
                        v-for="color in product.colors"
                        :key="'color-' + color.name"
                        :color="color.stock > 0 ? 'success' : 'error'"
                        variant="flat"
                        size="default"
                        class="font-weight-medium"
                      >
                        <VIcon
                          :icon="color.stock > 0 ? 'tabler-check' : 'tabler-x'"
                          start
                          size="16"
                        />
                        {{ color.name }}: {{ color.stock }} unités
                      </VChip>
                      <!-- Size variants -->
                      <VChip
                        v-for="size in product.sizes"
                        :key="'size-' + size.value"
                        :color="size.stock > 0 ? 'success' : 'error'"
                        variant="flat"
                        size="default"
                        class="font-weight-medium"
                      >
                        <VIcon
                          :icon="size.stock > 0 ? 'tabler-check' : 'tabler-x'"
                          start
                          size="16"
                        />
                        {{ size.value }}: {{ size.stock }} unités
                      </VChip>
                    </div>
                  </div>

                  <!-- Custom Sell Price Input -->
                  <VTextField
                    v-model.number="customSellPrice"
                    :label="t('catalogue.custom_sell_price_optional')"
                    type="number"
                    variant="outlined"
                    density="compact"
                    suffix="MAD"
                    :min="selectedCommandType === 'order_sample' ? minimumPrice : (product?.prix_achat || 0)"
                    :placeholder="product?.prix_vente?.toString() || '0'"
                    :hint="selectedCommandType === 'exchange' ? t('catalogue.price_disabled_for_exchange') : t('catalogue.minimum_price_hint', { price: minimumPrice })"
                    :disabled="selectedCommandType === 'exchange'"
                    persistent-hint
                    clearable
                  />
                </VCard>
              </div>

              <!-- Quantity Selector -->
              <div class="mb-4">
                <h4 class="text-subtitle-1 mb-3">{{ t('catalogue.detail.quantity') }}</h4>

                <!-- Selected Variant Stock Alert -->
                <VAlert
                  v-if="selectedVariantStock !== null"
                  :type="selectedVariantStock > 0 ? 'info' : 'warning'"
                  variant="tonal"
                  density="compact"
                  class="mb-3"
                >
                  <VIcon
                    :icon="selectedVariantStock > 0 ? 'tabler-package' : 'tabler-alert-triangle'"
                    start
                  />
                  <span v-if="selectedVariantStock > 0">
                    <strong>{{ t('catalogue.stock_available') }}:</strong> {{ t('catalogue.units_for_variant', { stock: selectedVariantStock }) }}
                  </span>
                  <span v-else>
                    <strong>{{ t('catalogue.out_of_stock') }}</strong> {{ t('catalogue.for_this_variant') }}
                  </span>
                </VAlert>
                <div class="d-flex align-center gap-3">
                  <VBtn
                    icon="tabler-minus"
                    size="small"
                    variant="outlined"
                    :disabled="store.selectedQty <= (product?.quantite_min || 1) || maxQuantity === 0"
                    @click="store.selectedQty--"
                  />
                  <VTextField
                    v-model.number="store.selectedQty"
                    type="number"
                    variant="outlined"
                    density="compact"
                    style="width: 80px;"
                    :min="product?.quantite_min || 1"
                    :max="maxQuantity"
                    :disabled="maxQuantity === 0"
                    hide-details
                  />
                  <VBtn
                    icon="tabler-plus"
                    size="small"
                    variant="outlined"
                    :disabled="store.selectedQty >= maxQuantity || maxQuantity === 0"
                    @click="store.selectedQty++"
                  />
                  <span class="text-caption text-medium-emphasis">{{ t('catalogue.max_label', { quantity: maxQuantity }) }}</span>
                </div>

                <!-- Minimum quantity info -->
                <div v-if="product?.quantite_min && product.quantite_min > 1" class="text-caption text-warning mt-2">
                  <VIcon icon="tabler-info-circle" size="14" class="me-1" />
                  {{ t('catalogue.minimum_quantity_required', { quantity: product.quantite_min }) }}
                </div>

                <!-- Stock out message -->
                <div v-if="maxQuantity === 0" class="text-caption text-error mt-2">
                  {{ t('catalogue.qty.max_zero') }}
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
          <!-- Size × Color Matrix Table -->
          <div v-if="matrixTable.length" class="mb-6">
            <h3 class="text-h6 mb-3">Stock par combinaison</h3>
            <VTable density="compact">
              <thead>
                <tr>
                  <th>{{ t('catalogue.table.quantity') }}</th>
                  <th>{{ t('catalogue.table.color') }}</th>
                  <th>{{ t('catalogue.table.size') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(item, index) in matrixTable" :key="index">
                  <td>
                    <VChip
                      :color="item.qty > 0 ? 'success' : 'error'"
                      size="small"
                      variant="tonal"
                    >
                      {{ item.qty }}
                    </VChip>
                  </td>
                  <td class="font-weight-medium">{{ item.color }}</td>
                  <td class="font-weight-medium">{{ item.size }}</td>
                </tr>
              </tbody>
            </VTable>
          </div>

          <!-- Images Grid -->
          <div v-if="product.images.length" class="mb-6">
            <h3 class="text-h6 mb-3">{{ t('catalogue.assets.images') }}</h3>
            <div class="d-flex gap-4 flex-wrap">
              <VCard
                v-for="(image, index) in product.images"
                :key="index"
                variant="elevated"
                width="140"
                class="image-asset-card"
                hover
              >
                <VCardText class="pa-3">
                  <VImg
                    :src="image.url"
                    width="100"
                    height="100"
                    cover
                    class="rounded mb-2"
                  />
                  <div class="d-flex gap-1 flex-column">
                    <VBtn
                      size="small"
                      variant="flat"
                      color="primary"
                      prepend-icon="tabler-eye"
                      block
                      @click="openZoomModal(index)"
                    >
                      {{ t('catalogue.view') }}
                    </VBtn>
                    <VBtn
                      size="small"
                      variant="outlined"
                      color="primary"
                      prepend-icon="tabler-download"
                      block
                      @click.stop="downloadFile(image.url, `${product.titre}_image_${index + 1}.jpg`)"
                    >
                      {{ t('catalogue.download') }}
                    </VBtn>
                  </div>
                </VCardText>
              </VCard>
            </div>
          </div>

          <!-- Videos List -->
          <div v-if="product.videos.length" class="mb-8">
            <div class="d-flex align-center mb-4">
              <VIcon icon="tabler-video" class="me-2" color="primary" />
              <h3 class="text-h6 font-weight-bold text-primary">{{ t('catalogue.assets.videos') }}</h3>
            </div>
            <VAlert
              type="info"
              variant="tonal"
              class="mb-4"
              density="compact"
            >
              <VIcon icon="tabler-info-circle" start />
              {{ t('catalogue.video_instructions') }}
            </VAlert>
            <div class="d-flex gap-4 flex-wrap video-list">
              <VCard
                v-for="(video, index) in product.videos"
                :key="index"
                variant="elevated"
                width="240"
                class="video-asset"
                hover
              >
                <VCardText class="pa-4">
                  <div class="d-flex align-center mb-3">
                    <VAvatar color="primary" variant="tonal" size="32" class="me-3">
                      <VIcon icon="tabler-video" size="18" />
                    </VAvatar>
                    <div class="flex-grow-1">
                      <div class="text-subtitle-2 font-weight-medium">{{ video.title || `Vidéo ${index + 1}` }}</div>
                      <div class="text-caption text-medium-emphasis">Format: MP4</div>
                    </div>
                  </div>
                  <div class="d-flex gap-2 flex-column">
                    <VBtn
                      size="small"
                      variant="flat"
                      color="primary"
                      prepend-icon="tabler-eye"
                      block
                      @click="openVideo(video.url)"
                    >
                      {{ t('actions.view') }}
                    </VBtn>
                    <VBtn
                      size="small"
                      variant="outlined"
                      color="primary"
                      prepend-icon="tabler-download"
                      block
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
          {{ t('catalogue.actions.add_to_cart') }} - {{ (currentSellPrice * store.selectedQty).toFixed(2) }} MAD
        </VBtn>
      </div>

    </template>

    <!-- Loading State -->
    <div v-else class="d-flex align-center justify-center pa-8">
      <VProgressCircular indeterminate color="primary" />
    </div>
  </VNavigationDrawer>

  <!-- Image Zoom Modal -->
  <ImageZoomModal
    v-model="isZoomModalOpen"
    :images="zoomModalImages"
    :initial-index="selectedImageIndex"
  />
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

.download-btn-fixed {
  position: absolute;
  top: 4px;
  right: 4px;
  opacity: 0;
  transition: opacity 0.2s ease;
  z-index: 2;
  pointer-events: auto;
}

.thumbnail-container:hover .download-btn-fixed {
  opacity: 1;
}

.thumbnail-container {
  position: relative;
  display: inline-block;
}

/* Removed unused thumbnail-container-new class */

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

.download-overlay-fixed {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  opacity: 0;
  transition: opacity 0.2s ease;
  z-index: 2;
  pointer-events: auto;
}

.image-asset:hover .download-overlay-fixed {
  opacity: 1;
}

.image-asset {
  position: relative;
  display: inline-block;
}

.video-asset {
  transition: all 0.2s ease;
}

.video-asset:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.image-asset-card {
  transition: all 0.2s ease;
}

.image-asset-card:hover {
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

/* Removed duplicate download-btn rules - using download-btn-fixed instead */

.zoom-btn {
  position: absolute;
  top: 8px;
  right: 8px;
  opacity: 0;
  transition: opacity 0.2s ease;
}

.main-image-container:hover .zoom-btn {
  opacity: 1;
}

.main-image {
  transition: transform 0.2s ease;
}

.main-image:hover {
  transform: scale(1.02);
}

/* Variant Stock Cards */
.variant-stock-card {
  transition: all 0.2s ease;
  border: 2px solid transparent;
}

.variant-stock-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.variant-stock-card--out-of-stock {
  border-color: rgb(var(--v-theme-error));
  background-color: rgba(var(--v-theme-error), 0.05);
}

/* Video List */
.video-list {
  max-height: none;
  overflow: visible;
}

.video-asset {
  transition: all 0.2s ease;
}

.video-asset:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

/* Drawer Content Scrolling */
.drawer-content {
  scroll-behavior: smooth;
}

.drawer-content::-webkit-scrollbar {
  width: 6px;
}

.drawer-content::-webkit-scrollbar-track {
  background: rgba(var(--v-theme-surface-variant), 0.3);
  border-radius: 3px;
}

.drawer-content::-webkit-scrollbar-thumb {
  background: rgba(var(--v-theme-primary), 0.5);
  border-radius: 3px;
}

.drawer-content::-webkit-scrollbar-thumb:hover {
  background: rgba(var(--v-theme-primary), 0.7);
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
