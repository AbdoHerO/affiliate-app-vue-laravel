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
  (e: 'addToCart', data: { produit_id: string; variante_id?: string; qty: number }): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const { t } = useI18n()

// Local state
const selectedImageIndex = ref(0)
const selectedVariantId = ref<string>('')
const quantity = ref(1)
const activeTab = ref('details')

// Computed
const isOpen = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
})

const images = computed(() => {
  if (!props.product?.images?.length) return []
  return props.product.images.sort((a, b) => a.ordre - b.ordre)
})

const currentImage = computed(() => {
  if (!images.value.length) return 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAwIiBoZWlnaHQ9IjMwMCIgdmlld0JveD0iMCAwIDMwMCAzMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIzMDAiIGhlaWdodD0iMzAwIiBmaWxsPSIjRjVGNUY1Ii8+CjxwYXRoIGQ9Ik0xNTAgMTAwQzE2MS4wNDYgMTAwIDE3MCA5MC45NTQzIDE3MCA4MEM1NyA2OS4wNDU3IDE0Ny45NTQgNjAgMTM2IDYwQzEyNC45NTQgNjAgMTE2IDY5LjA0NTcgMTE2IDgwQzExNiA5MC45NTQzIDEyNC45NTQgMTAwIDEzNiAxMDBIMTUwWiIgZmlsbD0iI0NDQ0NDQyIvPgo8cGF0aCBkPSJNMTgwIDEyMEgxMjBDMTE2LjY4NiAxMjAgMTE0IDEyMi42ODYgMTE0IDEyNlYyMDBDMTE0IDIwMy4zMTQgMTE2LjY4NiAyMDYgMTIwIDIwNkgxODBDMTgzLjMxNCAyMDYgMTg2IDIwMy4zMTQgMTg2IDIwMFYxMjZDMTg2IDEyMi42ODYgMTgzLjMxNCAxMjAgMTgwIDEyMFoiIGZpbGw9IiNDQ0NDQ0MiLz4KPC9zdmc+'
  return images.value[selectedImageIndex.value]?.url || images.value[0]?.url
})

const variants = computed(() => {
  if (!props.product?.variantes) return { sizes: [], colors: [], others: [] }
  
  const sizes = props.product.variantes.filter(v => 
    ['taille', 'size'].includes(v.attribut_principal.toLowerCase())
  )
  const colors = props.product.variantes.filter(v => 
    ['couleur', 'color'].includes(v.attribut_principal.toLowerCase())
  )
  const others = props.product.variantes.filter(v => 
    !['taille', 'size', 'couleur', 'color'].includes(v.attribut_principal.toLowerCase())
  )
  
  return { sizes, colors, others }
})

const selectedVariant = computed(() => {
  if (!selectedVariantId.value || !props.product?.variantes) return null
  return props.product.variantes.find(v => v.id === selectedVariantId.value)
})

const maxQuantity = computed(() => {
  if (selectedVariant.value) {
    return Math.min(selectedVariant.value.stock, 10)
  }
  if (props.product) {
    const totalStock = props.product.variantes?.reduce((sum, v) => sum + v.stock, 0) || 0
    return Math.min(totalStock, 10)
  }
  return 1
})

const canAddToCart = computed(() => {
  if (!props.product) return false
  
  const totalStock = props.product.variantes?.reduce((sum, v) => sum + v.stock, 0) || 0
  return totalStock > 0 && quantity.value <= maxQuantity.value
})

const stockStatus = computed(() => {
  if (!props.product) return { text: '', color: 'default' }
  
  const totalStock = props.product.variantes?.reduce((sum, v) => sum + v.stock, 0) || 0
  
  if (totalStock === 0) {
    return { text: t('catalogue.card.out_of_stock'), color: 'error' }
  } else if (totalStock < 10) {
    return { text: t('catalogue.card.low_stock'), color: 'warning' }
  } else {
    return { text: `${totalStock} ${t('catalogue.stock')}`, color: 'success' }
  }
})

// Methods
const handleImageSelect = (index: number) => {
  selectedImageIndex.value = index
}

const handleVariantSelect = (variantId: string) => {
  selectedVariantId.value = variantId
  
  // Update image if variant has specific image
  const variant = props.product?.variantes?.find(v => v.id === variantId)
  if (variant?.image_url && images.value.length > 0) {
    const imageIndex = images.value.findIndex(img => img.url === variant.image_url)
    if (imageIndex !== -1) {
      selectedImageIndex.value = imageIndex
    }
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
    variante_id: selectedVariantId.value || undefined,
    qty: quantity.value
  })
}

