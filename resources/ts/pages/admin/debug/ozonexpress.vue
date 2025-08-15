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

        <!-- Mode Toggle -->
        <VCard class="mb-4" variant="outlined">
          <VCardTitle>Mode de Test</VCardTitle>
          <VCardText>
            <div class="d-flex align-center gap-4">
              <VSwitch
                v-model="testMode.useReal"
                :loading="loading.toggle"
                color="success"
                @change="toggleTestMode"
              >
                <template #label>
                  <div class="d-flex align-center gap-2">
                    <VIcon
                      :icon="testMode.useReal ? 'tabler-rocket' : 'tabler-flask'"
                      :color="testMode.useReal ? 'success' : 'warning'"
                    />
                    <span class="font-weight-medium">
                      {{ testMode.useReal ? 'Mode API Réelle' : 'Mode Test Mock' }}
                    </span>
                  </div>
                </template>
              </VSwitch>

              <VChip
                :color="testMode.useReal ? 'success' : 'warning'"
                variant="tonal"
                size="small"
              >
                {{ testMode.useReal ? 'PRODUCTION' : 'TEST' }}
              </VChip>
            </div>

            <VAlert
              :type="testMode.useReal ? 'success' : 'warning'"
              variant="tonal"
              density="compact"
              class="mt-3"
            >
              <template #prepend>
                <VIcon :icon="testMode.useReal ? 'tabler-check' : 'tabler-alert-triangle'" />
              </template>
              <span v-if="testMode.useReal">
                🚀 <strong>Mode Production:</strong> Les appels API créent de vrais colis avec de vrais numéros de suivi
              </span>
              <span v-else>
                🧪 <strong>Mode Test:</strong> Les appels API utilisent des données fictives pour les tests
              </span>
            </VAlert>
          </VCardText>
        </VCard>

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
                          {{ systemStatus.ozonexpress.enabled ? 'API RÉELLE ACTIVÉE' : 'Mode Mock' }}
                        </VChip></div>
                        <div>URL: {{ systemStatus.ozonexpress.base_url }}</div>
                        <div>Customer ID: {{ systemStatus.ozonexpress.customer_id || 'Non configuré' }}</div>
                        <div>API Key: <VChip :color="systemStatus.ozonexpress.api_key_set ? 'success' : 'error'" size="small">
                          {{ systemStatus.ozonexpress.api_key_set ? 'Configuré' : 'Manquant' }}
                        </VChip></div>
                        <div class="mt-2">
                          <VAlert
                            :type="systemStatus.test_mode === 'real' ? 'success' : 'warning'"
                            variant="tonal"
                            density="compact"
                          >
                            <template #prepend>
                              <VIcon :icon="systemStatus.test_mode === 'real' ? 'tabler-rocket' : 'tabler-flask'" />
                            </template>
                            <span v-if="systemStatus.test_mode === 'real'">
                              🚀 <strong>Mode API Réelle:</strong> Connexion directe à l'API OzonExpress
                            </span>
                            <span v-else>
                              🧪 <strong>Mode Test Mock:</strong> Données simulées pour les tests
                            </span>
                          </VAlert>
                        </div>
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

        <!-- Platform Sync -->
        <VCard class="mb-4" variant="outlined">
          <VCardTitle>Synchronisation Plateforme OzonExpress</VCardTitle>
          <VCardText>
            <div class="d-flex gap-2 mb-4">
              <VBtn
                color="success"
                @click="syncFromPlatform"
                :loading="loading.sync"
                :disabled="!testMode.useReal"
              >
                <VIcon start icon="tabler-refresh" />
                Synchroniser depuis OzonExpress
              </VBtn>
              <VBtn
                color="info"
                variant="outlined"
                @click="loadPlatformParcels"
                :loading="loading.platformParcels"
                :disabled="!testMode.useReal"
              >
                <VIcon start icon="tabler-cloud-download" />
                Voir Colis Plateforme
              </VBtn>
            </div>

            <VAlert v-if="!testMode.useReal" type="warning" variant="tonal" density="compact">
              <VIcon start icon="tabler-alert-triangle" />
              La synchronisation n'est disponible qu'en mode API réelle
            </VAlert>

            <div v-if="syncResult" class="mt-4">
              <VAlert
                :type="syncResult.success ? 'success' : 'error'"
                variant="tonal"
              >
                {{ syncResult.message }}
              </VAlert>

              <div v-if="syncResult.success && syncResult.data" class="mt-2">
                <VRow>
                  <VCol cols="12" md="4">
                    <VCard variant="tonal" color="success">
                      <VCardText class="text-center">
                        <div class="text-h6">{{ syncResult.data.synced_new }}</div>
                        <div class="text-caption">Nouveaux Colis</div>
                      </VCardText>
                    </VCard>
                  </VCol>
                  <VCol cols="12" md="4">
                    <VCard variant="tonal" color="info">
                      <VCardText class="text-center">
                        <div class="text-h6">{{ syncResult.data.updated_existing }}</div>
                        <div class="text-caption">Colis Mis à Jour</div>
                      </VCardText>
                    </VCard>
                  </VCol>
                  <VCol cols="12" md="4">
                    <VCard variant="tonal" color="primary">
                      <VCardText class="text-center">
                        <div class="text-h6">{{ syncResult.data.total_platform_parcels }}</div>
                        <div class="text-caption">Total Plateforme</div>
                      </VCardText>
                    </VCard>
                  </VCol>
                </VRow>
              </div>
            </div>

            <div v-if="platformParcels" class="mt-4">
              <VAlert type="info" variant="tonal" density="compact">
                📦 Colis trouvés sur la plateforme OzonExpress: {{ platformParcels.total_platform_parcels }}
              </VAlert>

              <VDataTable
                v-if="platformParcels.platform_parcels?.length"
                :headers="platformParcelHeaders"
                :items="platformParcels.platform_parcels"
                :items-per-page="10"
                class="elevation-1 mt-2"
              >
                <template #item.status="{ item }">
                  <VChip size="small" :color="getStatusColor(item.status)">
                    {{ item.status }}
                  </VChip>
                </template>

                <template #item.created_at="{ item }">
                  {{ formatDate(item.created_at) }}
                </template>
              </VDataTable>
            </div>
          </VCardText>
        </VCard>

        <!-- Real Parcels Analytics -->
        <VCard class="mb-4" variant="outlined">
          <VCardTitle>Analyse des Colis Réels</VCardTitle>
          <VCardText>
            <VBtn
              color="primary"
              @click="loadRealParcelsAnalytics"
              :loading="loading.analytics"
            >
              Analyser les Colis Réels
            </VBtn>

            <div v-if="parcelsAnalytics" class="mt-4">
              <VRow>
                <VCol cols="12" md="3">
                  <VCard variant="tonal" color="success">
                    <VCardText class="text-center">
                      <div class="text-h4">{{ parcelsAnalytics.real_count }}</div>
                      <div class="text-body-2">Colis Réels</div>
                    </VCardText>
                  </VCard>
                </VCol>
                <VCol cols="12" md="3">
                  <VCard variant="tonal" color="warning">
                    <VCardText class="text-center">
                      <div class="text-h4">{{ parcelsAnalytics.mock_count }}</div>
                      <div class="text-body-2">Colis Mock</div>
                    </VCardText>
                  </VCard>
                </VCol>
                <VCol cols="12" md="3">
                  <VCard variant="tonal" color="info">
                    <VCardText class="text-center">
                      <div class="text-h4">{{ parcelsAnalytics.today_count }}</div>
                      <div class="text-body-2">Aujourd'hui</div>
                    </VCardText>
                  </VCard>
                </VCol>
                <VCol cols="12" md="3">
                  <VCard variant="tonal" color="primary">
                    <VCardText class="text-center">
                      <div class="text-h4">{{ parcelsAnalytics.total_count }}</div>
                      <div class="text-body-2">Total</div>
                    </VCardText>
                  </VCard>
                </VCol>
              </VRow>

              <VAlert v-if="parcelsAnalytics.real_count > 0" type="success" variant="tonal" class="mt-4">
                🎉 Excellent! Vous avez {{ parcelsAnalytics.real_count }} colis réels créés via l'API OzonExpress
              </VAlert>
            </div>
          </VCardText>
        </VCard>

        <!-- Recent Real Parcels -->
        <VCard class="mb-4" variant="outlined">
          <VCardTitle>Colis Réels Récents</VCardTitle>
          <VCardText>
            <div class="d-flex gap-2 mb-4">
              <VBtn
                color="success"
                @click="loadRealParcelsOnly"
                :loading="loading.parcels"
              >
                Charger Colis Réels
              </VBtn>
              <VBtn
                color="info"
                variant="outlined"
                @click="loadShippingParcels"
                :loading="loading.parcels"
              >
                Tous les Colis
              </VBtn>
            </div>
            
            <div v-if="shippingParcels" class="mt-4">
              <div class="d-flex justify-space-between align-center mb-2">
                <div class="text-subtitle-1">
                  <span v-if="shippingParcels.filtered_type === 'real_only'">
                    Colis Réels: {{ shippingParcels.recent_parcels.length }}
                  </span>
                  <span v-else>
                    Total des colis: {{ shippingParcels.total_parcels }}
                  </span>
                </div>
                <VChip
                  :color="shippingParcels.filtered_type === 'real_only' ? 'success' : 'info'"
                  size="small"
                >
                  {{ shippingParcels.filtered_type === 'real_only' ? 'Colis Réels Uniquement' : 'Tous les Colis' }}
                </VChip>
              </div>
              
              <VDataTable
                :headers="parcelHeaders"
                :items="shippingParcels.recent_parcels"
                :items-per-page="10"
                class="elevation-1"
              >
                <template #item.tracking_number="{ item }">
                  <div class="d-flex align-center gap-2">
                    <span class="font-weight-medium">{{ item.tracking_number }}</span>
                    <VChip v-if="item.meta?.mock_data" color="warning" size="x-small">
                      MOCK
                    </VChip>
                    <VChip v-else color="success" size="x-small">
                      RÉEL
                    </VChip>
                  </div>
                </template>

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
  bulkTest: false,
  analytics: false,
  sync: false,
  platformParcels: false
})

