<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useAffiliateApplicationsStore } from '@/stores/admin/affiliateApplications'
import { useConfirmAction } from '@/composables/useConfirmAction'
import { useNotifications } from '@/composables/useNotifications'

definePage({
  meta: {
    requiresAuth: true,
    requiresRole: 'admin',
  },
})

const affiliateApplicationsStore = useAffiliateApplicationsStore()
const { confirm } = useConfirmAction()
const { showSuccess, showError } = useNotifications()

// Local state
const searchQuery = ref('')
const selectedApprovalStatus = ref('')
const selectedEmailVerified = ref('')

const dateFrom = ref('')
const dateTo = ref('')
const itemsPerPage = ref(15)
const showApproveDialog = ref(false)
const showRefuseDialog = ref(false)
const selectedUser = ref<any>(null)
const approveReason = ref('')
const refuseReason = ref('')

// Computed
const isLoading = computed(() => affiliateApplicationsStore.isLoading)
const applications = computed(() => affiliateApplicationsStore.applications)
const pagination = computed(() => affiliateApplicationsStore.pagination)
const stats = computed(() => affiliateApplicationsStore.stats)

// Table headers
const headers = [
  { title: 'Utilisateur', key: 'user', sortable: false },
  { title: 'Email', key: 'email', sortable: true },
  { title: 'Téléphone', key: 'telephone', sortable: false },
  { title: 'Email Vérifié', key: 'email_verified', sortable: true },
  { title: 'Statut Approbation', key: 'approval_status', sortable: true },

  { title: 'Inscrit le', key: 'created_at', sortable: true },
  { title: 'Actions', key: 'actions', sortable: false },
]

// Filter options
const approvalStatusOptions = [
  { title: 'Tous', value: '' },
  { title: 'En attente d\'approbation', value: 'pending_approval' },
  { title: 'Approuvé', value: 'approved' },
  { title: 'Refusé', value: 'refused' },
]

const emailVerifiedOptions = [
  { title: 'Tous', value: '' },
  { title: 'Email vérifié', value: 'true' },
  { title: 'Email non vérifié', value: 'false' },
]



// Methods
const fetchApplications = async () => {
  await affiliateApplicationsStore.fetchApplications({
    q: searchQuery.value || undefined,
    approval_status: selectedApprovalStatus.value || undefined,
    email_verified: selectedEmailVerified.value || undefined,
    from: dateFrom.value || undefined,
    to: dateTo.value || undefined,
    perPage: itemsPerPage.value,
  })
}

// Simple debounce implementation
let debounceTimer: NodeJS.Timeout
const debouncedFetch = () => {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(fetchApplications, 300)
}

const handleSearch = () => {
  affiliateApplicationsStore.filters.page = 1
  debouncedFetch()
}

const handlePageChange = (page: number) => {
  affiliateApplicationsStore.filters.page = page
  fetchApplications()
}

const handleSort = (sortBy: any) => {
  if (sortBy.length > 0) {
    affiliateApplicationsStore.filters.sort = sortBy[0].key
    affiliateApplicationsStore.filters.dir = sortBy[0].order
    fetchApplications()
  }
}

const openApproveDialog = (application: any) => {
  selectedUser.value = application
  approveReason.value = ''
  showApproveDialog.value = true
}

const openRefuseDialog = (application: any) => {
  selectedUser.value = application
  refuseReason.value = ''
  showRefuseDialog.value = true
}

const approveApplication = async () => {
  if (!selectedUser.value) return

  try {
    await affiliateApplicationsStore.approveApplication(selectedUser.value.id, approveReason.value)
    showSuccess('Demande d\'affiliation approuvée avec succès')
    showApproveDialog.value = false
    fetchApplications()
  } catch (error: any) {
    showError(error.message || 'Erreur lors de l\'approbation')
  }
}

const refuseApplication = async () => {
  if (!selectedUser.value || !refuseReason.value.trim()) return

  try {
    await affiliateApplicationsStore.refuseApplication(selectedUser.value.id, refuseReason.value)
    showSuccess('Demande refusée avec succès')
    showRefuseDialog.value = false
    fetchApplications()
  } catch (error: any) {
    showError(error.message || 'Erreur lors du refus')
  }
}

