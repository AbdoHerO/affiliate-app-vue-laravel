<script setup lang="ts">
interface StockIssue {
  id: string
  motif: string
  started_at: string
  expected_restock_at?: string | null
  variante_id?: string | null
  created_at: string
}

interface Props {
  stockIssues: StockIssue[]
  variants?: any[]
}

defineProps<Props>()

const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleDateString('fr-FR', {
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
}

const formatDateTime = (dateString: string) => {
  return new Date(dateString).toLocaleString('fr-FR', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

const getVariantName = (variantId: string | null, variants: any[] = []) => {
  if (!variantId) return 'All variants'
  const variant = variants.find(v => v.id === variantId)
  return variant ? `${variant.nom}: ${variant.valeur}` : 'Unknown variant'
}

const getStatusColor = (expectedRestockAt: string | null) => {
  if (!expectedRestockAt) return 'error'
  
  const restockDate = new Date(expectedRestockAt)
  const now = new Date()
  const daysUntilRestock = Math.ceil((restockDate.getTime() - now.getTime()) / (1000 * 60 * 60 * 24))
  
  if (daysUntilRestock <= 3) return 'warning'
  if (daysUntilRestock <= 7) return 'info'
  return 'success'
}

const getStatusText = (expectedRestockAt: string | null) => {
  if (!expectedRestockAt) return 'No restock date'
  
  const restockDate = new Date(expectedRestockAt)
  const now = new Date()
  const daysUntilRestock = Math.ceil((restockDate.getTime() - now.getTime()) / (1000 * 60 * 60 * 24))
  
  if (daysUntilRestock < 0) return 'Overdue'
  if (daysUntilRestock === 0) return 'Today'
  if (daysUntilRestock === 1) return 'Tomorrow'
  return `${daysUntilRestock} days`
}
</script>

<template>
  <VCard elevation="2">
    <VCardTitle class="d-flex align-center gap-2">
      <VIcon icon="tabler-alert-triangle" color="warning" />
      Stock Issues ({{ stockIssues.length }})
    </VCardTitle>
    <VCardText>
      <VAlert
        v-if="stockIssues.length === 0"
        type="success"
        variant="tonal"
        class="mb-0"
      >
        <VIcon icon="tabler-check" />
        No current stock issues
      </VAlert>
      
      <div v-else class="d-flex flex-column gap-3">
        <VCard
          v-for="issue in stockIssues"
          :key="issue.id"
          class="stock-issue-card"
          elevation="1"
          :color="getStatusColor(issue.expected_restock_at)"
          variant="outlined"
        >
          <VCardText class="pa-4">
            <div class="d-flex justify-space-between align-start mb-3">
              <div class="flex-grow-1">
                <h4 class="text-subtitle-1 font-weight-bold mb-1">
                  {{ issue.motif }}
                </h4>
                <div class="text-body-2 text-medium-emphasis">
                  Affects: {{ getVariantName(issue.variante_id, variants) }}
                </div>
              </div>
              
              <VChip
                :color="getStatusColor(issue.expected_restock_at)"
                size="small"
                variant="flat"
              >
                {{ getStatusText(issue.expected_restock_at) }}
              </VChip>
            </div>
            
            <VDivider class="my-3" />
            
            <div class="d-flex flex-column flex-sm-row gap-4">
              <div class="flex-grow-1">
                <div class="text-caption text-medium-emphasis">Started</div>
                <div class="font-weight-medium">
                  {{ formatDateTime(issue.started_at) }}
                </div>
              </div>
              
              <div v-if="issue.expected_restock_at" class="flex-grow-1">
                <div class="text-caption text-medium-emphasis">Expected Restock</div>
                <div class="font-weight-medium">
                  {{ formatDateTime(issue.expected_restock_at) }}
                </div>
              </div>
              
              <div v-else class="flex-grow-1">
                <div class="text-caption text-medium-emphasis">Status</div>
                <div class="font-weight-medium text-error">
                  No restock date set
                </div>
              </div>
            </div>
          </VCardText>
        </VCard>
      </div>
    </VCardText>
  </VCard>
</template>

<style scoped>
.stock-issue-card {
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.stock-issue-card:hover {
  transform: translateY(-1px);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}
</style>
