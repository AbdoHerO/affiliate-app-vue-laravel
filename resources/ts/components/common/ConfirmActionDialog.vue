<template>
  <VDialog
    :model-value="isDialogVisible"
    max-width="500"
    persistent
    @keydown.enter="handleConfirm"
    @keydown.esc="handleCancel"
  >
    <VCard class="text-center px-10 py-6">
      <VCardText>
        <!-- Icon -->
        <VBtn
          :icon="dialogIcon"
          variant="outlined"
          :color="dialogColor"
          class="my-4"
          style="block-size: 88px; inline-size: 88px; pointer-events: none;"
          size="large"
        >
          <VIcon
            :icon="dialogIcon"
            size="38"
          />
        </VBtn>

        <!-- Title -->
        <h6 class="text-lg font-weight-medium mb-2">
          {{ dialogTitle }}
        </h6>

        <!-- Message -->
        <p class="text-body-1 text-medium-emphasis">
          {{ dialogText }}
        </p>
      </VCardText>

      <!-- Actions -->
      <VCardText class="d-flex align-center justify-center gap-3">
        <VBtn
          variant="outlined"
          color="secondary"
          :disabled="isLoading || isHandling"
          @click="handleCancel"
        >
          {{ cancelButtonText }}
        </VBtn>

        <VBtn
          :color="dialogColor"
          :loading="isLoading || isHandling"
          :disabled="isHandling"
          variant="elevated"
          @click="handleConfirm"
        >
          {{ confirmButtonText }}
        </VBtn>
      </VCardText>
    </VCard>
  </VDialog>
</template>

<script setup lang="ts">
import { ref, nextTick } from 'vue'

interface Props {
  isDialogVisible: boolean
  isLoading: boolean
  dialogTitle: string
  dialogText: string
  dialogIcon: string
  dialogColor: string
  confirmButtonText: string
  cancelButtonText: string
}

interface Emits {
  (e: 'confirm'): void
  (e: 'cancel'): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

// Prevent double-clicks and rapid button presses
const isHandling = ref(false)

const handleConfirm = async () => {
  if (isHandling.value || props.isLoading) {
    console.log('[ConfirmDialog] Ignoring confirm click - already handling or loading')
    return
  }

  isHandling.value = true
  console.log('[ConfirmDialog] Emitting confirm event')

  try {
    emit('confirm')
    // Small delay to prevent rapid clicks
    await nextTick()
    setTimeout(() => {
      isHandling.value = false
    }, 100)
  } catch (error) {
    console.error('[ConfirmDialog] Error in handleConfirm:', error)
    isHandling.value = false
  }
}

const handleCancel = async () => {
  if (isHandling.value) {
    console.log('[ConfirmDialog] Ignoring cancel click - already handling')
    return
  }

  isHandling.value = true
  console.log('[ConfirmDialog] Emitting cancel event')

  try {
    emit('cancel')
    // Small delay to prevent rapid clicks
    await nextTick()
    setTimeout(() => {
      isHandling.value = false
    }, 100)
  } catch (error) {
    console.error('[ConfirmDialog] Error in handleCancel:', error)
    isHandling.value = false
  }
}
</script>

<style scoped>
/* Ensure proper focus management */
.v-dialog .v-card {
  outline: none;
}

/* Smooth transitions */
.v-btn {
  transition: all 0.2s ease-in-out;
}

/* Icon button styling */
.v-btn[style*="block-size: 88px"] {
  border-width: 2px;
  border-style: dashed;
}

/* Responsive adjustments */
@media (max-width: 600px) {
  .v-card {
    margin: 16px;
  }
  
  .px-10 {
    padding-left: 24px !important;
    padding-right: 24px !important;
  }
}
</style>
