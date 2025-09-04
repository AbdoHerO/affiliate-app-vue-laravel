import { ref, computed, inject } from 'vue'

// Global instance for injection / fallback singleton (declared early so factory can self-register)
let globalConfirmAction: ReturnType<typeof useConfirmAction> | null
import { useI18n } from 'vue-i18n'

export interface ConfirmOptions {
  title?: string
  text?: string
  type?: 'info' | 'warning' | 'danger' | 'success'
  confirmText?: string
  cancelText?: string
  icon?: string
  color?: string
  loading?: boolean
}

export interface ConfirmPreset {
  title: string
  text: string
  type: 'info' | 'warning' | 'danger' | 'success'
  confirmText: string
  cancelText: string
  icon: string
  color: string
}

export interface ConfirmPresets {
  create: (entity: string) => ConfirmPreset
  update: (entity: string, name?: string) => ConfirmPreset
  delete: (entity: string, name?: string) => ConfirmPreset
  restore: (entity: string, name?: string) => ConfirmPreset
  permanentDelete: (entity: string, name?: string) => ConfirmPreset
  bulkDelete: (entity: string, count: number) => ConfirmPreset
}

export function useConfirmAction() {
  const { t } = useI18n()

  // Dialog state
  const isDialogVisible = ref(false)
  const dialogOptions = ref<ConfirmOptions>({})
  const isLoading = ref(false)

  // Promise resolvers for async behavior with better tracking
  let resolvePromise: ((value: boolean) => void) | null = null
  let rejectPromise: ((reason?: any) => void) | null = null
  let currentPromiseId: string | null = null

  // Track if dialog is in the middle of an operation
  const isProcessing = ref(false)

  // Computed properties for dialog display
  const dialogTitle = computed(() => dialogOptions.value.title || t('common.confirm'))
  const dialogText = computed(() => dialogOptions.value.text || t('common.are_you_sure'))
  const dialogType = computed(() => dialogOptions.value.type || 'warning')
  const dialogIcon = computed(() => {
    if (dialogOptions.value.icon) return dialogOptions.value.icon
    
    switch (dialogType.value) {
      case 'danger': return 'tabler-alert-triangle'
      case 'warning': return 'tabler-alert-circle'
      case 'success': return 'tabler-check'
      case 'info': return 'tabler-info-circle'
      default: return 'tabler-help'
    }
  })
  
  const dialogColor = computed(() => {
    if (dialogOptions.value.color) return dialogOptions.value.color
    
    switch (dialogType.value) {
      case 'danger': return 'error'
      case 'warning': return 'warning'
      case 'success': return 'success'
      case 'info': return 'info'
      default: return 'warning'
    }
  })
  
  const confirmButtonText = computed(() => 
    dialogOptions.value.confirmText || t('common.confirm')
  )
  
  const cancelButtonText = computed(() => 
    dialogOptions.value.cancelText || t('common.cancel')
  )

  // Predefined presets for common actions
  const presets: ConfirmPresets = {
    create: (entity: string) => ({
      title: t('confirm.create_title'),
      text: t('confirm.create_text', { entity }),
      type: 'info',
      confirmText: t('common.create'),
      cancelText: t('common.cancel'),
      icon: 'tabler-plus',
      color: 'primary'
    }),
    
    update: (entity: string, name?: string) => ({
      title: t('confirm.update_title'),
      text: name 
        ? t('confirm.update_text_with_name', { entity, name })
        : t('confirm.update_text', { entity }),
      type: 'info',
      confirmText: t('common.save'),
      cancelText: t('common.cancel'),
      icon: 'tabler-edit',
      color: 'primary'
    }),
    
    delete: (entity: string, name?: string) => ({
      title: t('confirm.delete_title'),
      text: name
        ? t('confirm.delete_text_with_name', { entity, name })
        : t('confirm.delete_text', { entity }),
      type: 'danger',
      confirmText: t('common.delete'),
      cancelText: t('common.cancel'),
      icon: 'tabler-trash',
      color: 'error'
    }),

    restore: (entity: string, name?: string) => ({
      title: t('confirm.restore_title'),
      text: name
        ? t('confirm.restore_text_with_name', { entity, name })
        : t('confirm.restore_text', { entity }),
      type: 'success',
      confirmText: t('common.restore'),
      cancelText: t('common.cancel'),
      icon: 'tabler-restore',
      color: 'success'
    }),

    permanentDelete: (entity: string, name?: string) => ({
      title: t('confirm.permanent_delete_title'),
      text: name
        ? t('confirm.permanent_delete_text_with_name', { entity, name })
        : t('confirm.permanent_delete_text', { entity }),
      type: 'danger',
      confirmText: t('common.permanent_delete'),
      cancelText: t('common.cancel'),
      icon: 'tabler-trash-x',
      color: 'error'
    }),
    
    bulkDelete: (entity: string, count: number) => ({
      title: t('confirm.bulk_delete_title'),
      text: t('confirm.bulk_delete_text', { entity, count }),
      type: 'danger',
      confirmText: t('common.delete_all'),
      cancelText: t('common.cancel'),
      icon: 'tabler-trash',
      color: 'error'
    })
  }

  // Main confirm function with improved error handling
  const confirm = async (options: ConfirmOptions | ConfirmPreset): Promise<boolean> => {
    // Generate unique ID for this promise
    const promiseId = Math.random().toString(36).substring(2, 11)

    // If dialog is already visible, wait a bit and try again (instead of rejecting immediately)
    if (isDialogVisible.value || isProcessing.value) {
      console.warn('[ConfirmAction] Dialog already active, waiting for it to close...')
      // Wait for current dialog to close, then try again
      await new Promise(resolve => setTimeout(resolve, 100))

      // If still active after waiting, force cleanup and proceed
      if (isDialogVisible.value || isProcessing.value) {
        console.warn('[ConfirmAction] Force cleaning up previous dialog')
        cleanup()
      }
    }

    // Clean up any previous unresolved promises
    if (resolvePromise || rejectPromise) {
      console.warn('[ConfirmAction] Cleaning up previous unresolved promise')
      if (resolvePromise) resolvePromise(false)
      resolvePromise = null
      rejectPromise = null
    }

    return new Promise((resolve, reject) => {
      try {
        currentPromiseId = promiseId
        dialogOptions.value = { ...options }
        isDialogVisible.value = true
        isLoading.value = false
        isProcessing.value = true

        resolvePromise = (value: boolean) => {
          // Only resolve if this is still the current promise
          if (currentPromiseId === promiseId) {
            resolve(value)
          }
        }
        rejectPromise = (reason?: any) => {
          // Only reject if this is still the current promise
          if (currentPromiseId === promiseId) {
            reject(reason)
          }
        }

        // Auto-cleanup after 30 seconds to prevent memory leaks
        setTimeout(() => {
          if (currentPromiseId === promiseId && resolvePromise) {
            console.warn('[ConfirmAction] Auto-cleanup: Dialog timeout after 30s')
            resolvePromise(false)
            cleanup()
          }
        }, 30000)

      } catch (error) {
        console.error('[ConfirmAction] Error setting up dialog:', error)
        reject(error)
      }
    })
  }

  // Cleanup function to reset all state
  const cleanup = () => {
    resolvePromise = null
    rejectPromise = null
    currentPromiseId = null
    isProcessing.value = false
    isDialogVisible.value = false
    isLoading.value = false
    dialogOptions.value = {}
  }

  // Handle confirm action with better error handling
  const handleConfirm = () => {
    try {
      if (!resolvePromise) {
        closeDialog()
        return
      }

      // CRITICAL FIX: Store resolver BEFORE cleanup to prevent it from being nullified
      const resolver = resolvePromise
      resolver(true)
      cleanup()

    } catch (error) {
      console.error('[ConfirmAction] Error in handleConfirm:', error)
      cleanup()
    }
  }

  // Handle cancel action with better error handling
  const handleCancel = () => {
    try {
      if (!resolvePromise) {
        closeDialog()
        return
      }

      // CRITICAL FIX: Store resolver BEFORE cleanup to prevent it from being nullified
      const resolver = resolvePromise
      resolver(false)
      cleanup()

    } catch (error) {
      console.error('[ConfirmAction] Error in handleCancel:', error)
      cleanup()
    }
  }

  // Close dialog (legacy method, now uses cleanup)
  const closeDialog = () => {
    cleanup()
  }

  // Set loading state (useful for async operations)
  const setLoading = (loading: boolean) => {
    isLoading.value = loading
  }

  const api = {
    // State
    isDialogVisible,
    isLoading,

    // Computed properties
    dialogTitle,
    dialogText,
    dialogType,
    dialogIcon,
    dialogColor,
    confirmButtonText,
    cancelButtonText,

    // Methods
    confirm,
    handleConfirm,
    handleCancel,
    closeDialog,
    setLoading,

    // Presets
    presets
  }

  // Self-register as global singleton if none exists yet (first created wins)
  if (!globalConfirmAction) globalConfirmAction = api

  return api
}

