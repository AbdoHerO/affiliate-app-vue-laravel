<template>
  <VDialog
    v-model="isOpen"
    max-width="400"
    persistent
  >
    <VCard>
      <VCardTitle class="d-flex align-center">
        <VIcon icon="tabler-alert-circle" class="me-3" color="warning" />
        <span>{{ title }}</span>
      </VCardTitle>

      <VCardText>
        <p>{{ message }}</p>
      </VCardText>

      <VCardActions>
        <VSpacer />
        <VBtn
          variant="outlined"
          :disabled="loading"
          @click="cancel"
        >
          {{ $t('common.cancel') }}
        </VBtn>
        <VBtn
          color="error"
          :loading="loading"
          @click="confirm"
        >
          {{ $t('common.confirm') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

interface Props {
  modelValue: boolean
  title?: string
  message?: string
  loading?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  title: '',
  message: '',
  loading: false
})

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  'confirm': []
  'cancel': []
}>()

const { t } = useI18n()

const isOpen = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
})

const confirm = () => {
  emit('confirm')
}

const cancel = () => {
  isOpen.value = false
  emit('cancel')
}
</script>
