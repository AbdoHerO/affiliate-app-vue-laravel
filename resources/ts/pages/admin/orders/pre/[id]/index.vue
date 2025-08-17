<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { usePreordersStore } from '@/stores/admin/preorders'
import { useQuickConfirm } from '@/composables/useConfirmAction'
import { useNotifications } from '@/composables/useNotifications'
import ConfirmActionDialog from '@/components/common/ConfirmActionDialog.vue'
import OzonExpressConfirmDialog from '@/components/dialogs/OzonExpressConfirmDialog.vue'

definePage({
  meta: {
    requiresAuth: true,
    requiresRole: 'admin',
  },
})

const route = useRoute()
const router = useRouter()
const preordersStore = usePreordersStore()
const {
  confirm,
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
const { showSuccess, showError, snackbar } = useNotifications()

// Local state
const activeTab = ref('client')
const isEditing = ref(false)
const isSaving = ref(false)
const showOzonDialog = ref(false)
const ozonDialogLoading = ref(false)

// Form data
const formData = ref({
  statut: '',
  confirmation_cc: '',
  notes: '',
})

// Computed
const isLoading = computed(() => preordersStore.isLoading)
const preorder = computed(() => preordersStore.currentPreorder)
const orderId = computed(() => route.params.id as string)

// Status options
const statusOptions = [
  { title: 'En attente', value: 'en_attente' },
  { title: 'Confirmée', value: 'confirmee' },
]

const confirmationOptions = [
  { title: 'Non contacté', value: 'non_contacte' },
  { title: 'À confirmer', value: 'a_confirmer' },
  { title: 'Confirmé', value: 'confirme' },
  { title: 'Injoignable', value: 'injoignable' },
]

// Methods
const fetchPreorder = async () => {
  try {
    await preordersStore.fetchPreorder(orderId.value)
    if (preorder.value) {
      formData.value = {
        statut: preorder.value.statut,
        confirmation_cc: preorder.value.confirmation_cc,
        notes: preorder.value.notes || '',
      }
    }
  } catch (error: any) {
    showError(error.message || 'Erreur lors du chargement de la commande')
    router.push({ name: 'admin-orders-pre' })
  }
}

const toggleEdit = () => {
  isEditing.value = !isEditing.value
  if (!isEditing.value && preorder.value) {
    // Reset form data if canceling edit
    formData.value = {
      statut: preorder.value.statut,
      confirmation_cc: preorder.value.confirmation_cc,
      notes: preorder.value.notes || '',
    }
  }
}

const saveChanges = async () => {
  if (!preorder.value) return

  isSaving.value = true
  try {
    await preordersStore.updatePreorder(preorder.value.id, formData.value)
    showSuccess('Commande mise à jour avec succès')
    isEditing.value = false

    // Re-fetch the order to get updated data
    await fetchPreorder()

    // Stay on detail page after save
    router.replace({
      name: 'admin-orders-pre-id',
      params: { id: preorder.value.id }
    })
  } catch (error: any) {
    showError(error.message || 'Erreur lors de la mise à jour')
  } finally {
    isSaving.value = false
  }
}

const confirmOrder = async () => {
  if (!preorder.value) return

  const confirmed = await confirm({
    title: 'Confirmer la commande',
    text: 'Êtes-vous sûr de vouloir confirmer cette commande ?',
    confirmText: 'Confirmer',
    color: 'success',
  })

  if (confirmed) {
    try {
      await preordersStore.changeStatus(preorder.value.id, 'confirmee')
      showSuccess('Commande confirmée avec succès')
    } catch (error: any) {
      showError(error.message || 'Erreur lors de la confirmation')
    }
  }
}

const sendToOzonExpress = async () => {
  if (!preorder.value) return
  showOzonDialog.value = true
}

const handleOzonConfirm = async (mode: 'ramassage' | 'stock') => {
  if (!preorder.value) return

  ozonDialogLoading.value = true
  try {
    await preordersStore.sendToShipping(preorder.value.id, mode)
    showSuccess(`Commande envoyée vers OzonExpress en mode ${mode === 'ramassage' ? 'Ramassage' : 'Stock'}`)
    // Refresh order data
    await fetchPreorder()
  } catch (error: any) {
    showError(error.message || 'Erreur lors de l\'envoi vers OzonExpress')
    console.error('Detail shipping error:', error)
  } finally {
    ozonDialogLoading.value = false
  }
}

const handleOzonCancel = () => {
  // Dialog will close automatically
}

const getStatusColor = (status: string) => {
  switch (status) {
    case 'en_attente':
      return 'warning'
    case 'confirmee':
      return 'success'
    default:
      return 'default'
  }
}

const getStatusText = (status: string) => {
  switch (status) {
    case 'en_attente':
      return 'En attente'
    case 'confirmee':
      return 'Confirmée'
    default:
      return status
  }
}

const getConfirmationColor = (status: string) => {
  switch (status) {
    case 'non_contacte':
      return 'default'
    case 'a_confirmer':
      return 'warning'
    case 'confirme':
      return 'success'
    case 'injoignable':
      return 'error'
    default:
      return 'default'
  }
}

const getConfirmationText = (status: string) => {
  switch (status) {
    case 'non_contacte':
      return 'Non contacté'
    case 'a_confirmer':
      return 'À confirmer'
    case 'confirme':
      return 'Confirmé'
    case 'injoignable':
      return 'Injoignable'
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
  router.push({ name: 'admin-orders-pre' })
}

// Lifecycle
onMounted(() => {
  fetchPreorder()
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
    <div v-else-if="preorder">
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
              Commande {{ preorder?.id?.slice(0, 8) || 'Chargement...' }}
            </h1>
            <div v-if="preorder" class="d-flex align-center gap-2">
              <VChip
                size="small"
                :color="getStatusColor(preorder.statut)"
                variant="tonal"
              >
                {{ getStatusText(preorder.statut) }}
              </VChip>
              <VChip
                size="small"
                :color="getConfirmationColor(preorder.confirmation_cc)"
                variant="tonal"
              >
                {{ getConfirmationText(preorder.confirmation_cc) }}
              </VChip>
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="d-flex gap-2">
          <VBtn
            v-if="!isEditing"
            color="secondary"
            variant="outlined"
            @click="toggleEdit"
          >
            <VIcon start icon="tabler-edit" />
            Modifier
          </VBtn>
          
          <template v-if="isEditing">
            <VBtn
              color="error"
              variant="outlined"
              @click="toggleEdit"
            >
              Annuler
            </VBtn>
            <VBtn
              color="success"
              variant="elevated"
              :loading="isSaving"
              @click="saveChanges"
            >
              <VIcon start icon="tabler-check" />
              Sauvegarder
            </VBtn>
          </template>

          <VBtn
            v-if="preorder?.statut === 'en_attente' && !isEditing"
            color="success"
            variant="outlined"
            @click="confirmOrder"
          >
            <VIcon start icon="tabler-check" />
            Confirmer
          </VBtn>

          <VBtn
            v-if="preorder && preorder.statut === 'confirmee' && !preorder.shipping_parcel && !isEditing"
            color="primary"
            variant="elevated"
            @click="sendToOzonExpress"
          >
            <VIcon start icon="tabler-truck" />
            Envoyer OzonExpress
          </VBtn>

          <VBtn
            v-if="preorder?.shipping_parcel"
            color="info"
            variant="outlined"
            @click="router.push({ name: 'admin-orders-shipping-id', params: { id: preorder.id } })"
          >
            <VIcon start icon="tabler-package" />
            Voir Expédition
          </VBtn>
        </div>
      </div>

      <!-- Tabs -->
      <VTabs
        v-model="activeTab"
        class="mb-6"
      >
        <VTab value="client">
          <VIcon start icon="tabler-user" />
          Client & Adresse
        </VTab>
        <VTab value="articles">
          <VIcon start icon="tabler-package" />
          Articles
        </VTab>
        <VTab value="resume">
          <VIcon start icon="tabler-file-text" />
          Résumé
        </VTab>
      </VTabs>

      <!-- Tab Content -->
      <VWindow v-model="activeTab">
        <!-- Client Tab -->
        <VWindowItem value="client">
          <VRow>
            <VCol cols="12" md="6">
              <VCard>
                <VCardTitle>Informations Client</VCardTitle>
                <VCardText>
                  <div class="mb-4">
                    <div class="text-body-2 text-medium-emphasis mb-1">Nom complet</div>
                    <div class="text-h6">{{ preorder?.client?.nom_complet || '-' }}</div>
                  </div>
                  <div class="mb-4">
                    <div class="text-body-2 text-medium-emphasis mb-1">Téléphone</div>
                    <div class="text-body-1">{{ preorder?.client?.telephone || '-' }}</div>
                  </div>
                </VCardText>
              </VCard>
            </VCol>
            <VCol cols="12" md="6">
              <VCard>
                <VCardTitle>Adresse de Livraison</VCardTitle>
                <VCardText>
                  <div class="mb-4">
                    <div class="text-body-2 text-medium-emphasis mb-1">Ville</div>
                    <div class="text-h6">{{ preorder?.adresse?.ville || '-' }}</div>
                  </div>
                  <div class="mb-4">
                    <div class="text-body-2 text-medium-emphasis mb-1">Adresse complète</div>
                    <div class="text-body-1">{{ preorder?.adresse?.adresse || '-' }}</div>
                  </div>
                </VCardText>
              </VCard>
            </VCol>
          </VRow>
        </VWindowItem>

        <!-- Articles Tab -->
        <VWindowItem value="articles">
          <VCard>
            <VCardTitle>Articles de la Commande</VCardTitle>
            <VCardText>
              <VDataTable
                :headers="[
                  { title: 'Produit', key: 'produit' },
                  { title: 'Variante', key: 'variante' },
                  { title: 'Quantité', key: 'quantite' },
                  { title: 'Prix unitaire', key: 'prix_unitaire' },
                  { title: 'Total', key: 'total_ligne' },
                ]"
                :items="preorder?.articles || []"
                :items-per-page="-1"
              >
                <template #item.produit="{ item }">
                  <div class="d-flex align-center gap-3">
                    <VAvatar
                      v-if="item.produit.images?.[0]"
                      size="40"
                      rounded
                    >
                      <VImg :src="item.produit.images[0].url" />
                    </VAvatar>
                    <VAvatar
                      v-else
                      size="40"
                      color="grey-lighten-3"
                      rounded
                    >
                      <VIcon icon="tabler-package" />
                    </VAvatar>
                    <div>
                      <div class="font-weight-medium">{{ item.produit.titre }}</div>
                    </div>
                  </div>
                </template>

                <template #item.variante="{ item }">
                  <VChip
                    v-if="item.variante"
                    size="small"
                    color="info"
                    variant="tonal"
                  >
                    {{ item.variante?.nom || '-' }}
                  </VChip>
                  <span v-else class="text-medium-emphasis">-</span>
                </template>

                <template #item.prix_unitaire="{ item }">
                  {{ formatCurrency(item.prix_unitaire) }}
                </template>

                <template #item.total_ligne="{ item }">
                  <div class="font-weight-bold">
                    {{ formatCurrency(item.total_ligne) }}
                  </div>
                </template>
              </VDataTable>
            </VCardText>
          </VCard>
        </VWindowItem>

        <!-- Resume Tab -->
        <VWindowItem value="resume">
          <VRow>
            <VCol cols="12" md="8">
              <VCard>
                <VCardTitle>Détails de la Commande</VCardTitle>
                <VCardText>
                  <VRow>
                    <VCol cols="12" md="6">
                      <VSelect
                        v-if="isEditing"
                        v-model="formData.statut"
                        label="Statut"
                        :items="statusOptions"
                        variant="outlined"
                      />
                      <div v-else class="mb-4">
                        <div class="text-body-2 text-medium-emphasis mb-1">Statut</div>
                        <VChip
                          :color="getStatusColor(preorder?.statut || '')"
                          variant="tonal"
                        >
                          {{ getStatusText(preorder?.statut || '') }}
                        </VChip>
                      </div>
                    </VCol>
                    <VCol cols="12" md="6">
                      <VSelect
                        v-if="isEditing"
                        v-model="formData.confirmation_cc"
                        label="Confirmation CC"
                        :items="confirmationOptions"
                        variant="outlined"
                      />
                      <div v-else class="mb-4">
                        <div class="text-body-2 text-medium-emphasis mb-1">Confirmation CC</div>
                        <VChip
                          :color="getConfirmationColor(preorder?.confirmation_cc || '')"
                          variant="tonal"
                        >
                          {{ getConfirmationText(preorder?.confirmation_cc || '') }}
                        </VChip>
                      </div>
                    </VCol>
                  </VRow>

                  <VTextarea
                    v-if="isEditing"
                    v-model="formData.notes"
                    label="Notes"
                    variant="outlined"
                    rows="3"
                  />
                  <div v-else class="mb-4">
                    <div class="text-body-2 text-medium-emphasis mb-1">Notes</div>
                    <div class="text-body-1">{{ preorder?.notes || 'Aucune note' }}</div>
                  </div>

                  <VDivider class="my-4" />

                  <div class="mb-4">
                    <div class="text-body-2 text-medium-emphasis mb-1">Affilié</div>
                    <div class="text-body-1">{{ preorder.affiliate?.nom_complet || 'N/A' }}</div>
                    <div class="text-caption text-medium-emphasis">{{ preorder.affiliate?.email || 'N/A' }}</div>
                  </div>

                  <div class="mb-4">
                    <div class="text-body-2 text-medium-emphasis mb-1">Boutique</div>
                    <VChip color="info" variant="tonal">{{ preorder?.boutique?.nom || '-' }}</VChip>
                  </div>

                  <div class="mb-4">
                    <div class="text-body-2 text-medium-emphasis mb-1">Date de création</div>
                    <div class="text-body-1">{{ preorder?.created_at ? formatDate(preorder.created_at) : '-' }}</div>
                  </div>
                </VCardText>
              </VCard>
            </VCol>
            <VCol cols="12" md="4">
              <VCard>
                <VCardTitle>Résumé Financier</VCardTitle>
                <VCardText>
                  <div class="d-flex justify-space-between mb-2">
                    <span>Total HT:</span>
                    <span>{{ preorder?.total_ht ? formatCurrency(preorder.total_ht) : '-' }}</span>
                  </div>
                  <VDivider class="my-2" />
                  <div class="d-flex justify-space-between font-weight-bold text-h6">
                    <span>Total TTC:</span>
                    <span>{{ preorder?.total_ttc ? formatCurrency(preorder.total_ttc) : '-' }}</span>
                  </div>
                  <div class="text-caption text-medium-emphasis mt-2">
                    Mode de paiement: {{ preorder?.mode_paiement?.toUpperCase() || '-' }}
                  </div>
                </VCardText>
              </VCard>
            </VCol>
          </VRow>
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
        La commande demandée n'existe pas ou a été supprimée
      </p>
      <VBtn
        color="primary"
        variant="elevated"
        @click="goBack"
      >
        Retour à la liste
      </VBtn>
    </div>

    <!-- OzonExpress Confirm Dialog -->
    <OzonExpressConfirmDialog
      v-model="showOzonDialog"
      :loading="ozonDialogLoading"
      text="Êtes-vous sûr de vouloir envoyer cette commande vers OzonExpress ?"
      @confirm="handleOzonConfirm"
      @cancel="handleOzonCancel"
    />

    <!-- Confirm Dialog -->
    <ConfirmActionDialog
      :is-dialog-visible="isConfirmDialogVisible"
      :is-loading="isConfirmLoading"
      :dialog-title="dialogTitle"
      :dialog-text="dialogText"
      :dialog-icon="dialogIcon"
      :dialog-color="dialogColor"
      :confirm-button-text="confirmButtonText"
      :cancel-button-text="cancelButtonText"
      @confirm="handleConfirm"
      @cancel="handleCancel"
    />

    <!-- Success/Error Snackbar -->
    <VSnackbar
      v-model="snackbar.show"
      :color="snackbar.color"
      :timeout="snackbar.timeout"
      location="top end"
    >
      {{ snackbar.message }}
    </VSnackbar>
  </div>
</template>
