<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { $api } from '@/utils/api'
import { useSettingsStore } from '@/stores/settings'
import { useNotifications } from '@/composables/useNotifications'
import ThemeConfigService from '@/services/themeConfigService'
import AppInitService from '@/services/appInitService'

const { t, locale } = useI18n()
const settingsStore = useSettingsStore()
const { showSuccess, showError } = useNotifications()

// Ensure French locale is set and debug i18n
onMounted(() => {
  console.log('Current locale:', locale.value)
  console.log('Available locales:', Object.keys(t('settings') || {}))
  console.log('Settings title:', t('settings.title'))

  // Force French locale
  if (locale.value !== 'fr') {
    locale.value = 'fr'
    console.log('Locale forced to French')
  }
})

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

// Remove unused showApiKey variable

// Image helper functions
const getImageUrl = (filename: string | null) => {
  if (!filename) return ''
  // Check if filename is already a full URL
  if (filename.startsWith('http') || filename.startsWith('/storage/')) {
    return filename
  }
  // Return the full URL for the uploaded image
  return `/storage/settings/${filename}`
}

const clearLogo = () => {
  logoPreview.value = ''
  localData.value.company_logo = ''
}

const clearFavicon = () => {
  faviconPreview.value = ''
  localData.value.favicon = ''
}

// Handle file uploads
const handleLogoUpload = async (event: Event) => {
  const file = (event.target as HTMLInputElement).files?.[0]
  if (file) {
    if (!validateImageFile(file, 'logo')) return

    try {
      const formData = new FormData()
      formData.append('file', file)
      formData.append('type', 'logo')

      const response = await $api('/admin/settings/upload-file', {
        method: 'POST',
        body: formData
      })

      if (response.success) {
        localData.value.company_logo = response.data.filename
        logoPreview.value = response.data.url
        showSuccess(t('settings.messages.upload_success'))
      } else {
        showError(response.message || t('settings.messages.upload_failed'))
      }
    } catch (error) {
      console.error('Logo upload failed:', error)
      showError(t('settings.messages.upload_failed'))
    }
  }
}

const handleFaviconUpload = async (event: Event) => {
  const file = (event.target as HTMLInputElement).files?.[0]
  if (file) {
    if (!validateImageFile(file, 'favicon')) return

    try {
      const formData = new FormData()
      formData.append('file', file)
      formData.append('type', 'favicon')

      const response = await $api('/admin/settings/upload-file', {
        method: 'POST',
        body: formData
      })

      if (response.success) {
        localData.value.favicon = response.data.filename
        faviconPreview.value = response.data.url
        showSuccess(t('settings.messages.upload_success'))
      } else {
        showError(response.message || t('settings.messages.upload_failed'))
      }
    } catch (error) {
      console.error('Favicon upload failed:', error)
      showError(t('settings.messages.upload_failed'))
    }
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
    showError(t('settings.file_too_large_with_type', { type, maxSize: maxSizes[type] / (1024 * 1024) }))
    return false
  }

  if (!allowedTypes[type].includes(file.type)) {
    showError(t('settings.invalid_file_type_with_types', { type, allowedTypes: allowedTypes[type].join(', ') }))
    return false
  }

  return true
}

// Save settings
const handleSave = async () => {
  isSaving.value = true
  try {
    await settingsStore.updateSettings('general', localData.value)

    // Update metadata immediately after saving
    updateAppMetadata()

    // Update store to trigger reactive updates across the app
    await settingsStore.fetchSettings()

    // Update themeConfig with new settings
    await updateThemeConfig()

    showSuccess(t('settings.messages.saved_successfully'))

    // Emit settings update event for other components
    window.dispatchEvent(new CustomEvent('settings:updated', {
      detail: localData.value
    }))

    // Reinitialize app with new settings
    await AppInitService.reinitialize()

    // Reload page after short delay to ensure all changes are applied
    setTimeout(() => {
      window.location.reload()
    }, 1500)

  } catch (error) {
    console.error('Failed to save settings:', error)
    showError(t('settings.messages.save_failed'))
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

      // Load existing images
      if (localData.value.company_logo) {
        logoPreview.value = getImageUrl(localData.value.company_logo)
      }
      if (localData.value.favicon) {
        faviconPreview.value = getImageUrl(localData.value.favicon)
      }

      // Update app metadata immediately
      updateAppMetadata()

      // Update theme config with loaded settings
      updateThemeConfig()
    }
  } catch (error) {
    console.error('Failed to load settings:', error)
    showError(t('settings_load_failed'))
  } finally {
    loading.value = false
  }
}

