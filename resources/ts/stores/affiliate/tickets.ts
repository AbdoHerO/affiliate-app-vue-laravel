import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { $api } from '@/utils/api'

export interface AffiliateTicket {
  id: string
  subject: string
  status: 'open' | 'pending' | 'waiting_user' | 'waiting_third_party' | 'resolved' | 'closed'
  priority: 'low' | 'normal' | 'high' | 'urgent'
  category: 'general' | 'technical' | 'billing' | 'account' | 'order'
  requester_id: string
  assignee_id?: string
  first_response_at?: string
  resolved_at?: string
  last_activity_at: string
  created_at: string
  updated_at: string
  requester?: {
    id: string
    nom_complet: string
    email: string
  }
  assignee?: {
    id: string
    nom_complet: string
    email: string
  }
  messages?: TicketMessage[]
  messages_count?: number
  unread_messages_count?: number
}

export interface TicketMessage {
  id: string
  ticket_id: string
  user_id: string
  message: string
  is_internal: boolean
  created_at: string
  updated_at: string
  user?: {
    id: string
    nom_complet: string
    email: string
  }
  attachments?: TicketAttachment[]
}

export interface TicketAttachment {
  id: string
  filename: string
  path: string
  size: number
  mime_type: string
  created_at: string
}

export interface CreateTicketData {
  subject: string
  category: string
  priority: string
  message: string
  attachments?: File[]
}

export interface AddMessageData {
  message: string
  attachments?: File[]
}

export interface TicketFilters {
  q?: string
  status?: string[]
  priority?: string[]
  category?: string[]
  date_from?: string
  date_to?: string
  sort?: string
  dir?: 'asc' | 'desc'
  per_page?: number
}

export interface TicketsPagination {
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
}

