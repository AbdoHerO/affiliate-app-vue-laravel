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

// Local state
const selectedCommissions = ref<string[]>([])
const showRejectDialog = ref(false)
const showAdjustDialog = ref(false)
const showBulkRejectDialog = ref(false)
const currentCommission = ref<Commission | null>(null)
const rejectReason = ref('')
const adjustAmount = ref(0)
const adjustNote = ref('')
const bulkRejectReason = ref('')

// Computed
const breadcrumbs = computed(() => [
  { title: t('dashboard'), to: '/admin/dashboard' },
  { title: t('commissions'), to: '/admin/commissions' },
])

const statusOptions = [
  { value: 'pending_calc', title: 'En calcul', color: 'secondary' },
  { value: 'calculated', title: 'Calculée', color: 'info' },
  { value: 'eligible', title: 'Éligible', color: 'primary' },
  { value: 'approved', title: 'Approuvée', color: 'success' },
  { value: 'rejected', title: 'Rejetée', color: 'error' },
  { value: 'paid', title: 'Payée', color: 'success' },
  { value: 'adjusted', title: 'Ajustée', color: 'warning' },
  { value: 'canceled', title: 'Annulée', color: 'secondary' },
]

const headers = [
  { title: 'ID', key: 'id', sortable: true },
  { title: 'Affilié', key: 'affiliate.nom_complet', sortable: false },
  { title: 'Commande', key: 'commande_id', sortable: true },
  { title: 'Montant de base', key: 'base_amount', sortable: true },
  { title: 'Taux', key: 'rate', sortable: false },
  { title: 'Montant', key: 'amount', sortable: true },
  { title: 'Statut', key: 'status', sortable: true },
  { title: 'Éligible le', key: 'eligible_at', sortable: true },
  { title: 'Approuvée le', key: 'approved_at', sortable: true },
  { title: 'Actions', key: 'actions', sortable: false },
]

// Methods
const fetchCommissions = async (page = 1) => {
  await commissionsStore.fetchCommissions({ ...filters.value, page })
}

const handleTableUpdate = async (options: any) => {
  const { page, itemsPerPage, sortBy } = options

  const newFilters: Partial<CommissionFilters> = {
    page,
    per_page: itemsPerPage,
  }

  if (sortBy && sortBy.length > 0) {
    newFilters.sort = sortBy[0].key
    newFilters.dir = sortBy[0].order
  }

  await commissionsStore.fetchCommissions(newFilters)
}

const handleView = (commission: Commission, event?: Event) => {
  console.log('🔍 handleView called for commission:', commission.id)

  // Prevent any potential event bubbling
  if (event) {
    event.preventDefault()
    event.stopPropagation()
  }

  const targetPath = `/admin/commissions/${commission.id}`
  console.log('🎯 Navigating to:', targetPath)

  try {
    // Simple router push
    router.push(targetPath)
    console.log('✅ Navigation initiated')
  } catch (error) {
    console.error('❌ Navigation error:', error)
    showError('Erreur de navigation vers les détails de la commission')
  }
}

const handleApprove = async (commission: Commission) => {
  console.log('✅ handleApprove called for commission:', commission.id)

  try {
    const confirmed = await confirm({
      title: 'Approuver la commission',
      text: `Êtes-vous sûr de vouloir approuver cette commission de ${commission.amount} ${commission.currency} ?`,
      confirmText: 'Approuver',
      color: 'success',
      type: 'success',
    })

    console.log('🔔 Confirm dialog result:', confirmed)

    if (confirmed) {
      console.log('🚀 Calling approveCommission...')
      const result = await commissionsStore.approveCommission(commission.id)
      console.log('📊 Result:', result)

      if (result.success) {
        showSuccess(result.message)
        await fetchCommissions()
      } else {
        showError(result.message)
      }
    }
  } catch (error) {
    console.error('❌ Error in handleApprove:', error)
    showError('Erreur lors de l\'approbation de la commission')
  }
}

const openRejectDialog = (commission: Commission) => {
  currentCommission.value = commission
  rejectReason.value = ''
  showRejectDialog.value = true
}

const handleReject = async () => {
  if (!currentCommission.value || !rejectReason.value.trim()) return

  const result = await commissionsStore.rejectCommission(currentCommission.value.id, rejectReason.value)
  if (result.success) {
    showSuccess(result.message)
    showRejectDialog.value = false
  } else {
    showError(result.message)
  }
}

