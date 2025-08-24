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
          <VIcon icon="tabler-medal" size="64" class="mb-4" color="warning" />
          <h2 class="text-h4 mb-2">{{ t('affiliate_tiers') }}</h2>
          <p class="text-body-1 mb-4">{{ t('manage_affiliate_tier_levels_benefits') }}</p>
          <VChip color="warning" variant="tonal">
            {{ t('coming_soon') }}
          </VChip>
        </div>
      </VCardText>
    </VCard>
  </div>
</template>
