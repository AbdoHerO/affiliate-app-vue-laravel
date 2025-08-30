<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'
import { useAffiliateOrdersStore } from '@/stores/affiliate/orders'
import { useNotifications } from '@/composables/useNotifications'
import Breadcrumbs from '@/components/common/Breadcrumbs.vue'

definePage({
  meta: {
    requiresAuth: true,
    requiresRole: 'affiliate',
  },
})

const { t } = useI18n()
const router = useRouter()
const { showError } = useNotifications()

// Store
const ordersStore = useAffiliateOrdersStore()
const { orders, loading, pagination, filters, error } = storeToRefs(ordersStore)

// Local state
const searchQuery = ref('')
const selectedStatus = ref<string[]>([])
const selectedBoutique = ref('')
const dateFrom = ref('')
const dateTo = ref('')
const showFilters = ref(false)

// Computed
const breadcrumbs = computed(() => [
  { title: t('nav.dashboard'), to: { name: 'affiliate-dashboard' } },
  { title: t('affiliate_orders_title'), active: true },
])

const statusOptions = [
  { value: 'pending', title: t('affiliate_orders_status_pending'), color: 'warning' },
  { value: 'confirmed', title: t('affiliate_orders_status_confirmed'), color: 'info' },
  { value: 'sent', title: t('affiliate_orders_status_sent'), color: 'primary' },
  { value: 'expedited', title: t('affiliate_orders_status_expedited'), color: 'purple' },
  { value: 'delivered', title: t('affiliate_orders_status_delivered'), color: 'success' },
  { value: 'canceled', title: t('affiliate_orders_status_canceled'), color: 'error' },
  { value: 'returned', title: t('affiliate_orders_status_returned'), color: 'orange' },
  { value: 'delivery_failed', title: t('affiliate_orders_status_delivery_failed'), color: 'error' },
  { value: 'paid', title: t('affiliate_orders_status_paid'), color: 'success' },
]

const headers = [
  { title: t('table.reference'), key: 'id', sortable: true },
  { title: t('table.client'), key: 'client.nom_complet', sortable: false },
  { title: t('table.boutique'), key: 'boutique.nom', sortable: false },
  { title: 'SKU', key: 'sku_list', sortable: false, width: '150px' },
  { title: 'Type', key: 'type_command', sortable: true },
  { title: 'Livreur', key: 'delivery_boy', sortable: false, width: '150px' },
  { title: t('table.status'), key: 'statut', sortable: true },
  { title: t('table.total_ttc'), key: 'total_ttc', sortable: true },
  { title: t('table.date'), key: 'created_at', sortable: true },
  { title: t('table.actions'), key: 'actions', sortable: false },
]

// Methods
const applyFilters = () => {
  ordersStore.updateFilters({
    q: searchQuery.value,
    status: selectedStatus.value,
    boutique_id: selectedBoutique.value,
    date_from: dateFrom.value,
    date_to: dateTo.value,
  })
  fetchOrders()
}

const resetFilters = () => {
  searchQuery.value = ''
  selectedStatus.value = []
  selectedBoutique.value = ''
  dateFrom.value = ''
  dateTo.value = ''
  ordersStore.resetFilters()
  fetchOrders()
}

const fetchOrders = async (page = 1) => {
  try {
    await ordersStore.fetchOrders(page)
  } catch (err) {
    showError(t('errors.orders_load_failed'))
  }
}

const handlePageChange = (page: number) => {
  fetchOrders(page)
}

const handlePerPageChange = (perPage: number) => {
  ordersStore.updateFilters({ per_page: perPage })
  fetchOrders()
}

const handleSort = (sortBy: any) => {
  if (sortBy.length > 0) {
    const { key, order } = sortBy[0]
    ordersStore.updateFilters({
      sort: key,
      dir: order,
    })
    fetchOrders()
  }
}

const viewOrder = (order: any) => {
  router.push({ name: 'affiliate-orders-id', params: { id: order.id } })
}

const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'MAD',
  }).format(amount)
}

