<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
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

definePage({
  meta: {
    requiresAuth: true,
    requiresRole: 'admin',
  },
})

const router = useRouter()
const { t } = useI18n()
const ticketsStore = useTicketsStore()
const { confirm, presets } = useQuickConfirm()

// State
const selectedTickets = ref<string[]>([])
const showFilters = ref(false)
const bulkAssigneeId = ref<string | null>(null)
const bulkStatus = ref('')

// Computed
const {
  tickets,
  loading,
  pagination,
  filters,
  statistics,
  hasTickets,
  totalTickets,
} = storeToRefs(ticketsStore)

// Filter options
const statusOptions = [
  { title: t('ticket_status_open'), value: 'open' },
  { title: t('ticket_status_pending'), value: 'pending' },
  { title: t('ticket_status_waiting_user'), value: 'waiting_user' },
  { title: t('ticket_status_waiting_third_party'), value: 'waiting_third_party' },
  { title: t('ticket_status_resolved'), value: 'resolved' },
  { title: t('ticket_status_closed'), value: 'closed' },
]

const priorityOptions = [
  { title: t('ticket_priority_low'), value: 'low' },
  { title: t('ticket_priority_normal'), value: 'normal' },
  { title: t('ticket_priority_high'), value: 'high' },
  { title: t('ticket_priority_urgent'), value: 'urgent' },
]

const categoryOptions = [
  { title: t('ticket_category_general'), value: 'general' },
  { title: t('ticket_category_orders'), value: 'orders' },
  { title: t('ticket_category_payments'), value: 'payments' },
  { title: t('ticket_category_commissions'), value: 'commissions' },
  { title: t('ticket_category_kyc'), value: 'kyc' },
  { title: t('ticket_category_technical'), value: 'technical' },
  { title: t('ticket_category_other'), value: 'other' },
]

const sortOptions = [
  { title: t('sort_last_activity'), value: 'last_activity_at' },
  { title: t('sort_created_date'), value: 'created_at' },
  { title: t('sort_subject'), value: 'subject' },
  { title: t('sort_priority'), value: 'priority' },
  { title: t('sort_status'), value: 'status' },
]

// Table headers
const headers = [
  { title: '', key: 'select', sortable: false, width: 48 },
  { title: t('ticket_ref'), key: 'id', sortable: false, width: 120 },
  { title: t('subject'), key: 'subject', sortable: true },
  { title: t('category'), key: 'category', sortable: true, width: 120 },
  { title: t('priority'), key: 'priority', sortable: true, width: 100 },
  { title: t('status'), key: 'status', sortable: true, width: 120 },
  { title: t('requester'), key: 'requester', sortable: false, width: 180 },
  { title: t('assignee'), key: 'assignee', sortable: false, width: 180 },
  { title: t('last_activity'), key: 'last_activity_at', sortable: true, width: 150 },
  { title: t('actions'), key: 'actions', sortable: false, width: 120 },
]

// Methods
const fetchData = async () => {
  await Promise.all([
    ticketsStore.fetchTickets(),
    ticketsStore.fetchStatistics(),
  ])
}

const handleSearch = (query: string) => {
  ticketsStore.fetchTickets({ q: query, page: 1 })
}

const handleFilterChange = () => {
  ticketsStore.fetchTickets({ page: 1 })
}

const clearFilters = () => {
  filters.value = {
    page: 1,
    per_page: 15,
    sort: 'last_activity_at',
    dir: 'desc',
  }
  ticketsStore.fetchTickets()
}

const handleSort = ({ key, order }: { key: string, order: string }) => {
  ticketsStore.fetchTickets({
    sort: key,
    dir: order === 'asc' ? 'asc' : 'desc',
    page: 1,
  })
}

const handlePageChange = (page: number) => {
  ticketsStore.fetchTickets({ page })
}

const viewTicket = (ticket: any) => {
  router.push(`/admin/support/tickets/${ticket.id}`)
}

const createTicket = () => {
  router.push('/admin/support/tickets/create')
}

const assignTicket = async (ticketId: string, assigneeId: string | null) => {
  await ticketsStore.assignTicket(ticketId, assigneeId)
}

const changeTicketStatus = async (ticketId: string, status: string) => {
  await ticketsStore.changeStatus(ticketId, status)
}

const deleteTicket = async (ticket: any) => {
  const confirmed = await confirm(presets.delete('ticket', ticket.subject))
  if (confirmed) {
    await ticketsStore.deleteTicket(ticket.id)
  }
}

