import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { useApi } from '@/composables/useApi'
import { useNotifications } from '@/composables/useNotifications'

// Types
export interface Ticket {
  id: string
  subject: string
  status: 'open' | 'pending' | 'waiting_user' | 'waiting_third_party' | 'resolved' | 'closed'
  priority: 'low' | 'normal' | 'high' | 'urgent'
  category: 'general' | 'orders' | 'payments' | 'commissions' | 'kyc' | 'technical' | 'other'
  first_response_at?: string
  resolved_at?: string
  last_activity_at: string
  meta?: any
  created_at: string
  updated_at: string
  deleted_at?: string
  requester?: {
    id: string
    nom_complet: string
    email: string
    photo_profil?: string
  }
  assignee?: {
    id: string
    nom_complet: string
    email: string
    photo_profil?: string
  } | null
  messages?: TicketMessage[]
  relations?: TicketRelation[]
  messages_count?: number
  is_open: boolean
  is_closed: boolean
  has_first_response: boolean
  is_resolved: boolean
  age_in_hours: number
  last_activity_hours_ago: number
  response_time_hours?: number
  resolution_time_hours?: number
}

export interface TicketMessage {
  id: string
  ticket_id: string
  type: 'public' | 'internal'
  body: string
  attachments_count: number
  created_at: string
  updated_at: string
  sender?: {
    id: string
    nom_complet: string
    email: string
    photo_profil?: string
    roles: string[]
  }
  attachments?: TicketAttachment[]
  is_public: boolean
  is_internal: boolean
  has_attachments: boolean
  time_ago: string
}

export interface TicketAttachment {
  id: string
  original_name: string
  mime_type: string
  size: number
  human_size: string
  url: string
  is_image: boolean
  is_pdf: boolean
  extension: string
  created_at: string
}

export interface TicketRelation {
  id: string
  related_type: string
  related_id: string
  related_type_name: string
  related_display_name: string
  created_at: string
}

export interface TicketFilters {
  q?: string
  status?: string[]
  priority?: string[]
  category?: string
  requester_id?: string
  assignee_id?: string
  date_from?: string
  date_to?: string
  sort?: string
  dir?: 'asc' | 'desc'
  page?: number
  per_page?: number
}

export interface TicketPagination {
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number
  to: number
}

export interface TicketStatistics {
  open: number
  resolved: number
  closed: number
  total: number
  unassigned: number
  high_priority: number
}

export interface CreateTicketData {
  subject: string
  category: string
  priority: string
  requester_id: string
  relations?: Array<{
    related_type: string
    related_id: string
  }>
  first_message?: {
    body: string
    type?: 'public' | 'internal'
    attachments?: File[]
  }
}

export interface CreateMessageData {
  type: 'public' | 'internal'
  body: string
  attachments?: File[]
}

