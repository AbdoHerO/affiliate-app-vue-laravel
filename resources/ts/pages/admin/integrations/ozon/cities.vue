<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { useOzonCitiesStore, type ShippingCity } from '@/stores/admin/ozonCities'
import { useNotifications } from '@/composables/useNotifications'
import { useDebounceFn } from '@vueuse/core'
import SoftDeleteActions from '@/components/common/SoftDeleteActions.vue'
import { useI18n } from 'vue-i18n'

// Store
const ozonCitiesStore = useOzonCitiesStore()

// Composables
const { showSuccess, showError, snackbar } = useNotifications()
const { t } = useI18n()

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

// Handle delete event from SoftDeleteActions
const handleDeleted = async () => {
  await fetchCities()
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
watch(activeFilter, debouncedSearch)

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
        selectedCity.value ? t('admin.cities.cityModifiedSuccess') : t('admin.cities.cityCreatedSuccess')
      )
      showCreateDialog.value = false
      showEditDialog.value = false
      resetForm()
    } else {
      showError(ozonCitiesStore.error || t('admin.cities.saveError'))
    }
  } catch (error) {
    console.error('Error saving city:', error)
    showError(t('admin.cities.saveError'))
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
      showSuccess(t('admin.cities.citiesImportedSuccess'))
      showImportDialog.value = false
      importFile.value = null
    } else {
      showError(ozonCitiesStore.error || t('admin.cities.importError'))
    }
  } catch (error) {
    console.error('Error importing cities:', error)
    showError(t('admin.cities.importError'))
  }
}

