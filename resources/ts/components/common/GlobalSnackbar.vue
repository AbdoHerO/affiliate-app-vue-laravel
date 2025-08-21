<script setup lang="ts">
import { useNotifications } from '@/composables/useNotifications'

const { snackbar, hideSnackbar } = useNotifications()
</script>

<template>
  <VSnackbar
    v-model="snackbar.show"
    :color="snackbar.color"
    :timeout="snackbar.timeout"
    location="top right"
    class="global-notification"
    @update:model-value="!$event && hideSnackbar()"
  >
    {{ snackbar.message }}
    
    <template #actions>
      <VBtn
        variant="text"
        @click="hideSnackbar"
      >
        <VIcon icon="tabler-x" />
      </VBtn>
    </template>
  </VSnackbar>
</template>

<style scoped>
:deep(.global-notification .v-snackbar__wrapper) {
  border-radius: 12px;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
}

:deep(.global-notification .v-snackbar__content) {
  padding: 16px 20px;
  font-weight: 500;
}
</style>
