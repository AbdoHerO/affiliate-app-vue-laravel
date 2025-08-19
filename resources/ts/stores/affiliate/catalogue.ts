import { defineStore } from 'pinia'
import { ref, reactive, computed } from 'vue'
import { useApi } from '@/composables/useApi'
import { useNotifications } from '@/composables/useNotifications'

// Types for affiliate catalogue
export interface CatalogueProduct {
  id: string
  titre: string
  categorie: {
    id: string
    nom: string
  } | null
  images: Array<{
    url: string
    ordre: number
  }>
  videos?: Array<{
    url: string
    titre?: string
    ordre?: number
  }>
  prix_achat: number
  prix_vente: number
  prix_affilie: number
  stock_total: number
  variantes: CatalogueVariant[]
  rating_value?: number | null
  slug?: string
  description?: string
  copywriting?: string
}

export interface CatalogueVariant {
  id: string
  attribut_principal: string // e.g., 'taille', 'couleur'
  valeur: string // e.g., 'L', 'XL', 'Rouge'
  color?: string | null // hex color for color variants
  image_url?: string | null // variant-specific image
  stock: number
}

export interface CatalogueFilters {
  q?: string
  category_id?: string
  min_profit?: number
  color?: string
  size?: string
  page?: number
  per_page?: number
}

export interface CataloguePagination {
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number
  to: number
}

export interface CartItem {
  produit_id: string
  variante_id?: string
  qty: number
}

export interface CartSummary {
  items_count: number
  total_amount: number
}

// Normalized product for card display
export interface NormalizedProduct {
  id: string
  titre: string
  categorie: {
    id: string
    nom: string
  } | null
  mainImage: string
  prix_achat: number
  prix_vente: number
  prix_affilie: number
  stock_total: number
  rating_value: number
  variants: {
    sizes: Array<{ id: string; value: string; stock: number }>
    colors: Array<{ id: string; value: string; color?: string; image_url?: string; stock: number }>
  }
  slug?: string
  description?: string
}