const openAdjustDialog = (commission: Commission) => {
  currentCommission.value = commission
  adjustAmount.value = commission.amount
  adjustNote.value = ''
  showAdjustDialog.value = true
}

const handleAdjust = async () => {
  if (!currentCommission.value || !adjustNote.value.trim()) return

  const result = await commissionsStore.adjustCommission(
    currentCommission.value.id,
    adjustAmount.value,
    adjustNote.value
  )
  if (result.success) {
    showSuccess(result.message)
    showAdjustDialog.value = false
  } else {
    showError(result.message)
  }
}

const handleBulkApprove = async () => {
  if (selectedCommissions.value.length === 0) return

  const confirmed = await confirm({
    title: 'Approbation en lot',
    text: `Approuver ${selectedCommissions.value.length} commission(s) sélectionnée(s) ?`,
    confirmText: 'Approuver tout',
    color: 'success',
    type: 'success',
  })

  if (confirmed) {
    const result = await commissionsStore.bulkApprove(selectedCommissions.value)
    if (result.success) {
      showSuccess(result.message)
      selectedCommissions.value = []
    } else {
      showError(result.message)
    }
  }
}

const openBulkRejectDialog = () => {
  if (selectedCommissions.value.length === 0) return
  bulkRejectReason.value = ''
  showBulkRejectDialog.value = true
}

const handleBulkReject = async () => {
  if (!bulkRejectReason.value.trim()) return

  const result = await commissionsStore.bulkReject(selectedCommissions.value, bulkRejectReason.value)
  if (result.success) {
    showSuccess(result.message)
    showBulkRejectDialog.value = false
    selectedCommissions.value = []
  } else {
    showError(result.message)
  }
}

const handleExport = async () => {
  const result = await commissionsStore.exportCommissions()
  if (result.success) {
    showSuccess(result.message)
  } else {
    showError(result.message)
  }
}

const clearFilters = () => {
  commissionsStore.resetFilters()
  fetchCommissions(1)
}

// Watchers
let searchTimeout: NodeJS.Timeout
watch(() => filters.value.q, () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    fetchCommissions(1)
  }, 500)
})

watch(() => [filters.value.status, filters.value.eligible_only], () => {
  fetchCommissions(1)
})

