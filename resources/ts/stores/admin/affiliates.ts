import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axios from '@/plugins/axios'

export interface AffiliateFilters {
  q?: string
  statut?: string
  affiliate_status?: string
  gamme_id?: string
  kyc_statut?: string
  from?: string
  to?: string
  page?: number
  perPage?: number
  sort?: string
  dir?: string
}

export interface AffiliateTier {
  id: string
  code: string
  libelle: string
  actif?: boolean
}

export interface AffiliateProfile {
  id: string
  utilisateur_id: string
  gamme_id: string
  points: number
  statut: string
  rib?: string
  notes_interne?: string
  created_at: string
  updated_at: string
  gamme: AffiliateTier
}

export interface Affiliate {
  id: string
  nom_complet: string
  email: string
  telephone?: string
  adresse?: string
  statut: string
  email_verifie: boolean
  kyc_statut: string
  created_at: string
  updated_at: string
  profil_affilie: AffiliateProfile
  orders_count?: number
  commissions_count?: number
  total_commissions?: number
  kyc_documents?: Array<{
    utilisateur_id: string
    statut: string
  }>
}

export interface AffiliatePerformance {
  orders: {
    total: number
    this_month: number
    by_status: Record<string, number>
  }
  commissions: {
    total: number
    this_month: number
    by_status: Record<string, number>
  }
  payments: {
    total_paid: number
    pending: number
  }
}

export interface PaginationData {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export const useAffiliatesStore = defineStore('affiliates', () => {
  // State
  const affiliates = ref<Affiliate[]>([])
  const currentAffiliate = ref<Affiliate | null>(null)
  const affiliatePerformance = ref<AffiliatePerformance | null>(null)
  const affiliateTiers = ref<AffiliateTier[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)
  const pagination = ref<PaginationData>({
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 0,
  })

  const filters = ref<AffiliateFilters>({
    page: 1,
    perPage: 15,
    sort: 'created_at',
    dir: 'desc',
  })

  // Getters
  const hasAffiliates = computed(() => affiliates.value.length > 0)
  const isLoading = computed(() => loading.value)
  const hasTiers = computed(() => affiliateTiers.value.length > 0)

  // Actions
  const fetchAffiliates = async (newFilters?: Partial<AffiliateFilters>) => {
    loading.value = true
    error.value = null

    try {
      if (newFilters) {
        Object.assign(filters.value, newFilters)
      }

      const response = await axios.get('/api/admin/affiliates', {
        params: filters.value,
      })

      if (response.data.success) {
        affiliates.value = response.data.data
        pagination.value = response.data.pagination
      } else {
        throw new Error(response.data.message || 'Failed to fetch affiliates')
      }
    } catch (err: any) {
      error.value = err.response?.data?.message || err.message || 'Failed to fetch affiliates'
      console.error('Error fetching affiliates:', err)
    } finally {
      loading.value = false
    }
  }

  const fetchAffiliate = async (id: string) => {
    loading.value = true
    error.value = null

    try {
      const response = await axios.get(`/api/admin/affiliates/${id}`)

      if (response.data.success) {
        currentAffiliate.value = response.data.data
      } else {
        throw new Error(response.data.message || 'Failed to fetch affiliate')
      }
    } catch (err: any) {
      error.value = err.response?.data?.message || err.message || 'Failed to fetch affiliate'
      console.error('Error fetching affiliate:', err)
    } finally {
      loading.value = false
    }
  }

  const updateAffiliate = async (id: string, data: Partial<Affiliate>) => {
    loading.value = true
    error.value = null

    try {
      const response = await axios.put(`/api/admin/affiliates/${id}`, data)

      if (response.data.success) {
        currentAffiliate.value = response.data.data
        
        // Update in list if present
        const index = affiliates.value.findIndex(a => a.id === id)
        if (index !== -1) {
          affiliates.value[index] = response.data.data
        }

        return response.data.data
      } else {
        throw new Error(response.data.message || 'Failed to update affiliate')
      }
    } catch (err: any) {
      error.value = err.response?.data?.message || err.message || 'Failed to update affiliate'
      console.error('Error updating affiliate:', err)
      throw err
    } finally {
      loading.value = false
    }
  }

  const toggleBlock = async (id: string, action: 'block' | 'unblock', reason?: string) => {
    loading.value = true
    error.value = null

    try {
      const response = await axios.post(`/api/admin/affiliates/${id}/toggle-block`, {
        action,
        reason,
      })

      if (response.data.success) {
        currentAffiliate.value = response.data.data
        
        // Update in list if present
        const index = affiliates.value.findIndex(a => a.id === id)
        if (index !== -1) {
          affiliates.value[index] = response.data.data
        }

        return response.data.data
      } else {
        throw new Error(response.data.message || 'Failed to toggle affiliate status')
      }
    } catch (err: any) {
      error.value = err.response?.data?.message || err.message || 'Failed to toggle affiliate status'
      console.error('Error toggling affiliate status:', err)
      throw err
    } finally {
      loading.value = false
    }
  }

  const changeTier = async (id: string, gammeId: string, reason?: string) => {
    loading.value = true
    error.value = null

    try {
      const response = await axios.post(`/api/admin/affiliates/${id}/change-tier`, {
        gamme_id: gammeId,
        reason,
      })

      if (response.data.success) {
        currentAffiliate.value = response.data.data
        
        // Update in list if present
        const index = affiliates.value.findIndex(a => a.id === id)
        if (index !== -1) {
          affiliates.value[index] = response.data.data
        }

        return response.data.data
      } else {
        throw new Error(response.data.message || 'Failed to change affiliate tier')
      }
    } catch (err: any) {
      error.value = err.response?.data?.message || err.message || 'Failed to change affiliate tier'
      console.error('Error changing affiliate tier:', err)
      throw err
    } finally {
      loading.value = false
    }
  }

  const fetchPerformance = async (id: string) => {
    loading.value = true
    error.value = null

    try {
      const response = await axios.get(`/api/admin/affiliates/${id}/performance`)

      if (response.data.success) {
        affiliatePerformance.value = response.data.data
      } else {
        throw new Error(response.data.message || 'Failed to fetch performance')
      }
    } catch (err: any) {
      error.value = err.response?.data?.message || err.message || 'Failed to fetch performance'
      console.error('Error fetching performance:', err)
    } finally {
      loading.value = false
    }
  }

  const fetchTiers = async () => {
    loading.value = true
    error.value = null

    try {
      const response = await axios.get('/api/admin/affiliate-tiers')

      if (response.data.success) {
        affiliateTiers.value = response.data.data
      } else {
        throw new Error(response.data.message || 'Failed to fetch tiers')
      }
    } catch (err: any) {
      error.value = err.response?.data?.message || err.message || 'Failed to fetch tiers'
      console.error('Error fetching tiers:', err)
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

  const clearCurrentAffiliate = () => {
    currentAffiliate.value = null
    affiliatePerformance.value = null
  }

  return {
    // State
    affiliates,
    currentAffiliate,
    affiliatePerformance,
    affiliateTiers,
    loading,
    error,
    pagination,
    filters,

    // Getters
    hasAffiliates,
    isLoading,
    hasTiers,

    // Actions
    fetchAffiliates,
    fetchAffiliate,
    updateAffiliate,
    toggleBlock,
    changeTier,
    fetchPerformance,
    fetchTiers,
    resetFilters,
    clearCurrentAffiliate,
  }
})
