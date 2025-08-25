<template>
  <div class="pa-6">
    <VCard>
      <VCardTitle class="d-flex align-center gap-2">
        <VIcon icon="tabler-bug" />
        {{ t('admin.debug.ozonExpressTitle') }}
      </VCardTitle>
      
      <VCardText>
        <VAlert
          type="info"
          variant="tonal"
          class="mb-4"
        >
          {{ t('admin.debug.ozonExpressDescription') }}
        </VAlert>

        <!-- Mode Toggle -->
        <VCard class="mb-4" variant="outlined">
          <VCardTitle>{{ t('admin.debug.testMode') }}</VCardTitle>
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
                      {{ testMode.useReal ? t('admin.debug.realApiMode') : t('admin.debug.mockTestMode') }}
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
                🚀 <strong>{{ t('admin.debug.productionMode') }}:</strong> {{ t('admin.debug.productionModeDesc') }}
              </span>
              <span v-else>
                🧪 <strong>{{ t('admin.debug.testMode') }}:</strong> {{ t('admin.debug.testModeDesc') }}
              </span>
            </VAlert>
          </VCardText>
        </VCard>

        <!-- System Status -->
        <VCard class="mb-4" variant="outlined">
          <VCardTitle>{{ t('admin.debug.systemStatus') }}</VCardTitle>
          <VCardText>
            <VBtn
              color="primary"
              @click="loadSystemStatus"
              :loading="loading.systemStatus"
            >
              {{ t('admin.debug.checkStatus') }}
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
          <VCardTitle>{{ t('admin.debug.platformSync') }}</VCardTitle>
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
                {{ t('actions.viewPlatformPackage') }}
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
                    {{ getStatusLabel(item.status) }}
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
          <VCardTitle>{{ t('admin.debug.realParcelsAnalytics') }}</VCardTitle>
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
                      <div class="text-body-2">{{ t('admin_total') }}</div>
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
          <VCardTitle>{{ t('admin.debug.recentRealParcels') }}</VCardTitle>
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
                    {{ getStatusLabel(item.status) }}
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
                    {{ t('actions.viewDetails') }}
                  </VBtn>
                </template>
              </VDataTable>
            </div>
          </VCardText>
        </VCard>

        <!-- Quick Actions -->
        <VCard variant="outlined">
          <VCardTitle>{{ t('admin.debug.quickActions') }}</VCardTitle>
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

              <VBtn
                color="success"
                @click="testCreateParcel"
                :loading="loading.createTest"
                :disabled="!testMode.useReal"
              >
                <VIcon start icon="tabler-package-export" />
                Test Send Colis
              </VBtn>

              <VBtn
                color="info"
                @click="showTrackingDialog = true"
                :disabled="!testMode.useReal"
              >
                <VIcon start icon="tabler-search" />
                Suivre Colis
              </VBtn>

              <VBtn
                v-if="createTestResult?.success && createTestResult?.data?.tracking_number"
                color="secondary"
                @click="trackLastCreatedParcel"
                :loading="loading.trackTest"
              >
                <VIcon start icon="tabler-eye" />
                Suivre Dernier Colis Créé
              </VBtn>
            </div>

            <VAlert v-if="!testMode.useReal" type="warning" variant="tonal" density="compact" class="mt-2">
              <VIcon start icon="tabler-alert-triangle" />
              Les tests de création et suivi ne sont disponibles qu'en mode API réelle
            </VAlert>
            
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

            <!-- Test Create Parcel Results -->
            <div v-if="createTestResult" class="mt-4">
              <VAlert
                :type="createTestResult.success ? 'success' : 'error'"
                variant="tonal"
              >
                {{ createTestResult.message }}
              </VAlert>

              <div v-if="createTestResult.success && createTestResult.data" class="mt-2">
                <VCard variant="tonal" color="success">
                  <VCardTitle class="text-h6">Colis Créé avec Succès</VCardTitle>
                  <VCardText>
                    <div class="d-flex flex-column gap-2">
                      <div><strong>Numéro de Suivi:</strong> {{ createTestResult.data.tracking_number }}</div>
                      <div><strong>ID Local:</strong> {{ createTestResult.data.local_parcel_id }}</div>
                      <div><strong>Client Test:</strong> {{ createTestResult.data.test_data?.client_name }}</div>
                      <div><strong>Ville:</strong> {{ createTestResult.data.test_data?.city }}</div>
                      <div><strong>Prix:</strong> {{ createTestResult.data.test_data?.price }} DH</div>
                    </div>

                    <VExpansionPanels class="mt-3">
                      <VExpansionPanel>
                        <VExpansionPanelTitle>Réponse Plateforme</VExpansionPanelTitle>
                        <VExpansionPanelText>
                          <pre class="text-caption">{{ JSON.stringify(createTestResult.data.platform_response, null, 2) }}</pre>
                        </VExpansionPanelText>
                      </VExpansionPanel>
                    </VExpansionPanels>
                  </VCardText>
                </VCard>
              </div>
            </div>

            <!-- Test Track Parcel Results -->
            <div v-if="trackTestResult" class="mt-4">
              <VAlert
                :type="trackTestResult.success ? 'success' : 'error'"
                variant="tonal"
              >
                {{ trackTestResult.message }}
              </VAlert>

              <div v-if="trackTestResult.success && trackTestResult.data" class="mt-2">
                <VCard variant="tonal" color="info">
                  <VCardTitle class="text-h6">Informations de Suivi</VCardTitle>
                  <VCardText>
                    <div class="d-flex flex-column gap-2 mb-3">
                      <div><strong>Numéro de Suivi:</strong> {{ trackTestResult.data.tracking_number }}</div>
                      <div><strong>Existe Localement:</strong>
                        <VChip :color="trackTestResult.data.comparison?.exists_locally ? 'success' : 'error'" size="small">
                          {{ trackTestResult.data.comparison?.exists_locally ? 'Oui' : 'Non' }}
                        </VChip>
                      </div>
                      <div><strong>Existe sur Plateforme:</strong>
                        <VChip :color="trackTestResult.data.comparison?.exists_on_platform ? 'success' : 'error'" size="small">
                          {{ trackTestResult.data.comparison?.exists_on_platform ? 'Oui' : 'Non' }}
                        </VChip>
                      </div>
                      <div v-if="trackTestResult.data.comparison?.status_match !== null">
                        <strong>Statuts Correspondent:</strong>
                        <VChip :color="trackTestResult.data.comparison?.status_match ? 'success' : 'warning'" size="small">
                          {{ trackTestResult.data.comparison?.status_match ? 'Oui' : 'Non' }}
                        </VChip>
                      </div>
                    </div>

                    <VExpansionPanels>
                      <VExpansionPanel v-if="trackTestResult.data.local_parcel">
                        <VExpansionPanelTitle>Données Locales</VExpansionPanelTitle>
                        <VExpansionPanelText>
                          <pre class="text-caption">{{ JSON.stringify(trackTestResult.data.local_parcel, null, 2) }}</pre>
                        </VExpansionPanelText>
                      </VExpansionPanel>

                      <VExpansionPanel v-if="trackTestResult.data.platform_parcel_info?.success">
                        <VExpansionPanelTitle>Données Plateforme</VExpansionPanelTitle>
                        <VExpansionPanelText>
                          <pre class="text-caption">{{ JSON.stringify(trackTestResult.data.platform_parcel_info.data, null, 2) }}</pre>
                        </VExpansionPanelText>
                      </VExpansionPanel>

                      <VExpansionPanel v-if="trackTestResult.data.platform_tracking_info?.success">
                        <VExpansionPanelTitle>Historique de Suivi</VExpansionPanelTitle>
                        <VExpansionPanelText>
                          <pre class="text-caption">{{ JSON.stringify(trackTestResult.data.platform_tracking_info.data, null, 2) }}</pre>
                        </VExpansionPanelText>
                      </VExpansionPanel>
                    </VExpansionPanels>
                  </VCardText>
                </VCard>
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCardText>
    </VCard>

    <!-- Enhanced Debug API Section -->
    <VCard class="mt-6">
      <VCardTitle class="d-flex align-center gap-2">
        <VIcon icon="tabler-api" />
        {{ t('admin.debug.advancedApiDebug') }}
      </VCardTitle>
      <VCardText>
        <VRow>
          <!-- Send Parcel Card -->
          <VCol cols="12" lg="6">
            <VCard variant="outlined">
              <VCardTitle class="d-flex align-center gap-2">
                <VIcon icon="tabler-package-export" />
                {{ t('admin.debug.sendParcel') }}
              </VCardTitle>
              <VCardText>
                <VTabs v-model="sendTab" class="mb-4">
                  <VTab value="manual">{{ t('admin.debug.manualEntry') }}</VTab>
                  <VTab value="order">{{ t('admin.debug.fromOrder') }}</VTab>
                </VTabs>

                <VWindow v-model="sendTab">
                  <!-- Manual Entry Tab -->
                  <VWindowItem value="manual">
                    <VForm @submit.prevent="sendManualParcel">
                      <VRow>
                        <VCol cols="12" md="6">
                          <VTextField
                            v-model="manualForm.receiver"
                            :label="t('admin.debug.recipient')"
                            required
                          />
                        </VCol>
                        <VCol cols="12" md="6">
                          <VTextField
                            v-model="manualForm.phone"
                            :label="t('admin.debug.phone')"
                            required
                          />
                        </VCol>
                        <VCol cols="12" md="6">
                          <VSelect
                            v-model="manualForm.city"
                            :items="shippingStore.cities"
                            item-title="name"
                            item-value="city_id"
                            :label="t('admin.debug.city')"
                            :placeholder="t('admin_debug_city_placeholder')"
                            required
                            :loading="shippingStore.loading"
                          />
                        </VCol>
                        <VCol cols="12" md="6">
                          <VTextField
                            v-model="manualForm.price"
                            :label="t('admin.debug.price')"
                            type="number"
                            step="0.01"
                            required
                          />
                        </VCol>
                        <VCol cols="12">
                          <VTextarea
                            v-model="manualForm.address"
                            :label="t('admin.debug.address')"
                            required
                          />
                        </VCol>
                        <VCol cols="12" md="6">
                          <VTextField
                            v-model="manualForm.nature"
                            :label="t('admin.debug.parcelNature')"
                            required
                          />
                        </VCol>
                        <VCol cols="12" md="6">
                          <VSelect
                            v-model="manualForm.stock"
                            :items="[
                              { title: 'Ramassage', value: 0 },
                              { title: 'Stock', value: 1 }
                            ]"
                            :label="t('admin.debug.parcelType')"
                            required
                          />
                        </VCol>
                        <VCol cols="12">
                          <VBtn
                            type="submit"
                            color="primary"
                            :loading="ozonDebugStore.loading.sendParcel"
                            :disabled="!testMode.useReal"
                            block
                          >
                            <VIcon start icon="tabler-send" />
                            Envoyer Colis
                          </VBtn>
                        </VCol>
                      </VRow>
                    </VForm>
                  </VWindowItem>

                  <!-- From Order Tab -->
                  <VWindowItem value="order">
                    <VForm @submit.prevent="sendFromOrder">
                      <VRow>
                        <VCol cols="12">
                          <VTextField
                            v-model="selectedOrderId"
                            label="ID de la commande"
                            :placeholder="t('admin_debug_order_id_placeholder')"
                            required
                          />
                        </VCol>
                        <VCol cols="12">
                          <VBtn
                            type="submit"
                            color="primary"
                            :loading="ozonDebugStore.loading.sendParcel"
                            :disabled="!selectedOrderId || !testMode.useReal"
                            block
                          >
                            <VIcon start icon="tabler-send" />
                            Envoyer Colis
                          </VBtn>
                        </VCol>
                      </VRow>
                    </VForm>
                  </VWindowItem>
                </VWindow>

                <!-- Send Result -->
                <VAlert
                  v-if="sendResult"
                  :type="sendResult.success ? 'success' : 'error'"
                  variant="tonal"
                  class="mt-4"
                >
                  <div class="d-flex align-center justify-space-between">
                    <span>{{ sendResult.message }}</span>
                    <VBtn
                      v-if="sendResult.success && sendResult.tracking_number"
                      size="small"
                      variant="text"
                      @click="copyToClipboard(sendResult.tracking_number)"
                    >
                      <VIcon icon="tabler-copy" />
                      Copier
                    </VBtn>
                  </div>
                  <div v-if="sendResult.success && sendResult.tracking_number" class="mt-2">
                    <VChip
                      size="small"
                      color="primary"
                      variant="tonal"
                      class="font-mono"
                    >
                      {{ sendResult.tracking_number }}
                    </VChip>
                  </div>
                </VAlert>
              </VCardText>
            </VCard>
          </VCol>

          <!-- Track Parcel Card -->
          <VCol cols="12" lg="6">
            <VCard variant="outlined">
              <VCardTitle class="d-flex align-center gap-2">
                <VIcon icon="tabler-search" />
                {{ t('admin.debug.trackParcel') }}
              </VCardTitle>
              <VCardText>
                <VForm @submit.prevent="trackParcelEnhanced">
                  <VRow>
                    <VCol cols="12">
                      <VTextField
                        v-model="enhancedTrackingNumber"
                        :label="t('admin.debug.trackingNumber')"
                        :placeholder="t('admin_debug_tracking_placeholder')"
                        required
                      />
                    </VCol>
                    <VCol cols="12">
                      <VBtn
                        type="submit"
                        color="info"
                        :loading="ozonDebugStore.loading.track"
                        :disabled="!enhancedTrackingNumber || !testMode.useReal"
                        block
                      >
                        <VIcon start icon="tabler-search" />
                        Suivre Maintenant
                      </VBtn>
                    </VCol>
                  </VRow>
                </VForm>

                <!-- Track Result -->
                <VAlert
                  v-if="trackResult"
                  :type="trackResult.success ? 'success' : 'error'"
                  variant="tonal"
                  class="mt-4"
                >
                  {{ trackResult.message }}
                </VAlert>

                <!-- Tracking Details -->
                <div v-if="trackResult?.success && trackResult.data" class="mt-4">
                  <VCard variant="outlined">
                    <VCardTitle>{{ t('admin.debug.parcelDetails') }}</VCardTitle>
                    <VCardText>
                      <VRow>
                        <VCol cols="12" md="6">
                          <div class="text-caption text-medium-emphasis">{{ t('admin_status') }}</div>
                          <VChip
                            size="small"
                            :color="getStatusColor(trackResult.data.parcel?.status)"
                            variant="tonal"
                          >
                            {{ getStatusLabel(trackResult.data.parcel?.status) }}
                          </VChip>
                        </VCol>
                        <VCol cols="12" md="6">
                          <div class="text-caption text-medium-emphasis">Dernière mise à jour</div>
                          <div class="text-body-2">
                            {{ formatDate(trackResult.data.parcel?.last_status_at) }}
                          </div>
                        </VCol>
                        <VCol cols="12" v-if="trackResult.data.parcel?.last_status_text">
                          <div class="text-caption text-medium-emphasis">Dernier statut</div>
                          <div class="text-body-2">
                            {{ trackResult.data.parcel.last_status_text }}
                          </div>
                        </VCol>
                      </VRow>
                    </VCardText>
                  </VCard>
                </div>
              </VCardText>
            </VCard>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Delivery Note Testing -->
    <VCard class="mt-6">
      <VCardTitle class="d-flex align-center gap-2">
        <VIcon icon="tabler-file-plus" />
        {{ t('admin.debug.deliveryNoteTest') }}
      </VCardTitle>
      <VCardText>
        <VAlert
          type="info"
          variant="tonal"
          class="mb-4"
        >
          <VAlertTitle>Test du Workflow Bon de Livraison</VAlertTitle>
          Testez le processus complet : Créer → Ajouter Colis → Sauvegarder → PDFs
        </VAlert>

        <div class="d-flex flex-wrap gap-2 mb-4">
          <VBtn
            color="primary"
            variant="outlined"
            @click="testCreateDeliveryNote"
            :loading="loading.deliveryNote"
          >
            <VIcon start icon="tabler-plus" />
            1. Créer BL
          </VBtn>

          <VBtn
            color="info"
            variant="outlined"
            :disabled="!deliveryNoteRef"
            @click="testAddParcelsToDeliveryNote"
            :loading="loading.deliveryNote"
          >
            <VIcon start icon="tabler-package-import" />
            2. Ajouter Colis
          </VBtn>

          <VBtn
            color="success"
            variant="outlined"
            :disabled="!deliveryNoteRef"
            @click="testSaveDeliveryNote"
            :loading="loading.deliveryNote"
          >
            <VIcon start icon="tabler-device-floppy" />
            3. Sauvegarder
          </VBtn>

          <VBtn
            color="secondary"
            variant="outlined"
            :disabled="!deliveryNoteRef"
            @click="testGetDeliveryNotePdf"
            :loading="loading.deliveryNote"
          >
            <VIcon start icon="tabler-file-download" />
            4. Obtenir PDFs
          </VBtn>
        </div>

        <div v-if="deliveryNoteRef" class="mb-4">
          <VAlert type="success" variant="tonal">
            <VAlertTitle>Référence BL Active</VAlertTitle>
            {{ deliveryNoteRef }}
          </VAlert>
        </div>

        <div v-if="deliveryNoteResult" class="mt-4">
          <VAlert
            :type="deliveryNoteResult.success ? 'success' : 'error'"
            variant="tonal"
          >
            <VAlertTitle>{{ deliveryNoteResult.success ? t('admin_debug_success') : t('admin_debug_error') }}</VAlertTitle>
            {{ deliveryNoteResult.message }}
          </VAlert>

          <div v-if="deliveryNoteResult.success && deliveryNoteResult.data" class="mt-3">
            <VCard variant="outlined">
              <VCardTitle>Résultat</VCardTitle>
              <VCardText>
                <pre class="text-caption">{{ JSON.stringify(deliveryNoteResult.data, null, 2) }}</pre>
              </VCardText>
            </VCard>
          </div>
        </div>
      </VCardText>
    </VCard>

    <!-- Meta Dialog -->
    <VDialog v-model="metaDialog.show" max-width="600">
      <VCard>
        <VCardTitle>{{ t('admin.debug.parcelDetails') }}</VCardTitle>
        <VCardText>
          <pre class="text-caption">{{ JSON.stringify(metaDialog.data, null, 2) }}</pre>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn @click="metaDialog.show = false">{{ t('actions.close') }}</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Tracking Dialog -->
    <VDialog v-model="showTrackingDialog" max-width="500">
      <VCard>
        <VCardTitle>{{ t('admin.debug.trackParcel') }}</VCardTitle>
        <VCardText>
          <VTextField
            v-model="trackingNumber"
            :label="t('admin.debug.trackingNumber')"
            :placeholder="t('admin_debug_tracking_example')"
            variant="outlined"
            :loading="loading.trackTest"
          />

          <div v-if="createTestResult?.success && createTestResult?.data?.tracking_number" class="mt-2">
            <VBtn
              size="small"
              color="secondary"
              variant="outlined"
              @click="trackingNumber = createTestResult.data.tracking_number"
            >
              <VIcon start icon="tabler-copy" />
              {{ t('admin.debug.useLastCreated') }} ({{ createTestResult.data.tracking_number }})
            </VBtn>
          </div>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            color="grey"
            variant="text"
            @click="showTrackingDialog = false"
          >
            {{ t('admin.debug.cancel') }}
          </VBtn>
          <VBtn
            color="primary"
            @click="testTrackParcel"
            :loading="loading.trackTest"
            :disabled="!trackingNumber.trim()"
          >
            {{ t('admin.debug.track') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useOzonDebugStore } from '@/stores/admin/ozonDebug'
import { useShippingStore } from '@/stores/admin/shipping'
import { useNotifications } from '@/composables/useNotifications'
import { useI18n } from 'vue-i18n'

definePage({
  meta: {
    title: 'Debug OzonExpress',
    requiresAuth: true,
    requiresRole: 'admin'
  }
})

// Stores
const ozonDebugStore = useOzonDebugStore()
const shippingStore = useShippingStore()
const { showSuccess, showError } = useNotifications()
const { t } = useI18n()

// State
const loading = ref({
  systemStatus: false,
  apiTest: false,
  parcels: false,
  toggle: false,
  bulkTest: false,
  analytics: false,
  sync: false,
  platformParcels: false,
  createTest: false,
  trackTest: false,
  deliveryNote: false
})

const systemStatus = ref<any>(null)
const apiTestResult = ref<any>(null)
const shippingParcels = ref<any>(null)
const bulkTestResult = ref<any>(null)
const parcelsAnalytics = ref<any>(null)
const syncResult = ref<any>(null)
const platformParcels = ref<any>(null)
const createTestResult = ref<any>(null)
const trackTestResult = ref<any>(null)
const showTrackingDialog = ref(false)
const trackingNumber = ref('')

// Delivery Note state
const deliveryNoteRef = ref('')
const deliveryNoteResult = ref<any>(null)

// Enhanced debug state
const sendTab = ref('manual')
const selectedOrderId = ref('')
const enhancedTrackingNumber = ref('')
const sendResult = ref<any>(null)
const trackResult = ref<any>(null)

const manualForm = ref({
  receiver: '',
  phone: '',
  city: '', // Will store city_id instead of city name
  address: '',
  price: 0,
  nature: '',
  stock: 0,
})

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
  { title: t('labels.details'), key: 'meta', sortable: false }
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
        message: t('admin_debug_sync_error') + ': ' + (error.value as any)?.message
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
      message: t('admin_debug_sync_error') + ': ' + (error as any)?.message
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
        message: t('admin_debug_platform_parcels_loading_error') + ': ' + (error.value as any)?.message
      }
    } else {
      platformParcels.value = data.value
    }
  } catch (error) {
    console.error('Error loading platform parcels:', error)
    platformParcels.value = {
      success: false,
      message: t('admin_debug_platform_parcels_loading_error') + ': ' + (error as any)?.message
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
          message: t('admin_debug_batch_operations_error') + ': ' + (error.value as any)?.message,
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

const testCreateParcel = async () => {
  loading.value.createTest = true
  try {
    // Use the new debug API with predefined test data (use city ID)
    const result = await ozonDebugStore.sendParcel({
      receiver: 'Test Client',
      phone: '0612345678',
      city: '97', // Casablanca city ID
      address: '123 Rue Test, Quartier Test',
      price: 50.00,
      nature: 'Test Product',
      stock: 0, // Ramassage
      products: [
        { ref: 'TEST-PRODUCT-001', qnty: 1 }
      ]
    })

    createTestResult.value = result

    if (result.success) {
      showSuccess(t('admin_debug_test_parcel_created'))
      await loadShippingParcels()
    } else {
      showError(result.message || 'Erreur lors de la création du colis test')
    }
  } catch (error: any) {
    console.error('Error creating test parcel:', error)
    createTestResult.value = {
      success: false,
      message: 'Erreur lors de la création du colis test: ' + error.message
    }
    showError(error.message || 'Erreur lors de la création du colis test')
  } finally {
    loading.value.createTest = false
  }
}

const testTrackParcel = async () => {
  if (!trackingNumber.value.trim()) {
    trackTestResult.value = {
      success: false,
      message: 'Veuillez entrer un numéro de suivi'
    }
    return
  }

  loading.value.trackTest = true
  try {
    // Use the new debug API
    const result = await ozonDebugStore.track({
      tracking_number: trackingNumber.value.trim()
    })

    trackTestResult.value = result
    showTrackingDialog.value = false

    if (result.success) {
      showSuccess(t('admin_debug_tracking_updated'))
      await loadShippingParcels()
    } else {
      showError(result.message || 'Erreur lors du suivi du colis')
    }
  } catch (error: any) {
    console.error('Error tracking parcel:', error)
    trackTestResult.value = {
      success: false,
      message: 'Erreur lors du suivi du colis: ' + error.message
    }
    showError(error.message || 'Erreur lors du suivi du colis')
  } finally {
    loading.value.trackTest = false
  }
}

const trackLastCreatedParcel = async () => {
  if (!createTestResult.value?.data?.tracking_number && !createTestResult.value?.tracking_number) {
    trackTestResult.value = {
      success: false,
      message: 'Aucun colis test créé récemment'
    }
    return
  }

  const lastTrackingNumber = createTestResult.value.data?.tracking_number || createTestResult.value.tracking_number

  loading.value.trackTest = true
  try {
    // Use the new debug API
    const result = await ozonDebugStore.track({
      tracking_number: lastTrackingNumber
    })

    trackTestResult.value = result

    if (result.success) {
      showSuccess(t('admin_debug_last_parcel_tracking_updated'))
      await loadShippingParcels()
    } else {
      showError(result.message || 'Erreur lors du suivi du colis créé')
    }
  } catch (error: any) {
    console.error('Error tracking last created parcel:', error)
    trackTestResult.value = {
      success: false,
      message: 'Erreur lors du suivi du colis créé: ' + error.message
    }
    showError(error.message || 'Erreur lors du suivi du colis créé')
  } finally {
    loading.value.trackTest = false
  }
}

const showMetaDialog = (item: any) => {
  metaDialog.value.data = item.meta
  metaDialog.value.show = true
}

const getStatusColor = (status: string) => {
  switch (status?.toLowerCase()) {
    case 'delivered': return 'success'
    case 'shipped':
    case 'in_transit':
    case 'out_for_delivery': return 'info'
    case 'pending':
    case 'received':
    case 'ready_for_delivery': return 'warning'
    case 'cancelled':
    case 'refused':
    case 'delivery_failed': return 'error'
    case 'returned':
    case 'return_delivered': return 'secondary'
    default: return 'secondary'
  }
}

const getStatusLabel = (status: string) => {
  const statusLabels: Record<string, string> = {
    'pending': 'En Attente',
    'received': 'Reçu',
    'in_transit': 'En Transit',
    'out_for_delivery': 'En Cours de Livraison',
    'delivered': 'Livré',
    'returned': t('order.status.returned'),
    'refused': 'Refusé',
    'cancelled': 'Annulé',
    'shipped': 'Expédié',
    'at_facility': 'Arrivé au Centre',
    'ready_for_delivery': 'Prêt pour Livraison',
    'delivery_attempted': 'Tentative de Livraison',
    'delivery_failed': 'Échec de Livraison',
    'return_in_progress': t('order.status.returnInProgress'),
    'return_delivered': t('order.status.returnDelivered'),
    'unknown': 'Statut Inconnu'
  }
  return statusLabels[status?.toLowerCase()] || status || 'Inconnu'
}

const formatDate = (date: string) => {
  return new Date(date).toLocaleString('fr-FR')
}

// Enhanced debug functions
const sendManualParcel = async () => {
  sendResult.value = null
  try {
    const result = await ozonDebugStore.sendParcel({
      receiver: manualForm.value.receiver,
      phone: manualForm.value.phone,
      city: manualForm.value.city,
      address: manualForm.value.address,
      price: manualForm.value.price,
      nature: manualForm.value.nature,
      stock: manualForm.value.stock,
      products: [
        { ref: 'DEBUG-PRODUCT', qnty: 1 }
      ]
    })

    sendResult.value = result

    if (result.success) {
      showSuccess(t('admin_debug_parcel_sent'))
      // Reset form
      manualForm.value = {
        receiver: '',
        phone: '',
        city: '',
        address: '',
        price: 0,
        nature: '',
        stock: 0,
      }
      // Refresh parcels data
      await loadShippingParcels()
    } else {
      showError(result.message || 'Erreur lors de l\'envoi du colis')
    }
  } catch (error: any) {
    showError(error.message || 'Erreur lors de l\'envoi du colis')
  }
}

const sendFromOrder = async () => {
  if (!selectedOrderId.value) return

  sendResult.value = null
  try {
    const result = await ozonDebugStore.sendParcel({
      commande_id: selectedOrderId.value
    })

    sendResult.value = result

    if (result.success) {
      showSuccess(t('admin_debug_parcel_sent'))
      selectedOrderId.value = ''
      // Refresh parcels data
      await loadShippingParcels()
    } else {
      showError(result.message || 'Erreur lors de l\'envoi du colis')
    }
  } catch (error: any) {
    showError(error.message || 'Erreur lors de l\'envoi du colis')
  }
}

const trackParcelEnhanced = async () => {
  if (!enhancedTrackingNumber.value) return

  trackResult.value = null
  try {
    const result = await ozonDebugStore.track({
      tracking_number: enhancedTrackingNumber.value
    })

    trackResult.value = result

    if (result.success) {
      showSuccess(t('admin_debug_tracking_updated'))
      // Refresh parcels data
      await loadShippingParcels()
    } else {
      showError(result.message || 'Erreur lors du suivi du colis')
    }
  } catch (error: any) {
    showError(error.message || 'Erreur lors du suivi du colis')
  }
}

const copyToClipboard = async (text: string) => {
  try {
    await navigator.clipboard.writeText(text)
    showSuccess(t('admin_shipping_tracking_copied'))
  } catch (error) {
    showError('Erreur lors de la copie')
  }
}

// Delivery Note test methods
const testCreateDeliveryNote = async () => {
  loading.value.deliveryNote = true
  deliveryNoteResult.value = null

  try {
    const response = await fetch('/api/admin/shipping/ozon/dn/create', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${localStorage.getItem('token')}`,
      },
    })

    const data = await response.json()
    deliveryNoteResult.value = data

    if (data.success) {
      deliveryNoteRef.value = data.data.ref
      showSuccess(`Bon de livraison créé: ${data.data.ref}`)
    } else {
      showError(data.message || 'Erreur lors de la création du bon de livraison')
    }
  } catch (error: any) {
    deliveryNoteResult.value = {
      success: false,
      message: 'Erreur de connexion: ' + error.message
    }
    showError('Erreur de connexion')
  } finally {
    loading.value.deliveryNote = false
  }
}

const testAddParcelsToDeliveryNote = async () => {
  if (!deliveryNoteRef.value) return

  loading.value.deliveryNote = true
  deliveryNoteResult.value = null

  try {
    // Use some test tracking numbers
    const testTrackingNumbers = ['TEST-001', 'TEST-002', 'TEST-003']

    const response = await fetch('/api/admin/shipping/ozon/dn/add-parcels', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${localStorage.getItem('token')}`,
      },
      body: JSON.stringify({
        ref: deliveryNoteRef.value,
        codes: testTrackingNumbers
      })
    })

    const data = await response.json()
    deliveryNoteResult.value = data

    if (data.success) {
      showSuccess(`${testTrackingNumbers.length} colis ajoutés au bon de livraison`)
    } else {
      showError(data.message || 'Erreur lors de l\'ajout des colis')
    }
  } catch (error: any) {
    deliveryNoteResult.value = {
      success: false,
      message: 'Erreur de connexion: ' + error.message
    }
    showError('Erreur de connexion')
  } finally {
    loading.value.deliveryNote = false
  }
}

