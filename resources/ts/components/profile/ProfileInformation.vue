<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuth } from '@/composables/useAuth'
import { useApi } from '@/composables/useApi'
import { useNotifications } from '@/composables/useNotifications'
import type { User } from '@/types/auth'

// Props
interface Props {
  user: User
}

const props = defineProps<Props>()

const { t } = useI18n()
const { fetchUser } = useAuth()
const { showSuccess, showError } = useNotifications()

// Form state
const loading = ref(false)
const isEditing = ref(false)
const uploadingImage = ref(false)
const removingImage = ref(false)
const imageInput = ref<HTMLInputElement>()

// Form data
const form = ref({
  nom_complet: '',
  email: '',
  telephone: '',
  adresse: '',
  cin: '',
  photo_profil: ''
})

// Validation errors
const errors = ref<Record<string, string[]>>({})

// Initialize form with user data
const initializeForm = () => {
  if (props.user) {
    form.value = {
      nom_complet: props.user.nom_complet || '',
      email: props.user.email || '',
      telephone: props.user.telephone || '',
      adresse: props.user.adresse || '',
      cin: props.user.cin || '',
      photo_profil: props.user.photo_profil || ''
    }
    console.log('🔄 Form initialized with user data:', form.value)
    console.log('👤 Current user data:', props.user)
    console.log('🖼️ Profile image URL:', props.user.photo_profil)
  } else {
    console.log('⚠️ No user data available to initialize form')
  }
}

// Profile image computed
const profileImage = computed(() => {
  if (form.value.photo_profil) {
    return form.value.photo_profil
  }
  return `https://ui-avatars.com/api/?name=${encodeURIComponent(form.value.nom_complet || 'User')}&background=7367f0&color=fff&size=140`
})

// Handle image upload
const handleImageUpload = async (event: Event) => {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0]
  
  if (!file) return

  // Validate file type
  if (!file.type.startsWith('image/')) {
    showError('Please select a valid image file')
    return
  }

  // Validate file size (2MB)
  if (file.size > 2 * 1024 * 1024) {
    showError('Image size must be less than 2MB')
    return
  }

  try {
    uploadingImage.value = true
    
    const formData = new FormData()
    formData.append('profile_image', file)

    const response = await fetch('/api/upload/profile-image', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
        'Accept': 'application/json',
      },
      body: formData
    })

    if (!response.ok) {
      const errorData = await response.json()
      showError(errorData.message || t('image_upload_failed'))
      return
    }

    const data = await response.json()
    if (data.success) {
      form.value.photo_profil = data.url
      // Save the image URL to the database immediately
      await saveImageToProfile(data.url)
      showSuccess(data.message || t('image_upload_success'))
    }
  } catch (err: any) {
    showError(err.message || t('image_upload_failed'))
  } finally {
    uploadingImage.value = false
  }
}

// Trigger file input
const triggerImageUpload = () => {
  imageInput.value?.click()
}

// Save image URL to profile
const saveImageToProfile = async (imageUrl: string) => {
  try {
    const updatePayload = {
      photo_profil: imageUrl
    }

    const response = await fetch('/api/profile', {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
        'Accept': 'application/json',
      },
      body: JSON.stringify(updatePayload)
    })

    if (!response.ok) {
      throw new Error('Failed to save image to profile')
    }
  } catch (err: any) {
    console.error('Failed to save image to profile:', err)
  }
}

// Remove profile image
const removeProfileImage = async () => {
  try {
    removingImage.value = true
    
    const response = await fetch('/api/profile/remove-image', {
      method: 'DELETE',
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
        'Accept': 'application/json',
      }
    })

    if (!response.ok) {
      const errorData = await response.json()
      showError(errorData.message || 'Failed to remove image')
      return
    }

    const data = await response.json()
    if (data.success) {
      form.value.photo_profil = ''
      showSuccess(data.message || 'Profile image removed successfully')
    }
  } catch (err: any) {
    showError(err.message || 'Failed to remove image')
  } finally {
    removingImage.value = false
  }
}

