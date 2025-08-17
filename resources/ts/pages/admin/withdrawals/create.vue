<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useWithdrawalsStore } from '@/stores/admin/withdrawals'
import { useApi } from '@/composables/useApi'
import { useNotifications } from '@/composables/useNotifications'
import { useFormErrors } from '@/composables/useFormErrors'
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
const { api } = useApi()
const { showSuccess, showError } = useNotifications()
const { errors, setErrors, clearErrors, hasError, getError } = useFormErrors()

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

// Methods
const fetchUsers = async (search?: string) => {
  loadingUsers.value = true
  try {
    const response = await api.get('/admin/users', {
      params: {
        q: search,
        per_page: 50,
        role: 'affiliate', // Only affiliates can have withdrawals
      },
    })

    if (response.data.success) {
      users.value = response.data.data
    }
  } catch (error) {
    console.error('Error fetching users:', error)
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

    const result = await withdrawalsStore.create(payload)

    if (result.success) {
      showSuccess(result.message)
      router.push(`/admin/withdrawals/${result.data.id}`)
    } else {
      showError(result.message)
    }
  } catch (error: any) {
    if (error.response?.data?.errors) {
      setErrors(error.response.data.errors)
    } else {
      showError('Une erreur est survenue lors de la création du retrait')
    }
  } finally {
    loading.value = false
  }
}

const cancel = () => {
  router.push('/admin/withdrawals')
}

// Watch for user selection to reset commission selection
watch(() => form.value.user_id, () => {
  form.value.commission_ids = []
  targetAmount.value = 0
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
            <h3 class="text-h6 mb-4">Sélection de l'affilié</h3>
            
            <VRow>
              <VCol cols="12" md="8">
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
              <VCol cols="12" md="4">
                <VSelect
                  v-model="form.method"
                  :items="[{ title: 'Virement bancaire', value: 'bank_transfer' }]"
                  label="Méthode de retrait"
                  :error="hasError('method')"
                  :error-messages="getError('method')"
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
                      {{ targetAmount.toFixed(2) }} MAD
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
