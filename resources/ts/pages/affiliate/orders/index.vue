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
  { title: 'Dashboard', to: { name: 'affiliate-dashboard' } },
  { title: 'Mes Commandes', active: true },
])

const statusOptions = [
  { value: 'pending', title: 'En attente', color: 'warning' },
  { value: 'confirmed', title: 'Confirmée', color: 'info' },
  { value: 'sent', title: 'Envoyée', color: 'primary' },
  { value: 'expedited', title: 'Expédiée', color: 'purple' },
  { value: 'delivered', title: 'Livrée', color: 'success' },
  { value: 'canceled', title: 'Annulée', color: 'error' },
  { value: 'returned', title: 'Retournée', color: 'orange' },
  { value: 'delivery_failed', title: 'Échec livraison', color: 'error' },
  { value: 'paid', title: 'Payée', color: 'success' },
]

const headers = [
  { title: 'Référence', key: 'id', sortable: true },
  { title: 'Client', key: 'client.nom_complet', sortable: false },
  { title: 'Boutique', key: 'boutique.nom', sortable: false },
  { title: 'Statut', key: 'statut', sortable: true },
  { title: 'Total TTC', key: 'total_ttc', sortable: true },
  { title: 'Date', key: 'created_at', sortable: true },
  { title: 'Actions', key: 'actions', sortable: false },
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
    showError('Erreur lors du chargement des commandes')
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
          Mes Commandes
        </h1>
        <p class="text-body-1 text-medium-emphasis">
          Consultez l'historique de vos commandes et leur statut
        </p>
      </div>
    </div>

    <!-- Filters -->
    <VCard class="mb-6">
      <VCardText>
        <VRow>
          <VCol cols="12" md="4">
            <VTextField
              v-model="searchQuery"
              label="Rechercher..."
              placeholder="Référence, client, téléphone..."
              prepend-inner-icon="tabler-search"
              clearable
              @keyup.enter="applyFilters"
            />
          </VCol>
          <VCol cols="12" md="3">
            <VSelect
              v-model="selectedStatus"
              :items="statusOptions"
              label="Statut"
              multiple
              chips
              clearable
              item-title="title"
              item-value="value"
            />
          </VCol>
          <VCol cols="12" md="2">
            <VTextField
              v-model="dateFrom"
              label="Date début"
              type="date"
            />
          </VCol>
          <VCol cols="12" md="2">
            <VTextField
              v-model="dateTo"
              label="Date fin"
              type="date"
            />
          </VCol>
          <VCol cols="12" md="1" class="d-flex align-center">
            <VBtn
              color="primary"
              @click="applyFilters"
            >
              <VIcon icon="tabler-search" />
            </VBtn>
            <VBtn
              variant="outlined"
              class="ml-2"
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
            <h3 class="text-h6 mb-2">Aucune commande trouvée</h3>
            <p class="text-body-2 text-medium-emphasis">
              Aucune commande ne correspond aux critères de recherche.
            </p>
          </div>
        </template>
      </VDataTableServer>
    </VCard>
  </div>
</template>
