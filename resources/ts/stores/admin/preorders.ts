import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axios from '@/plugins/axios'

export interface PreorderFilters {
  q?: string
  statut?: string
  affilie_id?: string
  boutique_id?: string
  from?: string
  to?: string
  page?: number
  perPage?: number
  sort?: string
  dir?: string
}

export interface PreorderArticle {
  id: string
  produit_id: string
  variante_id?: string
  quantite: number
  prix_unitaire: number
  remise: number
  total_ligne: number
  produit: {
    id: string
    titre: string
    images?: Array<{ url: string }>
  }
  variante?: {
    id: string
    nom: string
  }
}

export interface Preorder {
  id: string
  boutique_id: string
  affilie_id: string
  client_id: string
  adresse_id: string
  offre_id?: string
  statut: string
  confirmation_cc: string
  mode_paiement: string
  total_ht: number
  total_ttc: number
  devise: string
  notes?: string
  no_answer_count: number
  created_at: string
  updated_at: string
  boutique: {
    id: string
    nom: string
  }
  affiliate: {
    id: string
    nom_complet: string
    email: string
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
  articles: PreorderArticle[]
  shipping_parcel?: any
}

export interface PaginationData {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export const usePreordersStore = defineStore('preorders', () => {
  // State
  const preorders = ref<Preorder[]>([])
  const currentPreorder = ref<Preorder | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)
  const pagination = ref<PaginationData>({
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 0,
  })

  const filters = ref<PreorderFilters>({
    page: 1,
    perPage: 15,
    sort: 'created_at',
    dir: 'desc',
  })

  // Getters
  const hasPreorders = computed(() => preorders.value.length > 0)
  const isLoading = computed(() => loading.value)

  // Actions
  const fetchPreorders = async (newFilters?: Partial<PreorderFilters>) => {
    loading.value = true
    error.value = null

    try {
      if (newFilters) {
        Object.assign(filters.value, newFilters)
      }

      const response = await axios.get('admin/preorders', {
        params: filters.value,
      })

      if (response.data.success) {
        preorders.value = response.data.data
        pagination.value = response.data.pagination
      } else {
        throw new Error(response.data.message || 'Failed to fetch preorders')
      }
    } catch (err: any) {
      error.value = err.response?.data?.message || err.message || 'Failed to fetch preorders'
      console.error('Error fetching preorders:', err)
    } finally {
      loading.value = false
    }
  }

  const fetchPreorder = async (id: string) => {
    loading.value = true
    error.value = null

    try {
      const response = await axios.get(`admin/preorders/${id}`)

      if (response.data.success) {
        currentPreorder.value = response.data.data
      } else {
        throw new Error(response.data.message || 'Failed to fetch preorder')
      }
    } catch (err: any) {
      error.value = err.response?.data?.message || err.message || 'Failed to fetch preorder'
      console.error('Error fetching preorder:', err)
    } finally {
      loading.value = false
    }
  }

  const updatePreorder = async (id: string, data: Partial<Preorder>) => {
    loading.value = true
    error.value = null

    try {
      const response = await axios.put(`admin/preorders/${id}`, data)

      if (response.data.success) {
        currentPreorder.value = response.data.data
        
        // Update in list if present
        const index = preorders.value.findIndex(p => p.id === id)
        if (index !== -1) {
          preorders.value[index] = response.data.data
        }

        return response.data.data
      } else {
        throw new Error(response.data.message || 'Failed to update preorder')
      }
    } catch (err: any) {
      error.value = err.response?.data?.message || err.message || 'Failed to update preorder'
      console.error('Error updating preorder:', err)
      throw err
    } finally {
      loading.value = false
    }
  }

  const confirmPreorder = async (id: string) => {
    loading.value = true
    error.value = null

    try {
      const response = await axios.post(`admin/preorders/${id}/confirm`)

      if (response.data.success) {
        currentPreorder.value = response.data.data
        
        // Update in list if present
        const index = preorders.value.findIndex(p => p.id === id)
        if (index !== -1) {
          preorders.value[index] = response.data.data
        }

        return response.data.data
      } else {
        throw new Error(response.data.message || 'Failed to confirm preorder')
      }
    } catch (err: any) {
      error.value = err.response?.data?.message || err.message || 'Failed to confirm preorder'
      console.error('Error confirming preorder:', err)
      throw err
    } finally {
      loading.value = false
    }
  }

  const resetFilters = () => {
    filters.value = {
      page: 1,
      perPage: 15,
      sort: 'created_at',
      dir: 'desc',
    }
  }

  const clearCurrentPreorder = () => {
    currentPreorder.value = null
  }

  // Bulk actions
  const bulkChangeStatus = async (ids: string[], to: string, note?: string) => {
    try {
      const response = await axios.post('admin/preorders/bulk/status', {
        ids,
        to,
        note
      })

      if (response.data.success) {
        // Update local state optimistically
        const updatedOrders = response.data.data
        updatedOrders.forEach((updatedOrder: Preorder) => {
          const index = preorders.value.findIndex(order => order.id === updatedOrder.id)
          if (index !== -1) {
            preorders.value[index] = { ...preorders.value[index], ...updatedOrder }
          }
        })
      }

      return response.data
    } catch (err: any) {
      const message = err.response?.data?.message || 'Erreur lors de la mise à jour en lot'
      error.value = message
      throw new Error(message)
    }
  }

  const bulkSendToShipping = async (ids: string[], mode: 'ramassage' | 'stock' = 'ramassage') => {
    try {
      const response = await axios.post('admin/preorders/bulk/send-to-shipping', {
        ids,
        mode
      })

      if (response.data.success) {
        // Update local state with the results
        const results = response.data.results || []
        const successCount = results.filter((r: any) => r.success).length
        const errorCount = results.filter((r: any) => !r.success).length

        // Refresh the list to get updated shipping status
        await fetchPreorders()

        // Return enhanced response with summary
        return {
          ...response.data,
          summary: {
            total: ids.length,
            success: successCount,
            errors: errorCount,
            results
          }
        }
      }

      return response.data
    } catch (err: any) {
      const message = err.response?.data?.message || 'Erreur lors de l\'envoi en lot'
      error.value = message
      throw new Error(message)
    }
  }

  const changeStatus = async (id: string, to: string, note?: string, increment?: boolean) => {
    try {
      const response = await axios.post(`admin/preorders/${id}/status`, {
        to,
        note,
        increment
      })

      if (response.data.success) {
        // Update local state
        const updatedOrder = response.data.data
        const index = preorders.value.findIndex(order => order.id === id)
        if (index !== -1) {
          preorders.value[index] = { ...preorders.value[index], ...updatedOrder }
        }

        // Update current preorder if it's the same
        if (currentPreorder.value?.id === id) {
          currentPreorder.value = { ...currentPreorder.value, ...updatedOrder }
        }
      }

      return response.data
    } catch (err: any) {
      const message = err.response?.data?.message || 'Erreur lors du changement de statut'
      error.value = message
      throw new Error(message)
    }
  }

  const incrementNoAnswer = async (id: string) => {
    try {
      const response = await axios.post(`admin/preorders/${id}/no-answer`)

      if (response.data.success) {
        // Update local state
        const index = preorders.value.findIndex(order => order.id === id)
        if (index !== -1) {
          preorders.value[index].no_answer_count = response.data.data.no_answer_count
        }

        // Update current preorder if it's the same
        if (currentPreorder.value?.id === id) {
          currentPreorder.value.no_answer_count = response.data.data.no_answer_count
        }
      }

      return response.data
    } catch (err: any) {
      const message = err.response?.data?.message || 'Erreur lors de l\'incrémentation'
      error.value = message
      throw new Error(message)
    }
  }

  const sendToShipping = async (id: string, mode: 'ramassage' | 'stock' = 'ramassage') => {
    try {
      const response = await axios.post(`admin/preorders/${id}/send-to-shipping`, {
        mode
      })

      if (response.data.success) {
        // Refresh the list to get updated shipping status
        await fetchPreorders()
      }

      return response.data
    } catch (err: any) {
      const message = err.response?.data?.message || 'Erreur lors de l\'envoi'
      error.value = message
      throw new Error(message)
    }
  }

  const moveToShippingLocal = async (id: string, note?: string) => {
    try {
      const response = await axios.post(`admin/preorders/${id}/move-to-shipping-local`, {
        note
      })

      if (response.data.success) {
        // Refresh the list to get updated shipping status
        await fetchPreorders()
      }

      return response.data
    } catch (err: any) {
      const message = err.response?.data?.message || 'Erreur lors du déplacement vers l\'expédition locale'
      error.value = message
      throw new Error(message)
    }
  }

  return {
    // State
    preorders,
    currentPreorder,
    loading,
    error,
    pagination,
    filters,

    // Getters
    hasPreorders,
    isLoading,

    // Actions
    fetchPreorders,
    fetchPreorder,
    updatePreorder,
    confirmPreorder,
    resetFilters,
    clearCurrentPreorder,

    // Bulk actions
    bulkChangeStatus,
    bulkSendToShipping,
    changeStatus,
    incrementNoAnswer,
    sendToShipping,
    moveToShippingLocal,
  }
})
