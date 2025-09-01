<template>
  <VDialog
    :model-value="modelValue"
    max-width="800"
    persistent
    @update:model-value="updateModelValue"
  >
    <VCard>
      <VCardTitle class="d-flex align-center gap-2 pa-6">
        <VIcon icon="tabler-route" color="primary" />
        <div>
          <h6 class="text-h6">Suivi de Colis</h6>
          <p class="text-body-2 text-medium-emphasis mb-0">
            {{ trackingNumber }}
          </p>
        </div>
        <VSpacer />
        <VBtn
          icon="tabler-x"
          variant="text"
          size="small"
          @click="updateModelValue(false)"
        />
      </VCardTitle>

      <VDivider />

      <VCardText class="pa-0">
        <div v-if="loading" class="text-center py-8">
          <VProgressCircular indeterminate color="primary" />
          <p class="mt-4 text-body-1">Chargement du suivi...</p>
        </div>

        <div v-else-if="error" class="text-center py-8">
          <VIcon icon="tabler-alert-circle" size="64" color="error" class="mb-4" />
          <h6 class="text-h6 mb-2">{{ t('shipping.tracking_error') }}</h6>
          <p class="text-body-2 text-medium-emphasis">{{ error }}</p>
          <VBtn color="primary" variant="outlined" @click="$emit('retry')">
            Réessayer
          </VBtn>
        </div>

        <div v-else-if="trackingData">
          <VTabs v-model="activeTab" class="border-b">
            <VTab value="summary">
              <VIcon start icon="tabler-info-circle" />
              Résumé
            </VTab>

          </VTabs>

          <VTabsWindow v-model="activeTab">
            <!-- Summary Tab -->
            <VTabsWindowItem value="summary" class="pa-6">
              <VRow>
                <VCol cols="12">
                  <VCard variant="outlined" class="h-100">
                    <VCardText>
                      <h6 class="text-h6 mb-4">Informations du Colis</h6>
                      
                      <div class="d-flex flex-column gap-3">
                        <div class="d-flex justify-space-between">
                          <span class="text-body-2 text-medium-emphasis">Numéro de suivi:</span>
                          <VChip size="small" color="primary" variant="tonal" class="font-mono">
                            {{ trackingNumber }}
                          </VChip>
                        </div>
                        
                        <div class="d-flex justify-space-between">
                          <span class="text-body-2 text-medium-emphasis">Statut actuel:</span>
                          <VChip 
                            size="small" 
                            :color="getStatusColor(lastStatus?.status)"
                            variant="tonal"
                          >
                            {{ lastStatus?.status_text || 'Inconnu' }}
                          </VChip>
                        </div>
                        
                        <div v-if="lastStatus?.time" class="d-flex justify-space-between">
                          <span class="text-body-2 text-medium-emphasis">Dernière mise à jour:</span>
                          <span class="text-body-2">{{ formatDate(lastStatus.time) }}</span>
                        </div>
                        
                        <div v-if="parcelInfo?.city_name" class="d-flex justify-space-between">
                          <span class="text-body-2 text-medium-emphasis">Ville:</span>
                          <VChip size="small" color="info" variant="tonal">
                            {{ parcelInfo.city_name }}
                          </VChip>
                        </div>
                        
                        <div v-if="parcelInfo?.receiver" class="d-flex justify-space-between">
                          <span class="text-body-2 text-medium-emphasis">Destinataire:</span>
                          <span class="text-body-2">{{ parcelInfo.receiver }}</span>
                        </div>
                      </div>
                    </VCardText>
                  </VCard>
                </VCol>
              </VRow>

              <!-- Delivery Boy Information -->
              <VRow v-if="deliveryBoyName || deliveryBoyPhone" class="mt-4">
                <VCol cols="12">
                  <VCard variant="outlined">
                    <VCardText>
                      <h6 class="text-h6 mb-4 d-flex align-center">
                        <VIcon icon="tabler-user" class="me-2" />
                        Informations du Livreur
                      </h6>

                      <div class="d-flex flex-column gap-3">
                        <div v-if="deliveryBoyName" class="d-flex justify-space-between">
                          <span class="text-body-2 text-medium-emphasis">Nom du livreur:</span>
                          <span class="text-body-2 font-weight-medium">{{ deliveryBoyName }}</span>
                        </div>

                        <div v-if="deliveryBoyPhone" class="d-flex justify-space-between">
                          <span class="text-body-2 text-medium-emphasis">Téléphone:</span>
                          <a
                            :href="`tel:${deliveryBoyPhone}`"
                            class="text-decoration-none text-primary font-weight-medium"
                          >
                            <VIcon icon="tabler-phone" size="16" class="me-1" />
                            {{ deliveryBoyPhone }}
                          </a>
                        </div>
                      </div>
                    </VCardText>
                  </VCard>
                </VCol>
              </VRow>
            </VTabsWindowItem>


          </VTabsWindow>
        </div>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-6">
        <VBtn
          color="primary"
          variant="outlined"
          @click="$emit('refresh')"
          :loading="loading"
        >
          <VIcon start icon="tabler-refresh" />
          Actualiser
        </VBtn>
        
        <VSpacer />
        
        <VBtn
          color="secondary"
          variant="text"
          @click="updateModelValue(false)"
        >
          {{ t('actions.close') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

interface TrackingEvent {
  status: string
  status_text: string
  time: string
  comment?: string
}

interface ParcelInfo {
  city_name?: string
  receiver?: string
  price?: number
  delivered_price?: number
  returned_price?: number
  refused_price?: number
}

interface Props {
  modelValue: boolean
  trackingNumber: string
  trackingData?: any
  loading?: boolean
  error?: string
  deliveryBoyName?: string | null
  deliveryBoyPhone?: string | null
}

interface Emit {
  (e: 'update:modelValue', value: boolean): void
  (e: 'refresh'): void
  (e: 'retry'): void
}

const props = withDefaults(defineProps<Props>(), {
  loading: false,
  error: '',
})

const emit = defineEmits<Emit>()

const activeTab = ref('summary')

const updateModelValue = (val: boolean) => {
  emit('update:modelValue', val)
}

// Extract data from tracking response
const lastStatus = computed(() => {
  // First try to get from the parcel data (preferred - already mapped)
  if (props.trackingData?.parcel?.status) {
    return {
      status: props.trackingData.parcel.status,
      status_text: props.trackingData.parcel.last_status_text || props.trackingData.parcel.status,
      time: props.trackingData.parcel.last_status_at,
      comment: props.trackingData.parcel.meta?.last_status_comment
    }
  }

  // Fallback to raw tracking data
  if (!props.trackingData?.tracking_info?.TRACKING?.LAST_TRACKING) return null

  const lastTracking = props.trackingData.tracking_info.TRACKING.LAST_TRACKING
  return {
    status: mapOzonStatus(lastTracking.STATUT),
    status_text: lastTracking.STATUT,
    time: lastTracking.TIME_STR,
    comment: lastTracking.COMMENT
  }
})

const parcelInfo = computed((): ParcelInfo | null => {
  if (!props.trackingData?.parcel_info?.PARCEL_INFO?.INFOS) return null
  
  const info = props.trackingData.parcel_info.PARCEL_INFO.INFOS
  return {
    city_name: info.CITY_NAME,
    receiver: info.RECEIVER,
    price: parseFloat(info.PRICE) || 0,
    delivered_price: parseFloat(info['DELIVERED-PRICE']) || 0,
    returned_price: parseFloat(info['RETURNED-PRICE']) || 0,
    refused_price: parseFloat(info['REFUSED-PRICE']) || 0,
  }
})

const trackingHistory = computed((): TrackingEvent[] => {
  if (!props.trackingData?.tracking_info?.TRACKING?.HISTORY) return []

  const history = props.trackingData.tracking_info.TRACKING.HISTORY
  if (!Array.isArray(history)) return []

  return history.map(event => ({
    status: mapOzonStatus(event.STATUT),
    status_text: event.STATUT,
    time: event.TIME_STR,
    comment: event.COMMENT
  })).reverse() // Show newest first
})

// Map OzonExpress French status to internal status
const mapOzonStatus = (ozonStatus: string): string => {
  if (!ozonStatus) return 'unknown'

  const statusMap: Record<string, string> = {
    'Nouveau Colis': 'pending',
    'Colis Reçu': 'received',
    'En Transit': 'in_transit',
    'En Cours de Livraison': 'out_for_delivery',
    'Livré': 'delivered',
    'Retourné': 'returned',
    'Refusé': 'refused',
    'Annulé': 'cancelled',
    'En Attente': 'pending',
    'Expédié': 'shipped',
    'Arrivé au Centre': 'at_facility',
    'Prêt pour Livraison': 'ready_for_delivery',
    'Tentative de Livraison': 'delivery_attempted',
  }

  return statusMap[ozonStatus] || 'unknown'
}

const getStatusColor = (status: string) => {
  switch (status?.toLowerCase()) {
    case 'delivered': return 'success'
    case 'in_transit':
    case 'out_for_delivery':
    case 'shipped': return 'info'
    case 'pending':
    case 'received':
    case 'at_facility':
    case 'ready_for_delivery': return 'warning'
    case 'cancelled':
    case 'refused':
    case 'delivery_attempted': return 'error'
    case 'returned': return 'secondary'
    default: return 'default'
  }
}

const getStatusIcon = (status: string) => {
  switch (status?.toLowerCase()) {
    case 'delivered': return 'tabler-check'
    case 'in_transit': return 'tabler-truck'
    case 'out_for_delivery': return 'tabler-truck-delivery'
    case 'shipped': return 'tabler-truck'
    case 'pending': return 'tabler-package'
    case 'received': return 'tabler-package-import'
    case 'at_facility': return 'tabler-building-warehouse'
    case 'ready_for_delivery': return 'tabler-truck-loading'
    case 'returned': return 'tabler-package-export'
    case 'refused': return 'tabler-x'
    case 'cancelled': return 'tabler-ban'
    case 'delivery_attempted': return 'tabler-clock-exclamation'
    default: return 'tabler-circle'
  }
}

const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('fr-MA', {
    style: 'currency',
    currency: 'MAD',
  }).format(amount)
}

const formatDate = (dateStr: string) => {
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
</script>
