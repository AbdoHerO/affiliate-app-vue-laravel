<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'
import { useAffiliateTicketsStore } from '@/stores/affiliate/tickets'
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
const { showSuccess, showError } = useNotifications()

// Store
const ticketsStore = useAffiliateTicketsStore()
const { tickets, loading, pagination, filters, error } = storeToRefs(ticketsStore)

// Local state
const searchQuery = ref('')
const selectedStatus = ref<string[]>([])
const selectedPriority = ref<string[]>([])
const selectedCategory = ref<string[]>([])
const dateFrom = ref('')
const dateTo = ref('')
const showCreateDialog = ref(false)

// Form data for creating tickets
const createForm = ref({
  subject: '',
  category: 'general',
  priority: 'normal',
  message: '',
  attachments: [] as File[],
})

// Computed
const breadcrumbs = computed(() => [
  { title: 'Dashboard', to: { name: 'affiliate-dashboard' } },
  { title: 'Support', active: true },
])

const statusOptions = [
  { value: 'open', title: 'Ouvert', color: 'info' },
  { value: 'pending', title: 'En attente', color: 'warning' },
  { value: 'waiting_user', title: 'En attente utilisateur', color: 'orange' },
  { value: 'resolved', title: 'Résolu', color: 'success' },
  { value: 'closed', title: 'Fermé', color: 'secondary' },
]

const priorityOptions = [
  { value: 'low', title: 'Faible', color: 'success' },
  { value: 'normal', title: 'Normal', color: 'info' },
  { value: 'high', title: 'Élevée', color: 'warning' },
  { value: 'urgent', title: 'Urgent', color: 'error' },
]

const categoryOptions = [
  { value: 'general', title: 'Général' },
  { value: 'orders', title: 'Commandes' },
  { value: 'payments', title: 'Paiements' },
  { value: 'commissions', title: 'Commissions' },
  { value: 'kyc', title: 'KYC' },
  { value: 'technical', title: 'Technique' },
  { value: 'other', title: 'Autre' },
]

const headers = [
  { title: 'Référence', key: 'id', sortable: false },
  { title: 'Sujet', key: 'subject', sortable: true },
  { title: 'Catégorie', key: 'category', sortable: true },
  { title: 'Priorité', key: 'priority', sortable: true },
  { title: 'Statut', key: 'status', sortable: true },
  { title: 'Dernière activité', key: 'last_activity_at', sortable: true },
  { title: 'Actions', key: 'actions', sortable: false },
]

// Methods
const applyFilters = () => {
  ticketsStore.updateFilters({
    q: searchQuery.value,
    status: selectedStatus.value,
    priority: selectedPriority.value,
    category: selectedCategory.value,
    date_from: dateFrom.value,
    date_to: dateTo.value,
  })
  fetchTickets()
}

const resetFilters = () => {
  searchQuery.value = ''
  selectedStatus.value = []
  selectedPriority.value = []
  selectedCategory.value = []
  dateFrom.value = ''
  dateTo.value = ''
  ticketsStore.resetFilters()
  fetchTickets()
}

const fetchTickets = async (page = 1) => {
  try {
    await ticketsStore.fetchTickets(page)
  } catch (err) {
    showError('Erreur lors du chargement des tickets')
  }
}

const handlePageChange = (page: number) => {
  fetchTickets(page)
}

const handlePerPageChange = (perPage: number) => {
  ticketsStore.updateFilters({ per_page: perPage })
  fetchTickets()
}

const handleSort = (sortBy: any) => {
  if (sortBy.length > 0) {
    const { key, order } = sortBy[0]
    ticketsStore.updateFilters({
      sort: key,
      dir: order,
    })
    fetchTickets()
  }
}

const viewTicket = (ticket: any) => {
  router.push({ name: 'affiliate-tickets-id', params: { id: ticket.id } })
}

const openCreateDialog = () => {
  createForm.value = {
    subject: '',
    category: 'general',
    priority: 'normal',
    message: '',
    attachments: [],
  }
  showCreateDialog.value = true
}

const handleFileUpload = (event: Event) => {
  const target = event.target as HTMLInputElement
  if (target.files) {
    createForm.value.attachments = Array.from(target.files)
  }
}

