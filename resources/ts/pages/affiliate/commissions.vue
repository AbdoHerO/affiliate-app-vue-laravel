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
          <VIcon icon="tabler-currency-dollar" size="64" class="mb-4" color="success" />
          <h2 class="text-h4 mb-2">My Commissions</h2>
          <p class="text-body-1 mb-4">View your commission earnings and history</p>
          <VChip color="warning" variant="tonal">
            Coming Soon
          </VChip>
        </div>
      </VCardText>
    </VCard>
  </div>
</template>
