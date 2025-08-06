<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useBoutiquesStore, type Boutique } from '@/stores/admin/boutiques'
import Breadcrumbs from '@/components/common/Breadcrumbs.vue'
import ConfirmModal from '@/components/common/ConfirmModal.vue'
import BoutiqueCrudDialog from '@/components/admin/boutiques/BoutiqueCrudDialog.vue'
import BoutiqueViewDialog from '@/components/admin/boutiques/BoutiqueViewDialog.vue'

definePage({
  meta: {
    requiresAuth: true,
    requiresRole: 'admin',
    layout: 'default',
  },
})

// Composables
const { t } = useI18n()
const boutiquesStore = useBoutiquesStore()

// Reactive state
const searchQuery = ref('')
const statusFilter = ref('')
const sortBy = ref('created_at')
const sortDesc = ref(true)
const showCrudDialog = ref(false)
const showViewDialog = ref(false)
const showDeleteDialog = ref(false)
const selectedBoutique = ref<Boutique | null>(null)
const crudMode = ref<'create' | 'edit'>('create')
const isDeleting = ref(false)

// Store getters
const { 
  items: boutiques,
  isLoading,
  pagination,
  hasItems,
  totalItems,
  getStatusBadgeColor
} = boutiquesStore

// Computed properties
const currentPage = computed(() => pagination.current_page)
const perPage = computed(() => pagination.per_page)

const totalBoutiques = computed(() => totalItems)
const activeCount = computed(() => boutiques.filter(b => b.statut === 'actif').length)
const suspendedCount = computed(() => boutiques.filter(b => b.statut === 'suspendu').length)  
const deactivatedCount = computed(() => boutiques.filter(b => b.statut === 'desactive').length)

const breadcrumbs = computed(() => [
  { title: t('title_admin_dashboard'), to: '/admin' },
  { title: t('admin_boutiques_title'), active: true }
])

const headers = computed(() => [
  { title: t('admin_boutiques_name'), key: 'nom', sortable: true },
  { title: t('form_name'), key: 'slug', sortable: true },
  { title: t('admin_boutiques_status'), key: 'statut', sortable: true },
  { title: t('user_name'), key: 'proprietaire', sortable: false },
  { title: t('admin_boutiques_commission_rate'), key: 'commission_par_defaut', sortable: true },
  { title: t('table_actions'), key: 'actions', sortable: false }
])

const statusOptions = computed(() => [
  { title: t('status_active'), value: 'actif' },
  { title: t('status_inactive'), value: 'suspendu' },
  { title: t('status_cancelled'), value: 'desactive' }
])

const sortOptions = computed(() => [
  { title: t('common.sort.created_desc'), value: 'created_at' },
  { title: t('common.sort.name_asc'), value: 'nom' },
  { title: t('common.sort.status'), value: 'statut' }
])

const deleteMessage = computed(() => {
  return selectedBoutique.value 
    ? t('admin.boutiques.delete.message', { name: selectedBoutique.value.nom })
    : ''
})

// Methods
const fetchData = () => {
  boutiquesStore.fetchBoutiques()
}

const applyFilters = () => {
  boutiquesStore.setFilters({
    q: searchQuery.value,
    statut: statusFilter.value,
    sort: sortBy.value,
    dir: sortDesc.value ? 'desc' : 'asc',
    page: 1
  })
  fetchData()
}

// Simple debounce utility
const debounce = (func: Function, wait: number) => {
  let timeout: NodeJS.Timeout
  return (...args: any[]) => {
    clearTimeout(timeout)
    timeout = setTimeout(() => func(...args), wait)
  }
}

const debouncedSearch = debounce(() => {
  applyFilters()
}, 300)

const resetFilters = () => {
  searchQuery.value = ''
  statusFilter.value = ''
  sortBy.value = 'created_at'
  sortDesc.value = true
  boutiquesStore.resetFilters()
  fetchData()
}

const updatePage = (page: number) => {
  boutiquesStore.setFilters({ page })
  fetchData()
}

const updatePerPage = (perPage: number) => {
  boutiquesStore.setFilters({ per_page: perPage, page: 1 })
  fetchData()
}

