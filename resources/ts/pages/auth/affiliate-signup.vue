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
import { useAffiliateSignupStore } from '@/stores/public/affiliateSignup'
import { useFormErrors } from '@/composables/useFormErrors'
import { useNotifications } from '@/composables/useNotifications'

definePage({
  meta: {
    layout: 'blank',
    public: true,
  },
})

const { t } = useI18n()
const affiliateStore = useAffiliateSignupStore()
const { showSuccess, showError } = useNotifications()
const { errors, set: setErrors, clear: clearErrors } = useFormErrors()

// Helper functions for error handling
const getError = (field: string) => errors[field] || []
const hasError = (field: string) => !!(errors[field] && errors[field].length > 0)

const form = ref({
  nom_complet: '',
  email: '',
  telephone: '',
  password: '',
  password_confirmation: '',
  adresse: '',
  ville: '',
  pays: 'Maroc',
  notes: '',
  accept_terms: false,
})

const isPasswordVisible = ref(false)
const isPasswordConfirmationVisible = ref(false)
const showSuccessScreen = ref(false)

const countries = [
  'Maroc',
  'France',
  'Algérie',
  'Tunisie',
  'Espagne',
  'Belgique',
  'Suisse',
  'Canada',
  'Autre'
]

const handleSubmit = async () => {
  clearErrors()
  
  try {
    const result = await affiliateStore.signup(form.value)
    
    if (result.success) {
      showSuccess(result.message)
      showSuccessScreen.value = true
    } else {
      if (result.errors) {
        setErrors(result.errors)
      } else {
        showError(result.message)
      }
    }
  } catch (error) {
    showError('Une erreur inattendue est survenue.')
  }
}

