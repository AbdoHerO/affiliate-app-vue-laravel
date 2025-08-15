<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { usePreordersStore } from '@/stores/admin/preorders'
import { useQuickConfirm } from '@/composables/useConfirmAction'
import { useNotifications } from '@/composables/useNotifications'
import ConfirmActionDialog from '@/components/common/ConfirmActionDialog.vue'

definePage({
  meta: {
    requiresAuth: true,
    requiresRole: 'admin',
  },
})

const router = useRouter()
const preordersStore = usePreordersStore()
const {
  confirm,
  isDialogVisible: isConfirmDialogVisible,
  isLoading: isConfirmLoading,
  dialogTitle,
  dialogText,
  dialogIcon,
  dialogColor,
  confirmButtonText,
  cancelButtonText,
  handleConfirm,
  handleCancel
} = useQuickConfirm()
const { showSuccess, showError, snackbar } = useNotifications()

// Local state
const searchQuery = ref('')
const selectedStatus = ref('')
const selectedAffiliate = ref('')
const selectedBoutique = ref('')
const dateFrom = ref('')
const dateTo = ref('')
const itemsPerPage = ref(15)

// Bulk selection state
const selectedOrders = ref<string[]>([])
const selectAll = ref(false)
const bulkActionLoading = ref(false)
const bulkStatusValue = ref('')

// Computed
const isLoading = computed(() => preordersStore.isLoading)
const preorders = computed(() => preordersStore.preorders)
const pagination = computed(() => preordersStore.pagination)

// Table headers
const headers = [
  { title: '', key: 'select', sortable: false, width: '50px' },
  { title: 'Code', key: 'id', sortable: true },
  { title: 'Client', key: 'client', sortable: false },
  { title: 'Affilié', key: 'affilie', sortable: false },
  { title: 'Boutique', key: 'boutique', sortable: false },
  { title: 'Total', key: 'total_ttc', sortable: true },
  { title: 'No Answer', key: 'no_answer_count', sortable: true, width: '100px' },
  { title: 'Statut', key: 'statut', sortable: true },
  { title: 'Expédition', key: 'shipping', sortable: false, width: '120px' },
  { title: 'Date', key: 'created_at', sortable: true },
  { title: 'Actions', key: 'actions', sortable: false, width: '150px' },
]

// Status options
const statusOptions = [
  { title: 'Tous', value: '' },
  { title: 'En attente', value: 'en_attente' },
  { title: 'Confirmée', value: 'confirmee' },
  { title: 'Injoignable', value: 'injoignable' },
  { title: 'Refusée', value: 'refusee' },
  { title: 'Annulée', value: 'annulee' },
]

// Methods
const fetchPreorders = async () => {
  await preordersStore.fetchPreorders({
    q: searchQuery.value || undefined,
    statut: selectedStatus.value || undefined,
    affilie_id: selectedAffiliate.value || undefined,
    boutique_id: selectedBoutique.value || undefined,
    from: dateFrom.value || undefined,
    to: dateTo.value || undefined,
    perPage: itemsPerPage.value,
  })
}



const handleSearch = () => {
  preordersStore.fetchPreorders({
    page: 1,
    q: searchQuery.value || undefined,
    statut: selectedStatus.value || undefined,
    affilie_id: selectedAffiliate.value || undefined,
    boutique_id: selectedBoutique.value || undefined,
    from: dateFrom.value || undefined,
    to: dateTo.value || undefined,
    perPage: itemsPerPage.value,
  })
}

const handlePageChange = (page: number) => {
  preordersStore.fetchPreorders({ page })
}

const handleSort = (sortBy: any) => {
  if (sortBy.length > 0) {
    preordersStore.fetchPreorders({
      sort: sortBy[0].key,
      dir: sortBy[0].order,
    })
  }
}

const viewPreorder = (preorder: any) => {
  router.push({ name: 'admin-orders-pre-id', params: { id: preorder.id } })
}



