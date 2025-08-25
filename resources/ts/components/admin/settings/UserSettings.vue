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
  change: [key, string, value: any]
  save: [data: Record<string, any>]
}>()

// Handle save
const handleSave = () => {
  emit('save', {})
}
</script>

<template>
  <VCard>
    <VCardText class="text-center pa-12">
      <VIcon icon="tabler-users" size="64" color="warning" class="mb-4" />
      <h3 class="text-h5 font-weight-bold mb-3">{{ t('user_management') }}</h3>
      <p class="text-body-1 text-medium-emphasis mb-6">
        {{ t('user_settings_coming_soon_description') }}
      </p>

      <VChip
        color="warning"
        variant="tonal"
        size="large"
        prepend-icon="tabler-clock"
      >
        {{ t('coming_soon') }}
      </VChip>
    </VCardText>
  </VCard>
</template>
