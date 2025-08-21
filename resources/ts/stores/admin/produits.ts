import { defineStore } from 'pinia'
import { ref, reactive } from 'vue'
import { useApi } from '@/composables/useApi'

export interface Produit {
  id: string
  boutique_id: string
  categorie_id: string | null
  titre: string
  description: string | null
  prix_achat: number
  prix_vente: number
  prix_affilie: number | null
  slug: string
  actif: boolean
  rating_value?: number | null
  rating?: {
    value?: number | null
    max: number
    updated_by?: string | null
    updated_at?: string | null
  }
  created_at: string
  updated_at: string
  boutique?: {
    id: string
    nom: string
    slug: string
  }
  categorie?: {
    id: string
    nom: string
    slug: string
  }
  images?: ProduitImage[]
  videos?: ProduitVideo[]
  variantes?: ProduitVariante[]
}

export interface ProduitImage {
  id: string
  produit_id: string
  url: string
  ordre: number
  created_at?: string
  updated_at?: string
}

export interface ProduitVideo {
  id: string
  produit_id: string
  url: string
  titre: string | null
  created_at?: string
  updated_at?: string
}

export interface ProduitVariante {
  id: string
  produit_id: string
  nom: string
  valeur: string
  prix_vente_variante: number | null
  image_url: string | null
  actif: boolean
  created_at?: string
  updated_at?: string
}

export interface ProduitProposition {
  id: string
  produit_id: string
  auteur_id: string
  titre: string
  description: string
  type: string
  statut: string
  image_url?: string
  auteur?: {
    id: string
    nom_complet: string
    email: string
  }
  created_at?: string
  updated_at?: string
}

export interface ProduitRupture {
  id: string
  produit_id: string
  variante_id?: string
  motif: string
  started_at: string
  expected_restock_at?: string
  active: boolean
  resolved_at?: string
  created_at?: string
  updated_at?: string
}

export interface ProduitFormData {
  boutique_id: string
  categorie_id: string | null
  titre: string
  description: string | null
  copywriting?: string | null
  prix_achat: number | null
  prix_vente: number | null
  quantite_min: number | null
  notes_admin: string | null
  slug?: string
  actif: boolean
  rating_value?: number | null
  stock_total?: number | null
}

export interface ProduitFilters {
  q?: string
  boutique_id?: string
  categorie_id?: string
  actif?: string
  sort?: string
  dir?: string
  page?: number
  perPage?: number
}

export interface ProduitPagination {
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number
  to: number
}

