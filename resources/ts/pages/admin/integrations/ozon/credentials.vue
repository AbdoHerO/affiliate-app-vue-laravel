<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useOzonSettingsStore } from '@/stores/admin/ozonSettings'
import { useQuickConfirm } from '@/composables/useConfirmAction'
import { useNotifications } from '@/composables/useNotifications'

// Store
const ozonSettingsStore = useOzonSettingsStore()

// Composables
const { confirmUpdate } = useQuickConfirm()
const { showSuccess, showError, snackbar } = useNotifications()

// Form state
const form = ref({
  customer_id: '',
  api_key: '',
  base_url: 'https://api.ozonexpress.ma',
})

const showApiKey = ref(false)
const formErrors = ref<Record<string, string[]>>({})

// Load settings on mount
onMounted(async () => {
  await ozonSettingsStore.fetchSettings()
  
  // Populate form with current settings
  form.value.customer_id = ozonSettingsStore.settings.customer_id || ''
  form.value.api_key = ozonSettingsStore.settings.api_key || ''
})

// Form submission
const saveSettings = async () => {
  try {
    console.log('Form data before save:', form.value)

    const success = await ozonSettingsStore.updateSettings(form.value)

    if (success) {
      showSuccess('Paramètres sauvegardés avec succès')
      formErrors.value = {}
    } else {
      showError(ozonSettingsStore.error || 'Erreur lors de la sauvegarde')
    }
  } catch (error) {
    console.error('Error saving settings:', error)
    showError('Erreur lors de la sauvegarde')
  }
}

// Test connection
const testConnection = async () => {
  const success = await ozonSettingsStore.testConnection()

  if (success) {
    showSuccess('Connexion réussie !')
  } else {
    showError(ozonSettingsStore.error || 'Échec de la connexion')
  }
}

// Toggle API key visibility
const toggleApiKeyVisibility = async () => {
  if (!showApiKey.value) {
    // Revealing - fetch real API key
    const realSettings = await ozonSettingsStore.fetchSettingsForEdit()
    if (realSettings) {
      form.value.api_key = realSettings.api_key || ''
    }
  } else {
    // Hiding - restore masked value
    form.value.api_key = ozonSettingsStore.settings.api_key || ''
  }
  showApiKey.value = !showApiKey.value
}

// Clear errors when user types
const clearFieldError = (field: string) => {
  if (formErrors.value[field]) {
    delete formErrors.value[field]
  }
}
</script>

