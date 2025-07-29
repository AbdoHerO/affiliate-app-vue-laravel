<script setup lang="ts">
definePage({
  meta: {
    requiresAuth: true,
    requiresRole: 'affiliate',
  },
})
</script>

<template>
  <div>
    <VCard>
      <VCardText>
        <div class="text-center py-8">
          <VIcon icon="tabler-shopping-cart" size="64" class="mb-4" color="primary" />
          <h2 class="text-h4 mb-2">My Orders</h2>
          <p class="text-body-1 mb-4">View and manage your orders</p>
          <VChip color="warning" variant="tonal">
            Coming Soon
          </VChip>
        </div>
      </VCardText>
    </VCard>
  </div>
</template>
