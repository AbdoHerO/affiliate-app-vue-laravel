<script setup lang="ts">
import { ref, computed, onMounted, watch, nextTick, onBeforeUnmount } from 'vue'
import { storeToRefs } from 'pinia'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useTicketsStore } from '@/stores/admin/tickets'
import { useQuickConfirm } from '@/composables/useConfirmAction'
import TicketStatusBadge from '@/components/admin/tickets/TicketStatusBadge.vue'
import TicketPriorityBadge from '@/components/admin/tickets/TicketPriorityBadge.vue'
import TicketAssigneeSelect from '@/components/admin/tickets/TicketAssigneeSelect.vue'
import ConfirmActionDialog from '@/components/common/ConfirmActionDialog.vue'
import SoftDeleteFilter from '@/components/common/SoftDeleteFilter.vue'

// Define page meta
definePage({
  meta: {
    requiresAuth: true,
    requiresRole: 'admin',
  },
})

// Initialize composables with enhanced error handling
const router = useRouter()
const { t } = useI18n()

// Initialize store and composables
let ticketsStore: ReturnType<typeof useTicketsStore>

// Initialize confirm composable
const {
  confirm,
  confirmDelete,
  confirmBulkDelete,
  isDialogVisible,
  isLoading,
  dialogTitle,
  dialogText,
  dialogIcon,
  dialogColor,
  confirmButtonText,
  cancelButtonText,
  handleConfirm,
  handleCancel,
} = useQuickConfirm()

try {
  console.log('🎫 [Tickets] Initializing tickets store...')
  ticketsStore = useTicketsStore()
  console.log('✅ [Tickets] Tickets store initialized successfully')
} catch (error) {
  console.error('🚫 [Tickets] Error initializing tickets store:', error)
  // Create fallback store
  ticketsStore = {
    tickets: ref([]),
    loading: ref(false),
    pagination: ref({ current_page: 1, per_page: 15, total: 0 }),
    filters: ref({}),
    statistics: ref(null),
    hasTickets: ref(false),
    totalTickets: ref(0),
    fetchTickets: () => Promise.resolve(),
    fetchStatistics: () => Promise.resolve(),
    assignTicket: () => Promise.resolve(),
    changeStatus: () => Promise.resolve(),
    deleteTicket: () => Promise.resolve(),
    bulkAction: () => Promise.resolve(),
  } as any
}



// Component cleanup flag
const isDestroyed = ref(false)

// State
const selectedTickets = ref<string[]>([])
const showFilters = ref(false)

// Dialog boolean states
const showBulkAssignDialog = ref(false)
const showBulkStatusDialog = ref(false)

// Form values
const bulkAssigneeId = ref<string | null>(null)
const bulkStatus = ref('')
const pageLoading = ref(true)
const hasError = ref(false)
const errorMessage = ref('')

// Store refs with enhanced error handling
let tickets: any, loading: any, pagination: any, filters: any, statistics: any, hasTickets: any, totalTickets: any

try {
  console.log('🎫 [Tickets] Accessing store refs...')
  const storeRefs = storeToRefs(ticketsStore)
  tickets = storeRefs.tickets
  loading = storeRefs.loading
  pagination = storeRefs.pagination
  filters = storeRefs.filters
  statistics = storeRefs.statistics
  hasTickets = storeRefs.hasTickets
  totalTickets = storeRefs.totalTickets
  console.log('✅ [Tickets] Store refs accessed successfully')
} catch (error) {
  console.error('🚫 [Tickets] Error accessing store refs:', error)
  // Fallback refs
  tickets = ref([])
  loading = ref(false)
  pagination = ref({ current_page: 1, per_page: 15, total: 0 })
  filters = ref({})
  statistics = ref(null)
  hasTickets = ref(false)
  totalTickets = ref(0)
}

