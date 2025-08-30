<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter, onBeforeRouteLeave } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useShippingStore } from '@/stores/admin/shipping'
import { useConfirmAction } from '@/composables/useConfirmAction'
import { useNotifications } from '@/composables/useNotifications'
import OrderStatusTimeline from '@/components/orders/OrderStatusTimeline.vue'

definePage({
  meta: {
    requiresAuth: true,
    requiresRole: 'admin',
  },
})

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const shippingStore = useShippingStore()
const { confirm } = useConfirmAction()
const { showSuccess, showError } = useNotifications()

// Local state
const activeTab = ref('parcel')
const trackingData = ref<any>(null)
const trackingLoading = ref(false)
const parcelInfo = ref(null)
const deliveryNoteRef = ref('')
const selectedParcels = ref<string[]>([])

// Manual status update state
const showStatusUpdateDialog = ref(false)
const statusUpdateLoading = ref(false)
const newStatus = ref('')
const statusNote = ref('')

// Delivery boy information state
const deliveryBoyName = ref('')
const deliveryBoyPhone = ref('')
const deliveryBoyLoading = ref(false)

// Computed
const isLoading = computed(() => shippingStore.isLoading)
const shippingOrder = computed(() => shippingStore.currentShippingOrder)
const orderId = computed(() => route.params.id as string)

// Methods
const fetchShippingOrder = async () => {
  await shippingStore.fetchShippingOrder(orderId.value)
}

const fetchTracking = async () => {
  if (!shippingOrder.value?.shipping_parcel?.tracking_number) return

  trackingLoading.value = true
  try {
    trackingData.value = await shippingStore.getTracking(
      shippingOrder.value.shipping_parcel.tracking_number
    )
    showSuccess(t('admin_shipping_tracking_success'))
  } catch (error: any) {
    showError(error.message || t('admin_shipping_tracking_error'))
  } finally {
    trackingLoading.value = false
  }
}

const fetchParcelInfo = async () => {
  if (!shippingOrder.value?.shipping_parcel?.tracking_number) return

  try {
    parcelInfo.value = await shippingStore.getParcelInfo(
      shippingOrder.value.shipping_parcel.tracking_number
    )
  } catch (error: any) {
    showError(error.message || t('admin_shipping_parcel_info_error'))
  }
}

const createDeliveryNote = async () => {
  const confirmed = await confirm({
    title: t('admin_shipping_delivery_note_create_confirm_title'),
    text: t('admin_shipping_delivery_note_create_confirm_text'),
    confirmText: t('admin_shipping_delivery_note_create_confirm_button'),
    color: 'primary',
  })

  if (confirmed) {
    try {
      const ref = await shippingStore.createDeliveryNote()
      deliveryNoteRef.value = ref
      showSuccess(`Bon de livraison créé: ${ref}`)
    } catch (error: any) {
      showError(error.message || t('admin_shipping_delivery_note_create_error'))
    }
  }
}

const addParcelsToDeliveryNote = async () => {
  if (!deliveryNoteRef.value || !shippingOrder.value?.shipping_parcel?.tracking_number) {
    showError(t('admin_shipping_delivery_note_required'))
    return
  }

  const confirmed = await confirm({
    title: 'Ajouter le colis au bon de livraison',
    text: `Ajouter le colis ${shippingOrder.value.shipping_parcel.tracking_number} au bon ${deliveryNoteRef.value} ?`,
    confirmText: 'Ajouter',
    color: 'primary',
  })

  if (confirmed) {
    try {
      await shippingStore.addParcelsToDeliveryNote(
        deliveryNoteRef.value,
        [shippingOrder.value.shipping_parcel.tracking_number]
      )
      showSuccess(t('admin_shipping_parcel_added'))
    } catch (error: any) {
      showError(error.message || t('admin_shipping_add_parcel_error'))
    }
  }
}

