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
const eligibleCommissions = ref<any[]>([])
const loadingCommissions = ref(false)
const formRef = ref()

// Computed
const breadcrumbItems = computed(() => [
  { title: t('dashboard'), to: '/admin/dashboard' },
  { title: t('finance'), disabled: true },
  { title: t('admin_withdrawals_title'), to: '/admin/withdrawals' },
  { title: t('admin_withdrawals_create'), disabled: true },
])

const canSubmit = computed(() => {
  // Only enable submit on step 3 (confirmation)
  if (currentStep.value !== maxSteps) return false

  // Step 1: require user_id
  if (!form.value.user_id) return false

  // Step 2: require either selected commission ids OR auto amount with preview
  const hasCommissions = form.value.commission_ids.length > 0
  const hasAutoAmount = targetAmount.value > 0 && selectionMode.value === 'auto'

  return hasCommissions || hasAutoAmount
})

const currentStep = ref(1)
const maxSteps = 3

// Helper functions for error handling
const hasError = (field: string) => {
  return errors.value?.[field] && (errors.value as any)[field]?.length > 0
}

const getError = (field: string) => {
  return (errors.value as any)?.[field] || []
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
      showError(response?.message || t('errors.users_loading_error'))
    }
  } catch (error) {
    console.error('🚫 [Create Withdrawal] Error fetching users:', error)
    showError(t('errors.users_loading_error'))
  } finally {
    loadingUsers.value = false
  }
}

const nextStep = async () => {
  if (currentStep.value < maxSteps) {
    // Validate current step before proceeding
    if (formRef.value) {
      const { valid } = await formRef.value.validate()
      if (!valid) return
    }

    currentStep.value++
  }
}

const prevStep = () => {
  if (currentStep.value > 1) {
    currentStep.value--
  }
}

