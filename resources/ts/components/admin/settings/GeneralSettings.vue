<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useSettingsStore } from '@/stores/settings'
import { $api } from '@/utils/api'

const { t } = useI18n()
const settingsStore = useSettingsStore()

// Props
interface Props {
  data: Record<string, any>
  loading: boolean
}

const props = defineProps<Props>()

// Emits
const emit = defineEmits<{
  change: [key: string, value: any]
  save: [data: Record<string, any>]
}>()

// Local state
const localData = ref({
  app_name: '',
  app_description: '',
  app_slogan: '',
  app_keywords: '',
  company_name: '',
  company_email: '',
  company_phone: '',
  company_address: '',
  company_website: '',
  company_social_facebook: '',
  company_social_instagram: '',
  company_social_twitter: '',

  // Branding & Appearance
  app_logo: '',
  app_favicon: '',
  primary_color: '#6366F1',
  secondary_color: '#8B5CF6',
  login_background_image: '',
  signup_background_image: '',
  app_theme: 'light',

  // Localization
  default_language: 'fr',
  timezone: 'Africa/Casablanca',
  currency: 'MAD',
  currency_symbol: 'MAD',
  date_format: 'DD/MM/YYYY',
  time_format: '24',
  number_format: 'european',

  // System Settings
  maintenance_mode: false,
  registration_enabled: true,
  email_verification_required: true,
  kyc_verification_required: true,
  max_file_upload_size: 10,
  allowed_file_types: 'jpg,jpeg,png,pdf,doc,docx',
  session_timeout: 120,
  password_min_length: 8,
  password_require_special: true,
  app_version: '1.0.0'
})

// Loading states
const isLoading = ref(false)
const isSaving = ref(false)
const isInitialized = ref(false)

// Available options
const languages = [
  { title: 'Français', value: 'fr', flag: '🇫🇷' },
  { title: 'العربية', value: 'ar', flag: '🇲🇦' },
  { title: 'English', value: 'en', flag: '🇺🇸' }
]

const timezones = [
  { title: 'Casablanca (GMT+1)', value: 'Africa/Casablanca' },
  { title: 'Paris (GMT+1)', value: 'Europe/Paris' },
  { title: 'London (GMT+0)', value: 'Europe/London' },
  { title: 'New York (GMT-5)', value: 'America/New_York' }
]

const dateFormats = [
  { title: 'DD/MM/YYYY', value: 'DD/MM/YYYY' },
  { title: 'MM/DD/YYYY', value: 'MM/DD/YYYY' },
  { title: 'YYYY-MM-DD', value: 'YYYY-MM-DD' },
  { title: 'DD-MM-YYYY', value: 'DD-MM-YYYY' }
]

const timeFormats = [
  { title: '24 heures (14:30)', value: '24' },
  { title: '12 heures (2:30 PM)', value: '12' }
]

const numberFormats = [
  { title: 'Européen (1 234,56)', value: 'european' },
  { title: 'Américain (1,234.56)', value: 'american' },
  { title: 'Arabe (١٢٣٤,٥٦)', value: 'arabic' }
]

const themeOptions = [
  { title: 'Clair', value: 'light' },
  { title: 'Sombre', value: 'dark' },
  { title: 'Auto', value: 'auto' }
]

const colorPresets = [
  { title: 'Indigo', value: '#6366F1', color: '#6366F1' },
  { title: 'Violet', value: '#8B5CF6', color: '#8B5CF6' },
  { title: 'Bleu', value: '#3B82F6', color: '#3B82F6' },
  { title: 'Vert', value: '#10B981', color: '#10B981' },
  { title: 'Orange', value: '#F59E0B', color: '#F59E0B' },
  { title: 'Rouge', value: '#EF4444', color: '#EF4444' }
]

// Watch for props.data changes and update localData
watch(() => props.data, (newData) => {
  if (newData) {
    Object.assign(localData.value, newData)
  }
}, { deep: true, immediate: true })

// Watch for changes and emit
watch(localData, (newData) => {
  Object.keys(newData).forEach(key => {
    if (newData[key] !== props.data[key]) {
      emit('change', key, newData[key])
    }
  })
}, { deep: true })

/**
 * Initialize settings data
 */