// (globalConfirmAction declared above)

// Composable for components to use the global confirm action
export function useGlobalConfirm() {
  // Prefer provided instance (from GlobalConfirmProvider)
  const provided = inject<ReturnType<typeof useConfirmAction> | null>('confirmAction', null)
  if (provided) return provided

  // Fallback singleton (in case provider not mounted yet)
  if (!globalConfirmAction) globalConfirmAction = useConfirmAction()
  return globalConfirmAction
}

// Helper function to quickly confirm actions with presets
export function useQuickConfirm() {
  // Reuse global/injected singleton so only one dialog exists app-wide
  const confirmAction = useGlobalConfirm()

  const confirmCreate = (entity: string) => confirmAction.confirm(confirmAction.presets.create(entity))
  const confirmUpdate = (entity: string, name?: string) => confirmAction.confirm(confirmAction.presets.update(entity, name))
  const confirmDelete = (entity: string, name?: string) => confirmAction.confirm(confirmAction.presets.delete(entity, name))
  const confirmRestore = (entity: string, name?: string) => confirmAction.confirm(confirmAction.presets.restore(entity, name))
  const confirmPermanentDelete = (entity: string, name?: string) => confirmAction.confirm(confirmAction.presets.permanentDelete(entity, name))
  const confirmBulkDelete = (entity: string, count: number) => confirmAction.confirm(confirmAction.presets.bulkDelete(entity, count))

  return {
    confirmCreate,
    confirmUpdate,
    confirmDelete,
    confirmRestore,
    confirmPermanentDelete,
    confirmBulkDelete,
    confirm: confirmAction.confirm,
    // Dialog reactive refs (no need to destructure to keep reactivity)
    isDialogVisible: confirmAction.isDialogVisible,
    isLoading: confirmAction.isLoading,
    dialogTitle: confirmAction.dialogTitle,
    dialogText: confirmAction.dialogText,
    dialogIcon: confirmAction.dialogIcon,
    dialogColor: confirmAction.dialogColor,
    confirmButtonText: confirmAction.confirmButtonText,
    cancelButtonText: confirmAction.cancelButtonText,
    handleConfirm: confirmAction.handleConfirm,
    handleCancel: confirmAction.handleCancel,
    setLoading: confirmAction.setLoading,
  }
}