export const useTicketsStore = defineStore('admin-tickets', () => {
  // State
  const tickets = ref<Ticket[]>([])
  const currentTicket = ref<Ticket | null>(null)
  const messages = ref<TicketMessage[]>([])
  const statistics = ref<TicketStatistics | null>(null)
  const loading = ref(false)
  const messagesLoading = ref(false)
  const error = ref<string | null>(null)
  
  const pagination = ref<TicketPagination>({
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 0,
    from: 0,
    to: 0,
  })

  const messagesPagination = ref<TicketPagination>({
    current_page: 1,
    last_page: 1,
    per_page: 20,
    total: 0,
    from: 0,
    to: 0,
  })

  const filters = ref<TicketFilters>({
    page: 1,
    per_page: 15,
    sort: 'last_activity_at',
    dir: 'desc',
  })

  // Getters
  const hasTickets = computed(() => tickets.value.length > 0)
  const totalTickets = computed(() => pagination.value.total)
  const hasMessages = computed(() => messages.value.length > 0)
  const totalMessages = computed(() => messagesPagination.value.total)

  // Actions
  const { api } = useApi()
  const { showSuccess, showError } = useNotifications()

  const setError = (message: string) => {
    error.value = message
    showError(message)
  }

  const clearError = () => {
    error.value = null
  }

  // Fetch tickets list
  const fetchTickets = async (newFilters: Partial<TicketFilters> = {}) => {
    loading.value = true
    clearError()

    try {
      // Merge filters
      const mergedFilters = { ...filters.value, ...newFilters }
      filters.value = mergedFilters

      const response = await api.get('/admin/tickets', { params: mergedFilters })
      
      if (response.data.success) {
        tickets.value = response.data.data
        pagination.value = response.data.pagination
      } else {
        setError(response.data.message || 'Failed to fetch tickets')
      }
    } catch (err: any) {
      setError(err.response?.data?.message || 'Failed to fetch tickets')
    } finally {
      loading.value = false
    }
  }

  // Fetch statistics
  const fetchStatistics = async () => {
    try {
      const response = await api.get('/admin/tickets/statistics')
      
      if (response.data.success) {
        statistics.value = response.data.data
      }
    } catch (err: any) {
      console.error('Failed to fetch ticket statistics:', err)
    }
  }

  // Fetch single ticket
  const fetchTicket = async (id: string) => {
    loading.value = true
    clearError()

    try {
      const response = await api.get(`/admin/tickets/${id}`)

      if (response.data.success) {
        currentTicket.value = response.data.data
        return response.data.data
      } else {
        setError(response.data.message || 'Failed to fetch ticket')
        return null
      }
    } catch (err: any) {
      setError(err.response?.data?.message || 'Failed to fetch ticket')
      return null
    } finally {
      loading.value = false
    }
  }

  // Create ticket
  const createTicket = async (data: CreateTicketData) => {
    loading.value = true
    clearError()

    try {
      const formData = new FormData()

      // Add basic fields
      formData.append('subject', data.subject)
      formData.append('category', data.category)
      formData.append('priority', data.priority)
      formData.append('requester_id', data.requester_id)

      // Add relations if provided
      if (data.relations) {
        data.relations.forEach((relation, index) => {
          formData.append(`relations[${index}][related_type]`, relation.related_type)
          formData.append(`relations[${index}][related_id]`, relation.related_id)
        })
      }

      // Add first message if provided
      if (data.first_message) {
        formData.append('first_message[body]', data.first_message.body)
        if (data.first_message.type) {
          formData.append('first_message[type]', data.first_message.type)
        }
        if (data.first_message.attachments) {
          data.first_message.attachments.forEach((file, index) => {
            formData.append(`first_message[attachments][${index}]`, file)
          })
        }
      }

      const response = await api.post('/admin/tickets', formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
      })

      if (response.data.success) {
        showSuccess(response.data.message)
        // Refresh tickets list
        await fetchTickets()
        return response.data.data
      } else {
        setError(response.data.message || 'Failed to create ticket')
        return null
      }
    } catch (err: any) {
      setError(err.response?.data?.message || 'Failed to create ticket')
      return null
    } finally {
      loading.value = false
    }
  }

  // Update ticket
  const updateTicket = async (id: string, data: Partial<CreateTicketData>) => {
    loading.value = true
    clearError()

    try {
      const response = await api.post(`/admin/tickets/${id}`, data)

      if (response.data.success) {
        showSuccess(response.data.message)

        // Update current ticket if it's the one being updated
        if (currentTicket.value?.id === id) {
          currentTicket.value = response.data.data
        }

        // Update in tickets list
        const index = tickets.value.findIndex(t => t.id === id)
        if (index !== -1) {
          tickets.value[index] = response.data.data
        }

        return response.data.data
      } else {
        setError(response.data.message || 'Failed to update ticket')
        return null
      }
    } catch (err: any) {
      setError(err.response?.data?.message || 'Failed to update ticket')
      return null
    } finally {
      loading.value = false
    }
  }

  // Assign ticket
  const assignTicket = async (id: string, assigneeId: string | null) => {
    loading.value = true
    clearError()

    try {
      const response = await api.post(`/admin/tickets/${id}/assign`, {
        assignee_id: assigneeId
      })

      if (response.data.success) {
        showSuccess(response.data.message)

        // Update current ticket if it's the one being assigned
        if (currentTicket.value?.id === id) {
          currentTicket.value = response.data.data
        }

        // Update in tickets list
        const index = tickets.value.findIndex(t => t.id === id)
        if (index !== -1) {
          tickets.value[index] = response.data.data
        }

        return response.data.data
      } else {
        setError(response.data.message || 'Failed to assign ticket')
        return null
      }
    } catch (err: any) {
      setError(err.response?.data?.message || 'Failed to assign ticket')
      return null
    } finally {
      loading.value = false
    }
  }

  // Change ticket status
  const changeStatus = async (id: string, status: string) => {
    loading.value = true
    clearError()

    try {
      const response = await api.post(`/admin/tickets/${id}/status`, { status })

      if (response.data.success) {
        showSuccess(response.data.message)

        // Update current ticket if it's the one being updated
        if (currentTicket.value?.id === id) {
          currentTicket.value = response.data.data
        }

        // Update in tickets list
        const index = tickets.value.findIndex(t => t.id === id)
        if (index !== -1) {
          tickets.value[index] = response.data.data
        }

        return response.data.data
      } else {
        setError(response.data.message || 'Failed to change ticket status')
        return null
      }
    } catch (err: any) {
      setError(err.response?.data?.message || 'Failed to change ticket status')
      return null
    } finally {
      loading.value = false
    }
  }

  // Delete ticket
  const deleteTicket = async (id: string) => {
    loading.value = true
    clearError()

    try {
      const response = await api.delete(`/admin/tickets/${id}`)

      if (response.data.success) {
        showSuccess(response.data.message)

        // Remove from tickets list
        tickets.value = tickets.value.filter(t => t.id !== id)

        // Clear current ticket if it's the one being deleted
        if (currentTicket.value?.id === id) {
          currentTicket.value = null
        }

        return true
      } else {
        setError(response.data.message || 'Failed to delete ticket')
        return false
      }
    } catch (err: any) {
      setError(err.response?.data?.message || 'Failed to delete ticket')
      return false
    } finally {
      loading.value = false
    }
  }

  // Bulk actions
  const bulkAction = async (action: string, ticketIds: string[], data?: any) => {
    loading.value = true
    clearError()

    try {
      const payload = {
        action,
        ticket_ids: ticketIds,
        ...data
      }

      const response = await api.post('/admin/tickets/bulk-action', payload)

      if (response.data.success) {
        showSuccess(response.data.message)
        // Refresh tickets list
        await fetchTickets()
        return response.data.updated_count
      } else {
        setError(response.data.message || 'Failed to perform bulk action')
        return 0
      }
    } catch (err: any) {
      setError(err.response?.data?.message || 'Failed to perform bulk action')
      return 0
    } finally {
      loading.value = false
    }
  }

  // Fetch messages for a ticket
  const fetchMessages = async (ticketId: string, page: number = 1) => {
    messagesLoading.value = true
    clearError()

    try {
      const response = await api.get(`/admin/tickets/${ticketId}/messages`, {
        params: { page, per_page: messagesPagination.value.per_page }
      })

      if (response.data.success) {
        if (page === 1) {
          messages.value = response.data.data
        } else {
          messages.value.push(...response.data.data)
        }
        messagesPagination.value = response.data.pagination
        return response.data.data
      } else {
        setError(response.data.message || 'Failed to fetch messages')
        return []
      }
    } catch (err: any) {
      setError(err.response?.data?.message || 'Failed to fetch messages')
      return []
    } finally {
      messagesLoading.value = false
    }
  }

  // Add message to ticket
  const addMessage = async (ticketId: string, data: CreateMessageData) => {
    messagesLoading.value = true
    clearError()

    try {
      const formData = new FormData()
      formData.append('type', data.type)
      formData.append('body', data.body)

      if (data.attachments) {
        data.attachments.forEach((file, index) => {
          formData.append(`attachments[${index}]`, file)
        })
      }

      const response = await api.post(`/admin/tickets/${ticketId}/messages`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
      })

      if (response.data.success) {
        showSuccess(response.data.message)

        // Add message to current messages list
        messages.value.push(response.data.data)

        // Update current ticket's last activity
        if (currentTicket.value?.id === ticketId) {
          currentTicket.value.last_activity_at = response.data.data.created_at
        }

        return response.data.data
      } else {
        setError(response.data.message || 'Failed to send message')
        return null
      }
    } catch (err: any) {
      setError(err.response?.data?.message || 'Failed to send message')
      return null
    } finally {
      messagesLoading.value = false
    }
  }

  // Reset state
  const resetState = () => {
    tickets.value = []
    currentTicket.value = null
    messages.value = []
    statistics.value = null
    error.value = null
    pagination.value = {
      current_page: 1,
      last_page: 1,
      per_page: 15,
      total: 0,
      from: 0,
      to: 0,
    }
    messagesPagination.value = {
      current_page: 1,
      last_page: 1,
      per_page: 20,
      total: 0,
      from: 0,
      to: 0,
    }
    filters.value = {
      page: 1,
      per_page: 15,
      sort: 'last_activity_at',
      dir: 'desc',
    }
  }

  return {
    // State
    tickets,
    currentTicket,
    messages,
    statistics,
    loading,
    messagesLoading,
    error,
    pagination,
    messagesPagination,
    filters,

    // Getters
    hasTickets,
    totalTickets,
    hasMessages,
    totalMessages,

    // Actions
    fetchTickets,
    fetchStatistics,
    fetchTicket,
    createTicket,
    updateTicket,
    assignTicket,
    changeStatus,
    deleteTicket,
    bulkAction,
    fetchMessages,
    addMessage,
    resetState,
    clearError,
  }
})
