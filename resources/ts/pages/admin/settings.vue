<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { $api } from '@/utils/api'

// Components
import GeneralSettings from '@/components/admin/settings/GeneralSettings.vue'
import BusinessSettings from '@/components/admin/settings/BusinessSettings.vue'
import ShippingSettings from '@/components/admin/settings/ShippingSettings.vue'
import UserSettings from '@/components/admin/settings/UserSettings.vue'
import ProductSettings from '@/components/admin/settings/ProductSettings.vue'
import CommunicationSettings from '@/components/admin/settings/CommunicationSettings.vue'
import SecuritySettings from '@/components/admin/settings/SecuritySettings.vue'
import SystemSettings from '@/components/admin/settings/SystemSettings.vue'

const { t } = useI18n()

// Page state
const loading = ref(false)
const activeTab = ref('general')

// Settings data
const settings = ref<Record<string, Record<string, any>>>({
  general: {},
  business: {},
  shipping: {},
  users: {},
  products: {},
  communication: {},
  security: {},
  system: {},
})

// Tab configuration
const tabs = computed(() => [
  {
    value: 'general',
    title: t('general_settings'),
    icon: 'tabler-settings'
  },
  {
    value: 'business',
    title: t('business_config'),
    icon: 'tabler-building-store'
  },
  {
    value: 'shipping',
    title: t('shipping_integration'),
    icon: 'tabler-truck-delivery'
  },
  {
    value: 'users',
    title: t('user_management'),
    icon: 'tabler-users'
  },
  {
    value: 'products',
    title: t('product_settings'),
    icon: 'tabler-package'
  },
  {
    value: 'communication',
    title: t('communication'),
    icon: 'tabler-mail'
  },
  {
    value: 'security',
    title: t('security_privacy'),
    icon: 'tabler-shield-lock'
  },
  {
    value: 'system',
    title: t('system_config'),
    icon: 'tabler-server'
  }
])

// Handle setting changes
const onSettingChange = (category: string, key: string, value: any) => {
  if (!settings.value[category]) {
    settings.value[category] = {}
  }
  settings.value[category][key] = value
}

// Save settings
const saveSettings = async (category: string, data: any) => {
  try {
    const response = await $api(`/admin/settings/${category}`, {
      method: 'PUT',
      body: data
    })

    if (response.success) {
      settings.value[category] = { ...settings.value[category], ...data }
      // You can add a toast notification here
      console.log('Settings saved successfully:', response.message)
    }

    return response.success
  } catch (error) {
    console.error('Failed to save settings:', error)
    return false
  }
}

// Load settings on mount
const loadSettings = async () => {
  loading.value = true
  try {
    const response = await $api('/admin/settings/new', {
      method: 'GET'
    })

    if (response.success) {
      // Merge loaded settings with defaults
      Object.keys(response.data).forEach(category => {
        if (settings.value[category]) {
          settings.value[category] = { ...settings.value[category], ...response.data[category] }
        } else {
          settings.value[category] = response.data[category]
        }
      })
    }
  } catch (error) {
    console.error('Failed to load settings:', error)
  } finally {
    loading.value = false
  }
}

// Initialize settings with defaults
const initializeSettings = () => {
  // Set default values for all categories
  settings.value = {
    general: {
      app_name: 'Affiliate Platform',
      app_description: '',
      app_tagline: '',
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
    },
    business: {},
    shipping: {},
    users: {},
    products: {},
    communication: {},
    security: {},
    system: {}
  }
}

// Load settings when component mounts
onMounted(() => {
  initializeSettings()
  loadSettings()
})
</script>

<template>
  <div>
    <!-- Page Header -->
    <div class="d-flex align-center justify-space-between mb-6">
      <div>
        <h4 class="text-h4 font-weight-bold mb-1">
          {{ t('app_settings') }}
        </h4>
        <p class="text-body-1 mb-0">
          {{ t('configure_app_behavior_preferences') }}
        </p>
      </div>
    </div>

    <!-- Settings Tabs -->
    <VTabs
      v-model="activeTab"
      class="v-tabs-pill"
    >
      <VTab
        v-for="tab in tabs"
        :key="tab.value"
        :value="tab.value"
      >
        <VIcon
          size="20"
          start
          :icon="tab.icon"
        />
        {{ tab.title }}
      </VTab>
    </VTabs>

    <!-- Settings Content -->
    <VWindow
      v-model="activeTab"
      class="mt-6 disable-tab-transition"
      :touch="false"
    >
      <!-- General Settings -->
      <VWindowItem value="general">
        <GeneralSettings
          :data="settings.general"
          :loading="loading"
          @change="(key, value) => onSettingChange('general', key, value)"
          @save="(data) => saveSettings('general', data)"
        />
      </VWindowItem>

      <!-- Business Settings -->
      <VWindowItem value="business">
        <BusinessSettings
          :data="settings.business"
          :loading="loading"
          @change="(key, value) => onSettingChange('business', key, value)"
          @save="(data) => saveSettings('business', data)"
        />
      </VWindowItem>

      <!-- Shipping Settings -->
      <VWindowItem value="shipping">
        <ShippingSettings
          :data="settings.shipping"
          :loading="loading"
          @change="(key, value) => onSettingChange('shipping', key, value)"
          @save="(data) => saveSettings('shipping', data)"
        />
      </VWindowItem>

      <!-- User Settings -->
      <VWindowItem value="users">
        <UserSettings
          :data="settings.users"
          :loading="loading"
          @change="(key, value) => onSettingChange('users', key, value)"
          @save="(data) => saveSettings('users', data)"
        />
      </VWindowItem>

      <!-- Product Settings -->
      <VWindowItem value="products">
        <ProductSettings
          :data="settings.products"
          :loading="loading"
          @change="(key, value) => onSettingChange('products', key, value)"
          @save="(data) => saveSettings('products', data)"
        />
      </VWindowItem>

      <!-- Communication Settings -->
      <VWindowItem value="communication">
        <CommunicationSettings
          :data="settings.communication"
          :loading="loading"
          @change="(key, value) => onSettingChange('communication', key, value)"
          @save="(data) => saveSettings('communication', data)"
        />
      </VWindowItem>

      <!-- Security Settings -->
      <VWindowItem value="security">
        <SecuritySettings
          :data="settings.security"
          :loading="loading"
          @change="(key, value) => onSettingChange('security', key, value)"
          @save="(data) => saveSettings('security', data)"
        />
      </VWindowItem>

      <!-- System Settings -->
      <VWindowItem value="system">
        <SystemSettings
          :data="settings.system"
          :loading="loading"
          @change="(key, value) => onSettingChange('system', key, value)"
          @save="(data) => saveSettings('system', data)"
        />
      </VWindowItem>
    </VWindow>
  </div>
</template>
