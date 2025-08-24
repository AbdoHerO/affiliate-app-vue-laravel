<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAffiliateApplicationsStore } from '@/stores/admin/affiliateApplications'
import { useNotifications } from '@/composables/useNotifications'
import ConfirmDialog from '@/components/dialogs/ConfirmDialog.vue'

definePage({
  meta: {
    requiresAuth: true,
    requiresRole: 'admin',
  },
})

const { t } = useI18n()
const affiliateApplicationsStore = useAffiliateApplicationsStore()
const { showSuccess, showError } = useNotifications()

// Local state
const searchQuery = ref('')
const selectedApprovalStatus = ref('')
const selectedEmailVerified = ref('')
const itemsPerPage = ref(15)
const showApproveDialog = ref(false)
const showRefuseDialog = ref(false)
const showResendConfirm = ref(false)
const selectedApplication = ref<any>(null)
const approveReason = ref('')
const refuseReason = ref('')

// Computed
const isLoading = computed(() => affiliateApplicationsStore.isLoading)
const applications = computed(() => affiliateApplicationsStore.applications)
const pagination = computed(() => affiliateApplicationsStore.pagination)
const stats = computed(() => affiliateApplicationsStore.stats)

// Table headers
const headers = [
  { title: t('table_column_user'), key: 'user', sortable: false },
  { title: t('table_column_email'), key: 'email', sortable: true },
  { title: t('table_column_phone'), key: 'telephone', sortable: false },
  { title: t('table_column_bank'), key: 'bank_type', sortable: false },
  { title: t('table_column_email_verified'), key: 'email_verified', sortable: true },
  { title: t('table_column_approval_status'), key: 'approval_status', sortable: true },
  { title: t('table_column_created_at'), key: 'created_at', sortable: true },
  { title: t('table_column_actions'), key: 'actions', sortable: false },
]

// Filter options
const approvalStatusOptions = [
  { title: t('filter_all'), value: '' },
  { title: t('affiliate_status_pending_approval'), value: 'pending_approval' },
  { title: t('affiliate_status_approved'), value: 'approved' },
  { title: t('affiliate_status_refused'), value: 'refused' },
]

const emailVerifiedOptions = [
  { title: t('filter_all'), value: '' },
  { title: t('email_verified'), value: 'true' },
  { title: t('email_not_verified'), value: 'false' },
]



// Methods
const fetchApplications = async () => {
  await affiliateApplicationsStore.fetchApplications({
    q: searchQuery.value || undefined,
    approval_status: selectedApprovalStatus.value || undefined,
    email_verified: selectedEmailVerified.value || undefined,
    perPage: itemsPerPage.value,
  })
}

// Simple debounce implementation
// Single debounced watcher for all filters
let debounceTimer: NodeJS.Timeout
const debouncedFetch = () => {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => {
    affiliateApplicationsStore.filters.page = 1
    fetchApplications()
  }, 300)
}

// Watch all filters with single debounced handler
watch([searchQuery, selectedApprovalStatus, selectedEmailVerified, itemsPerPage], () => {
  debouncedFetch()
}, { deep: true })



const handlePageChange = (page: number) => {
  affiliateApplicationsStore.filters.page = page
  fetchApplications()
}

const handleSort = (sortBy: any) => {
  if (sortBy.length > 0) {
    affiliateApplicationsStore.filters.sort = sortBy[0].key
    affiliateApplicationsStore.filters.dir = sortBy[0].order
    fetchApplications()
  }
}

const openApproveDialog = (application: any) => {
  selectedApplication.value = application
  approveReason.value = ''
  showApproveDialog.value = true
}

const openRefuseDialog = (application: any) => {
  selectedApplication.value = application
  refuseReason.value = ''
  showRefuseDialog.value = true
}

const approveApplication = async () => {
  if (!selectedApplication.value) return

  try {
    await affiliateApplicationsStore.approveApplication(selectedApplication.value.id, approveReason.value)
    showSuccess(t('affiliate_approved_success'))
    showApproveDialog.value = false
    fetchApplications()
  } catch (error: any) {
    showError(error.message || t('error_during_approval'))
  }
}

