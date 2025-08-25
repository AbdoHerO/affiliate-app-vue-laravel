<script setup lang="ts">
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

// Props
interface Props {
  data: Record<string, any>
  loading: boolean
}

const props = defineProps<Props>()

// Emits
const emit = defineEmits<{
  change: [key: string, value: any]
  save: [data: Record<string, any>]
}>()
</script>

<template>
  <VCard>
    <VCardText class="text-center pa-12">
      <VIcon icon="tabler-package" size="64" color="secondary" class="mb-4" />
      <h3 class="text-h5 font-weight-bold mb-3">{{ t('product_settings') }}</h3>
      <VChip color="secondary" variant="tonal" size="large" prepend-icon="tabler-clock">
        {{ t('coming_soon') }}
      </VChip>
    </VCardText>
  </VCard>
</template>
