<script setup lang="ts">
import { ref, computed, onMounted, watch, nextTick } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCatalogueStore } from '@/stores/affiliate/catalogue'
import { useAffiliateCartStore } from '@/stores/affiliate/cart'
import { useApi } from '@/composables/useApi'
import { useNotifications } from '@/composables/useNotifications'
import { useAffiliateCartUi } from '@/composables/useAffiliateCartUi'
import CatalogueCard from '@/components/affiliate/catalogue/CatalogueCard.vue'
import ProductDrawer from '@/components/affiliate/catalogue/ProductDrawer.vue'
import AffiliateHeaderCart from '@/components/affiliate/cart/AffiliateHeaderCart.vue'
import AffiliateCartModal from '@/components/affiliate/cart/AffiliateCartModal.vue'

definePage({
  meta: {
    requiresAuth: true,
    requiresRole: 'affiliate',
  },
})

const { t } = useI18n()
const catalogueStore = useCatalogueStore()
const cartStore = useAffiliateCartStore()
const { showSuccess, showError } = useNotifications()
const { cartDrawerOpen, openCartDrawer, closeCartDrawer } = useAffiliateCartUi()

// Local state
const searchQuery = ref('')
const selectedCategory = ref('')
const minProfit = ref<number | undefined>(undefined)
const selectedSize = ref('')
const selectedColor = ref('')
const sortBy = ref('created_at')
const sortDirection = ref('desc')
const searchTimeout = ref<NodeJS.Timeout | null>(null)
const categories = ref<Array<{ id: string; nom: string }>>([])
const categoriesLoading = ref(false)

// Computed
const breadcrumbs = computed(() => [
  { title: t('nav_dashboard'), to: { name: 'affiliate-dashboard' } },
  { title: t('catalogue.title'), active: true }
])

const isLoading = computed(() => catalogueStore.isLoading)
const products = computed(() => catalogueStore.items)
const pagination = computed(() => catalogueStore.pagination)
const hasProducts = computed(() => catalogueStore.hasItems)
const selectedProduct = computed(() => catalogueStore.selectedProduct)
const isDetailLoading = computed(() => catalogueStore.isDetailLoading)

const showProductDrawer = ref(false)
const selectedProductId = ref<string | null>(null)

const sortOptions = computed(() => [
  { value: 'created_at:desc', text: t('catalogue.filters.sort_newest') },
  { value: 'created_at:asc', text: t('catalogue.filters.sort_oldest') },
  { value: 'prix_vente:asc', text: t('catalogue.filters.sort_price_asc') },
  { value: 'prix_vente:desc', text: t('catalogue.filters.sort_price_desc') },
  { value: 'prix_affilie:asc', text: t('catalogue.filters.sort_profit_asc') },
  { value: 'prix_affilie:desc', text: t('catalogue.filters.sort_profit_desc') },
  { value: 'titre:asc', text: t('catalogue.filters.sort_name_asc') },
  { value: 'titre:desc', text: t('catalogue.filters.sort_name_desc') },
])

const profitOptions = computed(() => [
  { value: undefined, text: 'Tous les profits' },
  { value: 10, text: '10 MAD+' },
  { value: 25, text: '25 MAD+' },
  { value: 50, text: '50 MAD+' },
  { value: 100, text: '100 MAD+' },
])

const hasActiveFilters = computed(() => {
  return !!(searchQuery.value || selectedCategory.value || minProfit.value || 
           selectedSize.value || selectedColor.value)
})

// Methods
const loadData = async () => {
  const [sort, dir] = (sortBy.value + ':' + sortDirection.value).split(':')
  
  await catalogueStore.fetchList({
    q: searchQuery.value || undefined,
    category_id: selectedCategory.value || undefined,
    min_profit: minProfit.value,
    size: selectedSize.value || undefined,
    color: selectedColor.value || undefined,
    page: pagination.value.current_page,
    per_page: pagination.value.per_page
  })
}

const handleSearch = () => {
  if (searchTimeout.value) {
    clearTimeout(searchTimeout.value)
  }
  
  searchTimeout.value = setTimeout(() => {
    catalogueStore.setPage(1)
    loadData()
  }, 300)
}

const handleFilterChange = () => {
  catalogueStore.setPage(1)
  loadData()
}

const handleSortChange = (value: string) => {
  const [sort, dir] = value.split(':')
  sortBy.value = sort
  sortDirection.value = dir
  handleFilterChange()
}

const clearFilters = () => {
  searchQuery.value = ''
  selectedCategory.value = ''
  minProfit.value = undefined
  selectedSize.value = ''
  selectedColor.value = ''
  sortBy.value = 'created_at'
  sortDirection.value = 'desc'
  
  catalogueStore.resetFilters()
  loadData()
}

