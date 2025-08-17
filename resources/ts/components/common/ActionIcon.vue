<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useQuickConfirm } from '@/composables/useConfirmAction'

interface Props {
  icon: string
  label: string // i18n key or plain text
  variant?: 'default' | 'primary' | 'success' | 'warning' | 'danger'
  disabled?: boolean
  size?: 'x-small' | 'small' | 'default' | 'large' | 'x-large'
  confirm?: boolean // Only for destructive actions
  confirmTitle?: string
  confirmMessage?: string
  loading?: boolean
}

interface Emits {
  (e: 'click', event: MouseEvent): void
}

const props = withDefaults(defineProps<Props>(), {
  variant: 'default',
  disabled: false,
  size: 'small',
  confirm: false,
  loading: false,
})

const emit = defineEmits<Emits>()

const { t } = useI18n()
const { confirm } = useQuickConfirm()

// Computed properties
const tooltipText = computed(() => {
  // Check if it's an i18n key (contains dots) or plain text
  if (props.label.includes('.')) {
    try {
      const translated = t(props.label)
      // If translation returns the same key, it means translation failed
      return translated === props.label ? props.label.split('.').pop() || props.label : translated
    } catch {
      return props.label.split('.').pop() || props.label
    }
  }
  return props.label
})

const buttonColor = computed(() => {
  switch (props.variant) {
    case 'primary':
      return 'primary'
    case 'success':
      return 'success'
    case 'warning':
      return 'warning'
    case 'danger':
      return 'error'
    default:
      return undefined
  }
})

const buttonVariant = computed((): 'flat' | 'text' | 'elevated' | 'tonal' | 'outlined' | 'plain' => {
  return 'text'
})

// Methods
const handleClick = async (event: MouseEvent) => {
  console.log('🖱️ ActionIcon clicked:', props.label, props.icon)

  if (props.disabled || props.loading) {
    console.log('⚠️ ActionIcon disabled or loading, ignoring click')
    return
  }

  if (props.confirm) {
    console.log('🔔 ActionIcon showing confirm dialog')
    const confirmed = await confirm({
      title: props.confirmTitle || t('actions.confirm_delete'),
      text: props.confirmMessage || t('actions.confirm_delete_message'),
      confirmText: t('actions.delete'),
      color: 'error',
      type: 'danger',
    })

    if (!confirmed) {
      console.log('❌ ActionIcon confirm dialog cancelled')
      return
    }
  }

  console.log('✅ ActionIcon emitting click event')
  emit('click', event)
}

const handleKeyup = (event: KeyboardEvent) => {
  if (event.key === 'Enter' || event.key === ' ') {
    event.preventDefault()
    handleClick(event as any)
  }
}
</script>

<template>
  <VTooltip location="top">
    <template #activator="{ props: tooltipProps }">
      <VBtn
        v-bind="tooltipProps"
        :icon="icon"
        :variant="buttonVariant"
        :color="buttonColor"
        :size="size"
        :disabled="disabled || loading"
        :loading="loading"
        :aria-label="tooltipText"
        :title="tooltipText"
        role="button"
        tabindex="0"
        @click="handleClick"
        @keyup.enter.space="handleKeyup"
      />
    </template>
    <span>{{ tooltipText }}</span>
  </VTooltip>
</template>

<style scoped>
/* Ensure tooltips appear above modals */
:deep(.v-tooltip__content) {
  z-index: 9999;
}
</style>
