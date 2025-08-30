<script setup lang="ts">
import { computed, onMounted, onBeforeUnmount, ref, watch, onErrorCaptured } from 'vue'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'
import { useStockStore } from '@/stores/admin/stock'
import { useBoutiquesStore } from '@/stores/admin/boutiques'
import { useCategoriesStore } from '@/stores/admin/categories'
import { useNotifications } from '@/composables/useNotifications'
import { useQuickConfirm } from '@/composables/useConfirmAction'
import { useSafeNavigation } from '@/composables/useSafeNavigation'
import Breadcrumbs from '@/components/common/Breadcrumbs.vue'
import StockMovementDialog from '@/components/admin/stock/StockMovementDialog.vue'
import StockHistoryDialog from '@/components/admin/stock/StockHistoryDialog.vue'
import ConfirmActionDialog from '@/components/common/ConfirmActionDialog.vue'
import ErrorBoundary from '@/components/common/ErrorBoundary.vue'
import type { StockItem } from '@/types/admin/stock'

definePage({
  meta: {
    title: 'Stock Management',
    requiresAuth: true,
    requiresRole: 'admin',
  },
})

// Composables
const { t } = useI18n()
const { showSuccess, showError } = useNotifications()
const { safePush, checkEmergencyReset } = useSafeNavigation()

// Debug authentication
import { useAuthStore } from '@/stores/auth'
const authStore = useAuthStore()
console.log('🔐 [Stock Page] Auth status:', {
  isAuthenticated: authStore.isAuthenticated,
  hasToken: !!authStore.token,
  hasUser: !!authStore.user,
  tokenFromStorage: !!localStorage.getItem('auth_token'),
})
const {
  confirmCreate,
  isDialogVisible: isConfirmDialogVisible,
  isLoading: isConfirmLoading,
  dialogTitle,
  dialogText,
  dialogIcon,
  dialogColor,
  confirmButtonText,
  cancelButtonText,
  handleConfirm,
  handleCancel
} = useQuickConfirm()

// Stores
const stockStore = useStockStore()
const boutiquesStore = useBoutiquesStore()
const categoriesStore = useCategoriesStore()

const {
  items,
  summary,
  loading,
  summaryLoading,
  pagination,
  filters
} = storeToRefs(stockStore)

// Local state
const showMovementDialog = ref(false)
const showHistoryDialog = ref(false)
const selectedItem = ref<StockItem | null>(null)
const movementType = ref<'in' | 'out' | 'adjust'>('in')
const isMounted = ref(true)
const errorBoundaryRef = ref()

// Error handling
onErrorCaptured((err, instance, info) => {
  console.error('🚨 [Stock Page] Error captured:', err, info)

  // Check for navigation errors specifically
  const message = err.message || ''
  if (message.includes('navigation') || message.includes('router')) {
    checkEmergencyReset()
  }

  return false // Let error boundary handle it
})

// Computed with safe access
const breadcrumbs = computed(() => {
  try {
    return [
      { title: t('nav_dashboard'), to: 'admin-dashboard' },
      { title: t('stock.title'), to: 'admin-stock' },
    ]
  } catch (error) {
    return [
      { title: 'Dashboard', to: 'admin-dashboard' },
      { title: 'Stock', to: 'admin-stock' },
    ]
  }
})

// Stock statistics table
const stockStatsSearch = ref('')

const stockStatsHeaders = computed(() => [
  { title: t('product'), key: 'product', sortable: false, width: '35%' },
  { title: t('sku'), key: 'sku', sortable: true, width: '15%' },
  { title: t('on_hand'), key: 'on_hand', sortable: true, width: '12%' },
  { title: t('available'), key: 'available', sortable: true, width: '12%' },
  { title: t('reserved'), key: 'reserved', sortable: true, width: '12%' },
  { title: t('status'), key: 'status', sortable: false, width: '14%' },
])

const stockStatisticsTable = computed(() => {
  return items.value.map((item: any) => ({
    ...item,
    on_hand: item.metrics.on_hand,
    available: item.metrics.available,
    reserved: item.metrics.reserved,
  }))
})

