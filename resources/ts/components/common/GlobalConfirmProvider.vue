<template>
  <ConfirmActionDialog
    :is-dialog-visible="isDialogVisible"
    :is-loading="isLoading"
    :dialog-title="dialogTitle"
    :dialog-text="dialogText"
    :dialog-icon="dialogIcon"
    :dialog-color="dialogColor"
    :confirm-button-text="confirmButtonText"
    :cancel-button-text="cancelButtonText"
    @confirm="handleConfirm"
    @cancel="handleCancel"
  />
</template>

<script setup lang="ts">
import { provide } from 'vue'
import { useConfirmAction } from '@/composables/useConfirmAction'
import ConfirmActionDialog from './ConfirmActionDialog.vue'

// Create a single instance of the confirm action
const confirmAction = useConfirmAction()

// Destructure for template usage
const {
  isDialogVisible,
  isLoading,
  dialogTitle,
  dialogText,
  dialogIcon,
  dialogColor,
  confirmButtonText,
  cancelButtonText,
  handleConfirm: originalHandleConfirm,
  handleCancel: originalHandleCancel
} = confirmAction

// Wrap handlers with minimal logging for debugging
const handleConfirm = () => {
  originalHandleConfirm()
}

const handleCancel = () => {
  originalHandleCancel()
}

// Provide the confirm action globally
provide('confirmAction', confirmAction)
</script>
