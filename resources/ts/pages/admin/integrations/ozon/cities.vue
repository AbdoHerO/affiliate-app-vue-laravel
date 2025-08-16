<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { useOzonCitiesStore, type ShippingCity } from '@/stores/admin/ozonCities'
import { useQuickConfirm } from '@/composables/useConfirmAction'
import { useNotifications } from '@/composables/useNotifications'
import { useDebounceFn } from '@vueuse/core'
import ConfirmActionDialog from '@/components/common/ConfirmActionDialog.vue'
import SoftDeleteActions from '@/components/common/SoftDeleteActions.vue'

// Store
const ozonCitiesStore = useOzonCitiesStore()

// Composables
const {
  confirmDelete,
  isDialogVisible,
  isLoading,
  dialogTitle,
  dialogText,
  dialogIcon,
  dialogColor,
  confirmButtonText,
  cancelButtonText,
  handleConfirm,
  handleCancel
} = useQuickConfirm()
const { showSuccess, showError, snackbar } = useNotifications()

// UI State
const showCreateDialog = ref(false)
const showEditDialog = ref(false)
const showImportDialog = ref(false)
const selectedCity = ref<ShippingCity | null>(null)

// Form state
const cityForm = ref({
  city_id: '',
  name: '',
  ref: '',
  active: true,
  prices: {
    delivered: null as number | null,
    returned: null as number | null,
    refused: null as number | null,
  },
})

// Search and filters
const searchQuery = ref('')
const activeFilter = ref('')
const deleteFilter = ref('active')
const perPageOptions = [10, 15, 25, 50]

// Clear filters function
const clearFilters = () => {
  searchQuery.value = ''
  activeFilter.value = ''
  deleteFilter.value = 'active'
  ozonCitiesStore.setFilters({
    q: '',
    active: '',
    include_deleted: 'active',
    page: 1,
    per_page: 15
  })
  debouncedSearch()
}

// Import
const importFile = ref<File | null>(null)
const importFileInput = ref<HTMLInputElement>()

// Load data on mount
onMounted(async () => {
  await ozonCitiesStore.fetchCities()
})

// Fetch cities function for refresh
const fetchCities = async () => {
  await ozonCitiesStore.fetchCities()
}

// Debounced search
const debouncedSearch = useDebounceFn(async () => {
  ozonCitiesStore.setFilters({
    q: searchQuery.value,
    active: activeFilter.value,
    include_deleted: deleteFilter.value,
    page: 1
  })
  await ozonCitiesStore.fetchCities()
}, 300)

// Watch for search changes
watch(searchQuery, debouncedSearch)

// Watch for filter changes
watch(activeFilter, async () => {
  ozonCitiesStore.setFilters({
    active: activeFilter.value,
    page: 1
  })
  await ozonCitiesStore.fetchCities()
})

watch(deleteFilter, async () => {
  ozonCitiesStore.setFilters({
    include_deleted: deleteFilter.value,
    page: 1
  })
  await ozonCitiesStore.fetchCities()
})

// Pagination
const changePage = async (page: number) => {
  ozonCitiesStore.setFilters({ page })
  await ozonCitiesStore.fetchCities()
}

const changePerPage = async (perPage: number) => {
  ozonCitiesStore.setFilters({ 
    per_page: perPage,
    page: 1 
  })
  await ozonCitiesStore.fetchCities()
}

// CRUD Operations
const openCreateDialog = () => {
  resetForm()
  showCreateDialog.value = true
}

const openEditDialog = (city: ShippingCity) => {
  selectedCity.value = city
  cityForm.value = {
    city_id: city.city_id || '',
    name: city.name || '',
    ref: city.ref || '',
    active: city.active ?? true,
    prices: {
      delivered: city.prices?.delivered || null,
      returned: city.prices?.returned || null,
      refused: city.prices?.refused || null,
    },
  }
  console.log('Edit form populated with:', cityForm.value)
  showEditDialog.value = true
}

const resetForm = () => {
  cityForm.value = {
    city_id: '',
    name: '',
    ref: '',
    active: true,
    prices: {
      delivered: null,
      returned: null,
      refused: null,
    },
  }
  selectedCity.value = null
}

