import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axios from '@/plugins/axios'

export interface ShippingFilters {
  q?: string
  status?: string
  from?: string
  to?: string
  page?: number
  perPage?: number
  sort?: string
  dir?: string
}

export interface ShippingParcel {
  id: string
  commande_id: string
  provider: string
  tracking_number: string
  status: string
  city_id?: string
  city_name?: string
  receiver?: string
  phone?: string
  address?: string
  price?: number
  note?: string
  delivered_price?: number
  returned_price?: number
  refused_price?: number
  delivery_note_ref?: string
  last_synced_at?: string
  meta?: any
  created_at: string
  updated_at: string
}

export interface ShippingOrder {
  id: string
  statut: string
  total_ttc: number
  created_at: string
  updated_at: string
  boutique: {
    id: string
    nom: string
  }
  affilie: {
    utilisateur: {
      id: string
      nom_complet: string
      email: string
    }
  }
  client: {
    id: string
    nom_complet: string
    telephone: string
  }
  adresse: {
    id: string
    ville: string
    adresse: string
  }
  shipping_parcel: ShippingParcel
}

export interface ShippingCity {
  city_id: string
  ref?: string
  name: string
  prices: {
    delivery: number
    return: number
    refused: number
  }
}

export interface PaginationData {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export const useShippingStore = defineStore('shipping', () => {
  // State
  const shippingOrders = ref<ShippingOrder[]>([])
  const currentShippingOrder = ref<ShippingOrder | null>(null)
  const cities = ref<ShippingCity[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)
  const pagination = ref<PaginationData>({
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 0,
  })

  const filters = ref<ShippingFilters>({
    page: 1,
    perPage: 15,
    sort: 'updated_at',
    dir: 'desc',
  })

  // Getters
  const hasShippingOrders = computed(() => shippingOrders.value.length > 0)
  const isLoading = computed(() => loading.value)
  const hasCities = computed(() => cities.value.length > 0)

  // Actions
  const fetchShippingOrders = async (newFilters?: Partial<ShippingFilters>) => {
    loading.value = true
    error.value = null

    try {
      if (newFilters) {
        Object.assign(filters.value, newFilters)
      }

      const response = await axios.get('/api/admin/shipping/orders', {
        params: filters.value,
      })

      if (response.data.success) {
        shippingOrders.value = response.data.data
        pagination.value = response.data.pagination
      } else {
        throw new Error(response.data.message || 'Failed to fetch shipping orders')
      }
    } catch (err: any) {
      error.value = err.response?.data?.message || err.message || 'Failed to fetch shipping orders'
      console.error('Error fetching shipping orders:', err)
    } finally {
      loading.value = false
    }
  }

  const fetchShippingOrder = async (id: string) => {
    loading.value = true
    error.value = null

    try {
      const response = await axios.get(`/api/admin/shipping/orders/${id}`)

      if (response.data.success) {
        currentShippingOrder.value = response.data.data
      } else {
        throw new Error(response.data.message || 'Failed to fetch shipping order')
      }
    } catch (err: any) {
      error.value = err.response?.data?.message || err.message || 'Failed to fetch shipping order'
      console.error('Error fetching shipping order:', err)
    } finally {
      loading.value = false
    }
  }

  const addParcel = async (commandeId: string, trackingNumber?: string) => {
    loading.value = true
    error.value = null

    try {
      const response = await axios.post('/api/admin/shipping/ozon/parcels', {
        commande_id: commandeId,
        tracking_number: trackingNumber,
      })

      if (response.data.success) {
        return response.data.data
      } else {
        throw new Error(response.data.message || 'Failed to create parcel')
      }
    } catch (err: any) {
      error.value = err.response?.data?.message || err.message || 'Failed to create parcel'
      console.error('Error creating parcel:', err)
      throw err
    } finally {
      loading.value = false
    }
  }

  const getTracking = async (trackingNumber: string) => {
    loading.value = true
    error.value = null

    try {
      const response = await axios.post('/api/admin/shipping/ozon/tracking', {
        tracking_number: trackingNumber,
      })

      if (response.data.success) {
        return response.data.data
      } else {
        throw new Error(response.data.message || 'Failed to get tracking')
      }
    } catch (err: any) {
      error.value = err.response?.data?.message || err.message || 'Failed to get tracking'
      console.error('Error getting tracking:', err)
      throw err
    } finally {
      loading.value = false
    }
  }

  const getParcelInfo = async (trackingNumber: string) => {
    loading.value = true
    error.value = null

    try {
      const response = await axios.post('/api/admin/shipping/ozon/parcel-info', {
        tracking_number: trackingNumber,
      })

      if (response.data.success) {
        return response.data.data
      } else {
        throw new Error(response.data.message || 'Failed to get parcel info')
      }
    } catch (err: any) {
      error.value = err.response?.data?.message || err.message || 'Failed to get parcel info'
      console.error('Error getting parcel info:', err)
      throw err
    } finally {
      loading.value = false
    }
  }

  const resendToOzon = async (commandeId: string, mode: 'ramassage' | 'stock' = 'ramassage') => {
    loading.value = true
    error.value = null

    try {
      const response = await axios.post('/api/admin/shipping/ozon/resend', {
        commande_id: commandeId,
        mode
      })

      if (response.data.success) {
        // Refresh shipping orders to get updated data
        await fetchShippingOrders(filters.value)
        return response.data.data
      } else {
        throw new Error(response.data.message || 'Failed to resend to OzonExpress')
      }
    } catch (err: any) {
      error.value = err.response?.data?.message || err.message || 'Failed to resend to OzonExpress'
      console.error('Error resending to OzonExpress:', err)
      throw err
    } finally {
      loading.value = false
    }
  }

  const trackParcel = async (trackingNumber: string) => {
    loading.value = true
    error.value = null

    try {
      const response = await axios.post('/api/admin/shipping/ozon/track', {
        tracking_number: trackingNumber,
      })

      if (response.data.success) {
        return response.data.data
      } else {
        throw new Error(response.data.message || 'Failed to track parcel')
      }
    } catch (err: any) {
      error.value = err.response?.data?.message || err.message || 'Failed to track parcel'
      console.error('Error tracking parcel:', err)
      throw err
    } finally {
      loading.value = false
    }
  }

  const getParcelInfoNew = async (trackingNumber: string) => {
    loading.value = true
    error.value = null

    try {
      const response = await axios.post('/api/admin/shipping/ozon/parcel-info', {
        tracking_number: trackingNumber,
      })

      if (response.data.success) {
        return response.data.data
      } else {
        throw new Error(response.data.message || 'Failed to get parcel info')
      }
    } catch (err: any) {
      error.value = err.response?.data?.message || err.message || 'Failed to get parcel info'
      console.error('Error getting parcel info:', err)
      throw err
    } finally {
      loading.value = false
    }
  }

  const createDeliveryNote = async () => {
    loading.value = true
    error.value = null

    try {
      const response = await axios.post('/api/admin/shipping/ozon/dn/create')

      if (response.data.success) {
        return response.data.data.ref
      } else {
        throw new Error(response.data.message || 'Failed to create delivery note')
      }
    } catch (err: any) {
      error.value = err.response?.data?.message || err.message || 'Failed to create delivery note'
      console.error('Error creating delivery note:', err)
      throw err
    } finally {
      loading.value = false
    }
  }

  const addParcelsToDeliveryNote = async (ref: string, codes: string[]) => {
    loading.value = true
    error.value = null

    try {
      const response = await axios.post('/api/admin/shipping/ozon/dn/add-parcels', {
        ref,
        codes,
      })

      if (response.data.success) {
        return response.data.data
      } else {
        throw new Error(response.data.message || 'Failed to add parcels to delivery note')
      }
    } catch (err: any) {
      error.value = err.response?.data?.message || err.message || 'Failed to add parcels to delivery note'
      console.error('Error adding parcels to delivery note:', err)
      throw err
    } finally {
      loading.value = false
    }
  }

  const saveDeliveryNote = async (ref: string) => {
    loading.value = true
    error.value = null

    try {
      const response = await axios.post('/api/admin/shipping/ozon/dn/save', {
        ref,
      })

      if (response.data.success) {
        return response.data.data
      } else {
        throw new Error(response.data.message || 'Failed to save delivery note')
      }
    } catch (err: any) {
      error.value = err.response?.data?.message || err.message || 'Failed to save delivery note'
      console.error('Error saving delivery note:', err)
      throw err
    } finally {
      loading.value = false
    }
  }

  const getDeliveryNotePdf = async (ref: string) => {
    loading.value = true
    error.value = null

    try {
      const response = await axios.get('/api/admin/shipping/ozon/dn/pdf', {
        params: { ref }
      })

      if (response.data.success) {
        return response.data.data
      } else {
        throw new Error(response.data.message || 'Failed to get PDF links')
      }
    } catch (err: any) {
      error.value = err.response?.data?.message || err.message || 'Failed to get PDF links'
      console.error('Error getting PDF links:', err)
      throw err
    } finally {
      loading.value = false
    }
  }

  const fetchCities = async (refresh = false) => {
    loading.value = true
    error.value = null

    try {
      const response = await axios.get('/api/admin/shipping/ozon/cities', {
        params: { refresh },
      })

      if (response.data.success) {
        cities.value = response.data.data
      } else {
        throw new Error(response.data.message || 'Failed to fetch cities')
      }
    } catch (err: any) {
      error.value = err.response?.data?.message || err.message || 'Failed to fetch cities'
      console.error('Error fetching cities:', err)
    } finally {
      loading.value = false
    }
  }

  const resetFilters = () => {
    filters.value = {
      page: 1,
      perPage: 15,
      sort: 'updated_at',
      dir: 'desc',
    }
  }

  const clearCurrentShippingOrder = () => {
    currentShippingOrder.value = null
  }

  const refreshTracking = async (trackingNumber: string): Promise<any> => {
    try {
      const response = await axios.post('/api/admin/shipping/orders/refresh-tracking', {
        tracking_number: trackingNumber
      })

      if (response.data.success) {
        // Refresh shipping orders to get updated data
        await fetchShippingOrders(filters.value)
      }

      return response.data
    } catch (error: any) {
      console.error('Error refreshing tracking:', error)
      throw error
    }
  }

  const refreshTrackingBulk = async (trackingNumbers: string[]): Promise<any> => {
    try {
      const response = await axios.post('/api/admin/shipping/orders/refresh-tracking-bulk', {
        tracking_numbers: trackingNumbers
      })

      if (response.data.success) {
        // Refresh shipping orders to get updated data
        await fetchShippingOrders(filters.value)
      }

      return response.data
    } catch (error: any) {
      console.error('Error refreshing tracking bulk:', error)
      throw error
    }
  }

  return {
    // State
    shippingOrders,
    currentShippingOrder,
    cities,
    loading,
    error,
    pagination,
    filters,

    // Getters
    hasShippingOrders,
    isLoading,
    hasCities,

    // Actions
    fetchShippingOrders,
    fetchShippingOrder,
    addParcel,
    getTracking,
    getParcelInfo,
    resendToOzon,
    trackParcel,
    getParcelInfoNew,
    createDeliveryNote,
    addParcelsToDeliveryNote,
    saveDeliveryNote,
    getDeliveryNotePdf,
    fetchCities,
    resetFilters,
    clearCurrentShippingOrder,
    refreshTracking,
    refreshTrackingBulk,
  }
})
