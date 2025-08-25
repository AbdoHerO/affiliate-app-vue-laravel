export interface AppSettings {
  // Application Information
  app_name: string
  app_description: string
  app_slogan: string
  app_keywords: string
  app_version: string

  // Company Information
  company_name: string
  company_email: string
  company_phone: string
  company_address: string
  company_website: string

  // Social Media Links
  company_social_facebook: string
  company_social_instagram: string
  company_social_twitter: string

  // Branding & Appearance
  app_logo: string
  app_favicon: string
  primary_color: string
  secondary_color: string
  login_background_image: string
  signup_background_image: string
  app_theme: 'light' | 'dark' | 'system'

  // Localization
  default_language: string
  timezone: string
  currency: string
  currency_symbol: string
  date_format: string
  time_format: '12' | '24'
  number_format: string

  // System Settings
  maintenance_mode: boolean
  registration_enabled: boolean
  email_verification_required: boolean
  kyc_verification_required: boolean
  max_file_upload_size: number
  allowed_file_types: string
  session_timeout: number
  password_min_length: number
  password_require_special: boolean

  // Business Configuration
  commission_calculation_method: 'per_order' | 'per_product' | 'percentage_of_sales'
  default_commission_rate: number
  tier_1_bronze_rate: number
  tier_2_silver_rate: number
  tier_3_gold_rate: number
  tier_1_bronze_threshold: number
  tier_2_silver_threshold: number
  tier_3_gold_threshold: number

  // Order Management
  auto_confirmation_timeout: number
  order_number_prefix: string
  order_number_format: string
  return_window: number
  refund_processing_time: number

  // Financial Settings
  payment_gateway: string
  payment_currency: string
  tax_rate: number
  shipping_cost: number
  free_shipping_threshold: number

  // Communication Settings
  email_notifications_enabled: boolean
  sms_notifications_enabled: boolean
  push_notifications_enabled: boolean
  newsletter_enabled: boolean

  // Security Settings
  two_factor_auth_enabled: boolean
  login_attempts_limit: number
  account_lockout_duration: number
  password_expiry_days: number

  // System Performance
  cache_enabled: boolean
  cache_duration: number
  api_rate_limit: number
  max_concurrent_users: number
}

export interface SettingsCategory {
  key: string
  name: string
  description: string
  icon: string
  fields: SettingsField[]
}

export interface SettingsField {
  key: string
  name: string
  description?: string
  type: 'string' | 'number' | 'boolean' | 'select' | 'color' | 'file' | 'textarea'
  default: any
  validation?: {
    required?: boolean
    min?: number
    max?: number
    pattern?: string
    options?: Array<{ value: any; label: string }>
  }
  is_public?: boolean
  is_encrypted?: boolean
}

export interface SettingsUpdateRequest {
  category: string
  data: Record<string, any>
}

export interface SettingsResponse {
  success: boolean
  message: string
  data: Partial<AppSettings>
}
