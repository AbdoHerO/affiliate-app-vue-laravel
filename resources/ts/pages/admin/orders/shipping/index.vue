<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
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

// Computed
const isLoading = computed(() => shippingStore.isLoading)
const shippingOrders = computed(() => shippingStore.shippingOrders)
const pagination = computed(() => shippingStore.pagination)

// Table headers
const headers = [
  { title: 'Code', key: 'id', sortable: true },
  { title: 'Tracking', key: 'tracking_number', sortable: true },
  { title: 'Client', key: 'client', sortable: false },
  { title: 'Ville', key: 'city', sortable: false },
  { title: 'Statut', key: 'status', sortable: true },
  { title: 'Total', key: 'total_ttc', sortable: true },
  { title: 'Mis à jour', key: 'updated_at', sortable: true },
  { title: 'Actions', key: 'actions', sortable: false },
]

// Status options
const statusOptions = [
  { title: 'Tous', value: '' },
  { title: 'Créé', value: 'created' },
  { title: 'En cours', value: 'in_transit' },
  { title: 'Livré', value: 'delivered' },
  { title: 'Retourné', value: 'returned' },
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

const showDeliveryNoteDialog = ref(false)
const showOzonDialog = ref(false)
const ozonDialogLoading = ref(false)
const currentOrderForResend = ref<any>(null)

const viewTracking = async (order: any) => {
  currentTrackingNumber.value = order.shipping_parcel.tracking_number
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
      parcel_info: parcelInfo
    }
    showTrackingModal.value = true
  } catch (error: any) {
    trackingError.value = error.message || 'Erreur lors de la récupération du tracking'
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

    trackingData.value = {
      tracking_info: trackingInfo,
      parcel_info: parcelInfo
    }
    showSuccess('Suivi actualisé avec succès')
  } catch (error: any) {
    trackingError.value = error.message || 'Erreur lors de l\'actualisation du tracking'
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
      showSuccess('Suivi mis à jour avec succès')
    } else {
      showError(result.message || 'Erreur lors de la mise à jour du suivi')
    }
  } catch (error: any) {
    showError(error.message || 'Erreur lors de la mise à jour du suivi')
  } finally {
    refreshingTracking.value = refreshingTracking.value.filter(tn => tn !== trackingNumber)
  }
}