// Reset form to original values
const resetForm = () => {
  initializeForm()
  errors.value = {}
  isEditing.value = false
}

// Save profile changes
const saveProfile = async () => {
  try {
    loading.value = true
    errors.value = {}

    // Exclude email from the update payload since it's readonly
    const updatePayload = {
      nom_complet: form.value.nom_complet,
      telephone: form.value.telephone,
      adresse: form.value.adresse,
      cin: form.value.cin,
      photo_profil: form.value.photo_profil
    }

    const response = await fetch('/api/profile', {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
        'Accept': 'application/json',
      },
      body: JSON.stringify(updatePayload)
    })

    if (!response.ok) {
      const errorData = await response.json()
      if (response.status === 422 && errorData.errors) {
        errors.value = errorData.errors
      } else {
        showError(errorData.message || t('profile_update_failed'))
      }
      return
    }

    const data = await response.json()
    if (data.success) {
      showSuccess(data.message || t('profile_updated_successfully'))
      // Refresh user data to get updated information
      await fetchUser()
      isEditing.value = false
    }
  } catch (err: any) {
    showError(err.message || t('profile_update_failed'))
  } finally {
    loading.value = false
  }
}

// Computed properties
const hasChanges = computed(() => {
  if (!props.user) return false

  return (
    form.value.nom_complet !== (props.user.nom_complet || '') ||
    form.value.telephone !== (props.user.telephone || '') ||
    form.value.adresse !== (props.user.adresse || '') ||
    form.value.cin !== (props.user.cin || '') ||
    form.value.photo_profil !== (props.user.photo_profil || '')
  )
})

const canSave = computed(() => {
  return hasChanges.value && form.value.nom_complet.trim()
})

// Watch for user data changes and reinitialize form
watch(() => props.user, (newUser) => {
  if (newUser) {
    console.log('👤 User data changed, reinitializing form:', newUser)
    initializeForm()
  }
}, { immediate: true })

// Initialize on mount
onMounted(() => {
  console.log('🔄 Component mounted, initializing form')
  if (props.user) {
    initializeForm()
  }
})
</script>

