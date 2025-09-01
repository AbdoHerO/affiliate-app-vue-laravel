<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRouter, onBeforeRouteLeave } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useShippingStore } from '@/stores/admin/shipping'
import { useConfirmAction } from '@/composables/useConfirmAction'
import { useNotifications } from '@/composables/useNotifications'
import TrackingModal from '@/components/dialogs/TrackingModal.vue'
import DeliveryNoteDialog from '@/components/dialogs/DeliveryNoteDialog.vue'
import OzonExpressConfirmDialog from '@/components/dialogs/OzonExpressConfirmDialog.vue'

definePage({
  meta: {
    requiresAuth: true,
    requiresRole: 'admin',
  },
})

const { t } = useI18n()
const router = useRouter()
const shippingStore = useShippingStore()
const { confirm } = useConfirmAction()
const { showSuccess, showError } = useNotifications()

// Local state
const searchQuery = ref('')
const selectedStatus = ref('')
const dateFrom = ref('')
const dateTo = ref('')
const itemsPerPage = ref(15)
const selectedOrders = ref<string[]>([])
const refreshingTracking = ref<string[]>([])

// Status update state
const showStatusUpdateDialog = ref(false)
const statusUpdateLoading = ref(false)
const currentOrderId = ref('')
const newStatus = ref('')
const statusNote = ref('')

// Stock management state
const shippingActionLoading = ref<string | null>(null)
const showReturnConfirmDialog = ref(false)
const returnReason = ref('')
const currentReturnOrder = ref<any>(null)

// Delivery boy quick edit state
const showDeliveryBoyDialog = ref(false)
const deliveryBoyLoading = ref(false)
const currentDeliveryBoyOrder = ref<any>(null)
const quickDeliveryBoyName = ref('')
const quickDeliveryBoyPhone = ref('')

// Computed
const isLoading = computed(() => shippingStore.isLoading)
const shippingOrders = computed(() => shippingStore.shippingOrders)
const pagination = computed(() => shippingStore.pagination)

// Table headers
const headers = [
  { title: t('tracking'), key: 'tracking_number', sortable: true },
  { title: t('client'), key: 'client', sortable: false },
  { title: t('city'), key: 'city', sortable: false },
  { title: 'SKU', key: 'sku_list', sortable: false, width: '150px' },
  { title: t('order_type'), key: 'type_command', sortable: false },
  { title: t('status'), key: 'status', sortable: true },
  { title: t('total'), key: 'total_ttc', sortable: true },
  { title: t('updated'), key: 'updated_at', sortable: true },
  { title: t('actions'), key: 'actions', sortable: false },
]

// Status options - consistent with OzonExpress mapping
const statusOptions = [
  { title: t('all'), value: '' },
  { title: t('pending'), value: 'pending' },
  { title: t('received'), value: 'received' },
  { title: t('in_transit'), value: 'in_transit' },
  { title: t('shipped'), value: 'shipped' },
  { title: t('at_facility'), value: 'at_facility' },
  { title: t('ready_for_delivery'), value: 'ready_for_delivery' },
  { title: t('out_for_delivery'), value: 'out_for_delivery' },
  { title: t('delivery_attempted'), value: 'delivery_attempted' },
  { title: t('delivered'), value: 'delivered' },
  { title: t('returned'), value: 'returned' },
  { title: t('refused'), value: 'refused' },
  { title: t('cancelled'), value: 'cancelled' },
  { title: t('unknown'), value: 'unknown' },
]

// Methods
const fetchShippingOrders = async () => {
  await shippingStore.fetchShippingOrders({
    q: searchQuery.value || undefined,
    status: selectedStatus.value || undefined,
    from: dateFrom.value || undefined,
    to: dateTo.value || undefined,
    perPage: itemsPerPage.value,
  })
}

// Simple debounce implementation
let debounceTimer: NodeJS.Timeout
const debouncedFetch = () => {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(fetchShippingOrders, 300)
}

const handleSearch = () => {
  shippingStore.fetchShippingOrders({
    page: 1,
    q: searchQuery.value || undefined,
    status: selectedStatus.value || undefined,
    from: dateFrom.value || undefined,
    to: dateTo.value || undefined,
    perPage: itemsPerPage.value,
  })
}

const handlePageChange = (page: number) => {
  shippingStore.fetchShippingOrders({ page })
}

const handleSort = (sortBy: any) => {
  if (sortBy.length > 0) {
    shippingStore.fetchShippingOrders({
      sort: sortBy[0].key,
      dir: sortBy[0].order,
    })
  }
}

