import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { $api } from '@/utils/api'

export interface Withdrawal {
  id: string
  user_id: string
  amount: number
  status: string
  status_color: string
  method: string
  iban_rib?: string
  bank_type?: string
  notes?: string
  admin_reason?: string
  payment_ref?: string
  evidence_path?: string
  evidence_url?: string
  approved_at?: string
  paid_at?: string
  meta?: any
  created_at: string
  updated_at: string
  total_commission_amount: number
  commission_count: number
  user?: {
    id: string
    nom_complet: string
    email: string
    telephone?: string
    rib?: string
    bank_type?: string
  }
  items?: WithdrawalItem[]
  can_approve: boolean
  can_reject: boolean
  can_mark_in_payment: boolean
  can_mark_paid: boolean
  can_cancel: boolean
}

export interface WithdrawalItem {
  id: string
  withdrawal_id: string
  commission_id: string
  amount: number
  created_at: string
  commission?: {
    id: string
    amount: number
    status: string
    type: string
    created_at: string
    commande?: {
      id: string
      statut: string
      total_ttc: number
      created_at: string
    }
    commande_article?: {
      id: string
      quantite: number
      prix_unitaire: number
      total_ligne: number
      type_command: string
      produit?: {
        id: string
        titre: string
        sku?: string | null
        prix_vente: number
      }
    }
    produit?: {
      id: string
      titre: string
      sku?: string | null
    }
  }
}

export interface WithdrawalSummary {
  pending: { count: number; amount: number }
  approved: { count: number; amount: number }
  in_payment: { count: number; amount: number }
  paid: { count: number; amount: number }
  rejected: { count: number; amount: number }
  canceled: { count: number; amount: number }
  total: { count: number; amount: number }
}

export interface WithdrawalFilters {
  page?: number
  per_page?: number
  sort?: string
  dir?: 'asc' | 'desc'
  q?: string
  status?: string[]
  user_id?: string
  method?: string
  date_from?: string
  date_to?: string
}

export interface WithdrawalPagination {
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number
  to: number
}

export interface CreateWithdrawalData {
  user_id: string
  amount?: number
  method?: string
  notes?: string
  commission_ids?: string[]
}

export interface AttachCommissionsData {
  commission_ids: string[]
}

export interface ApproveWithdrawalData {
  note?: string
}

export interface RejectWithdrawalData {
  reason: string
}

export interface MarkInPaymentData {
  payment_ref?: string
}

export interface MarkPaidData {
  payment_ref?: string
  paid_at?: string
  evidence?: File
}