const closeDrawer = () => {
  isOpen.value = false
}

// Reset state when product changes
watch(() => props.product?.id, () => {
  selectedImageIndex.value = 0
  selectedVariantId.value = ''
  quantity.value = 1
  activeTab.value = 'details'
})

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
    width="600"
    temporary
    class="product-drawer"
  >
    <template v-if="product">
      <!-- Header -->
      <div class="product-drawer__header">
        <div class="d-flex align-center justify-space-between pa-4 border-b">
          <h5 class="text-h5">{{ product.titre }}</h5>
          <VBtn
            icon
            variant="text"
            @click="closeDrawer"
          >
            <VIcon icon="tabler-x" />
          </VBtn>
        </div>
      </div>

      <!-- Content -->
      <VContainer fluid class="pa-0">
        <!-- Image Gallery -->
        <div class="product-drawer__gallery pa-4">
          <!-- Main Image -->
          <VImg
            :src="currentImage"
            :alt="product.titre"
            aspect-ratio="1"
            cover
            class="rounded mb-4"
          />
          
          <!-- Thumbnail Images -->
          <div v-if="images.length > 1" class="product-drawer__thumbnails">
            <VBtn
              v-for="(image, index) in images"
              :key="index"
              :variant="selectedImageIndex === index ? 'elevated' : 'outlined'"
              :color="selectedImageIndex === index ? 'primary' : 'default'"
              size="small"
              class="me-2 mb-2"
              @click="handleImageSelect(index)"
            >
              <VImg
                :src="image.url"
                :alt="`${product.titre} ${index + 1}`"
                width="40"
                height="40"
                cover
              />
            </VBtn>
          </div>
        </div>

        <!-- Product Info Tabs -->
        <VTabs v-model="activeTab" class="border-b">
          <VTab value="details">{{ t('catalogue.detail.title') }}</VTab>
          <VTab value="variants">{{ t('catalogue.detail.variants') }}</VTab>
          <VTab value="pricing">{{ t('catalogue.detail.pricing') }}</VTab>
        </VTabs>

        <VWindow v-model="activeTab">
          <!-- Details Tab -->
          <VWindowItem value="details" class="pa-4">
            <!-- Stock Status -->
            <VAlert
              :color="stockStatus.color"
              variant="tonal"
              class="mb-4"
            >
              {{ stockStatus.text }}
            </VAlert>

            <!-- Description -->
            <div v-if="product.description" class="mb-4">
              <h6 class="text-h6 mb-2">{{ t('catalogue.detail.description') }}</h6>
              <div class="text-body-2" v-html="product.description"></div>
            </div>

            <!-- Category -->
            <div v-if="product.categorie" class="mb-4">
              <h6 class="text-h6 mb-2">{{ t('catalogue.category') }}</h6>
              <VChip color="primary" variant="tonal">
                {{ product.categorie.nom }}
              </VChip>
            </div>

            <!-- Rating -->
            <div v-if="product.rating_value" class="mb-4">
              <h6 class="text-h6 mb-2">{{ t('catalogue.card.rating') }}</h6>
              <VRating
                :model-value="product.rating_value"
                readonly
                length="5"
                half-increments
              />
            </div>
          </VWindowItem>

          <!-- Variants Tab -->
          <VWindowItem value="variants" class="pa-4">
            <!-- Sizes -->
            <div v-if="variants.sizes.length" class="mb-4">
              <h6 class="text-h6 mb-2">{{ t('catalogue.size') }}</h6>
              <div class="d-flex flex-wrap gap-2">
                <VChip
                  v-for="variant in variants.sizes"
                  :key="variant.id"
                  :color="selectedVariantId === variant.id ? 'primary' : 'default'"
                  :variant="selectedVariantId === variant.id ? 'elevated' : 'outlined'"
                  :disabled="variant.stock === 0"
                  @click="handleVariantSelect(variant.id)"
                >
                  {{ variant.valeur }}
                  <VTooltip activator="parent">
                    {{ variant.stock }} en stock
                  </VTooltip>
                </VChip>
              </div>
            </div>

            <!-- Colors -->
            <div v-if="variants.colors.length" class="mb-4">
              <h6 class="text-h6 mb-2">{{ t('catalogue.color') }}</h6>
              <div class="d-flex flex-wrap gap-2">
                <VBtn
                  v-for="variant in variants.colors"
                  :key="variant.id"
                  :variant="selectedVariantId === variant.id ? 'elevated' : 'outlined'"
                  :color="selectedVariantId === variant.id ? 'primary' : 'default'"
                  :disabled="variant.stock === 0"
                  size="small"
                  @click="handleVariantSelect(variant.id)"
                >
                  <div
                    v-if="variant.color"
                    class="color-swatch me-2"
                    :style="{ backgroundColor: variant.color }"
                  />
                  {{ variant.valeur }}
                  <VTooltip activator="parent">
                    {{ variant.stock }} en stock
                  </VTooltip>
                </VBtn>
              </div>
            </div>

            <!-- Other Variants -->
            <div v-if="variants.others.length" class="mb-4">
              <h6 class="text-h6 mb-2">{{ t('catalogue.detail.specifications') }}</h6>
              <div class="d-flex flex-wrap gap-2">
                <VChip
                  v-for="variant in variants.others"
                  :key="variant.id"
                  :color="selectedVariantId === variant.id ? 'primary' : 'default'"
                  :variant="selectedVariantId === variant.id ? 'elevated' : 'outlined'"
                  :disabled="variant.stock === 0"
                  @click="handleVariantSelect(variant.id)"
                >
                  {{ variant.attribut_principal }}: {{ variant.valeur }}
                  <VTooltip activator="parent">
                    {{ variant.stock }} en stock
                  </VTooltip>
                </VChip>
              </div>
            </div>
          </VWindowItem>

          <!-- Pricing Tab -->
          <VWindowItem value="pricing" class="pa-4">
            <VRow>
              <VCol cols="12" md="6">
                <VCard variant="outlined">
                  <VCardText>
                    <div class="text-center">
                      <h6 class="text-h6 mb-2">{{ t('catalogue.buy') }}</h6>
                      <div class="text-h4 text-primary">{{ product.prix_achat }} MAD</div>
                    </div>
                  </VCardText>
                </VCard>
              </VCol>
              <VCol cols="12" md="6">
                <VCard variant="outlined">
                  <VCardText>
                    <div class="text-center">
                      <h6 class="text-h6 mb-2">{{ t('catalogue.sell') }}</h6>
                      <div class="text-h4 text-success">{{ product.prix_vente }} MAD</div>
                    </div>
                  </VCardText>
                </VCard>
              </VCol>
              <VCol cols="12">
                <VCard variant="outlined" color="primary">
                  <VCardText>
                    <div class="text-center">
                      <h6 class="text-h6 mb-2">{{ t('catalogue.profit') }}</h6>
                      <div class="text-h3 text-primary">+{{ product.prix_affilie }} MAD</div>
                    </div>
                  </VCardText>
                </VCard>
              </VCol>
            </VRow>
          </VWindowItem>
        </VWindow>

        <!-- Add to Cart Section -->
        <div class="product-drawer__cart-section pa-4 border-t">
          <!-- Quantity Selector -->
          <div class="d-flex align-center justify-center mb-4">
            <VBtn
              icon
              variant="outlined"
              :disabled="quantity <= 1"
              @click="handleQuantityChange(-1)"
            >
              <VIcon icon="tabler-minus" />
            </VBtn>
            <div class="mx-4 text-h6 text-center" style="min-width: 60px;">
              {{ quantity }}
            </div>
            <VBtn
              icon
              variant="outlined"
              :disabled="quantity >= maxQuantity"
              @click="handleQuantityChange(1)"
            >
              <VIcon icon="tabler-plus" />
            </VBtn>
          </div>

          <!-- Add to Cart Button -->
          <VBtn
            block
            color="primary"
            size="large"
            :disabled="!canAddToCart"
            @click="handleAddToCart"
          >
            <VIcon start icon="tabler-shopping-cart" />
            {{ t('catalogue.add_to_cart') }}
          </VBtn>

          <!-- Max Quantity Info -->
          <div v-if="maxQuantity < 10" class="text-caption text-center mt-2 text-medium-emphasis">
            {{ t('catalogue.detail.max_quantity', { max: maxQuantity }) }}
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
.product-drawer__header {
  position: sticky;
  top: 0;
  z-index: 1;
  background: rgb(var(--v-theme-surface));
}

.product-drawer__gallery {
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.product-drawer__thumbnails {
  display: flex;
  flex-wrap: wrap;
}

.product-drawer__cart-section {
  position: sticky;
  bottom: 0;
  background: rgb(var(--v-theme-surface));
}

.color-swatch {
  width: 16px;
  height: 16px;
  border-radius: 50%;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}
</style>
