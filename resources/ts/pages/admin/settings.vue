<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { $api } from '@/utils/api'
import { useSettingsStore } from '@/stores/settings'
import { useNotifications } from '@/composables/useNotifications'

const { t } = useI18n()
const settingsStore = useSettingsStore()
const { showSuccess, showError } = useNotifications()

// Page state
const loading = ref(false)
const isSaving = ref(false)
const activeTab = ref('general')

// Settings data
const localData = ref({
  app_name: '',
  app_description: '',
  app_slogan: '',
  app_keywords: '',
  company_logo: '',
  favicon: '',
  facebook_pxm_api_key: ''
})

// Computed state
const isFormLoading = computed(() => loading.value || isSaving.value)

// Preview states
const logoPreview = ref('')
const faviconPreview = ref('')

// Password visibility for Facebook API key
const showApiKey = ref(false)

// Handle file uploads
const handleLogoUpload = (event: Event) => {
  const file = (event.target as HTMLInputElement).files?.[0]
  if (file) {
    if (!validateImageFile(file, 'logo')) return

    const reader = new FileReader()
    reader.onload = (e) => {
      logoPreview.value = e.target?.result as string
      localData.value.company_logo = file.name
    }
    reader.readAsDataURL(file)
  }
}

const handleFaviconUpload = (event: Event) => {
  const file = (event.target as HTMLInputElement).files?.[0]
  if (file) {
    if (!validateImageFile(file, 'favicon')) return

    const reader = new FileReader()
    reader.onload = (e) => {
      faviconPreview.value = e.target?.result as string
      localData.value.favicon = file.name
    }
    reader.readAsDataURL(file)
  }
}

// File validation
const validateImageFile = (file: File, type: 'logo' | 'favicon'): boolean => {
  const maxSizes = {
    logo: 2 * 1024 * 1024, // 2MB
    favicon: 1 * 1024 * 1024, // 1MB
  }

  const allowedTypes = {
    logo: ['image/jpeg', 'image/png', 'image/svg+xml'],
    favicon: ['image/x-icon', 'image/png', 'image/jpeg'],
  }

  if (file.size > maxSizes[type]) {
    showError(`File too large. Maximum size for ${type} is ${maxSizes[type] / (1024 * 1024)}MB`)
    return false
  }

  if (!allowedTypes[type].includes(file.type)) {
    showError(`Invalid file type for ${type}. Allowed types: ${allowedTypes[type].join(', ')}`)
    return false
  }

  return true
}

// Save settings
const handleSave = async () => {
  isSaving.value = true
  try {
    await settingsStore.updateSettings('general', localData.value)
    showSuccess(t('settings_saved_successfully'))
  } catch (error) {
    console.error('Failed to save settings:', error)
    showError(t('settings_save_failed'))
  } finally {
    isSaving.value = false
  }
}

// Load settings
const loadSettings = async () => {
  loading.value = true
  try {
    const response = await $api('/admin/settings/general', {
      method: 'GET'
    })

    if (response.success && response.data) {
      Object.keys(localData.value).forEach(key => {
        if (response.data[key] !== undefined) {
          (localData.value as any)[key] = response.data[key]
        }
      })
    }
  } catch (error) {
    console.error('Failed to load settings:', error)
    showError(t('settings_load_failed'))
  } finally {
    loading.value = false
  }
}

// Initialize on mount
onMounted(() => {
  loadSettings()
})
</script>

