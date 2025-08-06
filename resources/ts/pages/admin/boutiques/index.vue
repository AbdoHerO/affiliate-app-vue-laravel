<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
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
  },
})

// Composables
const router = useRouter()
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
  { title: t('created'), value: 'created_at' },
  { title: t('admin_boutiques_name'), value: 'nom' },
  { title: t('admin_boutiques_status'), value: 'statut' }
])

const deleteMessage = computed(() => {
  return selectedBoutique.value 
    ? t('admin_boutiques_delete_confirm', { name: selectedBoutique.value.nom })
    : ''
})

// Methods
const fetchData = () => {
  boutiquesStore.fetchBoutiques()
}

const applyFilters = () => {
  boutiquesStore.setFilters({
    search: searchQuery.value,
    statut: statusFilter.value,
    sort_by: sortBy.value,
    sort_desc: sortDesc.value,
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
  applyFilters()
}

// Removed openCreateDialog - using direct navigation instead

const openEditDialog = (boutique: Boutique) => {
  selectedBoutique.value = boutique
  crudMode.value = 'edit'
  showCrudDialog.value = true
}

const openViewDialog = (boutique: Boutique) => {
  selectedBoutique.value = boutique
  showViewDialog.value = true
}

const openDeleteDialog = (boutique: Boutique) => {
  selectedBoutique.value = boutique
  showDeleteDialog.value = true
}

const confirmDelete = async () => {
  if (!selectedBoutique.value) return
  
  isDeleting.value = true
  try {
    await boutiquesStore.deleteBoutique(selectedBoutique.value.id)
    showDeleteDialog.value = false
    selectedBoutique.value = null
    fetchData()
  } catch (error) {
    console.error('Delete error:', error)
  } finally {
    isDeleting.value = false
  }
}

const getStatusColor = (status: string) => {
  switch (status) {
    case 'actif': return 'success'
    case 'suspendu': return 'warning'
    case 'desactive': return 'error'
    default: return 'default'
  }
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
</script>

<template>
  <div class="d-flex flex-column gap-6">
    <!-- Page Header & Breadcrumbs -->
    <Breadcrumbs 
      :items="breadcrumbs"
      :title="$t('admin_boutiques_title')"
    />

    <!-- Stats Cards Row -->
    <VRow>
      <VCol cols="12" sm="6" md="3">
        <VCard class="text-center">
          <VCardText>
            <VIcon icon="tabler-building-store" size="48" class="mb-4 text-primary" />
            <div class="text-h4 font-weight-bold">{{ totalBoutiques }}</div>
            <div class="text-body-2 text-medium-emphasis">{{ $t('admin_boutiques_title') }}</div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" sm="6" md="3">
        <VCard class="text-center">
          <VCardText>
            <VIcon icon="tabler-check-circle" size="48" class="mb-4 text-success" />
            <div class="text-h4 font-weight-bold text-success">{{ activeCount }}</div>
            <div class="text-body-2 text-medium-emphasis">{{ $t('admin_boutiques_filter_status_active') }}</div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" sm="6" md="3">
        <VCard class="text-center">
          <VCardText>
            <VIcon icon="tabler-pause-circle" size="48" class="mb-4 text-warning" />
            <div class="text-h4 font-weight-bold text-warning">{{ suspendedCount }}</div>
            <div class="text-body-2 text-medium-emphasis">{{ $t('admin_boutiques_filter_status_inactive') }}</div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" sm="6" md="3">
        <VCard class="text-center">
          <VCardText>
            <VIcon icon="tabler-x-circle" size="48" class="mb-4 text-error" />
            <div class="text-h4 font-weight-bold text-error">{{ deactivatedCount }}</div>
            <div class="text-body-2 text-medium-emphasis">{{ $t('admin_boutiques_filter_status_pending') }}</div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Main Data Table Card -->
    <VCard>
      <VCardTitle class="d-flex align-center justify-space-between flex-wrap gap-4">
        <span>{{ $t('admin_boutiques_list_title') }}</span>
        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          @click="router.push({ name: 'admin-boutiques-create' })"
        >
          {{ $t('admin_boutiques_create') }}
        </VBtn>
      </VCardTitle>

      <!-- Filters Row -->
      <VCardText>
        <VRow class="mb-4">
          <VCol cols="12" md="4">
            <VTextField
              v-model="searchQuery"
              :label="$t('action_search')"
              :placeholder="$t('admin_boutiques_search_placeholder')"
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
              :label="$t('admin_boutiques_sort_by')"
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
              {{ $t('action_reset') }}
            </VBtn>
          </VCol>
        </VRow>

        <!-- Data Table -->
        <VDataTableServer
          v-model:items-per-page="perPage"
          v-model:page="currentPage"
          :headers="headers"
          :items="boutiques"
          :items-length="totalBoutiques"
          :loading="isLoading"
          item-value="id"
          class="elevation-1"
        >
          <!-- Status Column -->
          <template #[`item.statut`]="{ item }">
            <VChip
              :color="getStatusColor(item.statut)"
              size="small"
              variant="elevated"
            >
              {{ $t(`admin_boutiques_filter_status_${item.statut === 'actif' ? 'active' : item.statut === 'suspendu' ? 'inactive' : 'pending'}`) }}
            </VChip>
          </template>

          <!-- Owner Column -->
          <template #[`item.proprietaire`]="{ item }">
            <div class="d-flex align-center gap-3">
              <VAvatar
                :color="getAvatarColor(item.proprietaire?.nom_complet || 'Unknown')"
                size="32"
              >
                <span class="text-sm font-weight-medium">
                  {{ getInitials(item.proprietaire?.nom_complet || 'UK') }}
                </span>
              </VAvatar>
              <div>
                <div class="text-body-2 font-weight-medium">
                  {{ item.proprietaire?.nom_complet || 'N/A' }}
                </div>
                <div class="text-caption text-medium-emphasis">
                  {{ item.proprietaire?.email || '' }}
                </div>
              </div>
            </div>
          </template>

          <!-- Commission Column -->
          <template #[`item.commission_par_defaut`]="{ item }">
            <span class="font-weight-medium">{{ item.commission_par_defaut }}%</span>
          </template>

          <!-- Actions Column -->
          <template #[`item.actions`]="{ item }">
            <div class="d-flex gap-1">
              <VBtn
                icon="tabler-eye"
                size="small"
                variant="text"
                color="info"
                @click="openViewDialog(item)"
              />
              <VBtn
                icon="tabler-edit"
                size="small"
                variant="text"
                color="primary"
                @click="openEditDialog(item)"
              />
              <VBtn
                icon="tabler-trash"
                size="small"
                variant="text"
                color="error"
                @click="openDeleteDialog(item)"
              />
            </div>
          </template>

          <!-- Loading State -->
          <template #loading>
            <VSkeletonLoader type="table-row@10" />
          </template>

          <!-- No Data State -->
          <template #no-data>
            <div class="text-center py-8">
              <VIcon icon="tabler-building-store" size="64" class="mb-4" color="disabled" />
              <h6 class="text-h6 mb-2">{{ $t('admin_boutiques_no_results') }}</h6>
              <p class="text-body-2">{{ $t('try_adjusting_search') }}</p>
            </div>
          </template>
        </VDataTableServer>
      </VCardText>
    </VCard>

    <!-- CRUD Dialog -->
    <BoutiqueCrudDialog
      v-model="showCrudDialog"
      :mode="crudMode"
      :boutique="selectedBoutique"
      @saved="fetchData"
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
