import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { useApi } from '@/composables/useApi'
import { useNotifications } from '@/composables/useNotifications'

export interface Boutique {
  id: string
  nom: string
  slug: string
  statut: 'actif' | 'suspendu' | 'desactive'
  commission_par_defaut: number
  email_pro?: string
  adresse?: string
  proprietaire: {
    id: string
    nom_complet: string
    email: string
  }
  created_at: string
  updated_at: string
}

export interface BoutiqueFilters {
  q?: string
  statut?: string
  sort?: string
  dir?: 'asc' | 'desc'
  page?: number
  per_page?: number
}

export interface BoutiquePagination {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export interface BoutiqueFormData {
  nom: string
  slug?: string
  proprietaire_id: string
  email_pro?: string
  adresse?: string
  statut: 'actif' | 'suspendu' | 'desactive'
  commission_par_defaut?: number
}

export const useBoutiquesStore = defineStore('admin-boutiques', () => {
  // State
  const items = ref<Boutique[]>([])
  const currentItem = ref<Boutique | null>(null)
  const pagination = ref<BoutiquePagination>({
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 0,
  })
  const filters = ref<BoutiqueFilters>({
    q: '',
    statut: '',
    sort: 'created_at',
    dir: 'desc',
    page: 1,
    per_page: 15,
  })
  const isLoading = ref(false)
  const error = ref<string | null>(null)

  // Getters
  const hasItems = computed(() => items.value.length > 0)
  const totalItems = computed(() => pagination.value.total)

  // Actions
  const { showSuccess, showError } = useNotifications()

  const setError = (message: string) => {
    error.value = message
    showError(message)
  }

  const clearError = () => {
    error.value = null
  }

  const setFilters = (newFilters: Partial<BoutiqueFilters>) => {
    filters.value = { ...filters.value, ...newFilters }
  }

  const resetFilters = () => {
    filters.value = {
      q: '',
      statut: '',
      sort: 'created_at',
      dir: 'desc',
      page: 1,
      per_page: 15,
    }
  }

  const fetchBoutiques = async (params?: Partial<BoutiqueFilters>) => {
    isLoading.value = true
    clearError()

    try {
      const queryParams = { ...filters.value, ...params }
      
      // Build URL with query parameters
      const searchParams = new URLSearchParams()
      Object.entries(queryParams).forEach(([key, value]) => {
        if (value !== '' && value !== null && value !== undefined) {
          searchParams.append(key, String(value))
        }
      })
      
      const url = `/admin/boutiques${searchParams.toString() ? `?${searchParams.toString()}` : ''}`
      const { data, error: apiError } = await useApi(url)

      if (apiError.value) {
        const message = (apiError.value as any).message || 'Error fetching boutiques'
        setError(message)
        throw apiError.value
      } else if (data.value) {
        const responseData = data.value as any
        items.value = responseData.data || []
        
        // Update pagination from meta
        if (responseData.meta) {
          pagination.value = responseData.meta
        }

        // Update filters to reflect actual query
        setFilters(queryParams)
      }
    } catch (err: any) {
      console.error('Error fetching boutiques:', err)
      if (!error.value) {
        setError('Error fetching boutiques')
      }
    } finally {
      isLoading.value = false
    }
  }

  const fetchBoutique = async (id: string) => {
    isLoading.value = true
    clearError()

    try {
      const { data, error: apiError } = await useApi(`/admin/boutiques/${id}`)

      if (apiError.value) {
        const message = (apiError.value as any).message || 'Error fetching boutique'
        setError(message)
        throw apiError.value
      } else if (data.value) {
        const responseData = data.value as any
        const boutique = responseData.data || responseData
        currentItem.value = boutique
        return boutique
      }
    } catch (err: any) {
      console.error('Error fetching boutique:', err)
      if (!error.value) {
        setError('Error fetching boutique')
      }
      throw err
    } finally {
      isLoading.value = false
    }
  }

  const create = async (data: BoutiqueFormData) => {
    isLoading.value = true
    clearError()

    try {
      const { data: responseData, error: apiError } = await useApi('/admin/boutiques', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
      })

      if (apiError.value) {
        const message = (apiError.value as any).message || 'Error creating boutique'
        setError(message)

        // Handle validation errors
        if ((apiError.value as any).status === 422) {
          throw apiError.value
        }
        throw apiError.value
      } else if (responseData.value) {
        const newBoutique = (responseData.value as any).data || responseData.value

        // Add to items if we're on the first page
        if (pagination.value.current_page === 1) {
          items.value.unshift(newBoutique)
        }

        showSuccess('Boutique created successfully')

        return newBoutique
      }
    } catch (err: any) {
      console.error('Error creating boutique:', err)
      throw err
    } finally {
      isLoading.value = false
    }
  }

  const update = async (id: string, data: Partial<BoutiqueFormData>) => {
    isLoading.value = true
    clearError()

    try {
      const { data: responseData, error: apiError } = await useApi(`/admin/boutiques/${id}`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
      })

      if (apiError.value) {
        const message = (apiError.value as any).message || 'Error updating boutique'
        setError(message)

        // Handle validation errors
        if ((apiError.value as any).status === 422) {
          throw apiError.value
        }
        throw apiError.value
      } else if (responseData.value) {
        const updatedBoutique = (responseData.value as any).data || responseData.value

        // Update in items list
        const index = items.value.findIndex(item => item.id === id)
        if (index !== -1) {
          items.value[index] = updatedBoutique
        }

        // Update current item if it's the same
        if (currentItem.value?.id === id) {
          currentItem.value = updatedBoutique
        }

        showSuccess('Boutique updated successfully')

        return updatedBoutique
      }
    } catch (err: any) {
      console.error('Error updating boutique:', err)
      throw err
    } finally {
      isLoading.value = false
    }
  }

  const destroy = async (id: string) => {
    isLoading.value = true
    clearError()

    try {
      const { error: apiError } = await useApi(`/admin/boutiques/${id}`, {
        method: 'DELETE'
      })

      if (apiError.value) {
        if ((apiError.value as any).status === 409) {
          // Conflict - related records prevent deletion
          const message = (apiError.value as any).data?.message || 'Cannot delete boutique: related records exist'
          setError(message)
          throw apiError.value
        } else {
          const message = (apiError.value as any).message || 'Error deleting boutique'
          setError(message)
          throw apiError.value
        }
      } else {
        // Remove from items list
        const index = items.value.findIndex(item => item.id === id)
        if (index !== -1) {
          items.value.splice(index, 1)
        }

        // Update pagination total
        if (pagination.value.total > 0) {
          pagination.value.total--
        }

        showSuccess('Boutique deleted successfully')
      }
    } catch (err: any) {
      console.error('Error deleting boutique:', err)
      throw err
    } finally {
      isLoading.value = false
    }
  }

  // Helper methods
  const getBoutiqueById = (id: string) => {
    return items.value.find(item => item.id === id)
  }

  const getStatusBadgeColor = (status: string) => {
    switch (status) {
      case 'actif': return 'success'
      case 'suspendu': return 'warning'  
      case 'desactive': return 'error'
      default: return 'default'
    }
  }

  return {
    // State
    items,
    currentItem,
    pagination,
    filters,
    isLoading,
    error,

    // Getters
    hasItems,
    totalItems,

    // Actions
    fetchBoutiques,
    fetchBoutique,
    create,
    update,
    destroy,
    setFilters,
    resetFilters,
    getBoutiqueById,
    getStatusBadgeColor,
  }
})
