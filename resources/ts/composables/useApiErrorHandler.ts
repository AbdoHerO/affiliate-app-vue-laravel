/**
 * Composable for handling API errors consistently across components
 */

import { handle401Unauthorized, is401Error, handleApiError } from '@/utils/authHandler'
import { useNotifications } from './useNotifications'

export function useApiErrorHandler() {
  const { showError } = useNotifications()

  /**
   * Handle API errors with proper 401 handling and user notifications
   */
  const handleError = async (error: any, customMessage?: string) => {
    console.error('🚫 [API Error Handler] Handling error:', error)

    // Handle 401 errors first
    if (is401Error(error)) {
      await handle401Unauthorized()
      return
    }

    // Handle other HTTP errors
    if (error?.response) {
      const status = error.response.status
      const data = error.response.data

      switch (status) {
        case 403:
          showError(customMessage || 'Accès refusé. Vous n\'avez pas les permissions nécessaires.')
          break
        case 404:
          showError(customMessage || 'Ressource non trouvée.')
          break
        case 422:
          // Validation errors - let the component handle them
          if (data?.errors) {
            const firstError = Object.values(data.errors)[0]
            if (Array.isArray(firstError) && firstError.length > 0) {
              showError(customMessage || firstError[0])
            } else {
              showError(customMessage || 'Erreur de validation.')
            }
          } else {
            showError(customMessage || data?.message || 'Erreur de validation.')
          }
          break
        case 429:
          showError(customMessage || 'Trop de requêtes. Veuillez patienter avant de réessayer.')
          break
        case 500:
          showError(customMessage || 'Erreur serveur. Veuillez réessayer plus tard.')
          break
        default:
          showError(customMessage || data?.message || `Erreur ${status}. Veuillez réessayer.`)
      }
    } else if (error?.message) {
      // Network or other errors
      if (error.message.includes('Network Error') || error.message.includes('fetch')) {
        showError(customMessage || 'Erreur de connexion. Vérifiez votre connexion internet.')
      } else {
        showError(customMessage || error.message)
      }
    } else {
      // Unknown errors
      showError(customMessage || 'Une erreur inattendue s\'est produite.')
    }

    // Re-throw the error for component-level handling if needed
    throw error
  }

  /**
   * Wrapper for API calls with automatic error handling
   */
  const withErrorHandling = async <T>(
    apiCall: () => Promise<T>,
    customErrorMessage?: string
  ): Promise<T | null> => {
    try {
      return await apiCall()
    } catch (error) {
      await handleError(error, customErrorMessage)
      return null
    }
  }

  /**
   * Handle errors without re-throwing (for fire-and-forget operations)
   */
  const handleErrorSilently = async (error: any, customMessage?: string) => {
    try {
      await handleError(error, customMessage)
    } catch {
      // Ignore re-thrown error
    }
  }

  return {
    handleError,
    withErrorHandling,
    handleErrorSilently,
    handle401Unauthorized,
    is401Error,
    handleApiError
  }
}