<template>
  <VCard class="profile-info-card">
    <VCardTitle class="profile-info-title">
      <div class="title-content">
        <VIcon icon="tabler-user-edit" size="28" class="title-icon" />
        <div>
          <h3 class="title-text">{{ t('profile_information') }}</h3>
          <p class="title-subtitle">{{ t('manage_your_personal_information') }}</p>
        </div>
      </div>
      
      <VBtn
        v-if="!isEditing"
        color="primary"
        variant="tonal"
        size="small"
        class="edit-btn"
        @click="isEditing = true"
      >
        <VIcon start icon="tabler-edit" />
        {{ t('edit') }}
      </VBtn>
    </VCardTitle>

    <VCardText class="profile-info-content">
      <VForm @submit.prevent="saveProfile" class="profile-form">
        <!-- Personal Information Section -->
        <div class="form-section">
          <h6 class="section-title">
            <VIcon icon="tabler-user" size="20" />
            {{ t('personal_information') }}
          </h6>
          
          <VRow>
            <!-- Full Name -->
            <VCol cols="12" md="6">
              <VTextField
                v-model="form.nom_complet"
                :label="t('form_full_name')"
                :placeholder="t('enter_full_name')"
                :readonly="!isEditing"
                :error-messages="errors.nom_complet"
                required
                variant="outlined"
                class="form-field"
              >
                <template #prepend-inner>
                  <VIcon icon="tabler-user" size="20" class="field-icon" />
                </template>
              </VTextField>
            </VCol>

            <!-- CIN -->
            <VCol cols="12" md="6">
              <VTextField
                v-model="form.cin"
                :label="t('cin_number')"
                :placeholder="t('enter_cin')"
                :readonly="!isEditing"
                :error-messages="errors.cin"
                variant="outlined"
                class="form-field"
              >
                <template #prepend-inner>
                  <VIcon icon="tabler-id" size="20" class="field-icon" />
                </template>
              </VTextField>
            </VCol>
          </VRow>
        </div>

        <!-- Contact Information Section -->
        <div class="form-section">
          <h6 class="section-title">
            <VIcon icon="tabler-address-book" size="20" />
            {{ t('contact_information') }}
          </h6>
          
          <VRow>
            <!-- Email -->
            <VCol cols="12" md="6">
              <div class="readonly-field-container">
                <span class="readonly-field-label">{{ t('form_email') }}</span>
                <VTextField
                  v-model="form.email"
                  :placeholder="t('email_readonly_notice')"
                  readonly
                  disabled
                  hide-details
                  variant="plain"
                  class="readonly-field-input"
                />
              </div>
            </VCol>

            <!-- Phone -->
            <VCol cols="12" md="6">
              <VTextField
                v-model="form.telephone"
                :label="t('form_phone')"
                :placeholder="t('enter_phone')"
                :readonly="!isEditing"
                :error-messages="errors.telephone"
                variant="outlined"
                class="form-field"
              >
                <template #prepend-inner>
                  <VIcon icon="tabler-phone" size="20" class="field-icon" />
                </template>
              </VTextField>
            </VCol>

            <!-- Address -->
            <VCol cols="12">
              <VTextarea
                v-model="form.adresse"
                :label="t('form_address')"
                :placeholder="t('enter_address')"
                :readonly="!isEditing"
                :error-messages="errors.adresse"
                variant="outlined"
                rows="3"
                class="form-field"
              >
                <template #prepend-inner>
                  <VIcon icon="tabler-map-pin" size="20" class="field-icon" />
                </template>
              </VTextarea>
            </VCol>
          </VRow>
        </div>

        <!-- Profile Image Section -->
        <div v-if="isEditing" class="form-section">
          <h6 class="section-title">
            <VIcon icon="tabler-photo" size="20" />
            {{ t('profile_image') }}
          </h6>
          
          <VRow>
            <VCol cols="12">
              <div class="image-upload-section">
                <div class="current-image">
                  <VAvatar size="120" class="profile-avatar">
                    <VImg
                      v-if="form.photo_profil"
                      :src="form.photo_profil"
                      :alt="t('profile_image')"
                      cover
                      @error="console.error('Failed to load profile image in form:', form.photo_profil)"
                    />
                    <VImg
                      v-else
                      :src="profileImage"
                      :alt="t('profile_image')"
                      cover
                    />
                  </VAvatar>
                  <p class="text-caption mt-2">
                    {{ form.photo_profil ? t('current_image') : t('no_image_uploaded') }}
                  </p>
                </div>
                
                <div class="image-actions">
                  <VBtn
                    color="primary"
                    variant="tonal"
                    class="upload-btn"
                    @click="triggerImageUpload"
                    :loading="uploadingImage"
                  >
                    <VIcon start icon="tabler-upload" />
                    {{ t('upload_profile_image') }}
                  </VBtn>
                  
                  <VBtn
                    v-if="form.photo_profil"
                    color="error"
                    variant="outlined"
                    class="remove-btn"
                    @click="removeProfileImage"
                    :loading="removingImage"
                  >
                    <VIcon start icon="tabler-trash" />
                    {{ t('remove_profile_image') }}
                  </VBtn>
                </div>
              </div>
              
              <input
                ref="imageInput"
                type="file"
                accept="image/*"
                style="display: none"
                @change="handleImageUpload"
              />
            </VCol>
          </VRow>
        </div>

        <!-- Banking Information Section removed as per requirements -->

        <!-- Action Buttons -->
        <VExpandTransition>
          <div v-if="isEditing" class="action-section">
            <div class="action-buttons">
              <VBtn
                type="submit"
                color="primary"
                size="large"
                :loading="loading"
                :disabled="!canSave"
                class="action-btn primary-btn"
              >
                <VIcon start icon="tabler-check" />
                {{ t('save_changes') }}
              </VBtn>

              <VBtn
                color="secondary"
                variant="outlined"
                size="large"
                :disabled="loading"
                class="action-btn secondary-btn"
                @click="resetForm"
              >
                <VIcon start icon="tabler-x" />
                {{ t('cancel_changes') }}
              </VBtn>
            </div>
            
            <div v-if="hasChanges" class="changes-indicator">
              <VIcon icon="tabler-circle-dot" size="12" class="changes-dot" />
              <span class="changes-text">{{ t('unsaved_changes') }}</span>
            </div>
          </div>
        </VExpandTransition>
      </VForm>
    </VCardText>
  </VCard>