const saveDeliveryNote = async () => {
  if (!deliveryNoteRef.value) {
    showError(t('admin_shipping_delivery_note_no_save'))
    return
  }

  const confirmed = await confirm({
    title: 'Sauvegarder le bon de livraison',
    text: `Sauvegarder le bon de livraison ${deliveryNoteRef.value} ?`,
    confirmText: 'Sauvegarder',
    color: 'success',
  })

  if (confirmed) {
    try {
      await shippingStore.saveDeliveryNote(deliveryNoteRef.value)
      showSuccess(t('admin_shipping_delivery_note_saved'))
      // Update the shipping order to reflect the delivery note
      await fetchShippingOrder()
    } catch (error: any) {
      showError(error.message || t('admin_shipping_save_error'))
    }
  }
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

// Manual status update methods
const openStatusUpdateDialog = () => {
  newStatus.value = shippingOrder.value?.shipping_parcel?.status || ''
  statusNote.value = ''
  showStatusUpdateDialog.value = true
}

const updateShippingStatus = async () => {
  if (!newStatus.value || !shippingOrder.value) return

  statusUpdateLoading.value = true
  try {
    await shippingStore.updateShippingStatus(shippingOrder.value.id, {
      status: newStatus.value,
      note: statusNote.value || undefined
    })

    showSuccess(`Statut mis à jour vers: ${getStatusText(newStatus.value)}` +
                (newStatus.value === 'livree' ? ' (Commission créée automatiquement)' : ''))
    showStatusUpdateDialog.value = false

    // Refresh the order data
    await fetchShippingOrder()
  } catch (error: any) {
    showError(error.message || 'Erreur lors de la mise à jour du statut')
  } finally {
    statusUpdateLoading.value = false
  }
}

const cancelStatusUpdate = () => {
  showStatusUpdateDialog.value = false
  newStatus.value = ''
  statusNote.value = ''
}

const getStatusColor = (status: string) => {
  switch (status) {
    case 'created':
      return 'info'
    case 'in_transit':
      return 'warning'
    case 'delivered':
      return 'success'
    case 'returned':
      return 'error'
    default:
      return 'default'
  }
}

const getStatusText = (status: string) => {
  switch (status) {
    case 'created':
      return 'Créé'
    case 'in_transit':
      return 'En transit'
    case 'delivered':
      return 'Livré'
    case 'returned':
      return t('order.status.returned')
    default:
      return status
  }
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

// Tracking data parsing helpers
const getTrackingNumber = computed(() => {
  return trackingData.value?.tracking_info?.TRACKING?.['TRACKING-NUMBER'] || ''
})

const getLastTracking = computed(() => {
  return trackingData.value?.tracking_info?.TRACKING?.LAST_TRACKING || null
})

const getTrackingHistory = computed(() => {
  const history = trackingData.value?.tracking_info?.TRACKING?.HISTORY
  if (!history) return []

  // Convert object to array and sort by time (newest first)
  return Object.values(history)
    .filter((item: any) => item && item.TIME_STR)
    .sort((a: any, b: any) => {
      // Sort by TIME_STR in descending order (newest first)
      return new Date(b.TIME_STR).getTime() - new Date(a.TIME_STR).getTime()
    })
})

const getParcelSummary = computed(() => {
  return trackingData.value?.parcel_info?.PARCEL_INFO?.INFOS || null
})

const getTrackingStatusColor = (status: string) => {
  if (!status) return 'secondary'

  const statusLower = status.toLowerCase()

  // Map Ozon STATUT to badge colors
  if (statusLower.includes('nouveau') || statusLower.includes('créé')) return 'info'
  if (statusLower.includes('en cours') || statusLower.includes('transit')) return 'warning'
  if (statusLower.includes('livré')) return 'success'
  if (statusLower.includes(t('order.status.returned').toLowerCase()) || statusLower.includes('refusé')) return 'error'

  return 'secondary'
}

const formatDateTime = (dateStr: string) => {
  if (!dateStr) return ''

  try {
    const date = new Date(dateStr)
    return date.toLocaleDateString('fr-FR', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    })
  } catch {
    return dateStr
  }
}

const copyTrackingNumber = async () => {
  const trackingNumber = getTrackingNumber.value
  if (!trackingNumber) return

  try {
    await navigator.clipboard.writeText(trackingNumber)
    showSuccess(t('admin_shipping_tracking_copied'))
  } catch (error) {
    showError(t('admin_shipping_copy_error'))
  }
}

const goBack = () => {
  router.push({ name: 'admin-orders-shipping' })
}

// Client Final helpers
const getClientFinalName = () => {
  return shippingOrder.value?.client_final_data?.nom_complet ||
         shippingOrder.value?.shipping_parcel?.receiver ||
         shippingOrder.value?.client?.nom_complet || '-'
}

const getClientFinalPhone = () => {
  return shippingOrder.value?.client_final_data?.telephone ||
         shippingOrder.value?.shipping_parcel?.phone ||
         shippingOrder.value?.client?.telephone || '-'
}

const getClientFinalEmail = () => {
  return shippingOrder.value?.client_final_data?.email ||
         shippingOrder.value?.client?.email || null
}

const getClientFinalCity = () => {
  return shippingOrder.value?.client_final_data?.ville ||
         shippingOrder.value?.shipping_parcel?.city_name ||
         shippingOrder.value?.adresse?.ville || '-'
}

const getClientFinalAddress = () => {
  return shippingOrder.value?.client_final_data?.adresse ||
         shippingOrder.value?.shipping_parcel?.address ||
         shippingOrder.value?.adresse?.adresse || '-'
}

const getClientFinalPostalCode = () => {
  return shippingOrder.value?.client_final_data?.code_postal ||
         shippingOrder.value?.adresse?.code_postal || null
}

const getClientFinalCountry = () => {
  return shippingOrder.value?.client_final_data?.pays ||
         shippingOrder.value?.adresse?.pays || 'MA'
}

// Copy address functionality
const copyAddress = async () => {
  const address = `${getClientFinalName()}
${getClientFinalPhone()}
${getClientFinalAddress()}
${getClientFinalCity()}, ${getClientFinalCountry()}`

  try {
    await navigator.clipboard.writeText(address)
    showSuccess(t('admin_shipping_address_copied'))
  } catch (error) {
    showError(t('admin_shipping_address_copy_failed'))
  }
}

// Delivery boy methods
const saveDeliveryBoyInfo = async () => {
  if (!deliveryBoyName.value.trim() && !deliveryBoyPhone.value.trim()) {
    showError('Veuillez saisir au moins le nom ou le téléphone du livreur')
    return
  }

  deliveryBoyLoading.value = true
  try {
    await shippingStore.updateDeliveryBoyInfo(orderId.value, {
      delivery_boy_name: deliveryBoyName.value.trim() || null,
      delivery_boy_phone: deliveryBoyPhone.value.trim() || null
    })

    showSuccess('Informations du livreur enregistrées avec succès')
    await fetchShippingOrder() // Refresh data
  } catch (error: any) {
    showError(error.message || 'Erreur lors de l\'enregistrement')
  } finally {
    deliveryBoyLoading.value = false
  }
}

const resetDeliveryBoyInfo = () => {
  deliveryBoyName.value = shippingOrder.value?.delivery_boy_name || ''
  deliveryBoyPhone.value = shippingOrder.value?.delivery_boy_phone || ''
}

const loadDeliveryBoyInfo = () => {
  if (shippingOrder.value) {
    deliveryBoyName.value = shippingOrder.value.delivery_boy_name || ''
    deliveryBoyPhone.value = shippingOrder.value.delivery_boy_phone || ''
  }
}

// Lifecycle
onMounted(async () => {
  await fetchShippingOrder()
  loadDeliveryBoyInfo()
})

// Navigation guard to close dialogs before leaving
onBeforeRouteLeave((_to, _from, next) => {
  console.log('🚪 [Navigation] Leaving shipping order detail page')

  try {
    // Close all dialogs to prevent white screen issue
    showStatusUpdateDialog.value = false

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
    <!-- Loading State -->
    <div v-if="isLoading" class="text-center py-8">
      <VProgressCircular
        indeterminate
        color="primary"
        size="64"
      />
      <p class="mt-4">{{ t('loading_order') }}</p>
    </div>

    <!-- Order Content -->
    <div v-else-if="shippingOrder">
      <!-- Header -->
      <div class="d-flex justify-space-between align-center mb-6">
        <div class="d-flex align-center gap-4">
          <VBtn
            icon="tabler-arrow-left"
            variant="text"
            @click="goBack"
          />
          <div>
            <h1 class="text-h4 font-weight-bold mb-1">
              {{ t('shipment') }} {{ shippingOrder.id.slice(0, 8) }}
            </h1>
            <div class="d-flex align-center gap-2">
              <VChip
                v-if="shippingOrder.shipping_parcel?.tracking_number"
                size="small"
                color="primary"
                variant="tonal"
                class="font-mono"
              >
                {{ shippingOrder.shipping_parcel.tracking_number }}
              </VChip>
              <VChip
                size="small"
                :color="getStatusColor(shippingOrder.shipping_parcel?.status || 'unknown')"
                variant="tonal"
              >
                {{ getStatusText(shippingOrder.shipping_parcel?.status || 'unknown') }}
              </VChip>
              <VChip
                size="small"
                :color="shippingOrder.shipping_parcel?.sent_to_carrier ? 'primary' : 'warning'"
                variant="tonal"
              >
                <VIcon
                  start
                  :icon="shippingOrder.shipping_parcel?.sent_to_carrier ? 'tabler-truck' : 'tabler-package'"
                />
                {{ shippingOrder.shipping_parcel?.sent_to_carrier ? t('carrier') : t('local') }}
              </VChip>
              <VChip
                size="small"
                color="info"
                variant="tonal"
              >
                {{ shippingOrder.shipping_parcel?.city_name || shippingOrder.adresse?.ville || '-' }}
              </VChip>
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="d-flex gap-2">
          <VBtn
            color="info"
            variant="outlined"
            @click="fetchTracking"
            :loading="trackingLoading"
          >
            <VIcon start icon="tabler-route" />
            Tracking
          </VBtn>
          
          <VBtn
            color="secondary"
            variant="outlined"
            @click="fetchParcelInfo"
          >
            <VIcon start icon="tabler-info-circle" />
            Infos Colis
          </VBtn>

          <!-- Manual Status Update Button (only for local deliveries) -->
          <VBtn
            v-if="!shippingOrder.shipping_parcel?.sent_to_carrier"
            color="warning"
            variant="outlined"
            @click="openStatusUpdateDialog"
          >
            <VIcon start icon="tabler-edit" />
            Modifier Statut
          </VBtn>

          <!-- TEMPORARILY HIDDEN: Delivery Note PDF Menu -->
          <VMenu v-if="false && shippingOrder.shipping_parcel?.delivery_note_ref">
            <template #activator="{ props }">
              <VBtn
                color="primary"
                variant="elevated"
                v-bind="props"
              >
                <VIcon start icon="tabler-file-download" />
                PDFs
              </VBtn>
            </template>
            <VList>
              <VListItem @click="openPDF(shippingOrder.shipping_parcel?.delivery_note_ref || '', 'pdf')">
                <VListItemTitle>PDF Standard</VListItemTitle>
              </VListItem>
              <VListItem @click="openPDF(shippingOrder.shipping_parcel?.delivery_note_ref || '', 'a4')">
                <VListItemTitle>Étiquettes A4</VListItemTitle>
              </VListItem>
              <VListItem @click="openPDF(shippingOrder.shipping_parcel?.delivery_note_ref || '', '100x100')">
                <VListItemTitle>Étiquettes 100x100</VListItemTitle>
              </VListItem>
            </VList>
          </VMenu>
        </div>
      </div>

      <!-- Tabs -->
      <VTabs
        v-model="activeTab"
        class="mb-6"
      >
        <VTab value="parcel">
          <VIcon start icon="tabler-package" />
          Informations Colis
        </VTab>
        <VTab value="tracking">
          <VIcon start icon="tabler-route" />
          Suivi
        </VTab>
        <!-- TEMPORARILY HIDDEN: Delivery Note Tab -->
        <VTab v-if="false" value="delivery-note">
          <VIcon start icon="tabler-file-text" />
          Bon de Livraison
        </VTab>
        <VTab value="delivery-boy">
          <VIcon start icon="tabler-user" />
          Livreur
        </VTab>
      </VTabs>

      <!-- Tab Content -->
      <VWindow v-model="activeTab">
        <!-- Parcel Info Tab -->
        <VWindowItem value="parcel">
          <!-- Client Final Card -->
          <VCard class="mb-6">
            <VCardTitle class="d-flex align-center justify-space-between">
              <span>{{ $t('orders.client_final.title') }}</span>
              <VBtn
                v-if="getClientFinalAddress()"
                size="small"
                color="primary"
                variant="outlined"
                prepend-icon="tabler-copy"
                @click="copyAddress"
              >
                {{ $t('orders.client_final.copy_address') }}
              </VBtn>
            </VCardTitle>
            <VCardText>
              <VRow>
                <VCol cols="12" md="6">
                  <div class="mb-4">
                    <div class="text-body-2 text-medium-emphasis mb-1">{{ $t('orders.client_final.name') }}</div>
                    <div class="text-h6">{{ getClientFinalName() }}</div>
                  </div>
                  <div class="mb-4">
                    <div class="text-body-2 text-medium-emphasis mb-1">{{ $t('orders.client_final.phone') }}</div>
                    <div class="text-body-1">
                      <a :href="`tel:${getClientFinalPhone()}`" class="text-decoration-none">
                        {{ getClientFinalPhone() }}
                      </a>
                    </div>
                  </div>
                  <div v-if="getClientFinalEmail()" class="mb-4">
                    <div class="text-body-2 text-medium-emphasis mb-1">{{ $t('orders.client_final.email') }}</div>
                    <div class="text-body-1">{{ getClientFinalEmail() }}</div>
                  </div>
                </VCol>
                <VCol cols="12" md="6">
                  <div class="mb-4">
                    <div class="text-body-2 text-medium-emphasis mb-1">{{ $t('orders.client_final.city') }}</div>
                    <div class="text-h6">
                      <VChip color="secondary" variant="tonal" size="small">
                        {{ getClientFinalCity() }}
                      </VChip>
                    </div>
                  </div>
                  <div class="mb-4">
                    <div class="text-body-2 text-medium-emphasis mb-1">{{ $t('orders.client_final.address') }}</div>
                    <div class="text-body-1">{{ getClientFinalAddress() }}</div>
                  </div>
                  <div v-if="getClientFinalPostalCode()" class="mb-4">
                    <div class="text-body-2 text-medium-emphasis mb-1">{{ $t('orders.client_final.postal_code') }}</div>
                    <div class="text-body-1">{{ getClientFinalPostalCode() }}</div>
                  </div>
                  <div class="mb-4">
                    <div class="text-body-2 text-medium-emphasis mb-1">{{ $t('orders.client_final.country') }}</div>
                    <div class="text-body-1">{{ getClientFinalCountry() }}</div>
                  </div>
                </VCol>
              </VRow>
            </VCardText>
          </VCard>

          <VRow>
            <VCol cols="12" md="8">
              <VCard>
                <VCardTitle>{{ t('labels.packageDetails') }}</VCardTitle>
                <VCardText>
                  <VRow>
                    <VCol cols="12" md="6">
                      <div class="mb-4">
                        <div class="text-body-2 text-medium-emphasis mb-1">Numéro de suivi</div>
                        <VChip color="primary" variant="tonal" class="font-mono">
                          {{ shippingOrder.shipping_parcel.tracking_number }}
                        </VChip>
                      </div>
                      <div class="mb-4">
                        <div class="text-body-2 text-medium-emphasis mb-1">{{ t('recipient') }}</div>
                        <div class="text-body-1">{{ shippingOrder.shipping_parcel.receiver }}</div>
                      </div>
                      <div class="mb-4">
                        <div class="text-body-2 text-medium-emphasis mb-1">{{ t('phone') }}</div>
                        <div class="text-body-1">{{ shippingOrder.shipping_parcel.phone }}</div>
                      </div>
                    </VCol>
                    <VCol cols="12" md="6">
                      <div class="mb-4">
                        <div class="text-body-2 text-medium-emphasis mb-1">{{ t('city') }}</div>
                        <VChip color="info" variant="tonal">
                          {{ shippingOrder.shipping_parcel.city_name }}
                        </VChip>
                      </div>
                      <div class="mb-4">
                        <div class="text-body-2 text-medium-emphasis mb-1">{{ t('address') }}</div>
                        <div class="text-body-1">{{ shippingOrder.shipping_parcel.address }}</div>
                      </div>
                      <div class="mb-4">
                        <div class="text-body-2 text-medium-emphasis mb-1">{{ t('price') }}</div>
                        <div class="text-h6">{{ formatCurrency(shippingOrder.shipping_parcel.price || 0) }}</div>
                      </div>
                    </VCol>
                  </VRow>

                  <VDivider class="my-4" />

                  <div class="mb-4">
                    <div class="text-body-2 text-medium-emphasis mb-1">Note</div>
                    <div class="text-body-1">{{ shippingOrder.shipping_parcel.note || 'Aucune note' }}</div>
                  </div>

                  <VRow v-if="shippingOrder.shipping_parcel.delivered_price">
                    <VCol cols="4">
                      <div class="text-body-2 text-medium-emphasis mb-1">Prix livraison</div>
                      <div class="text-body-1">{{ formatCurrency(shippingOrder.shipping_parcel.delivered_price) }}</div>
                    </VCol>
                    <VCol cols="4">
                      <div class="text-body-2 text-medium-emphasis mb-1">{{ t('labels.returnFee') }}</div>
                      <div class="text-body-1">{{ formatCurrency(shippingOrder.shipping_parcel.returned_price || 0) }}</div>
                    </VCol>
                    <VCol cols="4">
                      <div class="text-body-2 text-medium-emphasis mb-1">Prix refus</div>
                      <div class="text-body-1">{{ formatCurrency(shippingOrder.shipping_parcel.refused_price || 0) }}</div>
                    </VCol>
                  </VRow>
                </VCardText>
              </VCard>

              <!-- Parcel Info from API -->
              <VCard v-if="parcelInfo" class="mt-4">
                <VCardTitle>Informations API</VCardTitle>
                <VCardText>
                  <pre class="text-body-2">{{ JSON.stringify(parcelInfo, null, 2) }}</pre>
                </VCardText>
              </VCard>
            </VCol>
            <VCol cols="12" md="4">
              <VCard>
                <VCardTitle>Commande Originale</VCardTitle>
                <VCardText>
                  <div class="mb-4">
                    <div class="text-body-2 text-medium-emphasis mb-1">Client</div>
                    <div class="text-body-1">{{ shippingOrder.client?.nom_complet || '-' }}</div>
                    <div class="text-caption text-medium-emphasis">{{ shippingOrder.client?.telephone || '-' }}</div>
                  </div>
                  <div class="mb-4">
                    <div class="text-body-2 text-medium-emphasis mb-1">Affilié</div>
                    <div class="text-body-1">{{ shippingOrder.affilie?.utilisateur?.nom_complet || '-' }}</div>
                    <div class="text-caption text-medium-emphasis">{{ shippingOrder.affilie?.utilisateur?.email || '-' }}</div>
                  </div>
                  <div class="mb-4">
                    <div class="text-body-2 text-medium-emphasis mb-1">Boutique</div>
                    <VChip color="info" variant="tonal">{{ shippingOrder.boutique?.nom || '-' }}</VChip>
                  </div>
                  <div class="mb-4">
                    <div class="text-body-2 text-medium-emphasis mb-1">{{ t('admin_total') }}</div>
                    <div class="text-h6">{{ formatCurrency(shippingOrder.total_ttc || 0) }}</div>
                  </div>
                  <VBtn
                    color="primary"
                    variant="outlined"
                    block
                    @click="router.push({ name: 'admin-orders-pre-id', params: { id: shippingOrder.id } })"
                  >
                    {{ t('actions.viewOrder') }}
                  </VBtn>
                </VCardText>
              </VCard>
            </VCol>
          </VRow>
        </VWindowItem>

        <!-- Tracking Tab -->
        <VWindowItem value="tracking">
          <!-- Loading State -->
          <VCard v-if="trackingLoading">
            <VCardTitle>Suivi du Colis</VCardTitle>
            <VCardText>
              <div class="text-center py-8">
                <VProgressCircular
                  indeterminate
                  color="primary"
                  size="64"
                  class="mb-4"
                />
                <h3 class="text-h6 mb-2">Récupération des données de suivi...</h3>
                <p class="text-body-2 text-medium-emphasis">
                  Veuillez patienter pendant que nous récupérons les informations
                </p>
              </div>
            </VCardText>
          </VCard>

          <!-- Tracking Data Display -->
          <div v-else-if="trackingData">
            <!-- Header Card -->
            <VCard class="mb-4">
              <VCardTitle class="d-flex align-center justify-space-between">
                <span>Suivi du Colis</span>
                <VBtn
                  color="primary"
                  variant="outlined"
                  @click="fetchTracking"
                  :loading="trackingLoading"
                >
                  <VIcon start icon="tabler-refresh" />
                  Actualiser
                </VBtn>
              </VCardTitle>
              <VCardText>
                <!-- Tracking Number & Status -->
                <div class="d-flex flex-column flex-md-row align-md-center justify-space-between gap-4 mb-4">
                  <div class="d-flex align-center gap-2">
                    <VChip
                      color="primary"
                      variant="tonal"
                      class="font-mono"
                      size="large"
                    >
                      {{ getTrackingNumber }}
                    </VChip>
                    <VBtn
                      icon="tabler-copy"
                      variant="text"
                      size="small"
                      @click="copyTrackingNumber"
                    />
                  </div>

                  <div class="d-flex align-center gap-2">
                    <VChip
                      :color="getTrackingStatusColor(getLastTracking?.STATUT || '')"
                      variant="tonal"
                      size="large"
                    >
                      {{ getLastTracking?.STATUT || 'Statut inconnu' }}
                    </VChip>
                    <div v-if="getLastTracking?.TIME_STR" class="text-body-2 text-medium-emphasis">
                      Mis à jour le {{ formatDateTime(getLastTracking.TIME_STR) }}
                    </div>
                  </div>
                </div>

                <!-- Summary Information -->
                <VRow v-if="getParcelSummary">
                  <VCol cols="12" md="6">
                    <div class="mb-3">
                      <div class="text-body-2 text-medium-emphasis mb-1">Destinataire</div>
                      <div class="text-body-1 font-weight-medium">
                        {{ getParcelSummary.RECEIVER || '-' }}
                      </div>
                    </div>
                    <div class="mb-3">
                      <div class="text-body-2 text-medium-emphasis mb-1">Téléphone</div>
                      <div class="text-body-1">
                        {{ getParcelSummary.PHONE || '-' }}
                      </div>
                    </div>
                  </VCol>
                  <VCol cols="12" md="6">
                    <div class="mb-3">
                      <div class="text-body-2 text-medium-emphasis mb-1">Ville</div>
                      <div class="text-body-1">
                        {{ getParcelSummary.CITY_NAME || '-' }}
                      </div>
                    </div>
                    <div class="mb-3">
                      <div class="text-body-2 text-medium-emphasis mb-1">Adresse</div>
                      <div class="text-body-1">
                        {{ getParcelSummary.ADDRESS || '-' }}
                      </div>
                    </div>
                  </VCol>
                  <VCol cols="12" v-if="getParcelSummary.PRICE">
                    <div class="mb-3">
                      <div class="text-body-2 text-medium-emphasis mb-1">Prix</div>
                      <div class="text-h6 text-primary">
                        {{ formatCurrency(parseFloat(getParcelSummary.PRICE) || 0) }}
                      </div>
                    </div>
                  </VCol>
                </VRow>
              </VCardText>
            </VCard>

            <!-- Timeline Card -->
            <VCard>
              <VCardTitle>Historique du Suivi</VCardTitle>
              <VCardText>
                <div v-if="getTrackingHistory.length > 0">
                  <VTimeline
                    side="end"
                    align="start"
                    truncate-line="both"
                    density="compact"
                  >
                    <VTimelineItem
                      v-for="(event, index) in getTrackingHistory"
                      :key="index"
                      :dot-color="getTrackingStatusColor(event.STATUT)"
                      size="small"
                    >
                      <template #icon>
                        <VIcon
                          :icon="event.STATUT?.toLowerCase().includes('livré') ? 'tabler-check' : 'tabler-package'"
                          size="16"
                        />
                      </template>

                      <div class="d-flex flex-column flex-md-row align-md-center justify-space-between">
                        <div>
                          <div class="text-body-1 font-weight-medium mb-1">
                            {{ event.STATUT }}
                          </div>
                          <div v-if="event.COMMENT" class="text-body-2 text-medium-emphasis mb-2">
                            {{ event.COMMENT }}
                          </div>
                        </div>
                        <div class="text-body-2 text-medium-emphasis">
                          {{ formatDateTime(event.TIME_STR) }}
                        </div>
                      </div>
                    </VTimelineItem>
                  </VTimeline>
                </div>
                <div v-else class="text-center py-8">
                  <VIcon
                    icon="tabler-timeline"
                    size="64"
                    class="mb-4"
                    color="disabled"
                  />
                  <h3 class="text-h6 mb-2">Aucun événement de suivi</h3>
                  <p class="text-body-2 text-medium-emphasis">
                    Aucun événement de suivi pour le moment.
                  </p>
                </div>
              </VCardText>
            </VCard>
          </div>

          <!-- Empty State -->
          <VCard v-else>
            <VCardTitle class="d-flex align-center justify-space-between">
              <span>Suivi du Colis</span>
              <VBtn
                color="primary"
                variant="outlined"
                @click="fetchTracking"
                :loading="trackingLoading"
              >
                <VIcon start icon="tabler-refresh" />
                Récupérer le Suivi
              </VBtn>
            </VCardTitle>
            <VCardText>
              <div class="text-center py-8">
                <VIcon
                  icon="tabler-route"
                  size="64"
                  class="mb-4"
                  color="disabled"
                />
                <h3 class="text-h6 mb-2">Aucune donnée de suivi</h3>
                <p class="text-body-2 text-medium-emphasis mb-4">
                  Cliquez sur "Récupérer le Suivi" pour obtenir les informations de suivi
                </p>
                <VBtn
                  color="primary"
                  variant="elevated"
                  @click="fetchTracking"
                  :loading="trackingLoading"
                >
                  <VIcon start icon="tabler-route" />
                  Récupérer le Suivi
                </VBtn>
              </div>
            </VCardText>
          </VCard>
        </VWindowItem>

        <!-- TEMPORARILY HIDDEN: Delivery Note Tab Content -->
        <VWindowItem v-if="false" value="delivery-note">
          <VCard>
            <VCardTitle>Gestion du Bon de Livraison</VCardTitle>
            <VCardText>
              <div v-if="shippingOrder.shipping_parcel.delivery_note_ref">
                <div class="mb-4">
                  <div class="text-body-2 text-medium-emphasis mb-1">Référence du bon</div>
                  <VChip color="success" variant="tonal" class="font-mono">
                    {{ shippingOrder.shipping_parcel.delivery_note_ref }}
                  </VChip>
                </div>

                <div class="d-flex gap-2 mb-4">
                  <VBtn
                    color="primary"
                    variant="elevated"
                    @click="openPDF(shippingOrder.shipping_parcel.delivery_note_ref, 'pdf')"
                  >
                    <VIcon start icon="tabler-file-download" />
                    PDF Standard
                  </VBtn>
                  <VBtn
                    color="secondary"
                    variant="outlined"
                    @click="openPDF(shippingOrder.shipping_parcel.delivery_note_ref, 'a4')"
                  >
                    Étiquettes A4
                  </VBtn>
                  <VBtn
                    color="info"
                    variant="outlined"
                    @click="openPDF(shippingOrder.shipping_parcel.delivery_note_ref, '100x100')"
                  >
                    Étiquettes 100x100
                  </VBtn>
                </div>
              </div>

              <div v-else>
                <VAlert
                  type="info"
                  variant="tonal"
                  class="mb-4"
                >
                  Ce colis n'est pas encore associé à un bon de livraison
                </VAlert>

                <div class="mb-4">
                  <VTextField
                    v-model="deliveryNoteRef"
                    label="Référence du bon de livraison"
                    :placeholder="t('admin_shipping_delivery_note_placeholder')"
                    variant="outlined"
                  />
                </div>

                <div class="d-flex gap-2">
                  <VBtn
                    color="primary"
                    variant="elevated"
                    @click="createDeliveryNote"
                  >
                    <VIcon start icon="tabler-file-plus" />
                    Créer Bon
                  </VBtn>
                  <VBtn
                    color="secondary"
                    variant="outlined"
                    :disabled="!deliveryNoteRef"
                    @click="addParcelsToDeliveryNote"
                  >
                    <VIcon start icon="tabler-plus" />
                    Ajouter Colis
                  </VBtn>
                  <VBtn
                    color="success"
                    variant="outlined"
                    :disabled="!deliveryNoteRef"
                    @click="saveDeliveryNote"
                  >
                    <VIcon start icon="tabler-check" />
                    Sauvegarder
                  </VBtn>
                </div>
              </div>
            </VCardText>
          </VCard>
        </VWindowItem>

        <!-- Delivery Boy Tab -->
        <VWindowItem value="delivery-boy">
          <VCard>
            <VCardTitle class="d-flex align-center">
              <VIcon icon="tabler-user" class="me-2" />
              Informations du Livreur
            </VCardTitle>
            <VCardText>
              <VRow>
                <VCol cols="12" md="6">
                  <VTextField
                    v-model="deliveryBoyName"
                    label="Nom du livreur"
                    placeholder="Ex: Ahmed Benali"
                    variant="outlined"
                    prepend-inner-icon="tabler-user"
                    :disabled="deliveryBoyLoading"
                  />
                </VCol>
                <VCol cols="12" md="6">
                  <VTextField
                    v-model="deliveryBoyPhone"
                    label="Téléphone du livreur"
                    placeholder="Ex: +212 6 12 34 56 78"
                    variant="outlined"
                    prepend-inner-icon="tabler-phone"
                    :disabled="deliveryBoyLoading"
                  />
                </VCol>
              </VRow>

              <div class="d-flex gap-3 mt-4">
                <VBtn
                  color="primary"
                  variant="elevated"
                  :loading="deliveryBoyLoading"
                  @click="saveDeliveryBoyInfo"
                >
                  <VIcon start icon="tabler-device-floppy" />
                  Enregistrer
                </VBtn>
                <VBtn
                  variant="outlined"
                  @click="resetDeliveryBoyInfo"
                  :disabled="deliveryBoyLoading"
                >
                  <VIcon start icon="tabler-refresh" />
                  Réinitialiser
                </VBtn>
              </div>

              <!-- Current Info Display -->
              <VAlert
                v-if="shippingOrder?.delivery_boy_name || shippingOrder?.delivery_boy_phone"
                type="info"
                variant="tonal"
                class="mt-4"
              >
                <VAlertTitle>Informations actuelles</VAlertTitle>
                <div v-if="shippingOrder?.delivery_boy_name">
                  <strong>Nom:</strong> {{ shippingOrder.delivery_boy_name }}
                </div>
                <div v-if="shippingOrder?.delivery_boy_phone">
                  <strong>Téléphone:</strong> {{ shippingOrder.delivery_boy_phone }}
                </div>
              </VAlert>
            </VCardText>
          </VCard>
        </VWindowItem>
      </VWindow>

      <!-- Order Status Timeline -->
      <div class="mt-6">
        <OrderStatusTimeline
          v-if="shippingOrder?.id"
          :order-id="shippingOrder.id"
          endpoint="admin"
          order-type="shipping"
        />
      </div>
    </div>

    <!-- Error State -->
    <div v-else class="text-center py-8">
      <VIcon
        icon="tabler-alert-circle"
        size="64"
        class="mb-4"
        color="error"
      />
      <h3 class="text-h6 mb-2">Commande introuvable</h3>
      <p class="text-body-2 text-medium-emphasis mb-4">
        La commande demandée n'existe pas ou n'a pas été expédiée
      </p>
      <VBtn
        color="primary"
        variant="elevated"
        @click="goBack"
      >
        {{ t('actions.backToList') }}
      </VBtn>
    </div>
  </div>

  <!-- Manual Status Update Dialog -->
  <VDialog
    v-model="showStatusUpdateDialog"
    max-width="500"
    persistent
  >
    <VCard>
      <VCardTitle class="text-h6">
        <VIcon start icon="tabler-edit" color="warning" />
        {{ t('labels.modifyShippingStatus') }}
      </VCardTitle>

      <VCardText>
        <p class="mb-4">
          {{ t('modify_local_shipment_status') }}
          <strong>{{ t('note') }}:</strong> {{ t('note_delivered_status_commission') }}
        </p>

        <VSelect
          v-model="newStatus"
          label="Nouveau statut"
          :items="[
            { title: 'En attente', value: 'pending' },
            { title: 'Expédiée', value: 'expediee' },
            { title: 'Livrée', value: 'livree' },
            { title: 'Refusée', value: 'refusee' },
            { title: t('order.status.returned_feminine'), value: 'retournee' },
            { title: 'Annulée', value: 'annulee' }
          ]"
          variant="outlined"
          class="mb-4"
        />

        <VTextarea
          v-model="statusNote"
          label="Note (optionnelle)"
          :placeholder="t('admin_shipping_status_note_placeholder')"
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
          Annuler
        </VBtn>
        <VBtn
          color="warning"
          variant="elevated"
          :loading="statusUpdateLoading"
          :disabled="!newStatus"
          @click="updateShippingStatus"
        >
          <VIcon start icon="tabler-check" />
          Mettre à jour
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