const handleResendVerification = async () => {
  if (!affiliateStore.lastSignupEmail) return
  
  try {
    const result = await affiliateStore.resendVerification(affiliateStore.lastSignupEmail)
    
    if (result.success) {
      showSuccess(result.message)
    } else {
      showError(result.message)
    }
  } catch (error) {
    showError('Une erreur est survenue lors du renvoi de l\'email.')
  }
}

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
        class="mt-12 mt-sm-0 pa-4"
      >
        <VCardText>
          <VNodeRenderer
            :nodes="themeConfig.app.logo"
            class="mb-6"
          />

          <div v-if="!showSuccessScreen">
            <!-- Back to login link -->
            <div class="mb-4">
              <VBtn
                :to="{ name: 'login' }"
                variant="text"
                size="small"
                prepend-icon="tabler-arrow-left"
                class="text-primary"
              >
                Retour à la connexion
              </VBtn>
            </div>

            <h4 class="text-h4 mb-1">
              🚀 Rejoignez notre réseau d'affiliés
            </h4>
            <p class="mb-0">
              Commencez à gagner des commissions dès aujourd'hui !
            </p>
          </div>

          <div v-else>
            <h4 class="text-h4 mb-1 text-success">
              ✅ Inscription réussie !
            </h4>
            <p class="mb-4">
              Vérifiez votre email <strong>{{ affiliateStore.lastSignupEmail }}</strong> pour continuer.
            </p>
            
            <VAlert
              type="info"
              variant="tonal"
              class="mb-4"
            >
              <p class="mb-2">
                <strong>Prochaines étapes :</strong>
              </p>
              <ol class="ps-4">
                <li>Cliquez sur le lien dans votre email</li>
                <li>Attendez l'approbation de votre demande</li>
                <li>Recevez vos identifiants de connexion</li>
              </ol>
            </VAlert>

            <div class="d-flex gap-4">
              <VBtn
                variant="outlined"
                @click="handleResendVerification"
                :loading="affiliateStore.isResending"
              >
                Renvoyer l'email
              </VBtn>
              
              <VBtn
                color="primary"
                :to="{ name: 'login' }"
              >
                Retour à la connexion
              </VBtn>
            </div>
          </div>
        </VCardText>

        <VCardText v-if="!showSuccessScreen">
          <VForm @submit.prevent="handleSubmit">
            <VRow>
              <!-- Nom complet -->
              <VCol cols="12">
                <AppTextField
                  v-model="form.nom_complet"
                  autofocus
                  label="Nom complet *"
                  placeholder="Votre nom complet"
                  :error-messages="getError('nom_complet')"
                />
              </VCol>

              <!-- Email -->
              <VCol cols="12">
                <AppTextField
                  v-model="form.email"
                  label="Email *"
                  type="email"
                  placeholder="votre@email.com"
                  :error-messages="getError('email')"
                />
              </VCol>

              <!-- Téléphone -->
              <VCol cols="12">
                <AppTextField
                  v-model="form.telephone"
                  label="Téléphone *"
                  placeholder="+212 6 12 34 56 78"
                  :error-messages="getError('telephone')"
                />
              </VCol>

              <!-- Mot de passe -->
              <VCol cols="12">
                <AppTextField
                  v-model="form.password"
                  label="Mot de passe *"
                  placeholder="············"
                  :type="isPasswordVisible ? 'text' : 'password'"
                  :append-inner-icon="isPasswordVisible ? 'tabler-eye-off' : 'tabler-eye'"
                  :error-messages="getError('password')"
                  @click:append-inner="isPasswordVisible = !isPasswordVisible"
                />
              </VCol>

              <!-- Confirmation mot de passe -->
              <VCol cols="12">
                <AppTextField
                  v-model="form.password_confirmation"
                  label="Confirmer le mot de passe *"
                  placeholder="············"
                  :type="isPasswordConfirmationVisible ? 'text' : 'password'"
                  :append-inner-icon="isPasswordConfirmationVisible ? 'tabler-eye-off' : 'tabler-eye'"
                  :error-messages="getError('password_confirmation')"
                  @click:append-inner="isPasswordConfirmationVisible = !isPasswordConfirmationVisible"
                />
              </VCol>

              <!-- Adresse -->
              <VCol cols="12">
                <AppTextField
                  v-model="form.adresse"
                  label="Adresse *"
                  placeholder="Votre adresse complète"
                  :error-messages="getError('adresse')"
                />
              </VCol>

              <!-- Ville et Pays -->
              <VCol
                cols="12"
                sm="6"
              >
                <AppTextField
                  v-model="form.ville"
                  label="Ville *"
                  placeholder="Votre ville"
                  :error-messages="getError('ville')"
                />
              </VCol>

              <VCol
                cols="12"
                sm="6"
              >
                <AppSelect
                  v-model="form.pays"
                  label="Pays *"
                  :items="countries"
                  :error-messages="getError('pays')"
                />
              </VCol>

              <!-- Notes (optionnel) -->
              <VCol cols="12">
                <AppTextarea
                  v-model="form.notes"
                  label="Notes (optionnel)"
                  placeholder="Parlez-nous de votre expérience en marketing..."
                  rows="3"
                  :error-messages="getError('notes')"
                />
              </VCol>

              <!-- Conditions d'utilisation -->
              <VCol cols="12">
                <div class="d-flex align-center">
                  <VCheckbox
                    id="accept-terms"
                    v-model="form.accept_terms"
                    :error="hasError('accept_terms')"
                  />
                  <VLabel
                    for="accept-terms"
                    class="text-sm"
                    style="opacity: 1;"
                  >
                    <span class="me-1">J'accepte les</span>
                    <a
                      href="javascript:void(0)"
                      class="text-primary"
                    >conditions d'utilisation</a>
                    <span class="mx-1">et la</span>
                    <a
                      href="javascript:void(0)"
                      class="text-primary"
                    >politique de confidentialité</a>
                  </VLabel>
                </div>
                <div
                  v-if="hasError('accept_terms')"
                  class="text-error text-caption mt-1"
                >
                  {{ getError('accept_terms') }}
                </div>
              </VCol>

              <!-- Submit button -->
              <VCol cols="12">
                <VBtn
                  block
                  type="submit"
                  :loading="affiliateStore.isLoading"
                >
                  S'inscrire comme affilié
                </VBtn>
              </VCol>

              <!-- Login link -->
              <VCol
                cols="12"
                class="text-center"
              >
                <span class="text-sm">Vous avez déjà un compte ?</span>
                <RouterLink
                  class="text-primary ms-1 text-sm"
                  :to="{ name: 'login' }"
                >
                  Se connecter
                </RouterLink>
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
