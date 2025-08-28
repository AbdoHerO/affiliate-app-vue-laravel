<script setup lang="ts">
import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useApi } from '@/composables/useApi'
import { useNotifications } from '@/composables/useNotifications'

const { t } = useI18n()
const { showSuccess, showError } = useNotifications()

// Form state
const loading = ref(false)
const showCurrentPassword = ref(false)
const showNewPassword = ref(false)
const showConfirmPassword = ref(false)

// Form data
const form = ref({
  current_password: '',
  password: '',
  password_confirmation: ''
})

// Validation errors
const errors = ref<Record<string, string[]>>({})

// Reset form
const resetForm = () => {
  form.value = {
    current_password: '',
    password: '',
    password_confirmation: ''
  }
  errors.value = {}
}

// Change password
const changePassword = async () => {
  try {
    loading.value = true
    errors.value = {}

    const response = await fetch('/api/profile/password', {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
        'Accept': 'application/json',
      },
      body: JSON.stringify(form.value)
    })

    if (!response.ok) {
      const errorData = await response.json()
      if (response.status === 422 && errorData.errors) {
        errors.value = errorData.errors
      } else {
        showError(errorData.message || t('password_update_failed'))
      }
      return
    }

    const data = await response.json()
    if (data.success) {
      showSuccess(data.message || t('password_updated_successfully'))
      resetForm()
    }
  } catch (err: any) {
    showError(err.message || t('password_update_failed'))
  } finally {
    loading.value = false
  }
}

// Computed properties
const canSubmit = computed(() => {
  return (
    form.value.current_password.trim() &&
    form.value.password.trim() &&
    form.value.password_confirmation.trim() &&
    form.value.password === form.value.password_confirmation &&
    form.value.password.length >= 8
  )
})

const passwordsMatch = computed(() => {
  if (!form.value.password_confirmation) return true
  return form.value.password === form.value.password_confirmation
})

const passwordStrength = computed(() => {
  const password = form.value.password
  if (!password) return { score: 0, text: '', color: '' }
  
  let score = 0
  if (password.length >= 8) score++
  if (/[a-z]/.test(password)) score++
  if (/[A-Z]/.test(password)) score++
  if (/[0-9]/.test(password)) score++
  if (/[^A-Za-z0-9]/.test(password)) score++
  
  const levels = [
    { text: t('password_very_weak'), color: 'error' },
    { text: t('password_weak'), color: 'warning' },
    { text: t('password_fair'), color: 'info' },
    { text: t('password_good'), color: 'success' },
    { text: t('password_strong'), color: 'success' }
  ]
  
  return { score, ...levels[Math.min(score, 4)] }
})

// Helper function for strength icons
const getStrengthIcon = (score: number) => {
  const icons = [
    'tabler-shield-x',
    'tabler-shield-exclamation', 
    'tabler-shield-half-filled',
    'tabler-shield-check',
    'tabler-shield-star'
  ]
  return icons[Math.min(score, 4)]
}
</script>