const saveCity = async () => {
  try {
    console.log('Form data before save:', cityForm.value)

    let success = false

    if (selectedCity.value) {
      // Update existing city
      success = await ozonCitiesStore.updateCity(selectedCity.value.id, cityForm.value as any)
    } else {
      // Create new city
      success = await ozonCitiesStore.createCity(cityForm.value as any)
    }

    if (success) {
      showSuccess(
        selectedCity.value ? 'Ville modifiée avec succès' : 'Ville créée avec succès'
      )
      showCreateDialog.value = false
      showEditDialog.value = false
      resetForm()
    } else {
      showError(ozonCitiesStore.error || 'Erreur lors de la sauvegarde')
    }
  } catch (error) {
    console.error('Error saving city:', error)
    showError('Erreur lors de la sauvegarde')
  }
}



// Import functionality
const openImportDialog = () => {
  importFile.value = null
  showImportDialog.value = true
}

const handleFileSelect = (event: Event) => {
  const target = event.target as HTMLInputElement
  if (target.files && target.files.length > 0) {
    importFile.value = target.files[0]
  }
}

const importCities = async () => {
  if (!importFile.value) return

  try {
    const success = await ozonCitiesStore.importCities(importFile.value)

    if (success) {
      showSuccess('Villes importées avec succès')
      showImportDialog.value = false
      importFile.value = null
    } else {
      showError(ozonCitiesStore.error || 'Erreur lors de l\'importation')
    }
  } catch (error) {
    console.error('Error importing cities:', error)
    showError('Erreur lors de l\'importation')
  }
}

// Table headers
const headers = [
  { title: 'ID Ville', key: 'city_id', sortable: false },
  { title: 'Nom', key: 'name', sortable: false },
  { title: 'Statut', key: 'active', sortable: false },
  { title: 'Dernière MAJ', key: 'updated_at', sortable: false },
  { title: 'Actions', key: 'actions', sortable: false },
]

