<script setup lang="ts">
import { ref, computed, onMounted, watch, onBeforeUnmount, nextTick } from 'vue'
import { storeToRefs } from 'pinia'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useTicketsStore } from '@/stores/admin/tickets'
import { $api } from '@/utils/api'

// Safe component imports with fallbacks
let TicketStatusBadge: any = null
let TicketPriorityBadge: any = null
let TicketEntityLink: any = null
let MessageComposer: any = null
let ConfirmActionDialog: any = null

// Define page meta
definePage({
  meta: {
    requiresAuth: true,
    requiresRole: 'admin',
  },
})

// Initialize composables
const route = useRoute()
const router = useRouter()
const { t } = useI18n()

// Component state
const isDestroyed = ref(false)
const isInitialized = ref(false)
const pageLoading = ref(true)
const hasError = ref(false)
const errorMessage = ref('')
const componentsLoaded = ref(false)

// Store and composables - will be initialized safely
let ticketsStore: any = null
let storeRefs: any = null
let confirm: any = null
let presets: any = null

// Local reactive data (fallbacks)
const ticket = ref(null)
const messages = ref([])
const loading = ref(false)
const messagesLoading = ref(false)

// Form state
const showAssignDialog = ref(false)
const showStatusDialog = ref(false)
const newAssigneeId = ref<string | null>(null)
const newStatus = ref('')

// Admin users for assignment
const adminUsers = ref<any[]>([])
const adminUsersLoading = ref(false)

// Additional reactive variables for fallback message composer
const messageType = ref('public')
const messageBody = ref('')
const selectedFiles = ref<File[]>([])

// File input ref
const fileInput = ref<HTMLInputElement | null>(null)

// File handling methods
const handleFileSelect = (event: Event) => {
  try {
    const target = event.target as HTMLInputElement
    const files = Array.from(target.files || [])
    selectedFiles.value = [...selectedFiles.value, ...files]
  } catch (error) {
    console.error('Error handling file selection:', error)
  }
}

const removeFile = (index: number) => {
  try {
    selectedFiles.value.splice(index, 1)
  } catch (error) {
    console.error('Error removing file:', error)
  }
}

const formatFileSize = (bytes: number): string => {
  try {
    if (bytes === 0) return '0 Bytes'
    const k = 1024
    const sizes = ['Bytes', 'KB', 'MB', 'GB']
    const i = Math.floor(Math.log(bytes) / Math.log(k))
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
  } catch (error) {
    console.error('Error formatting file size:', error)
    return 'Unknown size'
  }
}

// Safe component loading
const loadComponents = async () => {
  try {
    console.log('🔧 [Ticket Detail] Loading components...')
    
    // Try to load custom components with better error handling
    const componentLoaders = [
      {
        name: 'TicketStatusBadge',
        path: '@/components/admin/tickets/TicketStatusBadge.vue',
        ref: () => TicketStatusBadge
      },
      {
        name: 'TicketPriorityBadge',
        path: '@/components/admin/tickets/TicketPriorityBadge.vue',
        ref: () => TicketPriorityBadge
      },
      {
        name: 'TicketEntityLink',
        path: '@/components/admin/tickets/TicketEntityLink.vue',
        ref: () => TicketEntityLink
      },
      {
        name: 'MessageComposer',
        path: '@/components/admin/tickets/MessageComposer.vue',
        ref: () => MessageComposer
      },
      {
        name: 'ConfirmActionDialog',
        path: '@/components/common/ConfirmActionDialog.vue',
        ref: () => ConfirmActionDialog
      }
    ]

    for (const loader of componentLoaders) {
      try {
        const module = await import(loader.path)
        if (loader.name === 'TicketStatusBadge') TicketStatusBadge = module.default
        else if (loader.name === 'TicketPriorityBadge') TicketPriorityBadge = module.default
        else if (loader.name === 'TicketEntityLink') TicketEntityLink = module.default
        else if (loader.name === 'MessageComposer') MessageComposer = module.default
        else if (loader.name === 'ConfirmActionDialog') ConfirmActionDialog = module.default
      } catch (error) {
        console.warn(`${loader.name} not available, using fallback`)
      }
    }

    componentsLoaded.value = true
    console.log('✅ [Ticket Detail] Components loaded')
    
  } catch (error) {
    console.error('🚫 [Ticket Detail] Component loading failed:', error)
    componentsLoaded.value = true // Continue anyway
  }
}

