<script setup lang="ts">
import { useAuth } from '@/composables/useAuth'
import { useAuthStore } from '@/stores/auth'
import { useI18n } from 'vue-i18n'
import ProfileImageUpload from '@/components/ProfileImageUpload.vue'

const { user } = useAuth()
const authStore = useAuthStore()
const { t } = useI18n()

// Form data
const accountForm = ref({
  nom_complet: '',
  email: '',
  telephone: '',
  adresse: '',
  photo_profil: ''
})

// Form state
const isLoading = ref(false)
const isDeactivateDialogOpen = ref(false)
const deactivateConfirmation = ref('')

// Initialize form with user data
watch(user, (newUser) => {
  if (newUser) {
    accountForm.value = {
      nom_complet: newUser.nom_complet || '',
      email: newUser.email || '',
      telephone: newUser.telephone || '',
      adresse: newUser.adresse || '',
      photo_profil: newUser.photo_profil || ''
    }
  }
}, { immediate: true })

// Validation rules
const validateAccountDeactivation = [(v: string) => !!v || t('please_confirm_deactivation')]

// Save account changes
const saveAccount = async () => {
  try {
    isLoading.value = true

    const response = await fetch('/api/profile', {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${authStore.token}`,
        'Accept': 'application/json',
      },
      body: JSON.stringify(accountForm.value)
    })

    const result = await response.json()

    if (result.success) {
      alert(t('account_updated_successfully'))
      // Refresh user data
      // You might want to call a method to refresh the user data in the auth store
    } else {
      throw new Error(result.message || t('account_update_failed'))
    }
  } catch (error) {
    console.error('Error updating account:', error)
    alert(t('account_update_failed'))
  } finally {
    isLoading.value = false
  }
}

// Reset form
const resetForm = () => {
  if (user.value) {
    accountForm.value = {
      nom_complet: user.value.nom_complet || '',
      email: user.value.email || '',
      telephone: user.value.telephone || '',
      adresse: user.value.adresse || '',
      photo_profil: user.value.photo_profil || ''
    }
  }
}

// Deactivate account
const deactivateAccount = async () => {
  if (deactivateConfirmation.value !== 'DEACTIVATE') {
    return
  }

  try {
    // This would be an API call to deactivate the account
    alert(t('account_deactivation_requested'))
    isDeactivateDialogOpen.value = false
    deactivateConfirmation.value = ''
  } catch (error) {
    console.error('Error deactivating account:', error)
    alert(t('account_deactivation_failed'))
  }
}

// Countries list (simplified)
const countries = [
  'Morocco',
  'France', 
  'Spain',
  'United States',
  'Canada',
  'United Kingdom',
  'Germany',
  'Italy'
]

// Languages list
const languages = [
  { value: 'en', title: 'English' },
  { value: 'fr', title: 'Français' },
  { value: 'ar', title: 'العربية' },
  { value: 'es', title: 'Español' }
]

// Timezones (simplified)
const timezones = [
  '(GMT+00:00) Casablanca, Rabat',
  '(GMT+01:00) Paris, Madrid',
  '(GMT-05:00) Eastern Time (US & Canada)',
  '(GMT-08:00) Pacific Time (US & Canada)'
]
</script>

<template>
  <VRow>
    <!-- Account Details -->
    <VCol cols="12">
      <VCard :title="t('account_details')">
        <VForm @submit.prevent="saveAccount">
          <VCardText class="d-flex">
            <!-- Avatar Section -->
            <VAvatar
              rounded
              size="100"
              class="me-6"
              :color="!accountForm.photo_profil ? 'primary' : undefined"
              :variant="!accountForm.photo_profil ? 'tonal' : undefined"
            >
              <VImg 
                v-if="accountForm.photo_profil"
                :src="accountForm.photo_profil"
                :alt="accountForm.nom_complet"
              />
              <span v-else class="text-5xl font-weight-medium">
                {{ accountForm.nom_complet?.charAt(0)?.toUpperCase() }}
              </span>
            </VAvatar>

            <!-- Profile Image Upload -->
            <div class="d-flex flex-column justify-center gap-4">
              <div class="d-flex flex-wrap gap-2">
                <ProfileImageUpload
                  v-model="accountForm.photo_profil"
                  :label="t('upload_photo')"
                />
              </div>
              <p class="text-body-2 mb-0">
                {{ t('profile_image_requirements') }}
              </p>
            </div>
          </VCardText>

          <VDivider />

          <VCardText>
            <!-- Form Fields -->
            <VRow>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="accountForm.nom_complet"
                  :label="t('form_full_name')"
                  :placeholder="t('placeholder_enter_full_name')"
                />
              </VCol>

              <VCol cols="12" md="6">
                <VTextField
                  v-model="accountForm.email"
                  :label="t('form_email')"
                  :placeholder="t('placeholder_enter_email')"
                  type="email"
                />
              </VCol>

              <VCol cols="12" md="6">
                <VTextField
                  v-model="accountForm.telephone"
                  :label="t('form_phone')"
                  :placeholder="t('placeholder_enter_phone')"
                />
              </VCol>

              <VCol cols="12" md="6">
                <VTextField
                  v-model="accountForm.adresse"
                  :label="t('form_address')"
                  :placeholder="t('placeholder_enter_address')"
                />
              </VCol>

              <VCol cols="12" md="6">
                <VSelect
                  :label="t('country')"
                  :placeholder="t('select_country')"
                  :items="countries"
                />
              </VCol>

              <VCol cols="12" md="6">
                <VSelect
                  :label="t('language')"
                  :placeholder="t('select_language')"
                  :items="languages"
                  item-title="title"
                  item-value="value"
                />
              </VCol>

              <VCol cols="12" md="6">
                <VSelect
                  :label="t('timezone')"
                  :placeholder="t('select_timezone')"
                  :items="timezones"
                />
              </VCol>
            </VRow>
          </VCardText>

          <VCardActions>
            <VBtn
              type="submit"
              :loading="isLoading"
            >
              {{ t('save_changes') }}
            </VBtn>

            <VBtn
              color="secondary"
              variant="outlined"
              @click="resetForm"
            >
              {{ t('reset') }}
            </VBtn>
          </VCardActions>
        </VForm>
      </VCard>
    </VCol>

    <!-- Delete Account -->
    <VCol cols="12">
      <VCard :title="t('delete_account')">
        <VCardText>
          <div>
            <VCheckbox
              label="I confirm my account deactivation"
              class="mb-3"
            />
            <VBtn
              color="error"
              class="me-3"
              @click="isDeactivateDialogOpen = true"
            >
              {{ t('deactivate_account') }}
            </VBtn>
            <span class="text-body-2">{{ t('deactivate_account_warning') }}</span>
          </div>
        </VCardText>
      </VCard>
    </VCol>

    <!-- Deactivate Account Dialog -->
    <VDialog
      v-model="isDeactivateDialogOpen"
      max-width="500"
    >
      <VCard>
        <VCardTitle>{{ t('confirm_account_deactivation') }}</VCardTitle>
        
        <VCardText>
          <p class="mb-4">{{ t('deactivation_warning_text') }}</p>
          
          <VTextField
            v-model="deactivateConfirmation"
            :label="t('type_deactivate_to_confirm')"
            :placeholder="'DEACTIVATE'"
            :rules="validateAccountDeactivation"
          />
        </VCardText>

        <VCardActions>
          <VSpacer />
          
          <VBtn
            color="secondary"
            variant="outlined"
            @click="isDeactivateDialogOpen = false"
          >
            {{ t('action_cancel') }}
          </VBtn>
          
          <VBtn
            color="error"
            :disabled="deactivateConfirmation !== 'DEACTIVATE'"
            @click="deactivateAccount"
          >
            {{ t('deactivate_account') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </VRow>
</template>
