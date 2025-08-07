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

export interface ProduitFormData {
  boutique_id: string
  categorie_id: string | null
  titre: string
  description: string | null
  prix_achat: number | null
  prix_vente: number | null
  prix_affilie: number | null
  quantite_min: number | null
  notes_admin: string | null
  slug?: string
  actif: boolean
}

export interface ProduitFilters {
  q?: string
  boutique_id?: string
  categorie_id?: string
  actif?: boolean | string
  sort?: string
  direction?: string
  page?: number
  per_page?: number
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
    direction: 'desc',
    page: 1,
    per_page: 15
  })

  // Actions
  const fetchProduits = async (params: ProduitFilters = {}) => {
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
      const url = `/admin/produits${searchParams.toString() ? `?${searchParams.toString()}` : ''}`
      const { data: responseData, error: apiError } = await useApi(url)

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
    } catch (error) {
      console.error('Failed to fetch produits:', error)
      throw error
    } finally {
      loading.value = false
    }
  }

  const fetchProduit = async (id: string) => {
    loading.value = true
    try {
      const { data: responseData, error: apiError } = await useApi(`/admin/produits/${id}`)

      if (apiError.value) {
        const message = (apiError.value as any).message || 'Error fetching product'
        error.value = message
        throw apiError.value
      }

      const response = responseData.value as any
      if (response.success) {
        currentProduit.value = response.data

        // Set images if included
        if (response.data.images) {
          images.value = response.data.images
        }

        return response.data
      } else {
        error.value = response.message
        throw new Error(response.message)
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
      const { data: responseData, error: apiError } = await useApi('/admin/produits', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
      })

      if (apiError.value) {
        const message = (apiError.value as any).message || 'Error creating product'
        error.value = message
        throw apiError.value
      }

      const response = responseData.value as any
      if (response.success) {
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
      
      if (response.data.success) {
        // Update in local state
        const index = produits.value.findIndex(p => p.id === id)
        if (index !== -1) {
          produits.value[index] = response.data.data
        }
        
        // Update current produit if it matches
        if (currentProduit.value?.id === id) {
          currentProduit.value = response.data.data
        }
        
        return response.data.data
      }
      
      throw new Error(response.data.message)
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
      
      if (response.data.success) {
        // Add to local images state
        images.value.push(response.data.data)
        
        // Update current produit images if loaded
        if (currentProduit.value?.id === produitId && currentProduit.value.images) {
          currentProduit.value.images.push(response.data.data)
        }
        
        return response.data.data
      }
      
      throw new Error(response.data.message)
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
      
      if (response.data.success) {
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
      
      throw new Error(response.data.message)
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
      
      if (response.data.success) {
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
      
      throw new Error(response.data.message)
    } catch (error) {
      console.error('Failed to delete image:', error)
      throw error
    }
  }

  const clearCurrentProduit = () => {
    currentProduit.value = null
    images.value = []
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

  return {
    // State
    produits,
    currentProduit,
    images,
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
    resetFilters
  }
})
