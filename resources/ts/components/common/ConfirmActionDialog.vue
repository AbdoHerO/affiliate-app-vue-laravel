<template>
  <VDialog
    :model-value="isDialogVisible"
    max-width="500"
    persistent
    @update:model-value="handleCancel"
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
          :disabled="isLoading"
          @click="handleCancel"
        >
          {{ cancelButtonText }}
        </VBtn>
        
        <VBtn
          :color="dialogColor"
          :loading="isLoading"
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

defineProps<Props>()
const emit = defineEmits<Emits>()

const handleConfirm = () => {
  emit('confirm')
}

const handleCancel = () => {
  emit('cancel')
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
