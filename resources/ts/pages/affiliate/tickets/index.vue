<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'
import { useAffiliateTicketsStore } from '@/stores/affiliate/tickets'
import { useNotifications } from '@/composables/useNotifications'
import { useTicketBadge } from '@/composables/useTicketBadge'
import Breadcrumbs from '@/components/common/Breadcrumbs.vue'

definePage({
  meta: {
    requiresAuth: true,
    requiresRole: 'affiliate',
  },
})

const { t } = useI18n()
const router = useRouter()
const { showSuccess, showError } = useNotifications()
const { refresh: refreshTicketBadge } = useTicketBadge()

// Store
const ticketsStore = useAffiliateTicketsStore()
const { tickets, loading, pagination, filters, error } = storeToRefs(ticketsStore)

// Local state
const searchQuery = ref('')
const selectedStatus = ref<string[]>([])
const selectedPriority = ref<string[]>([])
const selectedCategory = ref<string[]>([])
const dateFrom = ref('')
const dateTo = ref('')
const showCreateDialog = ref(false)

// Form data for creating tickets
const createForm = ref({
  subject: '',
  category: 'general',
  priority: 'normal',
  message: '',
  attachments: [] as File[],
})

// Computed
const breadcrumbs = computed(() => [
  { title: t('nav.dashboard'), to: '/affiliate/dashboard' },
  { title: t('affiliate_tickets_title'), active: true },
])

const statusOptions = [
  { value: 'open', title: t('affiliate_tickets_status_open'), color: 'info' },
  { value: 'pending', title: t('affiliate_tickets_status_pending'), color: 'warning' },
  { value: 'waiting_user', title: t('affiliate_tickets_status_waiting_user'), color: 'orange' },
  { value: 'resolved', title: t('affiliate_tickets_status_resolved'), color: 'success' },
  { value: 'closed', title: t('affiliate_tickets_status_closed'), color: 'secondary' },
]

const priorityOptions = [
  { value: 'low', title: t('affiliate_tickets_priority_low'), color: 'success' },
  { value: 'normal', title: t('affiliate_tickets_priority_normal'), color: 'info' },
  { value: 'high', title: t('affiliate_tickets_priority_high'), color: 'warning' },
  { value: 'urgent', title: t('affiliate_tickets_priority_urgent'), color: 'error' },
]

const categoryOptions = [
  { value: 'general', title: t('affiliate_tickets_category_general') },
  { value: 'orders', title: t('affiliate_tickets_category_orders') },
  { value: 'payments', title: t('affiliate_tickets_category_payments') },
  { value: 'commissions', title: t('affiliate_tickets_category_commissions') },
  { value: 'kyc', title: t('affiliate_tickets_category_kyc') },
  { value: 'technical', title: t('affiliate_tickets_category_technical') },
  { value: 'other', title: t('affiliate_tickets_category_other') },
]

const headers = [
  { title: t('table.reference'), key: 'id', sortable: false },
  { title: t('affiliate_tickets_subject'), key: 'subject', sortable: true },
  { title: t('table.category'), key: 'category', sortable: true },
  { title: t('affiliate_tickets_priority'), key: 'priority', sortable: true },
  { title: t('table.status'), key: 'status', sortable: true },
  { title: t('affiliate_tickets_last_activity'), key: 'last_activity_at', sortable: true },
  { title: t('table.actions'), key: 'actions', sortable: false },
]

// Methods
const applyFilters = () => {
  ticketsStore.updateFilters({
    q: searchQuery.value,
    status: selectedStatus.value,
    priority: selectedPriority.value,
    category: selectedCategory.value,
    date_from: dateFrom.value,
    date_to: dateTo.value,
  })
  fetchTickets()
}

const resetFilters = () => {
  searchQuery.value = ''
  selectedStatus.value = []
  selectedPriority.value = []
  selectedCategory.value = []
  dateFrom.value = ''
  dateTo.value = ''
  ticketsStore.resetFilters()
  fetchTickets()
}

const fetchTickets = async (page = 1) => {
  try {
    await ticketsStore.fetchTickets(page)
    // Refresh badge count when tickets are loaded
    refreshTicketBadge()
  } catch (err) {
    showError(t('affiliate_tickets_error_loading'))
  }
}

