<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

interface Props {
  priority: 'low' | 'normal' | 'high' | 'urgent'
  size?: 'small' | 'default' | 'large'
  variant?: 'flat' | 'tonal' | 'outlined' | 'text' | 'elevated' | 'plain'
  showIcon?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  size: 'small',
  variant: 'tonal',
  showIcon: false,
})

const { t } = useI18n()

const getPriorityColor = (priority: string) => {
  const colorMap = {
    low: 'success',
    normal: 'info',
    high: 'warning',
    urgent: 'error',
  }
  return colorMap[priority as keyof typeof colorMap] || 'secondary'
}

const getPriorityLabel = (priority: string) => {
  const labelMap = {
    low: t('ticket_priority_low'),
    normal: t('ticket_priority_normal'),
    high: t('ticket_priority_high'),
    urgent: t('ticket_priority_urgent'),
  }
  return labelMap[priority as keyof typeof labelMap] || priority
}

const getPriorityIcon = (priority: string) => {
  const iconMap = {
    low: 'tabler-arrow-down',
    normal: 'tabler-minus',
    high: 'tabler-arrow-up',
    urgent: 'tabler-alert-triangle',
  }
  return iconMap[priority as keyof typeof iconMap] || 'tabler-minus'
}

const priorityColor = computed(() => getPriorityColor(props.priority))
const priorityLabel = computed(() => getPriorityLabel(props.priority))
const priorityIcon = computed(() => getPriorityIcon(props.priority))
</script>

<template>
  <VChip
    :color="priorityColor"
    :variant="variant"
    :size="size"
  >
    <VIcon
      v-if="showIcon"
      :icon="priorityIcon"
      :size="size === 'small' ? 14 : 16"
      class="me-1"
    />
    {{ priorityLabel }}
  </VChip>
</template>
