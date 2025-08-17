<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useTicketsStore } from '@/stores/admin/tickets'
import { useQuickConfirm } from '@/composables/useConfirmAction'
import TicketStatusBadge from '@/components/admin/tickets/TicketStatusBadge.vue'
import TicketPriorityBadge from '@/components/admin/tickets/TicketPriorityBadge.vue'
import TicketAssigneeSelect from '@/components/admin/tickets/TicketAssigneeSelect.vue'
import TicketEntityLink from '@/components/admin/tickets/TicketEntityLink.vue'
import MessageComposer from '@/components/admin/tickets/MessageComposer.vue'
import ConfirmActionDialog from '@/components/common/ConfirmActionDialog.vue'

definePage({
  meta: {
    requiresAuth: true,
    requiresRole: 'admin',
  },
})

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const ticketsStore = useTicketsStore()
const { confirm, presets } = useQuickConfirm()

// State
const ticketId = computed(() => route.params.id as string)
const showAssignDialog = ref(false)
const showStatusDialog = ref(false)
const newAssigneeId = ref<string | null>(null)
const newStatus = ref('')

// Store refs
const {
  currentTicket: ticket,
  messages,
  loading,
  messagesLoading,
} = storeToRefs(ticketsStore)

// Status options
const statusOptions = [
  { title: t('ticket_status_open'), value: 'open' },
  { title: t('ticket_status_pending'), value: 'pending' },
  { title: t('ticket_status_waiting_user'), value: 'waiting_user' },
  { title: t('ticket_status_waiting_third_party'), value: 'waiting_third_party' },
  { title: t('ticket_status_resolved'), value: 'resolved' },
  { title: t('ticket_status_closed'), value: 'closed' },
]

// Computed
const canEdit = computed(() => ticket.value?.is_open)
const hasMessages = computed(() => messages.value.length > 0)

// Methods
const fetchData = async () => {
  await Promise.all([
    ticketsStore.fetchTicket(ticketId.value),
    ticketsStore.fetchMessages(ticketId.value),
  ])
}

const goBack = () => {
  router.push('/admin/support/tickets')
}

const handleAssign = async () => {
  if (!ticket.value) return
  
  await ticketsStore.assignTicket(ticket.value.id, newAssigneeId.value)
  showAssignDialog.value = false
  newAssigneeId.value = null
}

const handleStatusChange = async () => {
  if (!ticket.value || !newStatus.value) return
  
  await ticketsStore.changeStatus(ticket.value.id, newStatus.value)
  showStatusDialog.value = false
  newStatus.value = ''
}

const handleDelete = async () => {
  if (!ticket.value) return
  
  const confirmed = await confirm(presets.delete('ticket', ticket.value.subject))
  if (confirmed) {
    const success = await ticketsStore.deleteTicket(ticket.value.id)
    if (success) {
      router.push('/admin/support/tickets')
    }
  }
}

const handleMessageSubmit = async (messageData: any) => {
  if (!ticket.value) return
  
  await ticketsStore.addMessage(ticket.value.id, messageData)
}

const formatDate = (date: string) => {
  return new Date(date).toLocaleString()
}

const getTimeAgo = (date: string) => {
  const now = new Date()
  const messageDate = new Date(date)
  const diffInHours = Math.floor((now.getTime() - messageDate.getTime()) / (1000 * 60 * 60))
  
  if (diffInHours < 1) return t('just_now')
  if (diffInHours < 24) return t('hours_ago', { count: diffInHours })
  
  const diffInDays = Math.floor(diffInHours / 24)
  return t('days_ago', { count: diffInDays })
}

// Watchers
watch(ticketId, fetchData, { immediate: true })

// Lifecycle
onMounted(() => {
  fetchData()
})
</script>

