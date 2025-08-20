import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { useApi } from '@/composables/useApi'
import { useNotifications } from '@/composables/useNotifications'

// Types
export interface CartItem {
  key: string
  produit_id: string
  variante_id?: string
  qty: number
  product: {
    id: string
    titre: string
    prix_vente: number
    prix_affilie: number
    image?: string
  }
  variant?: {
    id: string
    attribut_principal: string
    valeur: string
    stock: number
  }
  item_total: number
  stock_available: number
}

export interface CartSummary {
  items_count: number
  total_amount: number
  estimated_commission: number
}

export interface ClientFinalForm {
  nom_complet: string
  telephone: string
  city_id: string
  adresse: string
  note?: string
}

export interface CheckoutResponse {
  success: boolean
  message: string
  data: {
    commande: {
      id: string
    }
  }
}

export const useAffiliateCartStore = defineStore('affiliate-cart', () => {
  // State
  const items = ref<CartItem[]>([])
  const summary = ref<CartSummary>({
    items_count: 0,
    total_amount: 0,
    estimated_commission: 0
  })
  const loading = ref(false)
  const error = ref<string | null>(null)

  // Notifications
  const { showSuccess, showError } = useNotifications()

  // Getters
  const count = computed(() => summary.value.items_count)
  const totalQty = computed(() => items.value.reduce((sum, item) => sum + item.qty, 0))
  const subtotal = computed(() => summary.value.total_amount)
  const estimatedCommission = computed(() => summary.value.estimated_commission)
  const hasItems = computed(() => items.value.length > 0)

  // Actions
  const fetchCart = async () => {
    loading.value = true
    error.value = null

    try {
      const { data, error: apiError } = await useApi('/api/affiliate/cart/summary', {
        method: 'GET'
      })

      if (apiError.value) {
        throw apiError.value
      }

      if (data.value) {
        items.value = data.value.items || []
        summary.value = {
          items_count: data.value.items_count || 0,
          total_amount: data.value.total_amount || 0,
          estimated_commission: data.value.estimated_commission || 0
        }
      }
    } catch (err: any) {
      error.value = err.message || 'Erreur lors du chargement du panier'
      showError(error.value)
    } finally {
      loading.value = false
    }
  }

  const addItem = async (item: { produit_id: string; variante_id?: string; qty: number }) => {
    try {
      const { data, error: apiError } = await useApi('/api/affiliate/cart/add', {
        method: 'POST',
        body: JSON.stringify(item),
        headers: {
          'Content-Type': 'application/json'
        }
      })

      if (apiError.value) {
        throw apiError.value
      }

      showSuccess('Ajouté au panier')
      await fetchCart() // Refresh cart
      return data.value
    } catch (err: any) {
      const message = err.message || 'Erreur lors de l\'ajout au panier'
      showError(message)
      throw err
    }
  }

  const updateItem = async (itemKey: string, updates: { qty?: number; variante_id?: string }) => {
    try {
      const { data, error: apiError } = await useApi('/api/affiliate/cart/items', {
        method: 'PATCH',
        body: JSON.stringify({ item_key: itemKey, ...updates }),
        headers: {
          'Content-Type': 'application/json'
        }
      })

      if (apiError.value) {
        throw apiError.value
      }

      showSuccess('Panier mis à jour')
      await fetchCart() // Refresh cart
      return data.value
    } catch (err: any) {
      const message = err.message || 'Erreur lors de la mise à jour'
      showError(message)
      throw err
    }
  }

  const removeItem = async (itemKey: string) => {
    try {
      const { data, error: apiError } = await useApi('/api/affiliate/cart/items', {
        method: 'DELETE',
        body: JSON.stringify({ item_key: itemKey }),
        headers: {
          'Content-Type': 'application/json'
        }
      })

      if (apiError.value) {
        throw apiError.value
      }

      showSuccess('Produit retiré du panier')
      await fetchCart() // Refresh cart
      return data.value
    } catch (err: any) {
      const message = err.message || 'Erreur lors de la suppression'
      showError(message)
      throw err
    }
  }

  const clear = async () => {
    try {
      const { data, error: apiError } = await useApi('/api/affiliate/cart/clear', {
        method: 'DELETE'
      })

      if (apiError.value) {
        throw apiError.value
      }

      showSuccess('Panier vidé')
      items.value = []
      summary.value = {
        items_count: 0,
        total_amount: 0,
        estimated_commission: 0
      }
      return data.value
    } catch (err: any) {
      const message = err.message || 'Erreur lors du vidage du panier'
      showError(message)
      throw err
    }
  }

  const preview = async () => {
    try {
      const { data, error: apiError } = await useApi('/api/affiliate/cart/preview', {
        method: 'POST'
      })

      if (apiError.value) {
        throw apiError.value
      }

      return data.value
    } catch (err: any) {
      const message = err.message || 'Erreur lors de l\'aperçu'
      showError(message)
      throw err
    }
  }

  const checkout = async (clientData: ClientFinalForm): Promise<CheckoutResponse> => {
    loading.value = true
    error.value = null

    try {
      const { data, error: apiError } = await useApi('/api/affiliate/checkout', {
        method: 'POST',
        body: JSON.stringify({
          receiver_name: clientData.nom_complet,
          receiver_phone: clientData.telephone,
          city_id: clientData.city_id,
          address_line: clientData.adresse,
          note: clientData.note || ''
        }),
        headers: {
          'Content-Type': 'application/json'
        }
      })

      if (apiError.value) {
        throw apiError.value
      }

      if (data.value?.success) {
        showSuccess(data.value.message || 'Commande créée avec succès')
        // Clear cart after successful checkout
        items.value = []
        summary.value = {
          items_count: 0,
          total_amount: 0,
          estimated_commission: 0
        }
      }

      return data.value as CheckoutResponse
    } catch (err: any) {
      error.value = err.message || 'Erreur lors de la validation de la commande'
      showError(error.value)
      throw err
    } finally {
      loading.value = false
    }
  }

  return {
    // State
    items,
    summary,
    loading,
    error,

    // Getters
    count,
    totalQty,
    subtotal,
    estimatedCommission,
    hasItems,

    // Actions
    fetchCart,
    addItem,
    updateItem,
    removeItem,
    clear,
    preview,
    checkout
  }
})
