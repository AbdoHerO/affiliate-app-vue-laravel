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
          <VIcon icon="tabler-credit-card" size="64" class="mb-4" color="success" />
          <h2 class="text-h4 mb-2">{{ t('admin_payments_title') }}</h2>
          <p class="text-body-1 mb-4">{{ t('manage_payment_processing') }}</p>
          <VChip color="warning" variant="tonal">
            {{ t('coming_soon') }}
          </VChip>
        </div>
      </VCardText>
    </VCard>
  </div>
</template>