const refreshTrackingBulk = async () => {
  if (selectedOrders.value.length === 0) {
    showError('Veuillez sélectionner au moins une commande')
    return
  }

  const confirmed = await confirm({
    title: 'Actualiser le suivi',
    text: `Voulez-vous actualiser le suivi de ${selectedOrders.value.length} commande(s) ?`,
    confirmText: 'Actualiser',
    cancelText: 'Annuler'
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
    showSuccess(result.message || 'Suivi mis à jour avec succès')
    selectedOrders.value = []
  } catch (error: any) {
    showError(error.message || 'Erreur lors de la mise à jour du suivi')
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
    showSuccess(`Commande renvoyée vers OzonExpress en mode ${mode === 'ramassage' ? 'Ramassage' : 'Stock'}`)
    // Refresh the list to show updated data
    await fetchShippingOrders()
  } catch (error: any) {
    showError(error.message || 'Erreur lors du renvoi vers OzonExpress')
  } finally {
    ozonDialogLoading.value = false
  }
}

const handleOzonCancel = () => {
  // Dialog will close automatically
}

const createDeliveryNote = async () => {
  if (selectedOrders.value.length === 0) {
    showError('Veuillez sélectionner au moins une commande')
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
    case 'created':
    case 'received':
    case 'ready_for_delivery': return 'warning'
    case 'cancelled':
    case 'refused':
    case 'delivery_failed': return 'error'
    case 'returned':
    case 'return_delivered': return 'secondary'
    default: return 'default'
  }
}

const getStatusText = (status: string) => {
  const statusLabels: Record<string, string> = {
    'pending': 'En Attente',
    'created': 'Créé',
    'received': 'Reçu',
    'in_transit': 'En Transit',
    'out_for_delivery': 'En Cours de Livraison',
    'delivered': 'Livré',
    'returned': 'Retourné',
    'refused': 'Refusé',
    'cancelled': 'Annulé',
    'shipped': 'Expédié',
    'at_facility': 'Arrivé au Centre',
    'ready_for_delivery': 'Prêt pour Livraison',
    'delivery_attempted': 'Tentative de Livraison',
    'delivery_failed': 'Échec de Livraison',
    'return_in_progress': 'Retour en Cours',
    'return_delivered': 'Retour Livré',
    'unknown': 'Statut Inconnu'
  }
  return statusLabels[status?.toLowerCase()] || status || 'Inconnu'
}

const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('fr-MA', {
    style: 'currency',
    currency: 'MAD',
  }).format(amount)
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
</script>

<template>
  <div class="d-flex flex-column gap-6">
    <!-- Page Header -->
    <div class="d-flex justify-space-between align-center mb-6">
      <div>
        <h1 class="text-h4 font-weight-bold mb-1">
          Commandes Expédiées
        </h1>
        <p class="text-body-1 mb-0">
          Gestion des commandes envoyées vers OzonExpress
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
          Actualiser Suivi ({{ selectedOrders.length }})
        </VBtn>
        <VBtn
          color="secondary"
          variant="outlined"
          :disabled="selectedOrders.length === 0"
          @click="createDeliveryNote"
        >
          <VIcon start icon="tabler-file-plus" />
          Bon de Livraison ({{ selectedOrders.length }})
        </VBtn>
        <VBtn
          color="primary"
          variant="elevated"
          @click="resetFilters"
        >
          <VIcon start icon="tabler-refresh" />
          Actualiser
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
              label="Rechercher..."
              placeholder="Client, téléphone, tracking..."
              prepend-inner-icon="tabler-search"
              clearable
              @input="handleSearch"
            />
          </VCol>
          <VCol cols="12" md="2">
            <VSelect
              v-model="selectedStatus"
              label="Statut"
              :items="statusOptions"
              clearable
              @update:model-value="handleSearch"
            />
          </VCol>
          <VCol cols="12" md="2">
            <VTextField
              v-model="dateFrom"
              label="Date début"
              type="date"
              @change="handleSearch"
            />
          </VCol>
          <VCol cols="12" md="2">
            <VTextField
              v-model="dateTo"
              label="Date fin"
              type="date"
              @change="handleSearch"
            />
          </VCol>
          <VCol cols="12" md="2">
            <VSelect
              v-model="itemsPerPage"
              label="Par page"
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
              {{ item.client.nom_complet }}
            </div>
            <div class="text-caption text-medium-emphasis">
              {{ item.client.telephone }}
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
            <VBtn
              size="small"
              color="primary"
              variant="text"
              icon="tabler-eye"
              @click="viewShippingOrder(item)"
            >
              <VTooltip activator="parent" location="top">
                Voir les détails
              </VTooltip>
            </VBtn>

            <!-- If no tracking number, show resend button -->
            <VBtn
              v-if="!item.shipping_parcel.tracking_number"
              size="small"
              color="warning"
              variant="text"
              icon="tabler-truck"
              @click="resendToOzonExpress(item)"
            >
              <VTooltip activator="parent" location="top">
                Renvoyer vers OzonExpress
              </VTooltip>
            </VBtn>

            <!-- If has tracking number, show tracking actions -->
            <template v-else>
              <VBtn
                size="small"
                color="info"
                variant="text"
                icon="tabler-route"
                :loading="trackingLoading"
                @click="viewTracking(item)"
              >
                <VTooltip activator="parent" location="top">
                  Voir le suivi
                </VTooltip>
              </VBtn>
              <VBtn
                size="small"
                color="success"
                variant="text"
                icon="tabler-refresh"
                :loading="refreshingTracking.includes(item.shipping_parcel.tracking_number)"
                @click="refreshTracking(item)"
              >
                <VTooltip activator="parent" location="top">
                  Actualiser le suivi
                </VTooltip>
              </VBtn>
            </template>
            <VMenu>
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
                  <VListItemTitle>PDF Standard</VListItemTitle>
                </VListItem>
                <VListItem
                  v-if="item.shipping_parcel.delivery_note_ref"
                  @click="openPDF(item.shipping_parcel.delivery_note_ref, 'a4')"
                >
                  <VListItemTitle>Étiquettes A4</VListItemTitle>
                </VListItem>
                <VListItem
                  v-if="item.shipping_parcel.delivery_note_ref"
                  @click="openPDF(item.shipping_parcel.delivery_note_ref, '100x100')"
                >
                  <VListItemTitle>Étiquettes 100x100</VListItemTitle>
                </VListItem>
                <VListItem v-if="!item.shipping_parcel.delivery_note_ref">
                  <VListItemTitle class="text-medium-emphasis">
                    Aucun bon de livraison
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
            <h3 class="text-h6 mb-2">Aucune commande expédiée</h3>
            <p class="text-body-2 text-medium-emphasis">
              Aucune commande envoyée vers OzonExpress trouvée
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
      @refresh="refreshTrackingModal"
      @retry="refreshTrackingModal"
    />

    <!-- OzonExpress Resend Dialog -->
    <OzonExpressConfirmDialog
      v-model="showOzonDialog"
      :loading="ozonDialogLoading"
      title="Renvoyer vers OzonExpress"
      text="Êtes-vous sûr de vouloir renvoyer cette commande vers OzonExpress ?"
      default-mode="ramassage"
      @confirm="handleOzonConfirm"
      @cancel="handleOzonCancel"
    />

    <!-- Delivery Note Dialog -->
    <DeliveryNoteDialog
      v-model="showDeliveryNoteDialog"
      :selected-tracking-numbers="selectedOrders
        .map(orderId => {
          const order = shippingOrders.find(o => o.id === orderId)
          return order?.shipping_parcel?.tracking_number
        })
        .filter(Boolean) as string[]"
      @created="handleDeliveryNoteCreated"
    />
  </div>
</template>
