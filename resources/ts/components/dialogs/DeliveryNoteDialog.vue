<template>
  <VDialog
    :model-value="modelValue"
    max-width="600"
    persistent
    @update:model-value="updateModelValue"
  >
    <VCard>
      <VCardTitle class="d-flex align-center gap-2 pa-6">
        <VIcon icon="tabler-file-plus" color="primary" />
        <div>
          <h6 class="text-h6">Bon de Livraison</h6>
          <p class="text-body-2 text-medium-emphasis mb-0">
            Créer et gérer les bons de livraison OzonExpress
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

      <VCardText class="pa-6">
        <!-- Step 1: Create Delivery Note -->
        <div v-if="step === 1">
          <div class="text-center mb-6">
            <VIcon icon="tabler-file-plus" size="64" color="primary" class="mb-4" />
            <h6 class="text-h6 mb-2">Créer un nouveau bon de livraison</h6>
            <p class="text-body-2 text-medium-emphasis">
              Un bon de livraison vous permet de regrouper plusieurs colis pour l'impression d'étiquettes
            </p>
          </div>

          <VAlert
            type="info"
            variant="tonal"
            class="mb-4"
          >
            <VAlertTitle>Information</VAlertTitle>
            <p class="mb-0">
              Après création, vous pourrez ajouter les colis sélectionnés au bon de livraison.
            </p>
          </VAlert>
        </div>

        <!-- Step 2: Add Parcels -->
        <div v-else-if="step === 2">
          <div class="mb-4">
            <h6 class="text-h6 mb-2">Ajouter des colis</h6>
            <p class="text-body-2 text-medium-emphasis">
              Référence du bon: <VChip size="small" color="primary" variant="tonal">{{ deliveryNoteRef }}</VChip>
            </p>
          </div>

          <VAlert
            type="success"
            variant="tonal"
            class="mb-4"
          >
            <VAlertTitle>Colis sélectionnés</VAlertTitle>
            <p class="mb-2">{{ selectedTrackingNumbers.length }} colis seront ajoutés au bon de livraison:</p>
            <div class="d-flex flex-wrap gap-1">
              <VChip
                v-for="tracking in selectedTrackingNumbers"
                :key="tracking"
                size="small"
                color="success"
                variant="tonal"
                class="font-mono"
              >
                {{ tracking }}
              </VChip>
            </div>
          </VAlert>
        </div>

        <!-- Step 3: Save and Download -->
        <div v-else-if="step === 3">
          <div class="text-center mb-6">
            <VIcon icon="tabler-check-circle" size="64" color="success" class="mb-4" />
            <h6 class="text-h6 mb-2">Bon de livraison créé avec succès</h6>
            <p class="text-body-2 text-medium-emphasis">
              Référence: <VChip size="small" color="success" variant="tonal">{{ deliveryNoteRef }}</VChip>
            </p>
          </div>

          <VAlert
            type="success"
            variant="tonal"
            class="mb-4"
          >
            <VAlertTitle>Prêt pour téléchargement</VAlertTitle>
            <p class="mb-0">
              Votre bon de livraison est maintenant disponible au téléchargement en différents formats.
            </p>
          </VAlert>

          <div class="d-flex flex-column gap-3">
            <VBtn
              v-for="pdfType in pdfTypes"
              :key="pdfType.key"
              :color="pdfType.color"
              variant="outlined"
              block
              :href="pdfType.url"
              target="_blank"
              :disabled="!pdfType.url"
            >
              <VIcon start :icon="pdfType.icon" />
              {{ pdfType.label }}
            </VBtn>
          </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="text-center py-8">
          <VProgressCircular indeterminate color="primary" />
          <p class="mt-4 text-body-1">{{ loadingMessage }}</p>
        </div>

        <!-- Error State -->
        <div v-if="error" class="text-center py-4">
          <VAlert
            type="error"
            variant="tonal"
            class="mb-4"
          >
            <VAlertTitle>Erreur</VAlertTitle>
            <p class="mb-0">{{ error }}</p>
          </VAlert>
        </div>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-6">
        <VBtn
          v-if="step > 1 && step < 3"
          color="secondary"
          variant="outlined"
          @click="previousStep"
          :disabled="loading"
        >
          <VIcon start icon="tabler-arrow-left" />
          Précédent
        </VBtn>

        <VSpacer />

        <VBtn
          color="secondary"
          variant="text"
          @click="updateModelValue(false)"
          :disabled="loading"
        >
          {{ step === 3 ? 'Fermer' : 'Annuler' }}
        </VBtn>

        <VBtn
          v-if="step === 1"
          color="primary"
          variant="elevated"
          @click="createDeliveryNote"
          :loading="loading"
        >
          <VIcon start icon="tabler-plus" />
          Créer
        </VBtn>

        <VBtn
          v-else-if="step === 2"
          color="primary"
          variant="elevated"
          @click="addParcelsAndSave"
          :loading="loading"
        >
          <VIcon start icon="tabler-package-import" />
          Ajouter et Sauvegarder
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'

