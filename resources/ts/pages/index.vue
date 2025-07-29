<script setup lang="ts">
import { useAuth } from '@/composables/useAuth'
import { useRouter } from 'vue-router'

const { isAuthenticated, hasRole } = useAuth()
const router = useRouter()

// Redirect authenticated users to their appropriate dashboard
onMounted(() => {
  if (isAuthenticated) {
    if (hasRole('admin')) {
      router.push({ name: 'admin-dashboard' })
    } else if (hasRole('affiliate')) {
      router.push({ name: 'affiliate-dashboard' })
    }
  } else {
    // Redirect unauthenticated users to login
    router.push({ name: 'login' })
  }
})
</script>

<template>
  <div class="d-flex align-center justify-center min-h-screen">
    <VCard
      class="pa-8 text-center"
      max-width="400"
    >
      <VCardText>
        <VProgressCircular
          indeterminate
          color="primary"
          size="64"
          class="mb-4"
        />
        <h3 class="text-h5 mb-2">
          {{ $t('Loading') }}...
        </h3>
        <p class="text-body-2 mb-0">
          {{ $t('Redirecting to your dashboard') }}
        </p>
      </VCardText>
    </VCard>
  </div>
</template>