export const useCatalogueStore = defineStore('affiliate-catalogue', () => {
  // State
  const items = ref<CatalogueProduct[]>([])
  const selectedProduct = ref<CatalogueProduct | null>(null)
  const loading = ref(false)
  const detailLoading = ref(false)
  const error = ref<string | null>(null)
  
  const pagination = reactive<CataloguePagination>({
    current_page: 1,
    last_page: 1,
    per_page: 12,
    total: 0,
    from: 0,
    to: 0
  })

  const filters = reactive<CatalogueFilters>({
    q: '',
    category_id: '',
    min_profit: undefined,
    color: '',
    size: '',
    page: 1,
    per_page: 12
  })

  const cartSummary = ref<CartSummary>({
    items_count: 0,
    total_amount: 0
  })

  // Getters
  const hasItems = computed(() => items.value.length > 0)
  const isLoading = computed(() => loading.value)
  const isDetailLoading = computed(() => detailLoading.value)
  const totalItems = computed(() => pagination.total)

  // Notifications
  const { showSuccess, showError } = useNotifications()

  // Data mapper - normalize API response to card view model
  const mapProductToNormalized = (product: CatalogueProduct): NormalizedProduct => {
    // Get main image (first image or fallback)
    const mainImage = product.images?.[0]?.url || 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAwIiBoZWlnaHQ9IjMwMCIgdmlld0JveD0iMCAwIDMwMCAzMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIzMDAiIGhlaWdodD0iMzAwIiBmaWxsPSIjRjVGNUY1Ii8+CjxwYXRoIGQ9Ik0xNTAgMTAwQzE2MS4wNDYgMTAwIDE3MCA5MC45NTQzIDE3MCA4MEM1NyA2OS4wNDU3IDE0Ny45NTQgNjAgMTM2IDYwQzEyNC45NTQgNjAgMTE2IDY5LjA0NTcgMTE2IDgwQzExNiA5MC45NTQzIDEyNC45NTQgMTAwIDEzNiAxMDBIMTUwWiIgZmlsbD0iI0NDQ0NDQyIvPgo8cGF0aCBkPSJNMTgwIDEyMEgxMjBDMTE2LjY4NiAxMjAgMTE0IDEyMi42ODYgMTE0IDEyNlYyMDBDMTE0IDIwMy4zMTQgMTE2LjY4NiAyMDYgMTIwIDIwNkgxODBDMTgzLjMxNCAyMDYgMTg2IDIwMy4zMTQgMTg2IDIwMFYxMjZDMTg2IDEyMi42ODYgMTgzLjMxNCAxMjAgMTgwIDEyMFoiIGZpbGw9IiNDQ0NDQ0MiLz4KPC9zdmc+'
    
    // Group variants by type - handle individual variants correctly
    const sizes: Array<{ id: string; value: string; stock: number }> = []
    const colors: Array<{ id: string; value: string; color?: string; image_url?: string; stock: number }> = []

    product.variantes?.forEach(v => {
      const stock = v.stock || 0

      if (v.attribut_principal === 'taille' || v.attribut_principal === 'size') {
        // Individual size variant
        sizes.push({
          id: v.id,
          value: v.valeur,
          stock
        })
      } else if (v.attribut_principal === 'couleur' || v.attribut_principal === 'color') {
        // Individual color variant
        colors.push({
          id: v.id,
          value: v.valeur,
          color: v.color,
          image_url: v.image_url,
          stock
        })
      }
    })

    return {
      id: product.id,
      titre: product.titre,
      categorie: product.categorie,
      mainImage,
      prix_achat: product.prix_achat,
      prix_vente: product.prix_vente,
      prix_affilie: product.prix_affilie,
      stock_total: product.stock_total,
      rating_value: product.rating_value || 0,
      variants: {
        sizes,
        colors
      },
      slug: product.slug,
      description: product.description
    }
  }

  // Actions
  const fetchList = async (params?: Partial<CatalogueFilters>) => {
    loading.value = true
    error.value = null

    try {
      // Merge params with current filters
      const queryParams = { ...filters, ...params }
      
      const { data, error: apiError } = await useApi('/affiliate/catalogue', {
        method: 'GET',
        params: queryParams
      })

      if (apiError.value) {
        throw apiError.value
      }

      if (data.value) {
        items.value = data.value.data || []
        
        // Update pagination
        if (data.value.meta) {
          Object.assign(pagination, {
            current_page: data.value.meta.current_page,
            last_page: data.value.meta.last_page,
            per_page: data.value.meta.per_page,
            total: data.value.meta.total,
            from: data.value.meta.from,
            to: data.value.meta.to
          })
        }
      }
    } catch (err: any) {
      error.value = err.message || 'Erreur lors du chargement du catalogue'
      showError(error.value)
    } finally {
      loading.value = false
    }
  }

  const fetchOne = async (id: string) => {
    detailLoading.value = true
    error.value = null

    try {
      const { data, error: apiError } = await useApi(`/affiliate/catalogue/${id}`, {
        method: 'GET'
      })

      if (apiError.value) {
        throw apiError.value
      }

      if (data.value) {
        selectedProduct.value = data.value
      }
    } catch (err: any) {
      error.value = err.message || 'Erreur lors du chargement du produit'
      showError(error.value)
    } finally {
      detailLoading.value = false
    }
  }

  const addToCart = async (item: CartItem) => {
    try {
      const { data, error: apiError } = await useApi('/affiliate/cart/items', {
        method: 'POST',
        body: JSON.stringify(item),
        headers: {
          'Content-Type': 'application/json'
        }
      })

      if (apiError.value) {
        throw apiError.value
      }

      showSuccess('Produit ajouté au panier')

      // Update cart summary
      await fetchCartSummary()

      return data.value
    } catch (err: any) {
      const message = err.message || 'Erreur lors de l\'ajout au panier'
      showError(message)
      throw err
    }
  }

  const fetchCartSummary = async () => {
    try {
      const { data, error: apiError } = await useApi('/affiliate/cart/summary', {
        method: 'GET'
      })

      if (apiError.value) {
        throw apiError.value
      }

      if (data.value) {
        cartSummary.value = data.value
      }
    } catch (err: any) {
      console.error('Error fetching cart summary:', err)
    }
  }

  const updateFilters = (newFilters: Partial<CatalogueFilters>) => {
    Object.assign(filters, newFilters)
    filters.page = 1 // Reset to first page when filters change
  }

  const resetFilters = () => {
    Object.assign(filters, {
      q: '',
      category_id: '',
      min_profit: undefined,
      color: '',
      size: '',
      page: 1,
      per_page: 12
    })
  }

  const clearSelectedProduct = () => {
    selectedProduct.value = null
  }

  const setPage = (page: number) => {
    filters.page = page
  }

  return {
    // State
    items,
    selectedProduct,
    loading,
    detailLoading,
    error,
    pagination,
    filters,
    cartSummary,

    // Getters
    hasItems,
    isLoading,
    isDetailLoading,
    totalItems,

    // Actions
    fetchList,
    fetchOne,
    addToCart,
    fetchCartSummary,
    updateFilters,
    resetFilters,
    clearSelectedProduct,
    setPage,
    mapProductToNormalized
  }
})
