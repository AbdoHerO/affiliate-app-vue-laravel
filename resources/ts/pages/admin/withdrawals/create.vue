<script setup lang="ts">
import { computed, ref, watch, onUnmounted } from 'vue'
import { useRouter, onBeforeRouteLeave } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useWithdrawalsStore } from '@/stores/admin/withdrawals'
import { $api } from '@/utils/api'
import { useNotifications } from '@/composables/useNotifications'
import { useFormErrors } from '@/composables/useFormErrors'
import { useSafeNavigation } from '@/composables/useSafeNavigation'
import Breadcrumbs from '@/components/common/Breadcrumbs.vue'
import CommissionSelector from '@/components/admin/withdrawals/CommissionSelector.vue'

definePage({
  meta: {
    requiresAuth: true,
    requiresRole: 'admin',
  },
})

const router = useRouter()
const { t } = useI18n()
const withdrawalsStore = useWithdrawalsStore()
const { showSuccess, showError } = useNotifications()
const { errors, set: setErrors, clear: clearErrors } = useFormErrors()
const { safePush } = useSafeNavigation()

// Form state
const form = ref({
  user_id: '',
  amount: null as number | null,
  method: 'bank_transfer',
  notes: '',
  commission_ids: [] as string[],
})

const selectionMode = ref<'auto' | 'manual'>('manual')
const targetAmount = ref(0)
const loading = ref(false)
const users = ref<any[]>([])
const loadingUsers = ref(false)

// Computed
const breadcrumbItems = computed(() => [
  { title: 'Dashboard', to: '/admin/dashboard' },
  { title: 'Finance', disabled: true },
  { title: 'Retraits', to: '/admin/withdrawals' },
  { title: 'Nouveau retrait', disabled: true },
])

const isFormValid = computed(() => {
  return form.value.user_id && 
         (form.value.amount || form.value.commission_ids.length > 0)
})

const currentStep = ref(1)
const maxSteps = 3

// Helper functions for error handling
const hasError = (field: string) => {
  return errors.value?.[field] && errors.value[field]!.length > 0
}

const getError = (field: string) => {
  return errors.value?.[field] || []
}

// Methods
const fetchUsers = async (search?: string) => {
  loadingUsers.value = true
  try {
    // Build query parameters
    const params = new URLSearchParams()
    if (search) params.append('q', search)
    params.append('per_page', '50')
    params.append('role', 'affiliate') // Only affiliates can have withdrawals

    const url = `/admin/users${params.toString() ? `?${params.toString()}` : ''}`
    console.log('🔍 [Create Withdrawal] Fetching users from:', url)
    const response = await $api(url)

    console.log('📥 [Create Withdrawal] API Response:', response)

    if (response?.users || response?.data?.users) {
      // Handle direct response or nested response
      users.value = response.users || response.data?.users || []
      console.log('✅ [Create Withdrawal] Users loaded:', users.value.length)
    } else {
      console.error('❌ [Create Withdrawal] API returned error:', response)
      showError(response?.message || 'Erreur lors du chargement des utilisateurs')
    }
  } catch (error) {
    console.error('🚫 [Create Withdrawal] Error fetching users:', error)
    showError('Erreur lors du chargement des utilisateurs')
  } finally {
    loadingUsers.value = false
  }
}

const nextStep = () => {
  if (currentStep.value < maxSteps) {
    currentStep.value++
  }
}

const prevStep = () => {
  if (currentStep.value > 1) {
    currentStep.value--
  }
}