const resendVerification = async (application: any) => {
  const confirmed = await confirm({
    title: 'Renvoyer l\'email de vérification',
    text: `Renvoyer l'email de vérification à ${application.email} ?`,
    confirmText: 'Renvoyer',
    color: 'primary',
  })

  if (confirmed) {
    try {
      await affiliateApplicationsStore.resendVerification(application.id)
      showSuccess('Email de vérification envoyé')
    } catch (error: any) {
      showError(error.message || 'Erreur lors de l\'envoi')
    }
  }
}

const getApprovalStatusColor = (status: string) => {
  switch (status) {
    case 'approved':
      return 'success'
    case 'pending_approval':
      return 'warning'
    case 'refused':
      return 'error'
    default:
      return 'default'
  }
}

const getApprovalStatusText = (status: string) => {
  switch (status) {
    case 'approved':
      return 'Approuvé'
    case 'pending_approval':
      return 'En attente'
    case 'refused':
      return 'Refusé'
    default:
      return status
  }
}



const formatDate = (date: string) => {
  return new Date(date).toLocaleDateString('fr-FR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  })
}

const resetFilters = () => {
  searchQuery.value = ''
  selectedApprovalStatus.value = ''
  selectedEmailVerified.value = ''

  dateFrom.value = ''
  dateTo.value = ''
  affiliateApplicationsStore.resetFilters()
  fetchApplications()
}

// Lifecycle
onMounted(async () => {
  await affiliateApplicationsStore.fetchStats()
  await fetchApplications()
})
</script>

<template>
  <div class="d-flex flex-column gap-6">
    <!-- Page Header -->
    <div class="d-flex justify-space-between align-center mb-6">
      <div>
        <h1 class="text-h4 font-weight-bold mb-1">
          File d'Attente d'Approbation
        </h1>
        <p class="text-body-1 mb-0">
          Gérer les demandes d'inscription des affiliés
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

    <!-- Stats Cards -->
    <VRow v-if="stats" class="mb-6">
      <VCol cols="12" md="2">
        <VCard variant="tonal" color="warning">
          <VCardText class="text-center">
            <div class="text-h4">{{ stats.pending_approval }}</div>
            <div class="text-body-2">En attente</div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" md="2">
        <VCard variant="tonal" color="info">
          <VCardText class="text-center">
            <div class="text-h4">{{ stats.email_not_verified }}</div>
            <div class="text-body-2">Email non vérifié</div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" md="2">
        <VCard variant="tonal" color="success">
          <VCardText class="text-center">
            <div class="text-h4">{{ stats.approved_applications }}</div>
            <div class="text-body-2">Approuvés</div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" md="2">
        <VCard variant="tonal" color="error">
          <VCardText class="text-center">
            <div class="text-h4">{{ stats.refused_applications }}</div>
            <div class="text-body-2">Refusés</div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" md="2">
        <VCard variant="tonal" color="primary">
          <VCardText class="text-center">
            <div class="text-h4">{{ stats.recent_signups }}</div>
            <div class="text-body-2">7 derniers jours</div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Filters Card -->
    <VCard class="mb-6">
      <VCardText>
        <VRow>
          <VCol cols="12" md="3">
            <VTextField
              v-model="searchQuery"
              label="Rechercher..."
              placeholder="Nom, email, téléphone..."
              prepend-inner-icon="tabler-search"
              clearable
              @input="handleSearch"
            />
          </VCol>
          <VCol cols="12" md="2">
            <VSelect
              v-model="selectedApprovalStatus"
              label="Statut Approbation"
              :items="approvalStatusOptions"
              clearable
              @update:model-value="handleSearch"
            />
          </VCol>
          <VCol cols="12" md="2">
            <VSelect
              v-model="selectedEmailVerified"
              label="Email Vérifié"
              :items="emailVerifiedOptions"
              clearable
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
        <VRow>
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
        </VRow>
      </VCardText>
    </VCard>

    <!-- Data Table -->
    <VCard>
      <VDataTableServer
        v-model:items-per-page="itemsPerPage"
        :headers="headers"
        :items="applications"
        :items-length="pagination.total"
        :loading="isLoading"
        :page="pagination.current_page"
        @update:page="handlePageChange"
        @update:sort-by="handleSort"
      >
        <!-- User Column -->
        <template #item.user="{ item }">
          <div class="d-flex align-center gap-3">
            <VAvatar
              size="40"
              color="primary"
              variant="tonal"
            >
              <VIcon icon="tabler-user" />
            </VAvatar>
            <div>
              <div class="font-weight-medium">
                {{ item.nom_complet }}
              </div>
              <div class="text-caption text-medium-emphasis">
                ID: {{ item.id.slice(0, 8) }}
              </div>
            </div>
          </div>
        </template>

        <!-- Email Verified Column -->
        <template #item.email_verified="{ item }">
          <VChip
            size="small"
            :color="item.email_verified_at ? 'success' : 'warning'"
            variant="tonal"
          >
            {{ item.email_verified_at ? 'Vérifié' : 'Non vérifié' }}
          </VChip>
        </template>

        <!-- Approval Status Column -->
        <template #item.approval_status="{ item }">
          <VChip
            size="small"
            :color="getApprovalStatusColor(item.approval_status)"
            variant="tonal"
          >
            {{ getApprovalStatusText(item.approval_status) }}
          </VChip>
        </template>



        <!-- Created At Column -->
        <template #item.created_at="{ item }">
          <div class="text-body-2">
            {{ formatDate(item.created_at) }}
          </div>
        </template>

        <!-- Actions Column -->
        <template #item.actions="{ item }">
          <div class="d-flex gap-1">
            <!-- Resend Verification -->
            <VBtn
              v-if="!item.email_verified_at"
              size="small"
              color="info"
              variant="text"
              icon="tabler-mail"
              @click="resendVerification(item)"
            />

            <!-- Approve -->
            <VBtn
              v-if="item.approval_status === 'pending_approval'"
              size="small"
              color="success"
              variant="text"
              icon="tabler-check"
              @click="openApproveDialog(item)"
            />

            <!-- Refuse -->
            <VBtn
              v-if="item.approval_status === 'pending_approval'"
              size="small"
              color="error"
              variant="text"
              icon="tabler-x"
              @click="openRefuseDialog(item)"
            />
          </div>
        </template>

        <!-- No data -->
        <template #no-data>
          <div class="text-center py-8">
            <VIcon
              icon="tabler-users-off"
              size="64"
              class="mb-4"
              color="disabled"
            />
            <h3 class="text-h6 mb-2">Aucun utilisateur trouvé</h3>
            <p class="text-body-2 text-medium-emphasis">
              Aucun utilisateur ne correspond aux critères de recherche
            </p>
          </div>
        </template>
      </VDataTableServer>
    </VCard>

    <!-- Approve Dialog -->
    <VDialog
      v-model="showApproveDialog"
      max-width="500"
    >
      <VCard>
        <VCardTitle>Approuver l'Utilisateur</VCardTitle>
        <VCardText>
          <p class="mb-4">
            Approuver <strong>{{ selectedUser?.nom_complet }}</strong> comme affilié ?
          </p>
          <VTextarea
            v-model="approveReason"
            label="Raison (optionnel)"
            placeholder="Raison de l'approbation..."
            variant="outlined"
            rows="3"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            color="secondary"
            variant="text"
            @click="showApproveDialog = false"
          >
            Annuler
          </VBtn>
          <VBtn
            color="success"
            variant="elevated"
            @click="approveApplication"
          >
            Approuver
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Refuse Dialog -->
    <VDialog
      v-model="showRefuseDialog"
      max-width="500"
    >
      <VCard>
        <VCardTitle>Refuser la Demande</VCardTitle>
        <VCardText>
          <p class="mb-4">
            Refuser la demande d'affiliation de <strong>{{ selectedUser?.nom_complet }}</strong> ?
          </p>
          <VTextarea
            v-model="refuseReason"
            label="Raison du refus *"
            placeholder="Expliquez pourquoi vous refusez cette demande..."
            variant="outlined"
            rows="3"
            :rules="[v => !!v || 'La raison est requise']"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            color="secondary"
            variant="text"
            @click="showRefuseDialog = false"
          >
            Annuler
          </VBtn>
          <VBtn
            color="error"
            variant="elevated"
            :disabled="!refuseReason.trim()"
            @click="refuseApplication"
          >
            Refuser
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