// Update app metadata in real-time
const updateAppMetadata = () => {
  // Update document title
  if (localData.value.app_name) {
    document.title = localData.value.app_name
  }

  // Update meta description
  const metaDescription = document.querySelector('meta[name="description"]')
  if (metaDescription && localData.value.app_description) {
    metaDescription.setAttribute('content', localData.value.app_description)
  }

  // Update meta keywords
  let metaKeywords = document.querySelector('meta[name="keywords"]')
  if (localData.value.app_keywords) {
    if (!metaKeywords) {
      metaKeywords = document.createElement('meta')
      metaKeywords.setAttribute('name', 'keywords')
      document.head.appendChild(metaKeywords)
    }
    metaKeywords.setAttribute('content', localData.value.app_keywords)
  }

  // Update favicon
  if (localData.value.favicon) {
    let faviconLink = document.querySelector('link[rel="icon"]') as HTMLLinkElement
    if (!faviconLink) {
      faviconLink = document.createElement('link')
      faviconLink.rel = 'icon'
      document.head.appendChild(faviconLink)
    }
    faviconLink.href = getImageUrl(localData.value.favicon)
  }
}

// Watch for changes and update metadata in real-time
watch(() => localData.value.app_name, (newValue) => {
  if (newValue) {
    document.title = newValue
  }
}, { immediate: true })

watch(() => localData.value.app_description, (newValue) => {
  const metaDescription = document.querySelector('meta[name="description"]')
  if (metaDescription && newValue) {
    metaDescription.setAttribute('content', newValue)
  }
}, { immediate: true })

watch(() => localData.value.app_keywords, (newValue) => {
  let metaKeywords = document.querySelector('meta[name="keywords"]')
  if (newValue) {
    if (!metaKeywords) {
      metaKeywords = document.createElement('meta')
      metaKeywords.setAttribute('name', 'keywords')
      document.head.appendChild(metaKeywords)
    }
    metaKeywords.setAttribute('content', newValue)
  }
}, { immediate: true })

watch(() => localData.value.favicon, (newValue) => {
  if (newValue) {
    let faviconLink = document.querySelector('link[rel="icon"]') as HTMLLinkElement
    if (!faviconLink) {
      faviconLink = document.createElement('link')
      faviconLink.rel = 'icon'
      document.head.appendChild(faviconLink)
    }
    faviconLink.href = getImageUrl(newValue)
  }
}, { immediate: true })