// Load data on mount
onMounted(async () => {
  await Promise.all([
    fetchCommissions(),
    commissionsStore.fetchSummary()
  ])
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
          {{ t('commissions') }}
        </h1>
        <p class="text-body-1 mb-0">
          Gérez les commissions des affiliés
        </p>
      </div>

      <div class="d-flex gap-3">
        <VBtn
          color="primary"
          variant="outlined"
          prepend-icon="tabler-download"
          @click="handleExport"
        >
          Exporter
        </VBtn>
      </div>
    </div>

    <!-- Summary Cards -->
    <VRow v-if="summary" class="mb-6">
      <VCol cols="12" sm="6" md="3">
        <VCard>
          <VCardText>
            <div class="d-flex align-center">
              <VAvatar color="info" variant="tonal" class="me-3">
                <VIcon icon="tabler-calculator" />
              </VAvatar>
              <div>
                <p class="text-sm text-medium-emphasis mb-0">Calculées</p>
                <h6 class="text-h6">{{ summary.count_calculated }}</h6>
                <p class="text-xs text-success mb-0">{{ summary.total_calculated }} MAD</p>
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
                <h6 class="text-h6">{{ summary.count_eligible }}</h6>
                <p class="text-xs text-success mb-0">{{ summary.total_eligible }} MAD</p>
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
                <h6 class="text-h6">{{ summary.count_approved }}</h6>
                <p class="text-xs text-success mb-0">{{ summary.total_approved }} MAD</p>
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
                <h6 class="text-h6">{{ summary.count_paid }}</h6>
                <p class="text-xs text-success mb-0">{{ summary.total_paid }} MAD</p>
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
              item-title="title"
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
              color="secondary"
              variant="outlined"
              @click="clearFilters"
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
                @click="openBulkRejectDialog"
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
        @update:options="handleTableUpdate"
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
            {{ item.base_amount }} {{ item.currency }}
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
            {{ item.amount }} {{ item.currency }}
          </span>
        </template>

        <!-- Status Column -->
        <template #item.status="{ item }">
          <VChip
            :color="item.status_badge.color"
            variant="tonal"
            size="small"
          >
            {{ item.status_badge.text }}
          </VChip>
        </template>

        <!-- Eligible At Column -->
        <template #item.eligible_at="{ item }">
          <span v-if="item.eligible_at" class="text-caption">
            {{ new Date(item.eligible_at).toLocaleDateString('fr-FR') }}
          </span>
          <span v-else class="text-medium-emphasis">-</span>
        </template>

        <!-- Approved At Column -->
        <template #item.approved_at="{ item }">
          <span v-if="item.approved_at" class="text-caption">
            {{ new Date(item.approved_at).toLocaleDateString('fr-FR') }}
          </span>
          <span v-else class="text-medium-emphasis">-</span>
        </template>

        <!-- Actions Column -->
        <template #item.actions="{ item }">
          <div class="d-flex gap-1">
            <ActionIcon
              icon="tabler-eye"
              label="actions.view"
              variant="default"
              @click="handleView(item)"
            />

            <ActionIcon
              v-if="item.can_be_approved"
              icon="tabler-check"
              label="actions.approve"
              variant="success"
              @click="handleApprove(item)"
            />

            <ActionIcon
              v-if="item.can_be_rejected"
              icon="tabler-x"
              label="actions.reject"
              variant="danger"
              @click="openRejectDialog(item)"
            />

            <ActionIcon
              v-if="item.can_be_adjusted"
              icon="tabler-edit"
              label="actions.adjust"
              variant="warning"
              @click="openAdjustDialog(item)"
            />
          </div>
        </template>

        <!-- No Data -->
        <template #no-data>
          <div class="text-center py-8">
            <VIcon icon="tabler-percentage" size="64" class="mb-4" color="disabled" />
            <h3 class="text-h6 mb-2">Aucune commission trouvée</h3>
            <p class="text-body-2 text-medium-emphasis">
              Aucune commission ne correspond aux critères de recherche.
            </p>
          </div>
        </template>
      </VDataTableServer>
    </VCard>

    <!-- Reject Dialog -->
    <VDialog v-model="showRejectDialog" max-width="500">
      <VCard>
        <VCardTitle>
          <span class="text-h6">Rejeter la commission</span>
        </VCardTitle>

        <VCardText>
          <p class="mb-4">
            Vous êtes sur le point de rejeter cette commission. Veuillez indiquer la raison :
          </p>

          <VTextarea
            v-model="rejectReason"
            label="Raison du rejet"
            placeholder="Expliquez pourquoi cette commission est rejetée..."
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
            Annuler
          </VBtn>
          <VBtn
            color="error"
            variant="elevated"
            :disabled="!rejectReason.trim()"
            @click="handleReject"
          >
            Rejeter
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Adjust Dialog -->
    <VDialog v-model="showAdjustDialog" max-width="500">
      <VCard>
        <VCardTitle>
          <span class="text-h6">Ajuster la commission</span>
        </VCardTitle>

        <VCardText>
          <p class="mb-4">
            Montant actuel : <strong>{{ currentCommission?.amount }} {{ currentCommission?.currency }}</strong>
          </p>

          <VTextField
            v-model.number="adjustAmount"
            label="Nouveau montant"
            type="number"
            step="0.01"
            min="0"
            suffix="MAD"
            required
            class="mb-4"
          />

          <VTextarea
            v-model="adjustNote"
            label="Raison de l'ajustement"
            placeholder="Expliquez pourquoi cette commission est ajustée..."
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
            Annuler
          </VBtn>
          <VBtn
            color="warning"
            variant="elevated"
            :disabled="!adjustNote.trim()"
            @click="handleAdjust"
          >
            Ajuster
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Bulk Reject Dialog -->
    <VDialog v-model="showBulkRejectDialog" max-width="500">
      <VCard>
        <VCardTitle>
          <span class="text-h6">Rejeter les commissions sélectionnées</span>
        </VCardTitle>

        <VCardText>
          <p class="mb-4">
            Vous êtes sur le point de rejeter {{ selectedCommissions.length }} commission(s).
            Veuillez indiquer la raison :
          </p>

          <VTextarea
            v-model="bulkRejectReason"
            label="Raison du rejet"
            placeholder="Expliquez pourquoi ces commissions sont rejetées..."
            rows="3"
            required
          />
        </VCardText>

        <VCardActions>
          <VSpacer />
          <VBtn
            color="grey"
            variant="text"
            @click="showBulkRejectDialog = false"
          >
            Annuler
          </VBtn>
          <VBtn
            color="error"
            variant="elevated"
            :disabled="!bulkRejectReason.trim()"
            @click="handleBulkReject"
          >
            Rejeter tout
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
