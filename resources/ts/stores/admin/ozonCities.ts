import { defineStore } from 'pinia'
import { ref, computed, readonly } from 'vue'
import type { AxiosResponse } from 'axios'
import { $api } from '@/utils/api'

export interface ShippingCity {
  id: string
  provider: string
  city_id: string
  ref?: string
  name: string
  active: boolean
  prices?: {
    delivered?: number
    returned?: number
    refused?: number
  }
  meta?: Record<string, any>
  created_at: string
  updated_at: string
  deleted_at?: string | null
}

export interface CityFilters {
  q: string
  active: string
  include_deleted: string
  page: number
  per_page: number
}

export interface CityPagination {
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number
  to: number
}

export interface CityStats {
  total: number
  active: number
  inactive: number
}

export interface CitiesResponse {
  success: boolean
  data: {
    data: ShippingCity[]
    current_page: number
    last_page: number
    per_page: number
    total: number
    from: number
    to: number
  }
  stats: CityStats
  message?: string
  errors?: Record<string, string[]>
}

export interface CityResponse {
  success: boolean
  data: ShippingCity
  message?: string
  errors?: Record<string, string[]>
}

export interface ImportResponse {
  success: boolean
  message: string
  data: {
    imported: number
    updated: number
    skipped: number
  }
}

