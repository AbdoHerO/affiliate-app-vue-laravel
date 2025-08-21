import { defineStore } from 'pinia'
import { ref, reactive, computed } from 'vue'
import { useApi } from '@/composables/useApi'
import { useNotifications } from '@/composables/useNotifications'

// Types for affiliate catalogue
export interface CatalogueProduct {
  id: string
  ref?: string
  titre: string
  description?: string
  copywriting?: string
  prix_achat: number
  prix_vente: number
  prix_affilie: number
  stock_total: number
  quantite_min?: number
  notes_admin?: string
  rating_value?: number | null
  rating_max?: number
  slug?: string
  categorie: {
    id: string
    nom: string
  } | null
  boutique?: {
    id: string
    nom: string
  } | null
  images: Array<{
    id?: string
    url: string
    ordre: number
  }>
  videos?: Array<{
    id?: string
    url: string
    titre?: string
    title?: string
    ordre?: number
  }>
  variantes: CatalogueVariant[]
  attributes?: {
    size?: string[]
    color?: Array<{
      name: string
      swatch?: string
    }>
  }
}

export interface CatalogueVariant {
  id: string
  type?: 'size' | 'color' | 'other'
  attribut_principal: string // e.g., 'taille', 'couleur'
  valeur: string // e.g., 'L', 'XL', 'Rouge'
  value?: string // normalized value
  code?: string
  color?: string | null // hex color for color variants
  swatch?: string // color swatch
  image_url?: string | null // variant-specific image
  stock: number
}

// Drawer-specific view model
export interface DrawerViewModel {
  id: string
  ref?: string
  titre: string
  description?: string
  copywriting?: string
  notes_admin?: string
  prix_achat: number
  prix_vente: number
  prix_affilie: number
  stock_total: number
  rating_value?: number | null
  categorie?: { id: string; nom: string } | null
  gallery: {
    main: string
    thumbnails: Array<{ id?: string; url: string; ordre: number }>
  }
  sizes: Array<{ id: string; value: string; stock: number }>
  colors: Array<{
    id: string
    name: string
    swatch?: string
    image_url?: string
    stock: number
  }>
  matrix: Array<{
    size?: string
    color?: string
    stock: number
    variant_id: string
  }>
  images: Array<{ id?: string; url: string; ordre: number }>
  videos: Array<{ id?: string; url: string; title?: string }>
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
  sell_price?: number
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
  // Enhanced variant data for cards
  colors?: Array<{ name: string; swatch?: string; image_url?: string }>
  sizes?: string[]
  variantsByCombo?: Record<string, any>
  slug?: string
  description?: string
}

