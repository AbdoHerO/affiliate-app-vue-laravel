<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { usePreordersStore } from '@/stores/admin/preorders'
import { useQuickConfirm } from '@/composables/useConfirmAction'
import { useNotifications } from '@/composables/useNotifications'
import ConfirmActionDialog from '@/components/common/ConfirmActionDialog.vue'
import OzonExpressConfirmDialog from '@/components/dialogs/OzonExpressConfirmDialog.vue'
import { useI18n } from 'vue-i18n'

definePage({
  meta: {
    requiresAuth: true,
    requiresRole: 'admin',
  },
})

const router = useRouter()
const preordersStore = usePreordersStore()
const { t } = useI18n()
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

// OzonExpress dialog state
const showOzonDialog = ref(false)
const ozonDialogLoading = ref(false)
const ozonDialogType = ref<'single' | 'bulk'>('single')
const currentOrderId = ref<string>('')

// Local shipping dialog state
const showLocalShippingDialog = ref(false)
const localShippingLoading = ref(false)
const localShippingNote = ref('')

// Computed
const isLoading = computed(() => preordersStore.isLoading)
const preorders = computed(() => preordersStore.preorders)
const pagination = computed(() => preordersStore.pagination)

// Table headers
const headers = [
  { title: '', key: 'select', sortable: false, width: '50px' },
  { title: t('table_client'), key: 'client', sortable: false },
  { title: t('table_city'), key: 'city', sortable: false },
  { title: t('table_affiliate'), key: 'affilie', sortable: false },
  { title: t('table_boutique'), key: 'boutique', sortable: false },
  { title: 'SKU', key: 'sku_list', sortable: false, width: '150px' },
  { title: 'Type', key: 'type_command', sortable: true, width: '100px' },
  { title: t('table_total'), key: 'total_ttc', sortable: true },
  { title: t('table_no_answer'), key: 'no_answer_count', sortable: true, width: '100px' },
  { title: t('table_status'), key: 'statut', sortable: true },
  { title: t('table_shipping'), key: 'shipping', sortable: false, width: '120px' },
  { title: t('table_date'), key: 'created_at', sortable: true },
  { title: t('table_actions'), key: 'actions', sortable: false, width: '150px' },
]

