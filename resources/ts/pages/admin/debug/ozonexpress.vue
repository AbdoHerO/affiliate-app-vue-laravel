<template>
  <div class="pa-6">
    <VCard>
      <VCardTitle class="d-flex align-center gap-2">
        <VIcon icon="tabler-bug" />
        OzonExpress API Debug & Testing
      </VCardTitle>
      
      <VCardText>
        <VAlert
          type="info"
          variant="tonal"
          class="mb-4"
        >
          Cette page permet de tester et déboguer l'intégration OzonExpress.
          Utilisez-la pour vérifier que l'API fonctionne correctement.
        </VAlert>

        <!-- System Status -->
        <VCard class="mb-4" variant="outlined">
          <VCardTitle>État du Système</VCardTitle>
          <VCardText>
            <VBtn
              color="primary"
              @click="loadSystemStatus"
              :loading="loading.systemStatus"
            >
              Vérifier l'État
            </VBtn>
            
            <div v-if="systemStatus" class="mt-4">
              <VRow>
                <VCol cols="12" md="6">
                  <VCard variant="tonal" color="primary">
                    <VCardText>
                      <div class="text-h6">OzonExpress</div>
                      <div class="text-body-2">
                        <div>Statut: <VChip :color="systemStatus.ozonexpress.enabled ? 'success' : 'warning'" size="small">
                          {{ systemStatus.ozonexpress.enabled ? 'Activé' : 'Désactivé' }}
                        </VChip></div>
                        <div>URL: {{ systemStatus.ozonexpress.base_url }}</div>
                        <div>Customer ID: {{ systemStatus.ozonexpress.customer_id || 'Non configuré' }}</div>
                        <div>API Key: <VChip :color="systemStatus.ozonexpress.api_key_set ? 'success' : 'error'" size="small">
                          {{ systemStatus.ozonexpress.api_key_set ? 'Configuré' : 'Manquant' }}
                        </VChip></div>
                      </div>
                    </VCardText>
                  </VCard>
                </VCol>
                <VCol cols="12" md="6">
                  <VCard variant="tonal" color="info">
                    <VCardText>
                      <div class="text-h6">Commandes</div>
                      <div class="text-body-2">
                        <div>En attente: {{ systemStatus.orders.en_attente }}</div>
                        <div>Confirmées: {{ systemStatus.orders.confirmee }}</div>
                        <div>Avec expédition: {{ systemStatus.orders.with_shipping }}</div>
                        <div>Sans expédition: {{ systemStatus.orders.without_shipping }}</div>
                      </div>
                    </VCardText>
                  </VCard>
                </VCol>
              </VRow>
            </div>
          </VCardText>
        </VCard>

        <!-- API Connectivity Test -->
        <VCard class="mb-4" variant="outlined">
          <VCardTitle>Test de Connectivité API</VCardTitle>
          <VCardText>
            <VBtn
              color="secondary"
              @click="testApiConnectivity"
              :loading="loading.apiTest"
            >
              Tester la Connectivité
            </VBtn>
            
            <div v-if="apiTestResult" class="mt-4">
              <VAlert
                :type="apiTestResult.success ? 'success' : 'error'"
                variant="tonal"
              >
                {{ apiTestResult.message }}
              </VAlert>
              
              <VExpansionPanels v-if="apiTestResult.api_response" class="mt-2">
                <VExpansionPanel>
                  <VExpansionPanelTitle>Réponse API Détaillée</VExpansionPanelTitle>
                  <VExpansionPanelText>
                    <pre class="text-caption">{{ JSON.stringify(apiTestResult.api_response, null, 2) }}</pre>
                  </VExpansionPanelText>
                </VExpansionPanel>
              </VExpansionPanels>
            </div>
          </VCardText>
        </VCard>

        <!-- Recent Shipping Parcels -->
        <VCard class="mb-4" variant="outlined">
          <VCardTitle>Colis Récents</VCardTitle>
          <VCardText>
            <VBtn
              color="info"
              @click="loadShippingParcels"
              :loading="loading.parcels"
            >
              Charger les Colis
            </VBtn>
            
            <div v-if="shippingParcels" class="mt-4">
              <div class="text-subtitle-1 mb-2">
                Total des colis: {{ shippingParcels.total_parcels }}
              </div>
              
              <VDataTable
                :headers="parcelHeaders"
                :items="shippingParcels.recent_parcels"
                :items-per-page="10"
                class="elevation-1"
              >
                <template #item.status="{ item }">
                  <VChip size="small" :color="getStatusColor(item.status)">
                    {{ item.status }}
                  </VChip>
                </template>
                
                <template #item.created_at="{ item }">
                  {{ formatDate(item.created_at) }}
                </template>
                
                <template #item.meta="{ item }">
                  <VBtn
                    size="small"
                    variant="text"
                    @click="showMetaDialog(item)"
                  >
                    Voir Détails
                  </VBtn>
                </template>
              </VDataTable>
            </div>
          </VCardText>
        </VCard>

        <!-- Quick Actions -->
        <VCard variant="outlined">
          <VCardTitle>Actions Rapides</VCardTitle>
          <VCardText>
            <div class="d-flex gap-2 flex-wrap">
              <VBtn
                color="success"
                @click="enableOzonExpress"
                :loading="loading.toggle"
              >
                Activer OzonExpress
              </VBtn>
              
              <VBtn
                color="warning"
                @click="disableOzonExpress"
                :loading="loading.toggle"
              >
                Désactiver (Mode Mock)
              </VBtn>
              
              <VBtn
                color="primary"
                @click="testBulkOperations"
                :loading="loading.bulkTest"
              >
                Test Opérations en Lot
              </VBtn>
            </div>
            
            <div v-if="bulkTestResult" class="mt-4">
              <VAlert
                :type="bulkTestResult.success ? 'success' : 'error'"
                variant="tonal"
              >
                {{ bulkTestResult.message }}
              </VAlert>
              
              <div v-if="bulkTestResult.summary" class="mt-2">
                <div class="text-body-2">
                  Total: {{ bulkTestResult.summary.total }} | 
                  Succès: {{ bulkTestResult.summary.success }} | 
                  Erreurs: {{ bulkTestResult.summary.errors }}
                </div>
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCardText>
    </VCard>

    <!-- Meta Dialog -->
    <VDialog v-model="metaDialog.show" max-width="600">
      <VCard>
        <VCardTitle>Détails du Colis</VCardTitle>
        <VCardText>
          <pre class="text-caption">{{ JSON.stringify(metaDialog.data, null, 2) }}</pre>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn @click="metaDialog.show = false">Fermer</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'

