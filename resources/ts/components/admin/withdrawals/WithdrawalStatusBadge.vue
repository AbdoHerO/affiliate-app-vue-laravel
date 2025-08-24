<script setup lang="ts">
import { useI18n } from 'vue-i18n'

interface Props {
  status: string
  color?: string
}

const props = withDefaults(defineProps<Props>(), {
  color: undefined,
})

const { t } = useI18n()

const getStatusLabel = (status: string): string => {
  switch (status) {
    case 'pending': return t('admin_withdrawals_pending')
    case 'approved': return t('admin_withdrawals_approved')
    case 'in_payment': return t('admin_withdrawals_in_payment')
    case 'paid': return t('admin_withdrawals_paid')
    case 'rejected': return t('admin_withdrawals_rejected')
    case 'canceled': return t('admin_withdrawals_canceled')
    default: return status
  }
}

const statusColors: Record<string, string> = {
  pending: 'warning',
  approved: 'info',
  in_payment: 'primary',
  paid: 'success',
  rejected: 'error',
  canceled: 'secondary',
}

const getStatusColor = (status: string): string => {
  return props.color || statusColors[status] || 'secondary'
}
</script>

<template>
  <VChip
    :color="getStatusColor(status)"
    variant="tonal"
    size="small"
  >
    {{ getStatusLabel(status) }}
  </VChip>
</template>