// Status options
const statusOptions = [
  { title: t('admin_preorders_status_all'), value: '' },
  { title: t('admin_preorders_status_pending'), value: 'en_attente' },
  { title: t('admin_preorders_status_confirmed'), value: 'confirmee' },
  { title: t('admin_preorders_status_unreachable'), value: 'injoignable' },
  { title: t('admin_preorders_status_refused'), value: 'refusee' },
  { title: t('admin_preorders_status_cancelled'), value: 'annulee' },
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

const getCommandTypeColor = (type: string) => {
  const colors: Record<string, string> = {
    'order_sample': 'primary',
    'exchange': 'warning'
  }
  return colors[type] || 'secondary'
}

const getCommandTypeLabel = (type: string) => {
  const labels: Record<string, string> = {
    'order_sample': 'Échantillon',
    'exchange': 'Échange'
  }
  return labels[type] || type
}

const getStatusText = (status: string) => {
  switch (status) {
    case 'en_attente':
      return t('admin_preorders_status_pending')
    case 'confirmee':
      return t('admin_preorders_status_confirmed')
    case 'injoignable':
      return t('admin_preorders_status_unreachable')
    case 'refusee':
      return t('admin_preorders_status_refused')
    case 'annulee':
      return t('admin_preorders_status_cancelled')
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

// Client Final helpers
const getClientFinalName = (item: any) => {
  return item.client_final_data?.nom_complet || item.client?.nom_complet || t('admin_preorders_client_na')
}

const getClientFinalPhone = (item: any) => {
  return item.client_final_data?.telephone || item.client?.telephone || t('admin_preorders_phone_na')
}

const getClientFinalCity = (item: any) => {
  return item.client_final_data?.ville || item.adresse?.ville || null
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
    title: t('admin_preorders_bulk_change_title'),
    text: t('admin_preorders_bulk_change_text', { count: selectedOrders.value.length }),
    confirmText: t('admin_preorders_bulk_confirm'),
    cancelText: t('admin_preorders_bulk_cancel')
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
    showSuccess(result.message || t('admin.orders.bulkUpdateSuccess', { count: selectedOrders.value.length, status: statusText }))
    selectedOrders.value = []
    selectAll.value = false
    bulkStatusValue.value = '' // Reset dropdown after success
  } catch (error: any) {
    showError(error.message || t('admin_preorders_bulk_status_error'))
    bulkStatusValue.value = '' // Reset dropdown on error
    console.error('Bulk status change error:', error)
  } finally {
    bulkActionLoading.value = false
  }
}

const bulkSendToShipping = async () => {
  if (selectedOrders.value.length === 0) return

  ozonDialogType.value = 'bulk'
  showOzonDialog.value = true
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
  currentOrderId.value = orderId
  ozonDialogType.value = 'single'
  showOzonDialog.value = true
}

const quickMoveToLocalShipping = async (orderId: string) => {
  currentOrderId.value = orderId
  localShippingNote.value = ''
  showLocalShippingDialog.value = true
}

const handleOzonConfirm = async (mode: 'ramassage' | 'stock') => {
  ozonDialogLoading.value = true

  try {
    if (ozonDialogType.value === 'bulk') {
      // Bulk action
      const result = await preordersStore.bulkSendToShipping(selectedOrders.value, mode)

      // Show detailed feedback based on results
      if (result.summary) {
        const { total, success, errors } = result.summary
        if (errors > 0) {
          showError(t('admin_preorders_ozon_error_bulk', { success, total, errors }))
        } else {
          const mode = mode === 'ramassage' ? t('admin_preorders_ozon_mode_pickup') : t('admin_preorders_ozon_mode_stock')
          showSuccess(t('admin_preorders_ozon_success_bulk', { count: success, mode }))
        }
      } else {
        showSuccess(result.message || t('admin_preorders_bulk_send_ozon'))
      }

      selectedOrders.value = []
      selectAll.value = false
      bulkStatusValue.value = ''
    } else {
      // Single action
      const result = await preordersStore.sendToShipping(currentOrderId.value, mode)
      const modeText = mode === 'ramassage' ? t('admin_preorders_ozon_mode_pickup') : t('admin_preorders_ozon_mode_stock')
      showSuccess(t('admin_preorders_ozon_success_single', { mode: modeText }))
    }
  } catch (error: any) {
    showError(error.message || t('admin_preorders_ozon_error'))
    console.error('OzonExpress shipping error:', error)
  } finally {
    ozonDialogLoading.value = false
  }
}

const handleOzonCancel = () => {
  // Dialog will close automatically
}

const handleLocalShippingConfirm = async () => {
  localShippingLoading.value = true

  try {
    const result = await preordersStore.moveToShippingLocal(currentOrderId.value, localShippingNote.value || undefined)
    showSuccess(t('admin_preorders_local_shipping_success'))
    showLocalShippingDialog.value = false

    // Navigate to shipping orders detail page
    router.push({ name: 'admin-orders-shipping-id', params: { id: currentOrderId.value } })
  } catch (error: any) {
    showError(error.message || t('admin_preorders_local_shipping_error'))
  } finally {
    localShippingLoading.value = false
  }
}

const handleLocalShippingCancel = () => {
  showLocalShippingDialog.value = false
  localShippingNote.value = ''
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
          {{ t('admin_preorders_title') }}
        </h1>
        <p class="text-body-1 mb-0">
          {{ t('admin_preorders_description') }}
        </p>
      </div>
      <VBtn
        color="primary"
        variant="elevated"
        @click="resetFilters"
      >
        <VIcon start icon="tabler-refresh" />
        {{ t('admin_preorders_refresh') }}
      </VBtn>
    </div>

    <!-- Filters Card -->
    <VCard class="mb-6">
      <VCardText>
        <VRow>
          <VCol cols="12" md="3">
            <VTextField
              v-model="searchQuery"
              :label="t('admin_preorders_search_label')"
              :placeholder="t('admin_preorders_search_placeholder')"
              prepend-inner-icon="tabler-search"
              clearable
              @input="handleSearch"
            />
          </VCol>
          <VCol cols="12" md="2">
            <VSelect
              v-model="selectedStatus"
              :label="t('admin_preorders_status_label')"
              :items="statusOptions"
              clearable
              @update:model-value="handleSearch"
            />
          </VCol>
          <VCol cols="12" md="2">
            <VTextField
              v-model="dateFrom"
              :label="t('admin_preorders_date_start')"
              type="date"
              @change="handleSearch"
            />
          </VCol>
          <VCol cols="12" md="2">
            <VTextField
              v-model="dateTo"
              :label="t('admin_preorders_date_end')"
              type="date"
              @change="handleSearch"
            />
          </VCol>
          <VCol cols="12" md="2">
            <VSelect
              v-model="itemsPerPage"
              :label="t('admin_preorders_per_page')"
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
            {{ t('admin_preorders_selected_count', { count: selectedOrders.length }) }}
          </span>

          <VSpacer />

          <VBtn
            color="primary"
            variant="elevated"
            :loading="bulkActionLoading"
            @click="bulkSendToShipping"
          >
            <VIcon start icon="tabler-truck" />
            {{ t('admin_preorders_bulk_send_ozon') }}
          </VBtn>

          <VSelect
            v-model="bulkStatusValue"
            :label="t('admin_preorders_bulk_change_status')"
            :placeholder="t('admin_preorders_bulk_select_status')"
            :items="[
              { title: t('admin_preorders_bulk_confirmed'), value: 'confirmee' },
              { title: t('admin_preorders_bulk_unreachable'), value: 'injoignable' },
              { title: t('admin_preorders_bulk_refused'), value: 'refusee' },
              { title: t('admin_preorders_bulk_cancelled'), value: 'annulee' }
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
              {{ getClientFinalName(item) }}
            </div>
            <div class="text-caption text-medium-emphasis">
              {{ getClientFinalPhone(item) }}
            </div>
            <div v-if="getClientFinalCity(item)" class="text-caption text-info">
              {{ getClientFinalCity(item) }}
            </div>
          </div>
        </template>

        <!-- City Column -->
        <template #item.city="{ item }">
          <div>
            <VChip
              size="small"
              color="secondary"
              variant="tonal"
            >
              {{ item.adresse?.ville || t('admin_preorders_city_na') }}
            </VChip>
          </div>
        </template>
        <!-- Affiliate Column -->
        <template #item.affilie="{ item }">
          <div>
            <div class="font-weight-medium">
              {{ item.affiliate?.nom_complet || t('admin_preorders_affiliate_na') }}
            </div>
            <div class="text-caption text-medium-emphasis">
              {{ item.affiliate?.email || t('admin_preorders_email_na') }}
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

        <!-- SKU List Column -->
        <template #item.sku_list="{ item }">
          <div class="d-flex flex-wrap gap-1">
            <VChip
              v-for="article in item.articles"
              :key="article.id"
              size="x-small"
              color="secondary"
              variant="outlined"
              class="font-mono"
            >
              {{ article.produit.sku || 'N/A' }}
            </VChip>
          </div>
        </template>

        <!-- Type Command Column -->
        <template #item.type_command="{ item }">
          <VChip
            size="small"
            :color="getCommandTypeColor(item.type_command)"
            variant="tonal"
          >
            {{ getCommandTypeLabel(item.type_command) }}
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
              {{ t('admin_preorders_tracking_tooltip', { tracking: item.shipping_parcel.tracking_number }) }}
            </VTooltip>
            <span class="text-caption text-success">{{ t('admin_preorders_shipped') }}</span>
          </div>
          <div v-else class="text-caption text-medium-emphasis">
            {{ t('admin_preorders_not_shipped') }}
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
                    {{ t('admin_preorders_action_confirm') }}
                  </VListItemTitle>
                </VListItem>

                <!-- Always show Injoignable (can increment multiple times) -->
                <VListItem
                  @click="quickInjoignable(item.id)"
                >
                  <VListItemTitle>
                    <VIcon start icon="tabler-phone-off" color="warning" />
                    {{ t('admin_preorders_action_unreachable') }}
                  </VListItemTitle>
                </VListItem>

                <!-- Always show Refusée (unless already refused) -->
                <VListItem
                  v-if="item.statut !== 'refusee'"
                  @click="quickChangeStatus(item.id, 'refusee')"
                >
                  <VListItemTitle>
                    <VIcon start icon="tabler-x" color="error" />
                    {{ t('admin_preorders_action_refuse') }}
                  </VListItemTitle>
                </VListItem>

                <!-- Always show Annulée (unless already cancelled) -->
                <VListItem
                  v-if="item.statut !== 'annulee'"
                  @click="quickChangeStatus(item.id, 'annulee')"
                >
                  <VListItemTitle>
                    <VIcon start icon="tabler-ban" color="error" />
                    {{ t('admin_preorders_action_cancel') }}
                  </VListItemTitle>
                </VListItem>

                <VDivider v-if="item.statut === 'confirmee' && !item.shipping_parcel" />

                <VListItem
                  v-if="item.statut === 'confirmee' && !item.shipping_parcel"
                  @click="quickSendToShipping(item.id)"
                >
                  <VListItemTitle>
                    <VIcon start icon="tabler-truck" color="info" />
                    {{ t('admin_preorders_action_send_ozon') }}
                  </VListItemTitle>
                </VListItem>

                <VListItem
                  v-if="item.statut === 'confirmee' && !item.shipping_parcel"
                  @click="quickMoveToLocalShipping(item.id)"
                >
                  <VListItemTitle>
                    <VIcon start icon="tabler-package" color="warning" />
                    {{ t('admin_preorders_action_local_shipping') }}
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
            <h3 class="text-h6 mb-2">{{ t('admin_preorders_no_data_title') }}</h3>
            <p class="text-body-2 text-medium-emphasis">
              {{ t('admin_preorders_no_data_description') }}
            </p>
          </div>
        </template>
      </VDataTableServer>
    </VCard>

    <!-- OzonExpress Confirm Dialog -->
    <OzonExpressConfirmDialog
      v-model="showOzonDialog"
      :loading="ozonDialogLoading"
      :text="ozonDialogType === 'bulk'
        ? t('admin_preorders_ozon_bulk_text', { count: selectedOrders.length })
        : t('admin_preorders_ozon_single_text')"
      @confirm="handleOzonConfirm"
      @cancel="handleOzonCancel"
    />

    <!-- Local Shipping Confirm Dialog -->
    <VDialog
      v-model="showLocalShippingDialog"
      max-width="500"
      persistent
    >
      <VCard>
        <VCardTitle class="text-h6">
          <VIcon start icon="tabler-package" color="warning" />
          {{ t('admin_preorders_local_shipping_title') }}
        </VCardTitle>

        <VCardText>
          <p class="mb-4">
            {{ t('admin_preorders_local_shipping_description') }}
          </p>

          <VTextarea
            v-model="localShippingNote"
            :label="t('admin_preorders_local_shipping_note_label')"
            :placeholder="t('admin_preorders_local_shipping_note_placeholder')"
            rows="3"
            variant="outlined"
          />
        </VCardText>

        <VCardActions>
          <VSpacer />
          <VBtn
            variant="text"
            @click="handleLocalShippingCancel"
            :disabled="localShippingLoading"
          >
            {{ t('admin_preorders_local_shipping_cancel') }}
          </VBtn>
          <VBtn
            color="warning"
            variant="elevated"
            :loading="localShippingLoading"
            @click="handleLocalShippingConfirm"
          >
            <VIcon start icon="tabler-package" />
            {{ t('admin_preorders_local_shipping_confirm') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

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
