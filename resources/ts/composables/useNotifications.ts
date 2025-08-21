import { ref, reactive } from 'vue'

interface NotificationState {
  show: boolean
  message: string
  color: 'success' | 'error' | 'warning' | 'info'
  timeout: number
}

// Global state - shared across all component instances
const globalSnackbar = reactive<NotificationState>({
  show: false,
  message: '',
  color: 'success',
  timeout: 4000,
})

const globalConfirmDialog = reactive({
  show: false,
  title: '',
  message: '',
  confirmText: 'Confirm',
  cancelText: 'Cancel',
  onConfirm: () => {},
  onCancel: () => {},
})

export function useNotifications() {
  // Show success notification
  const showSuccess = (message: string, timeout = 4000) => {
    Object.assign(globalSnackbar, {
      show: true,
      message,
      color: 'success',
      timeout,
    })
  }

  // Show error notification
  const showError = (message: string, timeout = 4000) => {
    Object.assign(globalSnackbar, {
      show: true,
      message,
      color: 'error',
      timeout,
    })
  }

  // Show warning notification
  const showWarning = (message: string, timeout = 4000) => {
    Object.assign(globalSnackbar, {
      show: true,
      message,
      color: 'warning',
      timeout,
    })
  }

  // Show info notification
  const showInfo = (message: string, timeout = 4000) => {
    Object.assign(globalSnackbar, {
      show: true,
      message,
      color: 'info',
      timeout,
    })
  }

  // Show confirmation dialog
  const showConfirm = (
    title: string,
    message: string,
    onConfirm: () => void,
    onCancel?: () => void,
    confirmText = 'Confirm',
    cancelText = 'Cancel'
  ) => {
    Object.assign(globalConfirmDialog, {
      show: true,
      title,
      message,
      confirmText,
      cancelText,
      onConfirm: () => {
        onConfirm()
        globalConfirmDialog.show = false
      },
      onCancel: () => {
        if (onCancel) onCancel()
        globalConfirmDialog.show = false
      },
    })
  }

  // Hide snackbar
  const hideSnackbar = () => {
    globalSnackbar.show = false
  }

  // Hide confirmation dialog
  const hideConfirm = () => {
    globalConfirmDialog.show = false
  }

  return {
    // State (now reactive and shared)
    snackbar: globalSnackbar,
    confirmDialog: globalConfirmDialog,
    
    // Methods
    showSuccess,
    showError,
    showWarning,
    showInfo,
    showConfirm,
    hideSnackbar,
    hideConfirm,
  }
}