const viewShippingOrder = (order: any) => {
  router.push({ name: 'admin-orders-shipping-id', params: { id: order.id } })
}

// Dialog states
const showTrackingModal = ref(false)
const trackingData = ref<any>(null)
const trackingLoading = ref(false)
const trackingError = ref('')
const currentTrackingNumber = ref('')
const currentTrackingOrder = ref<any>(null)

const showDeliveryNoteDialog = ref(false)
const showOzonDialog = ref(false)
const ozonDialogLoading = ref(false)
const currentOrderForResend = ref<any>(null)

const viewTracking = async (order: any) => {
  currentTrackingNumber.value = order.shipping_parcel.tracking_number
  currentTrackingOrder.value = order
  trackingLoading.value = true
  trackingError.value = ''

  try {
    // Get both tracking and parcel info
    const [trackingInfo, parcelInfo] = await Promise.all([
      shippingStore.trackParcel(order.shipping_parcel.tracking_number),
      shippingStore.getParcelInfoNew(order.shipping_parcel.tracking_number)
    ])

    trackingData.value = {
      tracking_info: trackingInfo,
      parcel_info: parcelInfo,
      parcel: order.shipping_parcel // Include the local parcel data with mapped status
    }
    showTrackingModal.value = true
  } catch (error: any) {
    trackingError.value = error.message || t('admin_shipping_error_tracking')
    showError(trackingError.value)
  } finally {
    trackingLoading.value = false
  }
}

const refreshTrackingModal = async () => {
  if (!currentTrackingNumber.value) return

  trackingLoading.value = true
  trackingError.value = ''

  try {
    const [trackingInfo, parcelInfo] = await Promise.all([
      shippingStore.trackParcel(currentTrackingNumber.value),
      shippingStore.getParcelInfoNew(currentTrackingNumber.value)
    ])

    // Find the updated parcel data from the store
    const updatedOrder = shippingOrders.value.find(order =>
      order.shipping_parcel?.tracking_number === currentTrackingNumber.value
    )

    trackingData.value = {
      tracking_info: trackingInfo,
      parcel_info: parcelInfo,
      parcel: updatedOrder?.shipping_parcel // Include updated parcel data
    }
    showSuccess(t('admin_shipping_tracking_updated'))

    // Refresh the orders list to get updated status
    await fetchShippingOrders()
  } catch (error: any) {
    trackingError.value = error.message || t('admin_shipping_error_tracking_refresh')
    showError(trackingError.value)
  } finally {
    trackingLoading.value = false
  }
}

const refreshTracking = async (order: any) => {
  const trackingNumber = order.shipping_parcel.tracking_number
  refreshingTracking.value.push(trackingNumber)

  try {
    const result = await shippingStore.refreshTracking(trackingNumber)
    if (result.success) {
      showSuccess(t('admin_shipping_tracking_updated'))
    } else {
      showError(result.message || t('admin_shipping_error_tracking_update'))
    }
  } catch (error: any) {
    showError(error.message || t('admin_shipping_error_tracking_update'))
  } finally {
    refreshingTracking.value = refreshingTracking.value.filter(tn => tn !== trackingNumber)
  }
}

const refreshTrackingBulk = async () => {
  if (selectedOrders.value.length === 0) {
    showError(t('admin_shipping_error_select_order'))
    return
  }

  const confirmed = await confirm({
    title: t('admin_shipping_tracking_refresh'),
    text: t('admin_shipping_tracking_refresh_confirm', { count: selectedOrders.value.length }),
    confirmText: t('admin_shipping_button_refresh'),
    cancelText: t('admin_shipping_button_cancel')
  })

  if (!confirmed) return

  try {
    const trackingNumbers = selectedOrders.value
      .map(orderId => {
        const order = shippingOrders.value.find(o => o.id === orderId)
        return order?.shipping_parcel?.tracking_number
      })
      .filter(Boolean) as string[]

    const result = await shippingStore.refreshTrackingBulk(trackingNumbers)
    showSuccess(result.message || t('admin_shipping_tracking_updated'))
    selectedOrders.value = []
  } catch (error: any) {
    showError(error.message || t('admin_shipping_error_tracking_update'))
  }
}

const resendToOzonExpress = async (order: any) => {
  currentOrderForResend.value = order
  showOzonDialog.value = true
}