const updateSort = ({ sortBy: newSortBy, sortDesc: newSortDesc }: { sortBy: string; sortDesc: boolean }) => {
  sortBy.value = newSortBy
  sortDesc.value = newSortDesc
  boutiquesStore.setFilters({
    sort: newSortBy,
    dir: newSortDesc ? 'desc' : 'asc',
    page: 1
  })
  fetchData()
}

const openCreateDialog = () => {
  selectedBoutique.value = null
  crudMode.value = 'create'
  showCrudDialog.value = true
}

const viewBoutique = (boutique: Boutique) => {
  selectedBoutique.value = boutique
  showViewDialog.value = true
}

const editBoutique = (boutique: Boutique) => {
  selectedBoutique.value = boutique
  crudMode.value = 'edit'
  showCrudDialog.value = true
}

const deleteBoutique = (boutique: Boutique) => {
  selectedBoutique.value = boutique
  showDeleteDialog.value = true
}

const confirmDelete = async () => {
  if (!selectedBoutique.value) return

  isDeleting.value = true
  try {
    await boutiquesStore.destroy(selectedBoutique.value.id)
    showDeleteDialog.value = false
    selectedBoutique.value = null
  } catch (error) {
    console.error('Delete failed:', error)
  } finally {
    isDeleting.value = false
  }
}

const handleSaved = () => {
  showCrudDialog.value = false
  selectedBoutique.value = null
  fetchData()
}

const getStatusColor = (status: string) => {
  return getStatusBadgeColor(status)
}

const getAvatarColor = (name: string) => {
  const colors = ['primary', 'secondary', 'success', 'info', 'warning', 'error']
  const index = name.length % colors.length
  return colors[index]
}

const getInitials = (name: string) => {
  return name.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2)
}

// Lifecycle
onMounted(() => {
  fetchData()
})

// Watchers
watch([searchQuery], () => {
  debouncedSearch()
})
</script>