// Update theme config with new settings
const updateThemeConfig = async () => {
  try {
    // Use the ThemeConfigService to update theme configuration
    ThemeConfigService.updateFromSettings({
      app_name: localData.value.app_name,
      company_logo: localData.value.company_logo
    })
  } catch (error) {
    console.error('Failed to update theme config:', error)
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

    <!-- Enhanced Settings Tabs -->
    <VCard v-else class="settings-card mb-6 h-50">
      <VTabs
        v-model="activeTab"
        color="primary"
        align-tabs="start"
        class="settings-tabs h-50"
        bg-color="grey-lighten-5"
        slider-color="primary"
        show-arrows
      >
        <VTab
          value="general"
          class="settings-tab"
        >
          <div class="d-flex align-center">
            <VIcon start icon="tabler-settings" size="20" />
            <span class="tab-text">{{ t('settings.tabs.general') }}</span>
          </div>
        </VTab>

        <VTab
          value="business"
          class="settings-tab d-none"
        >
          <div class="d-flex align-center">
            <VIcon start icon="tabler-building-store" size="20" />
            <span class="tab-text">{{ t('settings.tabs.business') }}</span>
          </div>
        </VTab>

        <VTab
          value="shipping"
          class="settings-tab d-none"
        >
          <div class="d-flex align-center">
            <VIcon start icon="tabler-truck" size="20" />
            <span class="tab-text">{{ t('settings.tabs.shipping') }}</span>
            <VChip
              size="x-small"
              color="warning"
              variant="tonal"
              class="ml-2"
            >
              {{ t('settings.coming_soon') }}
            </VChip>
          </div>
        </VTab>

        <VTab
          value="users"
          class="settings-tab d-none"
        >
          <div class="d-flex align-center">
            <VIcon start icon="tabler-users" size="20" />
            <span class="tab-text">{{ t('settings.tabs.users') }}</span>
            <VChip
              size="x-small"
              color="warning"
              variant="tonal"
              class="ml-2"
            >
              {{ t('settings.coming_soon') }}
            </VChip>
          </div>
        </VTab>

        <VTab
          value="products"
          class="settings-tab d-none"
        >
          <div class="d-flex align-center">
            <VIcon start icon="tabler-package" size="20" />
            <span class="tab-text">{{ t('settings.tabs.products') }}</span>
            <VChip
              size="x-small"
              color="warning"
              variant="tonal"
              class="ml-2"
            >
              {{ t('settings.coming_soon') }}
            </VChip>
          </div>
        </VTab>

        <VTab
          value="communication"
          class="settings-tab d-none"
        >
          <div class="d-flex align-center">
            <VIcon start icon="tabler-mail" size="20" />
            <span class="tab-text">{{ t('settings.tabs.communication') }}</span>
            <VChip
              size="x-small"
              color="warning"
              variant="tonal"
              class="ml-2"
            >
              {{ t('settings.coming_soon') }}
            </VChip>
          </div>
        </VTab>

        <VTab
          value="security"
          class="settings-tab d-none"
        >
          <div class="d-flex align-center">
            <VIcon start icon="tabler-shield" size="20" />
            <span class="tab-text">{{ t('settings.tabs.security') }}</span>
            <VChip
              size="x-small"
              color="warning"
              variant="tonal"
              class="ml-2"
            >
              {{ t('settings.coming_soon') }}
            </VChip>
          </div>
        </VTab>

        <VTab
          value="system"
          class="settings-tab d-none"
        >
          <div class="d-flex align-center">
            <VIcon start icon="tabler-server" size="20" />
            <span class="tab-text">{{ t('settings.tabs.system') }}</span>
            <VChip
              size="x-small"
              color="warning"
              variant="tonal"
              class="ml-2"
            >
              {{ t('settings.coming_soon') }}
            </VChip>
          </div>
        </VTab>
      </VTabs>
    </VCard>

    <!-- Settings Content -->
    <VTabsWindow v-model="activeTab">
      <!-- General Settings Tab -->
      <VTabsWindowItem value="general">
        <VCard>
          <VCardText class="pa-6">
            <div class="mb-8">
              <h3 class="text-h5 mb-2">{{ t('settings.general.title') }}</h3>
              <p class="text-body-2 text-medium-emphasis">{{ t('settings.general.description') }}</p>
            </div>

            <VForm @submit.prevent="handleSave">
              <VRow class="gy-4">
                <!-- App Name -->
                <VCol
                  cols="12"
                  md="6"
                >
                  <AppTextField
                    v-model="localData.app_name"
                    :label="`${t('settings.general.app_name')} *`"
                    :placeholder="t('settings.general.app_name_placeholder')"
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
                    :label="t('settings.general.app_description')"
                    :placeholder="t('settings.general.app_description_placeholder')"
                  />
                </VCol>

                <!-- App Slogan -->
                <VCol
                  cols="12"
                  md="6"
                >
                  <AppTextField
                    v-model="localData.app_slogan"
                    :label="t('settings.general.app_slogan')"
                    :placeholder="t('settings.general.app_slogan_placeholder')"
                  />
                </VCol>

                <!-- App Keywords -->
                <VCol
                  cols="12"
                  md="6"
                >
                  <AppTextField
                    v-model="localData.app_keywords"
                    :label="t('settings.general.app_keywords')"
                    :placeholder="t('settings.general.app_keywords_placeholder')"
                  />
                  <VLabel class="text-caption text-medium-emphasis mt-1">
                    {{ t('settings.general.keywords_help') }}
                  </VLabel>
                </VCol>

                <!-- Company Logo Upload -->
                <VCol
                  cols="12"
                  md="6"
                >
                  <VCard variant="outlined" class="pa-4 mb-4">
                    <VCardTitle class="text-body-1 pa-0 mb-3">
                      <VIcon start icon="tabler-photo" />
                      {{ t('settings.general.company_logo') }}
                    </VCardTitle>

                    <!-- Current Logo Display -->
                    <div v-if="logoPreview || localData.company_logo" class="mb-4">
                      <VLabel class="text-caption text-medium-emphasis mb-2">
                        {{ t('settings.general.preview') }}
                      </VLabel>
                      <div class="d-flex align-center gap-3">
                        <VAvatar
                          size="80"
                          rounded="lg"
                          variant="outlined"
                        >
                          <VImg
                            :src="logoPreview || getImageUrl(localData.company_logo)"
                            :alt="t('settings.general.company_logo')"
                            cover
                          />
                        </VAvatar>
                        <div>
                          <p class="text-body-2 mb-1">{{ localData.company_logo || t('settings.general.file_selected') }}</p>
                          <VBtn
                            v-if="logoPreview || localData.company_logo"
                            size="small"
                            color="error"
                            variant="text"
                            @click="clearLogo"
                          >
                            <VIcon start icon="tabler-trash" />
                            {{ t('settings.general.remove') }}
                          </VBtn>
                        </div>
                      </div>
                    </div>

                    <!-- Upload New Logo -->
                    <VFileInput
                      accept="image/jpeg,image/png,image/svg+xml"
                      :label="t('settings.general.logo_upload')"
                      prepend-icon="tabler-upload"
                      variant="outlined"
                      density="compact"
                      @change="handleLogoUpload"
                    />
                    <VAlert
                      type="info"
                      variant="tonal"
                      density="compact"
                      class="mt-2"
                    >
                      {{ t('settings.general.logo_requirements') }}
                    </VAlert>
                  </VCard>
                </VCol>

                <!-- Favicon Upload -->
                <VCol
                  cols="12"
                  md="6"
                >
                  <VCard variant="outlined" class="pa-4 mb-4">
                    <VCardTitle class="text-body-1 pa-0 mb-3">
                      <VIcon start icon="tabler-world" />
                      {{ t('settings.general.favicon') }}
                    </VCardTitle>

                    <!-- Current Favicon Display -->
                    <div v-if="faviconPreview || localData.favicon" class="mb-4">
                      <VLabel class="text-caption text-medium-emphasis mb-2">
                        {{ t('settings.general.preview') }}
                      </VLabel>
                      <div class="d-flex align-center gap-3">
                        <VAvatar
                          size="40"
                          rounded="sm"
                          variant="outlined"
                        >
                          <VImg
                            :src="faviconPreview || getImageUrl(localData.favicon)"
                            :alt="t('settings.general.favicon')"
                            cover
                          />
                        </VAvatar>
                        <div>
                          <p class="text-body-2 mb-1">{{ localData.favicon || t('settings.general.file_selected') }}</p>
                          <VBtn
                            v-if="faviconPreview || localData.favicon"
                            size="small"
                            color="error"
                            variant="text"
                            @click="clearFavicon"
                          >
                            <VIcon start icon="tabler-trash" />
                            {{ t('settings.general.remove') }}
                          </VBtn>
                        </div>
                      </div>
                    </div>

                    <!-- Upload New Favicon -->
                    <VFileInput
                      accept="image/x-icon,image/png,image/jpeg"
                      :label="t('settings.general.favicon_upload')"
                      prepend-icon="tabler-upload"
                      variant="outlined"
                      density="compact"
                      @change="handleFaviconUpload"
                    />
                    <VAlert
                      type="info"
                      variant="tonal"
                      density="compact"
                      class="mt-2"
                    >
                      {{ t('settings.general.favicon_requirements') }}
                    </VAlert>
                  </VCard>
                </VCol>

                <!-- Facebook Pixel ID -->
                <VCol cols="12">
                  <VCard variant="outlined" class="pa-4 mb-6">
                    <VCardTitle class="text-body-1 pa-0 mb-3">
                      <VIcon start icon="tabler-brand-facebook" />
                      {{ t('settings.general.facebook_pixel') }}
                    </VCardTitle>

                    <AppTextField
                      v-model="localData.facebook_pxm_api_key"
                      :label="t('settings.general.facebook_pixel_id')"
                      :placeholder="t('settings.general.facebook_pixel_placeholder')"
                      variant="outlined"
                      density="compact"
                      prepend-inner-icon="tabler-hash"
                    />

                    <VAlert
                      type="info"
                      variant="tonal"
                      density="compact"
                      class="mt-3"
                    >
                      <VIcon start icon="tabler-info-circle" />
                      {{ t('settings.general.facebook_pixel_help') }}
                    </VAlert>
                  </VCard>
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
                      {{ t('settings.actions.save') }}
                    </VBtn>

                    <VBtn
                      color="secondary"
                      variant="outlined"
                      prepend-icon="tabler-refresh"
                      :disabled="isFormLoading"
                      @click="loadSettings"
                    >
                      {{ t('settings.actions.reload') }}
                    </VBtn>
                  </div>
                </VCol>
              </VRow>
            </VForm>
          </VCardText>
          </VCard>
        </VTabsWindowItem>

        <!-- Business Settings Tab -->
        <VTabsWindowItem value="business">
          <VCard>
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
          </VCard>
        </VTabsWindowItem>

        <!-- Coming Soon Tabs -->
        <VTabsWindowItem
          v-for="tab in ['shipping', 'users', 'products', 'communication', 'security', 'system']"
          :key="tab"
          :value="tab"
        >
          <VCard>
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
          </VCard>
        </VTabsWindowItem>
      </VTabsWindow>
  </div>
</template>

<style scoped>
.settings-card {
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
  border-radius: 12px;
  overflow: hidden;
}

.settings-tabs {
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
}

.settings-tab {
  min-height: 64px;
  padding: 12px 20px;
  font-weight: 500;
  transition: all 0.3s ease;
  border-radius: 8px 8px 0 0;
}

.settings-tab:hover {
  background-color: rgba(var(--v-theme-primary), 0.08);
  transform: translateY(-1px);
}

.settings-tab.v-tab--selected {
  background-color: rgba(var(--v-theme-primary), 0.12);
  color: rgb(var(--v-theme-primary));
  font-weight: 600;
}

.tab-text {
  font-size: 0.875rem;
  font-weight: inherit;
  margin-left: 8px;
}

.v-card {
  transition: all 0.3s ease;
}

.v-card:hover {
  box-shadow: 0 8px 25px -5px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
}

.v-file-input {
  transition: all 0.3s ease;
}

.v-file-input:hover {
  transform: translateY(-1px);
}

.v-avatar {
  transition: all 0.3s ease;
}

.v-avatar:hover {
  transform: scale(1.05);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.v-alert {
  border-radius: 8px;
  font-size: 0.8rem;
}

.v-btn {
  transition: all 0.3s ease;
  border-radius: 8px;
}

.v-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.v-text-field {
  transition: all 0.3s ease;
}

.v-text-field:hover {
  transform: translateY(-1px);
}

/* Enhanced form styling */
.v-card-title {
  font-weight: 600;
  color: rgb(var(--v-theme-on-surface));
}

.v-label {
  font-weight: 500;
  color: rgb(var(--v-theme-on-surface-variant));
}

/* Coming soon section styling */
.text-center {
  padding: 3rem 2rem;
}

.text-center .v-icon {
  opacity: 0.6;
  margin-bottom: 1rem;
}

.text-center h3 {
  color: rgb(var(--v-theme-on-surface));
  margin-bottom: 0.5rem;
}

.text-center p {
  color: rgb(var(--v-theme-on-surface-variant));
  max-width: 400px;
  margin: 0 auto;
}

/* Responsive design */
@media (max-width: 768px) {
  .settings-tabs {
    overflow-x: auto;
  }

  .settings-tab {
    min-width: 120px;
    padding: 8px 16px;
  }

  .tab-text {
    font-size: 0.8rem;
  }
}
</style>
