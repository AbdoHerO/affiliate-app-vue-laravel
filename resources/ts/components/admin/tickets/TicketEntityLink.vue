<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { TicketRelation } from '@/stores/admin/tickets'

interface Props {
  relation: TicketRelation
  showIcon?: boolean
  showType?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  showIcon: true,
  showType: true,
})

const { t } = useI18n()

// Get icon for entity type
const getEntityIcon = (type: string) => {
  const iconMap = {
    'App\\Models\\Commande': 'tabler-shopping-cart',
    'App\\Models\\User': 'tabler-user',
    'App\\Models\\Commission': 'tabler-coins',
    'App\\Models\\Withdrawal': 'tabler-cash',
    'App\\Models\\Produit': 'tabler-package',
    'App\\Models\\KycDocument': 'tabler-file-certificate',
  }
  return iconMap[type as keyof typeof iconMap] || 'tabler-link'
}

// Get color for entity type
const getEntityColor = (type: string) => {
  const colorMap = {
    'App\\Models\\Commande': 'primary',
    'App\\Models\\User': 'info',
    'App\\Models\\Commission': 'success',
    'App\\Models\\Withdrawal': 'warning',
    'App\\Models\\Produit': 'secondary',
    'App\\Models\\KycDocument': 'error',
  }
  return colorMap[type as keyof typeof colorMap] || 'default'
}

// Get route for entity
const getEntityRoute = (type: string, id: string) => {
  const routeMap = {
    'App\\Models\\Commande': `/admin/orders/${id}`,
    'App\\Models\\User': `/admin/users/${id}`,
    'App\\Models\\Commission': `/admin/commissions/${id}`,
    'App\\Models\\Withdrawal': `/admin/withdrawals/${id}`,
    'App\\Models\\Produit': `/admin/products/${id}`,
    'App\\Models\\KycDocument': `/admin/kyc-documents/${id}`,
  }
  return routeMap[type as keyof typeof routeMap] || '#'
}

const entityIcon = computed(() => getEntityIcon(props.relation.related_type))
const entityColor = computed(() => getEntityColor(props.relation.related_type))
const entityRoute = computed(() => getEntityRoute(props.relation.related_type, props.relation.related_id))
</script>

<template>
  <VChip
    :color="entityColor"
    variant="tonal"
    size="small"
    :to="entityRoute"
    class="text-decoration-none"
  >
    <VIcon
      v-if="showIcon"
      :icon="entityIcon"
      size="14"
      class="me-1"
    />
    
    <span v-if="showType" class="me-1">
      {{ relation.related_type_name }}:
    </span>
    
    <span class="font-weight-medium">
      {{ relation.related_display_name }}
    </span>
  </VChip>
</template>
