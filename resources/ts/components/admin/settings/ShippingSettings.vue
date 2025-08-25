<script setup lang="ts">
import { ref } from 'vue'
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

// Local state
const localData = ref({
  ozon_api_key: '',
  ozon_api_secret: '',
  default_shipping_mode: 'ramassage',
  default_shipping_zone: 'casablanca',
  shipping_fee: 25,
  free_shipping_threshold: 500,
  ...props.data
})

// Handle save
const handleSave = () => {
  emit('save', localData.value)
}
</script>

<template>
  <VCard>
    <VCardText class="text-center pa-12">
      <VIcon icon="tabler-truck-delivery" size="64" color="info" class="mb-4" />
      <h3 class="text-h5 font-weight-bold mb-3">{{ t('shipping_integration') }}</h3>
      <p class="text-body-1 text-medium-emphasis mb-6">
        {{ t('shipping_settings_coming_soon_description') }}
      </p>

      <VChip
        color="info"
        variant="tonal"
        size="large"
        prepend-icon="tabler-clock"
      >
        {{ t('coming_soon') }}
      </VChip>
    </VCardText>
  </VCard>
</template>