const systemStatus = ref<any>(null)
const apiTestResult = ref<any>(null)
const shippingParcels = ref<any>(null)
const bulkTestResult = ref<any>(null)
const parcelsAnalytics = ref<any>(null)
const syncResult = ref<any>(null)
const platformParcels = ref<any>(null)

// Test mode state
const testMode = ref({
  useReal: true, // Start with real API mode
  currentMode: 'real'
})

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

const platformParcelHeaders = [
  { title: 'Tracking', key: 'tracking_number' },
  { title: 'Statut', key: 'status' },
  { title: 'Ville', key: 'city' },
  { title: 'Prix', key: 'price' },
  { title: 'Date', key: 'created_at' }
]

// Methods
const toggleTestMode = async () => {
  loading.value.toggle = true
  try {
    // Update the mode
    testMode.value.currentMode = testMode.value.useReal ? 'real' : 'mock'

    // Clear previous results when switching modes
    systemStatus.value = null
    apiTestResult.value = null
    shippingParcels.value = null
    bulkTestResult.value = null
    parcelsAnalytics.value = null

    console.log(`Switched to ${testMode.value.currentMode} mode`)
  } catch (error) {
    console.error('Error toggling test mode:', error)
  } finally {
    loading.value.toggle = false
  }
}

const loadSystemStatus = async () => {
  loading.value.systemStatus = true
  try {
    const endpoint = testMode.value.useReal
      ? '/admin/test/system-status'
      : '/admin/test/system-status?mock=true'

    const { data, error } = await useApi(endpoint)
    if (error.value) {
      console.error('Error loading system status:', error.value)
    } else {
      systemStatus.value = {
        ...(data.value as any),
        test_mode: testMode.value.currentMode
      }
    }
  } catch (error) {
    console.error('Error loading system status:', error)
  } finally {
    loading.value.systemStatus = false
  }
}

