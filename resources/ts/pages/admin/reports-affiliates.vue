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
          <VIcon icon="tabler-chart-pie" size="64" class="mb-4" color="secondary" />
          <h2 class="text-h4 mb-2">{{ t('affiliate_performance') }}</h2>
          <p class="text-body-1 mb-4">{{ t('view_affiliate_performance_analytics') }}</p>
          <VChip color="warning" variant="tonal">
            {{ t('coming_soon') }}
          </VChip>
        </div>
      </VCardText>
    </VCard>
  </div>
</template>
