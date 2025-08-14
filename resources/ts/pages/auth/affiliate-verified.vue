<script setup lang="ts">
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

definePage({
  meta: {
    layout: 'blank',
    public: true,
  },
})

const { t } = useI18n()
const route = useRoute()

const isAlreadyVerified = computed(() => route.query.already_verified === '1')

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
      md="8"
      class="d-none d-md-flex"
    >
      <div class="position-relative bg-background w-100 me-0">
        <div
          class="d-flex align-center justify-center w-100 h-100"
          style="padding-inline: 150px;"
        >
          <VImg
            max-width="613"
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
      md="4"
      class="auth-card-v2 d-flex align-center justify-center"
    >
      <VCard
        flat
        :max-width="500"
        class="mt-12 mt-sm-0 pa-4 text-center"
      >
        <VCardText>
          <VNodeRenderer
            :nodes="themeConfig.app.logo"
            class="mb-6"
          />

          <div v-if="isAlreadyVerified">
            <VIcon
              icon="tabler-check-circle"
              size="64"
              color="warning"
              class="mb-4"
            />
            
            <h4 class="text-h4 mb-1 text-warning">
              Email déjà vérifié
            </h4>
            <p class="mb-4">
              Votre adresse email a déjà été vérifiée. Votre demande d'affiliation est en cours d'examen par notre équipe.
            </p>
          </div>

          <div v-else>
            <VIcon
              icon="tabler-check-circle"
              size="64"
              color="success"
              class="mb-4"
            />
            
            <h4 class="text-h4 mb-1 text-success">
              ✅ Email vérifié avec succès !
            </h4>
            <p class="mb-4">
              Félicitations ! Votre adresse email a été vérifiée avec succès.
            </p>
          </div>

          <VAlert
            type="info"
            variant="tonal"
            class="mb-6 text-start"
          >
            <h6 class="text-h6 mb-2">
              Prochaines étapes :
            </h6>
            <ol class="ps-4">
              <li class="mb-1">
                <strong>Examen de votre demande :</strong> Notre équipe va examiner votre candidature
              </li>
              <li class="mb-1">
                <strong>Notification par email :</strong> Vous recevrez un email avec la décision
              </li>
              <li class="mb-1">
                <strong>Accès à la plateforme :</strong> Si approuvé, vous recevrez vos identifiants de connexion
              </li>
            </ol>
          </VAlert>

          <VAlert
            type="warning"
            variant="tonal"
            class="mb-6 text-start"
          >
            <h6 class="text-h6 mb-2">
              ⏱️ Délai d'examen
            </h6>
            <p class="mb-0">
              L'examen des demandes d'affiliation prend généralement <strong>24 à 48 heures</strong>. 
              Nous vous contacterons dès que votre demande aura été traitée.
            </p>
          </VAlert>

          <div class="d-flex flex-column gap-4">
            <VBtn
              color="primary"
              variant="flat"
              size="large"
              :to="{ name: 'login' }"
            >
              Retour à la connexion
            </VBtn>
            
            <VBtn
              variant="outlined"
              size="large"
              :to="{ name: 'auth-affiliate-signup' }"
            >
              Nouvelle inscription
            </VBtn>
          </div>

          <VDivider class="my-6" />

          <div class="text-body-2 text-medium-emphasis">
            <p class="mb-2">
              <strong>Besoin d'aide ?</strong>
            </p>
            <p class="mb-0">
              Contactez notre équipe support à 
              <a href="mailto:support@affilio.com" class="text-primary">support@affilio.com</a>
            </p>
          </div>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>

<style lang="scss">
@use "@core-scss/template/pages/page-auth";
</style>