// Stock status helpers
const getStockStatusColor = (available: number) => {
  if (available <= 0) return 'error'
  if (available <= 5) return 'warning'
  if (available <= 10) return 'info'
  return 'success'
}

const getStockStatusIcon = (available: number) => {
  if (available <= 0) return 'tabler-alert-circle'
  if (available <= 5) return 'tabler-alert-triangle'
  if (available <= 10) return 'tabler-info-circle'
  return 'tabler-circle-check'
}

const getStockStatusLabel = (available: number) => {
  if (available <= 0) return 'Rupture'
  if (available <= 5) return 'Faible'
  if (available <= 10) return 'Moyen'
  return 'Bon'
}

const headers = computed(() => {
  try {
    return [
      { title: t('stock.columns.product'), key: 'product', sortable: false, width: '22%', minWidth: '220px' },
      { title: 'SKU', key: 'sku', sortable: false, width: '10%', minWidth: '100px' },
      { title: t('stock.columns.variant'), key: 'variant', sortable: false, width: '13%', minWidth: '110px' },
      { title: t('stock.columns.category'), key: 'category', sortable: false, width: '10%', minWidth: '90px' },
      { title: t('stock.columns.boutique'), key: 'boutique', sortable: false, width: '10%', minWidth: '90px' },
      { title: t('stock.columns.on_hand'), key: 'on_hand', sortable: true, align: 'center' as const, width: '7%', minWidth: '70px' },
      { title: t('stock.columns.reserved'), key: 'reserved', sortable: true, align: 'center' as const, width: '7%', minWidth: '70px' },
      { title: t('stock.columns.available'), key: 'available', sortable: true, align: 'center' as const, width: '7%', minWidth: '70px' },
      { title: t('stock.columns.last_movement'), key: 'last_movement', sortable: false, width: '10%', minWidth: '100px' },
      { title: t('common.actions'), key: 'actions', sortable: false, align: 'center' as const, width: '4%', minWidth: '120px' },
    ]
  } catch (error) {
    return [
      { title: 'Product', key: 'product', sortable: false, width: '60%' },
      { title: 'Actions', key: 'actions', sortable: false, width: '40%' },
    ]
  }
})

const boutiques = computed(() => {
  try {
    return boutiquesStore.items || []
  } catch (error) {
    return []
  }
})

const categories = computed(() => {
  try {
    return categoriesStore.categories || []
  } catch (error) {
    return []
  }
})

// Methods
const fetchData = async () => {
  if (!isMounted.value) return

  try {
    await Promise.all([
      stockStore.fetchList(),
      stockStore.fetchSummary(),
      boutiquesStore.fetchBoutiques({ per_page: 100 }),
      categoriesStore.fetchCategories({ per_page: 100 }),
    ])
  } catch (error) {
    if (isMounted.value) {
      console.error('Fetch error:', error)
    }
  }
}

const handleTableUpdate = (options: any) => {
  stockStore.updateFilters({
    page: options.page,
    per_page: options.itemsPerPage,
    sort: options.sortBy?.[0]?.key,
    dir: options.sortBy?.[0]?.order,
  })
  // Don't call fetchList here - the watcher will handle it
}

const openMovementDialog = (item: StockItem, type: 'in' | 'out' | 'adjust') => {
  selectedItem.value = item
  movementType.value = type
  showMovementDialog.value = true
}

const openHistoryDialog = (item: StockItem) => {
  selectedItem.value = item
  showHistoryDialog.value = true
}

const handleMovementCreated = () => {
  showMovementDialog.value = false
  showSuccess(t('stock.messages.movement_created'))
}

const clearFilters = () => {
  stockStore.resetFilters()
  if (isMounted.value) {
    stockStore.fetchList()
  }
}

// Error handling methods
const handleError = (error: Error) => {
  console.error('🚨 [Stock Page] Error boundary caught error:', error)
  showError(t('common.error_occurred') || 'Error occurred')
}

const handleRetry = () => {
  console.log('🔄 [Stock Page] Retrying after error')
  fetchData()
}