<template>
  <VCard class="password-card">
    <VCardTitle class="password-card-title">
      <div class="title-content">
        <VIcon icon="tabler-shield-lock" size="28" class="title-icon" />
        <div>
          <h3 class="title-text">{{ t('change_password') }}</h3>
          <p class="title-subtitle">{{ t('update_your_password_securely') }}</p>
        </div>
      </div>
    </VCardTitle>

    <VCardText class="password-card-content">
      <!-- Security Info Alert -->
      <VAlert
        type="info"
        variant="tonal"
        class="security-alert"
        border="start"
        border-color="info"
      >
        <template #prepend>
          <VIcon icon="tabler-info-circle" />
        </template>
        <div class="alert-content">
          <h6 class="alert-title">{{ t('security_notice') }}</h6>
          <p class="alert-text">{{ t('password_change_info') }}</p>
        </div>
      </VAlert>

      <VForm @submit.prevent="changePassword" class="password-form">
        <div class="form-sections">
          <!-- Current Password Section -->
          <div class="form-section">
            <h6 class="section-title">{{ t('current_password_section') }}</h6>
            <VTextField
              v-model="form.current_password"
              :label="t('current_password')"
              :placeholder="t('enter_current_password')"
              :type="showCurrentPassword ? 'text' : 'password'"
              :append-inner-icon="showCurrentPassword ? 'tabler-eye-off' : 'tabler-eye'"
              :error-messages="errors.current_password"
              variant="outlined"
              class="password-field"
              required
              @click:append-inner="showCurrentPassword = !showCurrentPassword"
            >
              <template #prepend-inner>
                <VIcon icon="tabler-lock" size="20" class="field-icon" />
              </template>
            </VTextField>
          </div>

          <!-- New Password Section -->
          <div class="form-section">
            <h6 class="section-title">{{ t('new_password_section') }}</h6>
            
            <VTextField
              v-model="form.password"
              :label="t('new_password')"
              :placeholder="t('enter_new_password')"
              :type="showNewPassword ? 'text' : 'password'"
              :append-inner-icon="showNewPassword ? 'tabler-eye-off' : 'tabler-eye'"
              :error-messages="errors.password"
              variant="outlined"
              class="password-field"
              required
              @click:append-inner="showNewPassword = !showNewPassword"
            >
              <template #prepend-inner>
                <VIcon icon="tabler-key" size="20" class="field-icon" />
              </template>
            </VTextField>
            
            <!-- Password Strength Indicator -->
            <VExpandTransition>
              <div v-if="form.password" class="password-strength">
                <div class="strength-header">
                  <span class="strength-label">{{ t('password_strength') }}:</span>
                  <VChip
                    :color="passwordStrength.color"
                    size="small"
                    variant="flat"
                    class="strength-chip"
                  >
                    <VIcon 
                      start 
                      :icon="getStrengthIcon(passwordStrength.score)" 
                      size="14" 
                    />
                    {{ passwordStrength.text }}
                  </VChip>
                </div>
                <VProgressLinear
                  :model-value="(passwordStrength.score / 5) * 100"
                  :color="passwordStrength.color"
                  height="6"
                  rounded
                  class="strength-progress"
                />
                <div class="strength-tips">
                  <div class="tips-grid">
                    <div class="tip-item" :class="{ 'tip-valid': form.password.length >= 8 }">
                      <VIcon 
                        :icon="form.password.length >= 8 ? 'tabler-check' : 'tabler-x'" 
                        size="16" 
                        :color="form.password.length >= 8 ? 'success' : 'error'"
                      />
                      <span>{{ t('password_min_8_chars') }}</span>
                    </div>
                    <div class="tip-item" :class="{ 'tip-valid': /[a-z]/.test(form.password) && /[A-Z]/.test(form.password) }">
                      <VIcon 
                        :icon="/[a-z]/.test(form.password) && /[A-Z]/.test(form.password) ? 'tabler-check' : 'tabler-x'" 
                        size="16" 
                        :color="/[a-z]/.test(form.password) && /[A-Z]/.test(form.password) ? 'success' : 'error'"
                      />
                      <span>{{ t('password_mix_case') }}</span>
                    </div>
                    <div class="tip-item" :class="{ 'tip-valid': /[0-9]/.test(form.password) }">
                      <VIcon 
                        :icon="/[0-9]/.test(form.password) ? 'tabler-check' : 'tabler-x'" 
                        size="16" 
                        :color="/[0-9]/.test(form.password) ? 'success' : 'error'"
                      />
                      <span>{{ t('password_include_numbers') }}</span>
                    </div>
                    <div class="tip-item" :class="{ 'tip-valid': /[^A-Za-z0-9]/.test(form.password) }">
                      <VIcon 
                        :icon="/[^A-Za-z0-9]/.test(form.password) ? 'tabler-check' : 'tabler-x'" 
                        size="16" 
                        :color="/[^A-Za-z0-9]/.test(form.password) ? 'success' : 'error'"
                      />
                      <span>{{ t('password_include_symbols') }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </VExpandTransition>

            <!-- Confirm Password -->
            <VTextField
              v-model="form.password_confirmation"
              :label="t('confirm_new_password')"
              :placeholder="t('confirm_new_password')"
              :type="showConfirmPassword ? 'text' : 'password'"
              :append-inner-icon="showConfirmPassword ? 'tabler-eye-off' : 'tabler-eye'"
              :error-messages="errors.password_confirmation"
              :error="!passwordsMatch"
              variant="outlined"
              class="password-field mt-4"
              required
              @click:append-inner="showConfirmPassword = !showConfirmPassword"
            >
              <template #prepend-inner>
                <VIcon icon="tabler-shield-check" size="20" class="field-icon" />
              </template>
            </VTextField>
            
            <VExpandTransition>
              <VAlert
                v-if="form.password_confirmation && !passwordsMatch"
                type="error"
                variant="tonal"
                density="compact"
                class="mt-2"
              >
                <VIcon start icon="tabler-alert-circle" />
                {{ t('passwords_do_not_match') }}
              </VAlert>
            </VExpandTransition>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="action-section">
          <div class="action-buttons">
            <VBtn
              type="submit"
              color="primary"
              size="large"
              :loading="loading"
              :disabled="!canSubmit"
              class="action-btn primary-btn"
            >
              <VIcon start icon="tabler-check" />
              {{ t('update_password') }}
            </VBtn>

            <VBtn
              color="secondary"
              variant="outlined"
              size="large"
              :disabled="loading"
              class="action-btn secondary-btn"
              @click="resetForm"
            >
              <VIcon start icon="tabler-refresh" />
              {{ t('reset_form') }}
            </VBtn>
          </div>
        </div>
      </VForm>
    </VCardText>
  </VCard>
</template>

<style lang="scss" scoped>
.password-card {
  border-radius: 20px;
  background: white;
  backdrop-filter: none;
  border: 1px solid rgba(var(--v-theme-outline), 0.08);
  box-shadow: 0 8px 32px rgba(var(--v-theme-on-surface), 0.06);
  overflow: hidden;
}

.password-card-title {
  padding: 2rem 2rem 1rem;
  border-bottom: 1px solid rgba(var(--v-theme-outline), 0.08);
  background: transparent;
}

.title-content {
  display: flex;
  align-items: center;
  gap: 1rem;
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

.password-card-content {
  padding: 2rem;
}

.security-alert {
  margin-bottom: 2rem;
  border-radius: 16px;
  border-left-width: 4px;
}

.alert-content {
  .alert-title {
    font-size: 0.875rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: rgb(var(--v-theme-info));
  }
  
  .alert-text {
    font-size: 0.875rem;
    margin: 0;
    line-height: 1.5;
  }
}

.password-form {
  .form-sections {
    display: flex;
    flex-direction: column;
    gap: 2rem;
  }
  
  .form-section {
    background: rgba(var(--v-theme-surface-variant), 0.08);
    border-radius: 16px;
    padding: 1.5rem;
    border: 1px solid rgba(var(--v-theme-outline), 0.06);
  }
  
  .section-title {
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 1rem;
    color: rgb(var(--v-theme-on-surface));
    display: flex;
    align-items: center;
    gap: 0.5rem;
    
    &::before {
      content: '';
      width: 4px;
      height: 1.2rem;
      background: rgb(var(--v-theme-primary));
      border-radius: 2px;
    }
  }
}

.password-field {
  :deep(.v-field) {
    border-radius: 12px;
    background: rgba(var(--v-theme-surface), 0.8);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(var(--v-theme-outline), 0.12);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    
    &:hover {
      border-color: rgba(var(--v-theme-primary), 0.3);
    }
  }
  
  &.v-text-field--focused :deep(.v-field) {
    border-color: rgb(var(--v-theme-primary));
    box-shadow: 0 0 0 3px rgba(var(--v-theme-primary), 0.1);
  }
  
  .field-icon {
    color: rgba(var(--v-theme-on-surface), 0.5);
  }
}

.password-strength {
  margin-top: 1rem;
  padding: 1rem;
  background: rgba(var(--v-theme-surface), 0.4);
  border-radius: 12px;
  border: 1px solid rgba(var(--v-theme-outline), 0.08);
}

.strength-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.75rem;
}

.strength-label {
  font-size: 0.875rem;
  font-weight: 500;
  color: rgba(var(--v-theme-on-surface), 0.7);
}

.strength-chip {
  border-radius: 8px;
  font-weight: 500;
  font-size: 0.75rem;
}

.strength-progress {
  margin-bottom: 1rem;
  border-radius: 3px;
}

.strength-tips {
  .tips-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 0.75rem;
  }
  
  .tip-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.75rem;
    padding: 0.5rem;
    border-radius: 8px;
    background: rgba(var(--v-theme-surface-variant), 0.08);
    transition: all 0.3s ease;
    
    &.tip-valid {
      background: rgba(var(--v-theme-success), 0.1);
      color: rgb(var(--v-theme-success));
    }
    
    span {
      font-weight: 500;
    }
  }
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

// Responsive Design
@media (max-width: 768px) {
  .password-card-title,
  .password-card-content {
    padding: 1.5rem;
  }
  
  .title-content {
    flex-direction: column;
    text-align: center;
    gap: 0.75rem;
  }
  
  .form-section {
    padding: 1rem;
  }
  
  .strength-tips .tips-grid {
    grid-template-columns: 1fr;
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
  .password-card-title,
  .password-card-content {
    padding: 1rem;
  }
  
  .title-text {
    font-size: 1.25rem;
  }
  
  .strength-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.5rem;
  }
}

// Dark mode adjustments
@media (prefers-color-scheme: dark) {
  .password-card {
    background: white;
    border-color: rgba(var(--v-theme-outline), 0.12);
  }
  
  .form-section {
    background: transparent;
    border-color: rgba(var(--v-theme-outline), 0.08);
  }
  
  .password-strength {
    background: transparent;
    border-color: rgba(var(--v-theme-outline), 0.1);
  }
}
</style>
