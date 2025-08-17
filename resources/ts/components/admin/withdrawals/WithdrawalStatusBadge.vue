<script setup lang="ts">
interface Props {
  status: string
  color?: string
}

const props = withDefaults(defineProps<Props>(), {
  color: undefined,
})

const statusLabels: Record<string, string> = {
  pending: 'En attente',
  approved: 'Approuvé',
  in_payment: 'En cours de paiement',
  paid: 'Payé',
  rejected: 'Rejeté',
  canceled: 'Annulé',
}

const statusColors: Record<string, string> = {
  pending: 'warning',
  approved: 'info',
  in_payment: 'primary',
  paid: 'success',
  rejected: 'error',
  canceled: 'secondary',
}

const getStatusLabel = (status: string): string => {
  return statusLabels[status] || status
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