<template>
  <div class="d-flex flex-column gap-6">
    <!-- Page Header & Breadcrumbs -->
    <Breadcrumbs 
      :items="breadcrumbs"
      :title="$t('admin.boutiques.title')"
    />

    <!-- Stats Cards Row -->
    <VRow>
      <VCol cols="12" sm="6" md="3">
        <VCard class="text-center">
          <VCardText>
            <VIcon icon="tabler-building-store" size="48" class="mb-4 text-primary" />
            <div class="text-h4 font-weight-bold">{{ totalBoutiques }}</div>
            <div class="text-body-2 text-medium-emphasis">{{ $t('admin.boutiques.stats.total') }}</div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" sm="6" md="3">
        <VCard class="text-center">
          <VCardText>
            <VIcon icon="tabler-check-circle" size="48" class="mb-4 text-success" />
            <div class="text-h4 font-weight-bold text-success">{{ activeCount }}</div>
            <div class="text-body-2 text-medium-emphasis">{{ $t('admin.boutiques.stats.active') }}</div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" sm="6" md="3">
        <VCard class="text-center">
          <VCardText>
            <VIcon icon="tabler-pause-circle" size="48" class="mb-4 text-warning" />
            <div class="text-h4 font-weight-bold text-warning">{{ suspendedCount }}</div>
            <div class="text-body-2 text-medium-emphasis">{{ $t('admin.boutiques.stats.suspended') }}</div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" sm="6" md="3">
        <VCard class="text-center">
          <VCardText>
            <VIcon icon="tabler-x-circle" size="48" class="mb-4 text-error" />
            <div class="text-h4 font-weight-bold text-error">{{ deactivatedCount }}</div>
            <div class="text-body-2 text-medium-emphasis">{{ $t('admin.boutiques.stats.deactivated') }}</div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Main Data Table Card -->
    <VCard>
      <VCardTitle class="d-flex align-center justify-space-between flex-wrap gap-4">
        <span>{{ $t('admin.boutiques.list.title') }}</span>
        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          @click="openCreateDialog"
        >
          {{ $t('admin.boutiques.actions.create') }}
        </VBtn>
      </VCardTitle>

      <!-- Filters Row -->
      <VCardText>
        <VRow class="mb-4">
          <VCol cols="12" md="4">
            <VTextField
              v-model="searchQuery"
              :label="$t('common.search')"
              :placeholder="$t('admin.boutiques.search_placeholder')"
              prepend-inner-icon="tabler-search"
              clearable
              variant="outlined"
              density="compact"
              @input="debouncedSearch"
            />
          </VCol>
          <VCol cols="12" md="3">
            <VSelect
              v-model="statusFilter"
              :items="statusOptions"
              :label="$t('admin_boutiques_status')"
              variant="outlined"
              density="compact"
              clearable
              @update:model-value="applyFilters"
            />
          </VCol>
          <VCol cols="12" md="3">
            <VSelect
              v-model="sortBy"
              :items="sortOptions"
              :label="$t('common.sort_by')"
              variant="outlined"
              density="compact"
              @update:model-value="applyFilters"
            />
          </VCol>
          <VCol cols="12" md="2">
            <VBtn
              variant="outlined"
              color="secondary"
              @click="resetFilters"
            >
              {{ $t('common.reset') }}
            </VBtn>
          </VCol>
        </VRow>

        <!-- Data Table -->
        <VDataTable
          :items="boutiques"
          :headers="headers"
          :loading="isLoading"
          :items-per-page="perPage"
          :page="currentPage"
          :server-items-length="totalItems"
          class="elevation-0"
        >
          <!-- Status Column -->
          <template #[`item.statut`]="{ item }">
            <VChip
              :color="getStatusColor(item.statut)"
              size="small"
              variant="elevated"
            >
              {{ $t(`admin.boutiques.statuts.${item.statut}`) }}
            </VChip>
          </template>

          <!-- Owner Column -->
          <template #[`item.proprietaire`]="{ item }">
            <div class="d-flex align-center gap-3">
              <VAvatar size="32" :color="getAvatarColor(item.proprietaire.nom_complet)">
                {{ getInitials(item.proprietaire.nom_complet) }}
              </VAvatar>
              <div>
                <div class="text-body-2 font-weight-medium">{{ item.proprietaire.nom_complet }}</div>
                <div class="text-caption text-medium-emphasis">{{ item.proprietaire.email }}</div>
              </div>
            </div>
          </template>

          <!-- Commission Column -->
          <template #[`item.commission_par_defaut`]="{ item }">
            <span class="text-body-2">{{ item.commission_par_defaut }}%</span>
          </template>

          <!-- Actions Column -->
          <template #[`item.actions`]="{ item }">
            <div class="d-flex gap-1">
              <VTooltip text="View">
                <template #activator="{ props }">
                  <VBtn
                    v-bind="props"
                    icon="tabler-eye"
                    variant="text"
                    size="small"
                    color="primary"
                    @click="viewBoutique(item)"
                  />
                </template>
              </VTooltip>
              
              <VTooltip text="Edit">
                <template #activator="{ props }">
                  <VBtn
                    v-bind="props"
                    icon="tabler-edit"
                    variant="text"
                    size="small"
                    color="info"
                    @click="editBoutique(item)"
                  />
                </template>
              </VTooltip>

              <VTooltip text="Delete">
                <template #activator="{ props }">
                  <VBtn
                    v-bind="props"
                    icon="tabler-trash"
                    variant="text"
                    size="small"
                    color="error"
                    @click="deleteBoutique(item)"
                  />
                </template>
              </VTooltip>
            </div>
          </template>
        </VDataTable>
      </VCardText>
    </VCard>

    <!-- Create/Edit Dialog -->
    <BoutiqueCrudDialog
      v-model="showCrudDialog"
      :boutique="selectedBoutique"
      :mode="crudMode"
      @saved="handleSaved"
    />

    <!-- View Dialog -->
    <BoutiqueViewDialog
      v-model="showViewDialog"
      :boutique="selectedBoutique"
    />

    <!-- Delete Confirmation -->
    <ConfirmModal
      v-model="showDeleteDialog"
      :title="$t('admin_boutiques_delete_title')"
      :message="deleteMessage"
      :loading="isDeleting"
      @confirm="confirmDelete"
    />
  </div>
</template>
