<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'
import { useWithdrawalsStore, type Withdrawal, type WithdrawalFilters } from '@/stores/admin/withdrawals'
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

const router = useRouter()
const { t } = useI18n()
const withdrawalsStore = useWithdrawalsStore()
const authStore = useAuthStore()
const { confirm } = useQuickConfirm()
const { showSuccess, showError } = useNotifications()

// Store state
const { 
  withdrawals, 
  summary, 
  loading, 
  pagination, 
  filters 
} = storeToRefs(withdrawalsStore)

// Local state
const searchQuery = ref('')
const selectedStatuses = ref<string[]>([])
const selectedUserId = ref('')
const selectedMethod = ref('')
const dateFrom = ref('')
const dateTo = ref('')
const selectedWithdrawals = ref<string[]>([])

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
  { title: 'Retraits', disabled: true },
])

const statusOptions = [
  { title: 'En attente', value: 'pending' },
  { title: 'Approuvé', value: 'approved' },
  { title: 'En cours de paiement', value: 'in_payment' },
  { title: 'Payé', value: 'paid' },
  { title: 'Rejeté', value: 'rejected' },
  { title: 'Annulé', value: 'canceled' },
]

const methodOptions = [
  { title: 'Virement bancaire', value: 'bank_transfer' },
]

const tableHeaders = [
  { title: 'Référence', key: 'id', sortable: true },
  { title: 'Affilié', key: 'user.nom_complet', sortable: true },
  { title: 'Montant', key: 'amount', sortable: true },
  { title: 'Méthode', key: 'method', sortable: false },
  { title: 'Statut', key: 'status', sortable: true },
  { title: 'Commissions', key: 'commission_count', sortable: false },
  { title: 'Créé le', key: 'created_at', sortable: true },
  { title: 'Actions', key: 'actions', sortable: false },
]

const hasFilters = computed(() => {
  return searchQuery.value || 
         selectedStatuses.value.length > 0 || 
         selectedUserId.value || 
         selectedMethod.value || 
         dateFrom.value || 
         dateTo.value
})

const isAllSelected = computed(() => {
  return withdrawals.value.length > 0 && 
         withdrawals.value.every(w => selectedWithdrawals.value.includes(w.id))
})

const isSomeSelected = computed(() => {
  return selectedWithdrawals.value.length > 0 && !isAllSelected.value
})

// Methods
const fetchWithdrawals = async () => {
  const filterParams: WithdrawalFilters = {
    page: pagination.value.current_page,
    per_page: pagination.value.per_page,
    sort: filters.value.sort,
    dir: filters.value.dir,
  }

  if (searchQuery.value) filterParams.q = searchQuery.value
  if (selectedStatuses.value.length > 0) filterParams.status = selectedStatuses.value
  if (selectedUserId.value) filterParams.user_id = selectedUserId.value
  if (selectedMethod.value) filterParams.method = selectedMethod.value
  if (dateFrom.value) filterParams.date_from = dateFrom.value
  if (dateTo.value) filterParams.date_to = dateTo.value

  await withdrawalsStore.fetchList(filterParams)
}

const clearFilters = () => {
  searchQuery.value = ''
  selectedStatuses.value = []
  selectedUserId.value = ''
  selectedMethod.value = ''
  dateFrom.value = ''
  dateTo.value = ''
  selectedWithdrawals.value = []
  fetchWithdrawals()
}

const toggleSelectAll = () => {
  if (isAllSelected.value) {
    selectedWithdrawals.value = []
  } else {
    selectedWithdrawals.value = withdrawals.value.map(w => w.id)
  }
}

const viewWithdrawal = (withdrawal: Withdrawal) => {
  if (!withdrawal?.id) {
    showError('ID de retrait invalide')
    return
  }
  router.push(`/admin/withdrawals/${withdrawal.id}`)
}

const openActionDialog = (withdrawal: Withdrawal, action: typeof actionDialog.value.action) => {
  if (!withdrawal) {
    console.error('Cannot open action dialog: withdrawal is null')
    return
  }

  actionDialog.value = {
    isVisible: true,
    withdrawal: { ...withdrawal }, // Create a copy to avoid reactivity issues
    action,
  }
}

const resetActionDialog = () => {
  actionDialog.value = {
    isVisible: false,
    withdrawal: null,
    action: 'approve'
  }
}

const handleActionSuccess = async () => {
  try {
    // Close dialog first to prevent reactivity issues
    resetActionDialog()

    // Clear selections
    selectedWithdrawals.value = []

    // Refresh data
    await fetchWithdrawals()

    // Show success message
    showSuccess('Action effectuée avec succès')
  } catch (error) {
    console.error('Error handling action success:', error)
    showError('Erreur lors de la mise à jour des données')
  }
}

const exportWithdrawals = async () => {
  const filterParams: WithdrawalFilters = {}
  
  if (searchQuery.value) filterParams.q = searchQuery.value
  if (selectedStatuses.value.length > 0) filterParams.status = selectedStatuses.value
  if (selectedUserId.value) filterParams.user_id = selectedUserId.value
  if (selectedMethod.value) filterParams.method = selectedMethod.value
  if (dateFrom.value) filterParams.date_from = dateFrom.value
  if (dateTo.value) filterParams.date_to = dateTo.value

  const result = await withdrawalsStore.exportCsv(filterParams)
  if (result.success) {
    showSuccess(result.message || 'Export réussi')
  } else {
    showError(result.message || 'Erreur lors de l\'export')
  }
}

