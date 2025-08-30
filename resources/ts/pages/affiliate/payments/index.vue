<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import { useAffiliatePaymentsStore } from '@/stores/affiliate/payments'
import { useAuthStore } from '@/stores/auth'
import { useNotifications } from '@/composables/useNotifications'
import { useQuickConfirm } from '@/composables/useConfirmAction'
import Breadcrumbs from '@/components/common/Breadcrumbs.vue'
import ConfirmActionDialog from '@/components/common/ConfirmActionDialog.vue'
import WithdrawalDetailsModal from '@/components/affiliate/WithdrawalDetailsModal.vue'

definePage({
  meta: {
    requiresAuth: true,
    requiresRole: 'affiliate',
  },
})

const { t } = useI18n()
const router = useRouter()
const { showSuccess, showError } = useNotifications()
const { confirm } = useQuickConfirm()

// Store
const authStore = useAuthStore()
const paymentsStore = useAffiliatePaymentsStore()
const {
  commissions,
  withdrawals,
  currentWithdrawal,
  commissionsSummary,
  loading,
  error,
  commissionsPagination,
  withdrawalsPagination,
  commissionsFilters,
  withdrawalsFilters,
  eligibleCommissionsTotal,
  eligibleCommissionsCount,
} = storeToRefs(paymentsStore)

// Local state
const activeTab = ref('commissions')
const showPayoutDialog = ref(false)
const payoutNotes = ref('')
const downloadingPdf = ref<string | null>(null)
const showWithdrawalModal = ref(false)

// Computed
const breadcrumbs = computed(() => [
  { title: t('nav.dashboard'), to: { name: 'affiliate-dashboard' } },
  { title: t('affiliate_payments_title'), active: true },
])

const commissionsHeaders = [
  { title: t('table.order'), key: 'commande.id', sortable: false },
  { title: t('table.product'), key: 'commandeArticle.produit.titre', sortable: false },
  { title: 'SKU', key: 'sku', sortable: false, width: '100px' },
  { title: t('order_type'), key: 'order_type', sortable: false },
  { title: t('table.type'), key: 'type', sortable: true },
  { title: t('affiliate_payments_base_amount'), key: 'base_amount', sortable: true },
  { title: t('table.commission'), key: 'amount', sortable: true },
  { title: t('table.status'), key: 'status', sortable: true },
  { title: t('table.date'), key: 'created_at', sortable: true },
]

const withdrawalsHeaders = [
  { title: t('table.reference'), key: 'id', sortable: false },
  { title: t('table.amount'), key: 'amount', sortable: true },
  { title: t('affiliate_payments_method'), key: 'method', sortable: false },
  { title: t('table.status'), key: 'status', sortable: true },
  { title: t('affiliate_payments_commissions'), key: 'commission_count', sortable: false },
  { title: t('table.date'), key: 'created_at', sortable: true },
  { title: t('table.actions'), key: 'actions', sortable: false },
]

const canRequestPayout = computed(() => {
  return eligibleCommissionsCount.value > 0 && eligibleCommissionsTotal.value > 0
})

// Methods
const fetchCommissions = async (page = 1) => {
  try {
    await paymentsStore.fetchCommissions(page)
  } catch (err) {
    showError(t('errors.commissions_load_failed'))
  }
}

const fetchWithdrawals = async (page = 1) => {
  try {
    await paymentsStore.fetchWithdrawals(page)
  } catch (err) {
    showError(t('errors.withdrawals_load_failed'))
  }
}

const handleCommissionsPageChange = (page: number) => {
  fetchCommissions(page)
}

const handleWithdrawalsPageChange = (page: number) => {
  fetchWithdrawals(page)
}

const handleCommissionsPerPageChange = (perPage: number) => {
  paymentsStore.updateCommissionsFilters({ per_page: perPage })
  fetchCommissions()
}

const handleWithdrawalsPerPageChange = (perPage: number) => {
  paymentsStore.updateWithdrawalsFilters({ per_page: perPage })
  fetchWithdrawals()
}

const handleCommissionsSort = (sortBy: any) => {
  if (sortBy.length > 0) {
    const { key, order } = sortBy[0]
    paymentsStore.updateCommissionsFilters({
      sort: key,
      dir: order,
    })
    fetchCommissions()
  }
}

const handleWithdrawalsSort = (sortBy: any) => {
  if (sortBy.length > 0) {
    const { key, order } = sortBy[0]
    paymentsStore.updateWithdrawalsFilters({
      sort: key,
      dir: order,
    })
    fetchWithdrawals()
  }
}

const openPayoutDialog = () => {
  if (!canRequestPayout.value) {
    showError(t('errors.no_eligible_commissions'))
    return
  }
  showPayoutDialog.value = true
  payoutNotes.value = ''
}

