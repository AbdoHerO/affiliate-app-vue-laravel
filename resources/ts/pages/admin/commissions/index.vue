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
  { title: t('admin_commissions_title'), active: true },
])

// Filters
const statusOptions = [
  { value: '', text: t('all_statuses') },
  { value: 'pending_calc', text: t('commission_status_pending_calc') },
  { value: 'calculated', text: t('commission_status_calculated') },
  { value: 'eligible', text: t('commission_status_eligible') },
  { value: 'approved', text: t('commission_status_approved') },
  { value: 'rejected', text: t('commission_status_rejected') },
  { value: 'paid', text: t('commission_status_paid') },
  { value: 'adjusted', text: t('commission_status_adjusted') },
  { value: 'canceled', text: t('commission_status_canceled') },
]

const typeOptions = [
  { value: '', text: t('all_types') },
  { value: 'sale', text: t('commission_type_sale') },
  { value: 'referral', text: t('commission_type_referral') },
  { value: 'bonus', text: t('commission_type_bonus') },
]

// Table headers
const headers = [
  { title: t('table_column_affiliate'), key: 'affiliate.nom_complet', sortable: true },
  { title: t('table_column_order'), key: 'commande_id', sortable: true },
  { title: t('table_column_product'), key: 'product', sortable: false },
  { title: t('table_column_type'), key: 'type', sortable: true },
  { title: t('admin_commissions_base_amount'), key: 'base_amount', sortable: true },
  { title: t('admin_commissions_rate'), key: 'rate', sortable: false },
  { title: t('admin_commissions_amount'), key: 'amount', sortable: true },
  { title: t('table_column_status'), key: 'status', sortable: true },
  { title: t('table_column_date'), key: 'created_at', sortable: true },
  { title: t('table_column_actions'), key: 'actions', sortable: false, width: 120 },
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
    showError(t('commission_navigation_error'))
  }
}

const handleApprove = async (commission: Commission) => {
  const confirmed = await confirm({
    title: t('commission_approve_title'),
    text: t('commission_approve_confirm', { amount: commission.amount, currency: commission.currency }),
    confirmText: t('action_approve'),
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
    title: t('commission_reject_title'),
    text: t('commission_reject_confirm', { amount: commission.amount, currency: commission.currency }),
    confirmText: t('action_reject'),
    color: 'error',
    type: 'error',
  })

  if (confirmed) {
    // For now, we'll use a default reason. In a real app, you'd show a dialog to get the reason
    const result = await commissionsStore.rejectCommission(commission.id, t('commission_default_reject_reason'))
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
    title: t('commission_adjust_title'),
    text: t('commission_adjust_confirm', { amount: commission.amount, currency: commission.currency }),
    confirmText: t('action_adjust'),
    color: 'warning',
    type: 'warning',
  })

  if (confirmed) {
    // For now, we'll use default values. In a real app, you'd show a dialog to get the new amount and reason
    const newAmount = parseFloat(commission.amount) * 0.8 // Reduce by 20% as example
    const result = await commissionsStore.adjustCommission(commission.id, newAmount, t('administrative_adjustment'))
    if (result.success) {
      showSuccess(result.message)
      await fetchCommissions()
    } else {
      showError(result.message)
    }
  }
}

