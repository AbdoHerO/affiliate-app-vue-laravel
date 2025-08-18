export interface StockItem {
  product: {
    id: string
    titre: string
    slug: string
    categorie?: {
      id: string
      nom: string
    } | null
    boutique: {
      id: string
      nom: string
    }
  }
  variant?: {
    id: string
    libelle: string
    attributes: string
    image_url?: string | null
  } | null
  metrics: {
    on_hand: number
    reserved: number
    available: number
    incoming: number
    last_movement_at?: string | null
    last_movement_type?: string | null
  }
  kpis: {
    sum_in: number
    sum_out: number
    adjustments: number
    total_movements: number
  }
}

export interface StockSummary {
  totals: {
    products_count: number
    variants_count: number
    total_on_hand: number
    total_reserved: number
    total_available: number
  }
  top_lowest: Array<{
    id: string
    label: string
    qty: number
  }>
  top_movers_in: Array<{
    id: string
    label: string
    qty: number
  }>
  top_movers_out: Array<{
    id: string
    label: string
    qty: number
  }>
}

export interface StockMovement {
  id: string
  type: 'in' | 'out' | 'adjust'
  quantity: number
  reference?: string | null
  created_at: string
  variant: {
    id: string
    libelle: string
  }
  product: {
    id: string
    titre: string
  }
  entrepot?: {
    id: string
    nom: string
  } | null
}

export interface CreateStockMovementForm {
  produit_id: string
  variante_id?: string | null
  entrepot_id?: string | null
  type: 'in' | 'out' | 'adjust'
  quantity: number
  reason: 'purchase' | 'correction' | 'return' | 'damage' | 'manual' | 'delivery_return' | 'cancel'
  note?: string | null
  reference?: string | null
}

export interface StockFilters {
  q?: string
  categorie_id?: string
  boutique_id?: string
  actif?: boolean
  with_variants?: boolean
  min_qty?: number
  max_qty?: number
  page: number
  per_page: number
  sort?: 'product' | 'variant' | 'qty' | 'available' | 'updated_at'
  dir?: 'asc' | 'desc'
}

export interface StockHistoryFilters {
  variante_id?: string
  entrepot_id?: string
  type?: 'in' | 'out' | 'adjust'
  reason?: 'purchase' | 'correction' | 'return' | 'damage' | 'manual' | 'delivery_return' | 'cancel'
  date_from?: string
  date_to?: string
  per_page?: number
}

export interface StockPagination {
  current_page: number
  last_page: number
  per_page: number
  total: number
  from?: number
  to?: number
}

export interface StockResponse {
  success: boolean
  data: StockItem[]
  pagination: StockPagination
}

export interface StockSummaryResponse {
  success: boolean
  data: StockSummary
}

export interface StockMovementResponse {
  success: boolean
  message: string
  data: {
    movement: StockMovement
    snapshot: {
      on_hand: number
      reserved: number
      available: number
      last_movement_at: string
      last_movement_type: string
    }
  }
}

export interface StockHistoryResponse {
  success: boolean
  data: StockMovement[]
  pagination: StockPagination
}

// Movement type options for UI
export const MOVEMENT_TYPES = [
  { value: 'in', label: 'Entrée stock', color: 'success', icon: 'tabler-arrow-up' },
  { value: 'out', label: 'Sortie stock', color: 'error', icon: 'tabler-arrow-down' },
  { value: 'adjust', label: 'Ajustement', color: 'warning', icon: 'tabler-adjustments' },
] as const

// Movement reason options for UI
export const MOVEMENT_REASONS = [
  { value: 'purchase', label: 'Achat' },
  { value: 'correction', label: 'Correction' },
  { value: 'return', label: 'Retour' },
  { value: 'damage', label: 'Dommage' },
  { value: 'manual', label: 'Manuel' },
  { value: 'delivery_return', label: 'Retour livraison' },
  { value: 'cancel', label: 'Annulation' },
] as const

// Sort options for UI
export const SORT_OPTIONS = [
  { value: 'product', label: 'Produit' },
  { value: 'variant', label: 'Variante' },
  { value: 'qty', label: 'Quantité' },
  { value: 'available', label: 'Disponible' },
  { value: 'updated_at', label: 'Dernière mise à jour' },
] as const