const handleSubmit = async () => {
  if (!canSubmit.value) return

  // Validate form first
  const { valid } = await formRef.value?.validate()
  if (!valid) return

  loading.value = true
  clearErrors()

  try {
    const payload: any = {
      user_id: form.value.user_id,
      method: form.value.method,
      notes: form.value.notes || undefined,
    }

    if (selectionMode.value === 'auto' && targetAmount.value > 0) {
      payload.amount = targetAmount.value
      payload.mode = 'auto'
    } else if (form.value.commission_ids.length > 0) {
      payload.commission_ids = form.value.commission_ids
      payload.mode = 'manual'
    }

    console.log('🔄 [Create Withdrawal] Submitting:', payload)
    const result = await withdrawalsStore.create(payload)

    if (result.success) {
      showSuccess(result.message || t('admin_withdrawals_create_success'))
      // Use safe navigation to the withdrawal detail page
      await safePush(`/admin/withdrawals/${result.data?.id}`)
    } else {
      showError(result.message || t('admin_withdrawals_create_error'))
    }
  } catch (error: any) {
    console.error('🚫 [Create Withdrawal] Error:', error)
    if (error.data?.errors) {
      setErrors(error.data.errors)
    } else {
      showError(t('admin_withdrawals_create_error_general'))
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

// Watch for user selection to load eligible commissions
watch(() => form.value.user_id, async (uid) => {
  // Reset form state
  form.value.commission_ids = []
  targetAmount.value = 0

  if (!uid) {
    eligibleCommissions.value = []
    return
  }

  loadingCommissions.value = true
  try {
    const res = await withdrawalsStore.fetchEligibleCommissions(uid)
    // Unwrap payload safely: res.data?.data ?? res.data ?? []
    const commissions = Array.isArray(res?.data?.data) ? res.data.data : (Array.isArray(res?.data) ? res.data : [])
    eligibleCommissions.value = commissions
    console.log('✅ [Create Withdrawal] Loaded eligible commissions:', eligibleCommissions.value.length)
  } catch (error) {
    console.error('❌ [Create Withdrawal] Error loading commissions:', error)
    showError(t('errors.commissions_loading_error'))
  } finally {
    loadingCommissions.value = false
  }
}, { immediate: true })

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
        <h1 class="text-h4 font-weight-bold">{{ t('admin_withdrawals_create_new') }}</h1>
        <p class="text-body-1 text-medium-emphasis">
          {{ t('admin_withdrawals_create_description') }}
        </p>
      </VCol>
    </VRow>

    <!-- Stepper -->
    <VCard class="mb-6">
      <VCardText>
        <VStepper
          v-model="currentStep"
          :items="[
            { title: t('admin_withdrawals_step_select_affiliate'), subtitle: t('admin_withdrawals_step_select_affiliate_subtitle') },
            { title: t('admin_withdrawals_step_select_commissions'), subtitle: t('admin_withdrawals_step_select_commissions_subtitle') },
            { title: t('admin_withdrawals_step_confirmation'), subtitle: t('admin_withdrawals_step_confirmation_subtitle') },
          ]"
          flat
        />
      </VCardText>
    </VCard>

    <!-- Form -->
    <VCard>
      <VCardText>
        <VForm ref="formRef" @submit.prevent="handleSubmit">
          <!-- Step 1: User Selection -->
          <div v-if="currentStep === 1">
            <h3 class="text-h6 mb-6">{{ t('admin_withdrawals_select_affiliate_title') }}</h3>

            <VRow>
              <VCol cols="12">
                <VAutocomplete
                  v-model="form.user_id"
                  :items="users"
                  :loading="loadingUsers"
                  item-title="nom_complet"
                  item-value="id"
                  :label="t('admin_withdrawals_select_affiliate_label')"
                  :placeholder="t('admin_withdrawals_search_affiliate_placeholder')"
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
                  :items="[{ title: t('admin_withdrawals_bank_transfer'), value: 'bank_transfer' }]"
                  :label="t('admin_withdrawals_method')"
                  :error="hasError('method')"
                  :error-messages="getError('method')"
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextarea
                  v-model="form.notes"
                  :label="t('admin_withdrawals_notes_optional')"
                  :placeholder="t('admin_withdrawals_notes_placeholder')"
                  rows="3"
                  :error="hasError('notes')"
                  :error-messages="getError('notes')"
                />
              </VCol>
            </VRow>


          </div>

          <!-- Step 2: Commission Selection -->
          <div v-if="currentStep === 2 && form.user_id">
            <h3 class="text-h6 mb-4">{{ t('admin_withdrawals_commission_selection') }}</h3>
            
            <CommissionSelector
              :user-id="form.user_id"
              v-model:selected-commissions="form.commission_ids"
              v-model:mode="selectionMode"
              v-model:target-amount="targetAmount"
            />
          </div>

          <!-- Step 3: Confirmation -->
          <div v-if="currentStep === 3">
            <h3 class="text-h6 mb-4">{{ t('confirmation') }}</h3>
            
            <VAlert color="info" variant="tonal" class="mb-4">
              <VIcon icon="tabler-info-circle" class="me-2" />
              {{ t('verify_before_creating_withdrawal') }}
            </VAlert>

            <VRow>
              <VCol cols="12" md="6">
                <VCard variant="tonal">
                  <VCardTitle>{{ t('withdrawal_information') }}</VCardTitle>
                  <VCardText>
                    <div class="mb-2">
                      <strong>{{ t('affiliate') }}:</strong> 
                      {{ users.find(u => u.id === form.user_id)?.nom_complet }}
                    </div>
                    <div class="mb-2">
                      <strong>{{ t('email') }}:</strong> 
                      {{ users.find(u => u.id === form.user_id)?.email }}
                    </div>
                    <div class="mb-2">
                      <strong>{{ t('method') }}:</strong> 
                      {{ form.method === 'bank_transfer' ? t('bank_transfer') : form.method }}
                    </div>
                    <div class="mb-2">
                      <strong>{{ t('selection_mode') }}:</strong> 
                      {{ selectionMode === 'auto' ? t('automatic') : t('manual') }}
                    </div>
                    <div v-if="selectionMode === 'auto'" class="mb-2">
                      <strong>{{ t('target_amount') }}:</strong> 
                      {{ Number(targetAmount).toFixed(2) }} MAD
                    </div>
                    <div class="mb-2">
                      <strong>{{ t('selected_commissions') }}:</strong> 
                      {{ form.commission_ids.length }}
                    </div>
                    <div v-if="form.notes" class="mb-2">
                      <strong>{{ t('notes') }}:</strong> 
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
                    {{ t('actions.previous') }}
                  </VBtn>
                </div>
                
                <div class="d-flex gap-2">
                  <VBtn
                    variant="tonal"
                    @click="cancel"
                  >
                    {{ t('actions.cancel') }}
                  </VBtn>
                  
                  <VBtn
                    v-if="currentStep < maxSteps"
                    color="primary"
                    :disabled="currentStep === 1 && !form.user_id"
                    @click="nextStep"
                  >
                    {{ t('actions.next') }}
                  </VBtn>
                  
                  <VBtn
                    v-if="currentStep === maxSteps"
                    color="primary"
                    :loading="loading"
                    :disabled="!canSubmit"
                    type="submit"
                  >
                    {{ t('admin_withdrawals_create_withdrawal') }}
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