const refuseApplication = async () => {
  if (!selectedApplication.value || !refuseReason.value.trim()) return

  try {
    await affiliateApplicationsStore.refuseApplication(selectedApplication.value.id, refuseReason.value)
    showSuccess(t('affiliate_refused_success'))
    showRefuseDialog.value = false
    fetchApplications()
  } catch (error: any) {
    showError(error.message || t('error_during_refusal'))
  }
}

const resendVerification = (application: any) => {
  selectedApplication.value = application
  showResendConfirm.value = true
}

const confirmResendVerification = async () => {
  if (!selectedApplication.value) return

  try {
    await affiliateApplicationsStore.resendVerification(selectedApplication.value.id)
    showSuccess(t('verification_email_sent_success'))
  } catch (error: any) {
    showError(error.message || t('error_sending_email'))
  }
}

const getApprovalStatusColor = (status: string) => {
  switch (status) {
    case 'approved':
      return 'success'
    case 'pending_approval':
      return 'warning'
    case 'refused':
      return 'error'
    default:
      return 'default'
  }
}

const getApprovalStatusText = (status: string) => {
  switch (status) {
    case 'approved':
      return t('affiliate_status_approved')
    case 'pending_approval':
      return t('affiliate_status_pending_approval_short')
    case 'refused':
      return t('affiliate_status_refused')
    default:
      return status
  }
}

const getApprovalStatusIcon = (status: string) => {
  switch (status) {
    case 'approved':
      return '✓'
    case 'pending_approval':
      return '⏳'
    case 'refused':
      return '✗'
    default:
      return '?'
  }
}



