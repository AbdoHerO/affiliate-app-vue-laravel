import { ref } from 'vue'

interface NotificationState {
  show: boolean
  message: string
  color: 'success' | 'error' | 'warning' | 'info'
  timeout: number
}

export function useNotifications() {
  // Snackbar state
  const snackbar = ref<NotificationState>({
    show: false,
    message: '',
    color: 'success',
    timeout: 4000,
  })

  // Confirmation dialog state
  const confirmDialog = ref({
    show: false,
    title: '',
    message: '',
    confirmText: 'Confirm',
    cancelText: 'Cancel',
    onConfirm: () => {},
    onCancel: () => {},
  })

  // Show success notification
  const showSuccess = (message: string, timeout = 4000) => {
    snackbar.value = {
      show: true,
      message,
      color: 'success',
      timeout,
    }
  }

  // Show error notification
  const showError = (message: string, timeout = 4000) => {
    snackbar.value = {
      show: true,
      message,
      color: 'error',
      timeout,
    }
  }

  // Show warning notification
  const showWarning = (message: string, timeout = 4000) => {
    snackbar.value = {
      show: true,
      message,
      color: 'warning',
      timeout,
    }
  }

  // Show info notification
  const showInfo = (message: string, timeout = 4000) => {
    snackbar.value = {
      show: true,
      message,
      color: 'info',
      timeout,
    }
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
    confirmDialog.value = {
      show: true,
      title,
      message,
      confirmText,
      cancelText,
      onConfirm: () => {
        onConfirm()
        confirmDialog.value.show = false
      },
      onCancel: () => {
        if (onCancel) onCancel()
        confirmDialog.value.show = false
      },
    }
  }

  // Hide snackbar
  const hideSnackbar = () => {
    snackbar.value.show = false
  }

  // Hide confirmation dialog
  const hideConfirm = () => {
    confirmDialog.value.show = false
  }

  return {
    // State
    snackbar: readonly(snackbar),
    confirmDialog: readonly(confirmDialog),
    
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
