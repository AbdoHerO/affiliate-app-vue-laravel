<script setup lang="ts">
definePage({
  meta: {
    requiresAuth: true,
    requiresRole: 'admin',
  },
})
</script>

<template>
  <div>
    <VCard>
      <VCardText>
        <div class="text-center py-8">
          <VIcon icon="tabler-medal" size="64" class="mb-4" color="warning" />
          <h2 class="text-h4 mb-2">Affiliate Tiers</h2>
          <p class="text-body-1 mb-4">Manage affiliate tier levels and benefits</p>
          <VChip color="warning" variant="tonal">
            Coming Soon
          </VChip>
        </div>
      </VCardText>
    </VCard>
  </div>
</template>
