import { defineStore } from 'pinia'
import { useApi } from '@/composables/useApi'

export interface Category {
  id: string
  nom: string
  slug: string
  image_url: string | null
  ordre: number
  actif: boolean
}

export interface CategoryFormData {
  nom: string
  slug?: string
  description?: string
  image?: File | null
  image_url?: string | null
  ordre?: number
  actif?: boolean
}

interface CategoryState {
  categories: Category[]
  currentCategory: Category | null
  loading: boolean
  error: string | null
  pagination: {
    current_page: number
    per_page: number
    total: number
    last_page: number
  }
}

export const useCategoriesStore = defineStore('categories', {
  state: (): CategoryState => ({
    categories: [],
    currentCategory: null,
    loading: false,
    error: null,
    pagination: {
      current_page: 1,
      per_page: 15,
      total: 0,
      last_page: 1,
    },
  }),

  getters: {
    activeCategories: (state) => state.categories.filter(cat => cat.actif),
    inactiveCategories: (state) => state.categories.filter(cat => !cat.actif),
    totalCategories: (state) => state.categories.length,
    getCategoryById: (state) => (id: string) => 
      state.categories.find(cat => cat.id === id),
  },

  actions: {
    async fetchCategories(params: {
      search?: string
      status?: boolean | string
      sort_by?: string
      sort_direction?: string
      per_page?: number
      page?: number
    } = {}) {
      this.loading = true
      this.error = null

      try {
        // Build URL with query parameters
        const searchParams = new URLSearchParams()
        Object.entries(params).forEach(([key, value]) => {
          if (value !== '' && value !== null && value !== undefined) {
            searchParams.append(key, String(value))
          }
        })

        const url = `/admin/categories${searchParams.toString() ? `?${searchParams.toString()}` : ''}`
        const { data: responseData, error: apiError } = await useApi(url)

        if (apiError.value) {
          const message = (apiError.value as any).message || 'Error fetching categories'
          this.error = message
          throw apiError.value
        }

        const response = responseData.value as any
        if (response.success) {
          this.categories = response.data
          this.pagination = response.pagination
        } else {
          this.error = response.message
        }
      } catch (error: any) {
        this.error = error.message || 'Error fetching categories'
        console.error('Error fetching categories:', error)
      } finally {
        this.loading = false
      }
    },

    async fetchCategory(id: string) {
      this.loading = true
      this.error = null

      try {
        const { data: responseData, error: apiError } = await useApi(`/admin/categories/${id}`)

        if (apiError.value) {
          const message = (apiError.value as any).message || 'Error fetching category'
          this.error = message
          throw apiError.value
        }

        const response = responseData.value as any
        if (response.success) {
          this.currentCategory = response.data
          return response.data
        } else {
          this.error = response.message
          throw new Error(response.message)
        }
      } catch (error: any) {
        this.error = error.message || 'Error fetching category'
        console.error('Error fetching category:', error)
        throw error
      } finally {
        this.loading = false
      }
    },

    async createCategory(data: CategoryFormData) {
      this.loading = true
      this.error = null

      try {
        // Create FormData for file upload
        const formData = new FormData()
        formData.append('nom', data.nom)
        if (data.slug) formData.append('slug', data.slug)
        if (data.description) formData.append('description', data.description)
        if (data.ordre !== undefined) formData.append('ordre', String(data.ordre))
        formData.append('actif', data.actif ? '1' : '0')
        if (data.image) formData.append('image', data.image)

        const { data: responseData, error: apiError } = await useApi('/admin/categories', {
          method: 'POST',
          body: formData
        })

        if (apiError.value) {
          const message = (apiError.value as any).message || 'Error creating category'
          this.error = message
          throw apiError.value
        }

        const response = responseData.value as any

        if (response.success) {
          // Add the new category to the list
          this.categories.unshift(response.data)
          this.pagination.total++
          return response.data
        } else {
          this.error = response.message
          throw new Error(response.message)
        }
      } catch (error: any) {
        this.error = error.message || 'Error creating category'
        console.error('Error creating category:', error)
        throw error
      } finally {
        this.loading = false
      }
    },

    async updateCategory(id: string, data: Partial<CategoryFormData>) {
      this.loading = true
      this.error = null

      try {
        // Create FormData for file upload
        const formData = new FormData()
        if (data.nom) formData.append('nom', data.nom)
        if (data.slug) formData.append('slug', data.slug)
        if (data.description) formData.append('description', data.description)
        if (data.ordre !== undefined) formData.append('ordre', String(data.ordre))
        if (data.actif !== undefined) formData.append('actif', data.actif ? '1' : '0')
        if (data.image) formData.append('image', data.image)

        // Add _method for Laravel to handle PUT with FormData
        formData.append('_method', 'PUT')

        const { data: responseData, error: apiError } = await useApi(`/admin/categories/${id}`, {
          method: 'POST', // Use POST with _method=PUT for FormData
          body: formData
        })

        if (apiError.value) {
          const message = (apiError.value as any).message || 'Error updating category'
          this.error = message
          throw apiError.value
        }

        const response = responseData.value as any

        if (response.success) {
          // Update the category in the list
          const index = this.categories.findIndex(cat => cat.id === id)
          if (index !== -1) {
            this.categories[index] = response.data
          }
          
          if (this.currentCategory?.id === id) {
            this.currentCategory = response.data
          }
          
          return response.data
        } else {
          this.error = response.message
          throw new Error(response.message)
        }
      } catch (error: any) {
        this.error = error.message || 'Error updating category'
        console.error('Error updating category:', error)
        throw error
      } finally {
        this.loading = false
      }
    },

    async deleteCategory(id: string) {
      this.loading = true
      this.error = null

      try {
        const { error: apiError } = await useApi(`/admin/categories/${id}`, {
          method: 'DELETE'
        })

        if (apiError.value) {
          const message = (apiError.value as any).message || 'Error deleting category'
          this.error = message
          throw apiError.value
        }

        // Remove the category from the list
        this.categories = this.categories.filter(cat => cat.id !== id)
        this.pagination.total--

        if (this.currentCategory?.id === id) {
          this.currentCategory = null
        }
      } catch (error: any) {
        this.error = error.message || 'Error deleting category'
        console.error('Error deleting category:', error)
        throw error
      } finally {
        this.loading = false
      }
    },

    async toggleCategoryStatus(id: string) {
      this.loading = true
      this.error = null

      try {
        const { data: responseData, error: apiError } = await useApi(`/admin/categories/${id}/toggle-status`, {
          method: 'POST'
        })

        if (apiError.value) {
          const message = (apiError.value as any).message || 'Error toggling category status'
          this.error = message
          throw apiError.value
        }

        const response = responseData.value as any

        if (response.success) {
          // Update the category in the list
          const index = this.categories.findIndex(cat => cat.id === id)
          if (index !== -1) {
            this.categories[index] = response.data
          }
          
          if (this.currentCategory?.id === id) {
            this.currentCategory = response.data
          }
          
          return response.data
        } else {
          this.error = response.message
          throw new Error(response.message)
        }
      } catch (error: any) {
        this.error = error.message || 'Error toggling category status'
        console.error('Error toggling category status:', error)
        throw error
      } finally {
        this.loading = false
      }
    },

    clearError() {
      this.error = null
    },

    clearCurrentCategory() {
      this.currentCategory = null
    },

    resetPagination() {
      this.pagination = {
        current_page: 1,
        per_page: 15,
        total: 0,
        last_page: 1,
      }
    },
  },
})