const formatDate = (date: string) => {
  return new Date(date).toLocaleDateString('fr-FR', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

const getOrderTypeColor = (type: string) => {
  const colors: Record<string, string> = {
    'order_sample': 'primary',
    'exchange': 'warning'
  }
  return colors[type] || 'secondary'
}

const getOrderTypeLabel = (type: string) => {
  const labels: Record<string, string> = {
    'order_sample': 'Échantillon',
    'exchange': 'Échange'
  }
  return labels[type] || type || 'N/A'
}

// Watchers
watch(error, (newError) => {
  if (newError) {
    showError(newError)
  }
})

// Lifecycle
onMounted(() => {
  fetchOrders()
})
</script>

<template>
  <div>
    <!-- Breadcrumbs -->
    <Breadcrumbs :items="breadcrumbs" />

    <!-- Page Header -->
    <div class="d-flex justify-space-between align-center mb-6">
      <div>
        <h1 class="text-h4 font-weight-bold mb-1">
          {{ t('affiliate_orders_title') }}
        </h1>
        <p class="text-body-1 text-medium-emphasis">
          {{ t('affiliate_orders_description') }}
        </p>
      </div>
    </div>

    <!-- Filters -->
    <VCard class="mb-6">
      <VCardText>
        <VRow>
          <VCol cols="12" sm="6" md="4" lg="4">
            <VTextField
              v-model="searchQuery"
              :label="t('search.label')"
              :placeholder="t('affiliate_orders_search_placeholder')"
              prepend-inner-icon="tabler-search"
              clearable
              @keyup.enter="applyFilters"
            />
          </VCol>
          <VCol cols="12" sm="6" md="4" lg="3">
            <VSelect
              v-model="selectedStatus"
              :items="statusOptions"
              :label="t('table.status')"
              multiple
              chips
              clearable
              item-title="title"
              item-value="value"
            />
          </VCol>
          <VCol cols="12" sm="6" md="4" lg="2">
            <VTextField
              v-model="dateFrom"
              :label="t('filters.date_start')"
              type="date"
            />
          </VCol>
          <VCol cols="12" sm="6" md="4" lg="2">
            <VTextField
              v-model="dateTo"
              :label="t('filters.date_end')"
              type="date"
            />
          </VCol>
          <VCol cols="12" sm="12" md="4" lg="1" class="d-flex align-center gap-2">
            <VBtn
              color="primary"
              size="small"
              @click="applyFilters"
            >
              <VIcon icon="tabler-search" />
            </VBtn>
            <VBtn
              variant="outlined"
              size="small"
              @click="resetFilters"
            >
              <VIcon icon="tabler-refresh" />
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Data Table -->
    <VCard>
      <VDataTableServer
        :headers="headers"
        :items="orders"
        :loading="loading"
        :items-length="pagination.total"
        :items-per-page="pagination.per_page"
        :page="pagination.current_page"
        item-value="id"
        @update:page="handlePageChange"
        @update:items-per-page="handlePerPageChange"
        @update:sort-by="handleSort"
      >
        <!-- Order ID Column -->
        <template #item.id="{ item }">
          <VBtn
            variant="text"
            size="small"
            color="primary"
            @click="viewOrder(item)"
          >
            #{{ item.id.toString().slice(-8) }}
          </VBtn>
        </template>

        <!-- Client Column -->
        <template #item.client.nom_complet="{ item }">
          <div class="d-flex align-center">
            <VAvatar size="32" class="me-3">
              <VIcon icon="tabler-user" />
            </VAvatar>
            <div>
              <div class="font-weight-medium">{{ item.client?.nom_complet || item.clientFinal?.nom_complet || 'N/A' }}</div>
              <div class="text-caption text-medium-emphasis">{{ item.client?.telephone || item.clientFinal?.telephone || 'N/A' }}</div>
            </div>
          </div>
        </template>

        <!-- Boutique Column -->
        <template #item.boutique.nom="{ item }">
          <VChip
            size="small"
            variant="tonal"
            color="info"
          >
            {{ item.boutique?.nom || 'N/A' }}
          </VChip>
        </template>

        <!-- SKU List Column -->
        <template #item.sku_list="{ item }">
          <div class="d-flex flex-wrap gap-1">
            <VChip
              v-for="article in item.articles || []"
              :key="article.id"
              size="x-small"
              color="secondary"
              variant="outlined"
              class="font-mono"
            >
              {{ article.produit?.sku || 'N/A' }}
            </VChip>
          </div>
        </template>

        <!-- Type Command Column -->
        <template #item.type_command="{ item }">
          <VChip
            size="small"
            :color="getOrderTypeColor(item.type_command)"
            variant="tonal"
          >
            {{ getOrderTypeLabel(item.type_command) }}
          </VChip>
        </template>

        <!-- Delivery Boy Column -->
        <template #item.delivery_boy="{ item }">
          <div v-if="item.delivery_boy_name || item.delivery_boy_phone" class="d-flex flex-column">
            <span v-if="item.delivery_boy_name" class="text-body-2 font-weight-medium">
              {{ item.delivery_boy_name }}
            </span>
            <a
              v-if="item.delivery_boy_phone"
              :href="`tel:${item.delivery_boy_phone}`"
              class="text-caption text-primary text-decoration-none"
            >
              <VIcon icon="tabler-phone" size="12" class="me-1" />
              {{ item.delivery_boy_phone }}
            </a>
          </div>
          <span v-else class="text-caption text-medium-emphasis">—</span>
        </template>

        <!-- Status Column -->
        <template #item.statut="{ item }">
          <VChip
            :color="ordersStore.getStatusColor(item.statut)"
            size="small"
            variant="tonal"
          >
            {{ ordersStore.getStatusLabel(item.statut) }}
          </VChip>
        </template>

        <!-- Total TTC Column -->
        <template #item.total_ttc="{ item }">
          <span class="font-weight-medium">
            {{ formatCurrency(item.total_ttc) }}
          </span>
        </template>

        <!-- Date Column -->
        <template #item.created_at="{ item }">
          <span class="text-body-2">
            {{ formatDate(item.created_at) }}
          </span>
        </template>

        <!-- Actions Column -->
        <template #item.actions="{ item }">
          <VBtn
            icon="tabler-eye"
            size="small"
            variant="text"
            color="primary"
            @click="viewOrder(item)"
          />
        </template>

        <!-- No data -->
        <template #no-data>
          <div class="text-center py-8">
            <VIcon
              icon="tabler-package-off"
              size="64"
              class="text-disabled mb-4"
            />
            <h3 class="text-h6 mb-2">{{ t('affiliate_orders_no_orders_found') }}</h3>
            <p class="text-body-2 text-medium-emphasis">
              {{ t('affiliate_orders_no_orders_description') }}
            </p>
          </div>
        </template>
      </VDataTableServer>
    </VCard>
  </div>
</template>