// Safe computed properties
const safeStatistics = computed(() => {
  try {
    return statistics?.value || null
  } catch (error) {
    console.error('🚫 [Tickets] Error accessing statistics:', error)
    return null
  }
})

const safeTickets = computed(() => {
  try {
    return tickets?.value || []
  } catch (error) {
    console.error('🚫 [Tickets] Error accessing tickets:', error)
    return []
  }
})

const safeLoading = computed(() => {
  try {
    return loading?.value || false
  } catch (error) {
    console.error('🚫 [Tickets] Error accessing loading state:', error)
    return false
  }
})

// Filter options with safe translations
const getTranslation = (key: string, fallback?: string) => {
  try {
    return t(key)
  } catch (error) {
    console.error(`🚫 [Tickets] Translation error for key: ${key}`, error)
    return fallback || key
  }
}

const statusOptions = [
  { title: getTranslation('ticket_status_open', 'Open'), value: 'open' },
  { title: getTranslation('ticket_status_pending', 'Pending'), value: 'pending' },
  { title: getTranslation('ticket_status_waiting_user', 'Waiting User'), value: 'waiting_user' },
  { title: getTranslation('ticket_status_waiting_third_party', 'Waiting Third Party'), value: 'waiting_third_party' },
  { title: getTranslation('ticket_status_resolved', 'Resolved'), value: 'resolved' },
  { title: getTranslation('ticket_status_closed', 'Closed'), value: 'closed' },
]

const priorityOptions = [
  { title: getTranslation('ticket_priority_low', 'Low'), value: 'low' },
  { title: getTranslation('ticket_priority_normal', 'Normal'), value: 'normal' },
  { title: getTranslation('ticket_priority_high', 'High'), value: 'high' },
  { title: getTranslation('ticket_priority_urgent', 'Urgent'), value: 'urgent' },
]

const categoryOptions = [
  { title: getTranslation('ticket_category_general', 'General'), value: 'general' },
  { title: getTranslation('ticket_category_orders', 'Orders'), value: 'orders' },
  { title: getTranslation('ticket_category_payments', 'Payments'), value: 'payments' },
  { title: getTranslation('ticket_category_commissions', 'Commissions'), value: 'commissions' },
  { title: getTranslation('ticket_category_kyc', 'KYC'), value: 'kyc' },
  { title: getTranslation('ticket_category_technical', 'Technical'), value: 'technical' },
  { title: getTranslation('ticket_category_other', 'Other'), value: 'other' },
]

const sortOptions = [
  { title: getTranslation('sort_last_activity', 'Last Activity'), value: 'last_activity_at' },
  { title: getTranslation('sort_created_date', 'Created Date'), value: 'created_at' },
  { title: getTranslation('sort_subject', 'Subject'), value: 'subject' },
  { title: getTranslation('sort_priority', 'Priority'), value: 'priority' },
  { title: getTranslation('sort_status', 'Status'), value: 'status' },
]

// Table headers
const headers = [
  { title: '', key: 'select', sortable: false, width: 48 },
  { title: getTranslation('ticket_ref', 'Ticket #'), key: 'id', sortable: false, width: 120 },
  { title: getTranslation('subject', 'Subject'), key: 'subject', sortable: true },
  { title: getTranslation('category', 'Category'), key: 'category', sortable: true, width: 120 },
  { title: getTranslation('priority', 'Priority'), key: 'priority', sortable: true, width: 100 },
  { title: getTranslation('status', 'Status'), key: 'status', sortable: true, width: 120 },
  { title: getTranslation('requester', 'Requester'), key: 'requester', sortable: false, width: 180 },
  { title: getTranslation('assignee', 'Assignee'), key: 'assignee', sortable: false, width: 180 },
  { title: getTranslation('last_activity', 'Last Activity'), key: 'last_activity_at', sortable: true, width: 150 },
  { title: getTranslation('actions', 'Actions'), key: 'actions', sortable: false, width: 120 },
]

