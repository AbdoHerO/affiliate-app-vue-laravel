<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import { useAffiliatePaymentsStore } from '@/stores/affiliate/payments'
import { useNotifications } from '@/composables/useNotifications'
import { useQuickConfirm } from '@/composables/useConfirmAction'
import Breadcrumbs from '@/components/common/Breadcrumbs.vue'
import ConfirmActionDialog from '@/components/common/ConfirmActionDialog.vue'

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
const paymentsStore = useAffiliatePaymentsStore()
const {
  commissions,
  withdrawals,
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

// Computed
const breadcrumbs = computed(() => [
  { title: 'Dashboard', to: { name: 'affiliate-dashboard' } },
  { title: 'Mes Paiements', active: true },
])

const commissionsHeaders = [
  { title: 'Commande', key: 'commande.id', sortable: false },
  { title: 'Produit', key: 'commandeArticle.produit.titre', sortable: false },
  { title: 'Type', key: 'type', sortable: true },
  { title: 'Montant de base', key: 'base_amount', sortable: true },
  { title: 'Taux', key: 'rate', sortable: true },
  { title: 'Commission', key: 'amount', sortable: true },
  { title: 'Statut', key: 'status', sortable: true },
  { title: 'Date', key: 'created_at', sortable: true },
]

const withdrawalsHeaders = [
  { title: 'Référence', key: 'id', sortable: false },
  { title: 'Montant', key: 'amount', sortable: true },
  { title: 'Méthode', key: 'method', sortable: false },
  { title: 'Statut', key: 'status', sortable: true },
  { title: 'Commissions', key: 'commission_count', sortable: false },
  { title: 'Date', key: 'created_at', sortable: true },
  { title: 'Actions', key: 'actions', sortable: false },
]

const canRequestPayout = computed(() => {
  return eligibleCommissionsCount.value > 0 && eligibleCommissionsTotal.value > 0
})

// Methods
const fetchCommissions = async (page = 1) => {
  try {
    await paymentsStore.fetchCommissions(page)
  } catch (err) {
    showError('Erreur lors du chargement des commissions')
  }
}

const fetchWithdrawals = async (page = 1) => {
  try {
    await paymentsStore.fetchWithdrawals(page)
  } catch (err) {
    showError('Erreur lors du chargement des retraits')
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
    showError('Aucune commission éligible pour un retrait')
    return
  }
  showPayoutDialog.value = true
  payoutNotes.value = ''
}

const requestPayout = async () => {
  try {
    const result = await confirm({
      title: 'Confirmer la demande de retrait',
      text: `Voulez-vous vraiment demander un retrait de ${formatCurrency(eligibleCommissionsTotal.value)} pour ${eligibleCommissionsCount.value} commission(s) éligible(s) ?`,
      icon: 'tabler-wallet',
      color: 'primary',
    })

    if (result) {
      await paymentsStore.requestPayout(payoutNotes.value)
      showSuccess('Demande de retrait créée avec succès')
      showPayoutDialog.value = false
    }
  } catch (err: any) {
    showError(err.message || 'Erreur lors de la demande de retrait')
  }
}

const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'MAD',
  }).format(amount)
}

const formatDate = (date: string) => {
  return new Date(date).toLocaleDateString('fr-FR', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })
}

const formatPercentage = (rate: number) => {
  return `${(rate * 100).toFixed(2)}%`
}

const viewWithdrawalDetails = (withdrawalId: string) => {
  // Navigate to withdrawal detail page (to be created)
  // For now, we'll use the existing route or create a modal
  console.log('View withdrawal details:', withdrawalId)
  // TODO: Implement withdrawal detail view
}

const canDownloadPdf = (withdrawal: any) => {
  // Only allow PDF download for approved/paid withdrawals
  return ['approved', 'in_payment', 'paid'].includes(withdrawal.status)
}

const downloadPdf = async (withdrawalId: string) => {
  try {
    downloadingPdf.value = withdrawalId

    // Make API call to download PDF
    const response = await fetch(`/api/affiliate/withdrawals/${withdrawalId}/pdf`, {
      method: 'GET',
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('accessToken')}`,
        'Accept': 'application/pdf',
      },
    })

    if (!response.ok) {
      const errorData = await response.json()
      throw new Error(errorData.message || 'Erreur lors du téléchargement du PDF')
    }

    // Create blob and download
    const blob = await response.blob()
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `facture-retrait-${withdrawalId}.pdf`
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    window.URL.revokeObjectURL(url)

    showSuccess('PDF téléchargé avec succès')
  } catch (err: any) {
    showError(err.message || 'Erreur lors du téléchargement du PDF')
  } finally {
    downloadingPdf.value = null
  }
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
          Mes Paiements
        </h1>
        <p class="text-body-1 text-medium-emphasis">
          Consultez vos commissions et gérez vos demandes de retrait
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
        Demander un retrait
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
                  {{ summary.count }} commission(s)
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
      <VTab value="commissions">Commissions</VTab>
      <VTab value="withdrawals">Retraits</VTab>
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

            <!-- Base Amount Column -->
            <template #item.base_amount="{ item }">
              {{ formatCurrency(item.base_amount) }}
            </template>

            <!-- Rate Column -->
            <template #item.rate="{ item }">
              {{ formatPercentage(item.rate) }}
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
                <h3 class="text-h6 mb-2">Aucune commission trouvée</h3>
                <p class="text-body-2 text-medium-emphasis">
                  Vous n'avez pas encore de commissions.
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
                {{ item.commission_count }} commission(s)
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

                <VBtn
                  v-if="canDownloadPdf(item)"
                  icon="tabler-download"
                  size="small"
                  variant="text"
                  color="success"
                  :loading="downloadingPdf === item.id"
                  @click="downloadPdf(item.id)"
                >
                  <VIcon icon="tabler-download" />
                  <VTooltip activator="parent" location="top">
                    {{ $t('payments.actions.download_pdf') }}
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
        <VCardTitle>Demander un retrait</VCardTitle>
        <VCardText>
          <div class="mb-4">
            <p class="text-body-1 mb-2">
              <strong>Montant éligible:</strong> {{ formatCurrency(eligibleCommissionsTotal) }}
            </p>
            <p class="text-body-2 text-medium-emphasis">
              {{ eligibleCommissionsCount }} commission(s) éligible(s) seront incluses dans cette demande.
            </p>
          </div>
          
          <VTextarea
            v-model="payoutNotes"
            label="Notes (optionnel)"
            placeholder="Ajoutez des notes pour cette demande de retrait..."
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
            Annuler
          </VBtn>
          <VBtn
            color="primary"
            :loading="loading.payout"
            @click="requestPayout"
          >
            Confirmer la demande
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Confirm Dialog -->
    <ConfirmActionDialog />
  </div>
</template>
