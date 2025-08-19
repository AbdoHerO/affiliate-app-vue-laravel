import { defineStore } from 'pinia'
import { ref, reactive } from 'vue'
import { $api } from '@/utils/api'
import type {
  StockItem,
  StockSummary,
  StockMovement,
  CreateStockMovementForm,
  StockFilters,
  StockHistoryFilters,
  StockPagination,
  StockResponse,
  StockSummaryResponse,
  StockMovementResponse,
  StockHistoryResponse,
} from '@/types/admin/stock'

export const useStockStore = defineStore('admin-stock', () => {

  // State
  const items = ref<StockItem[]>([])
  const summary = ref<StockSummary | null>(null)
  const history = ref<StockMovement[]>([])
  const loading = ref(false)
  const historyLoading = ref(false)
  const summaryLoading = ref(false)

  // Pagination
  const pagination = reactive<StockPagination>({
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 0,
    from: 0,
    to: 0,
  })

  const historyPagination = reactive<StockPagination>({
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 0,
    from: 0,
    to: 0,
  })

  // Filters
  const filters = reactive<StockFilters>({
    q: '',
    categorie_id: '',
    boutique_id: '',
    actif: true,
    with_variants: true,
    min_qty: undefined,
    max_qty: undefined,
    page: 1,
    per_page: 15,
    sort: 'updated_at',
    dir: 'desc',
  })

  // Actions
  let fetchListPromise: Promise<any> | null = null

  const fetchList = async (customFilters?: Partial<StockFilters>) => {
    // Prevent duplicate concurrent calls
    if (fetchListPromise) {
      console.log('🔄 [Stock Store] Fetch already in progress, waiting...')
      return fetchListPromise
    }

    loading.value = true

    fetchListPromise = (async () => {
      try {
      // Start with basic params for debugging
      const params: any = {
        page: customFilters?.page || filters.page || 1,
        per_page: customFilters?.per_page || filters.per_page || 15,
      }

      // Only add other params if they have values - ensure booleans are properly converted
      if (customFilters?.actif !== undefined || filters.actif !== undefined) {
        const actifValue = customFilters?.actif ?? filters.actif
        params.actif = actifValue === true ? 1 : (actifValue === false ? 0 : actifValue)
      }

      if (customFilters?.with_variants !== undefined || filters.with_variants !== undefined) {
        const withVariantsValue = customFilters?.with_variants ?? filters.with_variants
        params.with_variants = withVariantsValue === true ? 1 : (withVariantsValue === false ? 0 : withVariantsValue)
      }
      
      if (customFilters?.q || filters.q) {
        params.q = customFilters?.q || filters.q
      }
      
      if (customFilters?.categorie_id || filters.categorie_id) {
        params.categorie_id = customFilters?.categorie_id || filters.categorie_id
      }
      
      if (customFilters?.boutique_id || filters.boutique_id) {
        params.boutique_id = customFilters?.boutique_id || filters.boutique_id
      }

      console.log('🔧 [Stock Store] Making API call with params:', params)
      console.log('🔧 [Stock Store] Auth token:', localStorage.getItem('auth_token') ? 'Present' : 'Missing')

      const response = await $api<StockResponse>('/admin/stock', {
        method: 'GET',
        query: params,
      })

      console.log('📦 [Stock Store] API Response:', response)
      console.log('📦 [Stock Store] Response success:', response.success)

      if (response.success) {
        items.value = response.data
        Object.assign(pagination, response.pagination)
      }

      return response
    } catch (error) {
      console.error('🚨 [Stock Store] Failed to fetch stock list:', error)
      throw error
    } finally {
      loading.value = false
      fetchListPromise = null
    }
    })()

    return fetchListPromise
  }

  const fetchSummary = async () => {
    summaryLoading.value = true
    try {
      console.log('📊 [Stock Store] Fetching summary...')
      
      const response = await $api<StockSummaryResponse>('/admin/stock/summary', {
        method: 'GET',
      })

      console.log('📊 [Stock Store] Summary response:', response)

      if (response.success) {
        summary.value = response.data
      }

      return response
    } catch (error) {
      console.error('🚨 [Stock Store] Failed to fetch stock summary:', error)
      throw error
    } finally {
      summaryLoading.value = false
    }
  }

  const createMovement = async (data: CreateStockMovementForm) => {
    try {
      const response = await $api<StockMovementResponse>('/admin/stock/movements', {
        method: 'POST',
        body: data,
      })

      if (response.success) {
        // Refresh the list to show updated stock levels
        await fetchList()
        
        // Refresh summary if it's loaded
        if (summary.value) {
          await fetchSummary()
        }
      }

      return response
    } catch (error) {
      console.error('Failed to create stock movement:', error)
      throw error
    }
  }

  const fetchHistory = async (
    varianteId: string,
    customFilters?: Partial<StockHistoryFilters>
  ) => {
    historyLoading.value = true
    console.log('🔍 [Stock Store] fetchHistory called with:', {
      varianteId,
      customFilters
    })
    
    try {
      const params = { ...customFilters }
      
      // Clean up undefined values
      Object.keys(params).forEach(key => {
        if (params[key as keyof StockHistoryFilters] === undefined || params[key as keyof StockHistoryFilters] === '') {
          delete params[key as keyof StockHistoryFilters]
        }
      })

      const url = `/admin/stock/${varianteId}/history`
      console.log('📡 [Stock Store] Making API request:', {
        url,
        params
      })

      const response = await $api<StockHistoryResponse>(url, {
        method: 'GET',
        params,
      })

      console.log('📥 [Stock Store] API response:', response)

      if (response.success) {
        history.value = response.data
        Object.assign(historyPagination, response.pagination)
        console.log('✅ [Stock Store] History updated:', {
          historyLength: history.value.length,
          pagination: historyPagination
        })
      }

      return response
    } catch (error) {
      console.error('❌ [Stock Store] Failed to fetch stock history:', error)
      throw error
    } finally {
      historyLoading.value = false
      console.log('🏁 [Stock Store] fetchHistory completed, loading set to false')
    }
  }

  // Utility functions
  const updateFilters = (newFilters: Partial<StockFilters>) => {
    Object.assign(filters, newFilters)
  }

  const resetFilters = () => {
    Object.assign(filters, {
      q: '',
      categorie_id: '',
      boutique_id: '',
      actif: true,
      with_variants: true,
      min_qty: undefined,
      max_qty: undefined,
      page: 1,
      per_page: 15,
      sort: 'updated_at',
      dir: 'desc',
    })
  }

  const getStockStatusColor = (available: number, onHand: number) => {
    if (available <= 0) return 'error'
    if (available <= onHand * 0.1) return 'warning'
    return 'success'
  }

  const getStockStatusText = (available: number, onHand: number) => {
    if (available <= 0) return 'Rupture'
    if (available <= onHand * 0.1) return 'Stock faible'
    return 'En stock'
  }

  const getMovementTypeColor = (type: string) => {
    switch (type) {
      case 'in': return 'success'
      case 'out': return 'error'
      case 'adjust': return 'warning'
      default: return 'primary'
    }
  }

  const getMovementTypeIcon = (type: string) => {
    switch (type) {
      case 'in': return 'tabler-arrow-up'
      case 'out': return 'tabler-arrow-down'
      case 'adjust': return 'tabler-adjustments'
      default: return 'tabler-package'
    }
  }

  const formatMovementQuantity = (type: string, quantity: number) => {
    switch (type) {
      case 'in': return `+${quantity}`
      case 'out': return `-${Math.abs(quantity)}`
      case 'adjust': return quantity > 0 ? `+${quantity}` : `${quantity}`
      default: return `${quantity}`
    }
  }

  return {
    // State
    items,
    summary,
    history,
    loading,
    historyLoading,
    summaryLoading,
    pagination,
    historyPagination,
    filters,

    // Actions
    fetchList,
    fetchSummary,
    createMovement,
    fetchHistory,
    updateFilters,
    resetFilters,

    // Utilities
    getStockStatusColor,
    getStockStatusText,
    getMovementTypeColor,
    getMovementTypeIcon,
    formatMovementQuantity,
  }
})
