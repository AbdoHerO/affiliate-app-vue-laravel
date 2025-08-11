<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'
import { useCategoriesStore, type Category } from '@/stores/admin/categories'
import { useAuthStore } from '@/stores/auth'
import Breadcrumbs from '@/components/common/Breadcrumbs.vue'
import CategoryCrudDialog from '@/components/admin/categories/CategoryCrudDialog.vue'
import CategoryViewDialog from '@/components/admin/categories/CategoryViewDialog.vue'
import { useQuickConfirm } from '@/composables/useConfirmAction'

definePage({
  meta: {
    requiresAuth: true,
    requiresRole: 'admin',
    layout: 'default',
  },
})

// Composables
const { t } = useI18n()
const authStore = useAuthStore()
const categoriesStore = useCategoriesStore()
const { confirmDelete } = useQuickConfirm()

// Turn reactive state into refs
const { categories, loading } = storeToRefs(categoriesStore)

// Reactive state
const searchQuery = ref('')
const statusFilter = ref('')
const sortBy = ref('ordre')
const sortDesc = ref(false)
const showCrudDialog = ref(false)
const showViewDialog = ref(false)
const selectedCategory = ref<Category | null>(null)
const crudMode = ref<'create' | 'edit'>('create')
const isDeleting = ref(false)

// Debounced search
let searchTimeout: NodeJS.Timeout | null = null
const debouncedSearch = (value: string) => {
  if (searchTimeout) clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    searchQuery.value = value
  }, 300)
}

// Computed properties
const breadcrumbs = computed(() => [
  { title: t('title_admin_dashboard'), to: '/admin' },
  { title: t('admin_categories_title'), active: true }
])

const totalCategories = computed(() => categoriesStore.pagination.total)
const activeCategories = computed(() => categories.value.filter(cat => cat.actif).length)
const inactiveCategories = computed(() => categories.value.filter(cat => !cat.actif).length)

// Table headers
const headers = computed(() => [
  { title: t('admin_categories_order'), key: 'ordre', align: 'center' as const, width: 80 },
  { title: t('admin_categories_name'), key: 'nom', sortable: true },
  { title: t('admin_categories_slug'), key: 'slug' },
  { title: t('admin_categories_status'), key: 'actif', align: 'center' as const, width: 120 },
  { title: t('common_actions'), key: 'actions', align: 'center' as const, width: 120, sortable: false }
])

// Status options for filter
const statusOptions = computed(() => [
  { title: t('status_active'), value: 'true' },
  { title: t('status_inactive'), value: 'false' }
])

// Sort options
const sortOptions = computed(() => [
  { title: t('admin_categories_order'), value: 'ordre' },
  { title: t('admin_categories_name'), value: 'nom' },
  { title: t('common_created_at'), value: 'created_at' }
])



// Methods
const fetchCategories = async () => {
  await categoriesStore.fetchCategories({
    search: searchQuery.value || undefined,
    status: statusFilter.value || undefined,
    sort_by: sortBy.value,
    sort_direction: sortDesc.value ? 'desc' : 'asc',
    per_page: 15
  })
}

const applyFilters = () => {
  fetchCategories()
}

const resetFilters = () => {
  searchQuery.value = ''
  statusFilter.value = ''
  sortBy.value = 'ordre'
  sortDesc.value = false
  fetchCategories()
}

const createCategory = () => {
  selectedCategory.value = null
  crudMode.value = 'create'
  showCrudDialog.value = true
}

const editCategory = (category: Category) => {
  selectedCategory.value = category
  crudMode.value = 'edit'
  showCrudDialog.value = true
}

const viewCategory = (category: Category) => {
  selectedCategory.value = category
  showViewDialog.value = true
}

const deleteCategoryAction = async (category: Category) => {
  // Show confirm dialog before deleting
  const confirmed = await confirmDelete(t('category'), category.nom)
  if (!confirmed) return

  isDeleting.value = true
  try {
    await categoriesStore.deleteCategory(category.id)
    fetchCategories()
  } catch (error) {
    console.error('Delete failed:', error)
  } finally {
    isDeleting.value = false
  }
}

const handleSaved = () => {
  fetchCategories()
}

const toggleStatus = async (category: Category) => {
  try {
    await categoriesStore.toggleCategoryStatus(category.id)
  } catch (error) {
    console.error('Status toggle failed:', error)
  }
}

// Watchers
watch([searchQuery, statusFilter], () => {
  applyFilters()
})

watch([sortBy, sortDesc], () => {
  applyFilters()
})

// Lifecycle
onMounted(() => {
  // Wait for auth to be initialized before fetching data
  if (authStore.isInitialized) {
    fetchCategories()
  } else {
    // Watch for auth initialization
    const unwatch = watch(() => authStore.isInitialized, (initialized) => {
      if (initialized) {
        fetchCategories()
        unwatch() // Stop watching once initialized
      }
    })
  }
})
</script>

