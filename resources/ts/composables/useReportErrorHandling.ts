/**
 * Report Error Handling Composable
 * Provides comprehensive error handling for report pages
 */

import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'

export interface ReportError {
  type: 'network' | 'validation' | 'server' | 'unknown'
  message: string
  code?: string | number
  timestamp: Date
  context?: any
}

export interface LoadingState {
  [key: string]: boolean
}

export function useReportErrorHandling() {
  const { t } = useI18n()
  
  // State
  const errors = ref<ReportError[]>([])
  const loading = ref<LoadingState>({})
  const retryAttempts = ref<Record<string, number>>({})
  
  // Computed
  const hasErrors = computed(() => errors.value.length > 0)
  const latestError = computed(() => errors.value[errors.value.length - 1])
  const isLoading = computed(() => Object.values(loading.value).some(Boolean))
  
  // Methods
  const addError = (error: Partial<ReportError> & { message: string }) => {
    const reportError: ReportError = {
      type: error.type || 'unknown',
      message: error.message,
      code: error.code,
      timestamp: new Date(),
      context: error.context,
    }
    
    errors.value.push(reportError)
    
    // Limit error history to last 10 errors
    if (errors.value.length > 10) {
      errors.value.shift()
    }
  }
  
  const clearErrors = () => {
    errors.value = []
  }
  
  const removeError = (index: number) => {
    if (index >= 0 && index < errors.value.length) {
      errors.value.splice(index, 1)
    }
  }
  
  const setLoading = (key: string, isLoading: boolean) => {
    loading.value[key] = isLoading
  }
  
  const clearLoading = () => {
    loading.value = {}
  }
  
  const handleApiError = (error: any, context?: string): ReportError => {
    let reportError: ReportError
    
    if (error?.response) {
      // HTTP error response
      const status = error.response.status
      const data = error.response.data
      
      if (status >= 400 && status < 500) {
        reportError = {
          type: 'validation',
          message: data?.message || t('client_error'),
          code: status,
          timestamp: new Date(),
          context: { ...data, context },
        }
      } else if (status >= 500) {
        reportError = {
          type: 'server',
          message: data?.message || t('server_error'),
          code: status,
          timestamp: new Date(),
          context: { ...data, context },
        }
      } else {
        reportError = {
          type: 'unknown',
          message: data?.message || t('unknown_error'),
          code: status,
          timestamp: new Date(),
          context: { ...data, context },
        }
      }
    } else if (error?.code === 'NETWORK_ERROR' || !navigator.onLine) {
      reportError = {
        type: 'network',
        message: t('network_error'),
        code: 'NETWORK_ERROR',
        timestamp: new Date(),
        context: { context },
      }
    } else if (error instanceof Error) {
      reportError = {
        type: 'unknown',
        message: error.message || t('unknown_error'),
        timestamp: new Date(),
        context: { context },
      }
    } else {
      reportError = {
        type: 'unknown',
        message: String(error) || t('unknown_error'),
        timestamp: new Date(),
        context: { context },
      }
    }
    
    addError(reportError)
    return reportError
  }
  
  const withErrorHandling = async <T>(
    operation: () => Promise<T>,
    context?: string,
    loadingKey?: string
  ): Promise<T | null> => {
    if (loadingKey) {
      setLoading(loadingKey, true)
    }
    
    try {
      const result = await operation()
      
      // Reset retry attempts on success
      if (context) {
        retryAttempts.value[context] = 0
      }
      
      return result
    } catch (error) {
      handleApiError(error, context)
      return null
    } finally {
      if (loadingKey) {
        setLoading(loadingKey, false)
      }
    }
  }
  
  const retry = async <T>(
    operation: () => Promise<T>,
    context: string,
    maxAttempts: number = 3,
    loadingKey?: string
  ): Promise<T | null> => {
    const attempts = retryAttempts.value[context] || 0
    
    if (attempts >= maxAttempts) {
      addError({
        type: 'unknown',
        message: t('max_retry_attempts_reached'),
        context: { context, attempts },
      })
      return null
    }
    
    retryAttempts.value[context] = attempts + 1
    
    return withErrorHandling(operation, context, loadingKey)
  }
  
  const getErrorMessage = (error: ReportError): string => {
    switch (error.type) {
      case 'network':
        return t('network_error_message')
      case 'validation':
        return error.message || t('validation_error_message')
      case 'server':
        return t('server_error_message')
      default:
        return error.message || t('unknown_error_message')
    }
  }
  
  const getErrorColor = (error: ReportError): string => {
    switch (error.type) {
      case 'network':
        return 'warning'
      case 'validation':
        return 'info'
      case 'server':
        return 'error'
      default:
        return 'error'
    }
  }
  
  const getErrorIcon = (error: ReportError): string => {
    switch (error.type) {
      case 'network':
        return 'tabler-wifi-off'
      case 'validation':
        return 'tabler-alert-circle'
      case 'server':
        return 'tabler-server-off'
      default:
        return 'tabler-exclamation-mark'
    }
  }
  
  const canRetry = (error: ReportError): boolean => {
    return error.type === 'network' || error.type === 'server'
  }
  
  const formatErrorForDisplay = (error: ReportError) => {
    return {
      message: getErrorMessage(error),
      color: getErrorColor(error),
      icon: getErrorIcon(error),
      canRetry: canRetry(error),
      timestamp: error.timestamp.toLocaleString(),
      code: error.code,
    }
  }
  
  // Cleanup function
  const cleanup = () => {
    clearErrors()
    clearLoading()
    retryAttempts.value = {}
  }
  
  return {
    // State
    errors: readonly(errors),
    loading: readonly(loading),
    retryAttempts: readonly(retryAttempts),
    
    // Computed
    hasErrors,
    latestError,
    isLoading,
    
    // Methods
    addError,
    clearErrors,
    removeError,
    setLoading,
    clearLoading,
    handleApiError,
    withErrorHandling,
    retry,
    getErrorMessage,
    getErrorColor,
    getErrorIcon,
    canRetry,
    formatErrorForDisplay,
    cleanup,
  }
}

// Helper function to create a readonly ref
function readonly<T>(ref: any) {
  return computed(() => ref.value)
}