// Table headers
const headers = [
  { title: t('admin.cities.cityId'), key: 'city_id', sortable: false },
  { title: t('admin.cities.name'), key: 'name', sortable: false },
  { title: t('admin.cities.status'), key: 'active', sortable: false },
  { title: t('admin.cities.lastUpdate'), key: 'updated_at', sortable: false },
  { title: t('table.actions'), key: 'actions', sortable: false },
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
          {{ t('admin.cities.ozonExpressCities') }}
        </h1>
        <p class="text-body-1 text-medium-emphasis">
          {{ t('admin.cities.manageCitiesDesc') }}
        </p>
      </div>
      
      <div class="d-flex gap-3">
        <VBtn
          color="secondary"
          variant="outlined"
          @click="openImportDialog"
        >
          <VIcon icon="tabler-upload" start />
          {{ t('admin.cities.import') }}
        </VBtn>
        
        <VBtn
          color="primary"
          @click="openCreateDialog"
        >
          <VIcon icon="tabler-plus" start />
          {{ t('admin.cities.newCity') }}
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
                {{ t('admin.cities.totalCities') }}
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
                {{ t('admin.cities.activeCities') }}
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
                {{ t('admin.cities.inactiveCities') }}
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
              :label="t('admin.cities.search')"
              :placeholder="t('admin.cities.searchPlaceholder')"
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
              :label="t('admin.cities.status')"
              :items="[
                { title: t('admin.cities.all'), value: '' },
                { title: t('admin.cities.active'), value: '1' },
                { title: t('admin.cities.inactive'), value: '0' },
              ]"
              variant="outlined"
              density="compact"
              clearable
            />
          </VCol>

          <VCol cols="12" md="3">
            <VSelect
              v-model="deleteFilter"
              :label="t('table.display')"
              :items="[
                { title: t('table.activeOnly'), value: 'active' },
                { title: t('table.deletedOnly'), value: 'deleted' },
                { title: t('table.allActiveAndDeleted'), value: 'all' },
              ]"
              variant="outlined"
              density="compact"
            />
          </VCol>

          <VCol cols="12" md="2">
            <VSelect
              :model-value="ozonCitiesStore.filters.per_page"
              :label="t('admin.cities.perPage')"
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
              {{ t('admin.cities.clear') }}
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
            {{ item.active ? t('admin.cities.active') : t('admin.cities.inactive') }}
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
            @deleted="handleDeleted"
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
        <VCardTitle>{{ t('admin.cities.newCity') }}</VCardTitle>
        
        <VCardText>
          <VForm @submit.prevent="saveCity">
            <VRow>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="cityForm.city_id"
                  :label="t('admin.cities.cityId')"
                  :placeholder="t('admin.cities.cityIdPlaceholder')"
                  variant="outlined"
                  required
                />
              </VCol>
              
              <VCol cols="12" md="6">
                <VTextField
                  v-model="cityForm.ref"
                  :label="t('admin.cities.reference')"
                  :placeholder="t('admin.cities.referencePlaceholder')"
                  variant="outlined"
                />
              </VCol>
              
              <VCol cols="12">
                <VTextField
                  v-model="cityForm.name"
                  :label="t('admin.cities.cityName')"
                  :placeholder="t('admin.cities.cityNamePlaceholder')"
                  variant="outlined"
                  required
                />
              </VCol>
              
              <VCol cols="12">
                <VCheckbox
                  v-model="cityForm.active"
                  :label="t('admin.cities.activeCity')"
                />
              </VCol>
              
              <VCol cols="12" md="4">
                <VTextField
                  v-model.number="cityForm.prices.delivered"
                  :label="t('admin.cities.deliveryPrice')"
                  type="number"
                  step="0.01"
                  variant="outlined"
                />
              </VCol>
              
              <VCol cols="12" md="4">
                <VTextField
                  v-model.number="cityForm.prices.returned"
                  :label="t('admin.cities.returnPrice')"
                  type="number"
                  step="0.01"
                  variant="outlined"
                />
              </VCol>
              
              <VCol cols="12" md="4">
                <VTextField
                  v-model.number="cityForm.prices.refused"
                  :label="t('admin.cities.refusedPrice')"
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
            {{ t('actions.cancel') }}
          </VBtn>
          <VBtn
            color="primary"
            :loading="ozonCitiesStore.loading"
            @click="saveCity"
          >
            {{ t('actions.create') }}
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
        <VCardTitle>{{ t('admin.cities.editCity') }}</VCardTitle>
        
        <VCardText>
          <VForm @submit.prevent="saveCity">
            <VRow>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="cityForm.city_id"
                  :label="t('admin.cities.cityId')"
                  variant="outlined"
                  required
                />
              </VCol>
              
              <VCol cols="12" md="6">
                <VTextField
                  v-model="cityForm.ref"
                  :label="t('admin.cities.reference')"
                  variant="outlined"
                />
              </VCol>
              
              <VCol cols="12">
                <VTextField
                  v-model="cityForm.name"
                  :label="t('admin.cities.cityName')"
                  variant="outlined"
                  required
                />
              </VCol>
              
              <VCol cols="12">
                <VCheckbox
                  v-model="cityForm.active"
                  :label="t('admin.cities.activeCity')"
                />
              </VCol>
              
              <VCol cols="12" md="4">
                <VTextField
                  v-model.number="cityForm.prices.delivered"
                  :label="t('admin.cities.deliveryPrice')"
                  type="number"
                  step="0.01"
                  variant="outlined"
                />
              </VCol>
              
              <VCol cols="12" md="4">
                <VTextField
                  v-model.number="cityForm.prices.returned"
                  :label="t('admin.cities.returnPrice')"
                  type="number"
                  step="0.01"
                  variant="outlined"
                />
              </VCol>
              
              <VCol cols="12" md="4">
                <VTextField
                  v-model.number="cityForm.prices.refused"
                  :label="t('admin.cities.refusedPrice')"
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
            {{ t('actions.cancel') }}
          </VBtn>
          <VBtn
            color="primary"
            :loading="ozonCitiesStore.loading"
            @click="saveCity"
          >
            {{ t('actions.edit') }}
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
        <VCardTitle>{{ t('admin.cities.importCities') }}</VCardTitle>
        
        <VCardText>
          <VFileInput
            ref="importFileInput"
            :label="t('admin.cities.fileToImport')"
            accept=".json,.csv"
            variant="outlined"
            @change="handleFileSelect"
          />
          
          <VAlert
            type="info"
            variant="tonal"
            class="mt-4"
          >
            {{ t('admin.cities.importFormats') }}
          </VAlert>
        </VCardText>
        
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="outlined"
            @click="showImportDialog = false"
          >
            {{ t('actions.cancel') }}
          </VBtn>
          <VBtn
            color="primary"
            :loading="ozonCitiesStore.loading"
            :disabled="!importFile"
            @click="importCities"
          >
            {{ t('admin.cities.import') }}
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