// Format date
const formatDate = (date: string) => {
  return new Date(date).toLocaleDateString('fr-FR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

// Format price
const formatPrice = (price: number | null | undefined) => {
  if (price === null || price === undefined) return '-'
  return `${price.toFixed(2)} MAD`
}
</script>

<template>
  <div>
    <!-- Page Header -->
    <div class="d-flex justify-space-between align-center mb-6">
      <div>
        <h1 class="text-h4 font-weight-bold mb-1">
          Villes OzonExpress
        </h1>
        <p class="text-body-1 text-medium-emphasis">
          Gérez les villes disponibles pour les expéditions OzonExpress
        </p>
      </div>
      
      <div class="d-flex gap-3">
        <VBtn
          color="secondary"
          variant="outlined"
          @click="openImportDialog"
        >
          <VIcon icon="tabler-upload" start />
          Importer
        </VBtn>
        
        <VBtn
          color="primary"
          @click="openCreateDialog"
        >
          <VIcon icon="tabler-plus" start />
          Nouvelle ville
        </VBtn>
      </div>
    </div>

    <!-- Stats Cards -->
    <VRow class="mb-6">
      <VCol cols="12" sm="4">
        <VCard>
          <VCardText class="d-flex align-center">
            <VAvatar color="primary" variant="tonal" class="me-4">
              <VIcon icon="tabler-map-pin" />
            </VAvatar>
            <div>
              <div class="text-h5 font-weight-bold">
                {{ ozonCitiesStore.stats.total }}
              </div>
              <div class="text-body-2 text-medium-emphasis">
                Total des villes
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
      
      <VCol cols="12" sm="4">
        <VCard>
          <VCardText class="d-flex align-center">
            <VAvatar color="success" variant="tonal" class="me-4">
              <VIcon icon="tabler-check" />
            </VAvatar>
            <div>
              <div class="text-h5 font-weight-bold">
                {{ ozonCitiesStore.stats.active }}
              </div>
              <div class="text-body-2 text-medium-emphasis">
                Villes actives
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
      
      <VCol cols="12" sm="4">
        <VCard>
          <VCardText class="d-flex align-center">
            <VAvatar color="warning" variant="tonal" class="me-4">
              <VIcon icon="tabler-x" />
            </VAvatar>
            <div>
              <div class="text-h5 font-weight-bold">
                {{ ozonCitiesStore.stats.inactive }}
              </div>
              <div class="text-body-2 text-medium-emphasis">
                Villes inactives
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Filters and Search -->
    <VCard class="mb-6">
      <VCardText>
        <VRow>
          <VCol cols="12" md="4">
            <VTextField
              v-model="searchQuery"
              label="Rechercher"
              placeholder="Nom, ID ou référence..."
              variant="outlined"
              density="compact"
              clearable
            >
              <template #prepend-inner>
                <VIcon icon="tabler-search" />
              </template>
            </VTextField>
          </VCol>
          
          <VCol cols="12" md="2">
            <VSelect
              v-model="activeFilter"
              label="Statut"
              :items="[
                { title: 'Tous', value: '' },
                { title: 'Actives', value: '1' },
                { title: 'Inactives', value: '0' },
              ]"
              variant="outlined"
              density="compact"
              clearable
            />
          </VCol>

          <VCol cols="12" md="3">
            <VSelect
              v-model="deleteFilter"
              label="Affichage"
              :items="[
                { title: 'Actives seulement', value: 'active' },
                { title: 'Supprimées seulement', value: 'deleted' },
                { title: 'Toutes (actives + supprimées)', value: 'all' },
              ]"
              variant="outlined"
              density="compact"
            />
          </VCol>

          <VCol cols="12" md="2">
            <VSelect
              :model-value="ozonCitiesStore.filters.per_page"
              label="Par page"
              :items="perPageOptions"
              variant="outlined"
              density="compact"
              @update:model-value="changePerPage"
            />
          </VCol>

          <VCol cols="12" md="1" class="d-flex align-center">
            <VBtn
              variant="outlined"
              color="secondary"
              size="small"
              @click="clearFilters"
            >
              <VIcon icon="tabler-filter-off" class="me-1" />
              Effacer
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Cities Table -->
    <VCard>
      <VDataTable
        :headers="headers"
        :items="ozonCitiesStore.cities"
        :loading="ozonCitiesStore.loading"
        :items-per-page="ozonCitiesStore.filters.per_page"
        hide-default-footer
        class="text-no-wrap"
      >
        <!-- Active Status -->
        <template #[`item.active`]="{ item }">
          <VChip
            :color="item.active ? 'success' : 'error'"
            variant="tonal"
            size="small"
          >
            {{ item.active ? 'Active' : 'Inactive' }}
          </VChip>
        </template>



        <!-- Updated At -->
        <template #[`item.updated_at`]="{ item }">
          {{ formatDate(item.updated_at) }}
        </template>

        <!-- Actions -->
        <template #[`item.actions`]="{ item }">
          <SoftDeleteActions
            :item="item"
            entity-name="ville"
            api-endpoint="/admin/integrations/ozon/cities"
            item-name-field="name"
            :show-view="false"
            @deleted="fetchCities"
            @restored="fetchCities"
            @permanently-deleted="fetchCities"
            @edit="openEditDialog"
          />
        </template>
      </VDataTable>

      <!-- Pagination -->
      <VCardText v-if="ozonCitiesStore.totalPages > 1">
        <div class="d-flex justify-center">
          <VPagination
            :model-value="ozonCitiesStore.currentPage"
            :length="ozonCitiesStore.totalPages"
            @update:model-value="changePage"
          />
        </div>
      </VCardText>
    </VCard>

    <!-- Create/Edit Dialog -->
    <VDialog
      v-model="showCreateDialog"
      max-width="600"
      persistent
    >
      <VCard>
        <VCardTitle>Nouvelle ville</VCardTitle>
        
        <VCardText>
          <VForm @submit.prevent="saveCity">
            <VRow>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="cityForm.city_id"
                  label="ID Ville"
                  placeholder="Ex: 97"
                  variant="outlined"
                  required
                />
              </VCol>
              
              <VCol cols="12" md="6">
                <VTextField
                  v-model="cityForm.ref"
                  label="Référence"
                  placeholder="Ex: CSA"
                  variant="outlined"
                />
              </VCol>
              
              <VCol cols="12">
                <VTextField
                  v-model="cityForm.name"
                  label="Nom de la ville"
                  placeholder="Ex: Casablanca"
                  variant="outlined"
                  required
                />
              </VCol>
              
              <VCol cols="12">
                <VCheckbox
                  v-model="cityForm.active"
                  label="Ville active"
                />
              </VCol>
              
              <VCol cols="12" md="4">
                <VTextField
                  v-model.number="cityForm.prices.delivered"
                  label="Prix livraison (MAD)"
                  type="number"
                  step="0.01"
                  variant="outlined"
                />
              </VCol>
              
              <VCol cols="12" md="4">
                <VTextField
                  v-model.number="cityForm.prices.returned"
                  label="Prix retour (MAD)"
                  type="number"
                  step="0.01"
                  variant="outlined"
                />
              </VCol>
              
              <VCol cols="12" md="4">
                <VTextField
                  v-model.number="cityForm.prices.refused"
                  label="Prix refus (MAD)"
                  type="number"
                  step="0.01"
                  variant="outlined"
                />
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
            :loading="ozonCitiesStore.loading"
            @click="saveCity"
          >
            Créer
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Edit Dialog -->
    <VDialog
      v-model="showEditDialog"
      max-width="600"
      persistent
    >
      <VCard>
        <VCardTitle>Modifier la ville</VCardTitle>
        
        <VCardText>
          <VForm @submit.prevent="saveCity">
            <VRow>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="cityForm.city_id"
                  label="ID Ville"
                  variant="outlined"
                  required
                />
              </VCol>
              
              <VCol cols="12" md="6">
                <VTextField
                  v-model="cityForm.ref"
                  label="Référence"
                  variant="outlined"
                />
              </VCol>
              
              <VCol cols="12">
                <VTextField
                  v-model="cityForm.name"
                  label="Nom de la ville"
                  variant="outlined"
                  required
                />
              </VCol>
              
              <VCol cols="12">
                <VCheckbox
                  v-model="cityForm.active"
                  label="Ville active"
                />
              </VCol>
              
              <VCol cols="12" md="4">
                <VTextField
                  v-model.number="cityForm.prices.delivered"
                  label="Prix livraison (MAD)"
                  type="number"
                  step="0.01"
                  variant="outlined"
                />
              </VCol>
              
              <VCol cols="12" md="4">
                <VTextField
                  v-model.number="cityForm.prices.returned"
                  label="Prix retour (MAD)"
                  type="number"
                  step="0.01"
                  variant="outlined"
                />
              </VCol>
              
              <VCol cols="12" md="4">
                <VTextField
                  v-model.number="cityForm.prices.refused"
                  label="Prix refus (MAD)"
                  type="number"
                  step="0.01"
                  variant="outlined"
                />
              </VCol>
            </VRow>
          </VForm>
        </VCardText>
        
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="outlined"
            @click="showEditDialog = false"
          >
            Annuler
          </VBtn>
          <VBtn
            color="primary"
            :loading="ozonCitiesStore.loading"
            @click="saveCity"
          >
            Modifier
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Import Dialog -->
    <VDialog
      v-model="showImportDialog"
      max-width="500"
      persistent
    >
      <VCard>
        <VCardTitle>Importer des villes</VCardTitle>
        
        <VCardText>
          <VFileInput
            ref="importFileInput"
            label="Fichier à importer"
            accept=".json,.csv"
            variant="outlined"
            @change="handleFileSelect"
          />
          
          <VAlert
            type="info"
            variant="tonal"
            class="mt-4"
          >
            Formats acceptés : JSON, CSV. Le fichier doit contenir les colonnes : city_id, name, ref (optionnel), prices.
          </VAlert>
        </VCardText>
        
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="outlined"
            @click="showImportDialog = false"
          >
            Annuler
          </VBtn>
          <VBtn
            color="primary"
            :loading="ozonCitiesStore.loading"
            :disabled="!importFile"
            @click="importCities"
          >
            Importer
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Snackbar for notifications -->
    <VSnackbar
      v-model="snackbar.show"
      :color="snackbar.color"
      :timeout="snackbar.timeout"
      location="top end"
    >
      {{ snackbar.message }}
    </VSnackbar>

    <!-- Confirm Dialog -->
    <ConfirmActionDialog
      v-model="isDialogVisible"
      :title="dialogTitle"
      :text="dialogText"
      :icon="dialogIcon"
      :color="dialogColor"
      :confirm-text="confirmButtonText"
      :cancel-text="cancelButtonText"
      :loading="isLoading"
      @confirm="handleConfirm"
      @cancel="handleCancel"
    />
  </div>
</template>

<style scoped>
.gap-2 {
  gap: 8px;
}

.gap-3 {
  gap: 12px;
}
</style>