const formatDate = (date: string) => {
  return new Date(date).toLocaleDateString('fr-FR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  })
}

const resetFilters = () => {
  searchQuery.value = ''
  selectedApprovalStatus.value = ''
  selectedEmailVerified.value = ''
  affiliateApplicationsStore.resetFilters()
  fetchApplications()
}

// Lifecycle
onMounted(async () => {
  await affiliateApplicationsStore.fetchStats()
  await fetchApplications()
})
</script>

<template>
  <div class="d-flex flex-column gap-6">
    <!-- Page Header -->
    <div class="d-flex justify-space-between align-center mb-6">
      <div>
        <h1 class="text-h4 font-weight-bold mb-1">
          File d'Attente d'Approbation
        </h1>
        <p class="text-body-1 mb-0">
          Gérer les demandes d'inscription des affiliés
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

    <!-- Stats Cards -->
    <VRow v-if="stats" class="mb-6">
      <VCol cols="12" md="2">
        <VCard variant="tonal" color="warning">
          <VCardText class="text-center">
            <div class="text-h4">{{ stats.pending_approval || 0 }}</div>
            <div class="text-body-2">{{ t('affiliate_status_pending_approval_short') }}</div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" md="2">
        <VCard variant="tonal" color="info">
          <VCardText class="text-center">
            <div class="text-h4">{{ stats.email_not_verified || 0 }}</div>
            <div class="text-body-2">{{ t('email_not_verified') }}</div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" md="2">
        <VCard variant="tonal" color="success">
          <VCardText class="text-center">
            <div class="text-h4">{{ stats.approved_applications || 0 }}</div>
            <div class="text-body-2">{{ t('affiliate_status_approved') }}</div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" md="2">
        <VCard variant="tonal" color="error">
          <VCardText class="text-center">
            <div class="text-h4">{{ stats.refused_applications || 0 }}</div>
            <div class="text-body-2">{{ t('affiliate_status_refused') }}</div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" md="2">
        <VCard variant="tonal" color="primary">
          <VCardText class="text-center">
            <div class="text-h4">{{ stats.recent_signups || 0 }}</div>
            <div class="text-body-2">{{ t('last_7_days') }}</div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Filters Card -->
    <VCard class="mb-6">
      <VCardText>
        <VRow class="align-center">
          <VCol cols="12" md="4">
            <VTextField
              v-model="searchQuery"
              :label="t('action_search')"
              :placeholder="t('search_placeholder_name_email_phone')"
              prepend-inner-icon="tabler-search"
              clearable
              density="compact"
            />
          </VCol>
          <VCol cols="12" md="3">
            <VSelect
              v-model="selectedApprovalStatus"
              :label="t('table_column_approval_status')"
              :items="approvalStatusOptions"
              clearable
              density="compact"
            />
          </VCol>
          <VCol cols="12" md="3">
            <VSelect
              v-model="selectedEmailVerified"
              :label="t('table_column_email_verified')"
              :items="emailVerifiedOptions"
              clearable
              density="compact"
            />
          </VCol>
          <VCol cols="12" md="2" class="d-flex gap-2">
            <VSelect
              v-model="itemsPerPage"
              :label="t('items_per_page')"
              :items="[10, 15, 25, 50]"
              density="compact"
            />
            <VBtn
              color="secondary"
              variant="outlined"
              icon="tabler-filter-off"
              @click="resetFilters"
            />
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Data Table -->
    <VCard>
      <VDataTableServer
        v-model:items-per-page="itemsPerPage"
        :headers="headers"
        :items="applications"
        :items-length="pagination.total"
        :loading="isLoading"
        :page="pagination.current_page"
        @update:page="handlePageChange"
        @update:sort-by="handleSort"
      >
        <!-- User Column -->
        <template #item.user="{ item }">
          <div class="d-flex align-center gap-3">
            <VAvatar
              size="40"
              color="primary"
              variant="tonal"
            >
              <VIcon icon="tabler-user" />
            </VAvatar>
            <div>
              <div class="font-weight-medium">
                {{ item.nom_complet }}
              </div>
              <div class="text-caption text-medium-emphasis">
                ID: {{ item.id.slice(0, 8) }}
              </div>
            </div>
          </div>
        </template>

        <!-- Bank Type Column -->
        <template #item.bank_type="{ item }">
          <div class="text-body-2">
            <VIcon
              icon="tabler-building-bank"
              size="16"
              class="me-2"
            />
            {{ item.bank_type || t('admin_affiliates_bank_not_provided') }}
          </div>
        </template>

        <!-- Email Verified Column -->
        <template #item.email_verified="{ item }">
          <VBadge
            :color="item.email_verified_at ? 'success' : 'warning'"
            :content="item.email_verified_at ? '✓' : '!'"
            inline
          >
            <VChip
              size="small"
              :color="item.email_verified_at ? 'success' : 'warning'"
              variant="tonal"
            >
              {{ item.email_verified_at ? t('admin_affiliates_email_verified') : t('admin_affiliates_email_not_verified') }}
            </VChip>
          </VBadge>
        </template>

        <!-- Approval Status Column -->
        <template #item.approval_status="{ item }">
          <VBadge
            :color="getApprovalStatusColor(item.approval_status)"
            :content="getApprovalStatusIcon(item.approval_status)"
            inline
          >
            <VChip
              size="small"
              :color="getApprovalStatusColor(item.approval_status)"
              variant="tonal"
            >
              {{ getApprovalStatusText(item.approval_status) }}
            </VChip>
          </VBadge>
        </template>



        <!-- Created At Column -->
        <template #item.created_at="{ item }">
          <div class="text-body-2">
            {{ formatDate(item.created_at) }}
          </div>
        </template>

        <!-- Actions Column -->
        <template #item.actions="{ item }">
          <div class="d-flex gap-1">
            <!-- Resend Verification -->
            <VTooltip
              v-if="!item.email_verified_at"
              :text="t('admin_affiliates_resend_verification_tooltip')"
            >
              <template #activator="{ props }">
                <VBtn
                  v-bind="props"
                  size="small"
                  color="info"
                  variant="text"
                  icon="tabler-mail"
                  :loading="affiliateApplicationsStore.isResending(item.id)"
                  :disabled="affiliateApplicationsStore.isResending(item.id)"
                  @click.stop="resendVerification(item)"
                />
              </template>
            </VTooltip>

            <!-- Approve -->
            <VTooltip
              v-if="item.approval_status === 'pending_approval'"
              :text="t('approve_request')"
            >
              <template #activator="{ props }">
                <VBtn
                  v-bind="props"
                  size="small"
                  color="success"
                  variant="text"
                  icon="tabler-check"
                  @click="openApproveDialog(item)"
                />
              </template>
            </VTooltip>

            <!-- Refuse -->
            <VTooltip
              v-if="item.approval_status === 'pending_approval'"
              :text="t('refuse_request')"
            >
              <template #activator="{ props }">
                <VBtn
                  v-bind="props"
                  size="small"
                  color="error"
                  variant="text"
                  icon="tabler-x"
                  @click="openRefuseDialog(item)"
                />
              </template>
            </VTooltip>
          </div>
        </template>

        <!-- No data -->
        <template #no-data>
          <div class="text-center py-8">
            <VIcon
              icon="tabler-users-off"
              size="64"
              class="mb-4"
              color="disabled"
            />
            <h3 class="text-h6 mb-2">{{ t('no_users_found') }}</h3>
            <p class="text-body-2 text-medium-emphasis">
              {{ t('no_users_match_criteria') }}
            </p>
          </div>
        </template>
      </VDataTableServer>
    </VCard>

    <!-- Approve Dialog -->
    <VDialog
      v-model="showApproveDialog"
      max-width="600"
      persistent
    >
      <VCard class="pa-6">
        <VCardText class="text-center">
          <!-- Icon -->
          <VAvatar
            size="88"
            color="success"
            variant="tonal"
            class="mb-6"
          >
            <VIcon
              icon="tabler-check"
              size="48"
            />
          </VAvatar>

          <!-- Title -->
          <h5 class="text-h5 mb-4">
            {{ t('approve_affiliation_request') }}
          </h5>

          <!-- Text -->
          <p class="text-body-1 mb-6">
            {{ t('approve_affiliate_confirm', { name: selectedApplication?.nom_complet }) }}
            <br>
            <small class="text-medium-emphasis">{{ t('user_account_created_automatically') }}</small>
          </p>

          <!-- Optional Reason -->
          <VTextarea
            v-model="approveReason"
            :label="t('reason_optional')"
            :placeholder="t('approval_reason_placeholder')"
            rows="3"
            variant="outlined"
            class="mb-4"
          />
        </VCardText>

        <!-- Actions -->
        <VCardActions class="justify-center gap-3">
          <VBtn
            color="success"
            variant="elevated"
            @click="approveApplication"
          >
            {{ t('action_approve') }}
          </VBtn>

          <VBtn
            color="secondary"
            variant="outlined"
            @click="showApproveDialog = false"
          >
            {{ t('action_cancel') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Refuse Dialog -->
    <VDialog
      v-model="showRefuseDialog"
      max-width="600"
      persistent
    >
      <VCard class="pa-6">
        <VCardText class="text-center">
          <!-- Icon -->
          <VAvatar
            size="88"
            color="error"
            variant="tonal"
            class="mb-6"
          >
            <VIcon
              icon="tabler-x"
              size="48"
            />
          </VAvatar>

          <!-- Title -->
          <h5 class="text-h5 mb-4">
            {{ t('refuse_affiliation_request') }}
          </h5>

          <!-- Text -->
          <p class="text-body-1 mb-6">
            {{ t('refuse_affiliate_confirm', { name: selectedApplication?.nom_complet }) }}
            <br>
            <small class="text-medium-emphasis">{{ t('action_requires_justification') }}</small>
          </p>

          <!-- Required Reason -->
          <VTextarea
            v-model="refuseReason"
            :label="t('refusal_reason_required')"
            :placeholder="t('refusal_reason_placeholder')"
            variant="outlined"
            rows="3"
            :error="!refuseReason.trim()"
            class="mb-4"
          />
        </VCardText>

        <!-- Actions -->
        <VCardActions class="justify-center gap-3">
          <VBtn
            color="error"
            variant="elevated"
            :disabled="!refuseReason.trim()"
            @click="refuseApplication"
          >
            {{ t('action_refuse') }}
          </VBtn>

          <VBtn
            color="secondary"
            variant="outlined"
            @click="showRefuseDialog = false"
          >
            {{ t('action_cancel') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Resend Verification Confirm Dialog -->
    <ConfirmDialog
      v-model="showResendConfirm"
      :title="t('resend_verification_email')"
      :text="t('resend_verification_confirm', { email: selectedApplication?.email })"
      :confirm-text="t('action_resend')"
      :cancel-text="t('action_cancel')"
      color="info"
      icon="tabler-mail"
      :loading="affiliateApplicationsStore.isResending(selectedApplication?.id || '')"
      @confirm="confirmResendVerification"
    />
  </div>
</template>