const getStatusColor = (status: string) => {
  switch (status) {
    case 'en_attente':
      return 'warning'
    case 'confirmee':
      return 'success'
    case 'injoignable':
      return 'orange'
    case 'refusee':
      return 'error'
    case 'annulee':
      return 'secondary'
    default:
      return 'default'
  }
}

const getStatusText = (status: string) => {
  switch (status) {
    case 'en_attente':
      return 'En attente'
    case 'confirmee':
      return 'Confirmée'
    case 'injoignable':
      return 'Injoignable'
    case 'refusee':
      return 'Refusée'
    case 'annulee':
      return 'Annulée'
    default:
      return status
  }
}

const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('fr-MA', {
    style: 'currency',
    currency: 'MAD',
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

const resetFilters = () => {
  searchQuery.value = ''
  selectedStatus.value = ''
  selectedAffiliate.value = ''
  selectedBoutique.value = ''
  dateFrom.value = ''
  dateTo.value = ''
  selectedOrders.value = []
  selectAll.value = false
  bulkStatusValue.value = ''
  preordersStore.resetFilters()
  fetchPreorders()
}

// Selection handlers
const toggleSelectAll = () => {
  if (selectAll.value) {
    selectedOrders.value = preorders.value.map(order => order.id)
  } else {
    selectedOrders.value = []
  }
}

const toggleOrderSelection = (orderId: string) => {
  const index = selectedOrders.value.indexOf(orderId)
  if (index > -1) {
    selectedOrders.value.splice(index, 1)
  } else {
    selectedOrders.value.push(orderId)
  }

  // Update select all state
  selectAll.value = selectedOrders.value.length === preorders.value.length
}

// Bulk actions
const bulkChangeStatus = async (status: string) => {
  if (selectedOrders.value.length === 0) return

  const confirmed = await confirm({
    title: 'Changer le statut',
    text: `Êtes-vous sûr de vouloir changer le statut de ${selectedOrders.value.length} commande(s) ?`,
    confirmText: 'Confirmer',
    cancelText: 'Annuler'
  })

  if (!confirmed) {
    // Reset dropdown value if user cancels
    bulkStatusValue.value = ''
    return
  }

  bulkActionLoading.value = true
  try {
    const result = await preordersStore.bulkChangeStatus(selectedOrders.value, status)
    const statusText = getStatusText(status)
    showSuccess(result.message || `${selectedOrders.value.length} commande(s) mise(s) à jour vers "${statusText}"`)
    selectedOrders.value = []
    selectAll.value = false
    bulkStatusValue.value = '' // Reset dropdown after success
  } catch (error: any) {
    showError(error.message || 'Erreur lors du changement de statut')
    bulkStatusValue.value = '' // Reset dropdown on error
    console.error('Bulk status change error:', error)
  } finally {
    bulkActionLoading.value = false
  }
}

const bulkSendToShipping = async () => {
  if (selectedOrders.value.length === 0) return

  const confirmed = await confirm({
    title: 'Envoyer vers OzonExpress',
    text: `Êtes-vous sûr de vouloir envoyer ${selectedOrders.value.length} commande(s) vers OzonExpress ?`,
    confirmText: 'Envoyer',
    cancelText: 'Annuler'
  })

  if (!confirmed) return

  bulkActionLoading.value = true
  try {
    const result = await preordersStore.bulkSendToShipping(selectedOrders.value)

    // Show detailed feedback based on results
    if (result.summary) {
      const { total, success, errors } = result.summary
      if (errors > 0) {
        showError(`${success}/${total} commandes envoyées avec succès. ${errors} erreur(s).`)
      } else {
        showSuccess(`${success} commande(s) envoyée(s) vers OzonExpress avec succès.`)
      }
    } else {
      showSuccess(result.message || 'Commandes envoyées vers OzonExpress')
    }

    selectedOrders.value = []
    selectAll.value = false
    bulkStatusValue.value = ''
  } catch (error: any) {
    showError(error.message || 'Erreur lors de l\'envoi vers OzonExpress')
    console.error('Bulk shipping error:', error)
  } finally {
    bulkActionLoading.value = false
  }
}

// Quick actions
const quickChangeStatus = async (orderId: string, status: string) => {
  try {
    const result = await preordersStore.changeStatus(orderId, status)
    showSuccess(result.message)
  } catch (error: any) {
    showError(error.message)
  }
}

const quickInjoignable = async (orderId: string) => {
  try {
    // Call changeStatus with increment flag for injoignable
    const result = await preordersStore.changeStatus(orderId, 'injoignable', undefined, true)
    showSuccess(result.message)
  } catch (error: any) {
    showError(error.message)
  }
}

const quickSendToShipping = async (orderId: string) => {
  const confirmed = await confirm({
    title: 'Envoyer vers OzonExpress',
    text: 'Êtes-vous sûr de vouloir envoyer cette commande vers OzonExpress ?',
    confirmText: 'Envoyer',
    cancelText: 'Annuler'
  })

  if (!confirmed) return

  try {
    const result = await preordersStore.sendToShipping(orderId)
    showSuccess(result.message)
  } catch (error: any) {
    showError(error.message || 'Erreur lors de l\'envoi vers OzonExpress')
    console.error('Individual shipping error:', error)
  }
}

// Lifecycle
onMounted(() => {
  fetchPreorders()
})
</script>

<template>
  <div class="d-flex flex-column gap-6">
    <!-- Page Header -->
    <div class="d-flex justify-space-between align-center mb-6">
      <div>
        <h1 class="text-h4 font-weight-bold mb-1">
          Pré-commandes
        </h1>
        <p class="text-body-1 mb-0">
          Gestion des commandes en attente d'expédition
        </p>
      </div>
      <VBtn
        color="primary"
        variant="elevated"
        @click="resetFilters"
      >
        <VIcon start icon="tabler-refresh" />
        Actualiser
      </VBtn>
    </div>

    <!-- Filters Card -->
    <VCard class="mb-6">
      <VCardText>
        <VRow>
          <VCol cols="12" md="3">
            <VTextField
              v-model="searchQuery"
              label="Rechercher..."
              placeholder="Client, téléphone, affilié..."
              prepend-inner-icon="tabler-search"
              clearable
              @input="handleSearch"
            />
          </VCol>
          <VCol cols="12" md="2">
            <VSelect
              v-model="selectedStatus"
              label="Statut"
              :items="statusOptions"
              clearable
              @update:model-value="handleSearch"
            />
          </VCol>
          <VCol cols="12" md="2">
            <VTextField
              v-model="dateFrom"
              label="Date début"
              type="date"
              @change="handleSearch"
            />
          </VCol>
          <VCol cols="12" md="2">
            <VTextField
              v-model="dateTo"
              label="Date fin"
              type="date"
              @change="handleSearch"
            />
          </VCol>
          <VCol cols="12" md="2">
            <VSelect
              v-model="itemsPerPage"
              label="Par page"
              :items="[10, 15, 25, 50]"
              @update:model-value="handleSearch"
            />
          </VCol>
          <VCol cols="12" md="1">
            <VBtn
              color="secondary"
              variant="outlined"
              block
              @click="resetFilters"
            >
              <VIcon icon="tabler-filter-off" />
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Bulk Actions Toolbar -->
    <VCard v-if="selectedOrders.length > 0" class="mb-4">
      <VCardText>
        <div class="d-flex align-center gap-4">
          <VIcon icon="tabler-check" color="primary" />
          <span class="text-body-1 font-weight-medium">
            {{ selectedOrders.length }} commande(s) sélectionnée(s)
          </span>

          <VSpacer />

          <VBtn
            color="primary"
            variant="elevated"
            :loading="bulkActionLoading"
            @click="bulkSendToShipping"
          >
            <VIcon start icon="tabler-truck" />
            Envoyer OzonExpress
          </VBtn>

          <VSelect
            v-model="bulkStatusValue"
            label="Changer statut"
            placeholder="Sélectionner un statut"
            :items="[
              { title: 'Confirmée', value: 'confirmee' },
              { title: 'Injoignable', value: 'injoignable' },
              { title: 'Refusée', value: 'refusee' },
              { title: 'Annulée', value: 'annulee' }
            ]"
            style="min-width: 180px"
            :loading="bulkActionLoading"
            :disabled="bulkActionLoading"
            clearable
            variant="outlined"
            density="compact"
            @update:model-value="(value) => value && bulkChangeStatus(value)"
          />

          <VBtn
            color="error"
            variant="outlined"
            @click="selectedOrders = []; selectAll = false"
          >
            <VIcon icon="tabler-x" />
          </VBtn>
        </div>
      </VCardText>
    </VCard>

    <!-- Data Table -->
    <VCard>
      <VDataTableServer
        v-model:items-per-page="itemsPerPage"
        :headers="headers"
        :items="preorders"
        :items-length="pagination.total"
        :loading="isLoading"
        :page="pagination.current_page"
        @update:page="handlePageChange"
        @update:sort-by="handleSort"
      >
        <!-- Header Select All -->
        <template #header.select>
          <VCheckbox
            v-model="selectAll"
            @update:model-value="toggleSelectAll"
          />
        </template>

        <!-- Select Column -->
        <template #item.select="{ item }">
          <VCheckbox
            :model-value="selectedOrders.includes(item.id)"
            @update:model-value="toggleOrderSelection(item.id)"
          />
        </template>

        <!-- Client Column -->
        <template #item.client="{ item }">
          <div>
            <div class="font-weight-medium">
              {{ item.client.nom_complet }}
            </div>
            <div class="text-caption text-medium-emphasis">
              {{ item.client.telephone }}
            </div>
          </div>
        </template>

        <!-- Affiliate Column -->
        <template #item.affilie="{ item }">
          <div>
            <div class="font-weight-medium">
              {{ item.affiliate?.nom_complet || 'N/A' }}
            </div>
            <div class="text-caption text-medium-emphasis">
              {{ item.affiliate?.email || 'N/A' }}
            </div>
          </div>
        </template>

        <!-- Boutique Column -->
        <template #item.boutique="{ item }">
          <VChip
            size="small"
            color="info"
            variant="tonal"
          >
            {{ item.boutique.nom }}
          </VChip>
        </template>

        <!-- Total Column -->
        <template #item.total_ttc="{ item }">
          <div class="font-weight-bold">
            {{ formatCurrency(item.total_ttc) }}
          </div>
        </template>

        <!-- No Answer Count Column -->
        <template #item.no_answer_count="{ item }">
          <VBadge
            v-if="item.no_answer_count > 0"
            :content="item.no_answer_count"
            color="warning"
            inline
          >
            <VIcon icon="tabler-phone-off" size="20" />
          </VBadge>
          <span v-else class="text-medium-emphasis">0</span>
        </template>

        <!-- Status Column -->
        <template #item.statut="{ item }">
          <VChip
            size="small"
            :color="getStatusColor(item.statut)"
            variant="tonal"
          >
            {{ getStatusText(item.statut) }}
          </VChip>
        </template>

        <!-- Shipping Column -->
        <template #item.shipping="{ item }">
          <div v-if="item.shipping_parcel" class="d-flex align-center gap-1">
            <VIcon icon="tabler-truck" color="success" size="16" />
            <VTooltip activator="parent" location="top">
              Tracking: {{ item.shipping_parcel.tracking_number }}
            </VTooltip>
            <span class="text-caption text-success">Expédié</span>
          </div>
          <div v-else class="text-caption text-medium-emphasis">
            Non expédié
          </div>
        </template>

        <!-- Date Column -->
        <template #item.created_at="{ item }">
          <div class="text-body-2">
            {{ formatDate(item.created_at) }}
          </div>
        </template>

        <!-- Actions Column -->
        <template #item.actions="{ item }">
          <div class="d-flex gap-1">
            <!-- View Details -->
            <VBtn
              size="small"
              color="primary"
              variant="text"
              icon="tabler-eye"
              @click="viewPreorder(item)"
            />

            <!-- Quick Status Actions (only if not shipped) -->
            <VMenu v-if="!item.shipping_parcel">
              <template #activator="{ props }">
                <VBtn
                  size="small"
                  color="secondary"
                  variant="text"
                  icon="tabler-dots-vertical"
                  v-bind="props"
                />
              </template>

              <VList>
                <!-- Always show Confirmée (unless already confirmed) -->
                <VListItem
                  v-if="item.statut !== 'confirmee'"
                  @click="quickChangeStatus(item.id, 'confirmee')"
                >
                  <VListItemTitle>
                    <VIcon start icon="tabler-check" color="success" />
                    Confirmer
                  </VListItemTitle>
                </VListItem>

                <!-- Always show Injoignable (can increment multiple times) -->
                <VListItem
                  @click="quickInjoignable(item.id)"
                >
                  <VListItemTitle>
                    <VIcon start icon="tabler-phone-off" color="warning" />
                    Injoignable (+1)
                  </VListItemTitle>
                </VListItem>

                <!-- Always show Refusée (unless already refused) -->
                <VListItem
                  v-if="item.statut !== 'refusee'"
                  @click="quickChangeStatus(item.id, 'refusee')"
                >
                  <VListItemTitle>
                    <VIcon start icon="tabler-x" color="error" />
                    Refuser
                  </VListItemTitle>
                </VListItem>

                <!-- Always show Annulée (unless already cancelled) -->
                <VListItem
                  v-if="item.statut !== 'annulee'"
                  @click="quickChangeStatus(item.id, 'annulee')"
                >
                  <VListItemTitle>
                    <VIcon start icon="tabler-ban" color="error" />
                    Annuler
                  </VListItemTitle>
                </VListItem>

                <VDivider v-if="item.statut === 'confirmee' && !item.shipping_parcel" />

                <VListItem
                  v-if="item.statut === 'confirmee' && !item.shipping_parcel"
                  @click="quickSendToShipping(item.id)"
                >
                  <VListItemTitle>
                    <VIcon start icon="tabler-truck" color="info" />
                    Envoyer OzonExpress
                  </VListItemTitle>
                </VListItem>
              </VList>
            </VMenu>
          </div>
        </template>

        <!-- No data -->
        <template #no-data>
          <div class="text-center py-8">
            <VIcon
              icon="tabler-package-off"
              size="64"
              class="mb-4"
              color="disabled"
            />
            <h3 class="text-h6 mb-2">Aucune pré-commande</h3>
            <p class="text-body-2 text-medium-emphasis">
              Aucune commande en attente d'expédition trouvée
            </p>
          </div>
        </template>
      </VDataTableServer>
    </VCard>

    <!-- Confirm Dialog -->
    <ConfirmActionDialog
      :is-dialog-visible="isConfirmDialogVisible"
      :is-loading="isConfirmLoading"
      :dialog-title="dialogTitle"
      :dialog-text="dialogText"
      :dialog-icon="dialogIcon"
      :dialog-color="dialogColor"
      :confirm-button-text="confirmButtonText"
      :cancel-button-text="cancelButtonText"
      @confirm="handleConfirm"
      @cancel="handleCancel"
    />

    <!-- Success/Error Snackbar -->
    <VSnackbar
      v-model="snackbar.show"
      :color="snackbar.color"
      :timeout="snackbar.timeout"
      location="top end"
    >
      {{ snackbar.message }}
    </VSnackbar>
  </div>
</template>