const handleSubmit = async () => {
  if (!isFormValid.value) return

  loading.value = true
  clearErrors()

  try {
    const payload = {
      user_id: form.value.user_id,
      method: form.value.method,
      notes: form.value.notes || undefined,
    }

    if (selectionMode.value === 'auto' && targetAmount.value > 0) {
      payload.amount = targetAmount.value
    } else if (form.value.commission_ids.length > 0) {
      payload.commission_ids = form.value.commission_ids
    }

    console.log('🔄 [Create Withdrawal] Submitting:', payload)
    const result = await withdrawalsStore.create(payload)

    if (result.success) {
      showSuccess(result.message || 'Retrait créé avec succès')
      // Use safe navigation to the withdrawal detail page
      await safePush(`/admin/withdrawals/${result.data?.id}`)
    } else {
      showError(result.message || 'Erreur lors de la création du retrait')
    }
  } catch (error: any) {
    console.error('🚫 [Create Withdrawal] Error:', error)
    if (error.data?.errors) {
      setErrors(error.data.errors)
    } else {
      showError('Une erreur est survenue lors de la création du retrait')
    }
  } finally {
    loading.value = false
  }
}

const cancel = async () => {
  console.log('🔄 [Create Withdrawal] Cancelling, navigating to withdrawals list')
  try {
    await safePush('/admin/withdrawals')
  } catch (error) {
    console.error('🚫 [Create Withdrawal] Error during cancel navigation:', error)
    // Fallback to direct router push
    router.push('/admin/withdrawals').catch(console.error)
  }
}

// Cleanup on component unmount
onUnmounted(() => {
  console.log('🧹 [Create Withdrawal] Component unmounting')
  // Clear any pending operations
  loading.value = false
  loadingUsers.value = false
})

// Watch for user selection to reset commission selection
watch(() => form.value.user_id, () => {
  form.value.commission_ids = []
  targetAmount.value = 0
})

// Navigation guard to prevent navigation issues
onBeforeRouteLeave((to, from, next) => {
  console.log('🔄 [Create Withdrawal] Leaving component, navigating to:', to?.path)

  // Clear loading states before leaving
  loading.value = false
  loadingUsers.value = false

  // Allow navigation
  next()
})

// Initialize
fetchUsers()
</script>

