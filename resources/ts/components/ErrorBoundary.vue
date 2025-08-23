<script setup lang="ts">
import { ref, onErrorCaptured } from 'vue'

const error = ref<Error | null>(null)
const hasError = ref(false)

onErrorCaptured((err: Error) => {
  console.error('Error caught by boundary:', err)
  error.value = err
  hasError.value = true
  return false // Prevent the error from propagating further
})

const retry = () => {
  error.value = null
  hasError.value = false
}
</script>

<template>
  <div v-if="hasError" class="error-boundary">
    <VCard class="mx-auto" max-width="500">
      <VCardTitle class="text-error">
        <VIcon icon="tabler-alert-triangle" class="me-2" />
        Something went wrong
      </VCardTitle>
      <VCardText>
        <p class="mb-4">
          An error occurred while loading this page. This might be a temporary issue.
        </p>
        <VAlert type="error" variant="tonal" class="mb-4">
          {{ error?.message || 'Unknown error' }}
        </VAlert>
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn color="primary" @click="retry">
          <VIcon start icon="tabler-refresh" />
          Try Again
        </VBtn>
        <VBtn variant="outlined" @click="() => $router.go(-1)">
          <VIcon start icon="tabler-arrow-left" />
          Go Back
        </VBtn>
      </VCardActions>
    </VCard>
  </div>
  <slot v-else />
</template>

<style scoped>
.error-boundary {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 400px;
  padding: 2rem;
}
</style>