// Safe initialization
const initializeComponents = async () => {
  try {
    console.log('🎫 [Ticket Detail] Initializing components...')
    
    // Load components first
    await loadComponents()
    
    // Initialize store
    ticketsStore = useTicketsStore()
    
    // Get store refs safely
    if (ticketsStore) {
      storeRefs = storeToRefs(ticketsStore)
      
      // Bind refs safely with null checks
      if (storeRefs.currentTicket) ticket.value = storeRefs.currentTicket.value || null
      if (storeRefs.messages) messages.value = storeRefs.messages.value || []
      if (storeRefs.loading) loading.value = storeRefs.loading.value || false
      if (storeRefs.messagesLoading) messagesLoading.value = storeRefs.messagesLoading.value || false
    }
    
    // Initialize confirm composable with better fallback
    try {
      const { useQuickConfirm } = await import('@/composables/useConfirmAction')
      const confirmComposable = useQuickConfirm()
      confirm = confirmComposable.confirm
      presets = confirmComposable.presets
    } catch (error) {
      console.warn('🚫 [Ticket Detail] Confirm composable failed, using fallback')
      // Better fallback confirm function
      confirm = async (options: any) => {
        try {
          const message = options?.text || options?.message || 'Are you sure?'
          return window.confirm(message)
        } catch (err) {
          console.error('Confirm dialog error:', err)
          return false
        }
      }
      presets = {
        delete: (type: string, name: string) => ({
          text: `Delete ${type}: ${name}?`,
          message: `Are you sure you want to delete this ${type}? This action cannot be undone.`,
          title: `Delete ${type}`,
          type: 'delete'
        })
      }
    }
    
    isInitialized.value = true
    console.log('✅ [Ticket Detail] Components initialized successfully')
    
  } catch (error) {
    console.error('🚫 [Ticket Detail] Component initialization failed:', error)
    hasError.value = true
    errorMessage.value = 'Failed to initialize components'
  }
}

// Computed properties
const ticketId = computed(() => {
  try {
    const id = route.params?.id
    return typeof id === 'string' ? id : String(id || '')
  } catch (error) {
    console.error('Error getting ticket ID:', error)
    return ''
  }
})

const safeTicket = computed(() => ticket.value)
const safeMessages = computed(() => messages.value || [])
const safeLoading = computed(() => loading.value || false)
const safeMessagesLoading = computed(() => messagesLoading.value || false)

const canEdit = computed(() => {
  try {
    return safeTicket.value?.is_open || false
  } catch (error) {
    return false
  }
})

const hasMessages = computed(() => {
  try {
    return safeMessages.value.length > 0
  } catch (error) {
    return false
  }
})

// Safe translation function
const getTranslation = (key: string, fallback?: string) => {
  try {
    return t(key)
  } catch (error) {
    return fallback || key
  }
}

// Status options
const statusOptions = computed(() => [
  { title: getTranslation('ticket_status_open', 'Open'), value: 'open' },
  { title: getTranslation('ticket_status_pending', 'Pending'), value: 'pending' },
  { title: getTranslation('ticket_status_waiting_user', 'Waiting User'), value: 'waiting_user' },
  { title: getTranslation('ticket_status_waiting_third_party', 'Waiting Third Party'), value: 'waiting_third_party' },
  { title: getTranslation('ticket_status_resolved', 'Resolved'), value: 'resolved' },
  { title: getTranslation('ticket_status_closed', 'Closed'), value: 'closed' },
])

// Methods with better error handling
const fetchData = async () => {
  if (isDestroyed.value || !isInitialized.value || !ticketId.value || !ticketsStore) return
  
  try {
    loading.value = true
    hasError.value = false
    errorMessage.value = ''

    if (ticketsStore.fetchTicket && ticketsStore.fetchMessages) {
      await Promise.allSettled([
        ticketsStore.fetchTicket(ticketId.value),
        ticketsStore.fetchMessages(ticketId.value),
      ])
      
      // Update local refs from store with safety checks
      if (storeRefs && !isDestroyed.value) {
        if (storeRefs.currentTicket) ticket.value = storeRefs.currentTicket.value || null
        if (storeRefs.messages) messages.value = storeRefs.messages.value || []
        if (storeRefs.loading) loading.value = storeRefs.loading.value || false
        if (storeRefs.messagesLoading) messagesLoading.value = storeRefs.messagesLoading.value || false
      }
    }
  } catch (error) {
    console.error('Error fetching ticket data:', error)
    hasError.value = true
    errorMessage.value = 'Failed to load ticket data'
  } finally {
    if (!isDestroyed.value) {
      loading.value = false
    }
  }
}

const goBack = () => {
  if (isDestroyed.value) return
  try {
    router.push('/admin/support/tickets')
  } catch (error) {
    console.error('Error navigating back:', error)
    try {
      window.location.href = '/admin/support/tickets'
    } catch (navError) {
      console.error('Failed to navigate:', navError)
    }
  }
}

const handleAssign = async () => {
  if (isDestroyed.value || !isInitialized.value || !safeTicket.value || !ticketsStore) return

  try {
    if (ticketsStore.assignTicket) {
      await ticketsStore.assignTicket(safeTicket.value.id, newAssigneeId.value)

      // Update local data with safety checks
      if (storeRefs && !isDestroyed.value && storeRefs.currentTicket) {
        ticket.value = storeRefs.currentTicket.value || ticket.value
      }
    }

    showAssignDialog.value = false
    newAssigneeId.value = null
  } catch (error) {
    console.error('Error assigning ticket:', error)
    // Show user-friendly error message
    alert('Failed to assign ticket. Please try again.')
  }
}