definePage({
  meta: {
    title: 'Debug OzonExpress',
    requiresAuth: true,
    roles: ['admin']
  }
})

// State
const loading = ref({
  systemStatus: false,
  apiTest: false,
  parcels: false,
  toggle: false,
  bulkTest: false
})

const systemStatus = ref<any>(null)
const apiTestResult = ref<any>(null)
const shippingParcels = ref<any>(null)
const bulkTestResult = ref<any>(null)

const metaDialog = ref({
  show: false,
  data: null
})

// Table headers
const parcelHeaders = [
  { title: 'Tracking', key: 'tracking_number' },
  { title: 'Client', key: 'client_name' },
  { title: 'Ville', key: 'city' },
  { title: 'Prix', key: 'price' },
  { title: 'Statut', key: 'status' },
  { title: 'Créé le', key: 'created_at' },
  { title: 'Détails', key: 'meta', sortable: false }
]

// Methods
const loadSystemStatus = async () => {
  loading.value.systemStatus = true
  try {
    const { data, error } = await useApi('/admin/test/system-status')
    if (error.value) {
      console.error('Error loading system status:', error.value)
    } else {
      systemStatus.value = data.value
    }
  } catch (error) {
    console.error('Error loading system status:', error)
  } finally {
    loading.value.systemStatus = false
  }
}

const testApiConnectivity = async () => {
  loading.value.apiTest = true
  try {
    const { data, error } = await useApi('/admin/test/api-connectivity')
    if (error.value) {
      console.error('Error testing API connectivity:', error.value)
      apiTestResult.value = {
        success: false,
        message: 'Erreur lors du test de connectivité: ' + (error.value as any)?.message
      }
    } else {
      apiTestResult.value = data.value
    }
  } catch (error) {
    console.error('Error testing API connectivity:', error)
    apiTestResult.value = {
      success: false,
      message: 'Erreur lors du test de connectivité: ' + (error as any)?.message
    }
  } finally {
    loading.value.apiTest = false
  }
}

const loadShippingParcels = async () => {
  loading.value.parcels = true
  try {
    const { data, error } = await useApi('/admin/test/shipping-parcels')
    if (error.value) {
      console.error('Error loading shipping parcels:', error.value)
    } else {
      shippingParcels.value = data.value
    }
  } catch (error) {
    console.error('Error loading shipping parcels:', error)
  } finally {
    loading.value.parcels = false
  }
}

const testBulkOperations = async () => {
  loading.value.bulkTest = true
  try {
    const { data, error } = await useApi('/admin/test/bulk-operations')
    if (error.value) {
      console.error('Error testing bulk operations:', error.value)
      bulkTestResult.value = {
        success: false,
        message: 'Erreur lors du test des opérations en lot: ' + (error.value as any)?.message
      }
    } else {
      bulkTestResult.value = data.value
    }
  } catch (error) {
    console.error('Error testing bulk operations:', error)
    bulkTestResult.value = {
      success: false,
      message: 'Erreur lors du test des opérations en lot: ' + (error as any)?.message
    }
  } finally {
    loading.value.bulkTest = false
  }
}

const enableOzonExpress = async () => {
  loading.value.toggle = true
  // This would need to be implemented as an API endpoint
  // For now, just show a message
  alert('Utilisez la commande: php artisan ozonexpress:toggle --enable')
  loading.value.toggle = false
}

const disableOzonExpress = async () => {
  loading.value.toggle = true
  // This would need to be implemented as an API endpoint
  // For now, just show a message
  alert('Utilisez la commande: php artisan ozonexpress:toggle --disable')
  loading.value.toggle = false
}

const showMetaDialog = (item: any) => {
  metaDialog.value.data = item.meta
  metaDialog.value.show = true
}

const getStatusColor = (status: string) => {
  switch (status) {
    case 'pending': return 'warning'
    case 'delivered': return 'success'
    case 'returned': return 'error'
    default: return 'info'
  }
}

const formatDate = (date: string) => {
  return new Date(date).toLocaleString('fr-FR')
}
</script>
