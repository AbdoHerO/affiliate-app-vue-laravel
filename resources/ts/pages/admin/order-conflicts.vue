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
          <VIcon icon="tabler-alert-triangle" size="64" class="mb-4" color="error" />
          <h2 class="text-h4 mb-2">Order Conflicts</h2>
          <p class="text-body-1 mb-4">Manage order conflicts and fraud detection</p>
          <VChip color="warning" variant="tonal">
            Coming Soon
          </VChip>
        </div>
      </VCardText>
    </VCard>
  </div>
</template>
