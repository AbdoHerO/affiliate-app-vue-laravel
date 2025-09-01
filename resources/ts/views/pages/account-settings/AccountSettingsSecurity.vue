<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { useAuth } from '@/composables/useAuth'
import { useAuthStore } from '@/stores/auth'
import { useNotifications } from '@/composables/useNotifications'

const { t } = useI18n()
const { user } = useAuth()
const authStore = useAuthStore()
const { showSuccess, showError } = useNotifications()

// Password change form
const isCurrentPasswordVisible = ref(false)
const isNewPasswordVisible = ref(false)
const isConfirmPasswordVisible = ref(false)
const currentPassword = ref('')
const newPassword = ref('')
const confirmPassword = ref('')
const isChangingPassword = ref(false)

// Two-factor authentication
const isTwoFactorEnabled = ref(false)
const isEnabling2FA = ref(false)

// Recent devices (mock data for now)
const recentDevices = ref([
  {
    browser: 'Chrome on Windows',
    device: 'Desktop Computer',
    location: 'New York, NY',
    recentActivity: new Date().toLocaleDateString(),
    deviceIcon: { icon: 'tabler-brand-windows', color: 'primary' },
    isCurrent: true
  },
  {
    browser: 'Safari on iPhone',
    device: 'iPhone 14 Pro',
    location: 'Los Angeles, CA',
    recentActivity: new Date(Date.now() - 86400000).toLocaleDateString(),
    deviceIcon: { icon: 'tabler-device-mobile', color: 'error' },
    isCurrent: false
  }
])

// Password requirements
const passwordRequirements = [
  t('password_requirement_length'),
  t('password_requirement_lowercase'),
  t('password_requirement_number')
]

// Password validation
const passwordValidation = computed(() => {
  const requirements = {
    length: newPassword.value.length >= 8,
    lowercase: /[a-z]/.test(newPassword.value),
    number: /[0-9]/.test(newPassword.value)
  }
  
  return {
    isValid: Object.values(requirements).every(Boolean),
    requirements
  }
})

const passwordsMatch = computed(() => {
  return newPassword.value === confirmPassword.value
})