const handlePageChange = (page: number) => {
  catalogueStore.setPage(page)
  loadData()
}

const handleProductOpen = async (productId: string) => {
  try {
    selectedProductId.value = productId
    showProductDrawer.value = true
    // Note: Initial variant selection would be passed here if needed
    // For now, the drawer will start with no variants selected
  } catch (error) {
    showProductDrawer.value = false
    showError('Erreur lors du chargement du produit')
  }
}

const handleAddToCart = async (data: { produit_id: string; variante_id?: string; qty: number }) => {
  try {
    console.log('🛒 [Catalogue] Adding to cart:', data)
    
    // Add item using cart store
    await cartStore.addItem(data)
    
    // Refresh cart to ensure sync
    await cartStore.fetchCart()
    
    // Also refresh catalogue cart summary
    await catalogueStore.fetchCartSummary()
    
    showSuccess('Ajouté au panier')
    
    console.log('✅ [Catalogue] Item added, cart count:', cartStore.count)
  } catch (error) {
    console.error('❌ [Catalogue] Add to cart error:', error)
    showError('Erreur lors de l\'ajout au panier')
  }
}

const handleVariantChange = (variantData: any) => {
  // This should no longer be called since we removed variant change events from cards
  console.log('Variant changed:', variantData)
}

const handleQtyChange = (qty: number) => {
  // Handle quantity change if needed
  console.log('Quantity changed:', qty)
}

const loadCategories = async () => {
  categoriesLoading.value = true
  try {
    const { data, error } = await useApi('/affiliate/categories', {
      method: 'GET'
    })

    if (error.value) {
      throw error.value
    }

    if (data.value && (data.value as any).data) {
      categories.value = (data.value as any).data.filter((cat: any) => cat.actif)
    }
  } catch (err) {
    console.error('Error loading categories:', err)
    showError('Erreur lors du chargement des catégories')
  } finally {
    categoriesLoading.value = false
  }
}

// Watchers
watch(searchQuery, handleSearch)
watch([selectedCategory, minProfit, selectedSize, selectedColor], handleFilterChange)

// Lifecycle
onMounted(async () => {
  // Load categories for filter
  await loadCategories()

  // Load initial data
  await loadData()

  // Load cart summary
  await catalogueStore.fetchCartSummary()
  
  // Load cart data
  await cartStore.fetchCart()
})
</script>

