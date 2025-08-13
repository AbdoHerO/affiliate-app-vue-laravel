<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { usePreordersStore } from '@/stores/admin/preorders'
import { useShippingStore } from '@/stores/admin/shipping'
import { useConfirmAction } from '@/composables/useConfirmAction'
import { useNotifications } from '@/composables/useNotifications'

definePage({
  meta: {
    requiresAuth: true,
    requiresRole: 'admin',
    layout: 'default',
  },
})

const router = useRouter()
const preordersStore = usePreordersStore()
const shippingStore = useShippingStore()
const { confirm } = useConfirmAction()
const { showSuccess, showError } = useNotifications()

// Local state
const searchQuery = ref('')
const selectedStatus = ref('')
const selectedAffiliate = ref('')
const selectedBoutique = ref('')
const dateFrom = ref('')
const dateTo = ref('')
const itemsPerPage = ref(15)

// Computed
const isLoading = computed(() => preordersStore.isLoading)
const preorders = computed(() => preordersStore.preorders)
const pagination = computed(() => preordersStore.pagination)

// Table headers
const headers = [
  { title: 'Code', key: 'id', sortable: true },
  { title: 'Client', key: 'client', sortable: false },
  { title: 'Affilié', key: 'affilie', sortable: false },
  { title: 'Boutique', key: 'boutique', sortable: false },
  { title: 'Total', key: 'total_ttc', sortable: true },
  { title: 'Statut', key: 'statut', sortable: true },
  { title: 'Date', key: 'created_at', sortable: true },
  { title: 'Actions', key: 'actions', sortable: false },
]

// Status options
const statusOptions = [
  { title: 'Tous', value: '' },
  { title: 'En attente', value: 'en_attente' },
  { title: 'Confirmée', value: 'confirmee' },
]

// Methods
const fetchPreorders = async () => {
  await preordersStore.fetchPreorders({
    q: searchQuery.value || undefined,
    statut: selectedStatus.value || undefined,
    affilie_id: selectedAffiliate.value || undefined,
    boutique_id: selectedBoutique.value || undefined,
    from: dateFrom.value || undefined,
    to: dateTo.value || undefined,
    perPage: itemsPerPage.value,
  })
}

// Simple debounce implementation
let debounceTimer: NodeJS.Timeout
const debouncedFetch = () => {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(fetchPreorders, 300)
}

const handleSearch = () => {
  preordersStore.filters.page = 1
  debouncedFetch()
}

const handlePageChange = (page: number) => {
  preordersStore.filters.page = page
  fetchPreorders()
}

const handleSort = (sortBy: any) => {
  if (sortBy.length > 0) {
    preordersStore.filters.sort = sortBy[0].key
    preordersStore.filters.dir = sortBy[0].order
    fetchPreorders()
  }
}

const viewPreorder = (preorder: any) => {
  router.push({ name: 'admin-orders-pre-id', params: { id: preorder.id } })
}

const sendToOzonExpress = async (preorder: any) => {
  const confirmed = await confirm({
    title: 'Envoyer vers OzonExpress',
    text: `Êtes-vous sûr de vouloir envoyer la commande ${preorder.id.slice(0, 8)} vers OzonExpress ?`,
    confirmText: 'Envoyer',
    color: 'primary',
  })

  if (confirmed) {
    try {
      await shippingStore.addParcel(preorder.id)
      showSuccess('Colis créé avec succès sur OzonExpress')
      fetchPreorders() // Refresh list
    } catch (error: any) {
      showError(error.message || 'Erreur lors de la création du colis')
    }
  }
}

const getStatusColor = (status: string) => {
  switch (status) {
    case 'en_attente':
      return 'warning'
    case 'confirmee':
      return 'success'
    default:
      return 'default'
  }
}

const getStatusText = (status: string) => {
  switch (status) {
    case 'en_attente':
      return 'En attente'
    case 'confirmee':
      return 'Confirmée'
    default:
      return status
  }
}

const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('fr-MA', {
    style: 'currency',
    currency: 'MAD',
  }).format(amount)
}