// Load admin users function
const loadAdminUsers = async () => {
  console.log('🔍 [Ticket Detail] Loading admin users...')
  adminUsersLoading.value = true
  try {
    const params = new URLSearchParams({
      per_page: '50',
      role: 'admin'
    })

    const response = await $api(`/admin/users?${params.toString()}`)
    console.log('🔍 [Ticket Detail] Admin users response:', response)

    if (response && response.users && Array.isArray(response.users)) {
      // Filter only users with admin role (double check)
      const adminOnlyUsers = response.users.filter((user: any) => {
        return user.roles && user.roles.some((role: any) => role.name === 'admin')
      })

      adminUsers.value = adminOnlyUsers.map((user: any) => ({
        ...user,
        nom_complet: user.nom_complet || user.name || 'Unknown User',
        email: user.email || ''
      }))

      console.log('✅ [Ticket Detail] Admin users loaded:', adminUsers.value.length)
    } else {
      adminUsers.value = []
    }
  } catch (error) {
    console.error('❌ [Ticket Detail] Failed to load admin users:', error)
    adminUsers.value = []
  } finally {
    adminUsersLoading.value = false
  }
}

// Watch for assign dialog opening to trigger data loading
watch(showAssignDialog, (newValue) => {
  console.log('🔍 [Ticket Detail] Assign dialog state changed:', newValue)
  if (newValue) {
    console.log('🔍 [Ticket Detail] Assign dialog opened, loading admin users...')
    loadAdminUsers()
  }
})

const handleStatusChange = async () => {
  if (isDestroyed.value || !isInitialized.value || !safeTicket.value || !newStatus.value || !ticketsStore) return
  
  try {
    if (ticketsStore.changeStatus) {
      await ticketsStore.changeStatus(safeTicket.value.id, newStatus.value)
      
      // Update local data with safety checks
      if (storeRefs && !isDestroyed.value && storeRefs.currentTicket) {
        ticket.value = storeRefs.currentTicket.value || ticket.value
      }
    }
    
    showStatusDialog.value = false
    newStatus.value = ''
  } catch (error) {
    console.error('Error changing status:', error)
    // Show user-friendly error message
    alert('Failed to change status. Please try again.')
  }
}

const handleDelete = async () => {
  if (isDestroyed.value || !isInitialized.value || !safeTicket.value || !ticketsStore) return
  
  try {
    // Create a safe confirm options object with null checks
    let confirmOptions
    if (presets && presets.delete) {
      confirmOptions = presets.delete('ticket', safeTicket.value.subject || 'this ticket')
    } else {
      confirmOptions = {
        text: `Delete ticket: ${safeTicket.value.subject || 'this ticket'}?`,
        message: `Are you sure you want to delete this ticket? This action cannot be undone.`,
        title: 'Delete Ticket',
        type: 'delete'
      }
    }
    
    // Safe confirm call
    let confirmed = false
    if (confirm) {
      confirmed = await confirm(confirmOptions)
    } else {
      confirmed = window.confirm(confirmOptions.message || confirmOptions.text)
    }
    
    if (confirmed && ticketsStore.deleteTicket) {
      const success = await ticketsStore.deleteTicket(safeTicket.value.id)
      if (success && !isDestroyed.value) {
        goBack()
      }
    }
  } catch (error) {
    console.error('Error deleting ticket:', error)
    // Show user-friendly error message
    alert('Failed to delete ticket. Please try again.')
  }
}

const handleMessageSubmit = async (messageData: any) => {
  if (isDestroyed.value || !isInitialized.value || !safeTicket.value || !ticketsStore) return
  
  try {
    if (ticketsStore.addMessage) {
      await ticketsStore.addMessage(safeTicket.value.id, messageData)
      
      // Update local data with safety checks
      if (storeRefs && !isDestroyed.value) {
        if (storeRefs.messages) messages.value = storeRefs.messages.value || messages.value
        if (storeRefs.currentTicket) ticket.value = storeRefs.currentTicket.value || ticket.value
      }
    }
  } catch (error) {
    console.error('Error submitting message:', error)
    alert('Failed to send message. Please try again.')
  }
}

// Enhanced message submit handler for fallback composer
const enhancedHandleMessageSubmit = async () => {
  if (!messageBody.value?.trim()) return
  
  const messageData = {
    type: messageType.value,
    body: messageBody.value,
    is_internal: messageType.value === 'internal',
    attachments: selectedFiles.value
  }
  
  await handleMessageSubmit(messageData)
  
  // Reset form
  messageBody.value = ''
  selectedFiles.value = []
  messageType.value = 'public'
}

const formatDate = (date: string) => {
  try {
    if (!date) return 'N/A'
    return new Date(date).toLocaleString()
  } catch (error) {
    return date || 'N/A'
  }
}

const getTimeAgo = (date: string) => {
  try {
    if (!date) return 'Unknown'
    const now = new Date()
    const messageDate = new Date(date)
    const diffInHours = Math.floor((now.getTime() - messageDate.getTime()) / (1000 * 60 * 60))
    
    if (diffInHours < 1) return getTranslation('just_now', 'Just now')
    if (diffInHours < 24) return getTranslation('hours_ago', `${diffInHours} hours ago`).replace('{count}', String(diffInHours))
    
    const diffInDays = Math.floor(diffInHours / 24)
    return getTranslation('days_ago', `${diffInDays} days ago`).replace('{count}', String(diffInDays))
  } catch (error) {
    return date || 'Unknown'
  }
}