const initializeSettings = async () => {
  if (isInitialized.value) return

  isLoading.value = true
  try {
    // Fetch settings from API
    const response = await $api('/admin/settings/general', {
      method: 'GET'
    })

    if (response.success && response.data) {
      // Populate form with fetched data
      Object.keys(localData.value).forEach(key => {
        if (response.data[key] !== undefined) {
          localData.value[key] = response.data[key]
        }
      })
    }

    isInitialized.value = true
  } catch (error) {
    console.error('Failed to load settings:', error)
  } finally {
    isLoading.value = false
  }
}

/**
 * Handle save
 */
const handleSave = async () => {
  isSaving.value = true
  try {
    await settingsStore.updateSettings('general', localData.value)
    emit('save', localData.value)
  } catch (error) {
    console.error('Failed to save settings:', error)
    throw error
  } finally {
    isSaving.value = false
  }
}

// File upload handlers with preview
const logoPreview = ref('')
const faviconPreview = ref('')
const loginBgPreview = ref('')
const signupBgPreview = ref('')

const handleLogoUpload = (event: Event) => {
  const file = (event.target as HTMLInputElement).files?.[0]
  if (file) {
    // Validate file
    if (!validateImageFile(file, 'logo')) return

    // Create preview
    const reader = new FileReader()
    reader.onload = (e) => {
      logoPreview.value = e.target?.result as string
      localData.value.app_logo = file.name // Store file reference
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
      localData.value.app_favicon = file.name
    }
    reader.readAsDataURL(file)
  }
}

const handleLoginBackgroundUpload = (event: Event) => {
  const file = (event.target as HTMLInputElement).files?.[0]
  if (file) {
    if (!validateImageFile(file, 'background')) return

    const reader = new FileReader()
    reader.onload = (e) => {
      loginBgPreview.value = e.target?.result as string
      localData.value.login_background_image = file.name
    }
    reader.readAsDataURL(file)
  }
}

const handleSignupBackgroundUpload = (event: Event) => {
  const file = (event.target as HTMLInputElement).files?.[0]
  if (file) {
    if (!validateImageFile(file, 'background')) return

    const reader = new FileReader()
    reader.onload = (e) => {
      signupBgPreview.value = e.target?.result as string
      localData.value.signup_background_image = file.name
    }
    reader.readAsDataURL(file)
  }
}

// Initialize on mount
onMounted(() => {
  initializeSettings()
})

// Watch for props changes and update local data
watch(() => props.data, (newData) => {
  if (newData && Object.keys(newData).length > 0) {
    Object.keys(localData.value).forEach(key => {
      if (newData[key] !== undefined) {
        localData.value[key] = newData[key]
      }
    })
  }
}, { immediate: true, deep: true })

// Computed loading state
const isFormLoading = computed(() => {
  return props.loading || isLoading.value || isSaving.value
})

// Validation function
const validateImageFile = (file: File, type: 'logo' | 'favicon' | 'background'): boolean => {
  const maxSizes = {
    logo: 2 * 1024 * 1024, // 2MB
    favicon: 1 * 1024 * 1024, // 1MB
    background: 5 * 1024 * 1024 // 5MB
  }

  const allowedTypes = {
    logo: ['image/jpeg', 'image/png', 'image/svg+xml'],
    favicon: ['image/x-icon', 'image/png', 'image/jpeg'],
    background: ['image/jpeg', 'image/png', 'image/webp']
  }

  // Check file size
  if (file.size > maxSizes[type]) {
    alert(`File too large. Maximum size for ${type} is ${maxSizes[type] / (1024 * 1024)}MB`)
    return false
  }

  // Check file type
  if (!allowedTypes[type].includes(file.type)) {
    alert(`Invalid file type for ${type}. Allowed types: ${allowedTypes[type].join(', ')}`)
    return false
  }

  return true
}

// Reset functions
const resetToDefaults = () => {
  localData.value = {
    app_name: 'Affiliate Platform',
    app_description: '',
    app_slogan: '',
    app_keywords: '',
    company_name: '',
    company_email: '',
    company_phone: '',
    company_address: '',
    company_website: '',
    company_social_facebook: '',
    company_social_instagram: '',
    company_social_twitter: '',
    app_logo: '',
    app_favicon: '',
    primary_color: '#6366F1',
    secondary_color: '#8B5CF6',
    login_background_image: '',
    signup_background_image: '',
    app_theme: 'light',
    default_language: 'fr',
    timezone: 'Africa/Casablanca',
    currency: 'MAD',
    currency_symbol: 'MAD',
    date_format: 'DD/MM/YYYY',
    time_format: '24',
    number_format: 'european',
    maintenance_mode: false,
    registration_enabled: true,
    email_verification_required: true,
    kyc_verification_required: true,
    max_file_upload_size: 10,
    allowed_file_types: 'jpg,jpeg,png,pdf,doc,docx',
    session_timeout: 120,
    password_min_length: 8,
    password_require_special: true,
    app_version: '1.0.0'
  }

  // Clear previews
  logoPreview.value = ''
  faviconPreview.value = ''
  loginBgPreview.value = ''
  signupBgPreview.value = ''
}

