<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useShippingStore } from '@/stores/admin/shipping'
import { useConfirmAction } from '@/composables/useConfirmAction'
import { useNotifications } from '@/composables/useNotifications'

definePage({
  meta: {
    requiresAuth: true,
    requiresRole: 'admin',
    layout: 'default',
  },
})

const route = useRoute()
const router = useRouter()
const shippingStore = useShippingStore()
const { confirm } = useConfirmAction()
const { showSuccess, showError } = useNotifications()

// Local state
const activeTab = ref('parcel')
const trackingData = ref(null)
const parcelInfo = ref(null)
const deliveryNoteRef = ref('')
const selectedParcels = ref<string[]>([])

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

  try {
    trackingData.value = await shippingStore.getTracking(
      shippingOrder.value.shipping_parcel.tracking_number
    )
  } catch (error: any) {
    showError(error.message || 'Erreur lors de la récupération du tracking')
  }
}

const fetchParcelInfo = async () => {
  if (!shippingOrder.value?.shipping_parcel?.tracking_number) return

  try {
    parcelInfo.value = await shippingStore.getParcelInfo(
      shippingOrder.value.shipping_parcel.tracking_number
    )
  } catch (error: any) {
    showError(error.message || 'Erreur lors de la récupération des infos du colis')
  }
}

const createDeliveryNote = async () => {
  const confirmed = await confirm({
    title: 'Créer un bon de livraison',
    text: 'Voulez-vous créer un nouveau bon de livraison ?',
    confirmText: 'Créer',
    color: 'primary',
  })

  if (confirmed) {
    try {
      const ref = await shippingStore.createDeliveryNote()
      deliveryNoteRef.value = ref
      showSuccess(`Bon de livraison créé: ${ref}`)
    } catch (error: any) {
      showError(error.message || 'Erreur lors de la création du bon de livraison')
    }
  }
}

const addParcelsToDeliveryNote = async () => {
  if (!deliveryNoteRef.value || !shippingOrder.value?.shipping_parcel?.tracking_number) {
    showError('Veuillez créer un bon de livraison et sélectionner des colis')
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
      showSuccess('Colis ajouté au bon de livraison')
    } catch (error: any) {
      showError(error.message || 'Erreur lors de l\'ajout du colis')
    }
  }
}

