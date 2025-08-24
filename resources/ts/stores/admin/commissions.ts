import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axios from '@/plugins/axios'

export interface CommissionFilters {
  q?: string
  status?: string[]
  user_id?: string
  commande_id?: string
  date_from?: string
  date_to?: string
  eligible_only?: boolean
  page?: number
  per_page?: number
  sort?: string
  dir?: string
}

export interface Commission {
  id: string
  commande_id: string
  commande_article_id: string
  user_id: string
  type: string
  base_amount: number
  rate?: number
  qty?: number
  amount: number
  currency: string
  status: string
  rule_code?: string
  notes?: string
  eligible_at?: string
  approved_at?: string
  paid_at?: string
  paid_withdrawal_id?: string
  meta?: any
  created_at: string
  updated_at: string
  affiliate?: {
    id: string
    nom_complet: string
    email: string
    telephone?: string
  }
  commande?: {
    id: string
    statut: string
    total_ttc: number
    devise: string
    notes?: string
    created_at: string
  }
  commande_article?: {
    id: string
    quantite: number
    prix_unitaire: number
    total_ligne: number
    produit?: {
      id: string
      titre: string
      prix_vente: number
    }
  }
  can_be_approved: boolean
  can_be_rejected: boolean
  can_be_adjusted: boolean
  status_badge: {
    color: string
    text: string
  }
}

export interface CommissionPagination {
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number
  to: number
}

export interface CommissionSummary {
  total_calculated: number
  total_eligible: number
  total_approved: number
  total_paid: number
  count_calculated: number
  count_eligible: number
  count_approved: number
  count_paid: number
}