const resetColors = () => {
  localData.value.primary_color = '#6366F1'
  localData.value.secondary_color = '#8B5CF6'
}

const resetLanguage = () => {
  localData.value.default_language = 'fr'
}
</script>

<template>
  <!-- Loading Skeleton -->
  <VRow v-if="isLoading && !isInitialized">
    <VCol cols="12">
      <VCard>
        <VCardText>
          <VSkeletonLoader
            type="heading, paragraph, divider, heading, paragraph"
            class="mb-6"
          />
          <VSkeletonLoader
            type="heading, paragraph, divider, heading, paragraph"
            class="mb-6"
          />
          <VSkeletonLoader
            type="heading, paragraph, button"
          />
        </VCardText>
      </VCard>
    </VCol>
  </VRow>

  <!-- Settings Form -->
  <VRow v-else>
    <!-- App Information -->
    <VCol cols="12">
      <VCard :title="t('app_information')">
        <VCardText>
          <VRow>
            <!-- App Name -->
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="localData.app_name"
                :label="t('app_name')"
                :placeholder="t('placeholder_app_name')"
              />
            </VCol>

            <!-- App Version -->
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="localData.app_version"
                :label="t('app_version')"
                placeholder="1.0.0"
                readonly
              />
            </VCol>

            <!-- App Description -->
            <VCol cols="12">
              <AppTextarea
                v-model="localData.app_description"
                :label="t('app_description')"
                :placeholder="t('placeholder_app_description')"
                rows="3"
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
                :placeholder="t('placeholder_app_slogan')"
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
                placeholder="SEO keywords, separated by commas"
              />
            </VCol>

            <!-- Logo Upload -->
            <VCol
              cols="12"
              md="6"
            >
              <VLabel class="mb-2">{{ t('company_logo') }}</VLabel>
              <VFileInput
                :label="t('choose_logo_file')"
                prepend-inner-icon="tabler-photo"
                variant="outlined"
                accept="image/*"
                @change="handleLogoUpload"
              />
            </VCol>

            <!-- Favicon Upload -->
            <VCol
              cols="12"
              md="6"
            >
              <VLabel class="mb-2">{{ t('favicon') }}</VLabel>
              <VFileInput
                :label="t('choose_favicon_file')"
                prepend-inner-icon="tabler-world"
                variant="outlined"
                accept="image/*"
                @change="handleFaviconUpload"
              />
            </VCol>
          </VRow>
        </VCardText>
      </VCard>
    </VCol>

    <!-- Company Information -->
    <VCol cols="12">
      <VCard :title="t('company_information')">
        <VCardText>
          <VRow>
            <!-- Company Name -->
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="localData.company_name"
                :label="t('company_name')"
                :placeholder="t('placeholder_company_name')"
              />
            </VCol>

            <!-- Company Email -->
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="localData.company_email"
                :label="t('company_email')"
                placeholder="contact@company.com"
                type="email"
              />
            </VCol>

            <!-- Company Phone -->
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="localData.company_phone"
                :label="t('company_phone')"
                placeholder="+212 6 12 34 56 78"
              />
            </VCol>

            <!-- Company Address -->
            <VCol
              cols="12"
              md="6"
            >
              <AppTextarea
                v-model="localData.company_address"
                :label="t('company_address')"
                placeholder="Company address"
                rows="3"
              />
            </VCol>

            <!-- Company Website -->
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="localData.company_website"
                :label="t('company_website')"
                placeholder="https://www.company.com"
                type="url"
              />
            </VCol>

            <!-- Social Media Links -->
            <VCol cols="12">
              <h6 class="text-h6 mb-3">{{ t('social_media_links') }}</h6>
            </VCol>

            <VCol
              cols="12"
              md="4"
            >
              <AppTextField
                v-model="localData.company_social_facebook"
                :label="t('facebook_page')"
                placeholder="https://facebook.com/yourpage"
                prepend-inner-icon="tabler-brand-facebook"
              />
            </VCol>

            <VCol
              cols="12"
              md="4"
            >
              <AppTextField
                v-model="localData.company_social_instagram"
                :label="t('instagram_page')"
                placeholder="https://instagram.com/yourpage"
                prepend-inner-icon="tabler-brand-instagram"
              />
            </VCol>

            <VCol
              cols="12"
              md="4"
            >
              <AppTextField
                v-model="localData.company_social_twitter"
                :label="t('twitter_page')"
                placeholder="https://twitter.com/yourpage"
                prepend-inner-icon="tabler-brand-twitter"
              />
            </VCol>
          </VRow>
        </VCardText>
      </VCard>
    </VCol>

    <!-- Branding & Appearance -->
    <VCol cols="12">
      <VCard :title="t('branding_appearance')">
        <VCardText>
          <VRow>
            <!-- App Logo -->
            <VCol
              cols="12"
              md="6"
            >
              <VLabel class="mb-2">{{ t('app_logo') }}</VLabel>
              <VFileInput
                v-model="localData.app_logo"
                :label="t('choose_logo_file')"
                prepend-inner-icon="tabler-photo"
                variant="outlined"
                accept="image/*"
                @change="handleLogoUpload"
              />

              <!-- Logo Preview -->
              <div v-if="logoPreview || localData.app_logo" class="mt-3">
                <VLabel class="mb-2">{{ t('preview') }}:</VLabel>
                <div class="border rounded pa-3 d-flex align-center justify-center" style="min-height: 80px; background: #f5f5f5;">
                  <img
                    :src="logoPreview || localData.app_logo"
                    alt="Logo Preview"
                    style="max-height: 60px; max-width: 200px; object-fit: contain;"
                  >
                </div>
              </div>

              <VAlert
                type="info"
                variant="tonal"
                class="mt-2"
                density="compact"
              >
                {{ t('logo_requirements') }}
              </VAlert>
            </VCol>

            <!-- App Favicon -->
            <VCol
              cols="12"
              md="6"
            >
              <VLabel class="mb-2">{{ t('app_favicon') }}</VLabel>
              <VFileInput
                v-model="localData.app_favicon"
                :label="t('choose_favicon_file')"
                prepend-inner-icon="tabler-world"
                variant="outlined"
                accept="image/*"
                @change="handleFaviconUpload"
              />

              <!-- Favicon Preview -->
              <div v-if="faviconPreview || localData.app_favicon" class="mt-3">
                <VLabel class="mb-2">{{ t('preview') }}:</VLabel>
                <div class="border rounded pa-3 d-flex align-center justify-center" style="min-height: 60px; background: #f5f5f5;">
                  <img
                    :src="faviconPreview || localData.app_favicon"
                    alt="Favicon Preview"
                    style="height: 32px; width: 32px; object-fit: contain;"
                  >
                </div>
              </div>

              <VAlert
                type="info"
                variant="tonal"
                class="mt-2"
                density="compact"
              >
                {{ t('favicon_requirements') }}
              </VAlert>
            </VCol>

            <!-- Primary Color -->
            <VCol
              cols="12"
              md="6"
            >
              <VLabel class="mb-2">{{ t('primary_color') }}</VLabel>
              <div class="d-flex gap-2 mb-2">
                <VBtn
                  v-for="preset in colorPresets"
                  :key="preset.value"
                  :color="preset.color"
                  size="small"
                  icon
                  @click="localData.primary_color = preset.value"
                >
                  <VIcon v-if="localData.primary_color === preset.value" icon="tabler-check" />
                </VBtn>
              </div>
              <div class="d-flex gap-2">
                <AppTextField
                  v-model="localData.primary_color"
                  :label="t('custom_color')"
                  type="color"
                  class="flex-grow-1"
                />
                <VBtn
                  color="secondary"
                  variant="outlined"
                  size="small"
                  @click="localData.primary_color = '#6366F1'"
                >
                  {{ t('reset') }}
                </VBtn>
              </div>
            </VCol>

            <!-- Secondary Color -->
            <VCol
              cols="12"
              md="6"
            >
              <VLabel class="mb-2">{{ t('secondary_color') }}</VLabel>
              <div class="d-flex gap-2 mb-2">
                <VBtn
                  v-for="preset in colorPresets"
                  :key="preset.value"
                  :color="preset.color"
                  size="small"
                  icon
                  @click="localData.secondary_color = preset.value"
                >
                  <VIcon v-if="localData.secondary_color === preset.value" icon="tabler-check" />
                </VBtn>
              </div>
              <div class="d-flex gap-2">
                <AppTextField
                  v-model="localData.secondary_color"
                  :label="t('custom_color')"
                  type="color"
                  class="flex-grow-1"
                />
                <VBtn
                  color="secondary"
                  variant="outlined"
                  size="small"
                  @click="localData.secondary_color = '#8B5CF6'"
                >
                  {{ t('reset') }}
                </VBtn>
              </div>
            </VCol>

            <!-- Login Background -->
            <VCol
              cols="12"
              md="6"
            >
              <VLabel class="mb-2">{{ t('login_background') }}</VLabel>
              <VFileInput
                v-model="localData.login_background_image"
                :label="t('choose_background_image')"
                prepend-inner-icon="tabler-photo"
                variant="outlined"
                accept="image/*"
                @change="handleLoginBackgroundUpload"
              />

              <!-- Login Background Preview -->
              <div v-if="loginBgPreview || localData.login_background_image" class="mt-3">
                <VLabel class="mb-2">{{ t('preview') }}:</VLabel>
                <div class="border rounded pa-2" style="height: 120px; background: #f5f5f5; overflow: hidden;">
                  <img
                    :src="loginBgPreview || localData.login_background_image"
                    alt="Login Background Preview"
                    style="width: 100%; height: 100%; object-fit: cover; border-radius: 4px;"
                  >
                </div>
              </div>
            </VCol>

            <!-- Signup Background -->
            <VCol
              cols="12"
              md="6"
            >
              <VLabel class="mb-2">{{ t('signup_background') }}</VLabel>
              <VFileInput
                v-model="localData.signup_background_image"
                :label="t('choose_background_image')"
                prepend-inner-icon="tabler-photo"
                variant="outlined"
                accept="image/*"
                @change="handleSignupBackgroundUpload"
              />

              <!-- Signup Background Preview -->
              <div v-if="signupBgPreview || localData.signup_background_image" class="mt-3">
                <VLabel class="mb-2">{{ t('preview') }}:</VLabel>
                <div class="border rounded pa-2" style="height: 120px; background: #f5f5f5; overflow: hidden;">
                  <img
                    :src="signupBgPreview || localData.signup_background_image"
                    alt="Signup Background Preview"
                    style="width: 100%; height: 100%; object-fit: cover; border-radius: 4px;"
                  >
                </div>
              </div>
            </VCol>

            <!-- App Theme -->
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="localData.app_theme"
                :items="themeOptions"
                :label="t('app_theme')"
              />
            </VCol>
          </VRow>
        </VCardText>
      </VCard>
    </VCol>

    <!-- Localization Settings -->
    <VCol cols="12">
      <VCard :title="t('localization')">
        <VCardText>
          <VRow>
            <!-- Default Language -->
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="localData.default_language"
                :items="languages"
                :label="t('default_language')"
              >
                <template #item="{ props, item }">
                  <VListItem v-bind="props">
                    <template #prepend>
                      <span class="me-2">{{ item.raw.flag }}</span>
                    </template>
                  </VListItem>
                </template>
              </AppSelect>
            </VCol>

            <!-- Timezone -->
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="localData.timezone"
                :items="timezones"
                :label="t('timezone')"
              />
            </VCol>

            <!-- Currency Code -->
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="localData.currency"
                :label="t('currency_code')"
                placeholder="MAD"
              />
            </VCol>

            <!-- Currency Symbol -->
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="localData.currency_symbol"
                :label="t('currency_symbol')"
                placeholder="MAD"
              />
            </VCol>
          </VRow>
        </VCardText>
      </VCard>
    </VCol>

    <!-- Format Settings -->
    <VCol cols="12">
      <VCard :title="t('format_settings')">
        <VCardText>
          <VRow>
            <!-- Date Format -->
            <VCol
              cols="12"
              md="4"
            >
              <AppSelect
                v-model="localData.date_format"
                :items="dateFormats"
                :label="t('date_format')"
              />
            </VCol>

            <!-- Time Format -->
            <VCol
              cols="12"
              md="4"
            >
              <AppSelect
                v-model="localData.time_format"
                :items="timeFormats"
                :label="t('time_format')"
              />
            </VCol>

            <!-- Number Format -->
            <VCol
              cols="12"
              md="4"
            >
              <AppSelect
                v-model="localData.number_format"
                :items="numberFormats"
                :label="t('number_format')"
              />
            </VCol>

            <!-- Maintenance Mode -->
            <VCol cols="12">
              <VSwitch
                v-model="localData.maintenance_mode"
                :label="t('maintenance_mode')"
                color="warning"
                inset
              />
              <p class="text-caption text-medium-emphasis mt-2">
                {{ t('maintenance_mode_description') }}
              </p>
            </VCol>
          </VRow>
        </VCardText>
      </VCard>
    </VCol>

    <!-- App Behavior Settings -->
    <VCol cols="12">
      <VCard :title="t('app_behavior_settings')">
        <VCardText>
          <VRow>
            <!-- Registration Enabled -->
            <VCol
              cols="12"
              md="6"
            >
              <VSwitch
                v-model="localData.registration_enabled"
                :label="t('registration_enabled')"
                color="success"
                inset
              />
              <p class="text-caption text-medium-emphasis">
                {{ t('registration_enabled_description') }}
              </p>
            </VCol>

            <!-- Email Verification Required -->
            <VCol
              cols="12"
              md="6"
            >
              <VSwitch
                v-model="localData.email_verification_required"
                :label="t('email_verification_required')"
                color="info"
                inset
              />
              <p class="text-caption text-medium-emphasis">
                {{ t('email_verification_description') }}
              </p>
            </VCol>

            <!-- KYC Verification Required -->
            <VCol
              cols="12"
              md="6"
            >
              <VSwitch
                v-model="localData.kyc_verification_required"
                :label="t('kyc_verification_required')"
                color="warning"
                inset
              />
              <p class="text-caption text-medium-emphasis">
                {{ t('kyc_verification_description') }}
              </p>
            </VCol>

            <!-- Session Timeout -->
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model.number="localData.session_timeout"
                :label="t('session_timeout')"
                type="number"
                min="30"
                max="480"
                suffix="minutes"
              />
            </VCol>
          </VRow>
        </VCardText>
      </VCard>
    </VCol>

    <!-- File Upload Settings -->
    <VCol cols="12">
      <VCard :title="t('file_upload_settings')">
        <VCardText>
          <VRow>
            <!-- Max File Upload Size -->
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model.number="localData.max_file_upload_size"
                :label="t('max_file_upload_size')"
                type="number"
                min="1"
                max="100"
                suffix="MB"
              />
            </VCol>

            <!-- Allowed File Types -->
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="localData.allowed_file_types"
                :label="t('allowed_file_types')"
                placeholder="jpg,jpeg,png,pdf,doc,docx"
              />
            </VCol>
          </VRow>
        </VCardText>
      </VCard>
    </VCol>

    <!-- Security Settings -->
    <VCol cols="12">
      <VCard :title="t('security_settings')">
        <VCardText>
          <VRow>
            <!-- Password Minimum Length -->
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model.number="localData.password_min_length"
                :label="t('password_min_length')"
                type="number"
                min="6"
                max="20"
              />
            </VCol>

            <!-- Password Require Special Characters -->
            <VCol
              cols="12"
              md="6"
            >
              <VSwitch
                v-model="localData.password_require_special"
                :label="t('password_require_special')"
                color="error"
                inset
              />
              <p class="text-caption text-medium-emphasis">
                {{ t('password_require_special_description') }}
              </p>
            </VCol>
          </VRow>
        </VCardText>
      </VCard>
    </VCol>

    <!-- Action Buttons -->
    <VCol cols="12">
      <div class="d-flex gap-3 flex-wrap">
        <VBtn
          color="primary"
          prepend-icon="tabler-device-floppy"
          :loading="isFormLoading"
          :disabled="isFormLoading"
          @click="handleSave"
        >
          {{ t('save_general_settings') }}
        </VBtn>

        <VBtn
          color="secondary"
          variant="outlined"
          prepend-icon="tabler-refresh"
          :disabled="isFormLoading"
          @click="resetColors"
        >
          {{ t('reset_colors') }}
        </VBtn>

        <VBtn
          color="warning"
          variant="outlined"
          prepend-icon="tabler-restore"
          :disabled="isFormLoading"
          @click="resetToDefaults"
        >
          {{ t('reset_to_defaults') }}
        </VBtn>
      </div>
    </VCol>
  </VRow>
</template>