// Safe file input click handler
const triggerFileInput = () => {
  try {
    if (fileInput.value) {
      fileInput.value.click()
    }
  } catch (error) {
    console.error('Error triggering file input:', error)
  }
}

// Watchers - with safety checks
watch(
  () => ticketId.value,
  async (newId, oldId) => {
    if (isDestroyed.value || !isInitialized.value) return
    if (newId && newId !== oldId) {
      await nextTick()
      await fetchData()
    }
  },
  { immediate: false }
)

// Lifecycle
onMounted(async () => {
  try {
    console.log('🎫 [Ticket Detail] Component mounting...')
    pageLoading.value = true
    
    // Initialize components first
    await initializeComponents()
    
    // Then fetch data if everything is ready
    if (!isDestroyed.value && ticketId.value && isInitialized.value) {
      await fetchData()
    }
    
    console.log('🎫 [Ticket Detail] Component mounted successfully')
    
  } catch (error) {
    console.error('🚫 [Ticket Detail] Error during component mount:', error)
    hasError.value = true
    errorMessage.value = 'Failed to initialize ticket detail page'
    
    // Navigate to safe route after delay
    setTimeout(() => {
      if (!isDestroyed.value) {
        goBack()
      }
    }, 3000)
  } finally {
    pageLoading.value = false
  }
})

onBeforeUnmount(() => {
  console.log('🎫 [Ticket Detail] Component unmounting...')
  isDestroyed.value = true
  showAssignDialog.value = false
  showStatusDialog.value = false
  newAssigneeId.value = null
  newStatus.value = ''
})
</script>