const handlePageChange = (page: number) => {
  fetchTickets(page)
}

const handlePerPageChange = (perPage: number) => {
  ticketsStore.updateFilters({ per_page: perPage })
  fetchTickets()
}

const handleSort = (sortBy: any) => {
  if (sortBy.length > 0) {
    const { key, order } = sortBy[0]
    ticketsStore.updateFilters({
      sort: key,
      dir: order,
    })
    fetchTickets()
  }
}

const viewTicket = (ticket: any) => {
  router.push({ name: 'affiliate-tickets-id', params: { id: ticket.id } })
}

const openCreateDialog = () => {
  createForm.value = {
    subject: '',
    category: 'general',
    priority: 'normal',
    message: '',
    attachments: [],
  }
  showCreateDialog.value = true
}

const handleFileUpload = (event: Event) => {
  const target = event.target as HTMLInputElement
  if (target.files) {
    createForm.value.attachments = Array.from(target.files)
  }
}

const createTicket = async () => {
  try {
    await ticketsStore.createTicket(createForm.value)
    showSuccess(t('affiliate_tickets_created_success'))
    showCreateDialog.value = false
    fetchTickets()
    // Badge refresh is already handled in the store
  } catch (err: any) {
    showError(err.message || t('affiliate_tickets_error_creating'))
  }
}

