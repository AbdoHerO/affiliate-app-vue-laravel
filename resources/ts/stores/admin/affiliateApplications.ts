import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axios from '@/plugins/axios'

export interface AffiliateApplicationFilters {
  q?: string
  approval_status?: string
  email_verified?: string
  from?: string
  to?: string
  page?: number
  perPage?: number
  sort?: string
  dir?: string
}

export interface AffiliateApplication {
  id: string
  nom_complet: string
  email: string
  telephone?: string
  adresse?: string
  ville?: string
  pays?: string
  notes?: string
  approval_status: string
  refusal_reason?: string
  email_verified_at?: string
  created_at: string
  updated_at: string
}

export interface ApplicationStats {
  pending_approval: number
  email_not_verified: number
  approved_applications: number
  refused_applications: number
  recent_signups: number
}

export interface PaginationData {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export const useAffiliateApplicationsStore = defineStore('affiliateApplications', () => {
  // State
  const applications = ref<AffiliateApplication[]>([])
  const stats = ref<ApplicationStats>({
    pending_approval: 0,
    email_not_verified: 0,
    approved_applications: 0,
    refused_applications: 0,
    recent_signups: 0,
  })
  const loading = ref(false)
  const resendingIds = ref<Set<string>>(new Set())
  const error = ref<string | null>(null)
  const pagination = ref<PaginationData>({
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 0,
  })

  const filters = ref<AffiliateApplicationFilters>({
    page: 1,
    perPage: 15,
    sort: 'created_at',
    dir: 'desc',
  })

  // Getters
  const hasApplications = computed(() => applications.value.length > 0)
  const isLoading = computed(() => loading.value)
  const isResending = (id: string) => resendingIds.value.has(id)

  // Actions
  const fetchApplications = async (newFilters?: Partial<AffiliateApplicationFilters>) => {
    loading.value = true
    error.value = null

    try {
      if (newFilters) {
        Object.assign(filters.value, newFilters)
      }

      const response = await axios.get('/api/admin/affiliate-applications', {
        params: filters.value,
      })

      if (response.data.success) {
        applications.value = response.data.data
        pagination.value = response.data.pagination
      } else {
        throw new Error(response.data.message || 'Failed to fetch applications')
      }
    } catch (err: any) {
      error.value = err.response?.data?.message || err.message || 'Failed to fetch applications'
      console.error('Error fetching applications:', err)
    } finally {
      loading.value = false
    }
  }

  const fetchStats = async () => {
    try {
      const response = await axios.get('/api/admin/affiliate-applications/stats')
      
      if (response.data.success) {
        stats.value = response.data.data
      }
    } catch (err: any) {
      console.error('Error fetching stats:', err)
    }
  }

  const approveApplication = async (applicationId: string, reason?: string) => {
    try {
      const response = await axios.post(`/api/admin/affiliate-applications/${applicationId}/approve`, {
        reason,
      })

      if (response.data.success) {
        // Update the application in the list
        const index = applications.value.findIndex(app => app.id === applicationId)
        if (index !== -1) {
          applications.value[index].approval_status = 'approved'
        }
        
        // Refresh stats
        await fetchStats()
        
        return response.data
      } else {
        throw new Error(response.data.message || 'Failed to approve application')
      }
    } catch (err: any) {
      throw new Error(err.response?.data?.message || err.message || 'Failed to approve application')
    }
  }

  const refuseApplication = async (applicationId: string, reason: string) => {
    try {
      const response = await axios.post(`/api/admin/affiliate-applications/${applicationId}/refuse`, {
        reason,
      })

      if (response.data.success) {
        // Update the application in the list
        const index = applications.value.findIndex(app => app.id === applicationId)
        if (index !== -1) {
          applications.value[index].approval_status = 'refused'
          applications.value[index].refusal_reason = reason
        }
        
        // Refresh stats
        await fetchStats()
        
        return response.data
      } else {
        throw new Error(response.data.message || 'Failed to refuse application')
      }
    } catch (err: any) {
      throw new Error(err.response?.data?.message || err.message || 'Failed to refuse application')
    }
  }

  const resendVerification = async (applicationId: string) => {
    resendingIds.value.add(applicationId)

    try {
      const response = await axios.post(`/api/admin/affiliate-applications/${applicationId}/resend-verification`)

      if (response.data.success) {
        return response.data
      } else {
        throw new Error(response.data.message || 'Failed to resend verification')
      }
    } catch (err: any) {
      throw new Error(err.response?.data?.message || err.message || 'Failed to resend verification')
    } finally {
      resendingIds.value.delete(applicationId)
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

  return {
    // State
    applications,
    stats,
    loading,
    resendingIds,
    error,
    pagination,
    filters,

    // Getters
    hasApplications,
    isLoading,
    isResending,

    // Actions
    fetchApplications,
    fetchStats,
    approveApplication,
    refuseApplication,
    resendVerification,
    resetFilters,
  }
})