const createTicket = async () => {
  try {
    await ticketsStore.createTicket(createForm.value)
    showSuccess('Ticket créé avec succès')
    showCreateDialog.value = false
    fetchTickets()
  } catch (err: any) {
    showError(err.message || 'Erreur lors de la création du ticket')
  }
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
  fetchTickets()
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
          Support
        </h1>
        <p class="text-body-1 text-medium-emphasis">
          Créez et gérez vos tickets de support
        </p>
      </div>
      <VBtn
        color="primary"
        prepend-icon="tabler-plus"
        @click="openCreateDialog"
      >
        Nouveau ticket
      </VBtn>
    </div>

    <!-- Filters -->
    <VCard class="mb-6">
      <VCardText>
        <VRow>
          <VCol cols="12" sm="6" md="4" lg="3">
            <VTextField
              v-model="searchQuery"
              label="Rechercher..."
              placeholder="Sujet, référence..."
              prepend-inner-icon="tabler-search"
              clearable
              @keyup.enter="applyFilters"
            />
          </VCol>
          <VCol cols="12" sm="6" md="4" lg="2">
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
          <VCol cols="12" sm="6" md="4" lg="2">
            <VSelect
              v-model="selectedPriority"
              :items="priorityOptions"
              label="Priorité"
              multiple
              chips
              clearable
              item-title="title"
              item-value="value"
            />
          </VCol>
          <VCol cols="12" sm="6" md="4" lg="2">
            <VSelect
              v-model="selectedCategory"
              :items="categoryOptions"
              label="Catégorie"
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
              label="Date début"
              type="date"
            />
          </VCol>
          <VCol cols="12" sm="6" md="4" lg="1" class="d-flex align-center gap-2">
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
        :items="tickets"
        :loading="loading.list"
        :items-length="pagination.total"
        :items-per-page="pagination.per_page"
        :page="pagination.current_page"
        item-value="id"
        @update:page="handlePageChange"
        @update:items-per-page="handlePerPageChange"
        @update:sort-by="handleSort"
      >
        <!-- Reference Column -->
        <template #item.id="{ item }">
          <VBtn
            variant="text"
            size="small"
            color="primary"
            @click="viewTicket(item)"
          >
            #{{ item.id.toString().slice(-8) }}
          </VBtn>
        </template>

        <!-- Subject Column -->
        <template #item.subject="{ item }">
          <div class="font-weight-medium">{{ item.subject }}</div>
        </template>

        <!-- Category Column -->
        <template #item.category="{ item }">
          <VChip
            size="small"
            variant="tonal"
            color="info"
          >
            {{ ticketsStore.getCategoryLabel(item.category) }}
          </VChip>
        </template>

        <!-- Priority Column -->
        <template #item.priority="{ item }">
          <VChip
            :color="ticketsStore.getPriorityColor(item.priority)"
            size="small"
            variant="tonal"
          >
            {{ ticketsStore.getPriorityLabel(item.priority) }}
          </VChip>
        </template>

        <!-- Status Column -->
        <template #item.status="{ item }">
          <VChip
            :color="ticketsStore.getStatusColor(item.status)"
            size="small"
            variant="tonal"
          >
            {{ ticketsStore.getStatusLabel(item.status) }}
          </VChip>
        </template>

        <!-- Last Activity Column -->
        <template #item.last_activity_at="{ item }">
          <span class="text-body-2">
            {{ formatDate(item.last_activity_at) }}
          </span>
        </template>

        <!-- Actions Column -->
        <template #item.actions="{ item }">
          <VBtn
            icon="tabler-eye"
            size="small"
            variant="text"
            color="primary"
            @click="viewTicket(item)"
          />
        </template>

        <!-- No data -->
        <template #no-data>
          <div class="text-center py-8">
            <VIcon
              icon="tabler-ticket-off"
              size="64"
              class="text-disabled mb-4"
            />
            <h3 class="text-h6 mb-2">Aucun ticket trouvé</h3>
            <p class="text-body-2 text-medium-emphasis">
              Vous n'avez pas encore créé de ticket de support.
            </p>
            <VBtn
              color="primary"
              class="mt-4"
              @click="openCreateDialog"
            >
              Créer votre premier ticket
            </VBtn>
          </div>
        </template>
      </VDataTableServer>
    </VCard>

    <!-- Create Ticket Dialog -->
    <VDialog
      v-model="showCreateDialog"
      max-width="600"
    >
      <VCard>
        <VCardTitle>Créer un nouveau ticket</VCardTitle>
        <VCardText>
          <VForm @submit.prevent="createTicket">
            <VRow>
              <VCol cols="12">
                <VTextField
                  v-model="createForm.subject"
                  label="Sujet *"
                  placeholder="Décrivez brièvement votre problème..."
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VSelect
                  v-model="createForm.category"
                  :items="categoryOptions"
                  label="Catégorie *"
                  item-title="title"
                  item-value="value"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VSelect
                  v-model="createForm.priority"
                  :items="priorityOptions"
                  label="Priorité *"
                  item-title="title"
                  item-value="value"
                  required
                />
              </VCol>
              <VCol cols="12">
                <VTextarea
                  v-model="createForm.message"
                  label="Message *"
                  placeholder="Décrivez votre problème en détail..."
                  rows="5"
                  counter="5000"
                  maxlength="5000"
                  required
                />
              </VCol>
              <VCol cols="12">
                <VFileInput
                  label="Pièces jointes (optionnel)"
                  multiple
                  accept="image/*,.pdf,.doc,.docx,.txt"
                  prepend-icon="tabler-paperclip"
                  @change="handleFileUpload"
                />
                <p class="text-caption text-medium-emphasis mt-1">
                  Formats acceptés: Images, PDF, DOC, DOCX, TXT (max 10MB par fichier)
                </p>
              </VCol>
            </VRow>
          </VForm>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="outlined"
            @click="showCreateDialog = false"
          >
            Annuler
          </VBtn>
          <VBtn
            color="primary"
            :loading="loading.create"
            @click="createTicket"
          >
            Créer le ticket
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
