<script setup lang="ts">
definePage({
  meta: {
    requiresAuth: true,
    requiresRole: 'admin',
  },
})
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useOzonSettingsStore } from '@/stores/admin/ozonSettings'
import { useQuickConfirm } from '@/composables/useConfirmAction'
import { useNotifications } from '@/composables/useNotifications'

// Composables
const { t } = useI18n()

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
      showSuccess(t('admin_ozon_settings_saved_success'))
      formErrors.value = {}
    } else {
      showError(ozonSettingsStore.error || t('admin_ozon_settings_save_error'))
    }
  } catch (error) {
    console.error('Error saving settings:', error)
    showError(t('admin_ozon_settings_save_error'))
  }
}

// Test connection
const testConnection = async () => {
  const success = await ozonSettingsStore.testConnection()

  if (success) {
    showSuccess(t('admin_ozon_connection_success'))
  } else {
    showError(ozonSettingsStore.error || t('admin_ozon_connection_failed'))
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
          {{ t('ozonexpress_settings') }}
        </h1>
        <p class="text-body-1 text-medium-emphasis">
          {{ t('ozonexpress_settings_desc') }}
        </p>
      </div>
    </div>

    <!-- Settings Card -->
    <VCard>
      <VCardTitle class="d-flex align-center">
        <VIcon icon="tabler-settings" class="me-3" />
        {{ t('api_credentials') }}
      </VCardTitle>
      
      <VCardText>
        <VForm @submit.prevent="saveSettings">
          <VRow>
            <!-- Customer ID -->
            <VCol cols="12" md="6">
              <VTextField
                v-model="form.customer_id"
                :label="t('customer_id')"
                :placeholder="t('customer_id_placeholder')"
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
                :label="showApiKey ? t('api_key') : t('api_key_masked')"
                :type="showApiKey ? 'text' : 'password'"
                :placeholder="t('api_key_placeholder')"
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
                :label="t('api_base_url')"
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
              {{ t('admin_ozon_save_button') }}
            </VBtn>

            <!-- <VBtn
              variant="outlined"
              color="secondary"
              :loading="ozonSettingsStore.loading"
              :disabled="!ozonSettingsStore.hasCredentials"
              @click="testConnection"
            >
              <VIcon icon="tabler-plug-connected" start />
              {{ t('admin_ozon_test_connection') }}
            </VBtn> -->
          </div>
        </VForm>
      </VCardText>
    </VCard>

    <!-- Configuration Status -->
    <VCard class="mt-6">
      <VCardTitle class="d-flex align-center">
        <VIcon icon="tabler-info-circle" class="me-3" />
        {{ t('admin_ozon_config_status_title') }}
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
            {{ ozonSettingsStore.isConfigured ? t('admin_ozon_configured') : t('admin_ozon_not_configured') }}
          </VChip>
          
          <span class="text-body-2 text-medium-emphasis">
            {{ ozonSettingsStore.isConfigured
              ? t('admin_ozon_config_ready_desc')
              : t('admin_ozon_config_needed_desc')
            }}
          </span>
        </div>
      </VCardText>
    </VCard>

    <!-- Help Card -->
    <VCard class="mt-6">
      <VCardTitle class="d-flex align-center">
        <VIcon icon="tabler-help" class="me-3" />
        {{ t('admin_ozon_help_title') }}
      </VCardTitle>
      
      <VCardText>
        <div class="text-body-2">
          <p class="mb-3">
            <strong>Customer ID :</strong> {{ t('admin_ozon_help_customer_id') }}
          </p>
          <p class="mb-3">
            <strong>API Key :</strong> {{ t('admin_ozon_help_api_key') }}
          </p>
          <p class="mb-0">
            <strong>Note :</strong> {{ t('admin_ozon_help_note') }}
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
