<script setup lang="ts">
import { useRouter } from 'vue-router'

definePage({
  meta: {
    requiresAuth: true,
    requiresRole: 'admin',
  },
})

const router = useRouter()

// Redirect to pre-orders by default
onMounted(() => {
  router.replace({ name: 'admin-orders-pre' })
})
</script>

<template>
  <div>
    <VCard>
      <VCardText>
        <div class="text-center py-8">
          <VIcon icon="tabler-loader-2" size="64" class="mb-4 animate-spin" color="primary" />
          <h2 class="text-h4 mb-2">Redirection...</h2>
          <p class="text-body-1 mb-4">Redirection vers les pré-commandes</p>
        </div>
      </VCardText>
    </VCard>
  </div>
</template>

<style scoped>
.animate-spin {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
</style>
