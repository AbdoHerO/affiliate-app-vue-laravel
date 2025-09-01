import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { useApi } from '@/composables/useApi'
import { useNotifications } from '@/composables/useNotifications'
import { useI18n } from 'vue-i18n'

// Types
export interface CartItem {
  key: string
  produit_id: string
  variante_id?: string
  qty: number
  sell_price?: number
  type_command?: string
  item_commission?: number
  product: {
    id: string
    titre: string
    sku?: string | null
    prix_vente: number
    prix_achat: number
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
  const { showSuccess, showError } = useNotifications()
  const { t } = useI18n()
  
  // State
  const items = ref<CartItem[]>([])
  const summary = ref<CartSummary>({
    items_count: 0,
    total_amount: 0,
    estimated_commission: 0
  })
  const loading = ref(false)
  const error = ref<string | null>(null)

  // Getters
  const count = computed(() => summary.value.items_count)
  const totalQty = computed(() => items.value.reduce((sum, item) => sum + item.qty, 0))
  const subtotal = computed(() => summary.value.total_amount)
  const estimatedCommission = computed(() => summary.value.estimated_commission)
  const hasItems = computed(() => items.value.length > 0)

  // Helper function to get current quantity for a product in cart
  const getProductQuantityInCart = (produitId: string, varianteId?: string): number => {
    return items.value
      .filter(item => {
        const itemVarianteId = item.variante_id || undefined
        return item.produit_id === produitId && itemVarianteId === varianteId
      })
      .reduce((sum, item) => sum + item.qty, 0)
  }

  // Helper function to check if adding quantity would meet minimum requirements
  const canAddQuantity = (produitId: string, quantiteMin: number, addingQty: number, varianteId?: string): { canAdd: boolean; currentQty: number; totalAfterAdd: number } => {
    const currentQty = getProductQuantityInCart(produitId, varianteId)
    const totalAfterAdd = currentQty + addingQty

    return {
      canAdd: totalAfterAdd >= quantiteMin,
      currentQty,
      totalAfterAdd
    }
  }

  // Actions
  const fetchCart = async () => {
    loading.value = true
    error.value = null
    console.log('🔄 [Cart Store] Fetching cart...')

    try {
      const { data, error: apiError } = await useApi('affiliate/cart/summary', {
        method: 'GET'
      })

      console.log('📡 [Cart Store] Fetch cart response:', { data: data.value, error: apiError.value })

      if (apiError.value) {
        throw apiError.value
      }

      if (data.value) {
        const responseData = data.value as any
        items.value = responseData.items || []
        summary.value = {
          items_count: responseData.items_count || 0,
          total_amount: responseData.total_amount || 0,
          estimated_commission: responseData.estimated_commission || 0
        }
        
        console.log('✅ [Cart Store] Cart updated:', {
          itemsCount: items.value.length,
          summaryCount: summary.value.items_count,
          totalAmount: summary.value.total_amount,
          estimatedCommission: summary.value.estimated_commission
        })

        // Log items with their command types for debugging
        console.log('🛒 [Cart Store] Items with command types:',
          items.value.map(item => ({
            title: item.product.titre,
            type_command: item.type_command,
            qty: item.qty,
            sell_price: item.sell_price,
            item_commission: item.item_commission
          }))
        )
      }
    } catch (err: any) {
      error.value = err.message || 'Erreur lors du chargement du panier'
      console.error('❌ [Cart Store] Fetch cart error:', err)
      showError(error.value || 'Erreur lors du chargement du panier')
    } finally {
      loading.value = false
    }
  }

  const addItem = async (item: { produit_id: string; variante_id?: string; qty: number; sell_price?: number; type_command?: string }) => {
    console.log('🔄 [Cart Store] Adding item:', item)
    
    try {
      const { data, error: apiError } = await useApi('affiliate/cart/add', {
        method: 'POST',
        body: JSON.stringify({
          ...item,
          type_command: item.type_command || 'order_sample'
        }),
        headers: {
          'Content-Type': 'application/json'
        }
      })

      console.log('📡 [Cart Store] API response:', { data: data.value, error: apiError.value })

      if (apiError.value) {
        // Handle specific error types
        if (apiError.value.status === 422) {
          // Validation errors (stock issues, minimum quantity, etc.)
          // Use the exact message from backend (ErrorService normalizes it to apiError.message)
          const validationMessage = apiError.value.message || 'Stock insuffisant ou données invalides'

          showError(validationMessage)
          throw new Error(validationMessage)
        } else if (apiError.value.status === 404) {
          const notFoundMessage = apiError.value.message || 'Produit non trouvé'
          showError(notFoundMessage)
          throw new Error(notFoundMessage)
        } else if (apiError.value.status === 400) {
          const stockMessage = apiError.value.message || 'Stock insuffisant'
          showError(stockMessage)
          throw new Error(stockMessage)
        } else {
          throw apiError.value
        }
      }

      showSuccess(t('alerts.cart.added_to_cart'))
      console.log('✅ [Cart Store] Item added successfully, refreshing cart...')
      await fetchCart() // Refresh cart
      console.log('🔄 [Cart Store] Cart refreshed, new count:', items.value.length)
      return data.value
    } catch (err: any) {
      console.error('❌ [Cart Store] Add item error:', err)
      const message = err.message || t('alerts.cart.error_adding')
      if (!err.message) {
        showError(message)
      }
      throw err
    }
  }

  const updateItem = async (itemKey: string, updates: { qty?: number; variante_id?: string }) => {
    try {
      const { data, error: apiError } = await useApi('affiliate/cart/items', {
        method: 'PATCH',
        body: JSON.stringify({ item_key: itemKey, ...updates }),
        headers: {
          'Content-Type': 'application/json'
        }
      })

      if (apiError.value) {
        // Handle specific error types for update
        if (apiError.value.status === 422) {
          // Use the exact message from backend
          const validationMessage = apiError.value.message || 'Données invalides'

          showError(validationMessage)
          throw new Error(validationMessage)
        } else if (apiError.value.status === 404) {
          const notFoundMessage = apiError.value.message || 'Article non trouvé'
          showError(notFoundMessage)
          throw new Error(notFoundMessage)
        } else {
          throw apiError.value
        }
      }

      showSuccess(t('alerts.cart.cart_updated'))
      await fetchCart() // Refresh cart
      return data.value
    } catch (err: any) {
      const message = err.message || 'Erreur lors de la mise à jour'
      if (!err.message) {
        showError(message)
      }
      throw err
    }
  }

  const removeItem = async (itemKey: string) => {
    try {
      const { data, error: apiError } = await useApi('affiliate/cart/items', {
        method: 'DELETE',
        body: JSON.stringify({ item_key: itemKey }),
        headers: {
          'Content-Type': 'application/json'
        }
      })

      if (apiError.value) {
        // Handle specific error types for removal
        if (apiError.value.status === 422) {
          // Minimum quantity validation error
          const validationMessage = apiError.value.message || 'Impossible de retirer ce produit'
          showError(validationMessage)
          throw new Error(validationMessage)
        } else if (apiError.value.status === 404) {
          const notFoundMessage = apiError.value.message || 'Produit non trouvé dans le panier'
          showError(notFoundMessage)
          throw new Error(notFoundMessage)
        } else {
          throw apiError.value
        }
      }

      showSuccess(t('alerts.cart.product_removed'))
      await fetchCart() // Refresh cart
      return data.value
    } catch (err: any) {
      const message = err.message || 'Erreur lors de la suppression'
      if (!err.message) {
        showError(message)
      }
      throw err
    }
  }

  const clear = async () => {
    try {
      const { data, error: apiError } = await useApi('affiliate/cart/clear', {
        method: 'DELETE'
      })

      if (apiError.value) {
        throw apiError.value
      }

      showSuccess(t('alerts.cart.cart_cleared'))
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
      const { data, error: apiError } = await useApi('/affiliate/cart/preview', {
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

  const checkout = async (clientData: ClientFinalForm, deliveryFee: number = 0, adjustedCommission: number = 0): Promise<CheckoutResponse> => {
    loading.value = true
    error.value = null

    try {
      const { data, error: apiError } = await useApi('affiliate/checkout', {
        method: 'POST',
        body: JSON.stringify({
          receiver_name: clientData.nom_complet,
          receiver_phone: clientData.telephone,
          city_id: clientData.city_id,
          address_line: clientData.adresse,
          note: clientData.note || '',
          delivery_fee: deliveryFee,
          adjusted_commission: adjustedCommission
        }),
        headers: {
          'Content-Type': 'application/json'
        }
      })

      if (apiError.value) {
        throw apiError.value
      }

      const responseData = data.value as any
      if (responseData?.success) {
        showSuccess(responseData.message || 'Commande créée avec succès')
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
      showError(error.value || 'Erreur lors de la validation de la commande')
      throw err
    } finally {
      loading.value = false
    }
  }

  const fetchCities = async (searchQuery?: string) => {
    try {
      const params = new URLSearchParams()
      if (searchQuery) {
        params.append('q', searchQuery)
      }
      // Remove per_page parameter - API now returns all cities

      const { data, error: apiError } = await useApi(`affiliate/ozon/cities?${params.toString()}`, {
        method: 'GET'
      })

      if (apiError.value) {
        throw apiError.value
      }

      const citiesData = (data.value as any)?.data || []

      // Ensure each city has proper city_id, name, and prices
      const validatedCities = citiesData.map((city: any) => ({
        city_id: city.city_id,  // Should be the OzonExpress city ID
        name: city.name,        // Should be string
        prices: city.prices || {} // Include prices data for delivery calculation
      }))

      console.log('🏙️ [Cart Store] Cities loaded:', validatedCities.length, 'cities')
      console.log('🏙️ [Cart Store] Sample city with prices:', validatedCities[0])

      // Log a few cities with their prices for debugging
      const sampleCities = validatedCities.slice(0, 3)
      console.log('🏙️ [Cart Store] Sample cities for debugging:', sampleCities)

      return validatedCities
    } catch (err: any) {
      console.error('❌ [Cart Store] Fetch cities error:', err)
      showError(t('alerts.cities.error_loading'))
      return []
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

    // Helper functions
    getProductQuantityInCart,
    canAddQuantity,

    // Actions
    fetchCart,
    addItem,
    updateItem,
    removeItem,
    clear,
    preview,
    checkout,
    fetchCities
  }
})
