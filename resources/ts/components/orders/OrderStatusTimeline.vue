<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { $api } from '@/utils/api'

interface TimelineEntry {
  id: string
  from_status: string | null
  to_status: string
  source: string
  source_label: string
  status_label: string
  note: string | null
  meta: any
  created_at: string
  changed_by: {
    id: string
    name: string
    email: string
  } | null
}

interface Props {
  orderId: string
  endpoint: string // 'admin' or 'affiliate'
  orderType?: string // 'preorder' or 'shipping'
}

const props = defineProps<Props>()
const { t } = useI18n()

// State
const timeline = ref<TimelineEntry[]>([])
const loading = ref(false)
const error = ref<string | null>(null)

// Computed
const hasTimeline = computed(() => timeline.value.length > 0)

// Methods
const fetchTimeline = async () => {
  if (!props.orderId) return

  loading.value = true
  error.value = null

  try {
    let endpoint = ''

    if (props.endpoint === 'admin') {
      if (props.orderType === 'shipping') {
        endpoint = `admin/shipping/orders/${props.orderId}/timeline`
      } else {
        endpoint = `admin/preorders/${props.orderId}/timeline`
      }
    } else {
      endpoint = `affiliate/orders/${props.orderId}/timeline`
    }

    const response = await $api(endpoint)
    
    if (response.success) {
      timeline.value = response.data
    } else {
      error.value = response.message || 'Erreur lors du chargement de l\'historique'
    }
  } catch (err: any) {
    console.error('Error fetching timeline:', err)
    error.value = err.response?.data?.message || 'Erreur lors du chargement de l\'historique'
  } finally {
    loading.value = false
  }
}

const getStatusColor = (status: string): string => {
  const statusColors: Record<string, string> = {
    'en_attente': 'warning',
    'confirmee': 'info',
    'expediee': 'primary',
    'livree': 'success',
    'annulee': 'error',
    'retournee': 'secondary',
    'refusee': 'error',
    'injoignable': 'warning',
    'echec_livraison': 'error',
    // OzonExpress statuses
    'pending': 'warning',
    'received': 'info',
    'in_transit': 'primary',
    'shipped': 'primary',
    'at_facility': 'info',
    'ready_for_delivery': 'primary',
    'out_for_delivery': 'primary',
    'delivery_attempted': 'warning',
    'delivered': 'success',
    'returned': 'secondary',
    'refused': 'error',
    'cancelled': 'error',
    'unknown': 'secondary',
  }
  return statusColors[status] || 'secondary'
}

const getSourceIcon = (source: string): string => {
  const sourceIcons: Record<string, string> = {
    'admin': 'tabler-user-shield',
    'affiliate': 'tabler-user-star',
    'ozon_express': 'tabler-truck',
    'system': 'tabler-robot',
  }
  return sourceIcons[source] || 'tabler-circle'
}

const formatDate = (dateString: string): string => {
  const date = new Date(dateString)
  return date.toLocaleString('fr-FR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

// Lifecycle
onMounted(() => {
  fetchTimeline()
})

// Expose refresh method
defineExpose({
  refresh: fetchTimeline
})
</script>

<template>
  <VCard>
    <VCardTitle class="d-flex align-center gap-2">
      <VIcon icon="tabler-timeline" />
      {{ t('order_status_timeline') }}
    </VCardTitle>

    <VCardText>
      <!-- Loading State -->
      <div v-if="loading" class="d-flex justify-center py-8">
        <VProgressCircular
          indeterminate
          color="primary"
          size="40"
        />
      </div>

      <!-- Error State -->
      <VAlert
        v-else-if="error"
        type="error"
        variant="tonal"
        class="mb-4"
      >
        {{ error }}
        <template #append>
          <VBtn
            variant="text"
            size="small"
            @click="fetchTimeline"
          >
            {{ t('retry') }}
          </VBtn>
        </template>
      </VAlert>

      <!-- Empty State -->
      <div
        v-else-if="!hasTimeline"
        class="text-center py-8"
      >
        <VIcon
          icon="tabler-timeline-event-x"
          size="48"
          class="text-disabled mb-4"
        />
        <p class="text-body-1 text-disabled">
          {{ t('no_status_history') }}
        </p>
      </div>

      <!-- Timeline -->
      <VTimeline
        v-else
        side="end"
        align="start"
        truncate-line="both"
        density="compact"
        class="timeline-custom"
      >
        <VTimelineItem
          v-for="(entry, index) in timeline"
          :key="entry.id"
          :dot-color="getStatusColor(entry.to_status)"
          size="small"
        >
          <template #icon>
            <VIcon
              :icon="getSourceIcon(entry.source)"
              size="16"
            />
          </template>

          <div class="timeline-content">
            <!-- Header -->
            <div class="d-flex justify-space-between align-start mb-2">
              <div>
                <h6 class="text-h6 mb-1">
                  {{ entry.status_label }}
                </h6>
                <div class="d-flex align-center gap-2 text-caption text-medium-emphasis">
                  <VChip
                    :color="getStatusColor(entry.to_status)"
                    size="x-small"
                    variant="tonal"
                  >
                    {{ entry.source_label }}
                  </VChip>
                  <span>{{ formatDate(entry.created_at) }}</span>
                </div>
              </div>
            </div>

            <!-- Status Change -->
            <div v-if="entry.from_status" class="mb-2">
              <span class="text-body-2 text-medium-emphasis">
                {{ entry.from_status }} → {{ entry.to_status }}
              </span>
            </div>

            <!-- Note -->
            <p
              v-if="entry.note"
              class="text-body-2 mb-2"
            >
              {{ entry.note }}
            </p>

            <!-- Changed By -->
            <div
              v-if="entry.changed_by"
              class="d-flex align-center gap-2 text-caption text-medium-emphasis"
            >
              <VIcon icon="tabler-user" size="14" />
              <span>{{ entry.changed_by.name }}</span>
            </div>

            <!-- Meta Information -->
            <div
              v-if="entry.meta && Object.keys(entry.meta).length > 0"
              class="mt-2"
            >
              <VExpansionPanels
                variant="accordion"
                class="timeline-meta"
              >
                <VExpansionPanel>
                  <VExpansionPanelTitle class="text-caption">
                    {{ t('additional_details') }}
                  </VExpansionPanelTitle>
                  <VExpansionPanelText>
                    <pre class="text-caption">{{ JSON.stringify(entry.meta, null, 2) }}</pre>
                  </VExpansionPanelText>
                </VExpansionPanel>
              </VExpansionPanels>
            </div>
          </div>
        </VTimelineItem>
      </VTimeline>
    </VCardText>
  </VCard>
</template>

<style scoped>
.timeline-custom :deep(.v-timeline-item__body) {
  padding-inline-start: 16px;
}

.timeline-content {
  min-height: 60px;
}

.timeline-meta :deep(.v-expansion-panel) {
  box-shadow: none;
  border: 1px solid rgb(var(--v-border-color));
}

.timeline-meta :deep(.v-expansion-panel-title) {
  min-height: 32px;
  padding: 8px 12px;
}

.timeline-meta :deep(.v-expansion-panel-text__wrapper) {
  padding: 8px 12px;
}

pre {
  white-space: pre-wrap;
  word-break: break-word;
  font-family: 'Courier New', monospace;
  background: rgb(var(--v-theme-surface-variant));
  padding: 8px;
  border-radius: 4px;
  font-size: 11px;
}
</style>