<template>
  <div>
    <!-- Loading State -->
    <div v-if="loading" class="d-flex justify-center align-center" style="min-height: 400px;">
      <VProgressCircular indeterminate color="primary" size="64" />
    </div>

    <!-- Ticket Content -->
    <div v-else-if="ticket">
      <!-- Page Header -->
      <div class="d-flex align-center mb-6">
        <VBtn
          icon="tabler-arrow-left"
          variant="text"
          @click="goBack"
        />
        <div class="ms-4 flex-grow-1">
          <div class="d-flex align-center gap-3 mb-2">
            <h1 class="text-h5 font-weight-bold">
              #{{ ticket.id.slice(-8) }}
            </h1>
            <TicketStatusBadge :status="ticket.status" />
            <TicketPriorityBadge :priority="ticket.priority" show-icon />
          </div>
          <h2 class="text-h6 mb-1">{{ ticket.subject }}</h2>
          <p class="text-body-2 text-medium-emphasis mb-0">
            {{ t('created_by') }} {{ ticket.requester?.nom_complet }} • 
            {{ formatDate(ticket.created_at) }}
          </p>
        </div>

        <!-- Header Actions -->
        <div class="d-flex gap-2">
          <VBtn
            variant="outlined"
            @click="showAssignDialog = true"
          >
            <VIcon icon="tabler-user-check" class="me-2" />
            {{ t('assign') }}
          </VBtn>

          <VBtn
            variant="outlined"
            @click="showStatusDialog = true"
          >
            <VIcon icon="tabler-edit" class="me-2" />
            {{ t('change_status') }}
          </VBtn>

          <VMenu>
            <template #activator="{ props }">
              <VBtn
                icon="tabler-dots-vertical"
                variant="outlined"
                v-bind="props"
              />
            </template>

            <VList>
              <VListItem @click="handleDelete" class="text-error">
                <template #prepend>
                  <VIcon icon="tabler-trash" />
                </template>
                <VListItemTitle>{{ t('delete_ticket') }}</VListItemTitle>
              </VListItem>
            </VList>
          </VMenu>
        </div>
      </div>

      <VRow>
        <!-- Messages Timeline -->
        <VCol cols="12" lg="8">
          <!-- Messages -->
          <VCard class="mb-6">
            <VCardTitle>
              {{ t('messages') }}
              <VChip v-if="hasMessages" size="small" class="ms-2">
                {{ messages.length }}
              </VChip>
            </VCardTitle>

            <VCardText>
              <div v-if="messagesLoading" class="text-center py-8">
                <VProgressCircular indeterminate color="primary" />
              </div>

              <div v-else-if="hasMessages" class="messages-timeline">
                <div
                  v-for="(message, index) in messages"
                  :key="message.id"
                  class="message-item"
                  :class="{ 'mb-6': index < messages.length - 1 }"
                >
                  <div class="d-flex">
                    <!-- Avatar -->
                    <VAvatar size="40" class="me-3">
                      <VImg
                        v-if="message.sender?.photo_profil"
                        :src="message.sender.photo_profil"
                        :alt="message.sender.nom_complet"
                      />
                      <VIcon v-else icon="tabler-user" size="20" />
                    </VAvatar>

                    <!-- Message Content -->
                    <div class="flex-grow-1">
                      <!-- Message Header -->
                      <div class="d-flex align-center justify-space-between mb-2">
                        <div class="d-flex align-center gap-2">
                          <span class="font-weight-medium">
                            {{ message.sender?.nom_complet }}
                          </span>
                          <VChip
                            :color="message.is_internal ? 'warning' : 'primary'"
                            size="x-small"
                            variant="tonal"
                          >
                            {{ message.is_internal ? t('internal') : t('public') }}
                          </VChip>
                        </div>
                        <span class="text-caption text-medium-emphasis">
                          {{ getTimeAgo(message.created_at) }}
                        </span>
                      </div>

                      <!-- Message Body -->
                      <VCard
                        variant="outlined"
                        :color="message.is_internal ? 'warning' : 'default'"
                        class="message-body"
                      >
                        <VCardText>
                          <div v-html="message.body" />

                          <!-- Attachments -->
                          <div v-if="message.attachments && message.attachments.length > 0" class="mt-3">
                            <div class="text-caption text-medium-emphasis mb-2">
                              {{ t('attachments') }}:
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                              <VChip
                                v-for="attachment in message.attachments"
                                :key="attachment.id"
                                size="small"
                                variant="outlined"
                                :href="attachment.url"
                                target="_blank"
                              >
                                <VIcon
                                  :icon="attachment.is_image ? 'tabler-photo' : 'tabler-file'"
                                  size="14"
                                  class="me-1"
                                />
                                {{ attachment.original_name }}
                                <span class="text-caption ms-1">({{ attachment.human_size }})</span>
                              </VChip>
                            </div>
                          </div>
                        </VCardText>
                      </VCard>
                    </div>
                  </div>
                </div>
              </div>

              <div v-else class="text-center py-8">
                <VIcon icon="tabler-message-off" size="64" class="mb-4" color="disabled" />
                <h3 class="text-h6 mb-2">{{ t('no_messages') }}</h3>
                <p class="text-body-2 text-medium-emphasis">
                  {{ t('no_messages_description') }}
                </p>
              </div>
            </VCardText>
          </VCard>

          <!-- Message Composer -->
          <MessageComposer
            :loading="messagesLoading"
            :disabled="!canEdit"
            @submit="handleMessageSubmit"
          />
        </VCol>

        <!-- Sidebar -->
        <VCol cols="12" lg="4">
          <!-- Ticket Info -->
          <VCard class="mb-6">
            <VCardTitle>{{ t('ticket_information') }}</VCardTitle>
            <VCardText>
              <div class="d-flex flex-column gap-4">
                <!-- Status -->
                <div>
                  <div class="text-caption text-medium-emphasis mb-1">{{ t('status') }}</div>
                  <TicketStatusBadge :status="ticket.status" />
                </div>

                <!-- Priority -->
                <div>
                  <div class="text-caption text-medium-emphasis mb-1">{{ t('priority') }}</div>
                  <TicketPriorityBadge :priority="ticket.priority" show-icon />
                </div>

                <!-- Category -->
                <div>
                  <div class="text-caption text-medium-emphasis mb-1">{{ t('category') }}</div>
                  <VChip size="small" variant="tonal">
                    {{ t(`ticket_category_${ticket.category}`) }}
                  </VChip>
                </div>

                <!-- Created -->
                <div>
                  <div class="text-caption text-medium-emphasis mb-1">{{ t('created') }}</div>
                  <div class="text-body-2">{{ formatDate(ticket.created_at) }}</div>
                </div>

                <!-- Last Activity -->
                <div>
                  <div class="text-caption text-medium-emphasis mb-1">{{ t('last_activity') }}</div>
                  <div class="text-body-2">{{ formatDate(ticket.last_activity_at) }}</div>
                </div>

                <!-- First Response -->
                <div v-if="ticket.first_response_at">
                  <div class="text-caption text-medium-emphasis mb-1">{{ t('first_response') }}</div>
                  <div class="text-body-2">{{ formatDate(ticket.first_response_at) }}</div>
                </div>

                <!-- Resolution -->
                <div v-if="ticket.resolved_at">
                  <div class="text-caption text-medium-emphasis mb-1">{{ t('resolved') }}</div>
                  <div class="text-body-2">{{ formatDate(ticket.resolved_at) }}</div>
                </div>
              </div>
            </VCardText>
          </VCard>

          <!-- People -->
          <VCard class="mb-6">
            <VCardTitle>{{ t('people') }}</VCardTitle>
            <VCardText>
              <div class="d-flex flex-column gap-4">
                <!-- Requester -->
                <div>
                  <div class="text-caption text-medium-emphasis mb-2">{{ t('requester') }}</div>
                  <div v-if="ticket.requester" class="d-flex align-center">
                    <VAvatar size="32" class="me-3">
                      <VImg
                        v-if="ticket.requester.photo_profil"
                        :src="ticket.requester.photo_profil"
                        :alt="ticket.requester.nom_complet"
                      />
                      <VIcon v-else icon="tabler-user" size="18" />
                    </VAvatar>
                    <div>
                      <div class="text-body-2 font-weight-medium">
                        {{ ticket.requester.nom_complet }}
                      </div>
                      <div class="text-caption text-medium-emphasis">
                        {{ ticket.requester.email }}
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Assignee -->
                <div>
                  <div class="text-caption text-medium-emphasis mb-2">{{ t('assignee') }}</div>
                  <div v-if="ticket.assignee" class="d-flex align-center">
                    <VAvatar size="32" class="me-3">
                      <VImg
                        v-if="ticket.assignee.photo_profil"
                        :src="ticket.assignee.photo_profil"
                        :alt="ticket.assignee.nom_complet"
                      />
                      <VIcon v-else icon="tabler-user" size="18" />
                    </VAvatar>
                    <div>
                      <div class="text-body-2 font-weight-medium">
                        {{ ticket.assignee.nom_complet }}
                      </div>
                      <div class="text-caption text-medium-emphasis">
                        {{ ticket.assignee.email }}
                      </div>
                    </div>
                  </div>
                  <VChip v-else size="small" variant="outlined" color="warning">
                    {{ t('unassigned') }}
                  </VChip>
                </div>
              </div>
            </VCardText>
          </VCard>

          <!-- Related Items -->
          <VCard v-if="ticket.relations && ticket.relations.length > 0">
            <VCardTitle>{{ t('related_items') }}</VCardTitle>
            <VCardText>
              <div class="d-flex flex-column gap-2">
                <TicketEntityLink
                  v-for="relation in ticket.relations"
                  :key="relation.id"
                  :relation="relation"
                />
              </div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>
    </div>

    <!-- Not Found -->
    <div v-else class="text-center py-8">
      <VIcon icon="tabler-ticket-off" size="64" class="mb-4" color="disabled" />
      <h3 class="text-h6 mb-2">{{ t('ticket_not_found') }}</h3>
      <p class="text-body-2 text-medium-emphasis mb-4">
        {{ t('ticket_not_found_description') }}
      </p>
      <VBtn color="primary" @click="goBack">
        {{ t('back_to_tickets') }}
      </VBtn>
    </div>

    <!-- Assign Dialog -->
    <VDialog v-model="showAssignDialog" max-width="400">
      <VCard>
        <VCardTitle>{{ t('assign_ticket') }}</VCardTitle>
        <VCardText>
          <TicketAssigneeSelect
            v-model="newAssigneeId"
            :label="t('select_assignee')"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn @click="showAssignDialog = false">{{ t('cancel') }}</VBtn>
          <VBtn color="primary" @click="handleAssign">{{ t('assign') }}</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Status Dialog -->
    <VDialog v-model="showStatusDialog" max-width="400">
      <VCard>
        <VCardTitle>{{ t('change_status') }}</VCardTitle>
        <VCardText>
          <VSelect
            v-model="newStatus"
            :items="statusOptions"
            :label="t('select_status')"
            variant="outlined"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn @click="showStatusDialog = false">{{ t('cancel') }}</VBtn>
          <VBtn color="primary" @click="handleStatusChange">{{ t('change_status') }}</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Confirm Dialog -->
    <ConfirmActionDialog />
  </div>
</template>

<style scoped>
.messages-timeline {
  position: relative;
}

.message-item {
  position: relative;
}

.message-body {
  border-left: 3px solid rgb(var(--v-theme-primary));
}

.message-body.warning {
  border-left-color: rgb(var(--v-theme-warning));
}
</style>