export const useWithdrawalsStore = defineStore('withdrawals', () => {
  // State
  const withdrawals = ref<Withdrawal[]>([])
  const currentWithdrawal = ref<Withdrawal | null>(null)
  const summary = ref<WithdrawalSummary | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)
  const pagination = ref<WithdrawalPagination>({
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 0,
    from: 0,
    to: 0,
  })

  const filters = ref<WithdrawalFilters>({
    page: 1,
    per_page: 15,
    sort: 'created_at',
    dir: 'desc',
  })

  // Getters
  const hasWithdrawals = computed(() => withdrawals.value.length > 0)
  const isLoading = computed(() => loading.value)

  // Utils
  const prune = <T extends Record<string, any>>(obj: T): Partial<T> => {
    return Object.fromEntries(
      Object.entries(obj).filter(([_, v]) => {
        if (v === '' || v === null || v === undefined) return false
        if (Array.isArray(v) && v.length === 0) return false
        return true
      })
    ) as Partial<T>
  }

  // Actions
  const fetchList = async (newFilters?: Partial<WithdrawalFilters>) => {
    try {
      loading.value = true
      error.value = null

      // Replace filters instead of merging to avoid stale values
      if (newFilters) {
        filters.value = { ...newFilters }
      }

      // Build query from ONLY current filters and prune empties
      const params = prune(filters.value)
      const queryParams = new URLSearchParams()
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          if (Array.isArray(value)) {
            value.forEach(v => queryParams.append(`${key}[]`, v.toString()))
          } else {
            queryParams.append(key, value.toString())
          }
        }
      })

      const url = `/admin/withdrawals${queryParams.toString() ? `?${queryParams.toString()}` : ''}`
      console.log('🔄 [Withdrawals Store] Fetching:', url)
      console.log('📋 [Withdrawals Store] Applied filters:', filters.value)

      const response = await $api(url)
      console.log('📥 [Withdrawals Store] Raw response:', response)

      if (response?.success) {
        withdrawals.value = response.data || []

        // Update pagination
        if (response.pagination) {
          Object.assign(pagination.value, response.pagination)
        }

        // Update summary
        if (response.summary && summary.value) {
          Object.assign(summary.value, response.summary)
        }

        console.log('✅ [Withdrawals Store] Data loaded:', withdrawals.value.length, 'withdrawals')
        console.log('📄 [Withdrawals Store] Pagination:', pagination.value)
        console.log('📊 [Withdrawals Store] Summary:', summary.value)
      } else {
        error.value = response?.message || 'Erreur lors du chargement des retraits'
      }
    } catch (err: any) {
      error.value = err.data?.message || err.message || 'Erreur lors du chargement des retraits'
      console.error('🚫 [Withdrawals Store] Error fetching withdrawals:', err)
    } finally {
      loading.value = false
    }
  }

  const fetchOne = async (id: string) => {
    try {
      loading.value = true
      error.value = null

      console.log('🔄 [Withdrawals Store] Fetching withdrawal:', id)

      const response = await $api(`/admin/withdrawals/${id}`)

      if (response?.success) {
        currentWithdrawal.value = response.data
        console.log('✅ [Withdrawals Store] Withdrawal loaded:', response.data?.id)
        return { success: true, data: response.data }
      } else {
        error.value = response?.message || 'Erreur lors du chargement du retrait'
        return { success: false, message: error.value }
      }
    } catch (err: any) {
      error.value = err.data?.message || err.message || 'Erreur lors du chargement du retrait'
      console.error('🚫 [Withdrawals Store] Error fetching withdrawal:', err)
      return { success: false, message: error.value }
    } finally {
      loading.value = false
    }
  }

  const create = async (data: CreateWithdrawalData) => {
    try {
      loading.value = true
      error.value = null

      const response = await $api('/admin/withdrawals', {
        method: 'POST',
        body: data
      })

      if (response?.success) {
        // Refresh the list
        await fetchList()
        return { success: true, data: response.data, message: response.message }
      } else {
        error.value = response?.message || 'Erreur lors de la création du retrait'
        return { success: false, message: error.value }
      }
    } catch (err: any) {
      error.value = err.data?.message || err.message || 'Erreur lors de la création du retrait'
      console.error('🚫 [Withdrawals Store] Error creating withdrawal:', err)
      return { success: false, message: error.value }
    } finally {
      loading.value = false
    }
  }

  const attachCommissions = async (id: string, data: AttachCommissionsData) => {
    try {
      loading.value = true
      error.value = null

      const response = await $api(`/admin/withdrawals/${id}/attach-commissions`, {
        method: 'POST',
        body: data
      })

      if (response?.success) {
        // Refresh current withdrawal if it's the one being updated
        if (currentWithdrawal.value?.id === id) {
          await fetchOne(id)
        }
        return { success: true, data: response.data.data, message: response.message }
      } else {
        error.value = response?.message || 'Erreur lors de l\'attachement des commissions'
        return { success: false, message: error.value }
      }
    } catch (err: any) {
      error.value = err.data?.message || err.message || 'Erreur lors de l\'attachement des commissions'
      console.error('🚫 [Withdrawals Store] Error attaching commissions:', err)
      return { success: false, message: error.value }
    } finally {
      loading.value = false
    }
  }

  const detachCommissions = async (id: string, data: AttachCommissionsData) => {
    try {
      loading.value = true
      error.value = null

      const response = await $api(`/admin/withdrawals/${id}/detach-commissions`, {
        method: 'POST',
        body: data
      })

      if (response?.success) {
        // Refresh current withdrawal if it's the one being updated
        if (currentWithdrawal.value?.id === id) {
          await fetchOne(id)
        }
        return { success: true, data: response.data.data, message: response.message }
      } else {
        error.value = response?.message || 'Erreur lors du détachement des commissions'
        return { success: false, message: error.value }
      }
    } catch (err: any) {
      error.value = err.data?.message || err.message || 'Erreur lors du détachement des commissions'
      console.error('🚫 [Withdrawals Store] Error detaching commissions:', err)
      return { success: false, message: error.value }
    } finally {
      loading.value = false
    }
  }

  const approve = async (id: string, data?: ApproveWithdrawalData) => {
    try {
      loading.value = true
      error.value = null

      const response = await $api(`/admin/withdrawals/${id}/approve`, {
        method: 'POST',
        body: data || {}
      })

      if (response?.success) {
        // Update the withdrawal in the list
        const index = withdrawals.value.findIndex(w => w.id === id)
        if (index !== -1) {
          withdrawals.value[index] = response.data.data
        }
        // Update current withdrawal if it's the one being updated
        if (currentWithdrawal.value?.id === id) {
          currentWithdrawal.value = response.data.data
        }
        return { success: true, data: response.data.data, message: response.message }
      } else {
        error.value = response?.message || 'Erreur lors de l\'approbation du retrait'
        return { success: false, message: error.value }
      }
    } catch (err: any) {
      error.value = err.data?.message || err.message || 'Erreur lors de l\'approbation du retrait'
      console.error('🚫 [Withdrawals Store] Error approving withdrawal:', err)
      return { success: false, message: error.value }
    } finally {
      loading.value = false
    }
  }

  const reject = async (id: string, data: RejectWithdrawalData) => {
    try {
      loading.value = true
      error.value = null

      const response = await $api(`/admin/withdrawals/${id}/reject`, {
        method: 'POST',
        body: data
      })

      if (response?.success) {
        // Update the withdrawal in the list
        const index = withdrawals.value.findIndex(w => w.id === id)
        if (index !== -1) {
          withdrawals.value[index] = response.data.data
        }
        // Update current withdrawal if it's the one being updated
        if (currentWithdrawal.value?.id === id) {
          currentWithdrawal.value = response.data.data
        }
        return { success: true, data: response.data.data, message: response.data.message }
      } else {
        error.value = response.data.message || 'Erreur lors du rejet du retrait'
        return { success: false, message: error.value }
      }
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Erreur lors du rejet du retrait'
      console.error('Error rejecting withdrawal:', err)
      return { success: false, message: error.value }
    } finally {
      loading.value = false
    }
  }

  const markInPayment = async (id: string, data?: MarkInPaymentData) => {
    try {
      loading.value = true
      error.value = null

      const response = await $api(`/admin/withdrawals/${id}/mark-in-payment`, {
        method: 'POST',
        body: data || {}
      })

      if (response?.success) {
        // Update the withdrawal in the list
        const index = withdrawals.value.findIndex(w => w.id === id)
        if (index !== -1) {
          withdrawals.value[index] = response.data
        }
        // Update current withdrawal if it's the one being updated
        if (currentWithdrawal.value?.id === id) {
          currentWithdrawal.value = response.data
        }
        return { success: true, data: response.data, message: response.message }
      } else {
        error.value = response?.message || 'Erreur lors du marquage en cours de paiement'
        return { success: false, message: error.value }
      }
    } catch (err: any) {
      error.value = err.data?.message || err.message || 'Erreur lors du marquage en cours de paiement'
      console.error('🚫 [Withdrawals Store] Error marking withdrawal in payment:', err)
      return { success: false, message: error.value }
    } finally {
      loading.value = false
    }
  }

  const markPaid = async (id: string, data?: MarkPaidData) => {
    try {
      loading.value = true
      error.value = null

      // Create FormData for file upload
      const formData = new FormData()
      if (data?.payment_ref) formData.append('payment_ref', data.payment_ref)
      if (data?.paid_at) formData.append('paid_at', data.paid_at)
      if (data?.evidence) formData.append('evidence', data.evidence)

      const response = await $api(`/admin/withdrawals/${id}/mark-paid`, {
        method: 'POST',
        body: formData
      })

      if (response?.success) {
        // Update the withdrawal in the list
        const index = withdrawals.value.findIndex(w => w.id === id)
        if (index !== -1) {
          withdrawals.value[index] = response.data
        }
        // Update current withdrawal if it's the one being updated
        if (currentWithdrawal.value?.id === id) {
          currentWithdrawal.value = response.data
        }
        return { success: true, data: response.data, message: response.message }
      } else {
        error.value = response?.message || 'Erreur lors du marquage comme payé'
        return { success: false, message: error.value }
      }
    } catch (err: any) {
      error.value = err.data?.message || err.message || 'Erreur lors du marquage comme payé'
      console.error('🚫 [Withdrawals Store] Error marking withdrawal as paid:', err)
      return { success: false, message: error.value }
    } finally {
      loading.value = false
    }
  }

  const fetchEligibleCommissions = async (userId: string, filters?: any) => {
    try {
      loading.value = true
      error.value = null

      // Build query string for filters
      const queryParams = new URLSearchParams()
      if (filters) {
        Object.entries(filters).forEach(([key, value]) => {
          if (value !== undefined && value !== null && value !== '') {
            queryParams.append(key, value.toString())
          }
        })
      }

      const url = `/admin/withdrawals/users/${userId}/eligible-commissions${queryParams.toString() ? `?${queryParams.toString()}` : ''}`
      const response = await $api(url)

      if (response?.success || response?.data) {
        // Normalize here to always return an array
        const commissions = Array.isArray(response?.data?.data)
          ? response.data.data
          : Array.isArray(response?.data)
          ? response.data
          : Array.isArray(response)
          ? response
          : []

        return {
          success: true,
          data: {
            data: commissions,
            pagination: response?.data?.pagination || null
          }
        }
      } else {
        error.value = response?.message || 'Erreur lors du chargement des commissions éligibles'
        return { success: false, message: error.value }
      }
    } catch (err: any) {
      error.value = err.data?.message || err.message || 'Erreur lors du chargement des commissions éligibles'
      console.error('🚫 [Withdrawals Store] Error fetching eligible commissions:', err)
      return { success: false, message: error.value }
    } finally {
      loading.value = false
    }
  }

  const exportCsv = async (filters?: Partial<WithdrawalFilters>) => {
    try {
      const params = { ...filters }
      // For file downloads, we need to use fetch directly
      const token = localStorage.getItem('auth_token')
      const queryParams = new URLSearchParams()
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          queryParams.append(key, value.toString())
        }
      })

      const apiUrl = `/admin/withdrawals/export${queryParams.toString() ? `?${queryParams.toString()}` : ''}`
      const response = await fetch(`${import.meta.env.VITE_API_BASE_URL || '/api'}${apiUrl}`, {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Accept': 'application/json',
        },
      })

      if (!response.ok) {
        throw new Error('Export failed')
      }

      const blob = await response.blob()

      // Create download link
      const downloadUrl = window.URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = downloadUrl
      link.setAttribute('download', `withdrawals_${new Date().toISOString().split('T')[0]}.csv`)
      document.body.appendChild(link)
      link.click()
      link.remove()
      window.URL.revokeObjectURL(downloadUrl)

      return { success: true, message: 'Export terminé avec succès' }
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Erreur lors de l\'export'
      console.error('Error exporting withdrawals:', err)
      return { success: false, message: error.value }
    }
  }

  const resetFilters = () => {
    filters.value = { sort: 'created_at', dir: 'desc', page: 1, per_page: 15 }
  }

  // Reset state
  const resetState = () => {
    withdrawals.value = []
    currentWithdrawal.value = null
    summary.value = null
    error.value = null
    pagination.value = {
      current_page: 1,
      last_page: 1,
      per_page: 15,
      total: 0,
      from: 0,
      to: 0,
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
    withdrawals,
    currentWithdrawal,
    summary,
    loading,
    error,
    pagination,
    filters,

    // Getters
    hasWithdrawals,
    isLoading,

    // Actions
    fetchList,
    fetchOne,
    create,
    attachCommissions,
    detachCommissions,
    approve,
    reject,
    markInPayment,
    markPaid,
    fetchEligibleCommissions,
    exportCsv,
    resetFilters,
    resetState,
  }
})
