import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axios from '@/plugins/axios'

export interface UserApprovalFilters {
  q?: string
  approval_status?: string
  email_verified?: string
  has_affiliate_role?: string
  from?: string
  to?: string
  page?: number
  perPage?: number
  sort?: string
  dir?: string
}

export interface UserForApproval {
  id: string
  nom_complet: string
  email: string
  telephone?: string
  adresse?: string
  statut: string
  email_verifie: boolean
  email_verified_at?: string
  kyc_statut: string
  approval_status: string
  refusal_reason?: string
  created_at: string
  updated_at: string
  roles: Array<{
    id: string
    name: string
  }>
  orders_count?: number
  commissions_count?: number
  total_commissions?: number
}

export interface ApprovalStats {
  pending_approval: number
  email_not_verified: number
  approved_affiliates: number
  refused_applications: number
  recent_signups: number
}

export interface PaginationData {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export const useUsersApprovalStore = defineStore('usersApproval', () => {
  // State
  const users = ref<UserForApproval[]>([])
  const stats = ref<ApprovalStats | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)
  const pagination = ref<PaginationData>({
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 0,
  })

  const filters = ref<UserApprovalFilters>({
    page: 1,
    perPage: 15,
    sort: 'created_at',
    dir: 'desc',
  })

  // Getters
  const hasUsers = computed(() => users.value.length > 0)
  const isLoading = computed(() => loading.value)
  const hasStats = computed(() => stats.value !== null)

  // Actions
  const fetchUsers = async (newFilters?: Partial<UserApprovalFilters>) => {
    loading.value = true
    error.value = null

    try {
      if (newFilters) {
        Object.assign(filters.value, newFilters)
      }

      const response = await axios.get('/api/admin/users/approval-queue', {
        params: filters.value,
      })

      if (response.data.success) {
        users.value = response.data.data
        pagination.value = response.data.pagination
      } else {
        throw new Error(response.data.message || 'Failed to fetch users')
      }
    } catch (err: any) {
      error.value = err.response?.data?.message || err.message || 'Failed to fetch users'
      console.error('Error fetching users:', err)
    } finally {
      loading.value = false
    }
  }

  const fetchStats = async () => {
    loading.value = true
    error.value = null

    try {
      const response = await axios.get('/api/admin/users/approval-queue/stats')

      if (response.data.success) {
        stats.value = response.data.data
      } else {
        throw new Error(response.data.message || 'Failed to fetch stats')
      }
    } catch (err: any) {
      error.value = err.response?.data?.message || err.message || 'Failed to fetch stats'
      console.error('Error fetching stats:', err)
    } finally {
      loading.value = false
    }
  }

  const approveUser = async (id: string, reason?: string) => {
    loading.value = true
    error.value = null

    try {
      const response = await axios.post(`/api/admin/users/${id}/approve`, {
        reason,
      })

      if (response.data.success) {
        // Update user in list
        const index = users.value.findIndex(u => u.id === id)
        if (index !== -1) {
          users.value[index] = response.data.data
        }

        return response.data.data
      } else {
        throw new Error(response.data.message || 'Failed to approve user')
      }
    } catch (err: any) {
      error.value = err.response?.data?.message || err.message || 'Failed to approve user'
      console.error('Error approving user:', err)
      throw err
    } finally {
      loading.value = false
    }
  }

  const refuseUser = async (id: string, reason: string) => {
    loading.value = true
    error.value = null

    try {
      const response = await axios.post(`/api/admin/users/${id}/refuse`, {
        reason,
      })

      if (response.data.success) {
        // Update user in list
        const index = users.value.findIndex(u => u.id === id)
        if (index !== -1) {
          users.value[index] = response.data.data
        }

        return response.data.data
      } else {
        throw new Error(response.data.message || 'Failed to refuse user')
      }
    } catch (err: any) {
      error.value = err.response?.data?.message || err.message || 'Failed to refuse user'
      console.error('Error refusing user:', err)
      throw err
    } finally {
      loading.value = false
    }
  }

  const resendVerification = async (id: string) => {
    loading.value = true
    error.value = null

    try {
      const response = await axios.post(`/api/admin/users/${id}/resend-verification`)

      if (response.data.success) {
        return true
      } else {
        throw new Error(response.data.message || 'Failed to resend verification')
      }
    } catch (err: any) {
      error.value = err.response?.data?.message || err.message || 'Failed to resend verification'
      console.error('Error resending verification:', err)
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

  const clearError = () => {
    error.value = null
  }

  return {
    // State
    users,
    stats,
    loading,
    error,
    pagination,
    filters,

    // Getters
    hasUsers,
    isLoading,
    hasStats,

    // Actions
    fetchUsers,
    fetchStats,
    approveUser,
    refuseUser,
    resendVerification,
    resetFilters,
    clearError,
  }
})
