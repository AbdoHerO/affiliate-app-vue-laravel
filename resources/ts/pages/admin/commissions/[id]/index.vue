<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'
import { useCommissionsStore, type Commission } from '@/stores/admin/commissions'
import Breadcrumbs from '@/components/common/Breadcrumbs.vue'
import ActionIcon from '@/components/common/ActionIcon.vue'
import { useQuickConfirm } from '@/composables/useConfirmAction'
import { useNotifications } from '@/composables/useNotifications'

definePage({
  meta: {
    requiresAuth: true,
    requiresRole: 'admin',
  },
})

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const { showSuccess, showError } = useNotifications()
const { confirm } = useQuickConfirm()

// Store
const commissionsStore = useCommissionsStore()
const { loading } = storeToRefs(commissionsStore)

// Local state
const commission = ref<Commission | null>(null)
const showRejectDialog = ref(false)
const showAdjustDialog = ref(false)
const rejectReason = ref('')
const adjustAmount = ref(0)
const adjustNote = ref('')

// Computed
const breadcrumbs = computed(() => [
  { title: t('dashboard'), to: '/admin/dashboard' },
  { title: t('commissions'), to: '/admin/commissions' },
  { title: `Commission ${route.params.id}`, to: `/admin/commissions/${route.params.id}` },
])

// Methods
const fetchCommission = async () => {
  try {
    console.log('🔍 Fetching commission:', route.params.id)
    const result = await commissionsStore.fetchCommission(route.params.id as string)

    // Check if commission was actually loaded
    if (!result) {
      console.error('❌ Commission not found after fetch')
      showError(t('admin_commissions_not_found'))
      router.push('/admin/commissions')
      return
    }

    commission.value = result
    console.log('✅ Commission fetched successfully:', result)
  } catch (error) {
    console.error('❌ Error fetching commission:', error)
    showError(t('admin_commissions_loading_error'))
    router.push('/admin/commissions')
  }
}

const handleApprove = async () => {
  if (!commission.value) return

    const confirmed = await confirm({
    title: t('admin_commissions_confirm_approval'),
    text: t('admin_commissions_approve_confirmation', { amount: commission.value.amount, currency: commission.value.currency }),
    confirmText: t('actions.approve'),
    color: 'success',
    type: 'success',
  })

  if (confirmed) {
    const result = await commissionsStore.approveCommission(commission.value.id)
    if (result.success) {
      showSuccess(result.message)
      await fetchCommission() // Refresh data
    } else {
      showError(result.message)
    }
  }
}

const openRejectDialog = () => {
  if (!commission.value) return
  rejectReason.value = ''
  showRejectDialog.value = true
}

const handleReject = async () => {
  if (!commission.value || !rejectReason.value.trim()) return

  const result = await commissionsStore.rejectCommission(commission.value.id, rejectReason.value)
  if (result.success) {
    showSuccess(result.message)
    showRejectDialog.value = false
    await fetchCommission() // Refresh data
  } else {
    showError(result.message)
  }
}

const handleMarkAsPaid = async () => {
  if (!commission.value) return

  const confirmed = await confirm({
    title: t('admin_commissions_mark_as_paid'),
    text: t('admin_commissions_payment_confirmation', { amount: commission.value.amount, currency: commission.value.currency }),
    confirmText: t('actions.confirm_payment'),
    color: 'primary',
    type: 'success',
  })

  if (confirmed) {
    const result = await commissionsStore.markAsPaid(commission.value.id)
    if (result.success) {
      showSuccess(result.message)
      await fetchCommission()
    } else {
      showError(result.message)
    }
  }
}

const openAdjustDialog = () => {
  if (!commission.value) return
  adjustAmount.value = commission.value.amount
  adjustNote.value = ''
  showAdjustDialog.value = true
}

const handleAdjust = async () => {
  if (!commission.value || !adjustNote.value.trim()) return

  const result = await commissionsStore.adjustCommission(
    commission.value.id,
    adjustAmount.value,
    adjustNote.value
  )
  if (result.success) {
    showSuccess(result.message)
    showAdjustDialog.value = false
    await fetchCommission() // Refresh data
  } else {
    showError(result.message)
  }
}