const formatDate = (date: string) => {
  return new Date(date).toLocaleDateString('fr-FR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

const resetFilters = () => {
  searchQuery.value = ''
  selectedStatus.value = ''
  selectedAffiliate.value = ''
  selectedBoutique.value = ''
  dateFrom.value = ''
  dateTo.value = ''
  preordersStore.resetFilters()
  fetchPreorders()
}

// Lifecycle
onMounted(() => {
  fetchPreorders()
})
</script>

<template>
  <div class="d-flex flex-column gap-6">
    <!-- Page Header -->
    <div class="d-flex justify-space-between align-center mb-6">
      <div>
        <h1 class="text-h4 font-weight-bold mb-1">
          Pré-commandes
        </h1>
        <p class="text-body-1 mb-0">
          Gestion des commandes en attente d'expédition
        </p>
      </div>
      <VBtn
        color="primary"
        variant="elevated"
        @click="resetFilters"
      >
        <VIcon start icon="tabler-refresh" />
        Actualiser
      </VBtn>
    </div>

    <!-- Filters Card -->
    <VCard class="mb-6">
      <VCardText>
        <VRow>
          <VCol cols="12" md="3">
            <VTextField
              v-model="searchQuery"
              label="Rechercher..."
              placeholder="Client, téléphone, affilié..."
              prepend-inner-icon="tabler-search"
              clearable
              @input="handleSearch"
            />
          </VCol>
          <VCol cols="12" md="2">
            <VSelect
              v-model="selectedStatus"
              label="Statut"
              :items="statusOptions"
              clearable
              @update:model-value="handleSearch"
            />
          </VCol>
          <VCol cols="12" md="2">
            <VTextField
              v-model="dateFrom"
              label="Date début"
              type="date"
              @change="handleSearch"
            />
          </VCol>
          <VCol cols="12" md="2">
            <VTextField
              v-model="dateTo"
              label="Date fin"
              type="date"
              @change="handleSearch"
            />
          </VCol>
          <VCol cols="12" md="2">
            <VSelect
              v-model="itemsPerPage"
              label="Par page"
              :items="[10, 15, 25, 50]"
              @update:model-value="handleSearch"
            />
          </VCol>
          <VCol cols="12" md="1">
            <VBtn
              color="secondary"
              variant="outlined"
              block
              @click="resetFilters"
            >
              <VIcon icon="tabler-filter-off" />
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Data Table -->
    <VCard>
      <VDataTableServer
        v-model:items-per-page="itemsPerPage"
        :headers="headers"
        :items="preorders"
        :items-length="pagination.total"
        :loading="isLoading"
        :page="pagination.current_page"
        @update:page="handlePageChange"
        @update:sort-by="handleSort"
      >
        <!-- Client Column -->
        <template #item.client="{ item }">
          <div>
            <div class="font-weight-medium">
              {{ item.client.nom_complet }}
            </div>
            <div class="text-caption text-medium-emphasis">
              {{ item.client.telephone }}
            </div>
          </div>
        </template>

        <!-- Affiliate Column -->
        <template #item.affilie="{ item }">
          <div>
            <div class="font-weight-medium">
              {{ item.affilie.utilisateur.nom_complet }}
            </div>
            <div class="text-caption text-medium-emphasis">
              {{ item.affilie.utilisateur.email }}
            </div>
          </div>
        </template>

        <!-- Boutique Column -->
        <template #item.boutique="{ item }">
          <VChip
            size="small"
            color="info"
            variant="tonal"
          >
            {{ item.boutique.nom }}
          </VChip>
        </template>

        <!-- Total Column -->
        <template #item.total_ttc="{ item }">
          <div class="font-weight-bold">
            {{ formatCurrency(item.total_ttc) }}
          </div>
        </template>

        <!-- Status Column -->
        <template #item.statut="{ item }">
          <VChip
            size="small"
            :color="getStatusColor(item.statut)"
            variant="tonal"
          >
            {{ getStatusText(item.statut) }}
          </VChip>
        </template>

        <!-- Date Column -->
        <template #item.created_at="{ item }">
          <div class="text-body-2">
            {{ formatDate(item.created_at) }}
          </div>
        </template>

        <!-- Actions Column -->
        <template #item.actions="{ item }">
          <div class="d-flex gap-2">
            <VBtn
              size="small"
              color="primary"
              variant="text"
              icon="tabler-eye"
              @click="viewPreorder(item)"
            />
            <VBtn
              v-if="!item.shipping_parcel"
              size="small"
              color="success"
              variant="text"
              icon="tabler-truck"
              @click="sendToOzonExpress(item)"
            />
            <VBtn
              v-else
              size="small"
              color="info"
              variant="text"
              icon="tabler-package"
              @click="router.push({ name: 'admin-orders-shipping-id', params: { id: item.id } })"
            />
          </div>
        </template>

        <!-- No data -->
        <template #no-data>
          <div class="text-center py-8">
            <VIcon
              icon="tabler-package-off"
              size="64"
              class="mb-4"
              color="disabled"
            />
            <h3 class="text-h6 mb-2">Aucune pré-commande</h3>
            <p class="text-body-2 text-medium-emphasis">
              Aucune commande en attente d'expédition trouvée
            </p>
          </div>
        </template>
      </VDataTableServer>
    </VCard>
  </div>
</template>