const handleOzonConfirm = async (mode: 'ramassage' | 'stock') => {
  if (!currentOrderForResend.value) return

  ozonDialogLoading.value = true
  try {
    await shippingStore.resendToOzon(currentOrderForResend.value.id, mode)
    const modeText = mode === 'ramassage' ? t('admin_shipping_mode_ramassage') : t('admin_shipping_mode_stock')
    showSuccess(t('admin_shipping_ozonexpress_resend', { mode: modeText }))
    // Refresh the list to show updated data
    await fetchShippingOrders()
  } catch (error: any) {
    showError(error.message || t('admin_shipping_error_ozonexpress_resend'))
  } finally {
    ozonDialogLoading.value = false
  }
}

const handleOzonCancel = () => {
  // Dialog will close automatically
}

// Status update methods
const openStatusUpdateDialog = (order: any) => {
  currentOrderId.value = order.id
  newStatus.value = order.shipping_parcel?.status || ''
  statusNote.value = ''
  showStatusUpdateDialog.value = true
}

const updateShippingStatus = async () => {
  if (!newStatus.value || !currentOrderId.value) return

  statusUpdateLoading.value = true
  try {
    await shippingStore.updateShippingStatus(currentOrderId.value, {
      status: newStatus.value,
      note: statusNote.value || undefined
    })

    showSuccess(`Statut mis à jour vers: ${getStatusText(newStatus.value)}` +
                (newStatus.value === 'livree' ? ' (Commission créée automatiquement)' : ''))
    showStatusUpdateDialog.value = false

    // Refresh the orders list
    await fetchShippingOrders()
  } catch (error: any) {
    showError(error.message || 'Erreur lors de la mise à jour du statut')
  } finally {
    statusUpdateLoading.value = false
  }
}

const cancelStatusUpdate = () => {
  showStatusUpdateDialog.value = false
  currentOrderId.value = ''
  newStatus.value = ''
  statusNote.value = ''
}

// Stock management functions
const canShipOrder = (order: any): boolean => {
  return ['confirmee', 'en_attente'].includes(order.statut)
}

const canReturnToWarehouse = (order: any): boolean => {
  return ['expediee', 'livree', 'retournee'].includes(order.statut)
}

const handleShipOrder = async (order: any) => {
  const confirmed = await confirm({
    title: 'Expédier la commande',
    text: `Êtes-vous sûr de vouloir expédier la commande ${order.id} ?\n\nCette action va décrémenter le stock des produits.`,
    confirmText: 'Expédier',
    cancelText: 'Annuler',
    type: 'warning'
  })

  if (!confirmed) return

  shippingActionLoading.value = order.id
  try {
    await shippingStore.decrementStock(order.id)
    showSuccess('Commande expédiée avec succès et stock décrémenté')
    await fetchShippingOrders()
  } catch (error: any) {
    showError(error.message || 'Erreur lors de l\'expédition')
  } finally {
    shippingActionLoading.value = null
  }
}

const handleReturnToWarehouse = (order: any) => {
  currentReturnOrder.value = order
  returnReason.value = ''
  showReturnConfirmDialog.value = true
}

const confirmReturnToWarehouse = async () => {
  if (!currentReturnOrder.value) return

  shippingActionLoading.value = currentReturnOrder.value.id
  try {
    await shippingStore.incrementStock(currentReturnOrder.value.id, returnReason.value)
    showSuccess('Commande retournée en entrepôt avec succès et stock ré-incrémenté')
    showReturnConfirmDialog.value = false
    await fetchShippingOrders()
  } catch (error: any) {
    showError(error.message || 'Erreur lors du retour en entrepôt')
  } finally {
    shippingActionLoading.value = null
    currentReturnOrder.value = null
  }
}

const cancelReturnToWarehouse = () => {
  showReturnConfirmDialog.value = false
  currentReturnOrder.value = null
  returnReason.value = ''
}

// Delivery boy quick edit methods
const openDeliveryBoyDialog = (order: any) => {
  currentDeliveryBoyOrder.value = order
  quickDeliveryBoyName.value = order.delivery_boy_name || ''
  quickDeliveryBoyPhone.value = order.delivery_boy_phone || ''
  showDeliveryBoyDialog.value = true
}

const saveQuickDeliveryBoyInfo = async () => {
  if (!currentDeliveryBoyOrder.value) return

  deliveryBoyLoading.value = true
  try {
    await shippingStore.updateDeliveryBoyInfo(currentDeliveryBoyOrder.value.id, {
      delivery_boy_name: quickDeliveryBoyName.value.trim() || null,
      delivery_boy_phone: quickDeliveryBoyPhone.value.trim() || null
    })

    showSuccess(t('delivery_person_info_updated_success'))
    showDeliveryBoyDialog.value = false
    await fetchShippingOrders()
  } catch (error: any) {
    showError(error.message || t('error_updating'))
  } finally {
    deliveryBoyLoading.value = false
  }
}