export const useProduitsStore = defineStore('produits', () => {
  // State
  const produits = ref<Produit[]>([])
  const currentProduit = ref<Produit | null>(null)
  const images = ref<ProduitImage[]>([])
  const videos = ref<ProduitVideo[]>([])
  const variantes = ref<ProduitVariante[]>([])
  const propositions = ref<ProduitProposition[]>([])
  const ruptures = ref<ProduitRupture[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)
  const pagination = reactive<ProduitPagination>({
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 0,
    from: 0,
    to: 0
  })

  const filters = reactive<ProduitFilters>({
    q: '',
    boutique_id: '',
    categorie_id: '',
    actif: '',
    sort: 'created_at',
    dir: 'desc',
    page: 1,
    perPage: 15
  })

  // Request cancellation
  let abortController: AbortController | null = null

  // Actions
  const fetchProduits = async (params: ProduitFilters = {}) => {
    // Cancel previous request
    if (abortController) {
      abortController.abort()
    }
    abortController = new AbortController()

    loading.value = true
    try {
      // Merge with current filters
      const searchParams = { ...filters, ...params }

      // Remove empty values
      Object.keys(searchParams).forEach(key => {
        const value = (searchParams as any)[key]
        if (value === '' || value === null || value === undefined) {
          delete (searchParams as any)[key]
        }
      })

      // Build URL with query parameters
      const stringParams: Record<string, string> = {}
      Object.entries(searchParams).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== '') {
          stringParams[key] = String(value)
        }
      })
      const queryString = new URLSearchParams(stringParams).toString()
      const url = `/admin/produits${queryString ? `?${queryString}` : ''}`

      const { data: responseData, error: apiError } = await useApi(url, {
        signal: abortController.signal
      })

      if (apiError.value) {
        const message = (apiError.value as any).message || 'Error fetching products'
        error.value = message
        throw apiError.value
      }

      const response = responseData.value as any

      if (response.success) {
        produits.value = response.data

        // Update pagination if present
        if (response.meta) {
          Object.assign(pagination, {
            current_page: response.meta.current_page,
            last_page: response.meta.last_page,
            per_page: response.meta.per_page,
            total: response.meta.total,
            from: response.meta.from || 0,
            to: response.meta.to || 0
          })
        }
      } else {
        error.value = response.message
      }
    } catch (error: any) {
      if (error.name !== 'AbortError') {
        console.error('Failed to fetch produits:', error)
        throw error
      }
    } finally {
      loading.value = false
    }
  }

  const fetchProduit = async (id: string) => {
    loading.value = true
    try {
      console.debug('[Store] Fetching product with ID:', id)
      const { data: responseData, error: apiError } = await useApi(`/admin/produits/${id}`)

      if (apiError.value) {
        const message = (apiError.value as any).message || 'Error fetching product'
        error.value = message
        throw apiError.value
      }

      const response = responseData.value as any

      // Handle both wrapped and direct responses
      const productData = response.success ? response.data : response.data || response

      if (productData && productData.id) {
        currentProduit.value = productData
        console.debug('[Store] Product fetched successfully:', {
          id: productData.id,
          images: productData.images?.length || 0,
          videos: productData.videos?.length || 0,
          variantes: productData.variantes?.length || 0,
          propositions: productData.propositions?.length || 0,
          ruptures: productData.ruptures?.length || 0
        })

        // Set relations if included
        if (productData.images) {
          images.value = productData.images
        }
        if (productData.videos) {
          videos.value = productData.videos
        }
        if (productData.variantes) {
          variantes.value = productData.variantes
        }
        if (productData.propositions) {
          propositions.value = productData.propositions
        }
        if (productData.ruptures) {
          ruptures.value = productData.ruptures
        }

        return productData
      } else {
        const errorMessage = response.message || 'Invalid product data received'
        error.value = errorMessage
        throw new Error(errorMessage)
      }
    } catch (error) {
      console.error('Failed to fetch produit:', error)
      throw error
    } finally {
      loading.value = false
    }
  }

  const createProduit = async (data: ProduitFormData) => {
    loading.value = true
    try {
      // Ensure quantite_min has a default value
      const formData = { ...data }
      if (!formData.quantite_min || formData.quantite_min < 1) {
        formData.quantite_min = 1
      }

      console.debug('[Store] Creating product with data:', formData)

      const { data: responseData, error: apiError } = await useApi('/admin/produits', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(formData)
      })

      if (apiError.value) {
        const message = (apiError.value as any).message || 'Error creating product'
        error.value = message
        throw apiError.value
      }

      const response = responseData.value as any
      if (response.success) {
        console.debug('[Store] Product created successfully with ID:', response.data.id)
        // Set as current product immediately
        currentProduit.value = response.data
        // Add to local state
        produits.value.unshift(response.data)
        return response.data
      } else {
        error.value = response.message
        throw new Error(response.message)
      }
    } catch (error) {
      console.error('Failed to create produit:', error)
      throw error
    } finally {
      loading.value = false
    }
  }

  const updateProduit = async (id: string, data: ProduitFormData) => {
    loading.value = true
    try {
      const { data: responseData, error: apiError } = await useApi(`/admin/produits/${id}`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
      })

      if (apiError.value) {
        const message = (apiError.value as any).message || 'Error updating product'
        error.value = message
        throw apiError.value
      }

      const response = responseData.value as any

      if (response.success) {
        // Update in local state
        const index = produits.value.findIndex(p => p.id === id)
        if (index !== -1) {
          produits.value[index] = response.data
        }

        // Update current produit if it matches
        if (currentProduit.value?.id === id) {
          currentProduit.value = response.data
        }

        return response.data
      }

      throw new Error(response.message)
    } catch (error) {
      console.error('Failed to update produit:', error)
      throw error
    } finally {
      loading.value = false
    }
  }

  const deleteProduit = async (id: string) => {
    loading.value = true
    try {
      const { error: apiError } = await useApi(`/admin/produits/${id}`, {
        method: 'DELETE'
      })

      if (apiError.value) {
        const message = (apiError.value as any).message || 'Error deleting product'
        error.value = message
        throw apiError.value
      }
      
      // Remove from local state
      const index = produits.value.findIndex(p => p.id === id)
      if (index !== -1) {
        produits.value.splice(index, 1)
      }

      // Clear current produit if it matches
      if (currentProduit.value?.id === id) {
        currentProduit.value = null
      }

      return true
    } catch (error) {
      console.error('Failed to delete produit:', error)
      throw error
    } finally {
      loading.value = false
    }
  }

  // Image management actions
  const fetchImages = async (produitId: string) => {
    try {
      const { data: responseData, error: apiError } = await useApi(`/admin/produits/${produitId}/images`)

      if (apiError.value) {
        throw apiError.value
      }

      const response = responseData.value as any
      images.value = response.data || []
      return images.value
    } catch (error) {
      console.error('Failed to fetch images:', error)
      throw error
    }
  }

  const addImage = async (produitId: string, imageData: { url: string; ordre?: number }) => {
    try {
      const { data: responseData, error: apiError } = await useApi(`/admin/produits/${produitId}/images`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(imageData)
      })

      if (apiError.value) throw apiError.value
      const response = responseData.value as any
      
      if (response.success) {
        // Add to local images state
        images.value.push(response.data)
        
        // Update current produit images if loaded
        if (currentProduit.value?.id === produitId && currentProduit.value.images) {
          currentProduit.value.images.push(response.data)
        }

        return response.data
      }
      
      throw new Error(response.message)
    } catch (error) {
      console.error('Failed to add image:', error)
      throw error
    }
  }

  const sortImages = async (produitId: string, items: { id: string; ordre: number }[]) => {
    try {
      const { data: responseData, error: apiError } = await useApi(`/admin/produits/${produitId}/images/sort`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ items })
      })

      if (apiError.value) throw apiError.value
      const response = responseData.value as any
      
      if (response.success) {
        // Update local images state with new order
        items.forEach(item => {
          const image = images.value.find(img => img.id === item.id)
          if (image) {
            image.ordre = item.ordre
          }
        })
        
        // Sort images by order
        images.value.sort((a, b) => a.ordre - b.ordre)
        
        // Update current produit images if loaded
        if (currentProduit.value?.images) {
          items.forEach(item => {
            const image = currentProduit.value!.images!.find(img => img.id === item.id)
            if (image) {
              image.ordre = item.ordre
            }
          })
          currentProduit.value.images.sort((a, b) => a.ordre - b.ordre)
        }
        
        return true
      }
      
      throw new Error(response.message)
    } catch (error) {
      console.error('Failed to sort images:', error)
      throw error
    }
  }

  const deleteImage = async (produitId: string, imageId: string) => {
    try {
      const { data: responseData, error: apiError } = await useApi(`/admin/produits/${produitId}/images/${imageId}`, {
        method: 'DELETE'
      })

      if (apiError.value) throw apiError.value
      const response = responseData.value as any
      
      if (response.success) {
        // Remove from local images state
        const index = images.value.findIndex(img => img.id === imageId)
        if (index !== -1) {
          images.value.splice(index, 1)
        }
        
        // Remove from current produit images if loaded
        if (currentProduit.value?.images) {
          const produitIndex = currentProduit.value.images.findIndex(img => img.id === imageId)
          if (produitIndex !== -1) {
            currentProduit.value.images.splice(produitIndex, 1)
          }
        }
        
        return true
      }
      
      throw new Error(response.message)
    } catch (error) {
      console.error('Failed to delete image:', error)
      throw error
    }
  }

  const clearCurrentProduit = () => {
    currentProduit.value = null
    images.value = []
    videos.value = []
    variantes.value = []
  }

  const resetFilters = () => {
    Object.assign(filters, {
      q: '',
      boutique_id: '',
      categorie_id: '',
      actif: '',
      sort: 'created_at',
      direction: 'desc',
      page: 1,
      per_page: 15
    })
  }

  // Upload methods
  const uploadImages = async (productId: string, files: File[]) => {
    try {
      const uploadPromises = files.map(async (file) => {
        const formData = new FormData()
        formData.append('file', file)

        const { data } = await useApi(`/admin/produits/${productId}/images/upload`, {
          method: 'POST',
          body: formData,
          headers: {
            'Accept': 'application/json'
          }
        })

        const response = data.value as any
        if (response.success) {
          images.value.push(response.data)
          return response.data
        }
        throw new Error(response.message || 'Upload failed')
      })

      return await Promise.all(uploadPromises)
    } catch (error) {
      console.error('Error uploading images:', error)
      throw error
    }
  }

  const addVideoUrl = async (productId: string, videoData: { url: string; titre?: string }) => {
    try {
      const { data } = await useApi(`/admin/produits/${productId}/videos`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(videoData)
      })

      const response = data.value as any
      if (response.success) {
        videos.value.push(response.data)
        return response.data
      }
      throw new Error(response.message || 'Failed to add video')
    } catch (error) {
      console.error('Error adding video URL:', error)
      throw error
    }
  }

  const uploadVideos = async (productId: string, files: File[]) => {
    try {
      const uploadPromises = files.map(async (file) => {
        const formData = new FormData()
        formData.append('file', file)

        const { data } = await useApi(`/admin/produits/${productId}/videos/upload`, {
          method: 'POST',
          body: formData,
          headers: {
            'Accept': 'application/json'
          }
        })

        const response = data.value as any
        if (response.success) {
          videos.value.push(response.data)
          return response.data
        }
        throw new Error(response.message || 'Upload failed')
      })

      return await Promise.all(uploadPromises)
    } catch (error) {
      console.error('Error uploading videos:', error)
      throw error
    }
  }

  const deleteVideo = async (productId: string, videoId: string) => {
    try {
      const { data } = await useApi(`/admin/produits/${productId}/videos/${videoId}`, {
        method: 'DELETE'
      })

      const response = data.value as any
      if (response.success) {
        const index = videos.value.findIndex(v => v.id === videoId)
        if (index !== -1) {
          videos.value.splice(index, 1)
        }
        return true
      }
      throw new Error(response.message || 'Failed to delete video')
    } catch (error) {
      console.error('Error deleting video:', error)
      throw error
    }
  }

  const createVariant = async (productId: string, variantData: any) => {
    try {
      const { data } = await useApi(`/admin/produits/${productId}/variantes`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(variantData)
      })

      const response = data.value as any
      if (response.success) {
        variantes.value.push(response.data)
        return response.data
      }
      throw new Error(response.message || 'Failed to create variant')
    } catch (error) {
      console.error('Error creating variant:', error)
      throw error
    }
  }

  const updateVariant = async (productId: string, variantId: string, variantData: any) => {
    try {
      const { data } = await useApi(`/admin/produits/${productId}/variantes/${variantId}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(variantData)
      })

      const response = data.value as any
      if (response.success) {
        const index = variantes.value.findIndex(v => v.id === variantId)
        if (index !== -1) {
          variantes.value[index] = response.data
        }
        return response.data
      }
      throw new Error(response.message || 'Failed to update variant')
    } catch (error) {
      console.error('Error updating variant:', error)
      throw error
    }
  }

  const deleteVariant = async (productId: string, variantId: string) => {
    try {
      const { data } = await useApi(`/admin/produits/${productId}/variantes/${variantId}`, {
        method: 'DELETE'
      })

      const response = data.value as any
      if (response.success) {
        const index = variantes.value.findIndex(v => v.id === variantId)
        if (index !== -1) {
          variantes.value.splice(index, 1)
        }
        return true
      }
      throw new Error(response.message || 'Failed to delete variant')
    } catch (error) {
      console.error('Error deleting variant:', error)
      throw error
    }
  }

  const uploadVariantImage = async (productId: string, variantId: string, file: File) => {
    try {
      const formData = new FormData()
      formData.append('file', file)

      const { data } = await useApi(`/admin/produits/${productId}/variantes/${variantId}/image`, {
        method: 'POST',
        body: formData,
        headers: {
          'Accept': 'application/json'
        }
      })

      const response = data.value as any
      if (response.success) {
        const index = variantes.value.findIndex(v => v.id === variantId)
        if (index !== -1) {
          variantes.value[index] = { ...variantes.value[index], image_url: response.data.image_url }
        }
        return response.data
      }
      throw new Error(response.message || 'Failed to upload variant image')
    } catch (error) {
      console.error('Error uploading variant image:', error)
      throw error
    }
  }

  return {
    // State
    produits,
    currentProduit,
    images,
    videos,
    variantes,
    propositions,
    ruptures,
    loading,
    error,
    pagination,
    filters,

    // Actions
    fetchProduits,
    fetchProduit,
    createProduit,
    updateProduit,
    deleteProduit,
    fetchImages,
    addImage,
    sortImages,
    deleteImage,
    clearCurrentProduit,
    resetFilters,

    // Upload methods
    uploadImages,
    addVideoUrl,
    uploadVideos,
    deleteVideo,
    createVariant,
    updateVariant,
    deleteVariant,
    uploadVariantImage
  }
})
