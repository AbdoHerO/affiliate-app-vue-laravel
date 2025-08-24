<script setup lang="ts">
import { ref, onMounted, computed, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'
import { useAffiliateTicketsStore } from '@/stores/affiliate/tickets'
import { useNotifications } from '@/composables/useNotifications'
import { useQuickConfirm } from '@/composables/useConfirmAction'
import Breadcrumbs from '@/components/common/Breadcrumbs.vue'
import ConfirmActionDialog from '@/components/common/ConfirmActionDialog.vue'

definePage({
  meta: {
    requiresAuth: true,
    requiresRole: 'affiliate',
  },
})

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const { showSuccess, showError } = useNotifications()
const confirmComposable = useQuickConfirm()
const { confirm } = confirmComposable

// Store
const ticketsStore = useAffiliateTicketsStore()
const { currentTicket, loading, error } = storeToRefs(ticketsStore)

// Local state
const newMessage = ref('')
const attachments = ref<File[]>([])
const messagesContainer = ref<HTMLElement>()

// Computed
const breadcrumbs = computed(() => [
  { title: t('nav.dashboard'), to: '/affiliate/dashboard' },
  { title: t('affiliate_tickets_title'), to: '/affiliate/tickets' },
  { title: t('affiliate_tickets_detail_title', { id: route.params.id?.toString().slice(-8) }), active: true },
])

const canClose = computed(() => {
  return currentTicket.value?.status !== 'closed'
})

const canReopen = computed(() => {
  return currentTicket.value?.status === 'closed'
})

const sortedMessages = computed(() => {
  if (!currentTicket.value?.messages) return []
  return [...currentTicket.value.messages].sort((a, b) => 
    new Date(a.created_at).getTime() - new Date(b.created_at).getTime()
  )
})

// Methods
const fetchTicket = async () => {
  try {
    await ticketsStore.fetchTicket(route.params.id as string)
    await nextTick()
    scrollToBottom()
  } catch (err) {
    showError(t('errors.ticket_load_failed'))
    router.push({ name: 'affiliate-tickets' })
  }
}

const handleFileUpload = (event: Event) => {
  const target = event.target as HTMLInputElement
  if (target.files) {
    attachments.value = Array.from(target.files)
  }
}

const addMessage = async () => {
  if (!newMessage.value.trim()) {
    showError(t('errors.message_required'))
    return
  }

  try {
    await ticketsStore.addMessage(route.params.id as string, {
      message: newMessage.value,
      attachments: attachments.value,
    })
    
    newMessage.value = ''
    attachments.value = []
    showSuccess(t('affiliate_tickets_message_success'))
    
    await nextTick()
    scrollToBottom()
  } catch (err: any) {
    showError(err.message || t('errors.message_add_failed'))
  }
}

const closeTicket = async () => {
  try {
    const result = await confirm({
      title: t('affiliate_tickets_close_title'),
      text: t('affiliate_tickets_close_text'),
      icon: 'tabler-lock',
      color: 'warning',
    })

    if (result) {
      await ticketsStore.updateTicketStatus(route.params.id as string, 'closed')
      showSuccess(t('affiliate_tickets_close_success'))
    }
  } catch (err: any) {
    showError(err.message || t('errors.ticket_close_failed'))
  }
}

const reopenTicket = async () => {
  try {
    const result = await confirm({
      title: t('affiliate_tickets_reopen_title'),
      text: t('affiliate_tickets_reopen_text'),
      icon: 'tabler-lock-open',
      color: 'info',
    })

    if (result) {
      await ticketsStore.updateTicketStatus(route.params.id as string, 'open')
      showSuccess(t('affiliate_tickets_reopen_success'))
    }
  } catch (err: any) {
    showError(err.message || t('errors.ticket_reopen_failed'))
  }
}

const scrollToBottom = () => {
  if (messagesContainer.value) {
    messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
  }
}

const formatDate = (date: string) => {
  return new Date(date).toLocaleDateString('fr-FR', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

const formatFileSize = (bytes: number) => {
  if (bytes === 0) return '0 Bytes'
  const k = 1024
  const sizes = ['Bytes', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}

const goBack = () => {
  router.push({ name: 'affiliate-tickets' })
}

// Lifecycle
onMounted(() => {
  fetchTicket()
})
</script>

<template>
  <div>
    <!-- Breadcrumbs -->
    <Breadcrumbs :items="breadcrumbs" />

    <!-- Loading State -->
    <div v-if="loading.detail" class="text-center py-8">
      <VProgressCircular
        indeterminate
        color="primary"
        size="64"
      />
      <p class="text-body-1 mt-4">{{ t('affiliate_tickets_loading') }}</p>
    </div>

    <!-- Ticket Details -->
    <div v-else-if="currentTicket">
      <!-- Header -->
      <div class="d-flex justify-space-between align-center mb-6">
        <div>
          <h1 class="text-h4 font-weight-bold mb-1">
            {{ currentTicket.subject }}
          </h1>
          <div class="d-flex align-center gap-4">
            <VChip
              :color="ticketsStore.getStatusColor(currentTicket.status)"
              variant="tonal"
            >
              {{ ticketsStore.getStatusLabel(currentTicket.status) }}
            </VChip>
            <VChip
              :color="ticketsStore.getPriorityColor(currentTicket.priority)"
              variant="tonal"
              size="small"
            >
              {{ ticketsStore.getPriorityLabel(currentTicket.priority) }}
            </VChip>
            <VChip
              color="info"
              variant="tonal"
              size="small"
            >
              {{ ticketsStore.getCategoryLabel(currentTicket.category) }}
            </VChip>
            <span class="text-body-2 text-medium-emphasis">
              {{ t('affiliate_tickets_created_on', { date: formatDate(currentTicket.created_at) }) }}
            </span>
          </div>
        </div>
        <div class="d-flex gap-2">
          <VBtn
            v-if="canReopen"
            color="info"
            variant="outlined"
            prepend-icon="tabler-lock-open"
            :loading="loading.status"
            @click="reopenTicket"
          >
            {{ t('actions.reopen') }}
          </VBtn>
          <VBtn
            v-if="canClose"
            color="warning"
            variant="outlined"
            prepend-icon="tabler-lock"
            :loading="loading.status"
            @click="closeTicket"
          >
            {{ t('actions.close') }}
          </VBtn>
          <VBtn
            variant="outlined"
            prepend-icon="tabler-arrow-left"
            @click="goBack"
          >
            {{ t('actions.back') }}
          </VBtn>
        </div>
      </div>

      <!-- Messages -->
      <VCard class="mb-6">
        <VCardTitle>{{ t('affiliate_tickets_conversation') }}</VCardTitle>
        <VCardText>
          <div
            ref="messagesContainer"
            class="messages-container"
            style="max-height: 500px; overflow-y: auto;"
          >
            <div
              v-for="message in sortedMessages"
              :key="message.id"
              class="message mb-4"
            >
              <div class="d-flex align-start gap-3">
                <VAvatar size="40">
                  <VIcon icon="tabler-user" />
                </VAvatar>
                <div class="flex-grow-1">
                  <div class="d-flex align-center justify-space-between mb-2">
                    <div>
                      <span class="font-weight-medium">{{ message.sender?.nom_complet || t('support') }}</span>
                      <VChip
                        v-if="message.sender?.id === currentTicket.requester_id"
                        size="x-small"
                        color="primary"
                        variant="tonal"
                        class="ml-2"
                      >
                        {{ t('affiliate_tickets_you') }}
                      </VChip>
                      <VChip
                        v-else
                        size="x-small"
                        color="success"
                        variant="tonal"
                        class="ml-2"
                      >
                        {{ t('affiliate_tickets_support') }}
                      </VChip>
                    </div>
                    <span class="text-caption text-medium-emphasis">
                      {{ formatDate(message.created_at) }}
                    </span>
                  </div>
                  <div class="message-content">
                    <p class="text-body-1 mb-2" style="white-space: pre-wrap;">{{ message.body }}</p>
                    
                    <!-- Attachments -->
                    <div v-if="message.attachments?.length" class="attachments">
                      <h4 class="text-subtitle-2 mb-2">{{ t('affiliate_tickets_attachments') }}:</h4>
                      <div class="d-flex flex-wrap gap-2">
                        <VChip
                          v-for="attachment in message.attachments"
                          :key="attachment.id"
                          size="small"
                          variant="outlined"
                          prepend-icon="tabler-paperclip"
                          clickable
                          color="primary"
                          :href="attachment.url"
                          target="_blank"
                          component="a"
                        >
                          <VIcon icon="tabler-download" size="16" class="me-1" />
                          {{ attachment.original_name }} ({{ formatFileSize(attachment.size) }})
                        </VChip>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <VDivider class="mt-4" />
            </div>
          </div>
        </VCardText>
      </VCard>

      <!-- Reply Form -->
      <VCard v-if="currentTicket.status !== 'closed'">
        <VCardTitle>{{ t('affiliate_tickets_add_reply') }}</VCardTitle>
        <VCardText>
          <VForm @submit.prevent="addMessage">
            <VTextarea
              v-model="newMessage"
              :label="t('affiliate_tickets_your_message')"
              :placeholder="t('affiliate_tickets_message_placeholder')"
              rows="4"
              counter="5000"
              maxlength="5000"
              required
            />
            
            <VFileInput
              :label="t('affiliate_tickets_attachments_optional')"
              multiple
              accept="image/*,.pdf,.doc,.docx,.txt"
              prepend-icon="tabler-paperclip"
              class="mt-4"
              @change="handleFileUpload"
            />
            <p class="text-caption text-medium-emphasis mt-1">
              {{ t('affiliate_tickets_file_formats') }}
            </p>
          </VForm>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            color="primary"
            prepend-icon="tabler-send"
            :loading="loading.message"
            @click="addMessage"
          >
            {{ t('actions.send') }}
          </VBtn>
        </VCardActions>
      </VCard>

      <!-- Closed Notice -->
      <VAlert
        v-else
        type="info"
        variant="tonal"
        class="mt-6"
      >
        {{ t('affiliate_tickets_closed_notice') }}
      </VAlert>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="text-center py-8">
      <VIcon
        icon="tabler-alert-circle"
        size="64"
        class="text-error mb-4"
      />
      <h3 class="text-h6 mb-2">{{ t('general.error') }}</h3>
      <p class="text-body-2 text-medium-emphasis mb-4">{{ error }}</p>
      <VBtn
        color="primary"
        @click="goBack"
      >
        {{ t('affiliate_tickets_back_to_tickets') }}
      </VBtn>
    </div>

    <!-- Confirm Dialog -->
    <ConfirmActionDialog
      :is-dialog-visible="confirmComposable.isDialogVisible.value"
      :is-loading="confirmComposable.isLoading.value"
      :dialog-title="confirmComposable.dialogTitle.value"
      :dialog-text="confirmComposable.dialogText.value"
      :dialog-icon="confirmComposable.dialogIcon.value"
      :dialog-color="confirmComposable.dialogColor.value"
      :confirm-button-text="confirmComposable.confirmButtonText.value"
      :cancel-button-text="confirmComposable.cancelButtonText.value"
      @confirm="confirmComposable.handleConfirm"
      @cancel="confirmComposable.handleCancel"
    />
  </div>
</template>

<style scoped>
.messages-container {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 4px;
  padding: 16px;
}

.message:last-child .v-divider {
  display: none;
}
</style>
