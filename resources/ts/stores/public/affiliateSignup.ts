import { defineStore } from 'pinia'
import { ref } from 'vue'
import axios from '@/plugins/axios'

interface SignupData {
  nom_complet: string
  email: string
  telephone: string
  password: string
  password_confirmation: string
  adresse: string
  ville: string
  pays: string
  rib: string
  bank_type: string
  notes?: string
  referral_code?: string
  accept_terms: boolean
}

interface SignupResponse {
  success: boolean
  message: string
  data?: {
    email: string
    expires_at: string
  }
  errors?: Record<string, string[]>
}

interface ResendResponse {
  success: boolean
  message: string
  data?: {
    email: string
    expires_at: string
  }
  errors?: Record<string, string[]>
}

export const useAffiliateSignupStore = defineStore('affiliateSignup', () => {
  const isLoading = ref(false)
  const isResending = ref(false)
  const lastSignupEmail = ref<string | null>(null)
  const lastExpiresAt = ref<string | null>(null)

  /**
   * Submit affiliate signup
   */
  const signup = async (data: SignupData): Promise<SignupResponse> => {
    isLoading.value = true
    
    try {
      const response = await axios.post('/public/affiliates/signup', data)
      
      if (response.data.success) {
        lastSignupEmail.value = response.data.data?.email || null
        lastExpiresAt.value = response.data.data?.expires_at || null
      }
      
      return response.data
    } catch (error: any) {
      if (error.response?.data) {
        return error.response.data
      }
      
      return {
        success: false,
        message: 'Une erreur réseau est survenue. Veuillez réessayer.',
      }
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Resend verification email
   */
  const resendVerification = async (email: string): Promise<ResendResponse> => {
    isResending.value = true
    
    try {
      const response = await axios.post('/public/affiliates/resend-verification', { email })
      
      if (response.data.success) {
        lastSignupEmail.value = response.data.data?.email || null
        lastExpiresAt.value = response.data.data?.expires_at || null
      }
      
      return response.data
    } catch (error: any) {
      if (error.response?.data) {
        return error.response.data
      }
      
      return {
        success: false,
        message: 'Une erreur réseau est survenue. Veuillez réessayer.',
      }
    } finally {
      isResending.value = false
    }
  }

  /**
   * Clear stored data
   */
  const clearData = () => {
    lastSignupEmail.value = null
    lastExpiresAt.value = null
  }

  return {
    // State
    isLoading,
    isResending,
    lastSignupEmail,
    lastExpiresAt,
    
    // Actions
    signup,
    resendVerification,
    clearData,
  }
})
