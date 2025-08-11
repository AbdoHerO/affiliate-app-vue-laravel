import { ref, computed } from 'vue'
import { useApi } from '@/composables/useApi'
import { useNotifications } from '@/composables/useNotifications'
import { useI18n } from 'vue-i18n'

export type SoftDeleteFilter = 'active' | 'trashed' | 'all'

export interface SoftDeleteOptions {
  entityName: string
  apiEndpoint: string
  onSuccess?: () => void
  onError?: (error: any) => void
}

export function useSoftDelete(options: SoftDeleteOptions) {
  const { t } = useI18n()
  const { showSuccess, showError } = useNotifications()
  
  const isLoading = ref(false)
  const filter = ref<SoftDeleteFilter>('active')

  // Filter options for UI
  const filterOptions = computed(() => [
    { title: t('common.active'), value: 'active' as SoftDeleteFilter },
    { title: t('common.trashed'), value: 'trashed' as SoftDeleteFilter },
    { title: t('common.all'), value: 'all' as SoftDeleteFilter }
  ])

  // Get filter label for display
  const getFilterLabel = (filterValue: SoftDeleteFilter): string => {
    switch (filterValue) {
      case 'active':
        return t('common.active')
      case 'trashed':
        return t('common.trashed')
      case 'all':
        return t('common.all')
      default:
        return t('common.active')
    }
  }

  // Soft delete an item
  const softDelete = async (id: string, itemName?: string): Promise<boolean> => {
    isLoading.value = true
    try {
      const { data, error } = await useApi(`${options.apiEndpoint}/${id}`, {
        method: 'DELETE'
      })

      if (error.value) {
        const errorMessage = error.value.message || t('messages.delete_failed', { entity: options.entityName })
        showError(errorMessage)
        options.onError?.(error.value)
        return false
      }

      const successMessage = itemName 
        ? t('messages.item_deleted_successfully', { entity: options.entityName, name: itemName })
        : t('messages.entity_deleted_successfully', { entity: options.entityName })
      
      showSuccess(successMessage)
      options.onSuccess?.()
      return true
    } catch (err: any) {
      const errorMessage = err.message || t('messages.delete_failed', { entity: options.entityName })
      showError(errorMessage)
      options.onError?.(err)
      return false
    } finally {
      isLoading.value = false
    }
  }

  // Restore a soft deleted item
  const restore = async (id: string, itemName?: string): Promise<boolean> => {
    isLoading.value = true
    try {
      const { data, error } = await useApi(`${options.apiEndpoint}/${id}/restore`, {
        method: 'POST'
      })

      if (error.value) {
        const errorMessage = error.value.message || t('messages.restore_failed', { entity: options.entityName })
        showError(errorMessage)
        options.onError?.(error.value)
        return false
      }

      const successMessage = itemName 
        ? t('messages.item_restored_successfully', { entity: options.entityName, name: itemName })
        : t('messages.entity_restored_successfully', { entity: options.entityName })
      
      showSuccess(successMessage)
      options.onSuccess?.()
      return true
    } catch (err: any) {
      const errorMessage = err.message || t('messages.restore_failed', { entity: options.entityName })
      showError(errorMessage)
      options.onError?.(err)
      return false
    } finally {
      isLoading.value = false
    }
  }

  // Permanently delete an item
  const forceDelete = async (id: string, itemName?: string): Promise<boolean> => {
    isLoading.value = true
    try {
      const { data, error } = await useApi(`${options.apiEndpoint}/${id}/force`, {
        method: 'DELETE'
      })

      if (error.value) {
        const errorMessage = error.value.message || t('messages.permanent_delete_failed', { entity: options.entityName })
        showError(errorMessage)
        options.onError?.(error.value)
        return false
      }

      const successMessage = itemName 
        ? t('messages.item_permanently_deleted', { entity: options.entityName, name: itemName })
        : t('messages.entity_permanently_deleted', { entity: options.entityName })
      
      showSuccess(successMessage)
      options.onSuccess?.()
      return true
    } catch (err: any) {
      const errorMessage = err.message || t('messages.permanent_delete_failed', { entity: options.entityName })
      showError(errorMessage)
      options.onError?.(err)
      return false
    } finally {
      isLoading.value = false
    }
  }

  // Get query parameters for API calls
  const getQueryParams = (additionalParams: Record<string, any> = {}): Record<string, any> => {
    return {
      include_deleted: filter.value,
      ...additionalParams
    }
  }

  // Check if an item is soft deleted
  const isSoftDeleted = (item: any): boolean => {
    return item.deleted_at !== null && item.deleted_at !== undefined
  }

  // Get status color for UI
  const getStatusColor = (item: any): string => {
    return isSoftDeleted(item) ? 'error' : 'success'
  }

  // Get status text for UI
  const getStatusText = (item: any): string => {
    return isSoftDeleted(item) ? t('common.deleted') : t('common.active')
  }

  return {
    // State
    isLoading,
    filter,
    
    // Computed
    filterOptions,
    
    // Methods
    softDelete,
    restore,
    forceDelete,
    getQueryParams,
    isSoftDeleted,
    getStatusColor,
    getStatusText,
    getFilterLabel
  }
}

// Utility function to create soft delete composable for specific entities
export const createSoftDeleteComposable = (entityName: string, apiEndpoint: string) => {
  return (onSuccess?: () => void, onError?: (error: any) => void) => {
    return useSoftDelete({
      entityName,
      apiEndpoint,
      onSuccess,
      onError
    })
  }
}

// Pre-configured composables for common entities
export const useUserSoftDelete = createSoftDeleteComposable('user', '/admin/users')
export const useProductSoftDelete = createSoftDeleteComposable('product', '/admin/produits')
export const useBoutiqueSoftDelete = createSoftDeleteComposable('boutique', '/admin/boutiques')
export const useCategorySoftDelete = createSoftDeleteComposable('category', '/admin/categories')
