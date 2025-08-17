<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'
import { useCommissionsStore, type Commission, type CommissionFilters } from '@/stores/admin/commissions'
import { useAuthStore } from '@/stores/auth'
import Breadcrumbs from '@/components/common/Breadcrumbs.vue'
import ConfirmActionDialog from '@/components/common/ConfirmActionDialog.vue'
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
const router = useRouter()
const { showSuccess, showError } = useNotifications()
const { confirm } = useQuickConfirm()

// Store
const commissionsStore = useCommissionsStore()
const { commissions, loading, pagination, filters, summary } = storeToRefs(commissionsStore)
const authStore = useAuthStore()

// Local state
const selectedCommissions = ref<string[]>([])
const showBulkActions = computed(() => selectedCommissions.value.length > 0)

// Computed
const breadcrumbs = computed(() => [
  { title: t('dashboard'), to: '/admin/dashboard' },
  { title: t('commissions'), active: true },
])

// Filters
const statusOptions = [
  { value: '', text: 'Tous les statuts' },
  { value: 'pending_calc', text: 'En calcul' },
  { value: 'calculated', text: 'Calculée' },
  { value: 'eligible', text: 'Éligible' },
  { value: 'approved', text: 'Approuvée' },
  { value: 'rejected', text: 'Rejetée' },
  { value: 'paid', text: 'Payée' },
  { value: 'adjusted', text: 'Ajustée' },
  { value: 'canceled', text: 'Annulée' },
]

const typeOptions = [
  { value: '', text: 'Tous les types' },
  { value: 'sale', text: 'Vente' },
  { value: 'referral', text: 'Parrainage' },
  { value: 'bonus', text: 'Bonus' },
]

// Table headers
const headers = [
  { title: 'Affilié', key: 'affiliate.nom_complet', sortable: true },
  { title: 'Commande', key: 'commande_id', sortable: true },
  { title: 'Type', key: 'type', sortable: true },
  { title: 'Base', key: 'base_amount', sortable: true },
  { title: 'Taux', key: 'rate', sortable: false },
  { title: 'Commission', key: 'amount', sortable: true },
  { title: 'Statut', key: 'status', sortable: true },
  { title: 'Date', key: 'created_at', sortable: true },
  { title: 'Actions', key: 'actions', sortable: false, width: 120 },
]

// Methods
const fetchCommissions = async () => {
  await commissionsStore.fetchCommissions(filters.value)
}

const handleView = async (commission: Commission, event?: Event) => {
  console.log('🔍 handleView called for commission:', commission.id)

  // Prevent any potential event bubbling
  if (event) {
    event.preventDefault()
    event.stopPropagation()
  }

  const targetPath = `/admin/commissions/${commission.id}`
  console.log('🎯 Navigating to:', targetPath)

  try {
    // Clear current commission before navigation
    commissionsStore.clearCurrentCommission()
    
    // Use await for proper error handling
    await router.push(targetPath)
    console.log('✅ Navigation successful')
  } catch (error) {
    console.error('❌ Navigation error:', error)
    showError('Erreur de navigation vers les détails de la commission')
  }
}

const handleApprove = async (commission: Commission) => {
  const confirmed = await confirm({
    title: 'Approuver la commission',
    text: `Êtes-vous sûr de vouloir approuver cette commission de ${commission.amount} ${commission.currency} ?`,
    confirmText: 'Approuver',
    color: 'success',
    type: 'success',
  })

  if (confirmed) {
    const result = await commissionsStore.approveCommission(commission.id)
    if (result.success) {
      showSuccess(result.message)
      await fetchCommissions()
    } else {
      showError(result.message)
    }
  }
}

const handleReject = async (commission: Commission) => {
  const confirmed = await confirm({
    title: 'Rejeter la commission',
    text: `Êtes-vous sûr de vouloir rejeter cette commission de ${commission.amount} ${commission.currency} ?`,
    confirmText: 'Rejeter',
    color: 'error',
    type: 'error',
  })

  if (confirmed) {
    // For now, we'll use a default reason. In a real app, you'd show a dialog to get the reason
    const result = await commissionsStore.rejectCommission(commission.id, 'Rejetée par l\'administrateur')
    if (result.success) {
      showSuccess(result.message)
      await fetchCommissions()
    } else {
      showError(result.message)
    }
  }
}