// Bulk actions
const handleBulkAssign = async () => {
  if (selectedTickets.value.length === 0 || !bulkAssigneeId.value) return
  
  const confirmed = await confirm({
    title: t('confirm_bulk_assign_title'),
    text: t('confirm_bulk_assign_text', { count: selectedTickets.value.length }),
    type: 'info',
    confirmText: t('assign'),
    cancelText: t('cancel'),
    icon: 'tabler-user-check',
    color: 'primary',
  })

  if (confirmed) {
    await ticketsStore.bulkAction('assign', selectedTickets.value, {
      assignee_id: bulkAssigneeId.value
    })
    selectedTickets.value = []
    bulkAssigneeId.value = null
  }
}

const handleBulkStatusChange = async () => {
  if (selectedTickets.value.length === 0 || !bulkStatus.value) return
  
  const confirmed = await confirm({
    title: t('confirm_bulk_status_title'),
    text: t('confirm_bulk_status_text', { count: selectedTickets.value.length }),
    type: 'warning',
    confirmText: t('change_status'),
    cancelText: t('cancel'),
    icon: 'tabler-edit',
    color: 'warning',
  })

  if (confirmed) {
    await ticketsStore.bulkAction('status', selectedTickets.value, {
      status: bulkStatus.value
    })
    selectedTickets.value = []
    bulkStatus.value = ''
  }
}

const handleBulkDelete = async () => {
  if (selectedTickets.value.length === 0) return
  
  const confirmed = await confirm(presets.bulkDelete('tickets', selectedTickets.value.length))
  if (confirmed) {
    await ticketsStore.bulkAction('delete', selectedTickets.value)
    selectedTickets.value = []
  }
}

// Watchers
watch(() => filters.value, handleFilterChange, { deep: true })

// Lifecycle
onMounted(() => {
  fetchData()
})
</script>

