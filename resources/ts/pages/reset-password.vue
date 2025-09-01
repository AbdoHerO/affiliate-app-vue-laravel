<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter, useRoute } from 'vue-router'
import { useGenerateImageVariant } from '@core/composable/useGenerateImageVariant'
import authV2LoginIllustrationBorderedDark from '@images/pages/auth-v2-login-illustration-bordered-dark.png'
import authV2LoginIllustrationBorderedLight from '@images/pages/auth-v2-login-illustration-bordered-light.png'
import authV2LoginIllustrationDark from '@images/pages/auth-v2-login-illustration-dark.png'
import authV2LoginIllustrationLight from '@images/pages/auth-v2-login-illustration-light.png'
import authV2MaskDark from '@images/pages/misc-mask-dark.png'
import authV2MaskLight from '@images/pages/misc-mask-light.png'
import { VNodeRenderer } from '@layouts/components/VNodeRenderer'
import { themeConfig } from '@themeConfig'
import { useNotifications } from '@/composables/useNotifications'
import { useFormErrors } from '@/composables/useFormErrors'
import axios from '@/plugins/axios'

definePage({
  meta: {
    layout: 'blank',
    public: true,
  },
})

const { t } = useI18n()
const router = useRouter()
const route = useRoute()
const { showSuccess, showError } = useNotifications()
const { errors, set: setErrors, clear: clearErrors } = useFormErrors()

// Helper function to get error messages
const getError = (field: string) => errors[field] || []

const form = ref({
  email: '',
  password: '',
  password_confirmation: '',
  token: '',
})

const isLoading = ref(false)
const isValidating = ref(true)
const isValidToken = ref(false)
const isPasswordVisible = ref(false)
const isPasswordConfirmationVisible = ref(false)
const isResetSuccessful = ref(false)

// Validate token on mount
onMounted(async () => {
  const token = route.query.token as string
  const email = route.query.email as string

  if (!token || !email) {
    showError(t('reset_password_invalid_token'))
    router.push({ name: 'login' })
    return
  }

  form.value.token = token
  form.value.email = email

  try {
    const response = await axios.post('/auth/validate-reset-token', {
      token: token,
      email: email,
    })

    if (response.data.success) {
      isValidToken.value = true
    } else {
      showError(t('reset_password_invalid_token'))
      router.push({ name: 'forgot-password' })
    }
  } catch (error: any) {
    showError(t('reset_password_invalid_token'))
    router.push({ name: 'forgot-password' })
  } finally {
    isValidating.value = false
  }
})

const handleSubmit = async () => {
  clearErrors()
  isLoading.value = true

  try {
    const response = await axios.post('/auth/reset-password', {
      email: form.value.email,
      password: form.value.password,
      password_confirmation: form.value.password_confirmation,
      token: form.value.token,
    })

    if (response.data.success) {
      isResetSuccessful.value = true
      showSuccess(t('reset_password_success'))
      
      // Redirect to login after 3 seconds
      setTimeout(() => {
        router.push({ name: 'login' })
      }, 3000)
    } else {
      showError(response.data.message || t('reset_password_error'))
    }
  } catch (error: any) {
    if (error.response?.status === 422 && error.response?.data?.errors) {
      setErrors(error.response.data.errors)
    } else {
      showError(error.response?.data?.message || t('reset_password_error'))
    }
  } finally {
    isLoading.value = false
  }
}

const goBackToLogin = () => {
  router.push({ name: 'login' })
}

// Generate theme-aware images
const authThemeImg = useGenerateImageVariant(
  authV2LoginIllustrationLight,
  authV2LoginIllustrationDark,
  authV2LoginIllustrationBorderedLight,
  authV2LoginIllustrationBorderedDark,
  true
)

const authThemeMask = useGenerateImageVariant(authV2MaskLight, authV2MaskDark)
</script>