const cancelDeliveryBoyDialog = () => {
  showDeliveryBoyDialog.value = false
  currentDeliveryBoyOrder.value = null
  quickDeliveryBoyName.value = ''
  quickDeliveryBoyPhone.value = ''
}

const createDeliveryNote = async () => {
  if (selectedOrders.value.length === 0) {
    showError(t('please_select_at_least_one_order'))
    return
  }

  showDeliveryNoteDialog.value = true
}

const handleDeliveryNoteCreated = (ref: string) => {
  showSuccess(`Bon de livraison créé avec succès: ${ref}`)
  selectedOrders.value = []
  // Refresh the list to show updated delivery note refs
  fetchShippingOrders()
}

const getStatusColor = (status: string) => {
  switch (status?.toLowerCase()) {
    case 'delivered': return 'success'
    case 'shipped':
    case 'in_transit':
    case 'out_for_delivery': return 'info'
    case 'pending':
    case 'received':
    case 'at_facility':
    case 'ready_for_delivery': return 'warning'
    case 'cancelled':
    case 'refused':
    case 'delivery_attempted': return 'error'
    case 'returned': return 'secondary'
    case 'returned_to_warehouse': return 'info'
    case 'unknown': return 'default'
    default: return 'default'
  }
}

const getShippingStatusLabel = (status: any) => {
  const statusLabels: Record<string, string> = {
    'pending': t('admin_shipping_status_pending'),
    'en_attente': t('admin_shipping_status_pending'),
    'in_progress': t('admin_shipping_status_in_progress'),
    'en_cours': t('admin_shipping_status_in_progress'),
    'confirmed': t('admin_shipping_status_confirmed'),
    'confirmee': t('admin_shipping_status_confirmed_fem'),
    'picked_up': t('admin_shipping_status_picked_up'),
    'collectee': t('admin_shipping_status_picked_up_fem'),
    'shipped': t('admin_shipping_status_shipped'),
    'in_transit': t('admin_shipping_status_in_transit'),
    'at_facility': t('admin_shipping_status_at_facility'),

    'expediee': t('admin_shipping_status_expedited'),
    'ready_for_delivery': t('admin_shipping_status_ready_delivery'),
    'out_for_delivery': t('admin_shipping_status_out_delivery'),
    'delivery_attempted': t('admin_shipping_status_delivery_attempted'),
    'delivered': t('admin_shipping_status_delivered'),
    'livree': t('admin_shipping_status_delivered_fem'),
    'returned': t('order.status.returned'),
    'retournee': t('order.status.returned_feminine'),
    'returned_to_warehouse': t('order.status.returned_to_warehouse'),
    'refused': t('admin_shipping_status_refused'),
    'refusee': t('admin_shipping_status_refused_fem'),
    'cancelled': t('admin_shipping_status_cancelled'),
    'annulee': t('admin_shipping_status_cancelled_fem'),
    'unknown': t('admin_shipping_status_unknown'),
  }
  return statusLabels[status?.toLowerCase()] || status || t('admin_shipping_status_unknown')
}

const getStatusText = (status: string) => {
  return getShippingStatusLabel(status)
}

const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('fr-MA', {
    style: 'currency',
    currency: 'MAD',
  }).format(amount)
}

const getOrderTypeColor = (type: string) => {
  const colors: Record<string, string> = {
    'order_sample': 'primary',
    'exchange': 'warning'
  }
  return colors[type] || 'secondary'
}

const getOrderTypeLabel = (type: string) => {
  const labels: Record<string, string> = {
    'order_sample': t('order_sample'),
    'exchange': t('exchange')
  }
  return labels[type] || type || 'N/A'
}

