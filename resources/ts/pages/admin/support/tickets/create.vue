<script setup lang="ts">
import { ref, computed, onMounted, watch, nextTick, onBeforeUnmount } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'
import { useTicketsStore } from '@/stores/admin/tickets'
import { useUsersStore } from '@/stores/admin/users'
import { useSafeNavigation } from '@/composables/useSafeNavigation'
import TicketForm from '@/components/admin/tickets/TicketForm.vue'

// Define page meta
definePage({
  meta: {
    requiresAuth: true,
    requiresRole: 'admin',
  },
})

// Initialize basic composables
const router = useRouter()
const { t } = useI18n()
const { safePush } = useSafeNavigation()

// Initialize stores with enhanced error handling
let ticketsStore: ReturnType<typeof useTicketsStore>
let usersStore: ReturnType<typeof useUsersStore>

try {
  console.log('🎫 [Create Ticket] Initializing tickets store...')
  ticketsStore = useTicketsStore()
  console.log('✅ [Create Ticket] Tickets store initialized successfully')
} catch (error) {
  console.error('🚫 [Create Ticket] Error initializing tickets store:', error)
  // Create fallback store
  ticketsStore = {
    loading: ref(false),
    createTicket: () => Promise.resolve({ success: false }),
    fetchCategories: () => Promise.resolve(),
    categories: ref([]),
  } as any
}

try {
  console.log('🎫 [Create Ticket] Initializing users store...')
  usersStore = useUsersStore()
  console.log('✅ [Create Ticket] Users store initialized successfully')
} catch (error) {
  console.error('🚫 [Create Ticket] Error initializing users store:', error)
  // Create fallback store
  usersStore = {
    loading: ref(false),
    users: ref([]),
    fetchUsers: () => Promise.resolve(),
    searchUsers: () => Promise.resolve([]),
  } as any
}

// Initialize confirm composable with better error handling
let confirm: any
let presets: any

try {
  console.log('🎫 [Create Ticket] Initializing confirm composable...')
  
  // Try to import the composable dynamically to catch import errors
  const { useQuickConfirm } = await import('@/composables/useConfirmAction')
  const confirmComposable = useQuickConfirm()
  
  if (confirmComposable && typeof confirmComposable === 'object') {
    confirm = confirmComposable.confirm || (() => Promise.resolve(true))
    presets = confirmComposable.presets || { 
      cancel: () => ({ title: 'Confirm Cancel', text: 'Are you sure you want to cancel? Unsaved changes will be lost.' })
    }
    console.log('✅ [Create Ticket] Confirm composable initialized successfully')
  } else {
    throw new Error('useQuickConfirm returned invalid object')
  }
} catch (error) {
  console.error('🚫 [Create Ticket] Error initializing confirm composable:', error)
  
  // Create safe fallback implementations
  confirm = async (options?: any) => {
    console.log('🔄 [Create Ticket] Using fallback confirm dialog')
    const message = options?.text || options?.title || 'Are you sure?'
    return window.confirm(message)
  }
  
  presets = {
    cancel: () => ({
      title: 'Cancel Creation',
      text: 'Are you sure you want to cancel? Unsaved changes will be lost.',
      type: 'warning',
      confirmText: 'Yes, Cancel',
      cancelText: 'Continue Editing',
      icon: 'tabler-x',
      color: 'warning',
    }),
    save: () => ({
      title: 'Save Ticket',
      text: 'Are you sure you want to create this ticket?',
      type: 'success',
      confirmText: 'Create Ticket',
      cancelText: 'Keep Editing',
      icon: 'tabler-check',
      color: 'success',
    })
  }
}

// Component cleanup flag
const isDestroyed = ref(false)

// Store refs with enhanced error handling
let loading: any, categories: any, users: any, usersLoading: any

try {
  console.log('🎫 [Create Ticket] Accessing store refs...')
  const ticketsStoreRefs = storeToRefs(ticketsStore)
  const usersStoreRefs = storeToRefs(usersStore)
  
  loading = ticketsStoreRefs.loading
  categories = ticketsStoreRefs.categories || ref([])
  users = usersStoreRefs.users
  usersLoading = usersStoreRefs.loading
  
  console.log('✅ [Create Ticket] Store refs accessed successfully')
} catch (error) {
  console.error('🚫 [Create Ticket] Error accessing store refs:', error)
  // Fallback refs
  loading = ref(false)
  categories = ref([])
  users = ref([])
  usersLoading = ref(false)
}

// State
const isLoading = ref(false)
const hasError = ref(false)
const errorMessage = ref('')
const isSubmitting = ref(false)

// Form data
const ticketForm = ref({
  title: '',
  description: '',
  priority: 'medium',
  category_id: null,
  user_id: null,
  assignee_id: null,
  status: 'open',
  tags: [],
  attachments: []
})