</template>

<style lang="scss" scoped>
.profile-info-card {
  border-radius: 20px;
  background: white;
  backdrop-filter: none;
  border: 1px solid rgba(var(--v-theme-outline), 0.08);
  box-shadow: 0 8px 32px rgba(var(--v-theme-on-surface), 0.06);
  overflow: hidden;
}

.profile-info-title {
  padding: 2rem 2rem 1rem;
  border-bottom: 1px solid rgba(var(--v-theme-outline), 0.08);
  background: transparent;
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
}

.title-content {
  display: flex;
  align-items: center;
  gap: 1rem;
  flex: 1;
}

.title-icon {
  padding: 0.75rem;
  background: rgba(var(--v-theme-primary), 0.1);
  border-radius: 12px;
  color: rgb(var(--v-theme-primary));
}

.title-text {
  font-size: 1.5rem;
  font-weight: 600;
  margin: 0;
  color: rgb(var(--v-theme-on-surface));
  letter-spacing: -0.025em;
}

.title-subtitle {
  font-size: 0.875rem;
  color: rgba(var(--v-theme-on-surface), 0.6);
  margin: 0.25rem 0 0;
}

.edit-btn {
  border-radius: 12px;
  font-weight: 500;
  letter-spacing: 0.025em;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  
  &:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(var(--v-theme-primary), 0.2);
  }
}

.profile-info-content {
  padding: 2rem;
}

.profile-form {
  display: flex;
  flex-direction: column;
  gap: 2rem;
}

.form-section {
  background: transparent;
  border-radius: 16px;
  padding: 1.5rem;
  border: 1px solid rgba(var(--v-theme-outline), 0.06);
  transition: all 0.3s ease;
  
  &:hover {
    background: transparent;
    border-color: rgba(var(--v-theme-outline), 0.12);
  }
  
  &.readonly-section {
    background: transparent;
    border-style: dashed;
    
    &:hover {
      background: transparent;
    }
  }
}

.section-title {
  font-size: 1rem;
  font-weight: 600;
  margin-bottom: 1.5rem;
  color: rgb(var(--v-theme-on-surface));
  display: flex;
  align-items: center;
  gap: 0.75rem;
  
  .v-icon {
    padding: 0.5rem;
    background: rgba(var(--v-theme-primary), 0.1);
    border-radius: 8px;
    color: rgb(var(--v-theme-primary));
  }
}

.banking-notice {
  margin-bottom: 1rem;
  border-radius: 12px;
}