<template>
  <div>
    <!-- Page Loading State -->
    <div v-if="pageLoading" class="d-flex justify-center align-center" style="min-height: 400px;">
      <div class="text-center">
        <VProgressCircular indeterminate color="primary" size="64" class="mb-4" />
        <div class="text-h6 mb-2">{{ getTranslation('loading', 'Loading...') }}</div>
        <div class="text-body-2 text-medium-emphasis">
          {{ getTranslation('loading_ticket', 'Loading ticket details...') }}
        </div>
      </div>
    </div>

    <!-- Error State -->
    <VAlert
      v-else-if="hasError"
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

    <!-- Ticket Content -->
    <div v-else-if="isInitialized && safeTicket">
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
              #{{ safeTicket.id?.toString().slice(-8) || 'N/A' }}
            </h1>
            
            <!-- Status Badge - Use component if available, fallback otherwise -->
            <component 
              v-if="TicketStatusBadge" 
              :is="TicketStatusBadge" 
              :status="safeTicket.status" 
            />
            <VChip
              v-else
              :color="safeTicket.status === 'open' ? 'success' : safeTicket.status === 'closed' ? 'error' : 'warning'"
              size="small"
              variant="tonal"
            >
              {{ getTranslation(`ticket_status_${safeTicket.status}`, safeTicket.status) }}
            </VChip>
            
            <!-- Priority Badge - Use component if available, fallback otherwise -->
            <component 
              v-if="TicketPriorityBadge" 
              :is="TicketPriorityBadge" 
              :priority="safeTicket.priority" 
              show-icon 
            />
            <VChip
              v-else
              :color="safeTicket.priority === 'urgent' ? 'error' : safeTicket.priority === 'high' ? 'warning' : 'default'"
              size="small"
              variant="outlined"
            >
              <VIcon 
                :icon="safeTicket.priority === 'urgent' ? 'tabler-alert-triangle' : 'tabler-flag'" 
                size="14" 
                class="me-1" 
              />
              {{ getTranslation(`ticket_priority_${safeTicket.priority}`, safeTicket.priority) }}
            </VChip>
          </div>
          <h2 class="text-h6 mb-1">{{ safeTicket.subject || 'No Subject' }}</h2>
          <p class="text-body-2 text-medium-emphasis mb-0">
            {{ getTranslation('created_by', 'Created by') }} {{ safeTicket.requester?.nom_complet || 'Unknown' }} • 
            {{ formatDate(safeTicket.created_at) }}
          </p>
        </div>

        <!-- Header Actions -->
        <div class="d-flex gap-2">
          <VBtn
            variant="outlined"
            :disabled="!isInitialized"
            @click="showAssignDialog = true"
          >
            <VIcon icon="tabler-user-check" class="me-2" />
            {{ getTranslation('assign', 'Assign') }}
          </VBtn>

          <VBtn
            variant="outlined"
            :disabled="!isInitialized"
            @click="showStatusDialog = true"
          >
            <VIcon icon="tabler-edit" class="me-2" />
            {{ getTranslation('change_status', 'Change Status') }}
          </VBtn>

          <VMenu>
            <template #activator="{ props }">
              <VBtn
                icon="tabler-dots-vertical"
                variant="outlined"
                :disabled="!isInitialized"
                v-bind="props"
              />
            </template>

            <VList>
              <VListItem @click="handleDelete" class="text-error">
                <template #prepend>
                  <VIcon icon="tabler-trash" />
                </template>
                <VListItemTitle>{{ getTranslation('delete_ticket', 'Delete Ticket') }}</VListItemTitle>
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
              {{ getTranslation('messages', 'Messages') }}
              <VChip v-if="hasMessages" size="small" class="ms-2">
                {{ safeMessages.length }}
              </VChip>
            </VCardTitle>

            <VCardText>
              <div v-if="safeMessagesLoading" class="text-center py-8">
                <VProgressCircular indeterminate color="primary" />
              </div>

              <div v-else-if="hasMessages" class="messages-timeline">
                <div
                  v-for="(message, index) in safeMessages"
                  :key="message.id || index"
                  class="message-item"
                  :class="{ 'mb-6': index < safeMessages.length - 1 }"
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
                            {{ message.sender?.nom_complet || 'Unknown' }}
                          </span>
                          <VChip
                            :color="message.is_internal ? 'warning' : 'primary'"
                            size="x-small"
                            variant="tonal"
                          >
                            {{ message.is_internal ? getTranslation('internal', 'Internal') : getTranslation('public', 'Public') }}
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
                        :class="{ 'warning': message.is_internal }"
                      >
                        <VCardText>
                          <div v-html="message.body" />

                          <!-- Attachments -->
                          <div v-if="message.attachments && message.attachments.length > 0" class="mt-3">
                            <div class="text-caption text-medium-emphasis mb-2">
                              {{ getTranslation('attachments', 'Attachments') }}:
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                              <VChip
                                v-for="attachment in message.attachments"
                                :key="attachment.id || attachment.name"
                                size="small"
                                variant="outlined"
                                :href="attachment.url"
                                target="_blank"
                                class="attachment-chip"
                              >
                                <VIcon
                                  :icon="attachment.is_image ? 'tabler-photo' : attachment.is_pdf ? 'tabler-file-type-pdf' : 'tabler-file'"
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
                <h3 class="text-h6 mb-2">{{ getTranslation('no_messages', 'No Messages') }}</h3>
                <p class="text-body-2 text-medium-emphasis">
                  {{ getTranslation('no_messages_description', 'No messages have been posted yet.') }}
                </p>
              </div>
            </VCardText>
          </VCard>

          <!-- Message Composer - Use component if available, fallback otherwise -->
          <component 
            v-if="MessageComposer" 
            :is="MessageComposer"
            :loading="safeMessagesLoading"
            :disabled="!canEdit"
            @submit="handleMessageSubmit"
          />
          <VCard v-else>
            <VCardTitle>{{ getTranslation('add_message', 'Add Message') }}</VCardTitle>
            <VCardText>
              <VForm @submit.prevent="enhancedHandleMessageSubmit">
                <VSelect
                  v-model="messageType"
                  :items="[
                    { title: getTranslation('public', 'Public'), value: 'public' },
                    { title: getTranslation('internal', 'Internal'), value: 'internal' }
                  ]"
                  :label="getTranslation('message_type', 'Message Type')"
                  variant="outlined"
                  class="mb-4"
                />
                
                <VTextarea
                  v-model="messageBody"
                  :label="getTranslation('message', 'Message')"
                  variant="outlined"
                  rows="4"
                  :disabled="!canEdit || safeMessagesLoading"
                  class="mb-4"
                />

                <!-- File Upload Area -->
                <VCard variant="outlined" class="mb-4">
                  <VCardText>
                                        <div class="d-flex align-center gap-3">
                      <VIcon icon="tabler-paperclip" />
                      <div class="flex-grow-1">
                        <div class="text-body-2 font-weight-medium mb-1">
                          {{ getTranslation('attachments', 'Attachments') }}
                        </div>
                        <div class="text-caption text-medium-emphasis">
                          {{ getTranslation('drag_drop_files', 'Drag and drop files here or click to browse') }}
                        </div>
                      </div>
                      <VBtn
                        variant="outlined"
                        size="small"
                        @click="triggerFileInput"
                      >
                        <VIcon icon="tabler-upload" class="me-2" />
                        {{ getTranslation('browse', 'Browse') }}
                      </VBtn>
                    </div>
                    
                    <input
                      ref="fileInput"
                      type="file"
                      multiple
                      style="display: none"
                      @change="handleFileSelect"
                    />

                    <!-- Selected Files -->
                    <div v-if="selectedFiles.length > 0" class="mt-3">
                      <div class="text-caption text-medium-emphasis mb-2">
                        {{ getTranslation('selected_files', 'Selected Files') }}:
                      </div>
                      <div class="d-flex flex-wrap gap-2">
                        <VChip
                          v-for="(file, index) in selectedFiles"
                          :key="index"
                          size="small"
                          variant="outlined"
                          closable
                          @click:close="removeFile(index)"
                        >
                          <VIcon
                            :icon="file.type && file.type.startsWith('image/') ? 'tabler-photo' : 'tabler-file'"
                            size="14"
                            class="me-1"
                          />
                          {{ file.name }}
                          <span class="text-caption ms-1">({{ formatFileSize(file.size) }})</span>
                        </VChip>
                      </div>
                    </div>
                  </VCardText>
                </VCard>
                
                <div class="d-flex justify-end">
                  <VBtn
                    type="submit"
                    color="primary"
                    :loading="safeMessagesLoading"
                    :disabled="!canEdit || !messageBody?.trim()"
                  >
                    <VIcon icon="tabler-send" class="me-2" />
                    {{ getTranslation('send_message', 'Send Message') }}
                  </VBtn>
                </div>
              </VForm>
            </VCardText>
          </VCard>
        </VCol>

        <!-- Sidebar -->
        <VCol cols="12" lg="4">
          <!-- Ticket Info -->
          <VCard class="mb-6">
            <VCardTitle>{{ getTranslation('ticket_information', 'Ticket Information') }}</VCardTitle>
            <VCardText>
              <div class="d-flex flex-column gap-4">
                <!-- Status -->
                <div>
                  <div class="text-caption text-medium-emphasis mb-1">{{ getTranslation('status', 'Status') }}</div>
                  <component 
                    v-if="TicketStatusBadge" 
                    :is="TicketStatusBadge" 
                    :status="safeTicket.status" 
                  />
                  <VChip
                    v-else
                    :color="safeTicket.status === 'open' ? 'success' : safeTicket.status === 'closed' ? 'error' : 'warning'"
                    size="small"
                    variant="tonal"
                  >
                    {{ getTranslation(`ticket_status_${safeTicket.status}`, safeTicket.status) }}
                  </VChip>
                </div>

                <!-- Priority -->
                <div>
                  <div class="text-caption text-medium-emphasis mb-1">{{ getTranslation('priority', 'Priority') }}</div>
                  <component 
                    v-if="TicketPriorityBadge" 
                    :is="TicketPriorityBadge" 
                    :priority="safeTicket.priority" 
                    show-icon 
                  />
                  <VChip
                    v-else
                    :color="safeTicket.priority === 'urgent' ? 'error' : safeTicket.priority === 'high' ? 'warning' : 'default'"
                    size="small"
                    variant="outlined"
                  >
                    <VIcon 
                      :icon="safeTicket.priority === 'urgent' ? 'tabler-alert-triangle' : 'tabler-flag'" 
                      size="14" 
                      class="me-1" 
                    />
                    {{ getTranslation(`ticket_priority_${safeTicket.priority}`, safeTicket.priority) }}
                  </VChip>
                </div>

                <!-- Category -->
                <div v-if="safeTicket.category">
                  <div class="text-caption text-medium-emphasis mb-1">{{ getTranslation('category', 'Category') }}</div>
                  <VChip size="small" variant="tonal">
                    {{ getTranslation(`ticket_category_${safeTicket.category}`, safeTicket.category) }}
                  </VChip>
                </div>

                <!-- Created -->
                <div>
                  <div class="text-caption text-medium-emphasis mb-1">{{ getTranslation('created', 'Created') }}</div>
                  <div class="text-body-2">{{ formatDate(safeTicket.created_at) }}</div>
                </div>

                <!-- Last Activity -->
                <div v-if="safeTicket.last_activity_at">
                  <div class="text-caption text-medium-emphasis mb-1">{{ getTranslation('last_activity', 'Last Activity') }}</div>
                  <div class="text-body-2">{{ formatDate(safeTicket.last_activity_at) }}</div>
                </div>

                <!-- First Response -->
                <div v-if="safeTicket.first_response_at">
                  <div class="text-caption text-medium-emphasis mb-1">{{ getTranslation('first_response', 'First Response') }}</div>
                  <div class="text-body-2">{{ formatDate(safeTicket.first_response_at) }}</div>
                </div>

                <!-- Resolution -->
                <div v-if="safeTicket.resolved_at">
                  <div class="text-caption text-medium-emphasis mb-1">{{ getTranslation('resolved', 'Resolved') }}</div>
                  <div class="text-body-2">{{ formatDate(safeTicket.resolved_at) }}</div>
                </div>

                <!-- Response Time SLA -->
                <div v-if="safeTicket.sla_response_time">
                  <div class="text-caption text-medium-emphasis mb-1">{{ getTranslation('response_time_sla', 'Response Time SLA') }}</div>
                  <VChip 
                    :color="safeTicket.sla_response_breached ? 'error' : 'success'" 
                    size="small" 
                    variant="tonal"
                  >
                    <VIcon 
                      :icon="safeTicket.sla_response_breached ? 'tabler-alert-triangle' : 'tabler-check'" 
                      size="14" 
                      class="me-1" 
                    />
                    {{ safeTicket.sla_response_time }}
                  </VChip>
                </div>

                <!-- Resolution Time SLA -->
                <div v-if="safeTicket.sla_resolution_time">
                  <div class="text-caption text-medium-emphasis mb-1">{{ getTranslation('resolution_time_sla', 'Resolution Time SLA') }}</div>
                  <VChip 
                    :color="safeTicket.sla_resolution_breached ? 'error' : 'success'" 
                    size="small" 
                    variant="tonal"
                  >
                    <VIcon 
                      :icon="safeTicket.sla_resolution_breached ? 'tabler-alert-triangle' : 'tabler-check'" 
                      size="14" 
                      class="me-1" 
                    />
                    {{ safeTicket.sla_resolution_time }}
                  </VChip>
                </div>
              </div>
            </VCardText>
          </VCard>

          <!-- People -->
          <VCard class="mb-6">
            <VCardTitle>{{ getTranslation('people', 'People') }}</VCardTitle>
            <VCardText>
              <div class="d-flex flex-column gap-4">
                <!-- Requester -->
                <div>
                  <div class="text-caption text-medium-emphasis mb-2">{{ getTranslation('requester', 'Requester') }}</div>
                  <div v-if="safeTicket.requester" class="d-flex align-center">
                    <VAvatar size="32" class="me-3">
                      <VImg
                        v-if="safeTicket.requester.photo_profil"
                        :src="safeTicket.requester.photo_profil"
                        :alt="safeTicket.requester.nom_complet"
                      />
                      <VIcon v-else icon="tabler-user" size="18" />
                    </VAvatar>
                    <div>
                      <div class="text-body-2 font-weight-medium">
                        {{ safeTicket.requester.nom_complet }}
                      </div>
                      <div class="text-caption text-medium-emphasis">
                        {{ safeTicket.requester.email }}
                      </div>
                      <div v-if="safeTicket.requester.phone" class="text-caption text-medium-emphasis">
                        {{ safeTicket.requester.phone }}
                      </div>
                    </div>
                  </div>
                  <div v-else class="text-body-2 text-medium-emphasis">
                    {{ getTranslation('no_requester', 'No requester information') }}
                  </div>
                </div>

                <!-- Assignee -->
                <div>
                  <div class="text-caption text-medium-emphasis mb-2">{{ getTranslation('assignee', 'Assignee') }}</div>
                  <div v-if="safeTicket.assignee" class="d-flex align-center">
                    <VAvatar size="32" class="me-3">
                      <VImg
                        v-if="safeTicket.assignee.photo_profil"
                        :src="safeTicket.assignee.photo_profil"
                        :alt="safeTicket.assignee.nom_complet"
                      />
                      <VIcon v-else icon="tabler-user" size="18" />
                    </VAvatar>
                    <div>
                      <div class="text-body-2 font-weight-medium">
                        {{ safeTicket.assignee.nom_complet }}
                      </div>
                      <div class="text-caption text-medium-emphasis">
                        {{ safeTicket.assignee.email }}
                      </div>
                      <div v-if="safeTicket.assignee.department" class="text-caption text-medium-emphasis">
                        {{ safeTicket.assignee.department }}
                      </div>
                    </div>
                  </div>
                  <VChip v-else size="small" variant="outlined" color="warning">
                    {{ getTranslation('unassigned', 'Unassigned') }}
                  </VChip>
                </div>

                <!-- Watchers -->
                <div v-if="safeTicket.watchers && safeTicket.watchers.length > 0">
                  <div class="text-caption text-medium-emphasis mb-2">{{ getTranslation('watchers', 'Watchers') }}</div>
                  <div class="d-flex flex-wrap gap-1">
                    <VTooltip
                      v-for="watcher in safeTicket.watchers"
                      :key="watcher.id || watcher.nom_complet"
                      :text="watcher.nom_complet"
                    >
                      <template #activator="{ props }">
                        <VAvatar size="24" v-bind="props">
                          <VImg
                            v-if="watcher.photo_profil"
                            :src="watcher.photo_profil"
                            :alt="watcher.nom_complet"
                          />
                          <VIcon v-else icon="tabler-user" size="14" />
                        </VAvatar>
                      </template>
                    </VTooltip>
                  </div>
                </div>
              </div>
            </VCardText>
          </VCard>

          <!-- Related Items -->
          <VCard v-if="safeTicket.relations && safeTicket.relations.length > 0" class="mb-6">
            <VCardTitle>{{ getTranslation('related_items', 'Related Items') }}</VCardTitle>
            <VCardText>
              <div class="d-flex flex-column gap-2">
                <!-- Use component if available, fallback otherwise -->
                <template v-if="TicketEntityLink">
                  <component
                    v-for="relation in safeTicket.relations"
                    :key="relation.id || relation.related_type"
                    :is="TicketEntityLink"
                    :relation="relation"
                  />
                </template>
                <template v-else>
                  <VChip
                    v-for="relation in safeTicket.relations"
                    :key="relation.id || relation.related_type"
                    size="small"
                    variant="outlined"
                    class="justify-start"
                  >
                    <VIcon 
                      :icon="relation.related_type === 'user' ? 'tabler-user' : 'tabler-link'" 
                      size="14" 
                      class="me-2" 
                    />
                    {{ relation.related_display_name || relation.related_type }}
                  </VChip>
                </template>
              </div>
            </VCardText>
          </VCard>

          <!-- Tags -->
          <VCard v-if="safeTicket.tags && safeTicket.tags.length > 0" class="mb-6">
            <VCardTitle>{{ getTranslation('tags', 'Tags') }}</VCardTitle>
            <VCardText>
              <div class="d-flex flex-wrap gap-2">
                <VChip
                  v-for="tag in safeTicket.tags"
                  :key="tag.id || tag.name"
                  size="small"
                  variant="tonal"
                  :color="tag.color || 'primary'"
                >
                  {{ tag.name }}
                </VChip>
              </div>
            </VCardText>
          </VCard>

          <!-- Custom Fields -->
          <VCard v-if="safeTicket.custom_fields && Object.keys(safeTicket.custom_fields).length > 0">
            <VCardTitle>{{ getTranslation('custom_fields', 'Custom Fields') }}</VCardTitle>
            <VCardText>
              <div class="d-flex flex-column gap-3">
                <div
                  v-for="(value, key) in safeTicket.custom_fields"
                  :key="key"
                >
                  <div class="text-caption text-medium-emphasis mb-1">{{ key }}</div>
                  <div class="text-body-2">{{ value }}</div>
                </div>
              </div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>
    </div>

    <!-- Not Found -->
    <div v-else-if="isInitialized" class="text-center py-8">
      <VIcon icon="tabler-ticket-off" size="64" class="mb-4" color="disabled" />
      <h3 class="text-h6 mb-2">{{ getTranslation('ticket_not_found', 'Ticket Not Found') }}</h3>
      <p class="text-body-2 text-medium-emphasis mb-4">
        {{ getTranslation('ticket_not_found_description', 'The requested ticket could not be found.') }}
      </p>
      <VBtn color="primary" @click="goBack">
        {{ getTranslation('back_to_tickets', 'Back to Tickets') }}
      </VBtn>
    </div>

    <!-- Assign Dialog -->
    <VDialog 
      v-model="showAssignDialog" 
      max-width="400"
      :persistent="false"
      @click:outside="showAssignDialog = false"
    >
      <VCard>
        <VCardTitle>{{ getTranslation('assign_ticket', 'Assign Ticket') }}</VCardTitle>
        <VCardText>
          <!-- Simple autocomplete for admin users -->
          <VAutocomplete
            v-model="newAssigneeId"
            :items="adminUsers"
            :loading="adminUsersLoading"
            :label="getTranslation('select_assignee', 'Select Assignee')"
            variant="outlined"
            clearable
            item-title="nom_complet"
            item-value="id"
            placeholder="Search admin users..."
          >
            <template #item="{ props: itemProps, item }">
              <VListItem v-bind="itemProps">
                <template #prepend>
                  <VAvatar size="24">
                    <VIcon icon="tabler-user" size="14" />
                  </VAvatar>
                </template>
                <VListItemTitle>{{ item.raw?.nom_complet || 'Unknown User' }}</VListItemTitle>
                <VListItemSubtitle>{{ item.raw?.email || '' }}</VListItemSubtitle>
              </VListItem>
            </template>
          </VAutocomplete>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn @click="showAssignDialog = false">{{ getTranslation('cancel', 'Cancel') }}</VBtn>
          <VBtn 
            color="primary" 
            @click="handleAssign"
            :disabled="!newAssigneeId"
          >
            {{ getTranslation('assign', 'Assign') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Status Dialog -->
    <VDialog 
      v-model="showStatusDialog" 
      max-width="400"
      :persistent="false"
      @click:outside="showStatusDialog = false"
    >
      <VCard>
        <VCardTitle>{{ getTranslation('change_status', 'Change Status') }}</VCardTitle>
        <VCardText>
          <VSelect
            v-model="newStatus"
            :items="statusOptions"
            :label="getTranslation('select_status', 'Select Status')"
            variant="outlined"
            item-title="title"
            item-value="value"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn @click="showStatusDialog = false">{{ getTranslation('cancel', 'Cancel') }}</VBtn>
          <VBtn 
            color="primary" 
            @click="handleStatusChange"
            :disabled="!newStatus"
          >
            {{ getTranslation('change_status', 'Change Status') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Confirm Dialog - Use component if available -->
    <component v-if="ConfirmActionDialog" :is="ConfirmActionDialog" />
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

.attachment-chip {
  cursor: pointer;
  transition: all 0.2s ease;
}

.attachment-chip:hover {
  transform: translateY(-1px);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

/* File upload area styling */
.v-card--variant-outlined {
  border: 2px dashed rgb(var(--v-theme-surface-variant));
  transition: border-color 0.2s ease;
}

.v-card--variant-outlined:hover {
  border-color: rgb(var(--v-theme-primary));
}

/* Message timeline styling */
.messages-timeline::before {
  content: '';
  position: absolute;
  left: 20px;
  top: 0;
  bottom: 0;
  width: 2px;
  background: rgb(var(--v-theme-surface-variant));
}

.message-item::before {
  content: '';
  position: absolute;
  left: 16px;
  top: 24px;
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: rgb(var(--v-theme-primary));
  border: 2px solid rgb(var(--v-theme-surface));
  z-index: 1;
}

/* Responsive adjustments */
@media (max-width: 960px) {
  .d-flex.gap-2 {
    flex-direction: column;
    gap: 8px;
  }
  
  .message-item .d-flex {
    flex-direction: column;
  }
  
  .message-item .v-avatar {
    align-self: flex-start;
    margin-bottom: 8px;
  }
}

/* Dark mode adjustments */
.v-theme--dark .messages-timeline::before {
  background: rgb(var(--v-theme-surface-bright));
}

.v-theme--dark .message-item::before {
  background: rgb(var(--v-theme-primary));
  border-color: rgb(var(--v-theme-surface));
}

/* Animation for new messages */
@keyframes slideInMessage {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.message-item {
  animation: slideInMessage 0.3s ease-out;
}

/* Loading states */
.v-progress-circular {
  margin: 16px;
}

/* Status and priority badges */
.v-chip {
  font-weight: 500;
}

.v-chip--variant-tonal {
  font-weight: 600;
}
</style>

