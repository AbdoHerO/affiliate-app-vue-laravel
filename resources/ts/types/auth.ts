export interface User {
  id: string // UUID
  nom_complet: string
  email: string
  telephone?: string
  adresse?: string
  photo_profil?: string
  statut: 'actif' | 'inactif' | 'bloque'
  email_verifie: boolean
  kyc_statut: 'non_requis' | 'en_attente' | 'valide' | 'refuse'
  rib?: string
  bank_type?: string
  roles: string[]
  permissions: string[]
  remember_token?: string
  created_at?: string
  updated_at?: string
}

export interface LoginCredentials {
  email: string
  password: string
}

export interface RegisterData {
  name: string
  email: string
  password: string
  password_confirmation: string
  role: 'affiliate' // Only affiliate registration allowed
}

export interface AuthResponse {
  message: string
  user: User
  token: string
}

export interface ApiError {
  message: string
  errors?: Record<string, string[]>
}

export type UserRole = 'admin' | 'affiliate'

export type Permission = 
  | 'manage users'
  | 'manage affiliates'
  | 'manage products'
  | 'manage orders'
  | 'manage payments'
  | 'view reports'
  | 'manage settings'
  | 'create orders'
  | 'view own orders'
  | 'view own commissions'
  | 'view marketing materials'
  | 'update profile'