<template>
  <div>
    <!-- Breadcrumbs -->
    <Breadcrumbs :items="breadcrumbItems" />

    <!-- Page Header -->
    <VRow class="mb-6">
      <VCol cols="12">
        <h1 class="text-h4 font-weight-bold">Créer un nouveau retrait</h1>
        <p class="text-body-1 text-medium-emphasis">
          Créez un retrait pour un affilié en sélectionnant les commissions éligibles
        </p>
      </VCol>
    </VRow>

    <!-- Stepper -->
    <VCard class="mb-6">
      <VCardText>
        <VStepper
          v-model="currentStep"
          :items="[
            { title: 'Sélection de l\'affilié', subtitle: 'Choisir l\'affilié' },
            { title: 'Sélection des commissions', subtitle: 'Choisir les commissions' },
            { title: 'Confirmation', subtitle: 'Vérifier et créer' },
          ]"
          flat
        />
      </VCardText>
    </VCard>

    <!-- Form -->
    <VCard>
      <VCardText>
        <VForm @submit.prevent="handleSubmit">
          <!-- Step 1: User Selection -->
          <div v-if="currentStep === 1">
            <h3 class="text-h6 mb-6">Sélection de l'affilié</h3>

            <VRow>
              <VCol cols="12">
                <VAutocomplete
                  v-model="form.user_id"
                  :items="users"
                  :loading="loadingUsers"
                  item-title="nom_complet"
                  item-value="id"
                  label="Sélectionner un affilié *"
                  placeholder="Rechercher un affilié..."
                  clearable
                  :error="hasError('user_id')"
                  :error-messages="getError('user_id')"
                  @update:search="fetchUsers"
                  @click:control="fetchUsers"
                  class="mb-4"
                >
                  <template #item="{ props, item }">
                    <VListItem v-bind="props">
                      <template #prepend>
                        <VAvatar size="32" color="primary">
                          <span class="text-caption">{{ item.raw.nom_complet?.charAt(0) }}</span>
                        </VAvatar>
                      </template>
                      <VListItemTitle>{{ item.raw.nom_complet }}</VListItemTitle>
                      <VListItemSubtitle>{{ item.raw.email }}</VListItemSubtitle>
                    </VListItem>
                  </template>
                </VAutocomplete>
              </VCol>
            </VRow>

            <VRow>
              <VCol cols="12" md="6">
                <VSelect
                  v-model="form.method"
                  :items="[{ title: 'Virement bancaire', value: 'bank_transfer' }]"
                  label="Méthode de retrait"
                  :error="hasError('method')"
                  :error-messages="getError('method')"
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextarea
                  v-model="form.notes"
                  label="Notes (optionnel)"
                  placeholder="Ajouter des notes pour ce retrait..."
                  rows="3"
                  :error="hasError('notes')"
                  :error-messages="getError('notes')"
                />
              </VCol>
            </VRow>

            <VTextarea
              v-model="form.notes"
              label="Notes (optionnelles)"
              placeholder="Ajouter des notes sur ce retrait..."
              rows="3"
              auto-grow
              :error="hasError('notes')"
              :error-messages="getError('notes')"
            />
          </div>

          <!-- Step 2: Commission Selection -->
          <div v-if="currentStep === 2 && form.user_id">
            <h3 class="text-h6 mb-4">Sélection des commissions</h3>
            
            <CommissionSelector
              :user-id="form.user_id"
              v-model:selected-commissions="form.commission_ids"
              v-model:mode="selectionMode"
              v-model:target-amount="targetAmount"
            />
          </div>

          <!-- Step 3: Confirmation -->
          <div v-if="currentStep === 3">
            <h3 class="text-h6 mb-4">Confirmation</h3>
            
            <VAlert color="info" variant="tonal" class="mb-4">
              <VIcon icon="tabler-info-circle" class="me-2" />
              Vérifiez les informations avant de créer le retrait
            </VAlert>

            <VRow>
              <VCol cols="12" md="6">
                <VCard variant="tonal">
                  <VCardTitle>Informations du retrait</VCardTitle>
                  <VCardText>
                    <div class="mb-2">
                      <strong>Affilié:</strong> 
                      {{ users.find(u => u.id === form.user_id)?.nom_complet }}
                    </div>
                    <div class="mb-2">
                      <strong>Email:</strong> 
                      {{ users.find(u => u.id === form.user_id)?.email }}
                    </div>
                    <div class="mb-2">
                      <strong>Méthode:</strong> 
                      {{ form.method === 'bank_transfer' ? 'Virement bancaire' : form.method }}
                    </div>
                    <div class="mb-2">
                      <strong>Mode de sélection:</strong> 
                      {{ selectionMode === 'auto' ? 'Automatique' : 'Manuel' }}
                    </div>
                    <div v-if="selectionMode === 'auto'" class="mb-2">
                      <strong>Montant cible:</strong> 
                      {{ Number(targetAmount).toFixed(2) }} MAD
                    </div>
                    <div class="mb-2">
                      <strong>Commissions sélectionnées:</strong> 
                      {{ form.commission_ids.length }}
                    </div>
                    <div v-if="form.notes" class="mb-2">
                      <strong>Notes:</strong> 
                      {{ form.notes }}
                    </div>
                  </VCardText>
                </VCard>
              </VCol>
            </VRow>
          </div>

          <!-- Navigation Buttons -->
          <VRow class="mt-6">
            <VCol cols="12">
              <div class="d-flex justify-space-between">
                <div>
                  <VBtn
                    v-if="currentStep > 1"
                    variant="tonal"
                    @click="prevStep"
                  >
                    Précédent
                  </VBtn>
                </div>
                
                <div class="d-flex gap-2">
                  <VBtn
                    variant="tonal"
                    @click="cancel"
                  >
                    Annuler
                  </VBtn>
                  
                  <VBtn
                    v-if="currentStep < maxSteps"
                    color="primary"
                    :disabled="currentStep === 1 && !form.user_id"
                    @click="nextStep"
                  >
                    Suivant
                  </VBtn>
                  
                  <VBtn
                    v-if="currentStep === maxSteps"
                    color="primary"
                    :loading="loading"
                    :disabled="!isFormValid"
                    type="submit"
                  >
                    Créer le retrait
                  </VBtn>
                </div>
              </div>
            </VCol>
          </VRow>
        </VForm>
      </VCardText>
    </VCard>
  </div>
</template>
