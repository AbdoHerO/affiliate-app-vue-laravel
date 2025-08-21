import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { $api } from '@/utils/api'

export interface AffiliateCommission {
  id: string
  commande_article_id: string
  commande_id: string
  user_id: string
  type: string
  base_amount: number
  rate: number
  qty: number
  amount: number
  currency: string
  status: string
  rule_code?: string
  notes?: string
  eligible_at?: string
  approved_at?: string
  paid_at?: string
  paid_withdrawal_id?: string
  created_at: string
  updated_at: string
  commande?: {
    id: string
    statut: string
    total_ttc: number
    created_at: string
  }
  commandeArticle?: {
    produit?: {
      id: string
      titre: string
    }
  }
}

export interface AffiliateWithdrawal {
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
  created_at: string
  updated_at: string
  total_commission_amount: number
  commission_count: number
  items?: any[]
}

export interface CommissionFilters {
  q?: string
  status?: string[]
  date_from?: string
  date_to?: string
  amount_min?: number
  amount_max?: number
  sort?: string
  dir?: 'asc' | 'desc'
  per_page?: number
}

export interface WithdrawalFilters {
  status?: string[]
  date_from?: string
  date_to?: string
  sort?: string
  dir?: 'asc' | 'desc'
  per_page?: number
}

export interface PaymentsPagination {
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
}

