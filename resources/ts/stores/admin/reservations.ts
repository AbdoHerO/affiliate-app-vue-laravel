import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axios from '@/plugins/axios'

export interface Reservation {
  id: string
  variante_id: string
  entrepot_id: string
  quantite: number
  gamme_id?: string | null
  affilie_id?: string | null
  offre_id?: string | null
  date_expire?: string | null
  statut: 'active' | 'utilisee' | 'expiree' | 'annulee'
  created_at: string
  variante?: {
    id: string
    libelle: string
    produit: {
      id: string
      titre: string
      slug: string
    }
  }
  entrepot?: {
    id: string
    nom: string
  }
  affilie?: {
    id: string
    nom_complet: string
  }
  offre?: {
    id: string
    titre: string
  }
}

export interface ReservationFilters {
  q?: string
  variante_id?: string
  entrepot_id?: string
  statut?: string
  affilie_id?: string
  page?: number
  per_page?: number
}

export interface ReservationStats {
  total_active: number
  total_expired: number
  total_used: number
  total_cancelled: number
  total_quantity_reserved: number
}

export interface CreateReservationForm {
  variante_id: string
  entrepot_id: string
  quantite: number
  gamme_id?: string | null
  affilie_id?: string | null
  offre_id?: string | null
  date_expire?: string | null
  note?: string | null
}

export interface ReservationOptions {
  variants: Array<{
    value: string
    title: string
    product_title: string
    variant_label: string
  }>
  entrepots: Array<{
    value: string
    title: string
  }>
  statuts: Array<{
    value: string
    title: string
  }>
}

export const useReservationsStore = defineStore('reservations', () => {
  // State
  const reservations = ref<Reservation[]>([])
  const loading = ref(false)
  const stats = ref<ReservationStats | null>(null)
  const options = ref<ReservationOptions | null>(null)
  
  // Pagination
  const pagination = ref({
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 0,
  })

  // Filters
  const filters = ref<ReservationFilters>({
    q: '',
    variante_id: '',
    entrepot_id: '',
    statut: '',
    affilie_id: '',
    page: 1,
    per_page: 15,
  })

  // Computed
  const hasReservations = computed(() => reservations.value.length > 0)
  const activeReservations = computed(() => 
    reservations.value.filter(r => r.statut === 'active')
  )

  // Actions
  const fetchReservations = async () => {
    try {
      loading.value = true
      
      const params = new URLSearchParams()
      Object.entries(filters.value).forEach(([key, value]) => {
        if (value !== '' && value !== null && value !== undefined) {
          params.append(key, String(value))
        }
      })

      const response = await axios.get(`/admin/reservations?${params.toString()}`)
      
      if (response.data.success) {
        reservations.value = response.data.data
        pagination.value = response.data.pagination
      }
      
      return response.data
    } catch (error) {
      console.error('Failed to fetch reservations:', error)
      throw error
    } finally {
      loading.value = false
    }
  }

  const createReservation = async (data: CreateReservationForm) => {
    try {
      const response = await axios.post('/admin/reservations', data)
      
      if (response.data.success) {
        // Refresh the list
        await fetchReservations()
      }
      
      return response.data
    } catch (error) {
      console.error('Failed to create reservation:', error)
      throw error
    }
  }

  const releaseReservation = async (reservationId: string, reason?: string) => {
    try {
      const response = await axios.post(`/admin/reservations/${reservationId}/release`, {
        reason
      })
      
      if (response.data.success) {
        // Refresh the list
        await fetchReservations()
      }
      
      return response.data
    } catch (error) {
      console.error('Failed to release reservation:', error)
      throw error
    }
  }

  const useReservation = async (reservationId: string, reference?: string) => {
    try {
      const response = await axios.post(`/admin/reservations/${reservationId}/use`, {
        reference
      })
      
      if (response.data.success) {
        // Refresh the list
        await fetchReservations()
      }
      
      return response.data
    } catch (error) {
      console.error('Failed to use reservation:', error)
      throw error
    }
  }

  const fetchStats = async () => {
    try {
      const response = await axios.get('/admin/reservations/stats')
      
      if (response.data.success) {
        stats.value = response.data.data
      }
      
      return response.data
    } catch (error) {
      console.error('Failed to fetch reservation stats:', error)
      throw error
    }
  }

  const fetchOptions = async () => {
    try {
      const response = await axios.get('/admin/reservations/options')
      
      if (response.data.success) {
        options.value = response.data.data
      }
      
      return response.data
    } catch (error) {
      console.error('Failed to fetch reservation options:', error)
      throw error
    }
  }

  const cleanupExpired = async () => {
    try {
      const response = await axios.post('/admin/reservations/cleanup')
      
      if (response.data.success) {
        // Refresh the list and stats
        await Promise.all([fetchReservations(), fetchStats()])
      }
      
      return response.data
    } catch (error) {
      console.error('Failed to cleanup expired reservations:', error)
      throw error
    }
  }

  // Utility functions
  const updateFilters = (newFilters: Partial<ReservationFilters>) => {
    Object.assign(filters.value, newFilters)
  }

  const resetFilters = () => {
    Object.assign(filters.value, {
      q: '',
      variante_id: '',
      entrepot_id: '',
      statut: '',
      affilie_id: '',
      page: 1,
      per_page: 15,
    })
  }

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'active': return 'success'
      case 'utilisee': return 'info'
      case 'expiree': return 'warning'
      case 'annulee': return 'error'
      default: return 'default'
    }
  }

  const getStatusIcon = (status: string) => {
    switch (status) {
      case 'active': return 'tabler-clock'
      case 'utilisee': return 'tabler-check'
      case 'expiree': return 'tabler-clock-off'
      case 'annulee': return 'tabler-x'
      default: return 'tabler-help'
    }
  }

  return {
    // State
    reservations,
    loading,
    stats,
    options,
    pagination,
    filters,
    
    // Computed
    hasReservations,
    activeReservations,
    
    // Actions
    fetchReservations,
    createReservation,
    releaseReservation,
    useReservation,
    fetchStats,
    fetchOptions,
    cleanupExpired,
    
    // Utilities
    updateFilters,
    resetFilters,
    getStatusColor,
    getStatusIcon,
  }
})