<template>
  <div class="d-flex flex-column gap-6">
    <!-- Page Header & Breadcrumbs -->
    <Breadcrumbs :items="breadcrumbs" />

    <!-- Stats Cards Row -->
    <VRow>
      <VCol cols="12" sm="6" md="3">
        <VCard class="text-center">
          <VCardText>
            <VIcon icon="tabler-category" size="48" class="mb-4 text-primary" />
            <div class="text-h4 font-weight-bold">{{ totalCategories }}</div>
            <div class="text-body-2 text-medium-emphasis">{{ $t('admin_categories_total') }}</div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" sm="6" md="3">
        <VCard class="text-center">
          <VCardText>
            <VIcon icon="tabler-check" size="48" class="mb-4 text-success" />
            <div class="text-h4 font-weight-bold text-success">{{ activeCategories }}</div>
            <div class="text-body-2 text-medium-emphasis">{{ $t('admin_categories_active') }}</div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" sm="6" md="3">
        <VCard class="text-center">
          <VCardText>
            <VIcon icon="tabler-x" size="48" class="mb-4 text-error" />
            <div class="text-h4 font-weight-bold text-error">{{ inactiveCategories }}</div>
            <div class="text-body-2 text-medium-emphasis">{{ $t('admin_categories_inactive') }}</div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" sm="6" md="3">
        <VCard class="text-center">
          <VCardText>
            <VIcon icon="tabler-plus" size="48" class="mb-4 text-info" />
            <div class="text-h4 font-weight-bold">
              <VBtn
                color="primary"
                size="small"
                @click="createCategory"
              >
                {{ $t('admin_categories_create') }}
              </VBtn>
            </div>
            <div class="text-body-2 text-medium-emphasis">{{ $t('admin_categories_add_new') }}</div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Categories Table -->
    <VCard>
      <VCardTitle class="d-flex align-center justify-space-between">
        <span>{{ $t('admin_categories_list') }}</span>
        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          @click="createCategory"
        >
          {{ $t('admin_categories_create') }}
        </VBtn>
      </VCardTitle>
      
      <VCardText>
        <!-- Filters Row -->
        <VRow class="mb-6">
          <VCol cols="12" md="4">
            <VTextField
              :label="$t('common_search')"
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
              :label="$t('admin_categories_status')"
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
              :label="$t('common_sort_by')"
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
              {{ $t('common_reset') }}
            </VBtn>
          </VCol>
        </VRow>

        <!-- Data Table -->
        <VDataTable
          :headers="headers"
          :items="categories"
          :loading="loading"
          :no-data-text="$t('common_no_data')"
          :loading-text="$t('common_loading')"
          :items-per-page="15"
          :sort-by="[{ key: sortBy, order: sortDesc ? 'desc' : 'asc' }]"
          class="elevation-1"
          item-value="id"
        >
          <!-- Order Column -->
          <template #[`item.ordre`]="{ item }">
            <VChip
              size="small"
              color="primary"
              variant="tonal"
            >
              {{ item.ordre || 0 }}
            </VChip>
          </template>

          <!-- Name Column -->
          <template #[`item.nom`]="{ item }">
            <div class="d-flex align-center gap-3">
              <VAvatar
                v-if="item.image_url"
                :image="item.image_url"
                size="32"
                rounded
              />
              <VAvatar
                v-else
                size="32"
                color="secondary"
                variant="tonal"
              >
                <VIcon icon="tabler-category" />
              </VAvatar>
              <div>
                <div class="font-weight-medium">{{ item.nom }}</div>
                <div class="text-caption text-medium-emphasis">{{ item.slug }}</div>
              </div>
            </div>
          </template>

          <!-- Status Column -->
          <template #[`item.actif`]="{ item }">
            <VTooltip>
              <template #activator="{ props }">
                <VChip
                  v-bind="props"
                  :color="item.actif ? 'success' : 'error'"
                  size="small"
                  variant="tonal"
                  @click="toggleStatus(item)"
                  class="cursor-pointer"
                >
                  {{ item.actif ? $t('status_active') : $t('status_inactive') }}
                </VChip>
              </template>
              <span>{{ $t('common_click_to_toggle') }}</span>
            </VTooltip>
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
                    @click="viewCategory(item)"
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
                    @click="editCategory(item)"
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
                    @click="deleteCategoryAction(item)"
                  />
                </template>
              </VTooltip>
            </div>
          </template>
        </VDataTable>
      </VCardText>
    </VCard>

    <!-- Create/Edit Dialog -->
    <CategoryCrudDialog
      v-model="showCrudDialog"
      :category="selectedCategory"
      :mode="crudMode"
      @saved="handleSaved"
    />

    <!-- View Dialog -->
    <CategoryViewDialog
      v-model="showViewDialog"
      :category="selectedCategory"
    />


  </div>
</template>
