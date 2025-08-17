import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axios from '@/plugins/axios'

export interface User {
  id: string
  nom_complet: string
  email: string
  telephone?: string
  adresse?: string
  statut: string
  email_verifie: boolean
  email_verified_at?: string
  kyc_statut: string
  created_at: string
  updated_at: string
  roles: Array<{
    id: string
    name: string
  }>
  photo_profil?: string
  rib?: string
  bank_type?: string
}

export interface UserFilters {
  q?: string
  search?: string
  role?: string
  statut?: string
  page?: number
  per_page?: number
  sort?: string
  dir?: string
  include_deleted?: string
}

export interface PaginationData {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export const useUsersStore = defineStore('users', () => {
  // State
  const users = ref<User[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)
  const pagination = ref<PaginationData>({
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 0,
  })

  const filters = ref<UserFilters>({
    page: 1,
    per_page: 15,
    sort: 'created_at',
    dir: 'desc',
  })

  // Getters
  const totalUsers = computed(() => pagination.value.total)
  const hasUsers = computed(() => users.value.length > 0)

  // Actions
  const fetchUsers = async (params: UserFilters = {}) => {
    try {
      loading.value = true
      error.value = null

      const queryParams = {
        ...filters.value,
        ...params,
      }

      const response = await axios.get('/admin/users', { params: queryParams })
      
      if (response.data.success) {
        users.value = response.data.data.data || []
        pagination.value = {
          current_page: response.data.data.current_page || 1,
          last_page: response.data.data.last_page || 1,
          per_page: response.data.data.per_page || 15,
          total: response.data.data.total || 0,
        }
        
        // Update filters with current params
        Object.assign(filters.value, queryParams)
      } else {
        throw new Error(response.data.message || 'Failed to fetch users')
      }
    } catch (err: any) {
      error.value = err.message || 'Failed to fetch users'
      console.error('Error fetching users:', err)
      users.value = []
    } finally {
      loading.value = false
    }
  }

  const searchUsers = async (query: string) => {
    try {
      const response = await axios.get('/admin/users', {
        params: {
          search: query,
          per_page: 50, // Get more results for search
        }
      })
      
      if (response.data.success) {
        return response.data.data.data || []
      } else {
        throw new Error(response.data.message || 'Failed to search users')
      }
    } catch (err: any) {
      console.error('Error searching users:', err)
      return []
    }
  }

  const getUserById = async (id: string) => {
    try {
      const response = await axios.get(`/admin/users/${id}`)
      
      if (response.data.success) {
        return response.data.data
      } else {
        throw new Error(response.data.message || 'Failed to fetch user')
      }
    } catch (err: any) {
      console.error('Error fetching user:', err)
      throw err
    }
  }

  const createUser = async (userData: Partial<User>) => {
    try {
      loading.value = true
      error.value = null

      const response = await axios.post('/admin/users', userData)
      
      if (response.data.success) {
        // Refresh users list
        await fetchUsers({ page: pagination.value.current_page })
        return response.data
      } else {
        throw new Error(response.data.message || 'Failed to create user')
      }
    } catch (err: any) {
      error.value = err.message || 'Failed to create user'
      throw err
    } finally {
      loading.value = false
    }
  }

  const updateUser = async (id: string, userData: Partial<User>) => {
    try {
      loading.value = true
      error.value = null

      const response = await axios.put(`/admin/users/${id}`, userData)
      
      if (response.data.success) {
        // Refresh users list
        await fetchUsers({ page: pagination.value.current_page })
        return response.data
      } else {
        throw new Error(response.data.message || 'Failed to update user')
      }
    } catch (err: any) {
      error.value = err.message || 'Failed to update user'
      throw err
    } finally {
      loading.value = false
    }
  }

  const deleteUser = async (id: string) => {
    try {
      loading.value = true
      error.value = null

      const response = await axios.delete(`/admin/users/${id}`)
      
      if (response.data.success) {
        // Refresh users list
        await fetchUsers({ page: pagination.value.current_page })
        return response.data
      } else {
        throw new Error(response.data.message || 'Failed to delete user')
      }
    } catch (err: any) {
      error.value = err.message || 'Failed to delete user'
      throw err
    } finally {
      loading.value = false
    }
  }

  const toggleUserStatus = async (id: string) => {
    try {
      const response = await axios.post(`/admin/users/${id}/toggle-status`)
      
      if (response.data.success) {
        // Refresh users list
        await fetchUsers({ page: pagination.value.current_page })
        return response.data
      } else {
        throw new Error(response.data.message || 'Failed to toggle user status')
      }
    } catch (err: any) {
      console.error('Error toggling user status:', err)
      throw err
    }
  }

  const restoreUser = async (id: string) => {
    try {
      const response = await axios.post(`/admin/users/${id}/restore`)
      
      if (response.data.success) {
        // Refresh users list
        await fetchUsers({ page: pagination.value.current_page })
        return response.data
      } else {
        throw new Error(response.data.message || 'Failed to restore user')
      }
    } catch (err: any) {
      console.error('Error restoring user:', err)
      throw err
    }
  }

  const forceDeleteUser = async (id: string) => {
    try {
      const response = await axios.delete(`/admin/users/${id}/force`)
      
      if (response.data.success) {
        // Refresh users list
        await fetchUsers({ page: pagination.value.current_page })
        return response.data
      } else {
        throw new Error(response.data.message || 'Failed to permanently delete user')
      }
    } catch (err: any) {
      console.error('Error force deleting user:', err)
      throw err
    }
  }

  // Reset state
  const resetState = () => {
    users.value = []
    loading.value = false
    error.value = null
    pagination.value = {
      current_page: 1,
      last_page: 1,
      per_page: 15,
      total: 0,
    }
    filters.value = {
      page: 1,
      per_page: 15,
      sort: 'created_at',
      dir: 'desc',
    }
  }

  return {
    // State
    users,
    loading,
    error,
    pagination,
    filters,
    
    // Getters
    totalUsers,
    hasUsers,
    
    // Actions
    fetchUsers,
    searchUsers,
    getUserById,
    createUser,
    updateUser,
    deleteUser,
    toggleUserStatus,
    restoreUser,
    forceDeleteUser,
    resetState,
  }
})
