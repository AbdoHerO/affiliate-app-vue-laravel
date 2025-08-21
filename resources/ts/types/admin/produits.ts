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
  quantite_min?: number
  stock_total?: number | null
  notes_admin?: string | null
  rating_value?: number | null
  rating?: {
    value?: number | null
    max: number
    updated_by?: string | null
    updated_at?: string | null
  }
  created_at: string
  updated_at: string
  
  // Relations
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
}

export interface ProduitVideo {
  id: string
  produit_id: string
  url: string
  titre: string | null
}

export interface ProduitVariante {
  id: string
  produit_id: string
  nom: string
  valeur: string
  prix_vente_variante: number | null
  image_url: string | null
  actif: boolean
}

export interface ProduitRupture {
  id: string
  variante_id: string
  actif: boolean
  created_at: string
}

export interface CreateProduitForm {
  boutique_id: string
  categorie_id: string | null
  titre: string
  description: string | null
  prix_achat: number
  prix_vente: number
  prix_affilie: number | null
  slug?: string
  actif: boolean
}

export interface UpdateProduitForm {
  boutique_id: string
  categorie_id: string | null
  titre: string
  description: string | null
  prix_achat: number
  prix_vente: number
  prix_affilie: number | null
  slug?: string
  actif: boolean
}

export interface ProduitFilters {
  q?: string
  boutique_id?: string
  categorie_id?: string
  actif?: boolean | string
  prix_min?: number
  prix_max?: number
  sort?: string
  direction?: string
  page?: number
  per_page?: number
}

export interface ProduitImageForm {
  url: string
  ordre?: number
}

export interface ProduitVideoForm {
  url: string
  titre?: string
}

export interface ProduitVarianteForm {
  nom: string
  valeur: string
  prix_vente_variante?: number | null
  image_url?: string | null
  actif: boolean
}

export interface BulkSortImageForm {
  id: string
  ordre: number
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
  actif: number
  inactif: number
  total_value: number
}