export const useOzonCitiesStore = defineStore('ozonCities', () => {
  // State
  const cities = ref<ShippingCity[]>([])
  const currentCity = ref<ShippingCity | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)
  const stats = ref<CityStats>({
    total: 0,
    active: 0,
    inactive: 0,
  })
  
  const pagination = ref<CityPagination>({
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 0,
    from: 0,
    to: 0,
  })
  
  const filters = ref<CityFilters>({
    q: '',
    active: '',
    include_deleted: 'active',
    page: 1,
    per_page: 15,
  })

  // Actions
  const fetchCities = async (): Promise<void> => {
    loading.value = true
    error.value = null

    try {
      const params = new URLSearchParams()
      if (filters.value.q) params.append('q', filters.value.q)
      if (filters.value.active !== '') params.append('active', filters.value.active)
      params.append('page', filters.value.page.toString())
      params.append('per_page', filters.value.per_page.toString())

      const response = await $api(`/admin/integrations/ozon/cities?${params}`, {
        method: 'GET',
      })

      console.log('Cities API Response:', response)

      if (response.success) {
        const paginationData = response.data
        cities.value = paginationData.data || []
        pagination.value = {
          current_page: paginationData.current_page || 1,
          last_page: paginationData.last_page || 1,
          per_page: paginationData.per_page || 15,
          total: paginationData.total || 0,
          from: paginationData.from || 0,
          to: paginationData.to || 0,
        }
        stats.value = response.stats || { total: 0, active: 0, inactive: 0 }
      } else {
        error.value = response.message || 'Failed to fetch cities'
      }
    } catch (err: any) {
      error.value = err.response?.data?.message || err.message || 'Failed to fetch cities'
      console.error('Error fetching cities:', err)
    } finally {
      loading.value = false
    }
  }

  const fetchCity = async (id: string): Promise<void> => {
    loading.value = true
    error.value = null
    
    try {
      const response: AxiosResponse<CityResponse> = await $api(`/admin/integrations/ozon/cities/${id}`, {
        method: 'GET',
      })
      
      if (response.data.success) {
        currentCity.value = response.data.data
      } else {
        error.value = response.data.message || 'Failed to fetch city'
      }
    } catch (err: any) {
      error.value = err.response?.data?.message || err.message || 'Failed to fetch city'
      console.error('Error fetching city:', err)
    } finally {
      loading.value = false
    }
  }

  const createCity = async (data: Partial<ShippingCity>): Promise<boolean> => {
    loading.value = true
    error.value = null

    try {
      console.log('Creating city with data:', data)

      const response = await $api('/admin/integrations/ozon/cities', {
        method: 'POST',
        body: data,
      })

      console.log('Create city response:', response)

      if (response.success) {
        // Refresh the list
        await fetchCities()
        return true
      } else {
        error.value = response.message || 'Failed to create city'
        return false
      }
    } catch (err: any) {
      console.error('Error creating city:', err)
      if (err.response?.status === 422) {
        const errors = err.response.data.errors
        if (errors) {
          const errorMessages = Object.values(errors).flat()
          error.value = errorMessages.join(', ')
        } else {
          error.value = err.response.data.message || 'Validation failed'
        }
      } else {
        error.value = err.response?.data?.message || err.message || 'Failed to create city'
      }
      return false
    } finally {
      loading.value = false
    }
  }

  const updateCity = async (id: string, data: Partial<ShippingCity>): Promise<boolean> => {
    loading.value = true
    error.value = null

    try {
      console.log('Updating city with data:', data)

      const response = await $api(`/admin/integrations/ozon/cities/${id}`, {
        method: 'PUT',
        body: data,
      })

      console.log('Update city response:', response)

      if (response.success) {
        // Update the current city if it's the one being edited
        if (currentCity.value?.id === id) {
          currentCity.value = response.data
        }
        // Refresh the list
        await fetchCities()
        return true
      } else {
        error.value = response.message || 'Failed to update city'
        return false
      }
    } catch (err: any) {
      console.error('Error updating city:', err)
      if (err.response?.status === 422) {
        const errors = err.response.data.errors
        if (errors) {
          const errorMessages = Object.values(errors).flat()
          error.value = errorMessages.join(', ')
        } else {
          error.value = err.response.data.message || 'Validation failed'
        }
      } else {
        error.value = err.response?.data?.message || err.message || 'Failed to update city'
      }
      return false
    } finally {
      loading.value = false
    }
  }

  const deleteCity = async (id: string): Promise<boolean> => {
    loading.value = true
    error.value = null

    try {
      console.log('Deleting city with id:', id)

      const response = await $api(`/admin/integrations/ozon/cities/${id}`, {
        method: 'DELETE',
      })

      console.log('Delete city response:', response)

      if (response && response.success) {
        // Clear current city if it's the one being deleted
        if (currentCity.value?.id === id) {
          currentCity.value = null
        }
        // Refresh the list
        await fetchCities()
        return true
      } else {
        error.value = response?.message || 'Failed to delete city'
        return false
      }
    } catch (err: any) {
      console.error('Error deleting city:', err)
      error.value = err.response?.data?.message || err.message || 'Failed to delete city'
      return false
    } finally {
      loading.value = false
    }
  }

  const importCities = async (file: File): Promise<boolean> => {
    loading.value = true
    error.value = null
    
    try {
      const formData = new FormData()
      formData.append('file', file)
      
      const response: AxiosResponse<ImportResponse> = await $api('/admin/integrations/ozon/cities/import', {
        method: 'POST',
        data: formData,
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      })
      
      if (response.data.success) {
        // Refresh the list after import
        await fetchCities()
        return true
      } else {
        error.value = response.data.message || 'Failed to import cities'
        return false
      }
    } catch (err: any) {
      if (err.response?.status === 422) {
        const errors = err.response.data.errors
        if (errors) {
          const errorMessages = Object.values(errors).flat()
          error.value = errorMessages.join(', ')
        } else {
          error.value = err.response.data.message || 'Validation failed'
        }
      } else {
        error.value = err.response?.data?.message || err.message || 'Failed to import cities'
      }
      console.error('Error importing cities:', err)
      return false
    } finally {
      loading.value = false
    }
  }

  const setFilters = (newFilters: Partial<CityFilters>): void => {
    filters.value = { ...filters.value, ...newFilters }
    // Reset to first page when filters change (except when changing page)
    if (!('page' in newFilters)) {
      filters.value.page = 1
    }
  }

  const resetFilters = (): void => {
    filters.value = {
      q: '',
      active: '',
      page: 1,
      per_page: 15,
    }
  }

  const clearError = (): void => {
    error.value = null
  }

  const clearCurrentCity = (): void => {
    currentCity.value = null
  }

  const reset = (): void => {
    cities.value = []
    currentCity.value = null
    error.value = null
    loading.value = false
    stats.value = { total: 0, active: 0, inactive: 0 }
    pagination.value = {
      current_page: 1,
      last_page: 1,
      per_page: 15,
      total: 0,
      from: 0,
      to: 0,
    }
    resetFilters()
  }

  // Getters
  const hasFilters = computed(() => {
    return !!(filters.value.q || filters.value.active !== '')
  })

  const totalPages = computed(() => pagination.value.last_page)
  const currentPage = computed(() => pagination.value.current_page)
  const totalItems = computed(() => pagination.value.total)

  return {
    // State
    cities: readonly(cities),
    currentCity: readonly(currentCity),
    loading: readonly(loading),
    error: readonly(error),
    stats: readonly(stats),
    pagination: readonly(pagination),
    filters: readonly(filters),
    
    // Getters
    hasFilters,
    totalPages,
    currentPage,
    totalItems,
    
    // Actions
    fetchCities,
    fetchCity,
    createCity,
    updateCity,
    deleteCity,
    importCities,
    setFilters,
    resetFilters,
    clearError,
    clearCurrentCity,
    reset,
  }
})
