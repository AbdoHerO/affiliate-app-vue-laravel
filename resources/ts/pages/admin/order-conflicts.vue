<script setup lang="ts">
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

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
          <h2 class="text-h4 mb-2">{{ t('order_conflicts') }}</h2>
          <p class="text-body-1 mb-4">{{ t('manage_order_conflicts_fraud_detection') }}</p>
          <VChip color="warning" variant="tonal">
            {{ t('coming_soon') }}
          </VChip>
        </div>
      </VCardText>
    </VCard>
  </div>
</template>
