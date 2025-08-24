import { defineStore } from 'pinia'
import { ref } from 'vue'
import axios from '@/plugins/axios'

export interface SendParcelPayload {
  commande_id?: string
  receiver?: string
  phone?: string
  city?: string
  address?: string
  price?: number
  nature?: string
  stock?: number
  products?: Array<{
    ref: string
    qnty: number
  }>
}

export interface TrackParcelPayload {
  tracking_number: string
}

export interface ParcelResult {
  id: string
  tracking_number: string
  status: string
  receiver?: string
  phone?: string
  city_name?: string
  address?: string
  price?: number
  last_status_text?: string
  last_status_code?: string
  last_status_at?: string
  created_at: string
  meta?: any
}

export interface ApiResponse<T = any> {
  success: boolean
  message: string
  data?: T
  tracking_number?: string
  errors?: Record<string, string[]>
}

export const useOzonDebugStore = defineStore('ozonDebug', () => {
  // State
  const loading = ref({
    sendParcel: false,
    track: false,
  })

  const lastSentParcel = ref<ParcelResult | null>(null)
  const lastTrackedParcel = ref<ParcelResult | null>(null)
  const trackingHistory = ref<any[]>([])

  // Actions
  const sendParcel = async (payload: SendParcelPayload): Promise<ApiResponse<ParcelResult>> => {
    loading.value.sendParcel = true
    try {
      const response = await axios.post('/admin/shipping/ozon/debug/send-parcel', payload)
      
      if (response.data.success) {
        lastSentParcel.value = response.data.data
      }
      
      return response.data
    } catch (error: any) {
      if (error.response?.data) {
        return error.response.data
      }
      return {
        success: false,
        message: error.message || 'Une erreur est survenue',
      }
    } finally {
      loading.value.sendParcel = false
    }
  }

  const track = async (payload: TrackParcelPayload): Promise<ApiResponse> => {
    loading.value.track = true
    try {
      const response = await axios.post('/admin/shipping/ozon/debug/track', payload)
      
      if (response.data.success) {
        lastTrackedParcel.value = response.data.data?.parcel || null
        if (response.data.data?.tracking_info) {
          trackingHistory.value.unshift({
            tracking_number: payload.tracking_number,
            timestamp: new Date().toISOString(),
            data: response.data.data,
          })
        }
      }
      
      return response.data
    } catch (error: any) {
      if (error.response?.data) {
        return error.response.data
      }
      return {
        success: false,
        message: error.message || 'Une erreur est survenue',
      }
    } finally {
      loading.value.track = false
    }
  }

  const clearHistory = () => {
    lastSentParcel.value = null
    lastTrackedParcel.value = null
    trackingHistory.value = []
  }

  return {
    // State
    loading,
    lastSentParcel,
    lastTrackedParcel,
    trackingHistory,

    // Actions
    sendParcel,
    track,
    clearHistory,
  }
})
