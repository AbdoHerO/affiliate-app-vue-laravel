import { defineStore } from 'pinia'
import { ref, reactive } from 'vue'
import { useApi } from '@/composables/useApi'
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
  const { api } = useApi()

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
  const fetchList = async (customFilters?: Partial<StockFilters>) => {
    loading.value = true
    try {
      const params = { ...filters, ...customFilters }
      
      // Clean up undefined values
      Object.keys(params).forEach(key => {
        if (params[key as keyof StockFilters] === undefined || params[key as keyof StockFilters] === '') {
          delete params[key as keyof StockFilters]
        }
      })

      const response = await api<StockResponse>('/admin/stock', {
        method: 'GET',
        params,
      })

      if (response.success) {
        items.value = response.data
        Object.assign(pagination, response.pagination)
      }

      return response
    } catch (error) {
      console.error('Failed to fetch stock list:', error)
      throw error
    } finally {
      loading.value = false
    }
  }

  const fetchSummary = async () => {
    summaryLoading.value = true
    try {
      const response = await api<StockSummaryResponse>('/admin/stock/summary', {
        method: 'GET',
      })

      if (response.success) {
        summary.value = response.data
      }

      return response
    } catch (error) {
      console.error('Failed to fetch stock summary:', error)
      throw error
    } finally {
      summaryLoading.value = false
    }
  }

  const createMovement = async (data: CreateStockMovementForm) => {
    try {
      const response = await api<StockMovementResponse>('/admin/stock/movements', {
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
    produitId: string,
    customFilters?: Partial<StockHistoryFilters>
  ) => {
    historyLoading.value = true
    try {
      const params = { ...customFilters }
      
      // Clean up undefined values
      Object.keys(params).forEach(key => {
        if (params[key as keyof StockHistoryFilters] === undefined || params[key as keyof StockHistoryFilters] === '') {
          delete params[key as keyof StockHistoryFilters]
        }
      })

      const response = await api<StockHistoryResponse>(`/admin/stock/${produitId}/history`, {
        method: 'GET',
        params,
      })

      if (response.success) {
        history.value = response.data
        Object.assign(historyPagination, response.pagination)
      }

      return response
    } catch (error) {
      console.error('Failed to fetch stock history:', error)
      throw error
    } finally {
      historyLoading.value = false
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