<template>
  <div class="affiliate-catalogue">
    <!-- Breadcrumbs -->
    <VBreadcrumbs
      :items="breadcrumbs"
      class="pa-0 mb-4"
    />

    <!-- Page Header -->
    <div class="d-flex align-center justify-space-between mb-6">
      <div>
        <h1 class="text-h4 mb-2">{{ t('catalogue.title') }}</h1>
        <p class="text-body-1 text-medium-emphasis mb-0">
          {{ t('catalogue.showing_results', { 
            from: pagination.from, 
            to: pagination.to, 
            total: pagination.total 
          }) }}
        </p>
      </div>
      
      <div class="d-flex align-center gap-4">
        <!-- Beautiful Cart Button -->
        <div class="cart-button-container">
          <!-- Cart Summary Badge (when has items) -->
          <VChip
            v-if="cartStore.count > 0"
            color="success"
            variant="elevated"
            size="small"
            class="cart-summary-chip mb-2"
          >
            <VIcon icon="tabler-check-circle" start size="16" />
            {{ cartStore.count }} article{{ cartStore.count > 1 ? 's' : '' }} • {{ cartStore.subtotal.toFixed(2) }} MAD
          </VChip>
          
          <!-- Main Cart Button -->
          <VBtn
            color="primary"
            variant="elevated"
            size="large"
            class="cart-button"
            @click="openCartDrawer"
          >
            <VBadge
              :content="cartStore.count"
              :model-value="cartStore.count > 0"
              color="error"
              offset-x="-5"
              offset-y="-5"
            >
              <div class="d-flex align-center gap-2">
                <VIcon icon="tabler-shopping-cart" size="20" />
                <span class="cart-button-text">
                  {{ cartStore.count > 0 ? 'Voir Panier' : 'Panier' }}
                </span>
              </div>
            </VBadge>
          </VBtn>
        </div>
      </div>
    </div>

    <!-- Filters Bar -->
    <VCard class="mb-6" variant="outlined">
      <VCardText>
        <VRow>
          <!-- Search -->
          <VCol cols="12" md="3">
            <VTextField
              v-model="searchQuery"
              :placeholder="t('catalogue.search_placeholder')"
              prepend-inner-icon="tabler-search"
              variant="outlined"
              density="compact"
              hide-details
            />
          </VCol>
          
          <!-- Category -->
          <VCol cols="12" md="2">
            <VSelect
              v-model="selectedCategory"
              :items="[
                { value: '', title: t('catalogue.all_categories') },
                ...categories.map(cat => ({ value: cat.id, title: cat.nom }))
              ]"
              :label="t('catalogue.category')"
              variant="outlined"
              density="compact"
              hide-details
            />
          </VCol>
          
          <!-- Min Profit -->
          <VCol cols="12" md="2">
            <VSelect
              v-model="minProfit"
              :items="profitOptions"
              :label="t('catalogue.min_profit')"
              variant="outlined"
              density="compact"
              hide-details
            />
          </VCol>
          
          <!-- Sort -->
          <VCol cols="12" md="3">
            <VSelect
              :model-value="sortBy + ':' + sortDirection"
              :items="sortOptions"
              :label="t('catalogue.filters.sort_by')"
              variant="outlined"
              density="compact"
              hide-details
              @update:model-value="handleSortChange"
            />
          </VCol>
          
          <!-- Clear Filters -->
          <VCol cols="12" md="2">
            <VBtn
              :disabled="!hasActiveFilters"
              variant="outlined"
              block
              @click="clearFilters"
            >
              {{ t('catalogue.clear_filters') }}
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Products Grid -->
    <div v-if="isLoading" class="text-center py-8">
      <VProgressCircular indeterminate color="primary" size="64" />
      <p class="text-body-1 mt-4">{{ t('catalogue.loading') }}</p>
    </div>

    <div v-else-if="!hasProducts" class="text-center py-12">
      <VIcon icon="tabler-package-off" size="64" class="mb-4 text-disabled" />
      <h3 class="text-h5 mb-2">{{ t('catalogue.no_products') }}</h3>
      <p class="text-body-1 text-medium-emphasis mb-4">{{ t('catalogue.no_products_subtitle') }}</p>
      <VBtn
        v-if="hasActiveFilters"
        color="primary"
        variant="outlined"
        @click="clearFilters"
      >
        {{ t('catalogue.clear_filters') }}
      </VBtn>
    </div>

    <VRow v-else class="catalogue-grid">
      <VCol
        v-for="product in products"
        :key="product.id"
        cols="12"
        sm="6"
        md="4"
        lg="3"
        class="catalogue-grid__item"
      >
        <CatalogueCard
          :product="catalogueStore.mapProductToNormalized(product)"
          @open="handleProductOpen"
          @add-to-cart="handleAddToCart"
          @variant-change="handleVariantChange"
          @qty-change="handleQtyChange"
        />
      </VCol>
    </VRow>

    <!-- Pagination -->
    <div v-if="hasProducts && pagination.last_page > 1" class="d-flex justify-center mt-8">
      <VPagination
        :model-value="pagination.current_page"
        :length="pagination.last_page"
        :total-visible="7"
        @update:model-value="handlePageChange"
      />
    </div>

    <!-- Product Detail Drawer -->
    <ProductDrawer
      v-model="showProductDrawer"
      :product-id="selectedProductId"
    />

    <!-- Cart Modal -->
    <AffiliateCartModal
      v-model="cartDrawerOpen"
      @close="closeCartDrawer"
      @success="closeCartDrawer"
    />
  </div>
</template>

<style scoped>
.affiliate-catalogue {
  padding: 24px;
  max-width: 1400px;
  margin: 0 auto;
}

.catalogue-grid {
  margin-top: 0;
}

.catalogue-grid__item {
  display: flex;
  align-items: stretch;
}

.catalogue-grid__item > * {
  width: 100%;
}

@media (max-width: 768px) {
  .affiliate-catalogue {
    padding: 16px;
  }
}

@media (max-width: 600px) {
  .affiliate-catalogue {
    padding: 12px;
  }
}

/* Beautiful Cart Button Styles */
.cart-button-container {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  position: relative;
}

.cart-summary-chip {
  animation: slideInFromTop 0.3s ease-out;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}

.cart-button {
  position: relative;
  border-radius: 16px !important;
  padding: 12px 20px !important;
  box-shadow: 0 4px 12px rgba(var(--v-theme-primary), 0.3) !important;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
  background: linear-gradient(135deg, rgb(var(--v-theme-primary)), rgba(var(--v-theme-primary), 0.8)) !important;
}

.cart-button:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(var(--v-theme-primary), 0.4) !important;
}

.cart-button:active {
  transform: translateY(0);
}

.cart-button-text {
  font-weight: 600;
  font-size: 0.875rem;
  white-space: nowrap;
}

/* Animation */
@keyframes slideInFromTop {
  from {
    opacity: 0;
    transform: translateY(-10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* Responsive cart button */
@media (max-width: 768px) {
  .cart-button-text {
    display: none;
  }
  
  .cart-button {
    padding: 10px !important;
    min-width: 48px !important;
  }
  
  .cart-summary-chip {
    font-size: 0.75rem;
  }
}
</style>
