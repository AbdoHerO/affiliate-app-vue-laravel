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
  { title: 'Dashboard', to: '/admin/dashboard' },
  { title: 'Finance', disabled: true },
  { title: 'Retraits', to: '/admin/withdrawals' },
  { title: currentWithdrawal.value?.id.substring(0, 8) || 'Détails', disabled: true },
])

const commissionHeaders = [
  { title: 'Commission', key: 'commission.id', sortable: false },
  { title: 'Commande', key: 'commission.commande.id', sortable: false },
  { title: 'Produit', key: 'commission.produit.titre', sortable: false },
  { title: 'Montant', key: 'amount', sortable: false },
  { title: 'Statut', key: 'commission.status', sortable: false },
  { title: 'Date', key: 'commission.created_at', sortable: false },
  { title: 'Actions', key: 'actions', sortable: false },
]

const canManageCommissions = computed(() => {
  return currentWithdrawal.value?.status === 'pending' || 
         currentWithdrawal.value?.status === 'approved'
})

// Methods
const fetchWithdrawal = async () => {
  const id = withdrawalId.value
  if (!id) {
    showError('ID de retrait invalide')
    router.push('/admin/withdrawals')
    return
  }

  const result = await withdrawalsStore.fetchOne(id)
  if (!result.success) {
    showError(result.message || 'Erreur lors du chargement du retrait')
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

const handleActionSuccess = () => {
  fetchWithdrawal()
}

const detachCommissions = async () => {
  if (!currentWithdrawal.value || selectedCommissions.value.length === 0) return

  const confirmed = await confirm({
    title: 'Détacher les commissions',
    text: `Êtes-vous sûr de vouloir détacher ${selectedCommissions.value.length} commission(s) de ce retrait ?`,
    confirmText: 'Détacher',
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
  showSuccess('Export en cours...')
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
      <p class="mt-4">Chargement du retrait...</p>
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
            <h1 class="text-h4 font-weight-bold">Retrait {{ currentWithdrawal.id.substring(0, 8) }}</h1>
            <WithdrawalStatusBadge :status="currentWithdrawal.status" />
          </div>
          <p class="text-body-1 text-medium-emphasis">
            Détails et gestion du retrait
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
              Approuver
            </VBtn>
            
            <VBtn
              v-if="currentWithdrawal.can_reject"
              color="error"
              variant="tonal"
              prepend-icon="tabler-x"
              @click="openActionDialog('reject')"
            >
              Rejeter
            </VBtn>
            
            <VBtn
              v-if="currentWithdrawal.can_mark_in_payment"
              color="primary"
              variant="tonal"
              prepend-icon="tabler-clock"
              @click="openActionDialog('mark_in_payment')"
            >
              En cours de paiement
            </VBtn>
            
            <VBtn
              v-if="currentWithdrawal.can_mark_paid"
              color="success"
              prepend-icon="tabler-credit-card"
              @click="openActionDialog('mark_paid')"
            >
              Marquer payé
            </VBtn>

            <VBtn
              variant="tonal"
              prepend-icon="tabler-download"
              @click="exportWithdrawal"
            >
              Exporter
            </VBtn>
          </div>
        </VCol>
      </VRow>

      <!-- Summary Cards -->
      <VRow class="mb-6">
        <!-- Withdrawal Info -->
        <VCol cols="12" md="6">
          <VCard>
            <VCardTitle>Informations du retrait</VCardTitle>
            <VCardText>
              <VRow>
                <VCol cols="6">
                  <div class="text-caption text-medium-emphasis">Montant</div>
                  <div class="text-h6 font-weight-bold">{{ Number(currentWithdrawal.amount).toFixed(2) }} MAD</div>
                </VCol>
                <VCol cols="6">
                  <div class="text-caption text-medium-emphasis">Méthode</div>
                  <div class="text-body-1">
                    {{ currentWithdrawal.method === 'bank_transfer' ? 'Virement bancaire' : currentWithdrawal.method }}
                  </div>
                </VCol>
                <VCol cols="6">
                  <div class="text-caption text-medium-emphasis">Commissions</div>
                  <div class="text-body-1">{{ currentWithdrawal.commission_count }} commission(s)</div>
                </VCol>
                <VCol cols="6">
                  <div class="text-caption text-medium-emphasis">Créé le</div>
                  <div class="text-body-1">{{ new Date(currentWithdrawal.created_at).toLocaleString() }}</div>
                </VCol>
                <VCol v-if="currentWithdrawal.approved_at" cols="6">
                  <div class="text-caption text-medium-emphasis">Approuvé le</div>
                  <div class="text-body-1">{{ new Date(currentWithdrawal.approved_at).toLocaleString() }}</div>
                </VCol>
                <VCol v-if="currentWithdrawal.paid_at" cols="6">
                  <div class="text-caption text-medium-emphasis">Payé le</div>
                  <div class="text-body-1">{{ new Date(currentWithdrawal.paid_at).toLocaleString() }}</div>
                </VCol>
                <VCol v-if="currentWithdrawal.payment_ref" cols="12">
                  <div class="text-caption text-medium-emphasis">Référence de paiement</div>
                  <div class="text-body-1 font-family-monospace">{{ currentWithdrawal.payment_ref }}</div>
                </VCol>
              </VRow>
            </VCardText>
          </VCard>
        </VCol>

        <!-- Affiliate Info -->
        <VCol cols="12" md="6">
          <VCard>
            <VCardTitle>Informations de l'affilié</VCardTitle>
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
                  <div class="text-caption text-medium-emphasis">Téléphone</div>
                  <div class="text-body-1">{{ currentWithdrawal.user.telephone }}</div>
                </VCol>
                <VCol v-if="currentWithdrawal.iban_rib" cols="12">
                  <div class="text-caption text-medium-emphasis">RIB/IBAN</div>
                  <div class="text-body-1 font-family-monospace">{{ currentWithdrawal.iban_rib }}</div>
                </VCol>
                <VCol v-if="currentWithdrawal.bank_type" cols="12">
                  <div class="text-caption text-medium-emphasis">Type de banque</div>
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
            <VCardTitle>Notes</VCardTitle>
            <VCardText>
              <div class="text-body-2" style="white-space: pre-line;">
                {{ currentWithdrawal.notes }}
              </div>
            </VCardText>
          </VCard>
        </VCol>
        <VCol v-if="currentWithdrawal.admin_reason" cols="12" md="6">
          <VCard color="error" variant="tonal">
            <VCardTitle>Raison du rejet</VCardTitle>
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
            <VCardTitle>Preuve de paiement</VCardTitle>
            <VCardText>
              <VBtn
                :href="currentWithdrawal.evidence_url"
                target="_blank"
                color="primary"
                variant="tonal"
                prepend-icon="tabler-download"
              >
                Télécharger la preuve
              </VBtn>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <!-- Linked Commissions -->
      <VCard>
        <VCardTitle class="d-flex justify-space-between align-center">
          <span>Commissions liées ({{ currentWithdrawal.items?.length || 0 }})</span>
          <div v-if="canManageCommissions && selectedCommissions.length > 0" class="d-flex gap-2">
            <VBtn
              color="error"
              variant="tonal"
              size="small"
              prepend-icon="tabler-unlink"
              @click="detachCommissions"
            >
              Détacher ({{ selectedCommissions.length }})
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

          <!-- Product -->
          <template #item.commission.produit.titre="{ item }">
            <div class="text-body-2">
              {{ item.commission?.produit?.titre || 'N/A' }}
            </div>
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
              {{ item.commission?.status }}
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
              label="Voir"
              tooltip="Voir la commission"
              @click="router.push(`/admin/commissions/${item.commission?.id}`)"
            />
          </template>

          <!-- No Data -->
          <template #no-data>
            <div class="text-center py-8">
              <VIcon icon="tabler-inbox" size="48" class="mb-2" />
              <p>Aucune commission liée à ce retrait</p>
            </div>
          </template>
        </VDataTable>

        <VCardText v-else>
          <div class="text-center py-8">
            <VIcon icon="tabler-inbox" size="48" class="mb-2" />
            <p>Aucune commission liée à ce retrait</p>
          </div>
        </VCardText>
      </VCard>
    </div>

    <!-- Error State -->
    <div v-else class="text-center py-8">
      <VIcon icon="tabler-alert-circle" size="64" color="error" class="mb-4" />
      <h3 class="text-h6 mb-2">Retrait non trouvé</h3>
      <p class="text-body-2 text-medium-emphasis mb-4">
        Le retrait demandé n'existe pas ou a été supprimé.
      </p>
      <VBtn color="primary" @click="goBack">
        Retour à la liste
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