.form-field {
  :deep(.v-field) {
    border-radius: 12px;
    background: transparent !important;
    backdrop-filter: none;
    border: 2px solid rgba(var(--v-theme-outline), 0.2);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    
    &:hover {
      border-color: rgba(var(--v-theme-primary), 0.4);
      box-shadow: 0 2px 8px rgba(var(--v-theme-primary), 0.1);
    }
  }
  
  :deep(.v-field__input) {
    padding: 16px 20px;
    font-size: 0.95rem;
    line-height: 1.5;
  }
  
  :deep(.v-field__prepend-inner) {
    padding-inline-start: 16px;
    padding-inline-end: 8px;
  }
  
  &.v-text-field--focused :deep(.v-field),
  &.v-textarea--focused :deep(.v-field) {
    border-color: rgb(var(--v-theme-primary)) !important;
    box-shadow: 0 0 0 3px rgba(var(--v-theme-primary), 0.12);
    background: transparent !important;
  }
  
  &.readonly-field :deep(.v-field) {
    background: transparent !important;
    border-color: rgba(var(--v-theme-outline), 0.1);
    border-style: dashed !important;
    min-height: 64px !important;
    
    .v-field__input {
      color: rgba(var(--v-theme-on-surface), 0.6);
      padding-top: 24px !important;
      padding-bottom: 8px !important;
    }
    
    .v-field__label {
      top: 4px !important;
      font-size: 0.75rem !important;
      opacity: 0.7 !important;
      background: white !important;
      padding: 0 4px !important;
      margin-left: 8px !important;
    }
    
    &:hover {
      border-color: rgba(var(--v-theme-outline), 0.15);
      box-shadow: none;
    }
  }
  
  .field-icon {
    color: rgba(var(--v-theme-on-surface), 0.5);
  }
}

// Image Upload Section Styles
.image-upload-section {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1.5rem;
  padding: 1.5rem;
  background: transparent;
  border-radius: 16px;
  border: 2px dashed rgba(var(--v-theme-outline), 0.2);
  transition: all 0.3s ease;
  
  &:hover {
    border-color: rgba(var(--v-theme-primary), 0.3);
    background: transparent;
  }
}

.current-image {
  position: relative;
  
  .profile-avatar {
    border: 3px solid rgba(var(--v-theme-primary), 0.2);
    box-shadow: 0 4px 16px rgba(var(--v-theme-shadow-key-umbra), 0.1);
    transition: all 0.3s ease;
    
    &:hover {
      border-color: rgba(var(--v-theme-primary), 0.4);
      box-shadow: 0 8px 24px rgba(var(--v-theme-shadow-key-umbra), 0.15);
    }
  }
}

.image-actions {
  display: flex;
  gap: 1rem;
  align-items: center;
  
  .upload-btn, .remove-btn {
    border-radius: 12px;
    padding: 8px 20px;
    font-weight: 500;
    transition: all 0.3s ease;
    
    &:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 16px rgba(var(--v-theme-primary), 0.2);
    }
  }
  
  .remove-btn {
    &:hover {
      box-shadow: 0 4px 16px rgba(var(--v-theme-error), 0.2);
    }
  }
}

.readonly-field {
  :deep(.v-field) {
    background: transparent !important;
    border-style: dashed !important;
    border-color: rgba(var(--v-theme-outline), 0.15) !important;
    min-height: 64px !important;
    
    .v-field__input {
      color: rgba(var(--v-theme-on-surface), 0.6);
      padding-top: 24px !important;
      padding-bottom: 8px !important;
      min-height: 24px !important;
    }
    
    .v-field__label {
      top: 4px !important;
      font-size: 0.75rem !important;
      opacity: 0.7 !important;
      background: white !important;
      padding: 0 4px !important;
      margin-left: 8px !important;
      border-radius: 4px !important;
    }
    
    .v-field__prepend-inner {
      padding-top: 20px !important;
      align-self: flex-start !important;
    }
    
    .v-field__append-inner {
      padding-top: 20px !important;
      align-self: flex-start !important;
    }
    
    &:hover {
      border-color: rgba(var(--v-theme-outline), 0.2) !important;
      box-shadow: none !important;
    }
  }
}

.lock-icon {
  color: rgba(var(--v-theme-on-surface), 0.4);
}

.action-section {
  margin-top: 2rem;
  padding-top: 2rem;
  border-top: 1px solid rgba(var(--v-theme-outline), 0.08);
}

.action-buttons {
  display: flex;
  gap: 1rem;
  justify-content: center;
  flex-wrap: wrap;
  margin-bottom: 1rem;
}

