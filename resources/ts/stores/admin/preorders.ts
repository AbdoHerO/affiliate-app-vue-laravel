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

      const response = await axios.get('/api/admin/preorders', {
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
      const response = await axios.get(`/api/admin/preorders/${id}`)

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
      const response = await axios.put(`/api/admin/preorders/${id}`, data)

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
      const response = await axios.post(`/api/admin/preorders/${id}/confirm`)

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
  }
})
