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
          <VIcon icon="tabler-chart-line" size="64" class="mb-4" color="info" />
          <h2 class="text-h4 mb-2">Sales Reports</h2>
          <p class="text-body-1 mb-4">View detailed sales analytics and reports</p>
          <VChip color="warning" variant="tonal">
            Coming Soon
          </VChip>
        </div>
      </VCardText>
    </VCard>
  </div>
</template>