.action-btn {
  border-radius: 12px;
  font-weight: 500;
  letter-spacing: 0.025em;
  padding: 0.75rem 2rem;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  
  &:hover {
    transform: translateY(-2px);
  }
  
  &.primary-btn {
    background: linear-gradient(135deg, 
      rgb(var(--v-theme-primary)) 0%, 
      rgba(var(--v-theme-primary), 0.8) 100%
    );
    box-shadow: 0 4px 16px rgba(var(--v-theme-primary), 0.3);
    
    &:hover {
      box-shadow: 0 8px 24px rgba(var(--v-theme-primary), 0.4);
    }
    
    &:disabled {
      background: rgba(var(--v-theme-on-surface), 0.12);
      box-shadow: none;
    }
  }
  
  &.secondary-btn {
    border: 2px solid rgba(var(--v-theme-outline), 0.2);
    background: rgba(var(--v-theme-surface), 0.8);
    
    &:hover {
      border-color: rgb(var(--v-theme-primary));
      background: rgba(var(--v-theme-primary), 0.05);
      box-shadow: 0 4px 16px rgba(var(--v-theme-primary), 0.1);
    }
  }
}

.changes-indicator {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  padding: 0.75rem 1rem;
  background: rgba(var(--v-theme-warning), 0.1);
  border: 1px solid rgba(var(--v-theme-warning), 0.2);
  border-radius: 12px;
  font-size: 0.875rem;
  font-weight: 500;
  color: rgb(var(--v-theme-warning));
}

.changes-dot {
  animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

@keyframes pulse {
  0%, 100% {
    opacity: 1;
  }
  50% {
    opacity: 0.5;
  }
}

// Responsive Design
@media (max-width: 768px) {
  .profile-info-title {
    padding: 1.5rem 1.5rem 1rem;
    flex-direction: column;
    gap: 1rem;
    align-items: flex-start;
  }
  
  .title-content {
    flex-direction: column;
    text-align: center;
    gap: 0.75rem;
    width: 100%;
  }
  
  .edit-btn {
    align-self: center;
  }
  
  .profile-info-content {
    padding: 1.5rem;
  }
  
  .form-section {
    padding: 1rem;
  }
  
  .action-buttons {
    flex-direction: column;
    align-items: center;
  }
  
  .action-btn {
    width: 100%;
    max-width: 280px;
  }
}

@media (max-width: 480px) {
  .profile-info-title,
  .profile-info-content {
    padding: 1rem;
  }
  
  .title-text {
    font-size: 1.25rem;
  }
  
  .form-section {
    padding: 0.75rem;
  }
  
  .section-title {
    font-size: 0.9rem;
    gap: 0.5rem;
    
    .v-icon {
      padding: 0.375rem;
    }
  }
}

// Dark mode adjustments
@media (prefers-color-scheme: dark) {
  .profile-info-card {
    background: white;
    border-color: rgba(var(--v-theme-outline), 0.12);
  }
  
  .form-section {
    background: transparent;
    border-color: rgba(var(--v-theme-outline), 0.08);
    
    &:hover {
      background: transparent;
    }
    
    &.readonly-section {
      background: transparent;

      &:hover {
        background: transparent;
      }
    }
  }
}

// New readonly field container styling
.readonly-field-container {
  padding: 16px;
  background: rgba(var(--v-theme-surface-variant), 0.1);
  border-radius: 12px;
  border: 1px solid rgba(var(--v-theme-outline), 0.2);

  .readonly-field-label {
    display: block;
    font-size: 0.75rem;
    font-weight: 500;
    color: rgba(var(--v-theme-on-surface), 0.6);
    margin-bottom: 8px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  .readonly-field-input {
    :deep(.v-field__input) {
      color: rgba(var(--v-theme-on-surface), 0.8);
      font-weight: 500;
      padding: 0;
      min-height: auto;
    }

    :deep(.v-field) {
      background: transparent;
      box-shadow: none;
    }
  }
}
</style>
