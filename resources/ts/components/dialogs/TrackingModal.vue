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
          <h6 class="text-h6 mb-2">Erreur de suivi</h6>
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
            <VTab value="history">
              <VIcon start icon="tabler-history" />
              Historique
            </VTab>
          </VTabs>

          <VTabsWindow v-model="activeTab">
            <!-- Summary Tab -->
            <VTabsWindowItem value="summary" class="pa-6">
              <VRow>
                <VCol cols="12" md="6">
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
                
                <VCol cols="12" md="6">
                  <VCard variant="outlined" class="h-100">
                    <VCardText>
                      <h6 class="text-h6 mb-4">Tarification</h6>
                      
                      <div class="d-flex flex-column gap-3">
                        <div v-if="parcelInfo?.price" class="d-flex justify-space-between">
                          <span class="text-body-2 text-medium-emphasis">Prix du colis:</span>
                          <span class="text-body-2 font-weight-bold">{{ formatCurrency(parcelInfo.price) }}</span>
                        </div>
                        
                        <div v-if="parcelInfo?.delivered_price" class="d-flex justify-space-between">
                          <span class="text-body-2 text-medium-emphasis">Frais de livraison:</span>
                          <span class="text-body-2">{{ formatCurrency(parcelInfo.delivered_price) }}</span>
                        </div>
                        
                        <div v-if="parcelInfo?.returned_price" class="d-flex justify-space-between">
                          <span class="text-body-2 text-medium-emphasis">Frais de retour:</span>
                          <span class="text-body-2">{{ formatCurrency(parcelInfo.returned_price) }}</span>
                        </div>
                        
                        <div v-if="parcelInfo?.refused_price" class="d-flex justify-space-between">
                          <span class="text-body-2 text-medium-emphasis">Frais de refus:</span>
                          <span class="text-body-2">{{ formatCurrency(parcelInfo.refused_price) }}</span>
                        </div>
                      </div>
                    </VCardText>
                  </VCard>
                </VCol>
              </VRow>
            </VTabsWindowItem>

            <!-- History Tab -->
            <VTabsWindowItem value="history" class="pa-6">
              <div v-if="trackingHistory && trackingHistory.length > 0">
                <VTimeline side="end" class="pt-0">
                  <VTimelineItem
                    v-for="(event, index) in trackingHistory"
                    :key="index"
                    :dot-color="getStatusColor(event.status)"
                    size="small"
                  >
                    <template #icon>
                      <VIcon :icon="getStatusIcon(event.status)" size="16" />
                    </template>
                    
                    <VCard variant="outlined" class="mb-2">
                      <VCardText class="pa-4">
                        <div class="d-flex justify-space-between align-center mb-2">
                          <VChip 
                            size="small" 
                            :color="getStatusColor(event.status)"
                            variant="tonal"
                          >
                            {{ event.status_text }}
                          </VChip>
                          <span class="text-caption text-medium-emphasis">
                            {{ formatDate(event.time) }}
                          </span>
                        </div>
                        <p v-if="event.comment" class="text-body-2 mb-0">
                          {{ event.comment }}
                        </p>
                      </VCardText>
                    </VCard>
                  </VTimelineItem>
                </VTimeline>
              </div>
              
              <div v-else class="text-center py-8">
                <VIcon icon="tabler-history-off" size="64" color="disabled" class="mb-4" />
                <h6 class="text-h6 mb-2">Aucun historique</h6>
                <p class="text-body-2 text-medium-emphasis">
                  Aucun événement de suivi disponible pour ce colis
                </p>
              </div>
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
          Fermer
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'

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
  if (!props.trackingData?.tracking_info?.TRACKING?.LAST_TRACKING) return null
  
  const lastTracking = props.trackingData.tracking_info.TRACKING.LAST_TRACKING
  return {
    status: lastTracking.STATUT,
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
    status: event.STATUT,
    status_text: event.STATUT,
    time: event.TIME_STR,
    comment: event.COMMENT
  })).reverse() // Show newest first
})

const getStatusColor = (status: string) => {
  switch (status?.toLowerCase()) {
    case 'livré': return 'success'
    case 'en transit':
    case 'en cours de livraison': return 'info'
    case 'nouveau colis':
    case 'colis reçu':
    case 'prêt pour livraison': return 'warning'
    case 'annulé':
    case 'refusé':
    case 'échec de livraison': return 'error'
    case 'retourné':
    case 'retour livré': return 'secondary'
    default: return 'default'
  }
}

const getStatusIcon = (status: string) => {
  switch (status?.toLowerCase()) {
    case 'livré': return 'tabler-check'
    case 'en transit': return 'tabler-truck'
    case 'en cours de livraison': return 'tabler-truck-delivery'
    case 'nouveau colis': return 'tabler-package'
    case 'colis reçu': return 'tabler-package-import'
    case 'retourné': return 'tabler-package-export'
    case 'refusé': return 'tabler-x'
    case 'annulé': return 'tabler-ban'
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