export const useAffiliateTicketsStore = defineStore('affiliateTickets', () => {
  // State
  const tickets = ref<AffiliateTicket[]>([])
  const currentTicket = ref<AffiliateTicket | null>(null)
  const loading = ref({
    list: false,
    detail: false,
    create: false,
    message: false,
    status: false,
  })
  const error = ref<string | null>(null)
  const pagination = ref<TicketsPagination>({
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 0,
    from: null,
    to: null,
  })
  const filters = ref<TicketFilters>({
    q: '',
    status: [],
    priority: [],
    category: [],
    date_from: '',
    date_to: '',
    sort: 'last_activity_at',
    dir: 'desc',
    per_page: 15,
  })

  // Getters
  const hasTickets = computed(() => tickets.value.length > 0)
  const isLoadingList = computed(() => loading.value.list)
  const isLoadingDetail = computed(() => loading.value.detail)
  const isLoadingCreate = computed(() => loading.value.create)
  const isLoadingMessage = computed(() => loading.value.message)
  const isLoadingStatus = computed(() => loading.value.status)

  // Actions
  const fetchTickets = async (page = 1) => {
    loading.value.list = true
    error.value = null

    try {
      const params = new URLSearchParams()
      
      // Add pagination
      params.append('page', page.toString())
      params.append('per_page', filters.value.per_page?.toString() || '15')
      
      // Add filters
      if (filters.value.q) params.append('q', filters.value.q)
      if (filters.value.status?.length) {
        filters.value.status.forEach(status => params.append('status[]', status))
      }
      if (filters.value.priority?.length) {
        filters.value.priority.forEach(priority => params.append('priority[]', priority))
      }
      if (filters.value.category?.length) {
        filters.value.category.forEach(category => params.append('category[]', category))
      }
      if (filters.value.date_from) params.append('date_from', filters.value.date_from)
      if (filters.value.date_to) params.append('date_to', filters.value.date_to)
      if (filters.value.sort) params.append('sort', filters.value.sort)
      if (filters.value.dir) params.append('dir', filters.value.dir)

      const response = await $api(`/affiliate/tickets?${params.toString()}`)
      
      if (response.success) {
        tickets.value = response.data
        pagination.value = response.pagination
      } else {
        throw new Error(response.message || 'Failed to fetch tickets')
      }
    } catch (err: any) {
      error.value = err.message || 'An error occurred while fetching tickets'
      console.error('Error fetching affiliate tickets:', err)
    } finally {
      loading.value.list = false
    }
  }

  const fetchTicket = async (id: string) => {
    loading.value.detail = true
    error.value = null

    try {
      const response = await $api(`/affiliate/tickets/${id}`)
      
      if (response.success) {
        currentTicket.value = response.data
      } else {
        throw new Error(response.message || 'Failed to fetch ticket')
      }
    } catch (err: any) {
      error.value = err.message || 'An error occurred while fetching the ticket'
      console.error('Error fetching affiliate ticket:', err)
    } finally {
      loading.value.detail = false
    }
  }

  const createTicket = async (data: CreateTicketData) => {
    loading.value.create = true
    error.value = null

    try {
      const formData = new FormData()
      formData.append('subject', data.subject)
      formData.append('category', data.category)
      formData.append('priority', data.priority)
      formData.append('message', data.message)
      
      if (data.attachments?.length) {
        data.attachments.forEach((file, index) => {
          formData.append(`attachments[${index}]`, file)
        })
      }

      const response = await $api('/affiliate/tickets', {
        method: 'POST',
        body: formData,
      })
      
      if (response.success) {
        // Refresh tickets list
        await fetchTickets()
        return response.data
      } else {
        throw new Error(response.message || 'Failed to create ticket')
      }
    } catch (err: any) {
      error.value = err.message || 'An error occurred while creating the ticket'
      console.error('Error creating affiliate ticket:', err)
      throw err
    } finally {
      loading.value.create = false
    }
  }

  const addMessage = async (ticketId: string, data: AddMessageData) => {
    loading.value.message = true
    error.value = null

    try {
      const formData = new FormData()
      formData.append('message', data.message)
      
      if (data.attachments?.length) {
        data.attachments.forEach((file, index) => {
          formData.append(`attachments[${index}]`, file)
        })
      }

      const response = await $api(`/affiliate/tickets/${ticketId}/messages`, {
        method: 'POST',
        body: formData,
      })
      
      if (response.success) {
        // Refresh current ticket to get updated messages
        if (currentTicket.value?.id === ticketId) {
          await fetchTicket(ticketId)
        }
        return response.data
      } else {
        throw new Error(response.message || 'Failed to add message')
      }
    } catch (err: any) {
      error.value = err.message || 'An error occurred while adding the message'
      console.error('Error adding message to affiliate ticket:', err)
      throw err
    } finally {
      loading.value.message = false
    }
  }

  const updateTicketStatus = async (ticketId: string, status: 'open' | 'closed') => {
    loading.value.status = true
    error.value = null

    try {
      const response = await $api(`/affiliate/tickets/${ticketId}/status`, {
        method: 'PATCH',
        body: JSON.stringify({ status }),
      })
      
      if (response.success) {
        // Update current ticket if it's the same one
        if (currentTicket.value?.id === ticketId) {
          currentTicket.value = response.data
        }
        
        // Update ticket in the list
        const ticketIndex = tickets.value.findIndex(t => t.id === ticketId)
        if (ticketIndex !== -1) {
          tickets.value[ticketIndex] = response.data
        }
        
        return response.data
      } else {
        throw new Error(response.message || 'Failed to update ticket status')
      }
    } catch (err: any) {
      error.value = err.message || 'An error occurred while updating the ticket status'
      console.error('Error updating affiliate ticket status:', err)
      throw err
    } finally {
      loading.value.status = false
    }
  }

  const updateFilters = (newFilters: Partial<TicketFilters>) => {
    filters.value = { ...filters.value, ...newFilters }
  }

  const resetFilters = () => {
    filters.value = {
      q: '',
      status: [],
      priority: [],
      category: [],
      date_from: '',
      date_to: '',
      sort: 'last_activity_at',
      dir: 'desc',
      per_page: 15,
    }
  }

  const clearCurrentTicket = () => {
    currentTicket.value = null
  }

  const getStatusColor = (status: string): string => {
    const statusColors: Record<string, string> = {
      'open': 'info',
      'pending': 'warning',
      'waiting_user': 'orange',
      'waiting_third_party': 'purple',
      'resolved': 'success',
      'closed': 'secondary',
    }
    return statusColors[status] || 'secondary'
  }

  const getStatusLabel = (status: string): string => {
    const statusLabels: Record<string, string> = {
      'open': 'Ouvert',
      'pending': 'En attente',
      'waiting_user': 'En attente utilisateur',
      'waiting_third_party': 'En attente tiers',
      'resolved': 'Résolu',
      'closed': 'Fermé',
    }
    return statusLabels[status] || status
  }

  const getPriorityColor = (priority: string): string => {
    const priorityColors: Record<string, string> = {
      'low': 'success',
      'normal': 'info',
      'high': 'warning',
      'urgent': 'error',
    }
    return priorityColors[priority] || 'secondary'
  }

  const getPriorityLabel = (priority: string): string => {
    const priorityLabels: Record<string, string> = {
      'low': 'Faible',
      'normal': 'Normal',
      'high': 'Élevée',
      'urgent': 'Urgent',
    }
    return priorityLabels[priority] || priority
  }

  const getCategoryLabel = (category: string): string => {
    const categoryLabels: Record<string, string> = {
      'general': 'Général',
      'technical': 'Technique',
      'billing': 'Facturation',
      'account': 'Compte',
      'order': 'Commande',
    }
    return categoryLabels[category] || category
  }

  return {
    // State
    tickets,
    currentTicket,
    loading,
    error,
    pagination,
    filters,

    // Getters
    hasTickets,
    isLoadingList,
    isLoadingDetail,
    isLoadingCreate,
    isLoadingMessage,
    isLoadingStatus,

    // Actions
    fetchTickets,
    fetchTicket,
    createTicket,
    addMessage,
    updateTicketStatus,
    updateFilters,
    resetFilters,
    clearCurrentTicket,
    getStatusColor,
    getStatusLabel,
    getPriorityColor,
    getPriorityLabel,
    getCategoryLabel,
  }
})