// Safe computed properties
const safeCategories = computed(() => {
  try {
    return categories?.value || []
  } catch (error) {
    console.error('🚫 [Create Ticket] Error accessing categories:', error)
    return []
  }
})

const safeUsers = computed(() => {
  try {
    return users?.value || []
  } catch (error) {
    console.error('🚫 [Create Ticket] Error accessing users:', error)
    return []
  }
})

const safeLoading = computed(() => {
  try {
    return loading?.value || isLoading.value || false
  } catch (error) {
    console.error('🚫 [Create Ticket] Error accessing loading state:', error)
    return false
  }
})

const canSubmit = computed(() => {
  try {
    return !isSubmitting.value && 
           ticketForm.value.title?.trim() && 
           ticketForm.value.description?.trim() &&
           ticketForm.value.user_id
  } catch (error) {
    console.error('🚫 [Create Ticket] Error in canSubmit computed:', error)
    return false
  }
})

// Priority options
const priorityOptions = [
  { value: 'low', label: 'Low', color: 'success' },
  { value: 'medium', label: 'Medium', color: 'warning' },
  { value: 'high', label: 'High', color: 'error' },
  { value: 'urgent', label: 'Urgent', color: 'error' }
]

// Status options
const statusOptions = [
  { value: 'open', label: 'Open', color: 'info' },
  { value: 'in_progress', label: 'In Progress', color: 'warning' },
  { value: 'pending', label: 'Pending', color: 'secondary' },
  { value: 'resolved', label: 'Resolved', color: 'success' },
  { value: 'closed', label: 'Closed', color: 'default' }
]

// Safe translation function
const getTranslation = (key: string, fallback?: string) => {
  try {
    return t(key)
  } catch (error) {
    console.error(`🚫 [Create Ticket] Translation error for key: ${key}`, error)
    return fallback || key
  }
}

// Methods
const handleSubmit = async () => {
  if (!canSubmit.value) {
    console.warn('🚫 [Create Ticket] Cannot submit - form validation failed')
    return
  }

  try {
    isSubmitting.value = true
    hasError.value = false
    errorMessage.value = ''

    console.log('🎫 [Create Ticket] Submitting ticket form:', ticketForm.value)

    // Validate required fields
    if (!ticketForm.value.title?.trim()) {
      throw new Error('Title is required')
    }
    if (!ticketForm.value.description?.trim()) {
      throw new Error('Description is required')
    }
    if (!ticketForm.value.user_id) {
      throw new Error('User is required')
    }

    const result = await ticketsStore.createTicket(ticketForm.value)

    if (result?.success) {
      console.log('✅ [Create Ticket] Ticket created successfully')
      
      // Navigate back to tickets list with success message
      await safePush('/admin/support/tickets', {
        fallback: '/admin/dashboard',
        maxRetries: 2
      })
    } else {
      throw new Error(result?.message || 'Failed to create ticket')
    }

  } catch (error: any) {
    console.error('🚫 [Create Ticket] Error creating ticket:', error)
    hasError.value = true
    errorMessage.value = error.message || 'An error occurred while creating the ticket'
  } finally {
    isSubmitting.value = false
  }
}

const handleCancel = async () => {
  try {
    // Check if form has unsaved changes
    const hasChanges = ticketForm.value.title?.trim() || 
                      ticketForm.value.description?.trim() ||
                      ticketForm.value.user_id ||
                      ticketForm.value.assignee_id

    if (hasChanges) {
      const shouldCancel = await confirm(presets.cancel())
      if (!shouldCancel) {
        return
      }
    }

    console.log('🎫 [Create Ticket] Cancelling ticket creation')
    await safePush('/admin/support/tickets', {
      fallback: '/admin/dashboard',
      maxRetries: 2
    })

  } catch (error) {
    console.error('🚫 [Create Ticket] Error during cancel:', error)
    // Force navigation as fallback
    await safePush('/admin/support/tickets')
  }
}

const handleUserSearch = async (query: string) => {
  try {
    if (!query || query.length < 2) {
      return []
    }

    console.log('🔍 [Create Ticket] Searching users:', query)
    const results = await usersStore.searchUsers(query)
    return results || []

  } catch (error) {
    console.error('🚫 [Create Ticket] Error searching users:', error)
    return []
  }
}

// Lifecycle
onMounted(async () => {
  if (isDestroyed.value) return

  try {
    console.log('🎫 [Create Ticket] Component mounted, initializing data...')
    isLoading.value = true

    // Load initial data
    await Promise.allSettled([
      ticketsStore.fetchCategories?.() || Promise.resolve(),
      usersStore.fetchUsers?.() || Promise.resolve()
    ])

    console.log('✅ [Create Ticket] Initial data loaded successfully')

  } catch (error) {
    console.error('🚫 [Create Ticket] Error during component initialization:', error)
    hasError.value = true
    errorMessage.value = 'Failed to load initial data'
  } finally {
    isLoading.value = false
  }
})

