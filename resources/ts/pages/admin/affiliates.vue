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
          <VIcon icon="tabler-users-group" size="64" class="mb-4" color="primary" />
          <h2 class="text-h4 mb-2">All Affiliates</h2>
          <p class="text-body-1 mb-4">Manage all affiliates in the system</p>
          <VChip color="warning" variant="tonal">
            Coming Soon
          </VChip>
        </div>
      </VCardText>
    </VCard>
  </div>
</template>