<template>
  <VRow
    no-gutters
    class="auth-wrapper bg-surface"
  >
    <VCol
      cols="12"
      lg="8"
      class="d-none d-lg-flex"
    >
      <div class="position-relative bg-background w-100 me-0">
        <div
          class="d-flex align-center justify-center w-100 h-100"
          style="padding-inline: 150px;"
        >
          <VImg
            max-width="500"
            :src="authThemeImg"
            class="auth-illustration mt-16 mb-2"
          />
        </div>

        <img
          class="auth-footer-mask"
          :src="authThemeMask"
          alt="auth-footer-mask"
          height="280"
          width="100"
        >
      </div>
    </VCol>

    <VCol
      cols="12"
      lg="4"
      class="auth-card-v2 d-flex align-center justify-center"
    >
      <VCard
        flat
        :max-width="500"
        class="mt-12 mt-sm-0 pa-6"
      >
        <VCardText>
          <VNodeRenderer
            :nodes="themeConfig.app.logo"
            class="mb-6"
          />

          <!-- Loading state -->
          <div v-if="isValidating">
            <h4 class="text-h4 mb-1">
              {{ t('reset_password_title') }}
            </h4>
            <p class="mb-0">
              Validating reset token...
            </p>
            <div class="text-center mt-4">
              <VProgressCircular
                indeterminate
                color="primary"
              />
            </div>
          </div>

          <!-- Valid token - show form -->
          <div v-else-if="isValidToken && !isResetSuccessful">
            <h4 class="text-h4 mb-1">
              {{ t('reset_password_title') }}
            </h4>
            <p class="mb-0">
              {{ t('reset_password_subtitle') }}
            </p>
          </div>

          <!-- Success state -->
          <div v-else-if="isResetSuccessful">
            <h4 class="text-h4 mb-1 text-success">
              ✅ {{ t('reset_password_success') }}
            </h4>
            <p class="mb-0">
              You will be redirected to login page in a few seconds...
            </p>
          </div>
        </VCardText>

        <!-- Reset Password Form -->
        <VCardText v-if="isValidToken && !isResetSuccessful && !isValidating">
          <VForm @submit.prevent="handleSubmit">
            <VRow>
              <!-- New Password -->
              <VCol cols="12">
                <AppTextField
                  v-model="form.password"
                  :label="t('reset_password_new_password')"
                  :placeholder="t('password_placeholder')"
                  :type="isPasswordVisible ? 'text' : 'password'"
                  autocomplete="new-password"
                  :append-inner-icon="isPasswordVisible ? 'tabler-eye-off' : 'tabler-eye'"
                  :error-messages="getError('password')"
                  @click:append-inner="isPasswordVisible = !isPasswordVisible"
                />
              </VCol>

              <!-- Confirm Password -->
              <VCol cols="12">
                <AppTextField
                  v-model="form.password_confirmation"
                  :label="t('reset_password_confirm_password')"
                  :placeholder="t('password_placeholder')"
                  :type="isPasswordConfirmationVisible ? 'text' : 'password'"
                  autocomplete="new-password"
                  :append-inner-icon="isPasswordConfirmationVisible ? 'tabler-eye-off' : 'tabler-eye'"
                  :error-messages="getError('password_confirmation')"
                  @click:append-inner="isPasswordConfirmationVisible = !isPasswordConfirmationVisible"
                />
              </VCol>

              <!-- Reset Password Button -->
              <VCol cols="12">
                <VBtn
                  block
                  type="submit"
                  :loading="isLoading"
                  :disabled="isLoading"
                >
                  {{ t('reset_password_button') }}
                </VBtn>
              </VCol>

              <!-- Back to login -->
              <VCol cols="12">
                <RouterLink
                  class="d-flex align-center justify-center"
                  :to="{ name: 'login' }"
                >
                  <VIcon
                    icon="tabler-chevron-left"
                    size="20"
                    class="me-1 flip-in-rtl"
                  />
                  <span>{{ t('forgot_password_back_to_login') }}</span>
                </RouterLink>
              </VCol>
            </VRow>
          </VForm>
        </VCardText>

        <!-- Success state actions -->
        <VCardText v-if="isResetSuccessful">
          <VRow>
            <!-- Success message -->
            <VCol cols="12">
              <VAlert
                type="success"
                variant="tonal"
                class="mb-4"
              >
                <div class="text-body-2">
                  <strong>{{ t('reset_password_success') }}</strong><br>
                  You can now login with your new password.
                </div>
              </VAlert>
            </VCol>

            <!-- Go to login -->
            <VCol cols="12">
              <VBtn
                block
                @click="goBackToLogin"
              >
                {{ t('forgot_password_back_to_login') }}
              </VBtn>
            </VCol>
          </VRow>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>

<style lang="scss">
@use "@core-scss/template/pages/page-auth.scss";

// Responsive image sizing
.auth-illustration {
  width: 100%;
  height: auto;
  max-width: 500px;

  @media (min-width: 1280px) {
    max-width: 700px;
  }
}

@media (min-width: 1280px) {
  .auth-card-v2 .v-card {
    max-width: 700px !important;
    padding: 3rem !important;
  }

  .auth-illustration {
    max-width: 700px;
  }
}
</style>