// Change password function
const changePassword = async () => {
  if (!passwordValidation.value.isValid || !passwordsMatch.value) {
    return
  }

  try {
    isChangingPassword.value = true

    const response = await fetch('/api/profile/password', {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${authStore.token}`,
        'Accept': 'application/json',
      },
      body: JSON.stringify({
        current_password: currentPassword.value,
        password: newPassword.value,
        password_confirmation: confirmPassword.value
      })
    })

    const result = await response.json()

    if (result.success) {
      // Reset form
      currentPassword.value = ''
      newPassword.value = ''
      confirmPassword.value = ''
      
      showSuccess(t('password_changed_successfully'))
    } else {
      throw new Error(result.message || t('password_change_failed'))
    }
  } catch (error) {
    console.error('Error changing password:', error)
    showError(t('password_change_failed'))
  } finally {
    isChangingPassword.value = false
  }
}

// Enable/disable 2FA
const toggle2FA = async () => {
  try {
    isEnabling2FA.value = true
    // This would be an API call to enable/disable 2FA
    await new Promise(resolve => setTimeout(resolve, 1000))
    isTwoFactorEnabled.value = !isTwoFactorEnabled.value
    showSuccess(isTwoFactorEnabled.value ? t('2fa_enabled') : t('2fa_disabled'))
  } catch (error) {
    console.error('Error toggling 2FA:', error)
  } finally {
    isEnabling2FA.value = false
  }
}

// Logout from device
const logoutFromDevice = (deviceIndex: number) => {
  if (recentDevices.value[deviceIndex].isCurrent) {
    showError(t('cannot_logout_current_device'))
    return
  }
  
  recentDevices.value.splice(deviceIndex, 1)
  showSuccess(t('device_logged_out'))
}
</script>

<template>
  <VRow>
    <!-- Change Password Section -->
    <VCol cols="12">
      <VCard :title="t('change_password')">
        <VForm @submit.prevent="changePassword">
          <VCardText class="pt-0">
            <!-- Current Password -->
            <VRow>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="currentPassword"
                  :type="isCurrentPasswordVisible ? 'text' : 'password'"
                  :append-inner-icon="isCurrentPasswordVisible ? 'tabler-eye-off' : 'tabler-eye'"
                  :label="t('current_password')"
                  :placeholder="t('placeholder_current_password')"
                  autocomplete="current-password"
                  @click:append-inner="isCurrentPasswordVisible = !isCurrentPasswordVisible"
                />
              </VCol>
            </VRow>

            <!-- New Password -->
            <VRow>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="newPassword"
                  :type="isNewPasswordVisible ? 'text' : 'password'"
                  :append-inner-icon="isNewPasswordVisible ? 'tabler-eye-off' : 'tabler-eye'"
                  :label="t('new_password')"
                  :placeholder="t('placeholder_new_password')"
                  autocomplete="new-password"
                  @click:append-inner="isNewPasswordVisible = !isNewPasswordVisible"
                />
              </VCol>

              <VCol cols="12" md="6">
                <VTextField
                  v-model="confirmPassword"
                  :type="isConfirmPasswordVisible ? 'text' : 'password'"
                  :append-inner-icon="isConfirmPasswordVisible ? 'tabler-eye-off' : 'tabler-eye'"
                  :label="t('confirm_password')"
                  :placeholder="t('placeholder_confirm_password')"
                  autocomplete="new-password"
                  :error="confirmPassword && !passwordsMatch"
                  :error-messages="confirmPassword && !passwordsMatch ? [t('passwords_do_not_match')] : []"
                  @click:append-inner="isConfirmPasswordVisible = !isConfirmPasswordVisible"
                />
              </VCol>
            </VRow>

            <!-- Password Requirements -->
            <div class="mt-4">
              <p class="text-body-2 mb-2">{{ t('password_requirements') }}:</p>
              <ul class="text-body-2">
                <li 
                  v-for="(requirement, index) in passwordRequirements"
                  :key="index"
                  :class="newPassword && Object.values(passwordValidation.requirements)[index] ? 'text-success' : 'text-disabled'"
                >
                  <VIcon 
                    :icon="newPassword && Object.values(passwordValidation.requirements)[index] ? 'tabler-check' : 'tabler-circle'"
                    size="14"
                    class="me-2"
                  />
                  {{ requirement }}
                </li>
              </ul>
            </div>
          </VCardText>

          <VCardActions>
            <VBtn
              type="submit"
              :loading="isChangingPassword"
              :disabled="!passwordValidation.isValid || !passwordsMatch || !currentPassword"
            >
              {{ t('change_password') }}
            </VBtn>
          </VCardActions>
        </VForm>
      </VCard>
    </VCol>

    <!-- Two-Factor Authentication -->
    <VCol cols="12">
      <VCard :title="t('two_factor_authentication')">
        <VCardText>
          <div class="d-flex justify-space-between align-center">
            <div>
              <h6 class="text-h6 mb-1">{{ t('two_factor_authentication') }}</h6>
              <p class="text-body-2 mb-0">
                {{ t('2fa_description') }}
              </p>
            </div>
            
            <VSwitch
              v-model="isTwoFactorEnabled"
              :loading="isEnabling2FA"
              @change="toggle2FA"
            />
          </div>
        </VCardText>
      </VCard>
    </VCol>

    <!-- Recent Devices -->
    <VCol cols="12">
      <VCard :title="t('recent_devices')">
        <VDataTable
          :headers="[
            { title: t('browser'), key: 'browser' },
            { title: t('device'), key: 'device' },
            { title: t('location'), key: 'location' },
            { title: t('recent_activity'), key: 'recentActivity' },
            { title: t('actions'), key: 'actions', sortable: false }
          ]"
          :items="recentDevices"
          class="text-no-wrap"
        >
          <template #item.browser="{ item }">
            <div class="d-flex align-center gap-x-3">
              <VIcon
                :icon="item.deviceIcon.icon"
                :color="item.deviceIcon.color"
                size="22"
              />
              <div>
                <h6 class="text-h6">{{ item.browser }}</h6>
              </div>
            </div>
          </template>

          <template #item.device="{ item }">
            <div class="d-flex align-center gap-2">
              <span>{{ item.device }}</span>
              <VChip
                v-if="item.isCurrent"
                color="success"
                size="x-small"
              >
                {{ t('current') }}
              </VChip>
            </div>
          </template>

          <template #item.actions="{ item, index }">
            <VBtn
              v-if="!item.isCurrent"
              icon
              size="small"
              color="error"
              variant="text"
              @click="logoutFromDevice(index)"
            >
              <VIcon icon="tabler-logout" />
              <VTooltip activator="parent">
                {{ t('logout_device') }}
              </VTooltip>
            </VBtn>
          </template>
        </VDataTable>
      </VCard>
    </VCol>
  </VRow>
</template>