const formatDate = (date: string) => {
  return new Date(date).toLocaleDateString('fr-FR', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

// Watchers
watch(error, (newError) => {
  if (newError) {
    showError(newError)
  }
})

// Lifecycle
onMounted(() => {
  fetchTickets()
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
          {{ t('support') }}
        </h1>
        <p class="text-body-1 text-medium-emphasis">
          {{ t('affiliate_tickets_description') }}
        </p>
      </div>
      <VBtn
        color="primary"
        prepend-icon="tabler-plus"
        @click="openCreateDialog"
      >
        Nouveau ticket
      </VBtn>
    </div>

    <!-- Filters -->
    <VCard class="mb-6">
      <VCardText>
        <VRow>
          <VCol cols="12" sm="6" md="4" lg="3">
            <VTextField
              v-model="searchQuery"
              :label="t('affiliate_tickets_search_label')"
              :placeholder="t('affiliate_tickets_search_placeholder')"
              prepend-inner-icon="tabler-search"
              clearable
              @keyup.enter="applyFilters"
            />
          </VCol>
          <VCol cols="12" sm="6" md="4" lg="2">
            <VSelect
              v-model="selectedStatus"
              :items="statusOptions"
              :label="t('affiliate_tickets_status_label')"
              multiple
              chips
              clearable
              item-title="title"
              item-value="value"
            />
          </VCol>
          <VCol cols="12" sm="6" md="4" lg="2">
            <VSelect
              v-model="selectedPriority"
              :items="priorityOptions"
              :label="t('affiliate_tickets_priority_label')"
              multiple
              chips
              clearable
              item-title="title"
              item-value="value"
            />
          </VCol>
          <VCol cols="12" sm="6" md="4" lg="2">
            <VSelect
              v-model="selectedCategory"
              :items="categoryOptions"
              :label="t('affiliate_tickets_category_label')"
              multiple
              chips
              clearable
              item-title="title"
              item-value="value"
            />
          </VCol>
          <VCol cols="12" sm="6" md="4" lg="2">
            <VTextField
              v-model="dateFrom"
              :label="t('affiliate_tickets_date_start')"
              type="date"
            />
          </VCol>
          <VCol cols="12" sm="6" md="4" lg="1" class="d-flex align-center gap-2">
            <VBtn
              color="primary"
              size="small"
              @click="applyFilters"
            >
              <VIcon icon="tabler-search" />
            </VBtn>
            <VBtn
              variant="outlined"
              size="small"
              @click="resetFilters"
            >
              <VIcon icon="tabler-refresh" />
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Data Table -->
    <VCard>
      <VDataTableServer
        :headers="headers"
        :items="tickets"
        :loading="loading.list"
        :items-length="pagination.total"
        :items-per-page="pagination.per_page"
        :page="pagination.current_page"
        item-value="id"
        @update:page="handlePageChange"
        @update:items-per-page="handlePerPageChange"
        @update:sort-by="handleSort"
      >
        <!-- Reference Column -->
        <template #item.id="{ item }">
          <VBtn
            variant="text"
            size="small"
            color="primary"
            @click="viewTicket(item)"
          >
            #{{ item.id.toString().slice(-8) }}
          </VBtn>
        </template>

        <!-- Subject Column -->
        <template #item.subject="{ item }">
          <div class="font-weight-medium">{{ item.subject }}</div>
        </template>

        <!-- Category Column -->
        <template #item.category="{ item }">
          <VChip
            size="small"
            variant="tonal"
            color="info"
          >
            {{ ticketsStore.getCategoryLabel(item.category) }}
          </VChip>
        </template>

        <!-- Priority Column -->
        <template #item.priority="{ item }">
          <VChip
            :color="ticketsStore.getPriorityColor(item.priority)"
            size="small"
            variant="tonal"
          >
            {{ ticketsStore.getPriorityLabel(item.priority) }}
          </VChip>
        </template>

        <!-- Status Column -->
        <template #item.status="{ item }">
          <VChip
            :color="ticketsStore.getStatusColor(item.status)"
            size="small"
            variant="tonal"
          >
            {{ ticketsStore.getStatusLabel(item.status) }}
          </VChip>
        </template>

        <!-- Last Activity Column -->
        <template #item.last_activity_at="{ item }">
          <span class="text-body-2">
            {{ formatDate(item.last_activity_at) }}
          </span>
        </template>

        <!-- Actions Column -->
        <template #item.actions="{ item }">
          <VBtn
            icon="tabler-eye"
            size="small"
            variant="text"
            color="primary"
            @click="viewTicket(item)"
          />
        </template>

        <!-- No data -->
        <template #no-data>
          <div class="text-center py-8">
            <VIcon
              icon="tabler-ticket-off"
              size="64"
              class="text-disabled mb-4"
            />
            <h3 class="text-h6 mb-2">{{ t('affiliate_tickets_no_tickets_found') }}</h3>
            <p class="text-body-2 text-medium-emphasis">
              {{ t('affiliate_tickets_no_tickets_description') }}
            </p>
            <VBtn
              color="primary"
              class="mt-4"
              @click="openCreateDialog"
            >
              Créer votre premier ticket
            </VBtn>
          </div>
        </template>
      </VDataTableServer>
    </VCard>

    <!-- Create Ticket Dialog -->
    <VDialog
      v-model="showCreateDialog"
      max-width="600"
    >
      <VCard>
        <VCardTitle>Créer un nouveau ticket</VCardTitle>
        <VCardText>
          <VForm @submit.prevent="createTicket">
            <VRow>
              <VCol cols="12">
                <VTextField
                  v-model="createForm.subject"
                  :label="t('affiliate_tickets_subject_required')"
                  :placeholder="t('affiliate_tickets_subject_placeholder')"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VSelect
                  v-model="createForm.category"
                  :items="categoryOptions"
                  :label="t('affiliate_tickets_category_required')"
                  item-title="title"
                  item-value="value"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VSelect
                  v-model="createForm.priority"
                  :items="priorityOptions"
                  :label="t('affiliate_tickets_priority_required')"
                  item-title="title"
                  item-value="value"
                  required
                />
              </VCol>
              <VCol cols="12">
                <VTextarea
                  v-model="createForm.message"
                  :label="t('affiliate_tickets_message_required')"
                  :placeholder="t('affiliate_tickets_message_placeholder')"
                  rows="5"
                  counter="5000"
                  maxlength="5000"
                  required
                />
              </VCol>
              <VCol cols="12">
                <VFileInput
                  :label="t('affiliate_tickets_attachments_optional')"
                  multiple
                  accept="image/*,.pdf,.doc,.docx,.txt"
                  prepend-icon="tabler-paperclip"
                  @change="handleFileUpload"
                />
                <p class="text-caption text-medium-emphasis mt-1">
                  {{ t('affiliate_tickets_file_formats') }}
                </p>
              </VCol>
            </VRow>
          </VForm>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="outlined"
            @click="showCreateDialog = false"
          >
            {{ t('affiliate_tickets_cancel') }}
          </VBtn>
          <VBtn
            color="primary"
            :loading="loading.create"
            @click="createTicket"
          >
            {{ t('affiliate_tickets_create_button') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