// Watchers with debouncing to prevent multiple API calls
let debounceTimer: NodeJS.Timeout | null = null

const stopWatcher = watch(
  () => filters.value,
  () => {
    if (!isMounted.value) return

    // Clear previous timer
    if (debounceTimer) {
      clearTimeout(debounceTimer)
    }

    // Debounce the API call
    debounceTimer = setTimeout(() => {
      stockStore.fetchList()
    }, 300) // 300ms debounce
  },
  { deep: true }
)

// Lifecycle
onMounted(() => {
  fetchData()
})

onBeforeUnmount(() => {
  isMounted.value = false

  // Clear debounce timer
  if (debounceTimer) {
    clearTimeout(debounceTimer)
    debounceTimer = null
  }

  // Force close dialogs
  showMovementDialog.value = false
  showHistoryDialog.value = false
  selectedItem.value = null

  // Stop watcher
  stopWatcher()
})
</script>

<template>
  <ErrorBoundary ref="errorBoundaryRef" fallback-route="/admin/dashboard" :max-retries="3" @error="handleError"
    @retry="handleRetry">
    <div>
      <!-- Breadcrumbs -->
      <Breadcrumbs :items="breadcrumbs" class="mb-6" />



      <!-- Header with KPI Cards -->
      <VRow class="mb-6">
        <VCol cols="12">
          <VCard>
            <VCardText>
              <div class="d-flex justify-space-between align-center mb-4">
                <div>
                  <h2 class="text-h4 mb-2">{{ t('stock.title') }}</h2>
                  <p class="text-body-1 mb-0">{{ t('stock.subtitle') }}</p>
                </div>
              </div>

              <!-- KPI Cards -->
              <VRow v-if="summary">
                <VCol cols="12" sm="6" md="3">
                  <VCard color="primary" variant="tonal">
                    <VCardText class="text-center">
                      <VIcon icon="tabler-package" size="32" class="mb-2" />
                      <div class="text-h5 font-weight-bold">{{ summary.totals.products_count }}</div>
                      <div class="text-body-2">{{ t('stock.kpi.products') }}</div>
                    </VCardText>
                  </VCard>
                </VCol>
                <VCol cols="12" sm="6" md="3">
                  <VCard color="success" variant="tonal">
                    <VCardText class="text-center">
                      <VIcon icon="tabler-stack" size="32" class="mb-2" />
                      <div class="text-h5 font-weight-bold">{{ summary.totals.total_on_hand }}</div>
                      <div class="text-body-2">{{ t('stock.kpi.on_hand') }}</div>
                    </VCardText>
                  </VCard>
                </VCol>
                <VCol cols="12" sm="6" md="3">
                  <VCard color="warning" variant="tonal">
                    <VCardText class="text-center">
                      <VIcon icon="tabler-clock" size="32" class="mb-2" />
                      <div class="text-h5 font-weight-bold">{{ summary.totals.total_reserved }}</div>
                      <div class="text-body-2">{{ t('stock.kpi.reserved') }}</div>
                    </VCardText>
                  </VCard>
                </VCol>
                <VCol cols="12" sm="6" md="3">
                  <VCard color="info" variant="tonal">
                    <VCardText class="text-center">
                      <VIcon icon="tabler-check" size="32" class="mb-2" />
                      <div class="text-h5 font-weight-bold">{{ summary.totals.total_available }}</div>
                      <div class="text-body-2">{{ t('stock.kpi.available') }}</div>
                    </VCardText>
                  </VCard>
                </VCol>
              </VRow>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <!-- Stock Statistics Table -->
      <VCard class="mb-6">
        <VCardTitle class="d-flex align-center">
          <VIcon icon="tabler-chart-bar" class="me-2" />
          {{ t('stock_statistics_by_product') }}
        </VCardTitle>
        <VCardText>
          <VDataTable :headers="stockStatsHeaders" :items="stockStatisticsTable" :loading="loading" density="compact"
            :items-per-page="10" :search="stockStatsSearch">
            <template #top>
              <VTextField v-model="stockStatsSearch" :label="t('search_product')" prepend-inner-icon="tabler-search"
                variant="outlined" density="compact" class="mb-4" clearable />
            </template>

            <template #item.product="{ item }">
              <div class="d-flex align-center">
                <VAvatar size="32" class="me-3">
                  <VImg v-if="item.product.image" :src="item.product.image" />
                  <VIcon v-else icon="tabler-package" />
                </VAvatar>
                <div>
                  <div class="font-weight-medium">{{ item.product.titre }}</div>
                  <div class="text-caption text-medium-emphasis">{{ item.variant.libelle }}</div>
                </div>
              </div>
            </template>

            <template #item.sku="{ item }">
              <VChip size="small" variant="tonal" color="primary">
                {{ item.variant.sku }}
              </VChip>
            </template>

            <template #item.available="{ item }">
              <VChip size="small" :color="getStockStatusColor(item.metrics.available)" variant="tonal">
                {{ item.metrics.available }}
              </VChip>
            </template>

            <template #item.status="{ item }">
              <VChip size="small" :color="getStockStatusColor(item.metrics.available)" variant="tonal">
                <VIcon :icon="getStockStatusIcon(item.metrics.available)" size="12" class="me-1" />
                {{ getStockStatusLabel(item.metrics.available) }}
              </VChip>
            </template>
          </VDataTable>
        </VCardText>
      </VCard>

      <!-- Filters -->
      <VCard class="mb-6">
        <VCardText>
          <div class="d-flex justify-space-between align-center mb-4">
            <h3 class="text-h6">{{ t('stock.filters.title') }}</h3>
            <VBtn color="secondary" variant="outlined" size="small" prepend-icon="tabler-refresh" @click="clearFilters">
              {{ t('common.clear') }}
            </VBtn>
          </div>

          <VRow>
            <!-- Search - Full width on mobile, 1/3 on desktop -->
            <VCol cols="12" md="4">
              <VTextField v-model="filters.q" :label="t('stock.filters.search')" prepend-inner-icon="tabler-search"
                variant="outlined" density="compact" clearable :placeholder="t('stock.filters.search_placeholder')" />
            </VCol>

            <!-- Boutique - Half width on tablet, 1/4 on desktop -->
            <VCol cols="12" sm="6" md="3">
              <VSelect v-model="filters.boutique_id" :items="boutiques" :label="t('stock.filters.boutique')"
                item-title="nom" item-value="id" variant="outlined" density="compact" clearable />
            </VCol>

            <!-- Category - Half width on tablet, 1/4 on desktop -->
            <VCol cols="12" sm="6" md="3">
              <VSelect v-model="filters.categorie_id" :items="categories" :label="t('stock.filters.category')"
                item-title="nom" item-value="id" variant="outlined" density="compact" clearable />
            </VCol>

            <!-- With Variants Switch - Quarter width on tablet, 1/6 on desktop -->
            <VCol cols="12" sm="6" md="2">
              <VSwitch v-model="filters.with_variants" :label="t('stock.filters.with_variants')" color="primary"
                density="compact" />
            </VCol>
          </VRow>

          <!-- Quantity Filters Row -->
          <VRow>
            <VCol cols="12" sm="6" md="3">
              <VTextField v-model.number="filters.min_qty" :label="t('stock.filters.min_qty')" type="number"
                variant="outlined" density="compact" min="0" prepend-inner-icon="tabler-arrow-up" />
            </VCol>
            <VCol cols="12" sm="6" md="3">
              <VTextField v-model.number="filters.max_qty" :label="t('stock.filters.max_qty')" type="number"
                variant="outlined" density="compact" min="0" prepend-inner-icon="tabler-arrow-down" />
            </VCol>
          </VRow>
        </VCardText>
      </VCard>

      <!-- Data Table -->
      <VCard>
        <VCardTitle class="d-flex justify-space-between align-center">
          <span>{{ t('stock.table.title') }}</span>
          <VChip v-if="!loading" size="small" color="primary" variant="tonal">
            {{ pagination.total }} {{ t('stock.table.items') }}
          </VChip>
        </VCardTitle>

        <VDataTableServer :headers="headers" :items="items" :loading="loading" :items-per-page="filters.per_page"
          :page="filters.page" :items-length="pagination.total" @update:options="handleTableUpdate" hide-default-footer
          class="stock-table">

          <!-- Product Column -->
          <template #item.product="{ item }">
            <div class="d-flex align-center">
              <VAvatar color="grey-lighten-2" size="40" class="me-3">
                <VIcon icon="tabler-package" />
              </VAvatar>
              <div>
                <div class="font-weight-medium">{{ item.product.titre }}</div>
                <div class="text-caption text-medium-emphasis">{{ item.product.slug }}</div>
              </div>
            </div>
          </template>

          <!-- SKU Column -->
          <template #item.sku="{ item }">
            <VChip v-if="item.product.sku" size="small" color="secondary" variant="tonal" class="font-mono">
              {{ item.product.sku }}
            </VChip>
            <span v-else class="text-medium-emphasis">—</span>
          </template>

          <!-- Variant Column -->
          <template #item.variant="{ item }">
            <div v-if="item.variant">
              <VChip size="small" color="primary" variant="tonal">
                {{ item.variant.libelle }}
              </VChip>
            </div>
            <span v-else class="text-medium-emphasis">—</span>
          </template>

          <!-- Category Column -->
          <template #item.category="{ item }">
            <VChip v-if="item.product.categorie" size="small" variant="outlined">
              {{ item.product.categorie.nom }}
            </VChip>
            <span v-else class="text-medium-emphasis">—</span>
          </template>

          <!-- Boutique Column -->
          <template #item.boutique="{ item }">
            <VChip size="small" color="info" variant="tonal">
              {{ item.product.boutique.nom }}
            </VChip>
          </template>

          <!-- On Hand Column -->
          <template #item.on_hand="{ item }">
            <VChip size="small" :color="stockStore.getStockStatusColor(item.metrics.available, item.metrics.on_hand)"
              variant="tonal">
              {{ item.metrics.on_hand }}
            </VChip>
          </template>

          <!-- Reserved Column -->
          <template #item.reserved="{ item }">
            <VChip size="small" color="warning" variant="tonal">
              {{ item.metrics.reserved }}
            </VChip>
          </template>

          <!-- Available Column -->
          <template #item.available="{ item }">
            <VChip size="small" :color="stockStore.getStockStatusColor(item.metrics.available, item.metrics.on_hand)">
              {{ item.metrics.available }}
            </VChip>
          </template>

          <!-- Last Movement Column -->
          <template #item.last_movement="{ item }">
            <div v-if="item.metrics.last_movement_at">
              <div class="d-flex align-center">
                <VIcon :icon="stockStore.getMovementTypeIcon(item.metrics.last_movement_type || '')"
                  :color="stockStore.getMovementTypeColor(item.metrics.last_movement_type || '')" size="16"
                  class="me-1" />
                <span class="text-caption">{{ item.metrics.last_movement_type }}</span>
              </div>
              <div class="text-caption text-medium-emphasis">
                {{ $d(new Date(item.metrics.last_movement_at), 'short') }}
              </div>
            </div>
            <span v-else class="text-medium-emphasis">—</span>
          </template>

          <!-- Actions Column -->
          <template #item.actions="{ item }">
            <div class="d-flex gap-1">
              <VTooltip>
                <template #activator="{ props }">
                  <VBtn v-bind="props" icon size="small" color="success" variant="text"
                    @click="openMovementDialog(item, 'in')">
                    <VIcon icon="tabler-arrow-up" />
                  </VBtn>
                </template>
                <span>{{ t('stock.actions.in') }}</span>
              </VTooltip>

              <VTooltip>
                <template #activator="{ props }">
                  <VBtn v-bind="props" icon size="small" color="error" variant="text"
                    @click="openMovementDialog(item, 'out')">
                    <VIcon icon="tabler-arrow-down" />
                  </VBtn>
                </template>
                <span>{{ t('stock.actions.out') }}</span>
              </VTooltip>

              <VTooltip>
                <template #activator="{ props }">
                  <VBtn v-bind="props" icon size="small" color="warning" variant="text"
                    @click="openMovementDialog(item, 'adjust')">
                    <VIcon icon="tabler-adjustments" />
                  </VBtn>
                </template>
                <span>{{ t('stock.actions.adjust') }}</span>
              </VTooltip>

              <VTooltip>
                <template #activator="{ props }">
                  <VBtn v-bind="props" icon size="small" color="info" variant="text" @click="openHistoryDialog(item)">
                    <VIcon icon="tabler-history" />
                  </VBtn>
                </template>
                <span>{{ t('stock.actions.history') }}</span>
              </VTooltip>
            </div>
          </template>

          <!-- Loading State -->
          <template #loading>
            <div class="pa-4">
              <VSkeletonLoader v-for="i in 8" :key="i" type="table-row" class="mb-2" />
            </div>
          </template>

          <!-- No Data State -->
          <template #no-data>
            <div class="text-center py-12">
              <VIcon icon="tabler-package-off" size="64" color="grey-lighten-1" class="mb-4" />
              <h3 class="text-h6 mb-2">{{ t('stock.table.no_data_title') }}</h3>
              <p class="text-body-2 text-medium-emphasis mb-4">
                {{ t('stock.table.no_data_subtitle') }}
              </p>
              <VBtn color="primary" variant="outlined" prepend-icon="tabler-refresh" @click="clearFilters">
                {{ t('stock.table.reset_filters') }}
              </VBtn>
            </div>
          </template>
        </VDataTableServer>

        <!-- Pagination -->
        <VCardText>
          <VPagination v-model="filters.page" :length="pagination.last_page" :total-visible="7" />
        </VCardText>
      </VCard>

      <!-- Movement Dialog -->
      <StockMovementDialog v-if="showMovementDialog" v-model="showMovementDialog" :item="selectedItem"
        :movement-type="movementType" @saved="handleMovementCreated" />

      <!-- History Dialog -->
      <StockHistoryDialog v-if="showHistoryDialog" v-model="showHistoryDialog" :item="selectedItem" />

      <!-- Confirm Dialog -->
      <ConfirmActionDialog :is-dialog-visible="isConfirmDialogVisible" :is-loading="isConfirmLoading"
        :dialog-title="dialogTitle" :dialog-text="dialogText" :dialog-icon="dialogIcon" :dialog-color="dialogColor"
        :confirm-button-text="confirmButtonText" :cancel-button-text="cancelButtonText" @confirm="handleConfirm"
        @cancel="handleCancel" />
    </div>
  </ErrorBoundary>