export const useCommissionsStore = defineStore('commissions', () => {
  // State
  const commissions = ref<Commission[]>([])
  const currentCommission = ref<Commission | null>(null)
  const summary = ref<CommissionSummary | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)
  const pagination = ref<CommissionPagination>({
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 0,
    from: 0,
    to: 0,
  })

  const filters = ref<CommissionFilters>({
    page: 1,
    per_page: 15,
    sort: 'created_at',
    dir: 'desc',
  })

  // Getters
  const hasCommissions = computed(() => commissions.value.length > 0)
  const isLoading = computed(() => loading.value)

  // Actions
  const fetchCommissions = async (newFilters?: Partial<CommissionFilters>) => {
    loading.value = true
    error.value = null

    try {
      if (newFilters) {
        Object.assign(filters.value, newFilters)
      }

      const response = await axios.get('/admin/commissions', {
        params: filters.value,
      })

      if (response.data.success) {
        commissions.value = response.data.data
        pagination.value = response.data.pagination
      } else {
        throw new Error(response.data.message || 'Failed to fetch commissions')
      }
    } catch (err: any) {
      error.value = err.response?.data?.message || err.message || 'Failed to fetch commissions'
      console.error('Error fetching commissions:', err)
    } finally {
      loading.value = false
    }
  }

  const fetchCommission = async (id: string) => {
    console.log('🔍 Store: fetchCommission called with ID:', id)
    
    // Validate ID format
    if (!id || id === 'undefined' || id === 'null') {
      error.value = 'ID de commission invalide'
      console.error('❌ Store: Invalid commission ID:', id)
      return
    }
    
    loading.value = true
    error.value = null

    try {
      console.log('🌐 Making API call to:', `/api/admin/commissions/${id}`)
      const response = await axios.get(`/admin/commissions/${id}`)
      console.log('📡 Raw API response:', response)

      if (response.data.success) {
        console.log('✅ Commission data received:', response.data.data)
        currentCommission.value = response.data.data
        return response.data.data
      } else {
        throw new Error(response.data.message || 'Failed to fetch commission')
      }
    } catch (err: any) {
      console.error('❌ Store: fetchCommission error:', err)
      
      // Handle specific error types
      if (err.response?.status === 404) {
        error.value = 'Commission non trouvée'
      } else if (err.response?.status === 422) {
        error.value = 'Format d\'ID invalide'
      } else {
        error.value = err.response?.data?.message || err.message || 'Failed to fetch commission'
      }

      // Return null to indicate failure
      return null
    } finally {
      loading.value = false
    }
  }

  const approveCommission = async (id: string, note?: string) => {
    try {
      const response = await axios.post(`/api/admin/commissions/${id}/approve`, {
        note,
      })

      if (response.data.success) {
        // Update the commission in the list
        const index = commissions.value.findIndex(c => c.id === id)
        if (index !== -1) {
          commissions.value[index] = response.data.data
        }
        
        // Update current commission if it's the same
        if (currentCommission.value?.id === id) {
          currentCommission.value = response.data.data
        }

        return { success: true, message: response.data.message }
      } else {
        throw new Error(response.data.message || 'Failed to approve commission')
      }
    } catch (err: any) {
      const message = err.response?.data?.message || err.message || 'Failed to approve commission'
      console.error('Error approving commission:', err)
      return { success: false, message }
    }
  }

  const rejectCommission = async (id: string, reason: string) => {
    try {
      const response = await axios.post(`/api/admin/commissions/${id}/reject`, {
        reason,
      })

      if (response.data.success) {
        // Update the commission in the list
        const index = commissions.value.findIndex(c => c.id === id)
        if (index !== -1) {
          commissions.value[index] = response.data.data
        }
        
        // Update current commission if it's the same
        if (currentCommission.value?.id === id) {
          currentCommission.value = response.data.data
        }

        return { success: true, message: response.data.message }
      } else {
        throw new Error(response.data.message || 'Failed to reject commission')
      }
    } catch (err: any) {
      const message = err.response?.data?.message || err.message || 'Failed to reject commission'
      console.error('Error rejecting commission:', err)
      return { success: false, message }
    }
  }

  const adjustCommission = async (id: string, amount: number, note: string) => {
    try {
      const response = await axios.post(`/api/admin/commissions/${id}/adjust`, {
        amount,
        note,
      })

      if (response.data.success) {
        // Update the commission in the list
        const index = commissions.value.findIndex(c => c.id === id)
        if (index !== -1) {
          commissions.value[index] = response.data.data
        }
        
        // Update current commission if it's the same
        if (currentCommission.value?.id === id) {
          currentCommission.value = response.data.data
        }

        return { success: true, message: response.data.message }
      } else {
        throw new Error(response.data.message || 'Failed to adjust commission')
      }
    } catch (err: any) {
      const message = err.response?.data?.message || err.message || 'Failed to adjust commission'
      console.error('Error adjusting commission:', err)
      return { success: false, message }
    }
  }

  const markAsPaid = async (id: string) => {
    try {
      const response = await axios.post(`/api/admin/commissions/${id}/mark-paid`)

      if (response.data.success) {
        // Update the commission in the list
        const index = commissions.value.findIndex(c => c.id === id)
        if (index !== -1) {
          commissions.value[index] = response.data.data
        }

        // Update current commission if it's the same
        if (currentCommission.value?.id === id) {
          currentCommission.value = response.data.data
        }

        return { success: true, message: response.data.message }
      } else {
        throw new Error(response.data.message || 'Failed to mark commission as paid')
      }
    } catch (err: any) {
      const message = err.response?.data?.message || err.message || 'Failed to mark commission as paid'
      console.error('Error marking commission as paid:', err)
      return { success: false, message }
    }
  }

  const bulkApprove = async (ids: string[], note?: string) => {
    try {
      const response = await axios.post('/api/admin/commissions/bulk/approve', {
        ids,
        note,
      })

      if (response.data.success) {
        // Refresh the list
        await fetchCommissions()
        return { success: true, message: response.data.message }
      } else {
        throw new Error(response.data.message || 'Failed to bulk approve commissions')
      }
    } catch (err: any) {
      const message = err.response?.data?.message || err.message || 'Failed to bulk approve commissions'
      console.error('Error bulk approving commissions:', err)
      return { success: false, message }
    }
  }

  const bulkReject = async (ids: string[], reason: string) => {
    try {
      const response = await axios.post('/api/admin/commissions/bulk/reject', {
        ids,
        reason,
      })

      if (response.data.success) {
        // Refresh the list
        await fetchCommissions()
        return { success: true, message: response.data.message }
      } else {
        throw new Error(response.data.message || 'Failed to bulk reject commissions')
      }
    } catch (err: any) {
      const message = err.response?.data?.message || err.message || 'Failed to bulk reject commissions'
      console.error('Error bulk rejecting commissions:', err)
      return { success: false, message }
    }
  }

  const recalculateOrder = async (commandeId: string) => {
    try {
      const response = await axios.post(`/api/admin/commissions/recalc/${commandeId}`)

      if (response.data.success) {
        // Refresh the list
        await fetchCommissions()
        return { success: true, message: response.data.message }
      } else {
        throw new Error(response.data.message || 'Failed to recalculate commissions')
      }
    } catch (err: any) {
      const message = err.response?.data?.message || err.message || 'Failed to recalculate commissions'
      console.error('Error recalculating commissions:', err)
      return { success: false, message }
    }
  }

  const exportCommissions = async (currentFilters?: CommissionFilters) => {
    try {
      const params = currentFilters || filters.value
      const response = await axios.get('/api/admin/commissions/export', {
        params,
        responseType: 'blob',
      })

      // Create download link
      const url = window.URL.createObjectURL(new Blob([response.data]))
      const link = document.createElement('a')
      link.href = url
      link.setAttribute('download', `commissions-${new Date().toISOString().split('T')[0]}.csv`)
      document.body.appendChild(link)
      link.click()
      link.remove()
      window.URL.revokeObjectURL(url)

      return { success: true, message: 'Export started' }
    } catch (err: any) {
      const message = err.response?.data?.message || err.message || 'Failed to export commissions'
      console.error('Error exporting commissions:', err)
      return { success: false, message }
    }
  }

  const fetchSummary = async (currentFilters?: CommissionFilters) => {
    try {
      const params = currentFilters || filters.value
      const response = await axios.get('/api/admin/commissions/summary', {
        params,
      })

      if (response.data.success) {
        summary.value = response.data.data
      }
    } catch (err: any) {
      console.error('Error fetching commission summary:', err)
    }
  }

  const resetFilters = () => {
    filters.value = {
      page: 1,
      per_page: 15,
      sort: 'created_at',
      dir: 'desc',
    }
  }

  const clearCurrentCommission = () => {
    currentCommission.value = null
  }

  return {
    // State
    commissions,
    currentCommission,
    summary,
    loading,
    error,
    pagination,
    filters,

    // Getters
    hasCommissions,
    isLoading,

    // Actions
    fetchCommissions,
    fetchCommission,
    approveCommission,
    rejectCommission,
    adjustCommission,
    markAsPaid,
    bulkApprove,
    bulkReject,
    recalculateOrder,
    exportCommissions,
    fetchSummary,
    resetFilters,
    clearCurrentCommission,
  }
})