// Safe methods
const fetchData = async () => {
  if (isDestroyed.value) return
  
  try {
    isLoading.value = true
    hasError.value = false
    errorMessage.value = ''

    if (ticketsStore && typeof ticketsStore.fetchTickets === 'function') {
      await Promise.all([
        ticketsStore.fetchTickets(),
        ticketsStore.fetchStatistics?.() || Promise.resolve(),
      ])
    }
  } catch (error) {
    console.error('Error fetching tickets data:', error)
    hasError.value = true
    errorMessage.value = 'Failed to load tickets data'
  } finally {
    if (!isDestroyed.value) {
      isLoading.value = false
    }
  }
}

const handleSearch = (query: string) => {
  if (isDestroyed.value) return
  try {
    ticketsStore?.fetchTickets?.({ q: query, page: 1 })
  } catch (error) {
    console.error('Error handling search:', error)
  }
}

const handleFilterChange = () => {
  if (isDestroyed.value) return
  try {
    ticketsStore?.fetchTickets?.({ page: 1 })
  } catch (error) {
    console.error('Error handling filter change:', error)
  }
}

const clearFilters = () => {
  if (isDestroyed.value) return
  try {
    if (filters?.value) {
      filters.value = {
        page: 1,
        per_page: 15,
        sort: 'last_activity_at',
        dir: 'desc',
      }
    }
    ticketsStore?.fetchTickets?.()
  } catch (error) {
    console.error('Error clearing filters:', error)
  }
}

const handleSort = ({ key, order }: { key: string, order: string }) => {
  if (isDestroyed.value) return
  try {
    ticketsStore?.fetchTickets?.({
      sort: key,
      dir: order === 'asc' ? 'asc' : 'desc',
      page: 1,
    })
  } catch (error) {
    console.error('Error handling sort:', error)
  }
}

const handlePageChange = (page: number) => {
  if (isDestroyed.value) return
  try {
    ticketsStore?.fetchTickets?.({ page })
  } catch (error) {
    console.error('Error handling page change:', error)
  }
}

const viewTicket = (ticket: any) => {
  if (isDestroyed.value) return
  try {
    router.push(`/admin/support/tickets/${ticket.id}`)
  } catch (error) {
    console.error('Error navigating to ticket:', error)
  }
}

const createTicket = () => {
  if (isDestroyed.value) return
  try {
    router.push('/admin/support/tickets/create')
  } catch (error) {
    console.error('Error navigating to create ticket:', error)
  }
}

const assignTicket = async (ticketId: string, assigneeId: string | null) => {
  if (isDestroyed.value) return
  try {
    await ticketsStore?.assignTicket?.(ticketId, assigneeId)
  } catch (error) {
    console.error('Error assigning ticket:', error)
  }
}

const changeTicketStatus = async (ticketId: string, status: string) => {
  if (isDestroyed.value) return
  try {
    await ticketsStore?.changeStatus?.(ticketId, status)
  } catch (error) {
    console.error('Error changing ticket status:', error)
  }
}

const deleteTicket = async (ticket: any) => {
  if (isDestroyed.value) return
  try {
    const confirmed = await confirmDelete('ticket', ticket.subject)
    if (confirmed) {
      await ticketsStore?.deleteTicket?.(ticket.id)
    }
  } catch (error) {
    console.error('Error deleting ticket:', error)
  }
}

// Dialog openers
const openBulkAssign = () => { showBulkAssignDialog.value = true }
const openBulkStatus = () => { showBulkStatusDialog.value = true }