const handleAdjust = async (commission: Commission) => {
  const confirmed = await confirm({
    title: 'Ajuster la commission',
    text: `Voulez-vous ajuster cette commission de ${commission.amount} ${commission.currency} ?`,
    confirmText: 'Ajuster',
    color: 'warning',
    type: 'warning',
  })

  if (confirmed) {
    // For now, we'll use default values. In a real app, you'd show a dialog to get the new amount and reason
    const newAmount = parseFloat(commission.amount) * 0.8 // Reduce by 20% as example
    const result = await commissionsStore.adjustCommission(commission.id, newAmount, 'Ajustement administratif')
    if (result.success) {
      showSuccess(result.message)
      await fetchCommissions()
    } else {
      showError(result.message)
    }
  }
}

const handleMarkAsPaid = async (commission: Commission) => {
  console.log('handleMarkAsPaid called', { commission, store: commissionsStore })
  console.log('markAsPaid method exists:', typeof commissionsStore.markAsPaid)

  const confirmed = await confirm({
    title: 'Marquer comme payée',
    text: `Confirmer le paiement de cette commission de ${commission.amount} ${commission.currency} ?`,
    confirmText: 'Confirmer le paiement',
    color: 'primary',
    type: 'success',
  })

  if (confirmed) {
    const result = await commissionsStore.markAsPaid(commission.id)
    if (result.success) {
      showSuccess(result.message)
      await fetchCommissions()
    } else {
      showError(result.message)
    }
  }
}

// Bulk actions
const handleBulkApprove = async () => {
  if (selectedCommissions.value.length === 0) return

  const confirmed = await confirm({
    title: 'Approuver les commissions sélectionnées',
    text: `Êtes-vous sûr de vouloir approuver ${selectedCommissions.value.length} commission(s) ?`,
    confirmText: 'Approuver tout',
    color: 'success',
    type: 'success',
  })

  if (confirmed) {
    const result = await commissionsStore.bulkApprove(selectedCommissions.value)
    if (result.success) {
      showSuccess(result.message)
      selectedCommissions.value = []
      await fetchCommissions()
    } else {
      showError(result.message)
    }
  }
}

const handleBulkReject = async () => {
  if (selectedCommissions.value.length === 0) return

  const confirmed = await confirm({
    title: 'Rejeter les commissions sélectionnées',
    text: `Êtes-vous sûr de vouloir rejeter ${selectedCommissions.value.length} commission(s) ?`,
    confirmText: 'Rejeter tout',
    color: 'error',
    type: 'error',
  })

  if (confirmed) {
    const result = await commissionsStore.bulkReject(selectedCommissions.value, 'Rejetées en masse par l\'administrateur')
    if (result.success) {
      showSuccess(result.message)
      selectedCommissions.value = []
      await fetchCommissions()
    } else {
      showError(result.message)
    }
  }
}

// Pagination
const handlePageChange = (page: number) => {
  filters.value.page = page
  fetchCommissions()
}

const handlePerPageChange = (perPage: number) => {
  filters.value.per_page = perPage
  filters.value.page = 1
  fetchCommissions()
}

// Sorting
const handleSort = (field: string) => {
  if (filters.value.sort === field) {
    filters.value.dir = filters.value.dir === 'asc' ? 'desc' : 'asc'
  } else {
    filters.value.sort = field
    filters.value.dir = 'desc'
  }
  fetchCommissions()
}

// Filters
const applyFilters = () => {
  filters.value.page = 1
  fetchCommissions()
}

const resetFilters = () => {
  commissionsStore.resetFilters()
  fetchCommissions()
}

// Watchers
watch(() => filters.value.status, applyFilters)
watch(() => filters.value.type, applyFilters)
watch(() => filters.value.user_id, applyFilters)
watch(() => filters.value.date_from, applyFilters)
watch(() => filters.value.date_to, applyFilters)

// Utility functions
const formatCurrency = (amount: number, currency: string = 'MAD') => {
  return new Intl.NumberFormat('fr-MA', {
    style: 'currency',
    currency: currency,
  }).format(amount)
}