</template>

<style scoped>
.stock-table {
  /* Enable horizontal scroll on small screens */
  overflow-x: auto;
}

/* Table column width enforcement */
.stock-table :deep(.v-data-table__wrapper) {
  min-width: 800px;
  /* Ensure minimum table width */
}

.stock-table :deep(.v-data-table__th),
.stock-table :deep(.v-data-table__td) {
  /* Respect column width settings */
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* Product column - allow text wrapping for long names */
.stock-table :deep(.v-data-table__th:first-child),
.stock-table :deep(.v-data-table__td:first-child) {
  white-space: normal;
  word-wrap: break-word;
  max-width: 250px;
}

/* Responsive table improvements */
@media (max-width: 768px) {
  .stock-table :deep(.v-data-table__wrapper) {
    overflow-x: auto;
  }

  .stock-table :deep(.v-data-table-header) {
    white-space: nowrap;
  }

  .stock-table :deep(.v-data-table__td) {
    white-space: nowrap;
    min-width: 120px;
  }

  /* On mobile, product column should still be readable */
  .stock-table :deep(.v-data-table__td:first-child) {
    min-width: 200px;
    max-width: 250px;
  }
}

/* Sticky header for better UX */
.stock-table :deep(.v-data-table-header) {
  position: sticky;
  top: 0;
  z-index: 1;
  background: rgb(var(--v-theme-surface));
}
</style>

<route lang="yaml">
meta:
  requiresAuth: true
  requiresRole: ['admin']
  layout: admin
</route>