const loadRealParcelsAnalytics = async () => {
  loading.value.analytics = true
  try {
    const endpoint = testMode.value.useReal
      ? '/admin/test/shipping-parcels'
      : '/admin/test/shipping-parcels?mock=true'

    const { data, error } = await useApi(endpoint)
    if (error.value) {
      console.error('Error loading parcels analytics:', error.value)
    } else {
      const parcelsData = data.value as any

      // Calculate analytics based on current mode
      if (testMode.value.useReal) {
        const realParcels = parcelsData.recent_parcels?.filter((p: any) => !p.meta?.mock_data) || []
        const mockParcels = parcelsData.recent_parcels?.filter((p: any) => p.meta?.mock_data) || []
        const today = new Date().toISOString().split('T')[0]
        const todayParcels = parcelsData.recent_parcels?.filter((p: any) =>
          p.created_at?.startsWith(today)
        ) || []

        parcelsAnalytics.value = {
          total_count: parcelsData.total_parcels || 0,
          real_count: (parcelsData.total_parcels || 0) - mockParcels.length,
          mock_count: mockParcels.length,
          today_count: todayParcels.length,
          recent_real: realParcels.slice(0, 10),
          recent_mock: mockParcels.slice(0, 5),
          mode: 'real'
        }
      } else {
        // Mock mode - show mock data analytics
        parcelsAnalytics.value = {
          total_count: 25,
          real_count: 0,
          mock_count: 25,
          today_count: 5,
          recent_real: [],
          recent_mock: Array.from({length: 10}, (_, i) => ({
            tracking_number: `MOCK${Date.now()}${i}`,
            status: 'pending',
            created_at: new Date().toISOString()
          })),
          mode: 'mock'
        }
      }
    }
  } catch (error) {
    console.error('Error loading parcels analytics:', error)
  } finally {
    loading.value.analytics = false
  }
}