const formatDate = (date: string) => {
  return new Date(date).toLocaleDateString('fr-FR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

const getStatusColor = (status: string) => {
  const colors = {
    pending_calc: 'secondary',
    calculated: 'info',
    eligible: 'primary',
    approved: 'success',
    rejected: 'error',
    paid: 'success',
    adjusted: 'warning',
    canceled: 'secondary',
  }
  return colors[status as keyof typeof colors] || 'secondary'
}

const getStatusText = (status: string) => {
  const texts = {
    pending_calc: 'En calcul',
    calculated: 'Calculée',
    eligible: 'Éligible',
    approved: 'Approuvée',
    rejected: 'Rejetée',
    paid: 'Payée',
    adjusted: 'Ajustée',
    canceled: 'Annulée',
  }
  return texts[status as keyof typeof texts] || status
}

// Lifecycle
onMounted(async () => {
  await fetchCommissions()
  await commissionsStore.fetchSummary()
})
</script>

<template>
  <div>
    <!-- Breadcrumbs -->
    <Breadcrumbs :items="breadcrumbs" />

    <!-- Summary Cards -->
    <VRow class="mb-6">
      <VCol cols="12" sm="6" md="3">
        <VCard>
          <VCardText>
            <div class="d-flex align-center">
              <VAvatar color="info" variant="tonal" class="me-3">
                <VIcon icon="tabler-calculator" />
              </VAvatar>
              <div>
                <p class="text-sm text-medium-emphasis mb-0">Calculées</p>
                <h6 class="text-h6">{{ summary?.count_calculated || 0 }}</h6>
                <p class="text-xs text-success mb-0">{{ summary?.total_calculated || 0 }} MAD</p>
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="3">
        <VCard>
          <VCardText>
            <div class="d-flex align-center">
              <VAvatar color="primary" variant="tonal" class="me-3">
                <VIcon icon="tabler-check" />
              </VAvatar>
              <div>
                <p class="text-sm text-medium-emphasis mb-0">Éligibles</p>
                <h6 class="text-h6">{{ summary?.count_eligible || 0 }}</h6>
                <p class="text-xs text-success mb-0">{{ summary?.total_eligible || 0 }} MAD</p>
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="3">
        <VCard>
          <VCardText>
            <div class="d-flex align-center">
              <VAvatar color="success" variant="tonal" class="me-3">
                <VIcon icon="tabler-thumb-up" />
              </VAvatar>
              <div>
                <p class="text-sm text-medium-emphasis mb-0">Approuvées</p>
                <h6 class="text-h6">{{ summary?.count_approved || 0 }}</h6>
                <p class="text-xs text-success mb-0">{{ summary?.total_approved || 0 }} MAD</p>
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="3">
        <VCard>
          <VCardText>
            <div class="d-flex align-center">
              <VAvatar color="success" variant="tonal" class="me-3">
                <VIcon icon="tabler-cash" />
              </VAvatar>
              <div>
                <p class="text-sm text-medium-emphasis mb-0">Payées</p>
                <h6 class="text-h6">{{ summary?.count_paid || 0 }}</h6>
                <p class="text-xs text-success mb-0">{{ summary?.total_paid || 0 }} MAD</p>
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Filters -->
    <VCard class="mb-6">
      <VCardText>
        <VRow>
          <VCol cols="12" md="4">
            <VTextField
              v-model="filters.q"
              label="Rechercher"
              placeholder="Nom, email de l'affilié..."
              prepend-inner-icon="tabler-search"
              clearable
              hide-details
            />
          </VCol>

          <VCol cols="12" md="3">
            <VSelect
              v-model="filters.status"
              :items="statusOptions"
              item-title="text"
              item-value="value"
              label="Statut"
              multiple
              chips
              clearable
              hide-details
            />
          </VCol>

          <VCol cols="12" md="2">
            <VCheckbox
              v-model="filters.eligible_only"
              label="Éligibles uniquement"
              hide-details
            />
          </VCol>

          <VCol cols="12" md="3" class="d-flex align-center gap-2">
            <VBtn
              color="primary"
              variant="elevated"
              @click="applyFilters"
            >
              Filtrer
            </VBtn>
            <VBtn
              color="secondary"
              variant="outlined"
              @click="resetFilters"
            >
              Réinitialiser
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Bulk Actions -->
    <VExpandTransition>
      <VCard v-if="selectedCommissions.length > 0" class="mb-6" color="primary" variant="tonal">
        <VCardText>
          <div class="d-flex align-center justify-space-between">
            <div class="d-flex align-center">
              <VIcon icon="tabler-check" class="me-2" />
              <span class="font-weight-medium">
                {{ selectedCommissions.length }} commission(s) sélectionnée(s)
              </span>
            </div>

            <div class="d-flex gap-2">
              <VBtn
                color="success"
                variant="elevated"
                size="small"
                @click="handleBulkApprove"
              >
                Approuver tout
              </VBtn>
              <VBtn
                color="error"
                variant="elevated"
                size="small"
                @click="handleBulkReject"
              >
                Rejeter tout
              </VBtn>
            </div>
          </div>
        </VCardText>
      </VCard>
    </VExpandTransition>

    <!-- Data Table -->
    <VCard>
      <VDataTableServer
        v-model="selectedCommissions"
        :headers="headers"
        :items="commissions"
        :loading="loading"
        :items-length="pagination.total"
        :items-per-page="pagination.per_page"
        :page="pagination.current_page"
        show-select
        item-value="id"
        @update:page="handlePageChange"
        @update:items-per-page="handlePerPageChange"
        @update:sort-by="handleSort"
      >
        <!-- Affiliate Column -->
        <template #item.affiliate.nom_complet="{ item }">
          <div class="d-flex align-center">
            <VAvatar size="32" class="me-3">
              <VIcon icon="tabler-user" />
            </VAvatar>
            <div>
              <div class="font-weight-medium">{{ item.affiliate?.nom_complet || 'N/A' }}</div>
              <div class="text-caption text-medium-emphasis">{{ item.affiliate?.email }}</div>
            </div>
          </div>
        </template>

        <!-- Order Column -->
        <template #item.commande_id="{ item }">
          <VChip
            :to="`/admin/orders/${item.commande_id}`"
            color="primary"
            variant="outlined"
            size="small"
          >
            {{ item.commande_id?.slice(-8) }}
          </VChip>
        </template>

        <!-- Base Amount Column -->
        <template #item.base_amount="{ item }">
          <span class="font-weight-medium">
            {{ formatCurrency(item.base_amount, item.currency) }}
          </span>
        </template>

        <!-- Rate Column -->
        <template #item.rate="{ item }">
          <span v-if="item.rate">{{ item.rate }}%</span>
          <VChip v-else color="info" variant="tonal" size="small">
            Fixe
          </VChip>
        </template>

        <!-- Amount Column -->
        <template #item.amount="{ item }">
          <span class="font-weight-bold text-success">
            {{ formatCurrency(item.amount, item.currency) }}
          </span>
        </template>

        <!-- Status Column -->
        <template #item.status="{ item }">
          <VChip
            :color="getStatusColor(item.status)"
            variant="tonal"
            size="small"
          >
            {{ getStatusText(item.status) }}
          </VChip>
        </template>

        <!-- Created At Column -->
        <template #item.created_at="{ item }">
          <span class="text-caption">{{ formatDate(item.created_at) }}</span>
        </template>

        <!-- Actions Column -->
        <template #item.actions="{ item }">
          <VMenu>
            <template #activator="{ props }">
              <VBtn
                icon="tabler-dots-vertical"
                size="small"
                variant="text"
                v-bind="props"
              />
            </template>

            <VList>
              <!-- View Action -->
              <VListItem @click="handleView(item)">
                <template #prepend>
                  <VIcon icon="tabler-eye" size="20" />
                </template>
                <VListItemTitle>Voir détails</VListItemTitle>
              </VListItem>

              <!-- Approve Action -->
              <VListItem
                v-if="item.can_be_approved"
                @click="handleApprove(item)"
              >
                <template #prepend>
                  <VIcon icon="tabler-check" size="20" color="success" />
                </template>
                <VListItemTitle>Approuver</VListItemTitle>
              </VListItem>

              <!-- Reject Action -->
              <VListItem
                v-if="item.can_be_rejected"
                @click="handleReject(item)"
              >
                <template #prepend>
                  <VIcon icon="tabler-x" size="20" color="error" />
                </template>
                <VListItemTitle>Rejeter</VListItemTitle>
              </VListItem>

              <!-- Adjust Action -->
              <VListItem
                v-if="item.can_be_adjusted"
                @click="handleAdjust(item)"
              >
                <template #prepend>
                  <VIcon icon="tabler-edit" size="20" color="warning" />
                </template>
                <VListItemTitle>Ajuster</VListItemTitle>
              </VListItem>

              <!-- Mark as Paid Action -->
              <VListItem
                v-if="item.status === 'approved' && !item.paid_at"
                @click="handleMarkAsPaid(item)"
              >
                <template #prepend>
                  <VIcon icon="tabler-cash" size="20" color="primary" />
                </template>
                <VListItemTitle>Marquer comme payée</VListItemTitle>
              </VListItem>
            </VList>
          </VMenu>
        </template>
      </VDataTableServer>
    </VCard>
  </div>
</template>
