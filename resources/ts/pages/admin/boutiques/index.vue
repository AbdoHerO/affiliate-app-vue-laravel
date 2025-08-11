<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'
import { useBoutiquesStore, type Boutique } from '@/stores/admin/boutiques'
import { useAuthStore } from '@/stores/auth'
import Breadcrumbs from '@/components/common/Breadcrumbs.vue'
import BoutiqueCrudDialog from '@/components/admin/boutiques/BoutiqueCrudDialog.vue'
import BoutiqueViewDialog from '@/components/admin/boutiques/BoutiqueViewDialog.vue'
import ConfirmActionDialog from '@/components/common/ConfirmActionDialog.vue'
import { useQuickConfirm } from '@/composables/useConfirmAction'
import { useBoutiqueSoftDelete } from '@/composables/useSoftDelete'
import SoftDeleteFilter from '@/components/common/SoftDeleteFilter.vue'
import SoftDeleteActions from '@/components/common/SoftDeleteActions.vue'

definePage({
  meta: {
    requiresAuth: true,
    requiresRole: 'admin',
  },
})

// Composables
const router = useRouter()
const { t } = useI18n()
const authStore = useAuthStore()
const boutiquesStore = useBoutiquesStore()
const {
  confirmDelete,
  isDialogVisible: isConfirmDialogVisible,
  isLoading: isConfirmLoading,
  dialogTitle,
  dialogText,
  dialogIcon,
  dialogColor,
  confirmButtonText,
  cancelButtonText,
  handleConfirm,
  handleCancel
} = useQuickConfirm()

// Soft delete functionality
const {
  filter: softDeleteFilter,
  getQueryParams: getSoftDeleteQueryParams,
  isSoftDeleted,
  getStatusColor: getSoftDeleteStatusColor,
  getStatusText: getSoftDeleteStatusText
} = useBoutiqueSoftDelete(
  () => fetchData(), // onSuccess
  (error) => console.error('Soft delete error:', error) // onError
)

// Keep functions on the store instance
const { fetchBoutiques, setFilters, destroy } = boutiquesStore

// Turn reactive state into refs
const {
  items: boutiques,
  isLoading,
  pagination,
  totalItems
} = storeToRefs(boutiquesStore)

// Reactive state
const searchQuery = ref('')
const statusFilter = ref('')
const sortBy = ref('created_at')
const sortDesc = ref(true)
const showCrudDialog = ref(false)
const showViewDialog = ref(false)
const selectedBoutique = ref<Boutique | null>(null)
const crudMode = ref<'create' | 'edit'>('create')
const isDeleting = ref(false)

// Computed properties with setters for v-model
const currentPage = computed({
  get: () => pagination.value.current_page,
  set: (p: number) => {
    setFilters({ page: p })
    fetchData()
  },
})

const perPage = computed({
  get: () => pagination.value.per_page,
  set: (n: number) => {
    setFilters({ per_page: n, page: 1 })
    fetchData()
  },
})

const totalBoutiques = computed(() => totalItems.value)
const activeCount = computed(() => boutiques.value.filter((b: Boutique) => b.statut === 'actif').length)
const suspendedCount = computed(() => boutiques.value.filter((b: Boutique) => b.statut === 'suspendu').length)
const deactivatedCount = computed(() => boutiques.value.filter((b: Boutique) => b.statut === 'desactive').length)

const breadcrumbs = computed(() => [
  { title: t('title_admin_dashboard'), to: '/admin' },
  { title: t('admin_boutiques_title'), active: true }
])

const headers = computed(() => [
  { title: t('admin_boutiques_name'), key: 'nom', sortable: true },
  { title: t('form_name'), key: 'slug', sortable: true },
  { title: t('admin_boutiques_status'), key: 'statut', sortable: true },
  { title: t('record_status'), key: 'record_status', sortable: false },
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



// Methods
const fetchData = () => {
  fetchBoutiques()
}

const applyFilters = () => {
  setFilters({
    q: searchQuery.value,
    statut: statusFilter.value,
    sort: sortBy.value,
    dir: sortDesc.value ? 'desc' : 'asc',
    page: 1,
    ...getSoftDeleteQueryParams() // Add soft delete filter
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

const deleteBoutique = async (boutique: Boutique) => {
  // Show confirm dialog before deleting
  const confirmed = await confirmDelete(t('boutique'), boutique.nom)
  if (!confirmed) return

  isDeleting.value = true
  try {
    await destroy(boutique.id)
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
  // Wait for auth to be initialized before fetching data
  if (authStore.isInitialized) {
    fetchData()
  } else {
    // Watch for auth initialization
    const unwatch = watch(() => authStore.isInitialized, (initialized) => {
      if (initialized) {
        fetchData()
        unwatch() // Stop watching once initialized
      }
    })
  }
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
          <VCol cols="12" md="3">
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
          <VCol cols="12" md="2">
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
          <VCol cols="12" md="2">
            <SoftDeleteFilter
              v-model="softDeleteFilter"
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

          <!-- Record Status Column -->
          <template #[`item.record_status`]="{ item }">
            <VChip
              :color="getSoftDeleteStatusColor(item)"
              size="small"
              variant="elevated"
            >
              {{ getSoftDeleteStatusText(item) }}
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
            <SoftDeleteActions
              :item="item"
              entity-name="boutique"
              api-endpoint="/admin/boutiques"
              item-name-field="nom"
              @deleted="fetchData"
              @restored="fetchData"
              @permanently-deleted="fetchData"
              @edit="openEditDialog"
              @view="openViewDialog"
            />
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

    <!-- Confirm Dialog -->
    <ConfirmActionDialog
      :is-dialog-visible="isConfirmDialogVisible"
      :is-loading="isConfirmLoading"
      :dialog-title="dialogTitle"
      :dialog-text="dialogText"
      :dialog-icon="dialogIcon"
      :dialog-color="dialogColor"
      :confirm-button-text="confirmButtonText"
      :cancel-button-text="cancelButtonText"
      @confirm="handleConfirm"
      @cancel="handleCancel"
    />

  </div>
</template>