const formatDate = (date: string) => {
  return new Date(date).toLocaleDateString('fr-FR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

// Client Final helpers
const getClientFinalName = (item: any) => {
  return item.client_final_data?.nom_complet || item.client?.nom_complet || t('admin_shipping_client_na')
}

const getClientFinalPhone = (item: any) => {
  return item.client_final_data?.telephone || item.client?.telephone || t('admin_shipping_client_na')
}

const getClientFinalCity = (item: any) => {
  return item.client_final_data?.ville || item.shipping_parcel?.city_name || item.adresse?.ville || null
}

const resetFilters = () => {
  searchQuery.value = ''
  selectedStatus.value = ''
  dateFrom.value = ''
  dateTo.value = ''
  shippingStore.resetFilters()
  fetchShippingOrders()
}

const openPDF = (ref: string, type: string) => {
  let url = ''
  switch (type) {
    case 'pdf':
      url = `https://client.ozoneexpress.ma/pdf-delivery-note?dn-ref=${ref}`
      break
    case 'a4':
      url = `https://client.ozoneexpress.ma/pdf-delivery-note-tickets?dn-ref=${ref}`
      break
    case '100x100':
      url = `https://client.ozoneexpress.ma/pdf-delivery-note-tickets-4-4?dn-ref=${ref}`
      break
  }
  if (url) {
    window.open(url, '_blank')
  }
}

// Lifecycle
onMounted(() => {
  fetchShippingOrders()
})

// Navigation guard to close dialogs before leaving
onBeforeRouteLeave((_to, _from, next) => {
  console.log('🚪 [Navigation] Leaving shipping orders page')

  try {
    // Close all dialogs to prevent white screen issue
    showStatusUpdateDialog.value = false
    showReturnConfirmDialog.value = false
    showDeliveryBoyDialog.value = false
    showTrackingModal.value = false

    console.log('✅ [Navigation] Cleanup completed successfully')
    next()
  } catch (error) {
    console.error('❌ [Navigation] Error during cleanup:', error)
    // Still allow navigation even if cleanup fails
    next()
  }
})
</script>

<template>
  <div class="d-flex flex-column gap-6">
    <!-- Page Header -->
    <div class="d-flex justify-space-between align-center mb-6">
      <div>
        <h1 class="text-h4 font-weight-bold mb-1">
          {{ t('admin_shipping_title') }}
        </h1>
        <p class="text-body-1 mb-0">
          {{ t('admin_shipping_description') }}
        </p>
      </div>
      <div class="d-flex gap-2">
        <VBtn
          color="success"
          variant="outlined"
          :disabled="selectedOrders.length === 0"
          @click="refreshTrackingBulk"
        >
          <VIcon start icon="tabler-refresh" />
          {{ t('admin_shipping_button_refresh_bulk', { count: selectedOrders.length }) }}
        </VBtn>
        <!-- TEMPORARILY HIDDEN: Delivery Note Button -->
        <VBtn
          v-if="false"
          color="secondary"
          variant="outlined"
          :disabled="selectedOrders.length === 0"
          @click="createDeliveryNote"
        >
          <VIcon start icon="tabler-file-plus" />
          {{ t('admin_shipping_button_delivery_note_bulk', { count: selectedOrders.length }) }}
        </VBtn>
        <VBtn
          color="primary"
          variant="elevated"
          @click="resetFilters"
        >
          <VIcon start icon="tabler-refresh" />
          {{ t('admin_shipping_button_refresh') }}
        </VBtn>
      </div>
    </div>

    <!-- Filters Card -->
    <VCard class="mb-6">
      <VCardText>
        <VRow>
          <VCol cols="12" md="3">
            <VTextField
              v-model="searchQuery"
              :label="t('search')"
              :placeholder="t('admin_shipping_search_placeholder')"
              prepend-inner-icon="tabler-search"
              clearable
              @input="handleSearch"
            />
          </VCol>
          <VCol cols="12" md="2">
            <VSelect
              v-model="selectedStatus"
              :label="t('status')"
              :items="statusOptions"
              clearable
              @update:model-value="handleSearch"
            />
          </VCol>
          <VCol cols="12" md="2">
            <VTextField
              v-model="dateFrom"
              :label="t('date_from')"
              type="date"
              @change="handleSearch"
            />
          </VCol>
          <VCol cols="12" md="2">
            <VTextField
              v-model="dateTo"
              :label="t('date_to')"
              type="date"
              @change="handleSearch"
            />
          </VCol>
          <VCol cols="12" md="2">
            <VSelect
              v-model="itemsPerPage"
              :label="t('per_page')"
              :items="[10, 15, 25, 50]"
              @update:model-value="handleSearch"
            />
          </VCol>
          <VCol cols="12" md="1">
            <VBtn
              color="secondary"
              variant="outlined"
              block
              @click="resetFilters"
            >
              <VIcon icon="tabler-filter-off" />
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Data Table -->
    <VCard>
      <VDataTableServer
        v-model:items-per-page="itemsPerPage"
        v-model="selectedOrders"
        :headers="headers"
        :items="shippingOrders"
        :items-length="pagination.total"
        :loading="isLoading"
        :page="pagination.current_page"
        show-select
        item-value="id"
        @update:page="handlePageChange"
        @update:sort-by="handleSort"
      >
        <!-- Tracking Column -->
        <template #item.tracking_number="{ item }">
          <div class="d-flex align-center gap-2">
            <VChip
              size="small"
              color="primary"
              variant="tonal"
              class="font-mono"
            >
              {{ item.shipping_parcel.tracking_number }}
            </VChip>
            <VBtn
              size="x-small"
              icon="tabler-eye"
              variant="text"
              @click="viewTracking(item)"
            />
          </div>
        </template>

        <!-- Client Column -->
        <template #item.client="{ item }">
          <div>
            <div class="font-weight-medium">
              {{ getClientFinalName(item) }}
            </div>
            <div class="text-caption text-medium-emphasis">
              {{ getClientFinalPhone(item) }}
            </div>
            <div v-if="getClientFinalCity(item)" class="text-caption text-info">
              {{ getClientFinalCity(item) }}
            </div>
          </div>
        </template>

        <!-- City Column -->
        <template #item.city="{ item }">
          <VChip
            size="small"
            color="info"
            variant="tonal"
          >
            {{ item.shipping_parcel.city_name || item.adresse.ville }}
          </VChip>
        </template>

        <!-- SKU List Column -->
        <template #item.sku_list="{ item }">
          <div class="d-flex flex-wrap gap-1">
            <VChip
              v-for="article in item.articles || []"
              :key="article.id"
              size="x-small"
              color="secondary"
              variant="outlined"
              class="font-mono"
            >
              {{ article.produit.sku || 'N/A' }}
            </VChip>
          </div>
        </template>

        <!-- Type Command Column -->
        <template #item.type_command="{ item }">
          <VChip
            size="small"
            :color="getOrderTypeColor(item.type_command)"
            variant="tonal"
          >
            {{ getOrderTypeLabel(item.type_command) }}
          </VChip>
        </template>

        <!-- Status Column -->
        <template #item.status="{ item }">
          <VChip
            size="small"
            :color="getStatusColor(item.shipping_parcel.status)"
            variant="tonal"
          >
            {{ getStatusText(item.shipping_parcel.status) }}
          </VChip>
        </template>

        <!-- Total Column -->
        <template #item.total_ttc="{ item }">
          <div class="font-weight-bold">
            {{ formatCurrency(item.total_ttc) }}
          </div>
        </template>

        <!-- Updated At Column -->
        <template #item.updated_at="{ item }">
          <div class="text-body-2">
            {{ formatDate(item.updated_at) }}
          </div>
        </template>

        <!-- Actions Column -->
        <template #item.actions="{ item }">
          <div class="d-flex gap-1">
            <!-- View Order Details -->
            <VTooltip location="top">
              <template #activator="{ props }">
                <VBtn
                  v-bind="props"
                  size="small"
                  color="primary"
                  variant="text"
                  icon="tabler-eye"
                  @click="viewShippingOrder(item)"
                />
              </template>
              <span>{{ t('actions.viewDetails') }}</span>
            </VTooltip>

            <!-- If has tracking number, show tracking actions -->
            <template v-if="item.shipping_parcel?.tracking_number">
              <VTooltip location="top">
                <template #activator="{ props }">
                  <VBtn
                    v-bind="props"
                    size="small"
                    color="info"
                    variant="text"
                    icon="tabler-route"
                    :loading="trackingLoading"
                    @click="viewTracking(item)"
                  />
                </template>
                <span>{{ t('actions.viewTracking') }}</span>
              </VTooltip>

              <VTooltip location="top">
                <template #activator="{ props }">
                  <VBtn
                    v-bind="props"
                    size="small"
                    color="success"
                    variant="text"
                    icon="tabler-refresh"
                    :loading="refreshingTracking.includes(item.shipping_parcel?.tracking_number)"
                    @click="refreshTracking(item)"
                  />
                </template>
                <span>{{ t('admin_shipping_tooltip_refresh_tracking') }}</span>
              </VTooltip>
            </template>

            <!-- TEMPORARILY HIDDEN: Stock Management Buttons -->
            <template v-if="false && canShipOrder(item)">
              <VTooltip location="top">
                <template #activator="{ props }">
                  <VBtn
                    v-bind="props"
                    size="small"
                    color="success"
                    variant="text"
                    icon="tabler-truck"
                    :loading="shippingActionLoading === item.id"
                    @click="shipOrder(item)"
                  />
                </template>
                <span>{{ t('admin_shipping_tooltip_ship_order') }}</span>
              </VTooltip>
            </template>

            <!-- Delivery Boy Info Button -->
            <VTooltip location="top">
              <template #activator="{ props }">
                <VBtn
                  v-bind="props"
                  size="small"
                  :color="item.delivery_boy_name ? 'success' : 'info'"
                  variant="text"
                  icon="tabler-user"
                  @click="openDeliveryBoyDialog(item)"
                />
              </template>
              <span>{{ item.delivery_boy_name ? t('edit_delivery_person') : t('add_delivery_person') }}</span>
            </VTooltip>

            <!-- Status Update Button (only for local deliveries) -->
            <template v-if="!item.shipping_parcel?.sent_to_carrier">
              <VTooltip location="top">
                <template #activator="{ props }">
                  <VBtn
                    v-bind="props"
                    size="small"
                    color="warning"
                    variant="text"
                    icon="tabler-edit"
                    @click="openStatusUpdateDialog(item)"
                  />
                </template>
                <span>{{ t('admin_shipping_tooltip_modify_status') }}</span>
              </VTooltip>
            </template>

            <!-- TEMPORARILY HIDDEN: Delivery Note Actions -->
            <VMenu v-if="false">
              <template #activator="{ props }">
                <VBtn
                  size="small"
                  color="secondary"
                  variant="text"
                  icon="tabler-file-download"
                  v-bind="props"
                />
              </template>
              <VList>
                <VListItem
                  v-if="item.shipping_parcel.delivery_note_ref"
                  @click="openPDF(item.shipping_parcel.delivery_note_ref, 'pdf')"
                >
                  <VListItemTitle>{{ t('pdf_standard') }}</VListItemTitle>
                </VListItem>
                <VListItem
                  v-if="item.shipping_parcel.delivery_note_ref"
                  @click="openPDF(item.shipping_parcel.delivery_note_ref, 'a4')"
                >
                  <VListItemTitle>{{ t('labels_a4') }}</VListItemTitle>
                </VListItem>
                <VListItem
                  v-if="item.shipping_parcel.delivery_note_ref"
                  @click="openPDF(item.shipping_parcel.delivery_note_ref, '100x100')"
                >
                  <VListItemTitle>{{ t('labels_100x100') }}</VListItemTitle>
                </VListItem>
                <VListItem v-if="!item.shipping_parcel.delivery_note_ref">
                  <VListItemTitle class="text-medium-emphasis">
                    {{ t('no_delivery_note') }}
                  </VListItemTitle>
                </VListItem>
              </VList>
            </VMenu>
          </div>
        </template>

        <!-- No data -->
        <template #no-data>
          <div class="text-center py-8">
            <VIcon
              icon="tabler-truck-off"
              size="64"
              class="mb-4"
              color="disabled"
            />
            <h3 class="text-h6 mb-2">{{ t('no_shipped_orders') }}</h3>
            <p class="text-body-2 text-medium-emphasis">
              {{ t('no_orders_sent_ozonexpress') }}
            </p>
          </div>
        </template>
      </VDataTableServer>
    </VCard>

    <!-- Enhanced Tracking Modal -->
    <TrackingModal
      v-model="showTrackingModal"
      :tracking-number="currentTrackingNumber"
      :tracking-data="trackingData"
      :loading="trackingLoading"
      :error="trackingError"
      :delivery-boy-name="currentTrackingOrder?.delivery_boy_name"
      :delivery-boy-phone="currentTrackingOrder?.delivery_boy_phone"
      @refresh="refreshTrackingModal"
      @retry="refreshTrackingModal"
    />

    <!-- OzonExpress Resend Dialog -->
    <OzonExpressConfirmDialog
      v-model="showOzonDialog"
      :loading="ozonDialogLoading"
      :title="t('admin_shipping_resend_ozon_title')"
      :text="t('admin_shipping_resend_ozon_text')"
      default-mode="ramassage"
      @confirm="handleOzonConfirm"
      @cancel="handleOzonCancel"
    />

    <!-- TEMPORARILY HIDDEN: Delivery Note Dialog -->
    <DeliveryNoteDialog
      v-if="false"
      v-model="showDeliveryNoteDialog"
      :selected-tracking-numbers="selectedOrders
        .map(orderId => {
          const order = shippingOrders.find(o => o.id === orderId)
          return order?.shipping_parcel?.tracking_number
        })
        .filter(Boolean) as string[]"
      @created="handleDeliveryNoteCreated"
    />

    <!-- Status Update Dialog -->
    <VDialog
      v-model="showStatusUpdateDialog"
      max-width="500"
      persistent
    >
      <VCard>
        <VCardTitle class="text-h6">
          <VIcon start icon="tabler-edit" color="warning" />
          {{ t('admin_shipping_modify_status_title') }}
        </VCardTitle>

        <VCardText>
          <p class="mb-4">
            {{ t('admin_shipping_modify_status_description') }}
            <strong>{{ t('admin_shipping_modify_status_note') }}:</strong> {{ t('admin_shipping_modify_status_note_commission') }}
          </p>

          <VSelect
            v-model="newStatus"
            :label="t('admin_shipping_new_status')"
            :items="[
              { title: t('admin_shipping_status_pending'), value: 'pending' },
              { title: t('admin_shipping_status_shipped'), value: 'expediee' },
              { title: t('admin_shipping_status_delivered_fem'), value: 'livree' },
              { title: t('admin_shipping_status_refused_fem'), value: 'refusee' },
              { title: t('order.status.returned_feminine'), value: 'retournee' },
              { title: t('admin_shipping_status_cancelled_fem'), value: 'annulee' }
            ]"
            variant="outlined"
            class="mb-4"
          />

          <VTextarea
            v-model="statusNote"
            :label="t('admin_shipping_note_optional')"
            :placeholder="t('admin_shipping_note_placeholder')"
            rows="3"
            variant="outlined"
          />
        </VCardText>

        <VCardActions>
          <VSpacer />
          <VBtn
            variant="text"
            @click="cancelStatusUpdate"
            :disabled="statusUpdateLoading"
          >
            {{ t('admin_shipping_cancel') }}
          </VBtn>
          <VBtn
            color="warning"
            variant="elevated"
            :loading="statusUpdateLoading"
            :disabled="!newStatus"
            @click="updateShippingStatus"
          >
            <VIcon start icon="tabler-check" />
            {{ t('admin_shipping_update') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Return to Warehouse Confirmation Dialog -->
    <VDialog
      v-model="showReturnConfirmDialog"
      max-width="500"
      persistent
    >
      <VCard>
        <VCardTitle class="d-flex align-center">
          <VIcon icon="tabler-alert-triangle" color="warning" class="me-2" />
          Retour en entrepôt
        </VCardTitle>

        <VCardText>
          <VAlert
            type="warning"
            variant="tonal"
            class="mb-4"
          >
            <VAlertTitle>Attention !</VAlertTitle>
            Cette action va ré-incrémenter le stock des produits de cette commande.
            Assurez-vous que les produits sont physiquement retournés en entrepôt.
          </VAlert>

          <p class="mb-4">
            Commande: <strong>{{ currentReturnOrder?.id }}</strong>
          </p>

          <VTextarea
            v-model="returnReason"
            label="Raison du retour (optionnel)"
            placeholder="Ex: Produit défectueux, refus client, etc."
            rows="3"
            variant="outlined"
          />
        </VCardText>

        <VCardActions>
          <VSpacer />
          <VBtn
            variant="text"
            @click="cancelReturnToWarehouse"
            :disabled="shippingActionLoading === currentReturnOrder?.id"
          >
            Annuler
          </VBtn>
          <VBtn
            color="warning"
            variant="elevated"
            :loading="shippingActionLoading === currentReturnOrder?.id"
            @click="confirmReturnToWarehouse"
          >
            <VIcon start icon="tabler-package-import" />
            Confirmer le retour
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Quick Delivery Boy Edit Dialog -->
    <VDialog
      v-model="showDeliveryBoyDialog"
      max-width="500"
      persistent
    >
      <VCard>
        <VCardTitle class="d-flex align-center">
          <VIcon icon="tabler-user" color="info" class="me-2" />
          {{ t('delivery_person_information') }}
        </VCardTitle>

        <VCardText>
          <VRow>
            <VCol cols="12">
              <VTextField
                v-model="quickDeliveryBoyName"
                :label="t('delivery_person_name')"
                :placeholder="t('delivery_person_name_placeholder')"
                variant="outlined"
                prepend-inner-icon="tabler-user"
                :disabled="deliveryBoyLoading"
              />
            </VCol>
            <VCol cols="12">
              <VTextField
                v-model="quickDeliveryBoyPhone"
                :label="t('delivery_person_phone')"
                :placeholder="t('delivery_person_phone_placeholder')"
                variant="outlined"
                prepend-inner-icon="tabler-phone"
                :disabled="deliveryBoyLoading"
              />
            </VCol>
          </VRow>
        </VCardText>

        <VCardActions>
          <VSpacer />
          <VBtn
            variant="text"
            @click="cancelDeliveryBoyDialog"
            :disabled="deliveryBoyLoading"
          >
            {{ t('cancel') }}
          </VBtn>
          <VBtn
            color="primary"
            variant="elevated"
            :loading="deliveryBoyLoading"
            @click="saveQuickDeliveryBoyInfo"
          >
            <VIcon start icon="tabler-device-floppy" />
            {{ t('save') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