const handleMarkAsPaid = async (commission: Commission) => {
  const confirmed = await confirm({
    title: t('action_mark_as_paid'),
    text: t('commission_confirm_payment', { amount: commission.amount, currency: commission.currency }),
    confirmText: t('action_confirm_payment'),
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
    title: t('approve_selected_commissions'),
    text: t('confirm_approve_commissions', { count: selectedCommissions.value.length }),
    confirmText: t('approve_all'),
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
    title: t('commission_bulk_reject_title'),
    text: t('commission_bulk_reject_confirm', { count: selectedCommissions.value.length }),
    confirmText: t('action_reject_all'),
    color: 'error',
    type: 'error',
  })

  if (confirmed) {
    const result = await commissionsStore.bulkReject(selectedCommissions.value, t('commission_bulk_reject_reason'))
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
    pending_calc: t('commission_status_pending_calc'),
    calculated: t('commission_status_calculated'),
    eligible: t('commission_status_eligible'),
    approved: t('commission_status_approved'),
    rejected: t('commission_status_rejected'),
    paid: t('commission_status_paid'),
    adjusted: t('commission_status_adjusted'),
    canceled: t('commission_status_canceled'),
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
                <p class="text-sm text-medium-emphasis mb-0">{{ t('commission_status_calculated') }}</p>
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
                <p class="text-sm text-medium-emphasis mb-0">{{ t('commission_status_eligible') }}</p>
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
                <p class="text-sm text-medium-emphasis mb-0">{{ t('commission_status_approved') }}</p>
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
                <p class="text-sm text-medium-emphasis mb-0">{{ t('commission_status_paid') }}</p>
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
              :label="t('action_search')"
              :placeholder="t('filter_search_placeholder')"
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
              :label="t('table_column_status')"
              multiple
              chips
              clearable
              hide-details
            />
          </VCol>

          <VCol cols="12" md="2">
            <VCheckbox
              v-model="filters.eligible_only"
              :label="t('commission_eligible_only')"
              hide-details
            />
          </VCol>

          <VCol cols="12" md="3" class="d-flex align-center gap-2">
            <VBtn
              color="primary"
              variant="elevated"
              @click="applyFilters"
            >
              {{ t('action_filter') }}
            </VBtn>
            <VBtn
              color="secondary"
              variant="outlined"
              @click="resetFilters"
            >
              {{ t('action_reset') }}
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
                {{ t('commission_selected_count', { count: selectedCommissions.length }) }}
              </span>
            </div>

            <div class="d-flex gap-2">
              <VBtn
                color="success"
                variant="elevated"
                size="small"
                @click="handleBulkApprove"
              >
                {{ t('action_approve_all') }}
              </VBtn>
              <VBtn
                color="error"
                variant="elevated"
                size="small"
                @click="handleBulkReject"
              >
                {{ t('action_reject_all') }}
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
              <div class="font-weight-medium">{{ item.affiliate?.nom_complet || t('not_available') }}</div>
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

        <!-- Product Column -->
        <template #item.product="{ item }">
          <div v-if="item.commande_article?.produit">
            <div class="font-weight-medium">{{ item.commande_article.produit.titre }}</div>
            <div v-if="item.commande_article.produit.sku" class="text-caption text-medium-emphasis">
              <VChip
                size="x-small"
                color="secondary"
                variant="outlined"
                class="font-mono"
              >
                {{ item.commande_article.produit.sku }}
              </VChip>
            </div>
          </div>
          <span v-else class="text-medium-emphasis">{{ t('not_available') }}</span>
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
            {{ t('commission_rate_fixed') }}
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
                <VListItemTitle>{{ t('action_view_details') }}</VListItemTitle>
              </VListItem>

              <!-- Approve Action -->
              <VListItem
                v-if="item.can_be_approved"
                @click="handleApprove(item)"
              >
                <template #prepend>
                  <VIcon icon="tabler-check" size="20" color="success" />
                </template>
                <VListItemTitle>{{ t('action_approve') }}</VListItemTitle>
              </VListItem>

              <!-- Reject Action -->
              <VListItem
                v-if="item.can_be_rejected"
                @click="handleReject(item)"
              >
                <template #prepend>
                  <VIcon icon="tabler-x" size="20" color="error" />
                </template>
                <VListItemTitle>{{ t('action_reject') }}</VListItemTitle>
              </VListItem>

              <!-- Adjust Action -->
              <VListItem
                v-if="item.can_be_adjusted"
                @click="handleAdjust(item)"
              >
                <template #prepend>
                  <VIcon icon="tabler-edit" size="20" color="warning" />
                </template>
                <VListItemTitle>{{ t('action_adjust') }}</VListItemTitle>
              </VListItem>

              <!-- Mark as Paid Action -->
              <VListItem
                v-if="item.status === 'approved' && !item.paid_at"
                @click="handleMarkAsPaid(item)"
              >
                <template #prepend>
                  <VIcon icon="tabler-cash" size="20" color="primary" />
                </template>
                <VListItemTitle>{{ t('action_mark_as_paid') }}</VListItemTitle>
              </VListItem>
            </VList>
          </VMenu>
        </template>
      </VDataTableServer>
    </VCard>
  </div>
</template>