const testSaveDeliveryNote = async () => {
  if (!deliveryNoteRef.value) return

  loading.value.deliveryNote = true
  deliveryNoteResult.value = null

  try {
    const response = await fetch('/api/admin/shipping/ozon/dn/save', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${localStorage.getItem('token')}`,
      },
      body: JSON.stringify({
        ref: deliveryNoteRef.value
      })
    })

    const data = await response.json()
    deliveryNoteResult.value = data

    if (data.success) {
      showSuccess(t('admin_debug_delivery_note_saved'))
    } else {
      showError(data.message || 'Erreur lors de la sauvegarde')
    }
  } catch (error: any) {
    deliveryNoteResult.value = {
      success: false,
      message: 'Erreur de connexion: ' + error.message
    }
    showError('Erreur de connexion')
  } finally {
    loading.value.deliveryNote = false
  }
}

const testGetDeliveryNotePdf = async () => {
  if (!deliveryNoteRef.value) return

  loading.value.deliveryNote = true
  deliveryNoteResult.value = null

  try {
    const response = await fetch(`/api/admin/shipping/ozon/dn/pdf?ref=${deliveryNoteRef.value}`, {
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('token')}`,
      },
    })

    const data = await response.json()
    deliveryNoteResult.value = data

    if (data.success) {
      showSuccess(t('admin_debug_pdf_links_retrieved'))

      // Open PDFs if available
      const links = data.data.pdf_links
      if (links.bl_pdf) {
        window.open(links.bl_pdf, '_blank')
      }
    } else {
      showError(data.message || 'Erreur lors de la récupération des PDFs')
    }
  } catch (error: any) {
    deliveryNoteResult.value = {
      success: false,
      message: 'Erreur de connexion: ' + error.message
    }
    showError('Erreur de connexion')
  } finally {
    loading.value.deliveryNote = false
  }
}

// Load cities on component mount
onMounted(async () => {
  await shippingStore.fetchCities()
})
</script>