const requestPayout = async () => {
  try {
    const result = await confirm({
      title: t('affiliate_payments_confirm_withdrawal'),
      text: t('affiliate_payments_confirm_withdrawal_text', { 
        amount: formatCurrency(eligibleCommissionsTotal.value), 
        count: eligibleCommissionsCount.value 
      }),
      icon: 'tabler-wallet',
      color: 'primary',
    })

    if (result) {
      await paymentsStore.requestPayout(payoutNotes.value)
      showSuccess(t('affiliate_payments_withdrawal_success'))
      showPayoutDialog.value = false
    }
  } catch (err: any) {
    showError(err.message || t('errors.withdrawal_request_failed'))
  }
}

const formatCurrency = (amount: number | null | undefined) => {
  if (amount === null || amount === undefined || isNaN(Number(amount))) {
    return '0,00 MAD'
  }
  return new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'MAD',
  }).format(Number(amount))
}

const formatDate = (date: string) => {
  return new Date(date).toLocaleDateString('fr-FR', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })
}



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

const viewWithdrawalDetails = async (withdrawalId: string) => {
  try {
    // Fetch withdrawal details from the API
    await paymentsStore.fetchWithdrawal(withdrawalId)

    // Open the modal
    showWithdrawalModal.value = true
  } catch (err: any) {
    showError(err.message || t('errors.withdrawal_details_load'))
  }
}

const canDownloadEvidence = (withdrawal: any) => {
  // Only allow evidence download for paid withdrawals with evidence
  return withdrawal.status === 'paid' && withdrawal.evidence_path
}



const downloadEvidence = (withdrawalId: string): void => {
  console.log('Starting evidence download for withdrawal:', withdrawalId)

  const token = authStore.token
  const apiBaseUrl = 'http://localhost:8000/api'
  const url = `${apiBaseUrl}/affiliate/withdrawals/${withdrawalId}/evidence`

  console.log('Evidence download details:', {
    token: token ? `${token.substring(0, 10)}...` : 'NO TOKEN',
    url,
    apiBaseUrl
  })

  if (!token) {
    console.error('No auth token available')
    showError(t('affiliate_payments_session_expired'))
    return
  }

  // Set downloading state
  downloadingPdf.value = withdrawalId

  // Use XMLHttpRequest for better error handling and proper authentication
  const xhr = new XMLHttpRequest()
  xhr.open('GET', url, true)
  xhr.setRequestHeader('Authorization', `Bearer ${token}`)
  xhr.setRequestHeader('Accept', '*/*')
  xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest')
  xhr.responseType = 'blob'

  xhr.onload = function() {
    downloadingPdf.value = null

    if (xhr.status === 200) {
      const blob = xhr.response
      console.log(`Received evidence blob: ${blob.size} bytes, type: ${blob.type}`)

      // Create download link
      const url = window.URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = url
      link.download = `preuve-paiement-retrait-${withdrawalId}`
      document.body.appendChild(link)
      link.click()
      document.body.removeChild(link)
      window.URL.revokeObjectURL(url)
      showSuccess('Preuve de paiement téléchargée avec succès')
    } else {
      console.error(`Evidence download failed with status: ${xhr.status}`)
      showError(`Erreur lors du téléchargement: ${xhr.status}`)
    }
  }

  xhr.onerror = function() {
    console.error('Evidence download network error')
    downloadingPdf.value = null
    showError('Erreur réseau lors du téléchargement')
  }

  xhr.ontimeout = function() {
    console.error('Evidence download timeout')
    downloadingPdf.value = null
    showError('Timeout lors du téléchargement')
  }

  xhr.timeout = 30000 // 30 seconds timeout

  console.log('Sending evidence download request...')
  xhr.send()
}

// Watchers
watch(activeTab, (newTab) => {
  if (newTab === 'commissions') {
    fetchCommissions()
  } else if (newTab === 'withdrawals') {
    fetchWithdrawals()
  }
})

watch(error, (newError) => {
  if (newError) {
    showError(newError)
  }
})

// Lifecycle
onMounted(() => {
  fetchCommissions()
})
</script>