<template>
  <div>
    <!-- Page Header -->
    <div class="d-flex justify-space-between align-center mb-6">
      <div>
        <h1 class="text-h4 font-weight-bold mb-1">
          Paramètres OzonExpress
        </h1>
        <p class="text-body-1 text-medium-emphasis">
          Configurez vos identifiants OzonExpress pour l'intégration des expéditions
        </p>
      </div>
    </div>

    <!-- Settings Card -->
    <VCard>
      <VCardTitle class="d-flex align-center">
        <VIcon icon="tabler-settings" class="me-3" />
        Identifiants API
      </VCardTitle>
      
      <VCardText>
        <VForm @submit.prevent="saveSettings">
          <VRow>
            <!-- Customer ID -->
            <VCol cols="12" md="6">
              <VTextField
                v-model="form.customer_id"
                label="Customer ID"
                placeholder="Votre Customer ID OzonExpress"
                variant="outlined"
                required
                :error-messages="formErrors.customer_id"
                @input="clearFieldError('customer_id')"
              />
            </VCol>

            <!-- API Key -->
            <VCol cols="12" md="6">
              <VTextField
                v-model="form.api_key"
                :label="showApiKey ? 'API Key' : 'API Key (masquée)'"
                :type="showApiKey ? 'text' : 'password'"
                placeholder="Votre clé API OzonExpress"
                variant="outlined"
                required
                :error-messages="formErrors.api_key"
                @input="clearFieldError('api_key')"
              >
                <template #append-inner>
                  <VBtn
                    icon
                    variant="text"
                    size="small"
                    @click="toggleApiKeyVisibility"
                  >
                    <VIcon :icon="showApiKey ? 'tabler-eye-off' : 'tabler-eye'" />
                  </VBtn>
                </template>
              </VTextField>
            </VCol>

            <!-- Base URL -->
            <VCol cols="12">
              <VTextField
                v-model="form.base_url"
                label="URL de base de l'API"
                placeholder="https://api.ozonexpress.ma"
                variant="outlined"
                required
                :error-messages="formErrors.base_url"
                @input="clearFieldError('base_url')"
              />
            </VCol>
          </VRow>

          <!-- Error Display -->
          <VAlert
            v-if="ozonSettingsStore.error"
            type="error"
            variant="tonal"
            class="mb-4"
            closable
            @click:close="ozonSettingsStore.clearError()"
          >
            {{ ozonSettingsStore.error }}
          </VAlert>

          <!-- Test Result Display -->
          <VAlert
            v-if="ozonSettingsStore.testResult"
            :type="ozonSettingsStore.testResult.success ? 'success' : 'error'"
            variant="tonal"
            class="mb-4"
            closable
            @click:close="ozonSettingsStore.clearTestResult()"
          >
            {{ ozonSettingsStore.testResult.message }}
          </VAlert>

          <!-- Action Buttons -->
          <div class="d-flex gap-3 mt-5">
            <VBtn
              type="submit"
              color="primary"
              :loading="ozonSettingsStore.loading"
              :disabled="!form.customer_id || !form.api_key"
            >
              <VIcon icon="tabler-device-floppy" start />
              Sauvegarder
            </VBtn>

            <!-- <VBtn
              variant="outlined"
              color="secondary"
              :loading="ozonSettingsStore.loading"
              :disabled="!ozonSettingsStore.hasCredentials"
              @click="testConnection"
            >
              <VIcon icon="tabler-plug-connected" start />
              Tester la connexion
            </VBtn> -->
          </div>
        </VForm>
      </VCardText>
    </VCard>

    <!-- Configuration Status -->
    <VCard class="mt-6">
      <VCardTitle class="d-flex align-center">
        <VIcon icon="tabler-info-circle" class="me-3" />
        Statut de la configuration
      </VCardTitle>
      
      <VCardText>
        <div class="d-flex align-center gap-3">
          <VChip
            :color="ozonSettingsStore.isConfigured ? 'success' : 'warning'"
            variant="tonal"
          >
            <VIcon
              :icon="ozonSettingsStore.isConfigured ? 'tabler-check' : 'tabler-alert-triangle'"
              start
            />
            {{ ozonSettingsStore.isConfigured ? 'Configuré' : 'Non configuré' }}
          </VChip>
          
          <span class="text-body-2 text-medium-emphasis">
            {{ ozonSettingsStore.isConfigured 
              ? 'Les identifiants OzonExpress sont configurés et prêts à être utilisés.' 
              : 'Veuillez configurer vos identifiants OzonExpress pour activer l\'intégration.' 
            }}
          </span>
        </div>
      </VCardText>
    </VCard>

    <!-- Help Card -->
    <VCard class="mt-6">
      <VCardTitle class="d-flex align-center">
        <VIcon icon="tabler-help" class="me-3" />
        Aide
      </VCardTitle>
      
      <VCardText>
        <div class="text-body-2">
          <p class="mb-3">
            <strong>Customer ID :</strong> Votre identifiant client fourni par OzonExpress.
          </p>
          <p class="mb-3">
            <strong>API Key :</strong> Votre clé API secrète fournie par OzonExpress. Cette clé est stockée de manière sécurisée et chiffrée.
          </p>
          <p class="mb-0">
            <strong>Note :</strong> Ces informations sont nécessaires pour créer et suivre les colis via l'API OzonExpress.
          </p>
        </div>
      </VCardText>
    </VCard>

    <!-- Snackbar for notifications -->
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

<style scoped>
.gap-3 {
  gap: 12px;
}
</style>