const loadRealParcelsOnly = async () => {
  loading.value.parcels = true
  try {
    if (testMode.value.useReal) {
      const { data, error } = await useApi('/admin/test/shipping-parcels')
      if (error.value) {
        console.error('Error loading shipping parcels:', error.value)
      } else {
        const parcelsData = data.value as any
        // Filter to show only real parcels
        const realParcels = parcelsData.recent_parcels?.filter((p: any) => !p.meta?.mock_data) || []

        shippingParcels.value = {
          ...parcelsData,
          recent_parcels: realParcels,
          filtered_type: 'real_only',
          test_mode: 'real'
        }
      }
    } else {
      // Mock mode - show mock real parcels
      shippingParcels.value = {
        total_parcels: 0,
        recent_parcels: [],
        filtered_type: 'real_only',
        test_mode: 'mock',
        message: 'Mode test: Aucun colis réel en mode mock'
      }
    }
  } catch (error) {
    console.error('Error loading real parcels:', error)
  } finally {
    loading.value.parcels = false
  }
}

const loadShippingParcels = async () => {
  loading.value.parcels = true
  try {
    if (testMode.value.useReal) {
      const { data, error } = await useApi('/admin/test/shipping-parcels')
      if (error.value) {
        console.error('Error loading shipping parcels:', error.value)
      } else {
        const parcelsData = data.value as any
        shippingParcels.value = {
          ...parcelsData,
          filtered_type: 'all',
          test_mode: 'real'
        }
      }
    } else {
      // Mock mode - generate mock parcels
      const mockParcels = Array.from({length: 15}, (_, i) => ({
        id: i + 1,
        tracking_number: `MOCK${Date.now()}${String(i).padStart(3, '0')}`,
        status: ['pending', 'shipped', 'delivered'][i % 3],
        provider: 'OzonExpress',
        client_name: `Client Test ${i + 1}`,
        city: ['Casablanca', 'Rabat', 'Marrakech'][i % 3],
        price: (Math.random() * 500 + 50).toFixed(2),
        created_at: new Date(Date.now() - i * 3600000).toISOString(),
        meta: { mock_data: true },
        is_real: false,
        type: 'mock'
      }))

      shippingParcels.value = {
        total_parcels: 15,
        real_parcels: 0,
        mock_parcels: 15,
        recent_parcels: mockParcels,
        filtered_type: 'all',
        test_mode: 'mock'
      }
    }
  } catch (error) {
    console.error('Error loading shipping parcels:', error)
  } finally {
    loading.value.parcels = false
  }
}