const goBack = () => {
  router.push('/admin/commissions')
}

// Load data on mount
onMounted(async () => {
  console.log('🚀 Commission detail page mounted, ID:', route.params.id)
  console.log('🔍 Current commission state:', commission.value)
  console.log('🔍 Loading state:', loading.value)

  // Check if ID is valid
  if (!route.params.id || route.params.id === 'undefined') {
    console.error('❌ Invalid commission ID:', route.params.id)
    showError(t('admin_commissions_invalid_id'))
    router.push('/admin/commissions')
    return
  }

  // Fetch commission data
  await fetchCommission()

  console.log('🔍 After fetch - Commission:', commission.value)
  console.log('🔍 After fetch - Loading:', loading.value)
})
</script>

<template>
  <div>
    <!-- Breadcrumbs -->
    <Breadcrumbs :items="breadcrumbs" />

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-8">
      <VProgressCircular indeterminate color="primary" />
      <p class="mt-4">{{ t('admin_commissions_loading') }}</p>
    </div>

    <!-- Error State -->
    <div v-else-if="!commission && !loading" class="text-center py-8">
      <VIcon icon="tabler-alert-circle" size="64" class="mb-4" color="error" />
      <h2 class="text-h5 mb-2">{{ t('admin_commissions_not_found') }}</h2>
      <p class="text-body-1 mb-4">{{ t('admin_commissions_not_found_description') }}</p>
      <VBtn
        color="primary"
        variant="elevated"
        prepend-icon="tabler-arrow-left"
        @click="goBack"
      >
        {{ t('actions.backToList') }}
      </VBtn>
    </div>

    <!-- Commission Details -->
    <div v-else-if="commission">
      <!-- Header -->
      <div class="d-flex justify-space-between align-center mb-6">
        <div>
          <h1 class="text-h4 font-weight-bold mb-1">
            Commission {{ commission.id.slice(-8) }}
          </h1>
          <div class="d-flex align-center gap-2">
            <VChip
              :color="commission.status_badge.color"
              variant="tonal"
            >
              {{ commission.status_badge.text }}
            </VChip>
            <span class="text-h5 font-weight-bold text-success">
              {{ commission.amount }} {{ commission.currency }}
            </span>
          </div>
        </div>
        
        <div class="d-flex gap-3">
          <VBtn
            color="grey"
            variant="outlined"
            prepend-icon="tabler-arrow-left"
            @click="goBack"
          >
            {{ t('actions.back') }}
          </VBtn>

          <VBtn
            v-if="commission.can_be_approved"
            color="success"
            variant="elevated"
            prepend-icon="tabler-check"
            @click="handleApprove"
          >
            {{ t('actions.approve') }}
          </VBtn>

          <VBtn
            v-if="commission.can_be_rejected"
            color="error"
            variant="elevated"
            prepend-icon="tabler-x"
            @click="openRejectDialog"
          >
            {{ t('actions.reject') }}
          </VBtn>

          <VBtn
            v-if="commission.can_be_adjusted"
            color="warning"
            variant="elevated"
            prepend-icon="tabler-edit"
            @click="openAdjustDialog"
          >
            {{ t('actions.adjust') }}
          </VBtn>

          <VBtn
            v-if="commission.status === 'approved' && !commission.paid_at"
            color="primary"
            variant="elevated"
            prepend-icon="tabler-cash"
            @click="handleMarkAsPaid"
          >
            {{ t('admin_commissions_mark_as_paid') }}
          </VBtn>
        </div>
      </div>

      <!-- Content Cards -->
      <VRow>
        <!-- Commission Details -->
        <VCol cols="12" md="6">
          <VCard>
            <VCardTitle>
              <VIcon icon="tabler-percentage" class="me-2" />
              {{ t('admin_commissions_details') }}
            </VCardTitle>
            <VCardText>
              <VList>
                <VListItem>
                  <VListItemTitle>{{ t('admin_commissions_base_amount') }}</VListItemTitle>
                  <VListItemSubtitle>{{ commission.base_amount }} {{ commission.currency }}</VListItemSubtitle>
                </VListItem>
                
                <VListItem v-if="commission.rate">
                  <VListItemTitle>{{ t('admin_commissions_rate') }}</VListItemTitle>
                  <VListItemSubtitle>{{ commission.rate }}%</VListItemSubtitle>
                </VListItem>
                
                <VListItem v-if="commission.qty">
                  <VListItemTitle>{{ t('admin_commissions_quantity') }}</VListItemTitle>
                  <VListItemSubtitle>{{ commission.qty }}</VListItemSubtitle>
                </VListItem>
                
                <VListItem>
                  <VListItemTitle>{{ t('admin_commissions_final_amount') }}</VListItemTitle>
                  <VListItemSubtitle class="text-success font-weight-bold">
                    {{ commission.amount }} {{ commission.currency }}
                  </VListItemSubtitle>
                </VListItem>
                
                <VListItem v-if="commission.rule_code">
                  <VListItemTitle>{{ t('admin_commissions_applied_rule') }}</VListItemTitle>
                  <VListItemSubtitle>{{ commission.rule_code }}</VListItemSubtitle>
                </VListItem>
                
                <VListItem v-if="commission.eligible_at">
                  <VListItemTitle>{{ t('admin_commissions_eligible_on') }}</VListItemTitle>
                  <VListItemSubtitle>
                    {{ new Date(commission.eligible_at).toLocaleString('fr-FR') }}
                  </VListItemSubtitle>
                </VListItem>
                
                <VListItem v-if="commission.approved_at">
                  <VListItemTitle>{{ t('admin_commissions_approved_on') }}</VListItemTitle>
                  <VListItemSubtitle>
                    {{ new Date(commission.approved_at).toLocaleString('fr-FR') }}
                  </VListItemSubtitle>
                </VListItem>
                
                <VListItem v-if="commission.paid_at">
                  <VListItemTitle>{{ t('admin_commissions_paid_on') }}</VListItemTitle>
                  <VListItemSubtitle>
                    {{ new Date(commission.paid_at).toLocaleString('fr-FR') }}
                  </VListItemSubtitle>
                </VListItem>
              </VList>
            </VCardText>
          </VCard>
        </VCol>

        <!-- Affiliate Info -->
        <VCol cols="12" md="6">
          <VCard>
            <VCardTitle>
              <VIcon icon="tabler-user" class="me-2" />
              {{ t('admin_commissions_affiliate_info') }}
            </VCardTitle>
            <VCardText>
              <div v-if="commission.affiliate" class="d-flex align-center mb-4">
                <VAvatar size="48" class="me-4">
                  <VIcon icon="tabler-user" />
                </VAvatar>
                <div>
                  <h6 class="text-h6">{{ commission.affiliate.nom_complet }}</h6>
                  <p class="text-body-2 mb-0">{{ commission.affiliate.email }}</p>
                  <p v-if="commission.affiliate.telephone" class="text-caption mb-0">
                    {{ commission.affiliate.telephone }}
                  </p>
                </div>
              </div>
            </VCardText>
          </VCard>
        </VCol>

        <!-- Order Info -->
        <VCol cols="12" md="6">
          <VCard>
            <VCardTitle>
              <VIcon icon="tabler-shopping-cart" class="me-2" />
              {{ t('admin_commissions_order_info') }}
            </VCardTitle>
            <VCardText>
              <div v-if="commission.commande">
                <VList>
                  <VListItem>
                    <VListItemTitle>{{ t('admin_orders_id') }}</VListItemTitle>
                    <VListItemSubtitle>
                      <VChip
                        :to="`/admin/orders/${commission.commande.id}`"
                        color="primary"
                        variant="outlined"
                        size="small"
                      >
                        {{ commission.commande.id.slice(-8) }}
                      </VChip>
                    </VListItemSubtitle>
                  </VListItem>
                  
                  <VListItem>
                    <VListItemTitle>{{ t('admin_orders_status') }}</VListItemTitle>
                    <VListItemSubtitle>{{ commission.commande.statut }}</VListItemSubtitle>
                  </VListItem>
                  
                  <VListItem>
                    <VListItemTitle>{{ t('admin_orders_total_ttc') }}</VListItemTitle>
                    <VListItemSubtitle>
                      {{ commission.commande.total_ttc }} {{ commission.commande.devise }}
                    </VListItemSubtitle>
                  </VListItem>
                  
                  <VListItem>
                    <VListItemTitle>{{ t('admin_orders_created_at') }}</VListItemTitle>
                    <VListItemSubtitle>
                      {{ new Date(commission.commande.created_at).toLocaleString('fr-FR') }}
                    </VListItemSubtitle>
                  </VListItem>
                </VList>
              </div>
            </VCardText>
          </VCard>
        </VCol>

        <!-- Notes -->
        <VCol v-if="commission.notes" cols="12" md="6">
          <VCard>
            <VCardTitle>
              <VIcon icon="tabler-notes" class="me-2" />
              {{ t('admin_commissions_notes') }}
            </VCardTitle>
            <VCardText>
              <pre class="text-wrap">{{ commission.notes }}</pre>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>
    </div>

    <!-- Not Found -->
    <div v-else class="text-center py-8">
      <VIcon icon="tabler-alert-circle" size="64" class="mb-4" color="error" />
      <h3 class="text-h6 mb-2">{{ t('admin_commissions_not_found') }}</h3>
      <p class="text-body-2 text-medium-emphasis mb-4">
        {{ t('admin_commissions_not_found_description') }}
      </p>
      <VBtn color="primary" @click="goBack">
        {{ t('actions.backToCommissions') }}
      </VBtn>
    </div>

    <!-- Reject Dialog -->
    <VDialog v-model="showRejectDialog" max-width="500">
      <VCard>
        <VCardTitle>
          <span class="text-h6">{{ t('admin_commissions_reject_commission') }}</span>
        </VCardTitle>
        
        <VCardText>
          <p class="mb-4">
            {{ t('admin_commissions_reject_message') }}
          </p>
          
          <VTextarea
            v-model="rejectReason"
            :label="t('admin_commissions_reject_reason')"
            :placeholder="t('admin_commissions_reject_placeholder')"
            rows="3"
            required
          />
        </VCardText>
        
        <VCardActions>
          <VSpacer />
          <VBtn
            color="grey"
            variant="text"
            @click="showRejectDialog = false"
          >
            {{ t('actions.cancel') }}
          </VBtn>
          <VBtn
            color="error"
            variant="elevated"
            :disabled="!rejectReason.trim()"
            @click="handleReject"
          >
            {{ t('actions.reject') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Adjust Dialog -->
    <VDialog v-model="showAdjustDialog" max-width="500">
      <VCard>
        <VCardTitle>
          <span class="text-h6">{{ t('admin_commissions_adjust_commission') }}</span>
        </VCardTitle>
        
        <VCardText>
          <p class="mb-4">
            {{ t('admin_commissions_current_amount') }}: <strong>{{ commission?.amount }} {{ commission?.currency }}</strong>
          </p>
          
          <VTextField
            v-model.number="adjustAmount"
            :label="t('admin_commissions_new_amount')"
            type="number"
            step="0.01"
            min="0"
            suffix="MAD"
            required
            class="mb-4"
          />
          
          <VTextarea
            v-model="adjustNote"
            :label="t('admin_commissions_adjust_reason')"
            :placeholder="t('admin_commissions_adjust_placeholder')"
            rows="3"
            required
          />
        </VCardText>
        
        <VCardActions>
          <VSpacer />
          <VBtn
            color="grey"
            variant="text"
            @click="showAdjustDialog = false"
          >
            {{ t('actions.cancel') }}
          </VBtn>
          <VBtn
            color="warning"
            variant="elevated"
            :disabled="!adjustNote.trim()"
            @click="handleAdjust"
          >
            {{ t('actions.adjust') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
