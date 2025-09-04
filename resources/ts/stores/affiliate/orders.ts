import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { $api } from '@/utils/api'

export interface AffiliateOrder {
  id: string
  boutique_id: string
  client_id: string
  client_final_id?: string
  adresse_id: string
  statut: string
  delivery_boy_name?: string | null
  delivery_boy_phone?: string | null
  confirmation_cc: string
  mode_paiement: string
  type_command: string
  total_ht: number
  total_ttc: number
  devise: string
  notes?: string
  created_at: string
  updated_at: string
  boutique?: {
    id: string
    nom: string
  }
  client?: {
    id: string
    nom_complet: string
    telephone: string
  }
  clientFinal?: {
    id: string
    nom_complet: string
    telephone: string
    email: string
  }
  adresse?: {
    id: string
    ville: string
    adresse: string
  }
  articles?: Array<{
    id: string
    produit_id: string
    variante_id?: string
    quantite: number
    prix_unitaire: number
    produit?: {
      id: string
      titre: string
      sku?: string | null
    }
    variante?: {
      id: string
      nom: string
    }
  }>
  shippingParcel?: any
  expeditions?: any[]
  commissions?: any[]
  conflits?: any[]
  retours?: any[]
}

export interface AffiliateOrderFilters {
  q?: string
  status?: string[]
  boutique_id?: string
  date_from?: string
  date_to?: string
  sort?: string
  dir?: 'asc' | 'desc'
  per_page?: number
}

export interface OrderPagination {
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
}

export const useAffiliateOrdersStore = defineStore('affiliateOrders', () => {
  // State
  const orders = ref<AffiliateOrder[]>([])
  const currentOrder = ref<AffiliateOrder | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)
  const pagination = ref<OrderPagination>({
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 0,
    from: null,
    to: null,
  })
  const filters = ref<AffiliateOrderFilters>({
    q: '',
    status: [],
    boutique_id: '',
    date_from: '',
    date_to: '',
    sort: 'created_at',
    dir: 'desc',
    per_page: 15,
  })

  // Getters
  const hasOrders = computed(() => orders.value.length > 0)
  const isLoading = computed(() => loading.value)

  // Actions
  const fetchOrders = async (page = 1) => {
    loading.value = true
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
      if (filters.value.boutique_id) params.append('boutique_id', filters.value.boutique_id)
      if (filters.value.date_from) params.append('date_from', filters.value.date_from)
      if (filters.value.date_to) params.append('date_to', filters.value.date_to)
      if (filters.value.sort) params.append('sort', filters.value.sort)
      if (filters.value.dir) params.append('dir', filters.value.dir)

      const response = await $api(`/affiliate/orders?${params.toString()}`)
      
      if (response.success) {
        orders.value = response.data
        pagination.value = response.pagination
      } else {
        throw new Error(response.message || 'Failed to fetch orders')
      }
    } catch (err: any) {
      error.value = err.message || 'An error occurred while fetching orders'
      console.error('Error fetching affiliate orders:', err)
    } finally {
      loading.value = false
    }
  }

  // Track current request to allow cancellation
  let currentOrderRequest: AbortController | null = null

  const fetchOrder = async (id: string) => {
    // Cancel previous request if still pending
    if (currentOrderRequest) {
      currentOrderRequest.abort()
    }

    // Create new abort controller for this request
    currentOrderRequest = new AbortController()

    loading.value = true
    error.value = null

    try {
      const response = await $api(`/affiliate/orders/${id}`, {
        signal: currentOrderRequest.signal
      })

      if (response.success) {
        currentOrder.value = response.data
      } else {
        throw new Error(response.message || 'Failed to fetch order')
      }
    } catch (err: any) {
      // Don't set error if request was aborted (user navigated away)
      if (err.name !== 'AbortError') {
        error.value = err.message || 'An error occurred while fetching the order'
        console.error('Error fetching affiliate order:', err)
      }
    } finally {
      loading.value = false
      currentOrderRequest = null
    }
  }

  const updateFilters = (newFilters: Partial<AffiliateOrderFilters>) => {
    filters.value = { ...filters.value, ...newFilters }
  }

  const resetFilters = () => {
    filters.value = {
      q: '',
      status: [],
      boutique_id: '',
      date_from: '',
      date_to: '',
      sort: 'created_at',
      dir: 'desc',
      per_page: 15,
    }
  }

  const clearCurrentOrder = () => {
    currentOrder.value = null
  }

  const getStatusColor = (status: string): string => {
    const statusColors: Record<string, string> = {
      // Actual French status values from database
      'en_attente': 'warning',
      'confirmee': 'success',
      'expediee': 'primary',
      'livree': 'success',
      'annulee': 'error',
      'retournee': 'secondary',
      'returned_to_warehouse': 'info',
      'echec_livraison': 'error',
      // OzonExpress statuses (for shipping)
      'pending': 'warning',
      'received': 'info',
      'in_transit': 'primary',
      'shipped': 'primary',
      'at_facility': 'info',
      'ready_for_delivery': 'primary',
      'out_for_delivery': 'primary',
      'delivery_attempted': 'warning',
      'delivered': 'success',
      'returned': 'secondary',
      'refused': 'error',
      'cancelled': 'error',
    }
    return statusColors[status] || 'secondary'
  }

  const getStatusLabel = (status: string): string => {
    const statusLabels: Record<string, string> = {
      // Actual French status values from database
      'en_attente': 'En attente',
      'confirmee': 'Confirmée',
      'expediee': 'Expédiée',
      'livree': 'Livrée',
      'annulee': 'Annulée',
      'retournee': 'Retournée',
      'returned_to_warehouse': 'Retournée en entrepôt',
      'echec_livraison': 'Échec livraison',
      // OzonExpress statuses (for shipping)
      'pending': 'En attente',
      'received': 'Reçu',
      'in_transit': 'En transit',
      'shipped': 'Expédié',
      'at_facility': 'En centre',
      'ready_for_delivery': 'Prêt livraison',
      'out_for_delivery': 'En livraison',
      'delivery_attempted': 'Tentative livraison',
      'delivered': 'Livré',
      'returned': 'Retourné',
      'refused': 'Refusé',
      'cancelled': 'Annulé',
    }
    return statusLabels[status] || status
  }

  return {
    // State
    orders,
    currentOrder,
    loading,
    error,
    pagination,
    filters,

    // Getters
    hasOrders,
    isLoading,

    // Actions
    fetchOrders,
    fetchOrder,
    updateFilters,
    resetFilters,
    clearCurrentOrder,
    getStatusColor,
    getStatusLabel,
  }
})
