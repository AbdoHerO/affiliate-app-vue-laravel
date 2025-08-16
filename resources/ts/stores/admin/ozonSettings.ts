import { defineStore } from 'pinia'
import { ref, computed, readonly } from 'vue'
import type { AxiosResponse } from 'axios'
import { $api } from '@/utils/api'

export interface OzonSettings {
  customer_id: string | null
  api_key: string | null
}

export interface OzonSettingsResponse {
  success: boolean
  data: OzonSettings
  message?: string
  errors?: Record<string, string[]>
}

export interface TestConnectionResponse {
  success: boolean
  message: string
  data?: any
}

export const useOzonSettingsStore = defineStore('ozonSettings', () => {
  // State
  const settings = ref<OzonSettings>({
    customer_id: null,
    api_key: null,
  })
  
  const loading = ref(false)
  const error = ref<string | null>(null)
  const testResult = ref<TestConnectionResponse | null>(null)

  // Actions
  const fetchSettings = async (): Promise<void> => {
    loading.value = true
    error.value = null

    try {
      const response = await $api('/admin/integrations/ozon/settings', {
        method: 'GET',
      })

      console.log('Settings API Response:', response)

      if (response.success) {
        settings.value = response.data || { customer_id: null, api_key: null }
      } else {
        error.value = response.message || 'Failed to fetch settings'
      }
    } catch (err: any) {
      error.value = err.response?.data?.message || err.message || 'Failed to fetch settings'
      console.error('Error fetching OzonExpress settings:', err)
    } finally {
      loading.value = false
    }
  }

  const fetchSettingsForEdit = async (): Promise<OzonSettings | null> => {
    loading.value = true
    error.value = null

    try {
      const response = await $api('/admin/integrations/ozon/settings/edit', {
        method: 'GET',
      })

      console.log('Settings for edit API Response:', response)

      if (response.success) {
        return response.data || { customer_id: null, api_key: null }
      } else {
        error.value = response.message || 'Failed to fetch settings for editing'
        return null
      }
    } catch (err: any) {
      error.value = err.response?.data?.message || err.message || 'Failed to fetch settings for editing'
      console.error('Error fetching OzonExpress settings for editing:', err)
      return null
    } finally {
      loading.value = false
    }
  }

  const updateSettings = async (data: OzonSettings): Promise<boolean> => {
    loading.value = true
    error.value = null

    try {
      console.log('Updating settings with data:', data)

      const response = await $api('/admin/integrations/ozon/settings', {
        method: 'PUT',
        body: data,
      })

      console.log('Update settings response:', response)

      if (response.success) {
        settings.value = response.data || data
        return true
      } else {
        error.value = response.message || 'Failed to update settings'
        return false
      }
    } catch (err: any) {
      console.error('Error updating OzonExpress settings:', err)
      if (err.response?.status === 422) {
        // Validation errors
        const errors = err.response.data.errors
        if (errors) {
          const errorMessages = Object.values(errors).flat()
          error.value = errorMessages.join(', ')
        } else {
          error.value = err.response.data.message || 'Validation failed'
        }
      } else {
        error.value = err.response?.data?.message || err.message || 'Failed to update settings'
      }
      return false
    } finally {
      loading.value = false
    }
  }

  const testConnection = async (): Promise<boolean> => {
    loading.value = true
    error.value = null
    testResult.value = null
    
    try {
      const response: AxiosResponse<TestConnectionResponse> = await $api('/admin/integrations/ozon/settings/test', {
        method: 'POST',
      })
      
      testResult.value = response.data
      
      if (!response.data.success) {
        error.value = response.data.message
      }
      
      return response.data.success
    } catch (err: any) {
      const message = err.response?.data?.message || err.message || 'Failed to test connection'
      error.value = message
      testResult.value = {
        success: false,
        message,
      }
      console.error('Error testing OzonExpress connection:', err)
      return false
    } finally {
      loading.value = false
    }
  }

  const clearError = (): void => {
    error.value = null
  }

  const clearTestResult = (): void => {
    testResult.value = null
  }

  const reset = (): void => {
    settings.value = {
      customer_id: null,
      api_key: null,
    }
    error.value = null
    testResult.value = null
    loading.value = false
  }

  // Getters
  const hasCredentials = computed(() => {
    return !!(settings.value.customer_id && settings.value.api_key)
  })

  const isConfigured = computed(() => {
    return hasCredentials.value
  })

  return {
    // State
    settings: readonly(settings),
    loading: readonly(loading),
    error: readonly(error),
    testResult: readonly(testResult),
    
    // Getters
    hasCredentials,
    isConfigured,
    
    // Actions
    fetchSettings,
    fetchSettingsForEdit,
    updateSettings,
    testConnection,
    clearError,
    clearTestResult,
    reset,
  }
})
