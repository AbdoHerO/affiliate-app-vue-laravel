<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
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
const { showSuccess, showError } = useNotifications()
const { errors, set: setErrors, clear: clearErrors } = useFormErrors()

// Helper function to get error messages
const getError = (field: string) => errors[field] || []

const form = ref({
  email: '',
})

const isLoading = ref(false)
const isSubmitted = ref(false)

const handleSubmit = async () => {
  clearErrors()
  isLoading.value = true

  // Basic validation
  if (!form.value.email || !form.value.email.trim()) {
    showError(t('forgot_password_email_required') || 'Email is required')
    isLoading.value = false
    return
  }

  try {
    const response = await axios.post('/auth/forgot-password', {
      email: form.value.email,
    })

    if (response.data.success) {
      isSubmitted.value = true
      showSuccess(t('forgot_password_success'))
    } else {
      showError(response.data.message || t('forgot_password_error'))
    }
  } catch (error: any) {
    if (error.response?.status === 422 && error.response?.data?.errors) {
      setErrors(error.response.data.errors)
    } else {
      showError(error.response?.data?.message || t('forgot_password_error'))
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

          <div v-if="!isSubmitted">
            <h4 class="text-h4 mb-1">
              {{ t('forgot_password_title') }}
            </h4>
            <p class="mb-0">
              {{ t('forgot_password_subtitle') }}
            </p>
          </div>

          <div v-else>
            <h4 class="text-h4 mb-1 text-success">
              ✉️ {{ t('forgot_password_success') }}
            </h4>
            <p class="mb-0">
              {{ t('forgot_password_subtitle') }}
            </p>
          </div>
        </VCardText>

        <VCardText v-if="!isSubmitted">
          <VForm @submit.prevent="handleSubmit">
            <VRow>
              <!-- Email -->
              <VCol cols="12">
                <AppTextField
                  v-model="form.email"
                  autofocus
                  :label="t('forgot_password_email_label')"
                  type="email"
                  :placeholder="t('forgot_password_email_placeholder')"
                  :error-messages="getError('email')"
                />
              </VCol>

              <!-- Send Reset Link -->
              <VCol cols="12">
                <VBtn
                  block
                  type="submit"
                  :loading="isLoading"
                  :disabled="isLoading"
                >
                  {{ t('forgot_password_send_button') }}
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

        <VCardText v-else>
          <VRow>
            <!-- Success message -->
            <VCol cols="12">
              <VAlert
                type="success"
                variant="tonal"
                class="mb-4"
              >
                <div class="text-body-2">
                  <strong>{{ t('forgot_password_success') }}</strong><br>
                  {{ t('forgot_password_subtitle') }}
                </div>
              </VAlert>
            </VCol>

            <!-- Back to login -->
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
