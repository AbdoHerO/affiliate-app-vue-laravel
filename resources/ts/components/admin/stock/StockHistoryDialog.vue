<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'
import { useStockStore } from '@/stores/admin/stock'
import type { StockItem, StockHistoryFilters } from '@/types/admin/stock'
import { MOVEMENT_TYPES, MOVEMENT_REASONS } from '@/types/admin/stock'

interface Props {
  modelValue: boolean
  item?: StockItem | null
}

interface Emits {
  (e: 'update:modelValue', value: boolean): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

// Composables
const { t, d } = useI18n()
const stockStore = useStockStore()

const {
  history,
  historyLoading,
  historyPagination
} = storeToRefs(stockStore)

// Local state
const filters = ref<StockHistoryFilters>({
  variante_id: '',
  entrepot_id: '',
  type: undefined,
  reason: undefined,
  date_from: '',
  date_to: '',
  per_page: 15,
})

// Computed
const isOpen = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
})

const dialogTitle = computed(() => {
  if (!props.item) return t('stock.history.title')
  return t('stock.history.title_for', { product: props.item.product.titre })
})

const headers = computed(() => [
  { title: t('stock.history.columns.date'), key: 'created_at', sortable: false },
  { title: t('stock.history.columns.type'), key: 'type', sortable: false },
  { title: t('stock.history.columns.quantity'), key: 'quantity', sortable: false, align: 'center' as const },
  { title: t('stock.history.columns.reference'), key: 'reference', sortable: false },
  { title: t('stock.history.columns.variant'), key: 'variant', sortable: false },
])

const movementTypeOptions = computed(() => [
  { value: '', label: t('common.all') },
  ...MOVEMENT_TYPES.map(type => ({ value: type.value, label: type.label }))
])

const reasonOptions = computed(() => [
  { value: '', label: t('common.all') },
  ...MOVEMENT_REASONS.map(reason => ({ value: reason.value, label: reason.label }))
])

// Methods
const fetchHistory = async () => {
  if (!props.item) {
    console.log('❌ [StockHistoryDialog] No item provided')
    return
  }

  // Stock history requires a variant ID - every stock item should have one
  if (!props.item.variant?.id) {
    console.error('❌ [StockHistoryDialog] No variant ID found for stock item:', props.item)
    return
  }

  const variantId = props.item.variant.id

  console.log('🔍 [StockHistoryDialog] Fetching history for:', {
    productId: props.item.product.id,
    productTitle: props.item.product.titre,
    variantId: variantId,
    variantLabel: props.item.variant.libelle,
    filters: filters.value
  })

  const cleanFilters = { ...filters.value }

  // Remove empty values
  Object.keys(cleanFilters).forEach(key => {
    if (cleanFilters[key as keyof StockHistoryFilters] === '') {
      delete cleanFilters[key as keyof StockHistoryFilters]
    }
  })

  console.log('📡 [StockHistoryDialog] Making API call with variant ID:', variantId, 'and filters:', cleanFilters)

  try {
    await stockStore.fetchHistory(variantId, cleanFilters)
    console.log('✅ [StockHistoryDialog] History fetched successfully')
  } catch (error) {
    console.error('❌ [StockHistoryDialog] Failed to fetch history:', error)
  }
}

const clearFilters = () => {
  filters.value = {
    variante_id: '',
    entrepot_id: '',
    type: undefined,
    reason: undefined,
    date_from: '',
    date_to: '',
    per_page: 15,
  }
  fetchHistory()
}

const close = () => {
  isOpen.value = false
}

// Watchers
watch(
  () => props.modelValue,
  (newValue) => {
    if (newValue && props.item) {
      // Set variant filter if specific variant
      if (props.item.variant) {
        filters.value.variante_id = props.item.variant.id
      }
      fetchHistory()
    }
  }
)

watch(
  () => filters.value,
  () => {
    if (props.modelValue && props.item) {
      fetchHistory()
    }
  },
  { deep: true }
)
</script>