// Bulk actions with error handling
const handleBulkAssign = async () => {
  if (isDestroyed.value || selectedTickets.value.length === 0 || !bulkAssigneeId.value) return
  
  try {
    const confirmed = await confirm?.({
      title: getTranslation('confirm_bulk_assign_title', 'Confirm Bulk Assign'),
      text: getTranslation('confirm_bulk_assign_text', `Assign ${selectedTickets.value.length} tickets?`),
      type: 'info',
      confirmText: getTranslation('assign', 'Assign'),
      cancelText: getTranslation('cancel', 'Cancel'),
      icon: 'tabler-user-check',
      color: 'primary',
    })

    if (confirmed) {
      await ticketsStore?.bulkAction?.('assign', selectedTickets.value, {
        assignee_id: bulkAssigneeId.value
      })
      selectedTickets.value = []
      bulkAssigneeId.value = null
      showBulkAssignDialog.value = false
    }
  } catch (error) {
    console.error('Error in bulk assign:', error)
  }
}

const handleBulkStatusChange = async () => {
  if (isDestroyed.value || selectedTickets.value.length === 0 || !bulkStatus.value) return
  
  try {
    const confirmed = await confirm?.({
      title: getTranslation('confirm_bulk_status_title', 'Confirm Status Change'),
      text: getTranslation('confirm_bulk_status_text', `Change status of ${selectedTickets.value.length} tickets?`),
      type: 'warning',
      confirmText: getTranslation('change_status', 'Change Status'),
      cancelText: getTranslation('cancel', 'Cancel'),
      icon: 'tabler-edit',
      color: 'warning',
    })

    if (confirmed) {
      await ticketsStore?.bulkAction?.('status', selectedTickets.value, {
        status: bulkStatus.value
      })
      selectedTickets.value = []
      bulkStatus.value = ''
      showBulkStatusDialog.value = false
    }
  } catch (error) {
    console.error('Error in bulk status change:', error)
  }
}

const handleBulkDelete = async () => {
  if (isDestroyed.value || selectedTickets.value.length === 0) return

  try {
    const confirmed = await confirmBulkDelete('tickets', selectedTickets.value.length)
    if (confirmed) {
      await ticketsStore?.bulkAction?.('delete', selectedTickets.value)
      selectedTickets.value = []
    }
  } catch (error) {
    console.error('Error in bulk delete:', error)
  }
}

// Watchers with error handling
watch(() => filters?.value, handleFilterChange, { deep: true })

// Lifecycle with comprehensive error handling
onMounted(async () => {
  try {
    console.log('🎫 [Tickets] Component mounting...')
    await nextTick() // Ensure component is fully mounted
    
    if (!isDestroyed.value) {
      await fetchData()
      console.log('🎫 [Tickets] Component mounted successfully')
    }
  } catch (error) {
    console.error('🚫 [Tickets] Error during component mount:', error)
    hasError.value = true
    errorMessage.value = 'Failed to initialize tickets page'
    
    // Try to navigate to a safe route after a delay
    setTimeout(() => {
      if (!isDestroyed.value) {
        router.push('/admin/dashboard').catch(console.error)
      }
    }, 2000)
  }
})

onBeforeUnmount(() => {
  console.log('🎫 [Tickets] Component unmounting...')
  isDestroyed.value = true
  
  // Clear any pending operations
  selectedTickets.value = []
  bulkAssigneeId.value = null
  bulkStatus.value = ''
})
</script>