<template>
  <div>
    <!-- Page Header -->
    <div class="d-flex align-center justify-space-between mb-6">
      <div>
        <h4 class="text-h4 font-weight-bold mb-1">
          {{ t('settings.title') }}
        </h4>
        <p class="text-body-1 mb-0">
          {{ t('settings.description') }}
        </p>
      </div>
    </div>

    <!-- Loading Skeleton -->
    <VRow v-if="loading">
      <VCol cols="12">
        <VCard>
          <VCardText>
            <VSkeletonLoader
              type="heading, paragraph, divider, heading, paragraph, button"
            />
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Settings Tabs -->
    <VCard v-else>
      <VTabs
        v-model="activeTab"
        color="primary"
        align-tabs="start"
      >
        <VTab value="general">
          <VIcon start icon="tabler-settings" />
          {{ t('settings.tabs.general') }}
        </VTab>

        <VTab value="business">
          <VIcon start icon="tabler-building-store" />
          {{ t('settings.tabs.business') }}
        </VTab>

        <VTab value="shipping">
          <VIcon start icon="tabler-truck" />
          {{ t('settings.tabs.shipping') }}
          <VChip
            size="x-small"
            color="warning"
            class="ml-2"
          >
            {{ t('settings.coming_soon') }}
          </VChip>
        </VTab>

        <VTab value="users">
          <VIcon start icon="tabler-users" />
          {{ t('settings.tabs.users') }}
          <VChip
            size="x-small"
            color="warning"
            class="ml-2"
          >
            {{ t('settings.coming_soon') }}
          </VChip>
        </VTab>

        <VTab value="products">
          <VIcon start icon="tabler-package" />
          {{ t('settings.tabs.products') }}
          <VChip
            size="x-small"
            color="warning"
            class="ml-2"
          >
            {{ t('settings.coming_soon') }}
          </VChip>
        </VTab>

        <VTab value="communication">
          <VIcon start icon="tabler-mail" />
          {{ t('settings.tabs.communication') }}
          <VChip
            size="x-small"
            color="warning"
            class="ml-2"
          >
            {{ t('settings.coming_soon') }}
          </VChip>
        </VTab>

        <VTab value="security">
          <VIcon start icon="tabler-shield" />
          {{ t('settings.tabs.security') }}
          <VChip
            size="x-small"
            color="warning"
            class="ml-2"
          >
            {{ t('settings.coming_soon') }}
          </VChip>
        </VTab>

        <VTab value="system">
          <VIcon start icon="tabler-server" />
          {{ t('settings.tabs.system') }}
          <VChip
            size="x-small"
            color="warning"
            class="ml-2"
          >
            {{ t('settings.coming_soon') }}
          </VChip>
        </VTab>
      </VTabs>

      <VTabsWindow v-model="activeTab">
        <!-- General Settings Tab -->
        <VTabsWindowItem value="general">
          <VCardText>
            <VForm @submit.prevent="handleSave">
              <VRow>
                <!-- App Name -->
                <VCol
                  cols="12"
                  md="6"
                >
                  <AppTextField
                    v-model="localData.app_name"
                    :label="`${t('app_name')} *`"
                    :placeholder="t('enter_app_name')"
                    required
                  />
                </VCol>

                <!-- App Description -->
                <VCol
                  cols="12"
                  md="6"
                >
                  <AppTextField
                    v-model="localData.app_description"
                    :label="t('app_description')"
                    :placeholder="t('enter_app_description')"
                  />
                </VCol>

                <!-- App Slogan -->
                <VCol
                  cols="12"
                  md="6"
                >
                  <AppTextField
                    v-model="localData.app_slogan"
                    :label="t('app_slogan')"
                    :placeholder="t('enter_app_slogan')"
                  />
                </VCol>

                <!-- App Keywords -->
                <VCol
                  cols="12"
                  md="6"
                >
                  <AppTextField
                    v-model="localData.app_keywords"
                    :label="t('app_keywords')"
                    :placeholder="t('enter_app_keywords')"
                  />
                </VCol>

                <!-- Company Logo Upload -->
                <VCol
                  cols="12"
                  md="6"
                >
                  <VLabel class="text-body-2 text-high-emphasis mb-1">
                    {{ t('company_logo') }}
                  </VLabel>
                  <VFileInput
                    accept="image/jpeg,image/png,image/svg+xml"
                    :label="t('select_logo_file')"
                    prepend-icon="tabler-upload"
                    @change="handleLogoUpload"
                  />
                  <div v-if="logoPreview" class="mt-2">
                    <VImg
                      :src="logoPreview"
                      width="100"
                      height="60"
                      class="border rounded"
                    />
                  </div>
                </VCol>

                <!-- Favicon Upload -->
                <VCol
                  cols="12"
                  md="6"
                >
                  <VLabel class="text-body-2 text-high-emphasis mb-1">
                    {{ t('favicon') }}
                  </VLabel>
                  <VFileInput
                    accept="image/x-icon,image/png,image/jpeg"
                    :label="t('select_favicon_file')"
                    prepend-icon="tabler-upload"
                    @change="handleFaviconUpload"
                  />
                  <div v-if="faviconPreview" class="mt-2">
                    <VImg
                      :src="faviconPreview"
                      width="32"
                      height="32"
                      class="border rounded"
                    />
                  </div>
                </VCol>

                <!-- Facebook Pixel API Key -->
                <VCol cols="12">
                  <AppTextField
                    v-model="localData.facebook_pxm_api_key"
                    :label="t('facebook_pixel_api_key')"
                    :placeholder="t('enter_facebook_pixel_api_key')"
                    :type="showApiKey ? 'text' : 'password'"
                    :append-inner-icon="showApiKey ? 'tabler-eye-off' : 'tabler-eye'"
                    @click:append-inner="showApiKey = !showApiKey"
                  >
                    <template #details>
                      <div class="text-caption text-medium-emphasis">
                        {{ t('facebook_pixel_api_key_description') }}
                      </div>
                    </template>
                  </AppTextField>
                </VCol>

                <!-- Action Buttons -->
                <VCol cols="12">
                  <div class="d-flex gap-3 flex-wrap">
                    <VBtn
                      color="primary"
                      prepend-icon="tabler-device-floppy"
                      :loading="isSaving"
                      :disabled="isFormLoading"
                      type="submit"
                    >
                      {{ t('save_settings') }}
                    </VBtn>

                    <VBtn
                      color="secondary"
                      variant="outlined"
                      prepend-icon="tabler-refresh"
                      :disabled="isFormLoading"
                      @click="loadSettings"
                    >
                      {{ t('reload_settings') }}
                    </VBtn>
                  </div>
                </VCol>
              </VRow>
            </VForm>
          </VCardText>
        </VTabsWindowItem>

        <!-- Business Settings Tab -->
        <VTabsWindowItem value="business">
          <VCardText>
            <VAlert
              type="info"
              variant="tonal"
              class="mb-4"
            >
              <VIcon start icon="tabler-info-circle" />
              {{ t('settings.business.description') }}
            </VAlert>
            <!-- Business settings content will be added in future versions -->
          </VCardText>
        </VTabsWindowItem>

        <!-- Coming Soon Tabs -->
        <VTabsWindowItem
          v-for="tab in ['shipping', 'users', 'products', 'communication', 'security', 'system']"
          :key="tab"
          :value="tab"
        >
          <VCardText>
            <div class="text-center py-8">
              <VIcon
                icon="tabler-clock"
                size="64"
                class="text-medium-emphasis mb-4"
              />
              <h3 class="text-h5 mb-2">
                {{ t('settings.coming_soon') }}
              </h3>
              <p class="text-body-1 text-medium-emphasis">
                {{ t('settings.coming_soon_description', { feature: t(`settings.tabs.${tab}`) }) }}
              </p>
            </div>
          </VCardText>
        </VTabsWindowItem>
      </VTabsWindow>
    </VCard>
  </div>
</template>
