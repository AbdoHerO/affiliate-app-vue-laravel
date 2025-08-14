<script setup lang="ts">
import AuthProvider from '@/views/pages/authentication/AuthProvider.vue'
import { useGenerateImageVariant } from '@core/composable/useGenerateImageVariant'
import { useI18n } from 'vue-i18n'
import authV2LoginIllustrationBorderedDark from '@images/pages/auth-v2-login-illustration-bordered-dark.png'
import authV2LoginIllustrationBorderedLight from '@images/pages/auth-v2-login-illustration-bordered-light.png'
import authV2LoginIllustrationDark from '@images/pages/auth-v2-login-illustration-dark.png'
import authV2LoginIllustrationLight from '@images/pages/auth-v2-login-illustration-light.png'
import authV2MaskDark from '@images/pages/misc-mask-dark.png'
import authV2MaskLight from '@images/pages/misc-mask-light.png'
import { VNodeRenderer } from '@layouts/components/VNodeRenderer'
import { themeConfig } from '@themeConfig'
import { useAuth } from '@/composables/useAuth'

definePage({
  meta: {
    layout: 'blank',
    public: true,
  },
})

const { login, isLoading, error } = useAuth()
const { t } = useI18n()

const form = ref({
  email: 'admin@cod.test', // Pre-fill for testing
  password: 'password',    // Pre-fill for testing
  remember: false,
})

const isPasswordVisible = ref(false)
const loginError = ref('')
const showAccountTypeMenu = ref(false)

const handleLogin = async () => {
  loginError.value = ''

  try {
    await login({
      email: form.value.email,
      password: form.value.password,
    })
  } catch (err) {
    loginError.value = err instanceof Error ? err.message : t('error_login_failed')
  }
}

const authThemeImg = useGenerateImageVariant(
  authV2LoginIllustrationLight,
  authV2LoginIllustrationDark,
  authV2LoginIllustrationBorderedLight,
  authV2LoginIllustrationBorderedDark,
  true)

const authThemeMask = useGenerateImageVariant(authV2MaskLight, authV2MaskDark)
</script>