<template>
  <div>
    <!-- Error State -->
    <VAlert
      v-if="hasError"
      type="error"
      variant="tonal"
      class="mb-6"
    >
      <VAlertTitle>{{ getTranslation('error', 'Error') }}</VAlertTitle>
      {{ errorMessage }}
      <template #append>
        <VBtn
          size="small"
          variant="outlined"
          @click="fetchData"
        >
          {{ getTranslation('retry', 'Retry') }}
        </VBtn>
      </template>
    </VAlert>

    <!-- Loading State -->
    <div v-if="isLoading && !hasError" class="d-flex justify-center align-center" style="min-height: 400px;">
      <VProgressCircular indeterminate color="primary" size="64" />
      <span class="ms-4">{{ getTranslation('loading_tickets', 'Loading tickets...') }}</span>
    </div>

    <!-- Main Content -->
    <div v-else-if="!hasError">
      <!-- Page Header -->
      <div class="d-flex justify-space-between align-center mb-6">
        <div>
          <h1 class="text-h4 font-weight-bold mb-1">
            {{ getTranslation('support_tickets', 'Support Tickets') }}
          </h1>
          <p class="text-body-1 text-medium-emphasis mb-0">
            {{ getTranslation('support_tickets_description', 'Manage customer support tickets') }}
          </p>
        </div>
        
        <VBtn
          color="primary"
          @click="createTicket"
        >
          <VIcon icon="tabler-plus" class="me-2" />
          {{ getTranslation('create_ticket_admin', 'Create Ticket') }}
        </VBtn>
      </div>

      <!-- Statistics Cards -->
      <VRow v-if="safeStatistics" class="mb-6">
        <VCol cols="12" sm="6" md="2">
          <VCard>
            <VCardText class="text-center">
              <VIcon icon="tabler-ticket" size="32" color="info" class="mb-2" />
              <div class="text-h5 font-weight-bold">{{ safeStatistics.open || 0 }}</div>
              <div class="text-body-2 text-medium-emphasis">{{ getTranslation('open_tickets', 'Open') }}</div>
            </VCardText>
          </VCard>
        </VCol>

        <VCol cols="12" sm="6" md="2">
          <VCard>
            <VCardText class="text-center">
              <VIcon icon="tabler-check" size="32" color="success" class="mb-2" />
              <div class="text-h5 font-weight-bold">{{ safeStatistics.resolved || 0 }}</div>
              <div class="text-body-2 text-medium-emphasis">{{ getTranslation('resolved_tickets', 'Resolved') }}</div>
            </VCardText>
          </VCard>
        </VCol>

        <VCol cols="12" sm="6" md="2">
          <VCard>
            <VCardText class="text-center">
              <VIcon icon="tabler-x" size="32" color="secondary" class="mb-2" />
              <div class="text-h5 font-weight-bold">{{ safeStatistics.closed || 0 }}</div>
              <div class="text-body-2 text-medium-emphasis">{{ getTranslation('closed_tickets', 'Closed') }}</div>
            </VCardText>
          </VCard>
        </VCol>

        <VCol cols="12" sm="6" md="2">
          <VCard>
            <VCardText class="text-center">
              <VIcon icon="tabler-user-x" size="32" color="warning" class="mb-2" />
              <div class="text-h5 font-weight-bold">{{ safeStatistics.unassigned || 0 }}</div>
              <div class="text-body-2 text-medium-emphasis">{{ getTranslation('unassigned_tickets', 'Unassigned') }}</div>
            </VCardText>
          </VCard>
        </VCol>

        <VCol cols="12" sm="6" md="2">
          <VCard>
            <VCardText class="text-center">
              <VIcon icon="tabler-alert-triangle" size="32" color="error" class="mb-2" />
              <div class="text-h5 font-weight-bold">{{ safeStatistics.high_priority || 0 }}</div>
              <div class="text-body-2 text-medium-emphasis">{{ getTranslation('high_priority_tickets', 'High Priority') }}</div>
            </VCardText>
          </VCard>
        </VCol>

        <VCol cols="12" sm="6" md="2">
          <VCard>
            <VCardText class="text-center">
              <VIcon icon="tabler-sum" size="32" color="primary" class="mb-2" />
              <div class="text-h5 font-weight-bold">{{ safeStatistics.total || 0 }}</div>
              <div class="text-body-2 text-medium-emphasis">{{ getTranslation('total_tickets', 'Total') }}</div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <!-- Filters and Search -->
      <VCard class="mb-6">
        <VCardText>
          <VRow>
            <!-- Search -->
            <VCol cols="12" md="4">
              <VTextField
                :model-value="filters?.q || ''"
                :label="getTranslation('search_tickets', 'Search tickets')"
                :placeholder="getTranslation('search_placeholder', 'Search by subject, ID...')"
                variant="outlined"
                density="compact"
                prepend-inner-icon="tabler-search"
                clearable
                @update:model-value="handleSearch"
              />
            </VCol>

            <!-- Quick Filters -->
            <VCol cols="12" md="8">
              <div class="d-flex align-center gap-3 flex-wrap">
                <VBtn
                  variant="outlined"
                  size="small"
                  :color="showFilters ? 'primary' : 'default'"
                  @click="showFilters = !showFilters"
                >
                  <VIcon icon="tabler-filter" class="me-2" />
                  {{ getTranslation('filters', 'Filters') }}
                </VBtn>

                <VBtn
                  variant="outlined"
                  size="small"
                  @click="clearFilters"
                >
                  <VIcon icon="tabler-x" class="me-2" />
                  {{ getTranslation('clear_filters', 'Clear') }}
                </VBtn>

                <VSpacer />

                <!-- Bulk Actions -->
                <div v-if="selectedTickets.length > 0" class="d-flex align-center gap-2">
                  <VChip color="primary" size="small">
                    {{ getTranslation('selected_count', `${selectedTickets.length} selected`) }}
                  </VChip>

                  <VBtn
                    variant="outlined"
                    size="small"
                    color="primary"
                    @click="openBulkAssign"
                  >
                    <VIcon icon="tabler-user-check" class="me-2" />
                    {{ getTranslation('assign', 'Assign') }}
                  </VBtn>

                  <VBtn
                    variant="outlined"
                    size="small"
                    color="warning"
                    @click="openBulkStatus"
                  >
                    <VIcon icon="tabler-edit" class="me-2" />
                    {{ getTranslation('change_status', 'Change Status') }}
                  </VBtn>

                  <VBtn
                    variant="outlined"
                    size="small"
                    color="error"
                    @click="handleBulkDelete"
                  >
                    <VIcon icon="tabler-trash" class="me-2" />
                    {{ getTranslation('delete', 'Delete') }}
                  </VBtn>
                </div>
              </div>
            </VCol>
          </VRow>

          <!-- Advanced Filters -->
          <VExpandTransition>
            <VRow v-if="showFilters" class="mt-4">
              <VCol cols="12" md="3">
                <VSelect
                  v-model="filters.status"
                  :items="statusOptions"
                  :label="getTranslation('status', 'Status')"
                  variant="outlined"
                  density="compact"
                  multiple
                  clearable
                />
              </VCol>

              <VCol cols="12" md="3">
                <VSelect
                  v-model="filters.priority"
                  :items="priorityOptions"
                  :label="getTranslation('priority', 'Priority')"
                  variant="outlined"
                  density="compact"
                  multiple
                  clearable
                />
              </VCol>

              <VCol cols="12" md="3">
                <VSelect
                  v-model="filters.category"
                  :items="categoryOptions"
                  :label="getTranslation('category', 'Category')"
                  variant="outlined"
                  density="compact"
                  clearable
                />
              </VCol>

              <VCol cols="12" md="3">
                <TicketAssigneeSelect
                  v-model="filters.assignee_id"
                  :label="getTranslation('assignee', 'Assignee')"
                  variant="outlined"
                  density="compact"
                  clearable
                />
              </VCol>
            </VRow>
          </VExpandTransition>
        </VCardText>
      </VCard>

      <!-- Data Table -->
      <VCard>
        <VDataTableServer
          v-model="selectedTickets"
          :headers="headers"
          :items="safeTickets"
          :loading="safeLoading"
          :items-length="totalTickets || 0"
          :items-per-page="pagination?.per_page || 15"
          :page="pagination?.current_page || 1"
          item-value="id"
          show-select
          @update:options="handleSort"
          @update:page="handlePageChange"
        >
          <!-- Ticket Reference -->
          <template #item.id="{ item }">
            <VBtn
              variant="text"
              size="small"
              color="primary"
              @click="viewTicket(item)"
            >
              #{{ item.id?.toString().slice(-8) || 'N/A' }}
            </VBtn>
          </template>

          <!-- Subject -->
          <template #item.subject="{ item }">
            <div class="d-flex flex-column">
              <span class="font-weight-medium cursor-pointer" @click="viewTicket(item)">
                {{ item.subject || 'No Subject' }}
              </span>
              <div v-if="item.messages_count" class="text-caption text-medium-emphasis">
                {{ getTranslation('messages_count', `${item.messages_count} messages`) }}
              </div>
            </div>
          </template>

          <!-- Category -->
          <template #item.category="{ item }">
            <VChip size="small" variant="tonal">
              {{ getTranslation(`ticket_category_${item.category}`, item.category) }}
            </VChip>
          </template>

          <!-- Priority -->
          <template #item.priority="{ item }">
            <TicketPriorityBadge :priority="item.priority" show-icon />
          </template>

          <!-- Status -->
          <template #item.status="{ item }">
            <TicketStatusBadge :status="item.status" />
          </template>

          <!-- Requester -->
          <template #item.requester="{ item }">
            <div v-if="item.requester" class="d-flex align-center">
              <VAvatar size="32" class="me-2">
                <VImg
                  v-if="item.requester.photo_profil"
                  :src="item.requester.photo_profil"
                  :alt="item.requester.nom_complet"
                />
                <VIcon v-else icon="tabler-user" size="18" />
              </VAvatar>
              <div class="d-flex flex-column">
                <span class="text-body-2 font-weight-medium">
                  {{ item.requester.nom_complet }}
                </span>
                <span class="text-caption text-medium-emphasis">
                  {{ item.requester.email }}
                </span>
              </div>
            </div>
          </template>

          <!-- Assignee -->
          <template #item.assignee="{ item }">
            <div v-if="item.assignee" class="d-flex align-center">
              <VAvatar size="32" class="me-2">
                <VImg
                  v-if="item.assignee.photo_profil"
                  :src="item.assignee.photo_profil"
                  :alt="item.assignee.nom_complet"
                />
                <VIcon v-else icon="tabler-user" size="18" />
              </VAvatar>
              <div class="d-flex flex-column">
                <span class="text-body-2 font-weight-medium">
                  {{ item.assignee.nom_complet }}
                </span>
                <span class="text-caption text-medium-emphasis">
                  {{ item.assignee.email }}
                </span>
              </div>
            </div>
            <VChip v-else size="small" variant="outlined" color="warning">
                            {{ getTranslation('unassigned', 'Unassigned') }}
            </VChip>
          </template>

          <!-- Last Activity -->
          <template #item.last_activity_at="{ item }">
            <div class="d-flex flex-column">
              <span class="text-body-2">
                {{ new Date(item.last_activity_at).toLocaleDateString() }}
              </span>
              <span class="text-caption text-medium-emphasis">
                {{ new Date(item.last_activity_at).toLocaleTimeString() }}
              </span>
            </div>
          </template>

          <!-- Actions -->
          <template #item.actions="{ item }">
            <VMenu>
              <template #activator="{ props }">
                <VBtn
                  icon="tabler-dots-vertical"
                  variant="text"
                  size="small"
                  v-bind="props"
                />
              </template>

              <VList>
                <VListItem @click="viewTicket(item)">
                  <template #prepend>
                    <VIcon icon="tabler-eye" />
                  </template>
                  <VListItemTitle>{{ getTranslation('view', 'View') }}</VListItemTitle>
                </VListItem>

                <VListItem @click="assignTicket(item.id, null)">
                  <template #prepend>
                    <VIcon icon="tabler-user-check" />
                  </template>
                  <VListItemTitle>{{ getTranslation('assign', 'Assign') }}</VListItemTitle>
                </VListItem>

                <VDivider />

                <VListItem @click="changeTicketStatus(item.id, 'resolved')">
                  <template #prepend>
                    <VIcon icon="tabler-check" />
                  </template>
                  <VListItemTitle>{{ getTranslation('mark_resolved', 'Mark Resolved') }}</VListItemTitle>
                </VListItem>

                <VListItem @click="changeTicketStatus(item.id, 'closed')">
                  <template #prepend>
                    <VIcon icon="tabler-x" />
                  </template>
                  <VListItemTitle>{{ getTranslation('mark_closed', 'Mark Closed') }}</VListItemTitle>
                </VListItem>

                <VDivider />

                <VListItem @click="deleteTicket(item)" class="text-error">
                  <template #prepend>
                    <VIcon icon="tabler-trash" />
                  </template>
                  <VListItemTitle>{{ getTranslation('delete', 'Delete') }}</VListItemTitle>
                </VListItem>
              </VList>
            </VMenu>
          </template>

          <!-- No Data -->
          <template #no-data>
            <div class="text-center py-8">
              <VIcon icon="tabler-ticket-off" size="64" class="mb-4" color="disabled" />
              <h3 class="text-h6 mb-2">{{ getTranslation('no_tickets_found', 'No tickets found') }}</h3>
              <p class="text-body-2 text-medium-emphasis mb-4">
                {{ getTranslation('no_tickets_description', 'No tickets match your current filters') }}
              </p>
              <VBtn color="primary" @click="createTicket">
                <VIcon icon="tabler-plus" class="me-2" />
                {{ getTranslation('create_first_ticket', 'Create First Ticket') }}
              </VBtn>
            </div>
          </template>
        </VDataTableServer>
      </VCard>

      <!-- Bulk Action Dialogs -->
      <VDialog v-model="showBulkAssignDialog" max-width="400">
        <VCard>
          <VCardTitle>{{ getTranslation('bulk_assign_tickets', 'Bulk Assign Tickets') }}</VCardTitle>
          <VCardText>
            <TicketAssigneeSelect
              v-model="bulkAssigneeId"
              :label="getTranslation('select_assignee', 'Select Assignee')"
            />
          </VCardText>
          <VCardActions>
            <VSpacer />
            <VBtn @click="showBulkAssignDialog = false">{{ getTranslation('cancel', 'Cancel') }}</VBtn>
            <VBtn color="primary" @click="handleBulkAssign">{{ getTranslation('assign', 'Assign') }}</VBtn>
          </VCardActions>
        </VCard>
      </VDialog>

      <VDialog v-model="showBulkStatusDialog" max-width="400">
        <VCard>
          <VCardTitle>{{ getTranslation('bulk_change_status', 'Bulk Change Status') }}</VCardTitle>
          <VCardText>
            <VSelect
              v-model="bulkStatus"
              :items="statusOptions"
              :label="getTranslation('select_status', 'Select Status')"
              variant="outlined"
            />
          </VCardText>
          <VCardActions>
            <VSpacer />
            <VBtn @click="showBulkStatusDialog = false">{{ getTranslation('cancel', 'Cancel') }}</VBtn>
            <VBtn color="primary" @click="handleBulkStatusChange">{{ getTranslation('change_status', 'Change Status') }}</VBtn>
          </VCardActions>
        </VCard>
      </VDialog>
    </div>

    <!-- Confirm Dialog -->
    <ConfirmActionDialog
      :is-dialog-visible="isDialogVisible"
      :is-loading="isLoading"
      :dialog-title="dialogTitle"
      :dialog-text="dialogText"
      :dialog-icon="dialogIcon"
      :dialog-color="dialogColor"
      :confirm-button-text="confirmButtonText"
      :cancel-button-text="cancelButtonText"
      @confirm="handleConfirm"
      @cancel="handleCancel"
    />
  </div>
</template>