const saveDeliveryNote = async () => {
  if (!deliveryNoteRef.value) {
    showError('Aucun bon de livraison à sauvegarder')
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
      showSuccess('Bon de livraison sauvegardé')
      // Update the shipping order to reflect the delivery note
      await fetchShippingOrder()
    } catch (error: any) {
      showError(error.message || 'Erreur lors de la sauvegarde')
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
      return 'Retourné'
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

const goBack = () => {
  router.push({ name: 'admin-orders-shipping' })
}

// Lifecycle
onMounted(() => {
  fetchShippingOrder()
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
      <p class="mt-4">Chargement de la commande...</p>
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
              Expédition {{ shippingOrder.id.slice(0, 8) }}
            </h1>
            <div class="d-flex align-center gap-2">
              <VChip
                size="small"
                color="primary"
                variant="tonal"
                class="font-mono"
              >
                {{ shippingOrder.shipping_parcel.tracking_number }}
              </VChip>
              <VChip
                size="small"
                :color="getStatusColor(shippingOrder.shipping_parcel.status)"
                variant="tonal"
              >
                {{ getStatusText(shippingOrder.shipping_parcel.status) }}
              </VChip>
              <VChip
                size="small"
                color="info"
                variant="tonal"
              >
                {{ shippingOrder.shipping_parcel.city_name || shippingOrder.adresse.ville }}
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

          <VMenu v-if="shippingOrder.shipping_parcel.delivery_note_ref">
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
              <VListItem @click="openPDF(shippingOrder.shipping_parcel.delivery_note_ref, 'pdf')">
                <VListItemTitle>PDF Standard</VListItemTitle>
              </VListItem>
              <VListItem @click="openPDF(shippingOrder.shipping_parcel.delivery_note_ref, 'a4')">
                <VListItemTitle>Étiquettes A4</VListItemTitle>
              </VListItem>
              <VListItem @click="openPDF(shippingOrder.shipping_parcel.delivery_note_ref, '100x100')">
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
        <VTab value="delivery-note">
          <VIcon start icon="tabler-file-text" />
          Bon de Livraison
        </VTab>
      </VTabs>

      <!-- Tab Content -->
      <VWindow v-model="activeTab">
        <!-- Parcel Info Tab -->
        <VWindowItem value="parcel">
          <VRow>
            <VCol cols="12" md="8">
              <VCard>
                <VCardTitle>Détails du Colis</VCardTitle>
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
                        <div class="text-body-2 text-medium-emphasis mb-1">Destinataire</div>
                        <div class="text-body-1">{{ shippingOrder.shipping_parcel.receiver }}</div>
                      </div>
                      <div class="mb-4">
                        <div class="text-body-2 text-medium-emphasis mb-1">Téléphone</div>
                        <div class="text-body-1">{{ shippingOrder.shipping_parcel.phone }}</div>
                      </div>
                    </VCol>
                    <VCol cols="12" md="6">
                      <div class="mb-4">
                        <div class="text-body-2 text-medium-emphasis mb-1">Ville</div>
                        <VChip color="info" variant="tonal">
                          {{ shippingOrder.shipping_parcel.city_name }}
                        </VChip>
                      </div>
                      <div class="mb-4">
                        <div class="text-body-2 text-medium-emphasis mb-1">Adresse</div>
                        <div class="text-body-1">{{ shippingOrder.shipping_parcel.address }}</div>
                      </div>
                      <div class="mb-4">
                        <div class="text-body-2 text-medium-emphasis mb-1">Prix</div>
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
                      <div class="text-body-2 text-medium-emphasis mb-1">Prix retour</div>
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
                    <div class="text-body-1">{{ shippingOrder.client.nom_complet }}</div>
                    <div class="text-caption text-medium-emphasis">{{ shippingOrder.client.telephone }}</div>
                  </div>
                  <div class="mb-4">
                    <div class="text-body-2 text-medium-emphasis mb-1">Affilié</div>
                    <div class="text-body-1">{{ shippingOrder.affilie.utilisateur.nom_complet }}</div>
                    <div class="text-caption text-medium-emphasis">{{ shippingOrder.affilie.utilisateur.email }}</div>
                  </div>
                  <div class="mb-4">
                    <div class="text-body-2 text-medium-emphasis mb-1">Boutique</div>
                    <VChip color="info" variant="tonal">{{ shippingOrder.boutique.nom }}</VChip>
                  </div>
                  <div class="mb-4">
                    <div class="text-body-2 text-medium-emphasis mb-1">Total</div>
                    <div class="text-h6">{{ formatCurrency(shippingOrder.total_ttc) }}</div>
                  </div>
                  <VBtn
                    color="primary"
                    variant="outlined"
                    block
                    @click="router.push({ name: 'admin-orders-pre-id', params: { id: shippingOrder.id } })"
                  >
                    Voir Commande
                  </VBtn>
                </VCardText>
              </VCard>
            </VCol>
          </VRow>
        </VWindowItem>

        <!-- Tracking Tab -->
        <VWindowItem value="tracking">
          <VCard>
            <VCardTitle class="d-flex align-center justify-space-between">
              <span>Suivi du Colis</span>
              <VBtn
                color="primary"
                variant="outlined"
                @click="fetchTracking"
              >
                <VIcon start icon="tabler-refresh" />
                Actualiser
              </VBtn>
            </VCardTitle>
            <VCardText>
              <div v-if="trackingData">
                <pre class="text-body-2">{{ JSON.stringify(trackingData, null, 2) }}</pre>
              </div>
              <div v-else class="text-center py-8">
                <VIcon
                  icon="tabler-route"
                  size="64"
                  class="mb-4"
                  color="disabled"
                />
                <h3 class="text-h6 mb-2">Aucune donnée de suivi</h3>
                <p class="text-body-2 text-medium-emphasis mb-4">
                  Cliquez sur "Actualiser" pour récupérer les informations de suivi
                </p>
                <VBtn
                  color="primary"
                  variant="elevated"
                  @click="fetchTracking"
                >
                  Récupérer le suivi
                </VBtn>
              </div>
            </VCardText>
          </VCard>
        </VWindowItem>

        <!-- Delivery Note Tab -->
        <VWindowItem value="delivery-note">
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
                    placeholder="Créer un nouveau bon ou saisir une référence existante"
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
      </VWindow>
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
        Retour à la liste
      </VBtn>
    </div>
  </div>
</template>
