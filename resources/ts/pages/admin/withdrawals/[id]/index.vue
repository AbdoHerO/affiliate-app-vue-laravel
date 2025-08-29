<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'
import { useWithdrawalsStore, type Withdrawal } from '@/stores/admin/withdrawals'
import { useAuthStore } from '@/stores/auth'
import Breadcrumbs from '@/components/common/Breadcrumbs.vue'
import WithdrawalStatusBadge from '@/components/admin/withdrawals/WithdrawalStatusBadge.vue'
import WithdrawalActionDialog from '@/components/admin/withdrawals/WithdrawalActionDialog.vue'
import ActionIcon from '@/components/common/ActionIcon.vue'
import { useQuickConfirm } from '@/composables/useConfirmAction'
import { useNotifications } from '@/composables/useNotifications'

definePage({
  meta: {
    requiresAuth: true,
    requiresRole: 'admin',
  },
})

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const withdrawalsStore = useWithdrawalsStore()
const authStore = useAuthStore()
const { confirm } = useQuickConfirm()
const { showSuccess, showError } = useNotifications()

// Store state
const { currentWithdrawal, loading } = storeToRefs(withdrawalsStore)

// Local state
const withdrawalId = computed(() => {
  const id = route.params.id
  if (!id || typeof id !== 'string' || id.trim() === '') {
    console.error('🚫 [Withdrawal Detail] Invalid withdrawal ID:', id)
    router.push('/admin/withdrawals').catch(console.error)
    return ''
  }
  return id
})
const selectedCommissions = ref<string[]>([])

// Action dialog state
const actionDialog = ref({
  isVisible: false,
  withdrawal: null as Withdrawal | null,
  action: 'approve' as 'approve' | 'reject' | 'mark_in_payment' | 'mark_paid',
})

// Computed
const breadcrumbItems = computed(() => [
  { title: t('nav_dashboard'), to: '/admin/dashboard' },
  { title: t('nav_financial_management'), disabled: true },
  { title: t('nav_withdrawals'), to: '/admin/withdrawals' },
  { title: currentWithdrawal.value?.id.substring(0, 8) || t('admin_withdrawals_details'), disabled: true },
])

const commissionHeaders = [
  { title: t('admin_commissions_title'), key: 'commission.id', sortable: false },
  { title: t('table_order_id'), key: 'commission.commande.id', sortable: false },
  { title: 'SKU', key: 'commission.sku', sortable: false, width: '150px' },
  { title: 'Type Commande', key: 'commission.order_type', sortable: false },
  { title: t('admin_withdrawals_amount'), key: 'amount', sortable: false },
  { title: t('table_status'), key: 'commission.status', sortable: false },
  { title: t('table_created'), key: 'commission.created_at', sortable: false },
  { title: t('table_actions'), key: 'actions', sortable: false },
]

const canManageCommissions = computed(() => {
  return currentWithdrawal.value?.status === 'pending' ||
         currentWithdrawal.value?.status === 'approved'
})

// Helper functions
const getOrderTypeColor = (type: string) => {
  const colors: Record<string, string> = {
    'order_sample': 'primary',
    'exchange': 'warning'
  }
  return colors[type] || 'secondary'
}

const getOrderTypeLabel = (type: string) => {
  const labels: Record<string, string> = {
    'order_sample': 'Échantillon',
    'exchange': 'Échange'
  }
  return labels[type] || type || 'N/A'
}

// Methods
const fetchWithdrawal = async () => {
  try {
    const id = withdrawalId.value
    if (!id) {
      showError(t('invalid_withdrawal_id'))
      router.push('/admin/withdrawals')
      return
    }

    const result = await withdrawalsStore.fetchOne(id)
    if (!result.success) {
      showError(result.message || t('error_loading_withdrawal'))
      router.push('/admin/withdrawals')
    }
  } catch (error) {
    console.error('Error fetching withdrawal:', error)
    showError(t('error_loading_withdrawal'))
    router.push('/admin/withdrawals')
  }
}

const openActionDialog = (action: typeof actionDialog.value.action) => {
  if (!currentWithdrawal.value) return
  
  actionDialog.value = {
    isVisible: true,
    withdrawal: currentWithdrawal.value,
    action,
  }
}

const handleActionSuccess = async () => {
  try {
    // Close dialog first
    actionDialog.value.isVisible = false
    actionDialog.value.withdrawal = null

    // Refresh data
    await fetchWithdrawal()

    // Show success message
    showSuccess(t('action_successful'))
  } catch (error) {
    console.error('Error handling action success:', error)
    showError(t('error_updating_data'))
  }
}