<template>
  <div>
    <!-- Page Header -->
    <div class="d-flex justify-space-between align-center mb-6">
      <div>
        <h1 class="text-h4 font-weight-bold mb-1">
          {{ t('support_tickets') }}
        </h1>
        <p class="text-body-1 text-medium-emphasis mb-0">
          {{ t('support_tickets_description') }}
        </p>
      </div>
      
      <VBtn
        color="primary"
        @click="createTicket"
      >
        <VIcon icon="tabler-plus" class="me-2" />
        {{ t('create_ticket_admin') }}
      </VBtn>
    </div>

    <!-- Statistics Cards -->
    <VRow v-if="statistics" class="mb-6">
      <VCol cols="12" sm="6" md="2">
        <VCard>
          <VCardText class="text-center">
            <VIcon icon="tabler-ticket" size="32" color="info" class="mb-2" />
            <div class="text-h5 font-weight-bold">{{ statistics.open }}</div>
            <div class="text-body-2 text-medium-emphasis">{{ t('open_tickets') }}</div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="2">
        <VCard>
          <VCardText class="text-center">
            <VIcon icon="tabler-check" size="32" color="success" class="mb-2" />
            <div class="text-h5 font-weight-bold">{{ statistics.resolved }}</div>
            <div class="text-body-2 text-medium-emphasis">{{ t('resolved_tickets') }}</div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="2">
        <VCard>
          <VCardText class="text-center">
            <VIcon icon="tabler-x" size="32" color="secondary" class="mb-2" />
            <div class="text-h5 font-weight-bold">{{ statistics.closed }}</div>
            <div class="text-body-2 text-medium-emphasis">{{ t('closed_tickets') }}</div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="2">
        <VCard>
          <VCardText class="text-center">
            <VIcon icon="tabler-user-x" size="32" color="warning" class="mb-2" />
            <div class="text-h5 font-weight-bold">{{ statistics.unassigned }}</div>
            <div class="text-body-2 text-medium-emphasis">{{ t('unassigned_tickets') }}</div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="2">
        <VCard>
          <VCardText class="text-center">
            <VIcon icon="tabler-alert-triangle" size="32" color="error" class="mb-2" />
            <div class="text-h5 font-weight-bold">{{ statistics.high_priority }}</div>
            <div class="text-body-2 text-medium-emphasis">{{ t('high_priority_tickets') }}</div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="2">
        <VCard>
          <VCardText class="text-center">
            <VIcon icon="tabler-sum" size="32" color="primary" class="mb-2" />
            <div class="text-h5 font-weight-bold">{{ statistics.total }}</div>
            <div class="text-body-2 text-medium-emphasis">{{ t('total_tickets') }}</div>
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
              :model-value="filters.q"
              :label="t('search_tickets')"
              :placeholder="t('search_placeholder')"
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
                {{ t('filters') }}
              </VBtn>

              <VBtn
                variant="outlined"
                size="small"
                @click="clearFilters"
              >
                <VIcon icon="tabler-x" class="me-2" />
                {{ t('clear_filters') }}
              </VBtn>

              <VSpacer />

              <!-- Bulk Actions -->
              <div v-if="selectedTickets.length > 0" class="d-flex align-center gap-2">
                <VChip color="primary" size="small">
                  {{ t('selected_count', { count: selectedTickets.length }) }}
                </VChip>

                <VBtn
                  variant="outlined"
                  size="small"
                  color="primary"
                  @click="handleBulkAssign"
                >
                  <VIcon icon="tabler-user-check" class="me-2" />
                  {{ t('assign') }}
                </VBtn>

                <VBtn
                  variant="outlined"
                  size="small"
                  color="warning"
                  @click="handleBulkStatusChange"
                >
                  <VIcon icon="tabler-edit" class="me-2" />
                  {{ t('change_status') }}
                </VBtn>

                <VBtn
                  variant="outlined"
                  size="small"
                  color="error"
                  @click="handleBulkDelete"
                >
                  <VIcon icon="tabler-trash" class="me-2" />
                  {{ t('delete') }}
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
                :label="t('status')"
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
                :label="t('priority')"
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
                :label="t('category')"
                variant="outlined"
                density="compact"
                clearable
              />
            </VCol>

            <VCol cols="12" md="3">
              <TicketAssigneeSelect
                v-model="filters.assignee_id"
                :label="t('assignee')"
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
        :items="tickets"
        :loading="loading"
        :items-length="totalTickets"
        :items-per-page="pagination.per_page"
        :page="pagination.current_page"
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
            #{{ item.id.slice(-8) }}
          </VBtn>
        </template>

        <!-- Subject -->
        <template #item.subject="{ item }">
          <div class="d-flex flex-column">
            <span class="font-weight-medium cursor-pointer" @click="viewTicket(item)">
              {{ item.subject }}
            </span>
            <div v-if="item.messages_count" class="text-caption text-medium-emphasis">
              {{ t('messages_count', { count: item.messages_count }) }}
            </div>
          </div>
        </template>

        <!-- Category -->
        <template #item.category="{ item }">
          <VChip size="small" variant="tonal">
            {{ t(`ticket_category_${item.category}`) }}
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
            {{ t('unassigned') }}
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
                <VListItemTitle>{{ t('view') }}</VListItemTitle>
              </VListItem>

              <VListItem @click="assignTicket(item.id, null)">
                <template #prepend>
                  <VIcon icon="tabler-user-check" />
                </template>
                <VListItemTitle>{{ t('assign') }}</VListItemTitle>
              </VListItem>

              <VDivider />

              <VListItem @click="changeTicketStatus(item.id, 'resolved')">
                <template #prepend>
                  <VIcon icon="tabler-check" />
                </template>
                <VListItemTitle>{{ t('mark_resolved') }}</VListItemTitle>
              </VListItem>

              <VListItem @click="changeTicketStatus(item.id, 'closed')">
                <template #prepend>
                  <VIcon icon="tabler-x" />
                </template>
                <VListItemTitle>{{ t('mark_closed') }}</VListItemTitle>
              </VListItem>

              <VDivider />

              <VListItem @click="deleteTicket(item)" class="text-error">
                <template #prepend>
                  <VIcon icon="tabler-trash" />
                </template>
                <VListItemTitle>{{ t('delete') }}</VListItemTitle>
              </VListItem>
            </VList>
          </VMenu>
        </template>

        <!-- No Data -->
        <template #no-data>
          <div class="text-center py-8">
            <VIcon icon="tabler-ticket-off" size="64" class="mb-4" color="disabled" />
            <h3 class="text-h6 mb-2">{{ t('no_tickets_found') }}</h3>
            <p class="text-body-2 text-medium-emphasis mb-4">
              {{ t('no_tickets_description') }}
            </p>
            <VBtn color="primary" @click="createTicket">
              <VIcon icon="tabler-plus" class="me-2" />
              {{ t('create_first_ticket') }}
            </VBtn>
          </div>
        </template>
      </VDataTableServer>
    </VCard>

    <!-- Bulk Action Dialogs -->
    <VDialog v-model="bulkAssigneeId" max-width="400">
      <VCard>
        <VCardTitle>{{ t('bulk_assign_tickets') }}</VCardTitle>
        <VCardText>
          <TicketAssigneeSelect
            v-model="bulkAssigneeId"
            :label="t('select_assignee')"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn @click="bulkAssigneeId = null">{{ t('cancel') }}</VBtn>
          <VBtn color="primary" @click="handleBulkAssign">{{ t('assign') }}</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog v-model="bulkStatus" max-width="400">
      <VCard>
        <VCardTitle>{{ t('bulk_change_status') }}</VCardTitle>
        <VCardText>
          <VSelect
            v-model="bulkStatus"
            :items="statusOptions"
            :label="t('select_status')"
            variant="outlined"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn @click="bulkStatus = ''">{{ t('cancel') }}</VBtn>
          <VBtn color="primary" @click="handleBulkStatusChange">{{ t('change_status') }}</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Confirm Dialog -->
    <ConfirmActionDialog />
  </div>
</template>