<template>
  <div>
    <!-- Breadcrumbs -->
    <Breadcrumbs :items="breadcrumbs" />

    <!-- Page Header -->
    <div class="d-flex justify-space-between align-center mb-6">
      <div>
        <h1 class="text-h4 font-weight-bold mb-1">
          {{ t('affiliate_payments_title') }}
        </h1>
        <p class="text-body-1 text-medium-emphasis">
          {{ t('affiliate_payments_description') }}
        </p>
      </div>
      <VBtn
        v-if="activeTab === 'commissions'"
        color="primary"
        prepend-icon="tabler-wallet"
        :disabled="!canRequestPayout"
        :loading="loading.payout"
        @click="openPayoutDialog"
      >
        {{ t('affiliate_payments_request_withdrawal') }}
      </VBtn>
    </div>

    <!-- Summary Cards -->
    <VRow v-if="activeTab === 'commissions'" class="mb-6">
      <VCol
        v-for="(summary, status) in commissionsSummary"
        :key="status"
        cols="12"
        sm="6"
        md="3"
      >
        <VCard>
          <VCardText>
            <div class="d-flex justify-space-between align-center">
              <div>
                <p class="text-caption text-medium-emphasis mb-1">
                  {{ paymentsStore.getCommissionStatusLabel(status) }}
                </p>
                <h3 class="text-h6 font-weight-bold">
                  {{ formatCurrency(summary.total) }}
                </h3>
                <p class="text-caption text-medium-emphasis">
                  {{ t('affiliate_payments_commission_count', { count: summary.count }) }}
                </p>
              </div>
              <VChip
                :color="paymentsStore.getCommissionStatusColor(status)"
                variant="tonal"
                size="small"
              >
                {{ status }}
              </VChip>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Tabs -->
    <VTabs v-model="activeTab" class="mb-6">
      <VTab value="commissions">{{ t('commissions') }}</VTab>
      <VTab value="withdrawals">{{ t('withdrawals') }}</VTab>
    </VTabs>

    <VWindow v-model="activeTab">
      <!-- Commissions Tab -->
      <VWindowItem value="commissions">
        <VCard>
          <VDataTableServer
            :headers="commissionsHeaders"
            :items="commissions"
            :loading="loading.commissions"
            :items-length="commissionsPagination.total"
            :items-per-page="commissionsPagination.per_page"
            :page="commissionsPagination.current_page"
            item-value="id"
            @update:page="handleCommissionsPageChange"
            @update:items-per-page="handleCommissionsPerPageChange"
            @update:sort-by="handleCommissionsSort"
          >
            <!-- Order Column -->
            <template #item.commande.id="{ item }">
              <VBtn
                variant="text"
                size="small"
                color="primary"
                @click="router.push({ name: 'affiliate-orders-id', params: { id: item.commande?.id } })"
              >
                #{{ item.commande?.id?.toString().slice(-8) || 'N/A' }}
              </VBtn>
            </template>

            <!-- Product Column -->
            <template #item.commandeArticle.produit.titre="{ item }">
              <span class="font-weight-medium">
                {{ item.commandeArticle?.produit?.titre || 'N/A' }}
              </span>
            </template>

            <!-- SKU Column -->
            <template #item.sku="{ item }">
              <VChip
                v-if="item.commandeArticle?.produit?.sku"
                size="small"
                color="secondary"
                variant="outlined"
                class="font-mono"
              >
                {{ item.commandeArticle.produit.sku }}
              </VChip>
              <span v-else class="text-medium-emphasis">—</span>
            </template>

            <!-- Order Type Column -->
            <template #item.order_type="{ item }">
              <VChip
                size="small"
                :color="getOrderTypeColor(item.commandeArticle?.type_command)"
                variant="tonal"
              >
                {{ getOrderTypeLabel(item.commandeArticle?.type_command) }}
              </VChip>
            </template>

            <!-- Base Amount Column -->
            <template #item.base_amount="{ item }">
              {{ formatCurrency(item.base_amount) }}
            </template>



            <!-- Amount Column -->
            <template #item.amount="{ item }">
              <span class="font-weight-bold">
                {{ formatCurrency(item.amount) }}
              </span>
            </template>

            <!-- Status Column -->
            <template #item.status="{ item }">
              <VChip
                :color="paymentsStore.getCommissionStatusColor(item.status)"
                size="small"
                variant="tonal"
              >
                {{ paymentsStore.getCommissionStatusLabel(item.status) }}
              </VChip>
            </template>

            <!-- Date Column -->
            <template #item.created_at="{ item }">
              {{ formatDate(item.created_at) }}
            </template>

            <!-- No data -->
            <template #no-data>
              <div class="text-center py-8">
                <VIcon
                  icon="tabler-percentage-off"
                  size="64"
                  class="text-disabled mb-4"
                />
                <h3 class="text-h6 mb-2">{{ t('affiliate_payments_no_commissions_found') }}</h3>
                <p class="text-body-2 text-medium-emphasis">
                  {{ t('affiliate_payments_no_commissions_yet') }}
                </p>
              </div>
            </template>
          </VDataTableServer>
        </VCard>
      </VWindowItem>

      <!-- Withdrawals Tab -->
      <VWindowItem value="withdrawals">
        <VCard>
          <VDataTableServer
            :headers="withdrawalsHeaders"
            :items="withdrawals"
            :loading="loading.withdrawals"
            :items-length="withdrawalsPagination.total"
            :items-per-page="withdrawalsPagination.per_page"
            :page="withdrawalsPagination.current_page"
            item-value="id"
            @update:page="handleWithdrawalsPageChange"
            @update:items-per-page="handleWithdrawalsPerPageChange"
            @update:sort-by="handleWithdrawalsSort"
          >
            <!-- Reference Column -->
            <template #item.id="{ item }">
              <span class="font-weight-medium">
                #{{ item.id.toString().slice(-8) }}
              </span>
            </template>

            <!-- Amount Column -->
            <template #item.amount="{ item }">
              <span class="font-weight-bold">
                {{ formatCurrency(item.amount) }}
              </span>
            </template>

            <!-- Method Column -->
            <template #item.method="{ item }">
              <VChip
                size="small"
                variant="tonal"
                color="info"
              >
                {{ item.method === 'bank_transfer' ? 'Virement bancaire' : item.method }}
              </VChip>
            </template>

            <!-- Status Column -->
            <template #item.status="{ item }">
              <VChip
                :color="paymentsStore.getWithdrawalStatusColor(item.status)"
                size="small"
                variant="tonal"
              >
                {{ paymentsStore.getWithdrawalStatusLabel(item.status) }}
              </VChip>
            </template>

            <!-- Commission Count Column -->
            <template #item.commission_count="{ item }">
              <span class="text-body-2">
                {{ t('affiliate_payments_commission_count', { count: item.commission_count }) }}
              </span>
            </template>

            <!-- Date Column -->
            <template #item.created_at="{ item }">
              {{ formatDate(item.created_at) }}
            </template>

            <!-- Actions Column -->
            <template #item.actions="{ item }">
              <div class="d-flex gap-1">
                <VBtn
                  icon="tabler-eye"
                  size="small"
                  variant="text"
                  color="primary"
                  @click="viewWithdrawalDetails(item.id)"
                >
                  <VIcon icon="tabler-eye" />
                  <VTooltip activator="parent" location="top">
                    {{ $t('payments.actions.view') }}
                  </VTooltip>
                </VBtn>

                <!-- Download Evidence (Payment Proof) -->
                <VBtn
                  v-if="canDownloadEvidence(item)"
                  icon="tabler-file-download"
                  size="small"
                  variant="text"
                  color="success"
                  :loading="downloadingPdf === item.id"
                  @click="downloadEvidence(item.id)"
                >
                  <VIcon icon="tabler-file-download" />
                  <VTooltip activator="parent" location="top">
                    {{ t('download_payment_proof') }}
                  </VTooltip>
                </VBtn>
              </div>
            </template>

            <!-- No data -->
            <template #no-data>
              <div class="text-center py-8">
                <VIcon
                  icon="tabler-wallet-off"
                  size="64"
                  class="text-disabled mb-4"
                />
                <h3 class="text-h6 mb-2">Aucun retrait trouvé</h3>
                <p class="text-body-2 text-medium-emphasis">
                  Vous n'avez pas encore demandé de retrait.
                </p>
              </div>
            </template>
          </VDataTableServer>
        </VCard>
      </VWindowItem>
    </VWindow>

    <!-- Payout Request Dialog -->
    <VDialog
      v-model="showPayoutDialog"
      max-width="500"
    >
      <VCard>
        <VCardTitle>{{ t('affiliate_payments_request_withdrawal') }}</VCardTitle>
        <VCardText>
          <div class="mb-4">
            <p class="text-body-1 mb-2">
              <strong>{{ t('affiliate_payments_eligible_amount') }}:</strong> {{ formatCurrency(eligibleCommissionsTotal) }}
            </p>
            <p class="text-body-2 text-medium-emphasis">
              {{ t('affiliate_payments_eligible_commissions_text', { count: eligibleCommissionsCount }) }}
            </p>
          </div>
          
          <VTextarea
            v-model="payoutNotes"
            :label="t('affiliate_payments_notes_optional')"
            :placeholder="t('affiliate_payments_notes_placeholder')"
            rows="3"
            counter="1000"
            maxlength="1000"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="outlined"
            @click="showPayoutDialog = false"
          >
            {{ t('affiliate_payments_cancel') }}
          </VBtn>
          <VBtn
            color="primary"
            :loading="loading.payout"
            @click="requestPayout"
          >
            {{ t('affiliate_payments_confirm_request') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Withdrawal Details Modal -->
    <WithdrawalDetailsModal
      v-model:is-visible="showWithdrawalModal"
      :withdrawal="currentWithdrawal"
    />

    <!-- Confirm Dialog -->
    <ConfirmActionDialog />
  </div>
</template>