interface Props {
  modelValue: boolean
  selectedTrackingNumbers: string[]
}

interface Emit {
  (e: 'update:modelValue', value: boolean): void
  (e: 'created', ref: string): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emit>()

const step = ref(1)
const loading = ref(false)
const error = ref('')
const loadingMessage = ref('')
const deliveryNoteRef = ref('')
const pdfLinks = ref<any>({})

const updateModelValue = (val: boolean) => {
  if (!val) {
    // Reset state when closing
    step.value = 1
    error.value = ''
    deliveryNoteRef.value = ''
    pdfLinks.value = {}
  }
  emit('update:modelValue', val)
}

const pdfTypes = computed(() => [
  {
    key: 'bl_pdf',
    label: 'Bon de Livraison PDF',
    icon: 'tabler-file-type-pdf',
    color: 'error',
    url: pdfLinks.value.bl_pdf
  },
  {
    key: 'tickets_a4',
    label: 'Étiquettes A4',
    icon: 'tabler-printer',
    color: 'info',
    url: pdfLinks.value.tickets_a4
  },
  {
    key: 'tickets_100x100',
    label: 'Étiquettes 100×100',
    icon: 'tabler-qrcode',
    color: 'success',
    url: pdfLinks.value.tickets_100x100
  }
])

const createDeliveryNote = async () => {
  loading.value = true
  loadingMessage.value = 'Création du bon de livraison...'
  error.value = ''

  try {
    const response = await fetch('/api/admin/shipping/ozon/dn/create', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${localStorage.getItem('token')}`,
      },
    })

    const data = await response.json()

    if (data.success) {
      deliveryNoteRef.value = data.data.ref
      step.value = 2
    } else {
      error.value = data.message || 'Erreur lors de la création du bon de livraison'
    }
  } catch (err: any) {
    if (err.response?.status === 404) {
      error.value = 'Fonctionnalité de bon de livraison non disponible dans cette version d\'OzonExpress'
    } else {
      error.value = 'Erreur de connexion: ' + err.message
    }
  } finally {
    loading.value = false
  }
}

const addParcelsAndSave = async () => {
  loading.value = true
  loadingMessage.value = 'Ajout des colis...'
  error.value = ''

  try {
    // Add parcels
    const addResponse = await fetch('/api/admin/shipping/ozon/dn/add-parcels', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${localStorage.getItem('token')}`,
      },
      body: JSON.stringify({
        ref: deliveryNoteRef.value,
        codes: props.selectedTrackingNumbers
      })
    })

    const addData = await addResponse.json()

    if (!addData.success) {
      error.value = addData.message || 'Erreur lors de l\'ajout des colis'
      return
    }

    loadingMessage.value = 'Sauvegarde du bon de livraison...'

    // Save delivery note
    const saveResponse = await fetch('/api/admin/shipping/ozon/dn/save', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${localStorage.getItem('token')}`,
      },
      body: JSON.stringify({
        ref: deliveryNoteRef.value
      })
    })

    const saveData = await saveResponse.json()

    if (!saveData.success) {
      error.value = saveData.message || 'Erreur lors de la sauvegarde'
      return
    }

    loadingMessage.value = 'Génération des liens PDF...'

    // Get PDF links
    const pdfResponse = await fetch(`/api/admin/shipping/ozon/dn/pdf?ref=${deliveryNoteRef.value}`, {
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('token')}`,
      },
    })

    const pdfData = await pdfResponse.json()

    if (pdfData.success) {
      pdfLinks.value = pdfData.data.pdf_links
    }

    step.value = 3
    emit('created', deliveryNoteRef.value)

  } catch (err: any) {
    if (err.response?.status === 404) {
      error.value = 'Fonctionnalité de bon de livraison non disponible dans cette version d\'OzonExpress'
    } else {
      error.value = 'Erreur de connexion: ' + err.message
    }
  } finally {
    loading.value = false
  }
}

const previousStep = () => {
  if (step.value > 1) {
    step.value--
    error.value = ''
  }
}
</script>