const createWithdrawal = () => {
  router.push('/admin/withdrawals/create')
}

// Watchers
watch([searchQuery, selectedStatuses, selectedUserId, selectedMethod, dateFrom, dateTo], () => {
  // Reset to first page when filters change
  pagination.value.current_page = 1
  fetchWithdrawals()
}, { deep: true })

// Lifecycle
onMounted(() => {
  fetchWithdrawals()
})
</script>

<template>
  <div>
    <!-- Breadcrumbs -->
    <Breadcrumbs :items="breadcrumbItems" />

    <!-- Page Header -->
    <VRow class="mb-6">
      <VCol cols="12" md="6">
        <h1 class="text-h4 font-weight-bold">Gestion des Retraits</h1>
        <p class="text-body-1 text-medium-emphasis">
          Gérez les demandes de retrait des affiliés
        </p>
      </VCol>
      <VCol cols="12" md="6" class="text-md-end">
        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          @click="createWithdrawal"
        >
          Nouveau Retrait
        </VBtn>
      </VCol>
    </VRow>

    <!-- KPI Cards -->
    <VRow v-if="summary" class="mb-6">
      <VCol cols="12" sm="6" md="3">
        <VCard color="warning" variant="tonal">
          <VCardText>
            <div class="d-flex justify-space-between align-center">
              <div>
                <div class="text-h6">{{ summary?.pending?.count || 0 }}</div>
                <div class="text-body-2">En attente</div>
              </div>
              <VIcon icon="tabler-clock" size="32" />
            </div>
            <div class="text-caption mt-2">{{ Number(summary?.pending?.amount || 0).toFixed(2) }} MAD</div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" sm="6" md="3">
        <VCard color="info" variant="tonal">
          <VCardText>
            <div class="d-flex justify-space-between align-center">
              <div>
                <div class="text-h6">{{ summary?.approved?.count || 0 }}</div>
                <div class="text-body-2">Approuvés</div>
              </div>
              <VIcon icon="tabler-check" size="32" />
            </div>
            <div class="text-caption mt-2">{{ Number(summary?.approved?.amount || 0).toFixed(2) }} MAD</div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" sm="6" md="3">
        <VCard color="primary" variant="tonal">
          <VCardText>
            <div class="d-flex justify-space-between align-center">
              <div>
                <div class="text-h6">{{ summary?.in_payment?.count || 0 }}</div>
                <div class="text-body-2">En cours</div>
              </div>
              <VIcon icon="tabler-credit-card" size="32" />
            </div>
            <div class="text-caption mt-2">{{ Number(summary?.in_payment?.amount || 0).toFixed(2) }} MAD</div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" sm="6" md="3">
        <VCard color="success" variant="tonal">
          <VCardText>
            <div class="d-flex justify-space-between align-center">
              <div>
                <div class="text-h6">{{ summary?.paid?.count || 0 }}</div>
                <div class="text-body-2">Payés</div>
              </div>
              <VIcon icon="tabler-check-circle" size="32" />
            </div>
            <div class="text-caption mt-2">{{ Number(summary?.paid?.amount || 0).toFixed(2) }} MAD</div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Filters -->
    <VCard class="mb-6">
      <VCardText>
        <VRow>
          <VCol cols="12" md="3">
            <VTextField
              v-model="searchQuery"
              label="Rechercher..."
              prepend-inner-icon="tabler-search"
              clearable
            />
          </VCol>
          <VCol cols="12" md="3">
            <VSelect
              v-model="selectedStatuses"
              :items="statusOptions"
              label="Statuts"
              multiple
              chips
              clearable
            />
          </VCol>
          <VCol cols="12" md="2">
            <VSelect
              v-model="selectedMethod"
              :items="methodOptions"
              label="Méthode"
              clearable
            />
          </VCol>
          <VCol cols="12" md="2">
            <VTextField
              v-model="dateFrom"
              label="Date début"
              type="date"
            />
          </VCol>
          <VCol cols="12" md="2">
            <VTextField
              v-model="dateTo"
              label="Date fin"
              type="date"
            />
          </VCol>
        </VRow>

        <VRow v-if="hasFilters" class="mt-2">
          <VCol cols="12">
            <VBtn
              variant="tonal"
              color="error"
              size="small"
              @click="clearFilters"
            >
              Effacer les filtres
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Data Table -->
    <VCard>
      <!-- Table Actions -->
      <VCardText v-if="selectedWithdrawals.length > 0" class="pb-0">
        <VAlert color="info" variant="tonal">
          <div class="d-flex justify-space-between align-center">
            <span>{{ selectedWithdrawals.length }} retrait(s) sélectionné(s)</span>
            <div class="d-flex gap-2">
              <VBtn
                variant="tonal"
                size="small"
                @click="exportWithdrawals"
              >
                Exporter sélection
              </VBtn>
            </div>
          </div>
        </VAlert>
      </VCardText>

      <VCardText class="d-flex justify-space-between align-center">
        <div class="d-flex align-center gap-2">
          <VCheckbox
            :model-value="isAllSelected"
            :indeterminate="isSomeSelected"
            @click="toggleSelectAll"
          />
          <span class="text-body-2">Tout sélectionner</span>
        </div>

        <div class="d-flex gap-2">
          <VBtn
            variant="tonal"
            prepend-icon="tabler-download"
            @click="exportWithdrawals"
          >
            Exporter
          </VBtn>
        </div>
      </VCardText>

      <VDataTable
        :headers="tableHeaders"
        :items="withdrawals"
        :loading="loading"
        :items-per-page="pagination.per_page"
        :page="pagination.current_page"
        :items-length="pagination.total"
        show-select
        v-model="selectedWithdrawals"
        item-value="id"
        @update:page="(page) => { pagination.current_page = page; fetchWithdrawals() }"
        @update:items-per-page="(perPage) => { pagination.per_page = perPage; fetchWithdrawals() }"
      >
        <!-- ID Column -->
        <template #item.id="{ item }">
          <div class="text-body-2 font-family-monospace">
            {{ item?.id?.slice(0, 8) ?? '—' }}...
          </div>
        </template>

        <!-- User Column -->
        <template #item.user.nom_complet="{ item }">
          <div>
            <div class="text-body-2 font-weight-medium">{{ item.user?.nom_complet }}</div>
            <div class="text-caption text-medium-emphasis">{{ item.user?.email }}</div>
          </div>
        </template>

        <!-- Amount Column -->
        <template #item.amount="{ item }">
          <div class="text-body-2 font-weight-medium">
            {{ Number(item.amount).toFixed(2) }} MAD
          </div>
        </template>

        <!-- Method Column -->
        <template #item.method="{ item }">
          <VChip variant="tonal" size="small">
            {{ item.method === 'bank_transfer' ? 'Virement' : item.method }}
          </VChip>
        </template>

        <!-- Status Column -->
        <template #item.status="{ item }">
          <WithdrawalStatusBadge :status="item.status" />
        </template>

        <!-- Commission Count Column -->
        <template #item.commission_count="{ item }">
          <div class="text-center">
            <VChip variant="tonal" color="primary" size="small">
              {{ item.commission_count }}
            </VChip>
          </div>
        </template>

        <!-- Created At Column -->
        <template #item.created_at="{ item }">
          <div class="text-body-2">
            {{ new Date(item.created_at).toLocaleDateString() }}
          </div>
          <div class="text-caption text-medium-emphasis">
            {{ new Date(item.created_at).toLocaleTimeString() }}
          </div>
        </template>

        <!-- Actions Column -->
        <template #item.actions="{ item }">
          <div class="d-flex gap-1">
            <!-- View -->
            <ActionIcon
              icon="tabler-eye"
              label="Voir les détails"
              @click="viewWithdrawal(item)"
            />

            <!-- Approve -->
            <ActionIcon
              v-if="item.can_approve"
              icon="tabler-check"
              label="Approuver"
              variant="success"
              @click="openActionDialog(item, 'approve')"
            />

            <!-- Reject -->
            <ActionIcon
              v-if="item.can_reject"
              icon="tabler-x"
              label="Rejeter"
              variant="danger"
              @click="openActionDialog(item, 'reject')"
            />

            <!-- Mark In Payment -->
            <ActionIcon
              v-if="item.can_mark_in_payment"
              icon="tabler-clock"
              label="Marquer en cours de paiement"
              variant="primary"
              @click="openActionDialog(item, 'mark_in_payment')"
            />

            <!-- Mark Paid -->
            <ActionIcon
              v-if="item.can_mark_paid"
              icon="tabler-credit-card"
              label="Marquer comme payé"
              variant="success"
              @click="openActionDialog(item, 'mark_paid')"
            />
          </div>
        </template>

        <!-- No Data -->
        <template #no-data>
          <div class="text-center py-8">
            <VIcon icon="tabler-inbox" size="64" class="mb-4" />
            <h3 class="text-h6 mb-2">Aucun retrait trouvé</h3>
            <p class="text-body-2 text-medium-emphasis mb-4">
              {{ hasFilters ? 'Aucun retrait ne correspond aux filtres appliqués' : 'Aucun retrait n\'a été créé' }}
            </p>
            <VBtn
              v-if="!hasFilters"
              color="primary"
              @click="createWithdrawal"
            >
              Créer le premier retrait
            </VBtn>
          </div>
        </template>
      </VDataTable>
    </VCard>

    <!-- Action Dialog -->
    <WithdrawalActionDialog
      v-if="actionDialog.isVisible && actionDialog.withdrawal"
      :key="(actionDialog.withdrawal?.id || 'new') + '-' + actionDialog.action"
      v-model:is-visible="actionDialog.isVisible"
      :withdrawal="actionDialog.withdrawal"
      :action="actionDialog.action"
      @success="handleActionSuccess"
      @closed="resetActionDialog"
    />
  </div>
</template>
