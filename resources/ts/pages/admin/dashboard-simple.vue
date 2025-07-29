<script setup lang="ts">
import { useAuth } from '@/composables/useAuth'

definePage({
  meta: {
    requiresAuth: true,
    requiresRole: 'admin',
  },
})

const { user } = useAuth()
</script>

<template>
  <div>
    <VCard>
      <VCardText>
        <h1>Simple Admin Dashboard</h1>
        <p>Welcome, {{ user?.nom_complet }}!</p>
        <p>This is a minimal dashboard to test for errors.</p>
      </VCardText>
    </VCard>
  </div>
</template>