export const useAffiliatePaymentsStore = defineStore('affiliatePayments', () => {
  // State
  const commissions = ref<AffiliateCommission[]>([])
  const withdrawals = ref<AffiliateWithdrawal[]>([])
  const currentWithdrawal = ref<AffiliateWithdrawal | null>(null)
  const commissionsSummary = ref<Record<string, { count: number; total: number }>>({})
  const loading = ref({
    commissions: false,
    withdrawals: false,
    payout: false,
  })
  const error = ref<string | null>(null)
  const commissionsPagination = ref<PaymentsPagination>({
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 0,
    from: null,
    to: null,
  })
  const withdrawalsPagination = ref<PaymentsPagination>({
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 0,
    from: null,
    to: null,
  })
  const commissionsFilters = ref<CommissionFilters>({
    q: '',
    status: [],
    date_from: '',
    date_to: '',
    sort: 'created_at',
    dir: 'desc',
    per_page: 15,
  })
  const withdrawalsFilters = ref<WithdrawalFilters>({
    status: [],
    date_from: '',
    date_to: '',
    sort: 'created_at',
    dir: 'desc',
    per_page: 15,
  })

  // Getters
  const hasCommissions = computed(() => commissions.value.length > 0)
  const hasWithdrawals = computed(() => withdrawals.value.length > 0)
  const isLoadingCommissions = computed(() => loading.value.commissions)
  const isLoadingWithdrawals = computed(() => loading.value.withdrawals)
  const isLoadingPayout = computed(() => loading.value.payout)
  
  const eligibleCommissionsTotal = computed(() => {
    return commissionsSummary.value.eligible?.total || 0
  })
  
  const eligibleCommissionsCount = computed(() => {
    return commissionsSummary.value.eligible?.count || 0
  })

  // Actions
  const fetchCommissions = async (page = 1) => {
    loading.value.commissions = true
    error.value = null

    try {
      const params = new URLSearchParams()
      
      // Add pagination
      params.append('page', page.toString())
      params.append('per_page', commissionsFilters.value.per_page?.toString() || '15')
      
      // Add filters
      if (commissionsFilters.value.q) params.append('q', commissionsFilters.value.q)
      if (commissionsFilters.value.status?.length) {
        commissionsFilters.value.status.forEach(status => params.append('status[]', status))
      }
      if (commissionsFilters.value.date_from) params.append('date_from', commissionsFilters.value.date_from)
      if (commissionsFilters.value.date_to) params.append('date_to', commissionsFilters.value.date_to)
      if (commissionsFilters.value.amount_min) params.append('amount_min', commissionsFilters.value.amount_min.toString())
      if (commissionsFilters.value.amount_max) params.append('amount_max', commissionsFilters.value.amount_max.toString())
      if (commissionsFilters.value.sort) params.append('sort', commissionsFilters.value.sort)
      if (commissionsFilters.value.dir) params.append('dir', commissionsFilters.value.dir)

      const response = await $api(`/affiliate/commissions?${params.toString()}`)
      
      if (response.success) {
        commissions.value = response.data
        commissionsPagination.value = response.pagination
        commissionsSummary.value = response.summary || {}
      } else {
        throw new Error(response.message || 'Failed to fetch commissions')
      }
    } catch (err: any) {
      error.value = err.message || 'An error occurred while fetching commissions'
      console.error('Error fetching affiliate commissions:', err)
    } finally {
      loading.value.commissions = false
    }
  }

  const fetchWithdrawals = async (page = 1) => {
    loading.value.withdrawals = true
    error.value = null

    try {
      const params = new URLSearchParams()
      
      // Add pagination
      params.append('page', page.toString())
      params.append('per_page', withdrawalsFilters.value.per_page?.toString() || '15')
      
      // Add filters
      if (withdrawalsFilters.value.status?.length) {
        withdrawalsFilters.value.status.forEach(status => params.append('status[]', status))
      }
      if (withdrawalsFilters.value.date_from) params.append('date_from', withdrawalsFilters.value.date_from)
      if (withdrawalsFilters.value.date_to) params.append('date_to', withdrawalsFilters.value.date_to)
      if (withdrawalsFilters.value.sort) params.append('sort', withdrawalsFilters.value.sort)
      if (withdrawalsFilters.value.dir) params.append('dir', withdrawalsFilters.value.dir)

      const response = await $api(`/affiliate/withdrawals?${params.toString()}`)
      
      if (response.success) {
        withdrawals.value = response.data
        withdrawalsPagination.value = response.pagination
      } else {
        throw new Error(response.message || 'Failed to fetch withdrawals')
      }
    } catch (err: any) {
      error.value = err.message || 'An error occurred while fetching withdrawals'
      console.error('Error fetching affiliate withdrawals:', err)
    } finally {
      loading.value.withdrawals = false
    }
  }

  const fetchWithdrawal = async (id: string) => {
    loading.value.withdrawals = true
    error.value = null

    try {
      const response = await $api(`/affiliate/withdrawals/${id}`)
      
      if (response.success) {
        currentWithdrawal.value = response.data
      } else {
        throw new Error(response.message || 'Failed to fetch withdrawal')
      }
    } catch (err: any) {
      error.value = err.message || 'An error occurred while fetching the withdrawal'
      console.error('Error fetching affiliate withdrawal:', err)
    } finally {
      loading.value.withdrawals = false
    }
  }

  const requestPayout = async (notes?: string) => {
    loading.value.payout = true
    error.value = null

    try {
      const response = await $api('/affiliate/withdrawals/request', {
        method: 'POST',
        body: JSON.stringify({ notes }),
      })
      
      if (response.success) {
        // Refresh data after successful payout request
        await Promise.all([
          fetchCommissions(),
          fetchWithdrawals(),
        ])
        return response.data
      } else {
        throw new Error(response.message || 'Failed to request payout')
      }
    } catch (err: any) {
      error.value = err.message || 'An error occurred while requesting payout'
      console.error('Error requesting affiliate payout:', err)
      throw err
    } finally {
      loading.value.payout = false
    }
  }

  const updateCommissionsFilters = (newFilters: Partial<CommissionFilters>) => {
    commissionsFilters.value = { ...commissionsFilters.value, ...newFilters }
  }

  const updateWithdrawalsFilters = (newFilters: Partial<WithdrawalFilters>) => {
    withdrawalsFilters.value = { ...withdrawalsFilters.value, ...newFilters }
  }

  const resetCommissionsFilters = () => {
    commissionsFilters.value = {
      q: '',
      status: [],
      date_from: '',
      date_to: '',
      sort: 'created_at',
      dir: 'desc',
      per_page: 15,
    }
  }

  const resetWithdrawalsFilters = () => {
    withdrawalsFilters.value = {
      status: [],
      date_from: '',
      date_to: '',
      sort: 'created_at',
      dir: 'desc',
      per_page: 15,
    }
  }

  const clearCurrentWithdrawal = () => {
    currentWithdrawal.value = null
  }

  const getCommissionStatusColor = (status: string): string => {
    const statusColors: Record<string, string> = {
      'pending': 'warning',
      'eligible': 'info',
      'approved': 'primary',
      'paid': 'success',
      'canceled': 'error',
      'adjusted': 'orange',
    }
    return statusColors[status] || 'secondary'
  }

  const getCommissionStatusLabel = (status: string): string => {
    const statusLabels: Record<string, string> = {
      'pending': 'En attente',
      'eligible': 'Éligible',
      'approved': 'Approuvée',
      'paid': 'Payée',
      'canceled': 'Annulée',
      'adjusted': 'Ajustée',
    }
    return statusLabels[status] || status
  }

  const getWithdrawalStatusColor = (status: string): string => {
    const statusColors: Record<string, string> = {
      'pending': 'warning',
      'approved': 'info',
      'in_payment': 'primary',
      'paid': 'success',
      'rejected': 'error',
      'canceled': 'secondary',
    }
    return statusColors[status] || 'secondary'
  }

  const getWithdrawalStatusLabel = (status: string): string => {
    const statusLabels: Record<string, string> = {
      'pending': 'En attente',
      'approved': 'Approuvé',
      'in_payment': 'En cours de paiement',
      'paid': 'Payé',
      'rejected': 'Rejeté',
      'canceled': 'Annulé',
    }
    return statusLabels[status] || status
  }

  return {
    // State
    commissions,
    withdrawals,
    currentWithdrawal,
    commissionsSummary,
    loading,
    error,
    commissionsPagination,
    withdrawalsPagination,
    commissionsFilters,
    withdrawalsFilters,

    // Getters
    hasCommissions,
    hasWithdrawals,
    isLoadingCommissions,
    isLoadingWithdrawals,
    isLoadingPayout,
    eligibleCommissionsTotal,
    eligibleCommissionsCount,

    // Actions
    fetchCommissions,
    fetchWithdrawals,
    fetchWithdrawal,
    requestPayout,
    updateCommissionsFilters,
    updateWithdrawalsFilters,
    resetCommissionsFilters,
    resetWithdrawalsFilters,
    clearCurrentWithdrawal,
    getCommissionStatusColor,
    getCommissionStatusLabel,
    getWithdrawalStatusColor,
    getWithdrawalStatusLabel,
  }
})