export const useCatalogueStore = defineStore('affiliate-catalogue', () => {
  // State
  const items = ref<NormalizedProduct[]>([])
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

  // Drawer state
  const selectedId = ref<string | null>(null)
  const drawerProduct = ref<DrawerViewModel | null>(null)
  const addingToCart = ref(false)
  const selectedColor = ref<string | null>(null)
  const selectedSize = ref<string | null>(null)
  const selectedVariantId = ref<string | null>(null)
  const selectedQty = ref(1)
  const maxQty = ref<number>(0)

  // Derived maps for variant resolution
  const variantsByCombo = ref<Record<string, { id: string; stock_available: number; image_url?: string }>>({})
  const sizes = ref<string[]>([])
  const colors = ref<Array<{ name: string; swatch?: string; image_url?: string }>>([])
  const matrixRows = ref<Array<{ qty: number; color: string; size: string }>>([])

  // Helper function to create combo key
  const createComboKey = (color: string | null, size: string | null): string => {
    const normalizedColor = color?.trim() || '∅'
    const normalizedSize = size?.trim() || '∅'
    return `${normalizedColor}|${normalizedSize}`
  }

  // Build variant maps for resolution
  const buildVariantMaps = (variants: any[]) => {
    // Reset maps
    variantsByCombo.value = {}
    sizes.value = []
    colors.value = []
    matrixRows.value = []

    const sizeSet = new Set<string>()
    const colorMap = new Map<string, { name: string; swatch?: string; image_url?: string }>()

    variants.forEach(variant => {
      const { type, color, size, id, stock_available, image_url } = variant

      // Build combo map
      const comboKey = createComboKey(color, size)
      variantsByCombo.value[comboKey] = {
        id,
        stock_available: stock_available || 0,
        image_url
      }

      // Collect sizes
      if (size) {
        sizeSet.add(size)
      }

      // Collect colors
      if (color) {
        colorMap.set(color, {
          name: color,
          swatch: color, // Use color name as swatch for now
          image_url
        })
      }

      // Build matrix rows for table
      if (color && size) {
        matrixRows.value.push({
          qty: stock_available || 0,
          color,
          size
        })
      }
    })

    // Convert to arrays
    sizes.value = Array.from(sizeSet).sort((a, b) => {
      // Custom size sorting: S < M < L < XL < 2XL < 3XL
      const sizeOrder = ['XS', 'S', 'M', 'L', 'XL', '2XL', '3XL', '4XL', '5XL']
      const aIndex = sizeOrder.indexOf(a)
      const bIndex = sizeOrder.indexOf(b)
      if (aIndex !== -1 && bIndex !== -1) return aIndex - bIndex
      if (aIndex !== -1) return -1
      if (bIndex !== -1) return 1
      return a.localeCompare(b)
    })

    colors.value = Array.from(colorMap.values()).sort((a, b) => a.name.localeCompare(b.name))

    // Sort matrix rows
    matrixRows.value.sort((a, b) => {
      const sizeOrder = ['XS', 'S', 'M', 'L', 'XL', '2XL', '3XL', '4XL', '5XL']
      const aIndex = sizeOrder.indexOf(a.size)
      const bIndex = sizeOrder.indexOf(b.size)
      if (aIndex !== bIndex) {
        if (aIndex !== -1 && bIndex !== -1) return aIndex - bIndex
        if (aIndex !== -1) return -1
        if (bIndex !== -1) return 1
      }
      return a.color.localeCompare(b.color)
    })
  }

  // Getters
  const hasItems = computed(() => items.value.length > 0)
  const isLoading = computed(() => loading.value)
  const isDetailLoading = computed(() => detailLoading.value)
  const totalItems = computed(() => pagination.total)

  // Notifications
  const { showSuccess, showError } = useNotifications()

  // Data mapper - normalize API response to card view model
  const mapProductToNormalized = (product: CatalogueProduct): NormalizedProduct => {
    console.log('🔄 [Store] Mapping product to normalized:', product.id, {
      title: product.titre,
      images: product.images,
      variantes: product.variantes,
      variants: (product as any).variants // Check if API provides parsed variants
    })

    // Get main image (first image or fallback)
    const mainImage = product.images?.[0]?.url || 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAwIiBoZWlnaHQ9IjMwMCIgdmlld0JveD0iMCAwIDMwMCAzMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIzMDAiIGhlaWdodD0iMzAwIiBmaWxsPSIjRjVGNUY1Ii8+CjxwYXRoIGQ9Ik0xNTAgMTAwQzE2MS4wNDYgMTAwIDE3MCA5MC45NTQzIDE3MCA4MEM1NyA2OS4wNDU3IDE0Ny45NTQgNjAgMTM2IDYwQzEyNC45NTQgNjAgMTE2IDY5LjA0NTcgMTE2IDgwQzExNiA5MC45NTQzIDEyNC45NTQgMTAwIDEzNiAxMDBIMTUwWiIgZmlsbD0iI0NDQ0NDQyIvPgo8cGF0aCBkPSJNMTgwIDEyMEgxMjBDMTE2LjY4NiAxMjAgMTE0IDEyMi42ODYgMTE0IDEyNlYyMDBDMTE0IDIwMy4zMTQgMTE2LjY4NiAyMDYgMTIwIDIwNkgxODBDMTgzLjMxNCAyMDYgMTg2IDIwMy4zMTQgMTg2IDIwMFYxMjZDMTg2IDEyMi42ODYgMTgzLjMxNCAxMjAgMTgwIDEyMFoiIGZpbGw9IiNDQ0NDQ0MiLz4KPC9zdmc+'

    console.log('🖼️ [Store] Main image extracted:', mainImage)

    // Initialize variant arrays
    let variantSizes: Array<{ id: string; value: string; stock: number }> = []
    let variantColors: Array<{ id: string; value: string; color?: string; image_url?: string; stock: number }> = []
    let colors: Array<{ name: string; swatch?: string; image_url?: string }> = []
    let sizes: string[] = []

    // Check if API provides pre-parsed variants structure
    const apiVariants = (product as any).variants
    if (apiVariants && (apiVariants.sizes || apiVariants.colors)) {
      console.log('🎯 [Store] Using API-parsed variants:', apiVariants)

      // Use API-parsed sizes
      if (apiVariants.sizes && Array.isArray(apiVariants.sizes)) {
        variantSizes = apiVariants.sizes.filter((s: any) => s.stock > 0)
        sizes = variantSizes.map((s: any) => s.value)
      }

      // Use API-parsed colors
      if (apiVariants.colors && Array.isArray(apiVariants.colors)) {
        variantColors = apiVariants.colors.filter((c: any) => c.stock > 0)
        colors = variantColors.map((c: any) => ({
          name: c.value,
          swatch: c.color || c.value,
          image_url: c.image_url
        }))
      }
    } else {
      // Fallback: Parse variant data manually from variantes array
      console.log('🔧 [Store] Manually parsing variants from variantes array')

      const sizeSet = new Set<string>()
      const colorMap = new Map<string, { name: string; swatch?: string; image_url?: string }>()

      product.variantes?.forEach(v => {
        const stock = v.stock || 0
        if (stock <= 0) return // Skip variants with no stock

        const attributCode = v.attribut_principal?.toLowerCase()

        console.log('🔧 [Store] Processing variant:', {
          id: v.id,
          attribut_principal: v.attribut_principal,
          valeur: v.valeur,
          stock,
          attributCode,
          color: v.color,
          image_url: v.image_url
        })

        // Handle combined variants like "Red - Medium"
        if (v.valeur?.includes(' - ')) {
          const [colorPart, sizePart] = v.valeur.split(' - ')

          // Add to sets
          if (colorPart) {
            colorMap.set(colorPart, {
              name: colorPart,
              swatch: v.color || colorPart,
              image_url: v.image_url || undefined
            })
          }
          if (sizePart) {
            sizeSet.add(sizePart)
          }

          // Add to variant arrays
          if (colorPart) {
            variantColors.push({
              id: v.id,
              value: colorPart,
              color: v.color || undefined,
              image_url: v.image_url || undefined,
              stock
            })
          }
          if (sizePart) {
            variantSizes.push({
              id: v.id,
              value: sizePart,
              stock
            })
          }
        } else {
          // Handle individual variants
          if (['taille', 'size'].includes(attributCode)) {
            sizeSet.add(v.valeur)
            variantSizes.push({
              id: v.id,
              value: v.valeur,
              stock
            })
          } else if (['couleur', 'color'].includes(attributCode)) {
            colorMap.set(v.valeur, {
              name: v.valeur,
              swatch: v.color || v.valeur,
              image_url: v.image_url || undefined
            })
            variantColors.push({
              id: v.id,
              value: v.valeur,
              color: v.color || undefined,
              image_url: v.image_url || undefined,
              stock
            })
          }
        }
      })

      // Convert to arrays for card display
      colors = Array.from(colorMap.values()).sort((a, b) => a.name.localeCompare(b.name))
      sizes = Array.from(sizeSet).sort((a, b) => {
        // Custom size sorting: S < M < L < XL < 2XL < 3XL
        const sizeOrder = ['XS', 'S', 'M', 'L', 'XL', '2XL', '3XL', '4XL', '5XL']
        const aIndex = sizeOrder.indexOf(a)
        const bIndex = sizeOrder.indexOf(b)
        if (aIndex !== -1 && bIndex !== -1) return aIndex - bIndex
        if (aIndex !== -1) return -1
        if (bIndex !== -1) return 1
        return a.localeCompare(b)
      })
    }

    console.log('✅ [Store] Final normalized product:', {
      id: product.id,
      mainImage,
      colors,
      sizes,
      variantSizes,
      variantColors,
      totalVariants: product.variantes?.length || 0
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
        sizes: variantSizes,
        colors: variantColors
      },
      // Enhanced variant data for cards (what CatalogueCard expects)
      colors, // Array of { name, swatch, image_url }
      sizes,  // Array of strings
      variantsByCombo: {}, // Will be populated if needed
      slug: product.slug,
      description: product.description
    }
  }

  // Helper function to get image for a specific color
  const imageForColor = (product: NormalizedProduct, colorName: string): string => {
    // First try to find variant image for this color
    const colorVariant = product.colors?.find(c => c.name === colorName)
    if (colorVariant?.image_url) {
      return colorVariant.image_url
    }

    // Fallback to main image
    return product.mainImage
  }

  // Actions
  const fetchList = async (params?: Partial<CatalogueFilters>) => {
    loading.value = true
    error.value = null

    try {
      // Merge params with current filters
      const queryParams = { ...filters, ...params }

      // Build query string
      const queryString = new URLSearchParams(
        Object.entries(queryParams)
          .filter(([_, value]) => value !== undefined && value !== '')
          .map(([key, value]) => [key, String(value)])
      ).toString()

      const url = queryString ? `/affiliate/catalogue?${queryString}` : '/affiliate/catalogue'
      const { data, error: apiError } = await useApi(url)

      if (apiError.value) {
        throw apiError.value
      }

      const response = data.value as any
      if (response) {
        // Transform raw API data to normalized products
        const rawProducts = response.data || []
        items.value = rawProducts.map((product: CatalogueProduct) => mapProductToNormalized(product))

        // Update pagination
        if (response.meta) {
          Object.assign(pagination, {
            current_page: response.meta.current_page,
            last_page: response.meta.last_page,
            per_page: response.meta.per_page,
            total: response.meta.total,
            from: response.meta.from,
            to: response.meta.to
          })
        }
      }
    } catch (err: any) {
      error.value = err.message || 'Erreur lors du chargement du catalogue'
      showError(error.value || 'Erreur inconnue')
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
        selectedProduct.value = data.value as CatalogueProduct
      }
    } catch (err: any) {
      error.value = err.message || 'Erreur lors du chargement du produit'
      showError(error.value || 'Erreur inconnue')
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
        cartSummary.value = data.value as CartSummary
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

  // Drawer actions
  const fetchOneForDrawer = async (id: string) => {
    selectedId.value = id
    detailLoading.value = true
    error.value = null

    try {
      const { data, error: apiError } = await useApi(`/affiliate/catalogue/${id}`)

      if (apiError.value) {
        error.value = apiError.value.message || 'Failed to fetch product details'
        showError(error.value || 'Failed to fetch product details')
        return
      }

      if (data.value) {
        const product = data.value as any
        drawerProduct.value = mapToDrawerViewModel(product)

        // Build variant maps for resolution
        buildVariantMaps(product.variantes || [])

        // Reset selections
        selectedColor.value = null
        selectedSize.value = null
        selectedVariantId.value = null
        selectedQty.value = 1
        maxQty.value = 0
      }
    } catch (err: any) {
      error.value = err.message || 'Failed to fetch product details'
      showError(error.value || 'Failed to fetch product details')
    } finally {
      detailLoading.value = false
    }
  }

  const mapToDrawerViewModel = (product: CatalogueProduct): DrawerViewModel => {
    // Get main image and thumbnails
    const images = product.images?.sort((a, b) => a.ordre - b.ordre) || []
    const mainImage = images[0]?.url || ''

    // Extract sizes and colors from variants
    const sizes: Array<{ id: string; value: string; stock: number }> = []
    const colors: Array<{ id: string; name: string; swatch?: string; image_url?: string; stock: number }> = []
    const matrix: Array<{ size?: string; color?: string; stock: number; variant_id: string }> = []

    product.variantes?.forEach(variant => {
      const attributCode = (variant as any).attribut?.code || variant.attribut_principal?.toLowerCase()

      if (['taille', 'size'].includes(attributCode)) {
        sizes.push({
          id: variant.id,
          value: variant.valeur,
          stock: variant.stock
        })
      } else if (['couleur', 'color'].includes(attributCode)) {
        colors.push({
          id: variant.id,
          name: variant.valeur,
          swatch: variant.color || variant.swatch,
          image_url: variant.image_url || undefined,
          stock: variant.stock
        })
      }

      // Add to matrix
      matrix.push({
        size: ['taille', 'size'].includes(attributCode) ? variant.valeur : undefined,
        color: ['couleur', 'color'].includes(attributCode) ? variant.valeur : undefined,
        stock: variant.stock,
        variant_id: variant.id
      })
    })

    return {
      id: product.id,
      ref: product.ref,
      titre: product.titre,
      description: product.description,
      copywriting: product.copywriting,
      notes_admin: product.notes_admin,
      prix_achat: product.prix_achat,
      prix_vente: product.prix_vente,
      prix_affilie: product.prix_affilie,
      stock_total: product.stock_total,
      rating_value: product.rating_value,
      categorie: product.categorie,
      gallery: {
        main: mainImage,
        thumbnails: images
      },
      sizes: sizes.sort((a, b) => a.value.localeCompare(b.value)),
      colors: colors.sort((a, b) => a.name.localeCompare(b.name)),
      matrix,
      images,
      videos: product.videos?.map(v => ({
        id: v.id,
        url: v.url,
        title: v.titre || v.title
      })) || []
    }
  }

  // Resolve variant ID based on color and size selection
  const resolveVariantId = (color: string | null, size: string | null) => {
    const comboKey = createComboKey(color, size)
    const variant = variantsByCombo.value[comboKey]

    if (variant) {
      selectedVariantId.value = variant.id
      maxQty.value = variant.stock_available
      // Clamp current quantity to max available
      if (selectedQty.value > maxQty.value) {
        selectedQty.value = Math.max(1, maxQty.value)
      }
    } else {
      selectedVariantId.value = null
      maxQty.value = 0
      selectedQty.value = 1
    }
  }

  const selectColor = (colorName: string) => {
    selectedColor.value = colorName
    resolveVariantId(selectedColor.value, selectedSize.value)

    // Update main image if color has specific image
    const variant = variantsByCombo.value[createComboKey(colorName, null)]
    if (variant?.image_url && drawerProduct.value) {
      drawerProduct.value.gallery.main = variant.image_url
    }
  }

  const selectSize = (sizeValue: string) => {
    selectedSize.value = sizeValue
    resolveVariantId(selectedColor.value, selectedSize.value)
  }

  const addToCartFromDrawer = async (data: { produit_id: string; variante_id?: string; qty: number }) => {
    addingToCart.value = true

    try {
      const { data: response, error: apiError } = await useApi('/affiliate/cart/add', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
      })

      if (apiError.value) {
        // Handle specific error types
        if (apiError.value.status === 422) {
          const validationMessage = apiError.value.data?.message || 'Stock insuffisant ou données invalides'
          showError(validationMessage)
        } else if (apiError.value.status === 404) {
          showError('Produit non trouvé')
        } else {
          showError(apiError.value.message || 'Erreur lors de l\'ajout au panier')
        }
        return false
      }

      if (response.value) {
        // Don't show success message here - it will be shown in ProductDrawer
        await fetchCartSummary()
        return true
      }

      return false
    } catch (err: any) {
      showError(err.message || 'Erreur lors de l\'ajout au panier')
      return false
    } finally {
      addingToCart.value = false
    }
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

    // Drawer state
    selectedId,
    drawerProduct,
    addingToCart,
    selectedColor,
    selectedSize,
    selectedVariantId,
    selectedQty,
    maxQty,

    // Variant resolution
    variantsByCombo,
    sizes,
    colors,
    matrixRows,

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
    mapProductToNormalized,
    imageForColor,

    // Drawer actions
    fetchOneForDrawer,
    selectColor,
    selectSize,
    addToCartFromDrawer
  }
})