<template>
  <a href="javascript:void(0)">
    <div class="auth-logo d-flex align-center gap-x-3">
      <VNodeRenderer :nodes="themeConfig.app.logo" />
      <h1 class="auth-title">
        {{ themeConfig.app.title }}
      </h1>
    </div>
  </a>

  <VRow
    no-gutters
    class="auth-wrapper bg-surface"
  >
    <VCol
      md="8"
      class="d-none d-md-flex"
    >
      <div class="position-relative bg-background w-100 me-0">
        <div
          class="d-flex align-center justify-center w-100 h-100"
          style="padding-inline: 6.25rem;"
        >
          <VImg
            max-width="613"
            :src="authThemeImg"
            class="auth-illustration mt-16 mb-2"
          />
        </div>

        <img
          class="auth-footer-mask flip-in-rtl"
          :src="authThemeMask"
          alt="auth-footer-mask"
          height="280"
          width="100"
        >
      </div>
    </VCol>

    <VCol
      cols="12"
      md="4"
      class="auth-card-v2 d-flex align-center justify-center"
    >
      <VCard
        flat
        :max-width="500"
        class="mt-12 mt-sm-0 pa-6"
      >
        <VCardText>
          <h4 class="text-h4 mb-1">
            {{ t('login_title', { title: themeConfig.app.title }) }}
          </h4>
          <p class="mb-0">
            {{ t('login_subtitle') }}
          </p>
        </VCardText>
        <VCardText>
          <!-- Error Alert -->
          <VAlert
            v-if="loginError"
            type="error"
            class="mb-4"
            closable
            @click:close="loginError = ''"
          >
            {{ loginError }}
          </VAlert>

          <!-- Demo Credentials Info -->
          <VAlert
            type="info"
            class="mb-4"
            variant="tonal"
          >
            <div class="text-body-2">
              <strong>{{ t('demo_credentials') }}</strong><br>
              <strong>{{ t('admin_credentials') }}</strong><br>
              <strong>{{ t('affiliate_credentials') }}</strong>
            </div>
          </VAlert>

          <VForm @submit.prevent="handleLogin">
            <VRow>
              <!-- email -->
              <VCol cols="12">
                <AppTextField
                  v-model="form.email"
                  autofocus
                  :label="t('placeholder_email_or_username')"
                  type="email"
                  :placeholder="t('placeholder_enter_email')"
                />
              </VCol>

              <!-- password -->
              <VCol cols="12">
                <AppTextField
                  v-model="form.password"
                  :label="t('form_password')"
                  :placeholder="t('placeholder_enter_password')"
                  :type="isPasswordVisible ? 'text' : 'password'"
                  autocomplete="password"
                  :append-inner-icon="isPasswordVisible ? 'tabler-eye-off' : 'tabler-eye'"
                  @click:append-inner="isPasswordVisible = !isPasswordVisible"
                />

                <div class="d-flex align-center flex-wrap justify-space-between my-6">
                  <VCheckbox
                    v-model="form.remember"
                    :label="t('remember_me')"
                  />
                  <a
                    class="text-primary"
                    href="javascript:void(0)"
                  >
                    {{ t('forgot_password') }}
                  </a>
                </div>

                <VBtn
                  block
                  type="submit"
                  :loading="isLoading"
                  :disabled="isLoading"
                >
                  {{ t('action_login') }}
                </VBtn>
              </VCol>

              <!-- create account -->
              <VCol
                cols="12"
                class="text-body-1 text-center"
              >
                <span class="d-inline-block">
                  {{ t('new_on_platform') }}
                </span>

                <VMenu
                  v-model="showAccountTypeMenu"
                  :close-on-content-click="false"
                  location="top"
                >
                  <template #activator="{ props }">
                    <a
                      class="text-primary ms-1 d-inline-block text-body-1"
                      href="javascript:void(0)"
                      v-bind="props"
                      @click="showAccountTypeMenu = !showAccountTypeMenu"
                    >
                      {{ t('create_account') }}
                      <VIcon
                        icon="tabler-chevron-up"
                        size="16"
                        class="ms-1"
                      />
                    </a>
                  </template>

                  <VCard
                    min-width="280"
                    class="mt-2"
                  >
                    <VCardText class="pa-4">
                      <h6 class="text-h6 mb-3">
                        🚀 Choisissez votre type de compte
                      </h6>

                      <VList class="pa-0">
                        <VListItem
                          :to="{ name: 'affiliate-signup' }"
                          class="pa-3 rounded mb-2"
                          style="border: 1px solid rgba(var(--v-theme-primary), 0.2);"
                        >
                          <template #prepend>
                            <VIcon
                              icon="tabler-users"
                              color="primary"
                              size="24"
                            />
                          </template>

                          <VListItemTitle class="text-primary font-weight-medium">
                            Compte Affilié
                          </VListItemTitle>
                          <VListItemSubtitle class="text-sm">
                            Rejoignez notre réseau d'affiliés et commencez à gagner des commissions
                          </VListItemSubtitle>
                        </VListItem>

                        <VListItem
                          href="javascript:void(0)"
                          class="pa-3 rounded"
                          style="border: 1px solid rgba(var(--v-theme-surface-variant), 0.3);"
                          disabled
                        >
                          <template #prepend>
                            <VIcon
                              icon="tabler-building-store"
                              color="surface-variant"
                              size="24"
                            />
                          </template>

                          <VListItemTitle class="text-disabled font-weight-medium">
                            Compte Marchand
                          </VListItemTitle>
                          <VListItemSubtitle class="text-sm text-disabled">
                            Bientôt disponible - Vendez vos produits sur notre plateforme
                          </VListItemSubtitle>
                        </VListItem>
                      </VList>

                      <VDivider class="my-3" />

                      <div class="text-center">
                        <VBtn
                          variant="text"
                          size="small"
                          @click="showAccountTypeMenu = false"
                        >
                          Fermer
                        </VBtn>
                      </div>
                    </VCardText>
                  </VCard>
                </VMenu>
              </VCol>

              <VCol
                cols="12"
                class="d-flex align-center"
              >
                <VDivider />
                <span class="mx-4">{{ t('or') }}</span>
                <VDivider />
              </VCol>

              <!-- affiliate signup promotion -->
              <VCol
                cols="12"
                class="text-center"
              >
                <VAlert
                  type="info"
                  variant="tonal"
                  class="mb-4"
                >
                  <div class="d-flex align-center">
                    <VIcon
                      icon="tabler-users"
                      size="20"
                      class="me-2"
                    />
                    <div class="flex-grow-1 text-start">
                      <div class="text-sm font-weight-medium">
                        Vous voulez devenir affilié ?
                      </div>
                      <div class="text-xs">
                        Rejoignez notre réseau et commencez à gagner des commissions
                      </div>
                    </div>
                    <VBtn
                      :to="{ name: 'affiliate-signup' }"
                      color="primary"
                      variant="flat"
                      size="small"
                      class="ms-3"
                    >
                      S'inscrire
                    </VBtn>
                  </div>
                </VAlert>
              </VCol>

              <!-- auth providers -->
              <VCol
                cols="12"
                class="text-center"
              >
                <AuthProvider />
              </VCol>
            </VRow>
          </VForm>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>

<style lang="scss">
@use "@core-scss/template/pages/page-auth";
</style>
