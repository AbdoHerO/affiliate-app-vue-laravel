<script setup lang="ts">
interface Props {
  modelValue: boolean
  title?: string
  text?: string
  confirmText?: string
  cancelText?: string
  color?: string
  icon?: string
  loading?: boolean
}

interface Emit {
  (e: 'update:modelValue', value: boolean): void
  (e: 'confirm'): void
  (e: 'cancel'): void
}

const props = withDefaults(defineProps<Props>(), {
  title: 'Confirmer l\'action',
  text: 'Êtes-vous sûr de vouloir continuer ?',
  confirmText: 'Confirmer',
  cancelText: 'Annuler',
  color: 'primary',
  icon: 'tabler-alert-circle',
  loading: false,
})

const emit = defineEmits<Emit>()

const updateModelValue = (val: boolean) => {
  emit('update:modelValue', val)
}

const onConfirm = () => {
  emit('confirm')
  updateModelValue(false)
}

const onCancel = () => {
  emit('cancel')
  updateModelValue(false)
}
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="500"
    persistent
    @update:model-value="updateModelValue"
  >
    <VCard class="text-center pa-6">
      <VCardText>
        <!-- Icon -->
        <VAvatar
          size="88"
          :color="props.color"
          variant="tonal"
          class="mb-6"
        >
          <VIcon
            :icon="props.icon"
            size="48"
          />
        </VAvatar>

        <!-- Title -->
        <h5 class="text-h5 mb-4">
          {{ props.title }}
        </h5>

        <!-- Text -->
        <p class="text-body-1 mb-6">
          {{ props.text }}
        </p>
      </VCardText>

      <!-- Actions -->
      <VCardActions class="justify-center gap-3">
        <VBtn
          :color="props.color"
          variant="elevated"
          :loading="props.loading"
          @click="onConfirm"
        >
          {{ props.confirmText }}
        </VBtn>

        <VBtn
          color="secondary"
          variant="outlined"
          :disabled="props.loading"
          @click="onCancel"
        >
          {{ props.cancelText }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