<template>
  <VDialog
    v-model="isOpen"
    max-width="1000"
    scrollable
  >
    <VCard>
      <VCardTitle class="d-flex align-center">
        <VIcon icon="tabler-history" class="me-2" />
        {{ dialogTitle }}
      </VCardTitle>

      <VCardText>
        <!-- Product Info -->
        <VAlert
          v-if="item"
          type="info"
          variant="tonal"
          class="mb-4"
        >
          <div class="d-flex align-center">
            <div class="flex-grow-1">
              <div class="font-weight-medium">{{ item.product.titre }}</div>
              <div v-if="item.variant" class="text-caption">{{ item.variant.libelle }}</div>
            </div>
            <div class="text-end">
              <div class="text-caption">{{ t('stock.current_stock') }}</div>
              <div class="d-flex gap-2">
                <VChip size="x-small" color="success">{{ t('stock.on_hand') }}: {{ item.metrics.on_hand }}</VChip>
                <VChip size="x-small" color="warning">{{ t('stock.reserved') }}: {{ item.metrics.reserved }}</VChip>
                <VChip size="x-small" color="info">{{ t('stock.available') }}: {{ item.metrics.available }}</VChip>
              </div>
            </div>
          </div>
        </VAlert>

        <!-- Filters -->
        <VExpansionPanels class="mb-4">
          <VExpansionPanel>
            <VExpansionPanelTitle>
              <VIcon icon="tabler-filter" class="me-2" />
              {{ t('common.filters') }}
            </VExpansionPanelTitle>
            <VExpansionPanelText>
              <VRow>
                <VCol cols="12" md="3">
                  <VSelect
                    v-model="filters.type"
                    :items="movementTypeOptions"
                    :label="t('stock.filters.type')"
                    item-title="label"
                    item-value="value"
                    variant="outlined"
                    density="compact"
                  />
                </VCol>
                <VCol cols="12" md="3">
                  <VSelect
                    v-model="filters.reason"
                    :items="reasonOptions"
                    :label="t('stock.filters.reason')"
                    item-title="label"
                    item-value="value"
                    variant="outlined"
                    density="compact"
                  />
                </VCol>
                <VCol cols="12" md="3">
                  <VTextField
                    v-model="filters.date_from"
                    :label="t('stock.filters.date_from')"
                    type="date"
                    variant="outlined"
                    density="compact"
                  />
                </VCol>
                <VCol cols="12" md="3">
                  <VTextField
                    v-model="filters.date_to"
                    :label="t('stock.filters.date_to')"
                    type="date"
                    variant="outlined"
                    density="compact"
                  />
                </VCol>
                <VCol cols="12">
                  <VBtn
                    color="secondary"
                    variant="outlined"
                    size="small"
                    @click="clearFilters"
                  >
                    {{ t('common.clear_filters') }}
                  </VBtn>
                </VCol>
              </VRow>
            </VExpansionPanelText>
          </VExpansionPanel>
        </VExpansionPanels>

        <!-- History Table -->
        <VDataTable
          :headers="headers"
          :items="history"
          :loading="historyLoading"
          :no-data-text="t('stock.history.no_movements')"
          hide-default-footer
        >
          <!-- Date Column -->
          <template #item.created_at="{ item }">
            <div>
              <div class="font-weight-medium">{{ d(new Date(item.created_at), 'short') }}</div>
              <div class="text-caption text-medium-emphasis">{{ d(new Date(item.created_at), 'time') }}</div>
            </div>
          </template>

          <!-- Type Column -->
          <template #item.type="{ item }">
            <VChip
              size="small"
              :color="stockStore.getMovementTypeColor(item.type)"
              :prepend-icon="stockStore.getMovementTypeIcon(item.type)"
            >
              {{ MOVEMENT_TYPES.find(t => t.value === item.type)?.label || item.type }}
            </VChip>
          </template>

          <!-- Quantity Column -->
          <template #item.quantity="{ item }">
            <VChip
              size="small"
              :color="stockStore.getMovementTypeColor(item.type)"
              variant="tonal"
            >
              {{ stockStore.formatMovementQuantity(item.type, item.quantity) }}
            </VChip>
          </template>

          <!-- Reference Column -->
          <template #item.reference="{ item }">
            <VChip
              v-if="item.reference"
              size="small"
              variant="outlined"
            >
              {{ item.reference }}
            </VChip>
            <span v-else class="text-medium-emphasis">—</span>
          </template>

          <!-- Variant Column -->
          <template #item.variant="{ item }">
            <VChip
              size="small"
              color="primary"
              variant="tonal"
            >
              {{ item.variant.libelle }}
            </VChip>
          </template>

          <!-- Loading State -->
          <template #loading>
            <VSkeletonLoader type="table-row@5" />
          </template>

          <!-- No Data State -->
          <template #no-data>
            <div class="text-center py-8">
              <VIcon icon="tabler-history-off" size="64" class="mb-4" color="disabled" />
              <h6 class="text-h6 mb-2">{{ t('stock.history.no_movements') }}</h6>
              <p class="text-body-2">{{ t('stock.history.no_movements_desc') }}</p>
            </div>
          </template>
        </VDataTable>

        <!-- Pagination -->
        <div v-if="historyPagination.total > 0" class="d-flex justify-center mt-4">
          <VPagination
            :model-value="historyPagination.current_page"
            :length="historyPagination.last_page"
            :total-visible="5"
            @update:model-value="(page) => { filters.per_page = page; fetchHistory() }"
          />
        </div>
      </VCardText>

      <VCardActions>
        <VSpacer />
        <VBtn
          variant="outlined"
          @click="close"
        >
          {{ t('common.close') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>