const detachCommissions = async () => {
  if (!currentWithdrawal.value || selectedCommissions.value.length === 0) return

  const confirmed = await confirm({
    title: t('admin_withdrawals_detach_commissions'),
    text: t('detach_commissions_confirm', { count: selectedCommissions.value.length }),
    confirmText: t('detach'),
    color: 'error',
    type: 'danger',
  })

  if (confirmed) {
    const result = await withdrawalsStore.detachCommissions(currentWithdrawal.value.id, {
      commission_ids: selectedCommissions.value,
    })

    if (result.success) {
      showSuccess(result.message)
      selectedCommissions.value = []
      fetchWithdrawal()
    } else {
      showError(result.message)
    }
  }
}

const exportWithdrawal = async () => {
  if (!currentWithdrawal.value) return
  
  // Export single withdrawal (you could implement this endpoint)
  showSuccess(t('export_in_progress'))
}

const goBack = () => {
  router.push('/admin/withdrawals')
}

// Lifecycle
onMounted(() => {
  fetchWithdrawal()
})
</script>

<template>
  <div>
    <!-- Breadcrumbs -->
    <Breadcrumbs :items="breadcrumbItems" />

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-8">
      <VProgressCircular indeterminate size="64" />
      <p class="mt-4">{{ t('loading_data') }}</p>
    </div>

    <!-- Content -->
    <div v-else-if="currentWithdrawal">
      <!-- Page Header -->
      <VRow class="mb-6">
        <VCol cols="12" md="6">
          <div class="d-flex align-center gap-3 mb-2">
            <VBtn
              icon="tabler-arrow-left"
              variant="tonal"
              size="small"
              @click="goBack"
            />
            <h1 class="text-h4 font-weight-bold">{{ t('nav_withdrawals') }} {{ currentWithdrawal.id.substring(0, 8) }}</h1>
            <WithdrawalStatusBadge :status="currentWithdrawal.status" />
          </div>
          <p class="text-body-1 text-medium-emphasis">
            {{ t('admin_withdrawals_details') }}
          </p>
        </VCol>
        <VCol cols="12" md="6" class="text-md-end">
          <div class="d-flex gap-2 justify-md-end">
            <!-- Action Buttons -->
            <VBtn
              v-if="currentWithdrawal.can_approve"
              color="success"
              variant="tonal"
              prepend-icon="tabler-check"
              @click="openActionDialog('approve')"
            >
              {{ t('admin_withdrawals_approve') }}
            </VBtn>
            
            <VBtn
              v-if="currentWithdrawal.can_reject"
              color="error"
              variant="tonal"
              prepend-icon="tabler-x"
              @click="openActionDialog('reject')"
            >
              {{ t('admin_withdrawals_reject') }}
            </VBtn>
            
            <VBtn
              v-if="currentWithdrawal.can_mark_in_payment"
              color="primary"
              variant="tonal"
              prepend-icon="tabler-clock"
              @click="openActionDialog('mark_in_payment')"
            >
              {{ t('admin_withdrawals_mark_in_payment') }}
            </VBtn>
            
            <VBtn
              v-if="currentWithdrawal.can_mark_paid"
              color="success"
              prepend-icon="tabler-credit-card"
              @click="openActionDialog('mark_paid')"
            >
              {{ t('admin_withdrawals_mark_paid') }}
            </VBtn>

            <VBtn
              variant="tonal"
              prepend-icon="tabler-download"
              @click="exportWithdrawal"
            >
              {{ t('action_download') }}
            </VBtn>
          </div>
        </VCol>
      </VRow>

      <!-- Summary Cards -->
      <VRow class="mb-6">
        <!-- Withdrawal Info -->
        <VCol cols="12" md="6">
          <VCard>
            <VCardTitle>{{ t('admin_withdrawals_details') }}</VCardTitle>
            <VCardText>
              <VRow>
                <VCol cols="6">
                  <div class="text-caption text-medium-emphasis">{{ t('admin_withdrawals_amount') }}</div>
                  <div class="text-h6 font-weight-bold">{{ Number(currentWithdrawal.amount).toFixed(2) }} MAD</div>
                </VCol>
                <VCol cols="6">
                  <div class="text-caption text-medium-emphasis">{{ t('payment_method') }}</div>
                  <div class="text-body-1">
                    {{ currentWithdrawal.method === 'bank_transfer' ? t('bank_transfer') : currentWithdrawal.method }}
                  </div>
                </VCol>
                <VCol cols="6">
                  <div class="text-caption text-medium-emphasis">{{ t('commissions') }}</div>
                  <div class="text-body-1">{{ t('commission_count_text', { count: currentWithdrawal.commission_count }) }}</div>
                </VCol>
                <VCol cols="6">
                  <div class="text-caption text-medium-emphasis">{{ t('admin_withdrawals_created_at') }}</div>
                  <div class="text-body-1">{{ new Date(currentWithdrawal.created_at).toLocaleString() }}</div>
                </VCol>
                <VCol v-if="currentWithdrawal.approved_at" cols="6">
                  <div class="text-caption text-medium-emphasis">{{ t('admin_withdrawals_approved_at') }}</div>
                  <div class="text-body-1">{{ new Date(currentWithdrawal.approved_at).toLocaleString() }}</div>
                </VCol>
                <VCol v-if="currentWithdrawal.paid_at" cols="6">
                  <div class="text-caption text-medium-emphasis">{{ t('admin_withdrawals_paid_at') }}</div>
                  <div class="text-body-1">{{ new Date(currentWithdrawal.paid_at).toLocaleString() }}</div>
                </VCol>
                <VCol v-if="currentWithdrawal.payment_ref" cols="12">
                  <div class="text-caption text-medium-emphasis">{{ t('payment_reference') }}</div>
                  <div class="text-body-1 font-family-monospace">{{ currentWithdrawal.payment_ref }}</div>
                </VCol>
              </VRow>
            </VCardText>
          </VCard>
        </VCol>

        <!-- Affiliate Info -->
        <VCol cols="12" md="6">
          <VCard>
            <VCardTitle>{{ t('admin_withdrawals_affiliate_info') }}</VCardTitle>
            <VCardText>
              <div class="d-flex align-center gap-3 mb-4">
                <VAvatar size="48" color="primary">
                  <span class="text-h6">{{ currentWithdrawal.user?.nom_complet?.charAt(0) }}</span>
                </VAvatar>
                <div>
                  <div class="text-h6">{{ currentWithdrawal.user?.nom_complet }}</div>
                  <div class="text-body-2 text-medium-emphasis">{{ currentWithdrawal.user?.email }}</div>
                </div>
              </div>
              
              <VRow>
                <VCol v-if="currentWithdrawal.user?.telephone" cols="12">
                  <div class="text-caption text-medium-emphasis">{{ t('phone') }}</div>
                  <div class="text-body-1">{{ currentWithdrawal.user.telephone }}</div>
                </VCol>
                <VCol v-if="currentWithdrawal.iban_rib" cols="12">
                  <div class="text-caption text-medium-emphasis">{{ t('rib') }}/IBAN</div>
                  <div class="text-body-1 font-family-monospace">{{ currentWithdrawal.iban_rib }}</div>
                </VCol>
                <VCol v-if="currentWithdrawal.bank_type" cols="12">
                  <div class="text-caption text-medium-emphasis">{{ t('bank_type') }}</div>
                  <div class="text-body-1">{{ currentWithdrawal.bank_type }}</div>
                </VCol>
              </VRow>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <!-- Notes and Reason -->
      <VRow v-if="currentWithdrawal.notes || currentWithdrawal.admin_reason" class="mb-6">
        <VCol v-if="currentWithdrawal.notes" cols="12" md="6">
          <VCard>
            <VCardTitle>{{ t('notes') }}</VCardTitle>
            <VCardText>
              <div class="text-body-2" style="white-space: pre-line;">
                {{ currentWithdrawal.notes }}
              </div>
            </VCardText>
          </VCard>
        </VCol>
        <VCol v-if="currentWithdrawal.admin_reason" cols="12" md="6">
          <VCard color="error" variant="tonal">
            <VCardTitle>{{ t('rejection_reason') }}</VCardTitle>
            <VCardText>
              <div class="text-body-2">
                {{ currentWithdrawal.admin_reason }}
              </div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <!-- Evidence -->
      <VRow v-if="currentWithdrawal.evidence_url" class="mb-6">
        <VCol cols="12">
          <VCard>
            <VCardTitle>{{ t('payment_proof') }}</VCardTitle>
            <VCardText>
              <VBtn
                :href="currentWithdrawal.evidence_url"
                target="_blank"
                color="primary"
                variant="tonal"
                prepend-icon="tabler-download"
              >
                {{ t('download_proof') }}
              </VBtn>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <!-- Linked Commissions -->
      <VCard>
        <VCardTitle class="d-flex justify-space-between align-center">
          <span>{{ t('admin_withdrawals_linked_commissions') }} ({{ currentWithdrawal.items?.length || 0 }})</span>
          <div v-if="canManageCommissions && selectedCommissions.length > 0" class="d-flex gap-2">
            <VBtn
              color="error"
              variant="tonal"
              size="small"
              prepend-icon="tabler-unlink"
              @click="detachCommissions"
            >
              {{ t('action_detach') }} ({{ selectedCommissions.length }})
            </VBtn>
          </div>
        </VCardTitle>

        <VDataTable
          v-if="currentWithdrawal.items && currentWithdrawal.items.length > 0"
          :headers="commissionHeaders"
          :items="currentWithdrawal.items"
          :show-select="canManageCommissions"
          v-model="selectedCommissions"
          item-value="commission.id"
          items-per-page="10"
        >
          <!-- Commission ID -->
          <template #item.commission.id="{ item }">
            <div class="text-body-2 font-family-monospace">
              {{ item.commission?.id?.substring(0, 8) }}...
            </div>
          </template>

          <!-- Order ID -->
          <template #item.commission.commande.id="{ item }">
            <div class="text-body-2 font-family-monospace">
              {{ item.commission?.commande?.id?.substring(0, 8) }}...
            </div>
          </template>

          <!-- SKU -->
          <template #item.commission.sku="{ item }">
            <VChip
              v-if="item.commission?.produit?.sku"
              size="small"
              color="secondary"
              variant="outlined"
              class="font-mono"
            >
              {{ item.commission.produit.sku }}
            </VChip>
            <span v-else class="text-medium-emphasis">—</span>
          </template>

          <!-- Order Type -->
          <template #item.commission.order_type="{ item }">
            <VChip
              size="small"
              :color="getOrderTypeColor(item.commission?.commande_article?.type_command)"
              variant="tonal"
            >
              {{ getOrderTypeLabel(item.commission?.commande_article?.type_command) }}
            </VChip>
          </template>

          <!-- Amount -->
          <template #item.amount="{ item }">
            <div class="text-body-2 font-weight-medium">
              {{ Number(item.amount).toFixed(2) }} MAD
            </div>
          </template>

          <!-- Status -->
          <template #item.commission.status="{ item }">
            <VChip
              :color="item.commission?.status === 'paid' ? 'success' : 'info'"
              variant="tonal"
              size="small"
            >
              {{ item.commission?.status === 'paid' ? t('status_paid') : t('status_pending') }}
            </VChip>
          </template>

          <!-- Date -->
          <template #item.commission.created_at="{ item }">
            <div class="text-body-2">
              {{ item.commission?.created_at ? new Date(item.commission.created_at).toLocaleDateString() : '-' }}
            </div>
          </template>

          <!-- Actions -->
          <template #item.actions="{ item }">
            <ActionIcon
              icon="tabler-eye"
              :label="t('action_view')"
              :tooltip="t('admin_withdrawals_view_commission')"
              @click="router.push(`/admin/commissions/${item.commission?.id}`)"
            />
          </template>

          <!-- No Data -->
          <template #no-data>
            <div class="text-center py-8">
              <VIcon icon="tabler-inbox" size="48" class="mb-2" />
              <p>{{ t('no_commissions_linked') }}</p>
            </div>
          </template>
        </VDataTable>

        <VCardText v-else>
          <div class="text-center py-8">
            <VIcon icon="tabler-inbox" size="48" class="mb-2" />
            <p>{{ t('no_commissions_linked') }}</p>
          </div>
        </VCardText>
      </VCard>
    </div>

    <!-- Error State -->
    <div v-else class="text-center py-8">
      <VIcon icon="tabler-alert-circle" size="64" color="error" class="mb-4" />
      <h3 class="text-h6 mb-2">{{ t('admin_withdrawals_not_found') }}</h3>
      <p class="text-body-2 text-medium-emphasis mb-4">
        {{ t('admin_withdrawals_not_found_description') }}
      </p>
      <VBtn color="primary" @click="goBack">
        {{ t('admin_withdrawals_back_to_list') }}
      </VBtn>
    </div>

    <!-- Action Dialog -->
    <WithdrawalActionDialog
      v-model:is-visible="actionDialog.isVisible"
      :withdrawal="actionDialog.withdrawal"
      :action="actionDialog.action"
      @success="handleActionSuccess"
    />
  </div>
</template>
