<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

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
  company_name: '',
  company_email: '',
  company_phone: '',
  company_address: '',
  default_language: 'fr',
  timezone: 'Africa/Casablanca',
  currency: 'MAD',
  currency_symbol: 'MAD',
  date_format: 'DD/MM/YYYY',
  time_format: '24',
  number_format: 'european',
  maintenance_mode: false,
  app_version: '1.0.0',
  ...props.data
})

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

// Watch for changes and emit
watch(localData, (newData) => {
  Object.keys(newData).forEach(key => {
    if (newData[key] !== props.data[key]) {
      emit('change', key, newData[key])
    }
  })
}, { deep: true })

// Handle save
const handleSave = () => {
  emit('save', localData.value)
}

// Handle logo upload
const handleLogoUpload = (file: File) => {
  // Handle logo upload logic here
  console.log('Logo upload:', file)
}

// Handle favicon upload
const handleFaviconUpload = (file: File) => {
  // Handle favicon upload logic here
  console.log('Favicon upload:', file)
}
</script>

<template>
  <VRow>
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
                placeholder="My Affiliate App"
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
                placeholder="Description of your affiliate application"
                rows="3"
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
                placeholder="Your Company Name"
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

        <!-- Save Button -->
        <VCardText>
          <VBtn
            color="primary"
            prepend-icon="tabler-device-floppy"
            :loading="loading"
            @click="handleSave"
          >
            {{ t('save_general_settings') }}
          </VBtn>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>
