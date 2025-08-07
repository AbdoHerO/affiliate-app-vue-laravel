export interface Produit {
  id: number
  boutique_id: number
  category_id: number
  name: string
  slug: string
  description?: string
  price_purchase?: number
  price: number
  status: 'active' | 'inactive'
  quantity_min?: number
  notes?: string
  created_at: string
  updated_at: string
  
  // Relations
  boutique?: {
    id: number
    nom: string
    slug: string
  }
  category?: {
    id: number
    nom: string
    slug: string
  }
  images?: ProduitImage[]
}

export interface ProduitImage {
  id: number
  produit_id: number
  image_url: string
  order: number
  created_at: string
  updated_at: string
}

export interface CreateProduitForm {
  boutique_id: string | number
  category_id: string | number
  name: string
  slug?: string
  description?: string
  price_purchase?: number | null
  price: string | number
  status: 'active' | 'inactive'
  quantity_min?: number | null
  notes?: string
}

export interface UpdateProduitForm {
  boutique_id: string | number
  category_id: string | number
  name: string
  slug?: string
  description?: string
  price_purchase?: number | null
  price: string | number
  status: 'active' | 'inactive'
  quantity_min?: number | null
  notes?: string
}

export interface ProduitFilters {
  search?: string
  boutique_id?: string | number
  category_id?: string | number
  status?: 'active' | 'inactive' | 'all'
  price_min?: number
  price_max?: number
}

export interface ProduitImageForm {
  image_url: string
  order?: number
}

export interface BulkSortImageForm {
  id: number
  order: number
}

export interface PaginatedProduitsResponse {
  data: Produit[]
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number
  to: number
}

export interface ProduitsStatsResponse {
  total: number
  active: number
  inactive: number
  total_value: number
}