const syncFromPlatform = async () => {
  loading.value.sync = true
  try {
    const { data, error } = await useApi('/admin/test/sync-parcels', { method: 'POST' })
    if (error.value) {
      console.error('Error syncing from platform:', error.value)
      syncResult.value = {
        success: false,
        message: 'Erreur lors de la synchronisation: ' + (error.value as any)?.message
      }
    } else {
      syncResult.value = data.value
      // Refresh parcels data after sync
      if ((data.value as any)?.success) {
        await loadShippingParcels()
      }
    }
  } catch (error) {
    console.error('Error syncing from platform:', error)
    syncResult.value = {
      success: false,
      message: 'Erreur lors de la synchronisation: ' + (error as any)?.message
    }
  } finally {
    loading.value.sync = false
  }
}

const loadPlatformParcels = async () => {
  loading.value.platformParcels = true
  try {
    const { data, error } = await useApi('/admin/test/platform-parcels')
    if (error.value) {
      console.error('Error loading platform parcels:', error.value)
      platformParcels.value = {
        success: false,
        message: 'Erreur lors du chargement des colis plateforme: ' + (error.value as any)?.message
      }
    } else {
      platformParcels.value = data.value
    }
  } catch (error) {
    console.error('Error loading platform parcels:', error)
    platformParcels.value = {
      success: false,
      message: 'Erreur lors du chargement des colis plateforme: ' + (error as any)?.message
    }
  } finally {
    loading.value.platformParcels = false
  }
}

const testBulkOperations = async () => {
  loading.value.bulkTest = true
  try {
    if (testMode.value.useReal) {
      const { data, error } = await useApi('/admin/test/bulk-operations')
      if (error.value) {
        console.error('Error testing bulk operations:', error.value)
        bulkTestResult.value = {
          success: false,
          message: 'Erreur lors du test des opérations en lot: ' + (error.value as any)?.message,
          test_mode: 'real'
        }
      } else {
        bulkTestResult.value = {
          ...(data.value as any),
          test_mode: 'real'
        }
      }
    } else {
      // Mock mode - simulate bulk operations
      bulkTestResult.value = {
        success: true,
        message: 'Test des opérations en lot réussi (mode mock)',
        operations_tested: 3,
        results: [
          { operation: 'Création de colis', status: 'success', message: 'Mock: 5 colis créés' },
          { operation: 'Mise à jour statuts', status: 'success', message: 'Mock: 3 statuts mis à jour' },
          { operation: 'Synchronisation', status: 'success', message: 'Mock: Synchronisation simulée' }
        ],
        test_mode: 'mock',
        note: 'Données simulées pour les tests'
      }
    }
  } catch (error) {
    console.error('Error testing bulk operations:', error)
    bulkTestResult.value = {
      success: false,
      message: 'Erreur lors du test des opérations en lot: ' + (error as any)?.message,
      test_mode: testMode.value.currentMode
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