onBeforeUnmount(() => {
  console.log('🎫 [Create Ticket] Component unmounting...')
  isDestroyed.value = true
})

// Watch for route changes to prevent navigation issues
watch(() => router.currentRoute.value, (newRoute) => {
  if (isDestroyed.value) return
  
  console.log('🎫 [Create Ticket] Route changed:', newRoute.path)
}, { immediate: false })
</script>

<template>
  <div class="create-ticket-page">
    <!-- Loading State -->
    <div v-if="safeLoading" class="d-flex justify-center align-center" style="min-height: 200px;">
      <v-progress-circular indeterminate color="primary" size="64" />
    </div>

    <!-- Error State -->
    <v-alert
      v-else-if="hasError"
      type="error"
      variant="tonal"
      closable
      @click:close="hasError = false"
      class="mb-6"
    >
      <template #title>
        {{ getTranslation('common.error', 'Error') }}
      </template>
      {{ errorMessage }}
    </v-alert>

    <!-- Main Content -->
    <div v-else>
      <!-- Page Header -->
      <div class="d-flex justify-space-between align-center mb-6">
        <div>
          <h1 class="text-h4 font-weight-bold mb-1">
            {{ getTranslation('tickets.create.title', 'Create New Ticket') }}
          </h1>
          <p class="text-body-1 text-medium-emphasis">
            {{ getTranslation('tickets.create.subtitle', 'Create a new support ticket') }}
          </p>
        </div>

        <div class="d-flex gap-3">
          <v-btn
            variant="outlined"
            color="default"
            @click="handleCancel"
            :disabled="isSubmitting"
          >
            <v-icon start>tabler-x</v-icon>
            {{ getTranslation('common.cancel', 'Cancel') }}
          </v-btn>

          <v-btn
            color="primary"
            @click="handleSubmit"
            :loading="isSubmitting"
            :disabled="!canSubmit"
          >
            <v-icon start>tabler-plus</v-icon>
            {{ getTranslation('tickets.create.submit', 'Create Ticket') }}
          </v-btn>
        </div>
      </div>

      <!-- Ticket Form -->
      <v-card>
        <v-card-text>
          <v-form @submit.prevent="handleSubmit">
            <v-row>
              <!-- Title -->
              <v-col cols="12">
                <v-text-field
                  v-model="ticketForm.title"
                  :label="getTranslation('tickets.form.title', 'Title')"
                  :placeholder="getTranslation('tickets.form.title_placeholder', 'Enter ticket title')"
                  variant="outlined"
                  required
                  :rules="[v => !!v || 'Title is required']"
                />
              </v-col>

              <!-- Description -->
              <v-col cols="12">
                <v-textarea
                  v-model="ticketForm.description"
                  :label="getTranslation('tickets.form.description', 'Description')"
                  :placeholder="getTranslation('tickets.form.description_placeholder', 'Enter ticket description')"
                  variant="outlined"
                  rows="4"
                  required
                  :rules="[v => !!v || 'Description is required']"
                />
              </v-col>

              <!-- User Selection -->
              <v-col cols="12" md="6">
                <v-select
                  v-model="ticketForm.user_id"
                  :items="safeUsers"
                  item-title="name"
                  item-value="id"
                  :label="getTranslation('tickets.form.user', 'User')"
                  variant="outlined"
                  required
                  :rules="[v => !!v || 'User is required']"
                  :loading="usersLoading"
                />
              </v-col>

              <!-- Category -->
              <v-col cols="12" md="6">
                <v-select
                  v-model="ticketForm.category_id"
                  :items="safeCategories"
                  item-title="name"
                  item-value="id"
                  :label="getTranslation('tickets.form.category', 'Category')"
                  variant="outlined"
                  clearable
                />
              </v-col>

              <!-- Priority -->
              <v-col cols="12" md="6">
                <v-select
                  v-model="ticketForm.priority"
                  :items="priorityOptions"
                  item-title="label"
                  item-value="value"
                  :label="getTranslation('tickets.form.priority', 'Priority')"
                  variant="outlined"
                />
              </v-col>

              <!-- Status -->
              <v-col cols="12" md="6">
                <v-select
                  v-model="ticketForm.status"
                  :items="statusOptions"
                  item-title="label"
                  item-value="value"
                  :label="getTranslation('tickets.form.status', 'Status')"
                  variant="outlined"
                />
              </v-col>

              <!-- Assignee -->
              <v-col cols="12">
                <v-select
                  v-model="ticketForm.assignee_id"
                  :items="safeUsers"
                  item-title="name"
                  item-value="id"
                  :label="getTranslation('tickets.form.assignee', 'Assignee')"
                  variant="outlined"
                  clearable
                  :loading="usersLoading"
                />
              </v-col>
            </v-row>
          </v-form>
        </v-card-text>
      </v-card>
    </div>
  </div>
</template>

<style scoped>
.create-ticket-page {
  padding: 24px;
}
</style>
