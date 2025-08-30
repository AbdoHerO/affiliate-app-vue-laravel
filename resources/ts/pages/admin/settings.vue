<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
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
  facebook_pixel_id: ''
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

    // Update metadata immediately after saving
    updateAppMetadata()

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

      // Load existing images
      if (localData.value.company_logo) {
        logoPreview.value = getImageUrl(localData.value.company_logo)
      }
      if (localData.value.favicon) {
        faviconPreview.value = getImageUrl(localData.value.favicon)
      }

      // Update app metadata immediately
      updateAppMetadata()
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
    <VCard v-else class="settings-card">
      <VTabs
        v-model="activeTab"
        color="primary"
        align-tabs="start"
        class="settings-tabs"
        bg-color="grey-lighten-5"
        slider-color="primary"
        show-arrows
      >
        <VTab
          value="general"
          class="settings-tab"
          prepend-icon="tabler-settings"
        >
          <div class="d-flex align-center">
            <VIcon start icon="tabler-settings" size="20" />
            <span class="tab-text">{{ t('settings.tabs.general') }}</span>
          </div>
        </VTab>

        <VTab
          value="business"
          class="settings-tab"
          prepend-icon="tabler-building-store"
        >
          <div class="d-flex align-center">
            <VIcon start icon="tabler-building-store" size="20" />
            <span class="tab-text">{{ t('settings.tabs.business') }}</span>
          </div>
        </VTab>

        <VTab
          value="shipping"
          class="settings-tab"
          prepend-icon="tabler-truck"
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
          class="settings-tab"
          prepend-icon="tabler-users"
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
          class="settings-tab"
          prepend-icon="tabler-package"
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
          class="settings-tab"
          prepend-icon="tabler-mail"
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
          class="settings-tab"
          prepend-icon="tabler-shield"
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
          class="settings-tab"
          prepend-icon="tabler-server"
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

      <VTabsWindow v-model="activeTab">
        <!-- General Settings Tab -->
        <VTabsWindowItem value="general">
          <VCardText class="pa-6">
            <div class="mb-6">
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
                  <VCard variant="outlined" class="pa-4">
                    <VCardTitle class="text-body-1 pa-0 mb-3">
                      <VIcon start icon="tabler-photo" />
                      {{ t('company_logo') }}
                    </VCardTitle>

                    <!-- Current Logo Display -->
                    <div v-if="logoPreview || localData.company_logo" class="mb-4">
                      <VLabel class="text-caption text-medium-emphasis mb-2">
                        {{ t('settings.current_logo') }}
                      </VLabel>
                      <div class="d-flex align-center gap-3">
                        <VAvatar
                          size="80"
                          rounded="lg"
                          variant="outlined"
                        >
                          <VImg
                            :src="logoPreview || getImageUrl(localData.company_logo)"
                            :alt="t('company_logo')"
                            cover
                          />
                        </VAvatar>
                        <div>
                          <p class="text-body-2 mb-1">{{ localData.company_logo || t('settings.no_logo') }}</p>
                          <VBtn
                            v-if="logoPreview || localData.company_logo"
                            size="small"
                            color="error"
                            variant="text"
                            @click="clearLogo"
                          >
                            <VIcon start icon="tabler-trash" />
                            {{ t('settings.remove') }}
                          </VBtn>
                        </div>
                      </div>
                    </div>

                    <!-- Upload New Logo -->
                    <VFileInput
                      accept="image/jpeg,image/png,image/svg+xml"
                      :label="t('select_logo_file')"
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
                      {{ t('settings.logo_requirements') }}
                    </VAlert>
                  </VCard>
                </VCol>

                <!-- Favicon Upload -->
                <VCol
                  cols="12"
                  md="6"
                >
                  <VCard variant="outlined" class="pa-4">
                    <VCardTitle class="text-body-1 pa-0 mb-3">
                      <VIcon start icon="tabler-world" />
                      {{ t('favicon') }}
                    </VCardTitle>

                    <!-- Current Favicon Display -->
                    <div v-if="faviconPreview || localData.favicon" class="mb-4">
                      <VLabel class="text-caption text-medium-emphasis mb-2">
                        {{ t('settings.current_favicon') }}
                      </VLabel>
                      <div class="d-flex align-center gap-3">
                        <VAvatar
                          size="40"
                          rounded="sm"
                          variant="outlined"
                        >
                          <VImg
                            :src="faviconPreview || getImageUrl(localData.favicon)"
                            :alt="t('favicon')"
                            cover
                          />
                        </VAvatar>
                        <div>
                          <p class="text-body-2 mb-1">{{ localData.favicon || t('settings.no_favicon') }}</p>
                          <VBtn
                            v-if="faviconPreview || localData.favicon"
                            size="small"
                            color="error"
                            variant="text"
                            @click="clearFavicon"
                          >
                            <VIcon start icon="tabler-trash" />
                            {{ t('settings.remove') }}
                          </VBtn>
                        </div>
                      </div>
                    </div>

                    <!-- Upload New Favicon -->
                    <VFileInput
                      accept="image/x-icon,image/png,image/jpeg"
                      :label="t('select_favicon_file')"
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
                      {{ t('settings.favicon_requirements') }}
                    </VAlert>
                  </VCard>
                </VCol>

                <!-- Facebook Pixel ID -->
                <VCol cols="12">
                  <VCard variant="outlined" class="pa-4">
                    <VCardTitle class="text-body-1 pa-0 mb-3">
                      <VIcon start icon="tabler-brand-facebook" />
                      {{ t('settings.facebook_pixel_id') }}
                    </VCardTitle>

                    <AppTextField
                      v-model="localData.facebook_pixel_id"
                      :label="t('settings.facebook_pixel_id')"
                      :placeholder="t('settings.enter_facebook_pixel_id')"
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
                      {{ t('settings.facebook_pixel_description') }}
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
