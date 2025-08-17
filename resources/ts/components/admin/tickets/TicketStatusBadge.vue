<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

interface Props {
  status: 'open' | 'pending' | 'waiting_user' | 'waiting_third_party' | 'resolved' | 'closed'
  size?: 'small' | 'default' | 'large'
  variant?: 'flat' | 'tonal' | 'outlined' | 'text' | 'elevated' | 'plain'
}

const props = withDefaults(defineProps<Props>(), {
  size: 'small',
  variant: 'tonal',
})

const { t } = useI18n()

const getStatusColor = (status: string) => {
  const colorMap = {
    open: 'info',
    pending: 'warning',
    waiting_user: 'orange',
    waiting_third_party: 'purple',
    resolved: 'success',
    closed: 'secondary',
  }
  return colorMap[status as keyof typeof colorMap] || 'secondary'
}

const getStatusLabel = (status: string) => {
  const labelMap = {
    open: t('ticket_status_open'),
    pending: t('ticket_status_pending'),
    waiting_user: t('ticket_status_waiting_user'),
    waiting_third_party: t('ticket_status_waiting_third_party'),
    resolved: t('ticket_status_resolved'),
    closed: t('ticket_status_closed'),
  }
  return labelMap[status as keyof typeof labelMap] || status
}

const statusColor = computed(() => getStatusColor(props.status))
const statusLabel = computed(() => getStatusLabel(props.status))
</script>

<template>
  <VChip
    :color="statusColor"
    :variant="variant"
    :size="size"
  >
    {{ statusLabel }}
  </VChip>
</template>
