<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'
import { useProduitsStore, type Produit } from '@/stores/admin/produits'
import { useBoutiquesStore } from '@/stores/admin/boutiques'
import { useCategoriesStore } from '@/stores/admin/categories'
import { useAuthStore } from '@/stores/auth'
import Breadcrumbs from '@/components/common/Breadcrumbs.vue'
import ConfirmModal from '@/components/common/ConfirmModal.vue'

// ⚠️ Ne PAS changer la meta layout sous peine de casser la sidebar. Voir ticket #123.
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
const produitsStore = useProduitsStore()
const boutiquesStore = useBoutiquesStore()
const categoriesStore = useCategoriesStore()

// Keep functions on the store instance
const { fetchProduits, deleteProduit } = produitsStore

// Turn reactive state into refs
const {
  produits,
  loading,
  error,
  pagination,
  filters
} = storeToRefs(produitsStore)

// Get boutiques and categories for filters
const { items: boutiques } = storeToRefs(boutiquesStore)
const { categories } = storeToRefs(categoriesStore)

// Reactive state
const searchQuery = ref('')
const boutiqueFilter = ref<string | null>(null)
const categorieFilter = ref<string | null>(null)
const statusFilter = ref('')
const sortBy = ref('created_at')
const sortDesc = ref(true)
const showDeleteDialog = ref(false)
const selectedProduit = ref<Produit | null>(null)
const isDeleting = ref(false)

// Computed properties with setters for v-model
const currentPage = computed({
  get: () => pagination.value.current_page,
  set: (value: number) => {
    pagination.value.current_page = value
    loadProduits()
  }
})

const itemsPerPage = computed({
  get: () => pagination.value.per_page,
  set: (value: number) => {
    pagination.value.per_page = value
    pagination.value.current_page = 1
    loadProduits()
  }
})

// Statistics
const totalProduits = computed(() => pagination.value.total)
const activeCount = computed(() => produits.value.filter((p: Produit) => p.actif).length)
const inactiveCount = computed(() => produits.value.filter((p: Produit) => !p.actif).length)

const breadcrumbs = computed(() => [
  { title: t('title_admin_dashboard'), to: '/admin' },
  { title: t('admin_produits_title'), active: true }
])

const headers = computed(() => [
  { title: t('admin_produits_titre'), key: 'titre', sortable: true },
  { title: t('admin_produits_boutique'), key: 'boutique.nom', sortable: true },
  { title: t('admin_produits_categorie'), key: 'categorie.nom', sortable: true },
  { title: t('admin_produits_prix_vente'), key: 'prix_vente', sortable: true, align: 'end' as const },
  { title: t('admin_produits_prix_affilie'), key: 'prix_affilie', sortable: true, align: 'end' as const },
  { title: t('common.status'), key: 'actif', sortable: true },
  { title: t('common.actions'), key: 'actions', sortable: false, width: 120 }
])

const statusOptions = computed(() => [
  { title: t('common.all'), value: '' },
  { title: t('common.active'), value: 'active' },
  { title: t('common.inactive'), value: 'inactive' }
])

// Debounced search
let searchTimeout: NodeJS.Timeout | null = null
const debouncedSearch = (value: string) => {
  if (searchTimeout) clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    searchQuery.value = value
  }, 300)
}

// Methods
const loadProduits = async () => {
  try {
    await fetchProduits({
      page: pagination.value.current_page,
      per_page: pagination.value.per_page,
      search: searchQuery.value,
      boutique_id: boutiqueFilter.value,
      categorie_id: categorieFilter.value,
      status: statusFilter.value,
      sort_by: sortBy.value,
      sort_desc: sortDesc.value
    })
  } catch (err) {
    console.error('Error loading products:', err)
  }
}

const loadFilterOptions = async () => {
  try {
    await Promise.all([
      boutiquesStore.fetchBoutiques(),
      categoriesStore.fetchCategories()
    ])
  } catch (err) {
    console.error('Error loading filter options:', err)
  }
}

const handleCreate = () => {
  router.push({ name: 'admin-produits-create' })
}

const handleEdit = (produit: Produit) => {
  router.push({ name: 'admin-produits-edit', params: { id: produit.id } })
}

const handleView = (produit: Produit) => {
  router.push({ name: 'admin-produits-show', params: { id: produit.id } })
}

const handleDelete = (produit: Produit) => {
  selectedProduit.value = produit
  showDeleteDialog.value = true
}

const confirmDelete = async () => {
  if (!selectedProduit.value) return

  isDeleting.value = true
  try {
    await deleteProduit(selectedProduit.value.id)
    showDeleteDialog.value = false
    selectedProduit.value = null
    await loadProduits() // Reload the list
  } catch (err) {
    console.error('Error deleting product:', err)
  } finally {
    isDeleting.value = false
  }
}

const formatPrice = (price: number | null): string => {
  if (!price) return '-'
  return new Intl.NumberFormat('fr-MA', {
    style: 'currency',
    currency: 'MAD',
    minimumFractionDigits: 2
  }).format(price)
}

const getStatusColor = (actif: boolean): string => {
  return actif ? 'success' : 'error'
}

const getStatusText = (actif: boolean): string => {
  return actif ? t('common.active') : t('common.inactive')
}

// Watchers
watch([searchQuery, boutiqueFilter, categorieFilter, statusFilter], () => {
  pagination.value.current_page = 1
  loadProduits()
})

watch([sortBy, sortDesc], () => {
  loadProduits()
})

// Lifecycle
onMounted(async () => {
  await loadFilterOptions()
  await loadProduits()
})
</script>

<template>
  <div>
    <!-- Breadcrumbs -->
    <Breadcrumbs :items="breadcrumbs" />

    <!-- Page Header -->
    <VRow class="mb-6">
      <VCol cols="12">
        <div class="d-flex align-center justify-space-between">
          <div>
            <h1 class="text-h4 font-weight-bold mb-2">
              {{ $t('admin_produits_title') }}
            </h1>
            <p class="text-body-1 text-medium-emphasis">
              {{ $t('admin_produits_subtitle') }}
            </p>
          </div>
          <VBtn
            color="primary"
            prepend-icon="tabler-plus"
            @click="handleCreate"
          >
            {{ $t('admin_produits_create') }}
          </VBtn>
        </div>
      </VCol>
    </VRow>

    <!-- Statistics Cards -->
    <VRow class="mb-6">
      <VCol cols="12" md="4">
        <VCard>
          <VCardText>
            <div class="d-flex align-center">
              <VAvatar color="primary" variant="tonal" class="me-4">
                <VIcon icon="tabler-package" />
              </VAvatar>
              <div>
                <h3 class="text-h5 font-weight-bold">{{ totalProduits }}</h3>
                <p class="text-body-2 text-medium-emphasis mb-0">
                  {{ $t('admin_produits_total') }}
                </p>
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" md="4">
        <VCard>
          <VCardText>
            <div class="d-flex align-center">
              <VAvatar color="success" variant="tonal" class="me-4">
                <VIcon icon="tabler-check" />
              </VAvatar>
              <div>
                <h3 class="text-h5 font-weight-bold">{{ activeCount }}</h3>
                <p class="text-body-2 text-medium-emphasis mb-0">
                  {{ $t('admin_produits_active') }}
                </p>
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" md="4">
        <VCard>
          <VCardText>
            <div class="d-flex align-center">
              <VAvatar color="error" variant="tonal" class="me-4">
                <VIcon icon="tabler-x" />
              </VAvatar>
              <div>
                <h3 class="text-h5 font-weight-bold">{{ inactiveCount }}</h3>
                <p class="text-body-2 text-medium-emphasis mb-0">
                  {{ $t('admin_produits_inactive') }}
                </p>
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
              :model-value="searchQuery"
              :label="$t('common.search')"
              :placeholder="$t('admin_produits_search_placeholder')"
              prepend-inner-icon="tabler-search"
              clearable
              @update:model-value="debouncedSearch"
            />
          </VCol>
          <VCol cols="12" md="2">
            <VSelect
              v-model="boutiqueFilter"
              :items="boutiques"
              item-title="nom"
              item-value="id"
              :label="$t('admin_produits_boutique')"
              clearable
            />
          </VCol>
          <VCol cols="12" md="2">
            <VSelect
              v-model="categorieFilter"
              :items="categories"
              item-title="nom"
              item-value="id"
              :label="$t('admin_produits_categorie')"
              clearable
            />
          </VCol>
          <VCol cols="12" md="2">
            <VSelect
              v-model="statusFilter"
              :items="statusOptions"
              item-title="title"
              item-value="value"
              :label="$t('common.status')"
              clearable
            />
          </VCol>
          <VCol cols="12" md="2">
            <VSelect
              v-model="itemsPerPage"
              :items="[10, 25, 50, 100]"
              :label="$t('common.items_per_page')"
            />
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Data Table -->
    <VCard>
      <VDataTable
        :headers="headers"
        :items="produits"
        :loading="loading"
        :no-data-text="$t('common_no_data')"
        :items-per-page="itemsPerPage"
        :page="currentPage"
        hide-default-footer
      >
        <template #item.titre="{ item }">
          <div class="d-flex align-center">
            <VImg
              v-if="item.images && item.images[0]"
              :src="item.images[0].url"
              width="40"
              height="40"
              class="rounded me-3"
              cover
            />
            <VAvatar
              v-else
              color="grey-lighten-2"
              size="40"
              class="me-3"
            >
              <VIcon icon="tabler-package" />
            </VAvatar>
            <div>
              <div class="font-weight-medium">{{ item.titre }}</div>
              <div class="text-caption text-medium-emphasis">{{ item.slug }}</div>
            </div>
          </div>
        </template>

        <template #item.boutique.nom="{ item }">
          <VChip
            v-if="item.boutique"
            size="small"
            color="primary"
            variant="tonal"
          >
            {{ item.boutique.nom }}
          </VChip>
          <span v-else class="text-medium-emphasis">-</span>
        </template>

        <template #item.categorie.nom="{ item }">
          <VChip
            v-if="item.categorie"
            size="small"
            color="secondary"
            variant="tonal"
          >
            {{ item.categorie.nom }}
          </VChip>
          <span v-else class="text-medium-emphasis">-</span>
        </template>

        <template #item.prix_vente="{ item }">
          <span class="font-weight-medium">{{ formatPrice(item.prix_vente) }}</span>
        </template>

        <template #item.prix_affilie="{ item }">
          <span class="font-weight-medium">{{ formatPrice(item.prix_affilie) }}</span>
        </template>

        <template #item.actif="{ item }">
          <VChip
            :color="getStatusColor(item.actif)"
            size="small"
          >
            {{ getStatusText(item.actif) }}
          </VChip>
        </template>

        <template #item.actions="{ item }">
          <div class="d-flex gap-1">
            <VBtn
              icon="tabler-eye"
              size="small"
              variant="text"
              @click="handleView(item)"
            />
            <VBtn
              icon="tabler-edit"
              size="small"
              variant="text"
              @click="handleEdit(item)"
            />
            <VBtn
              icon="tabler-trash"
              size="small"
              variant="text"
              color="error"
              @click="handleDelete(item)"
            />
          </div>
        </template>
      </VDataTable>

      <!-- Pagination -->
      <VDivider />
      <VCardText>
        <VRow align="center" justify="space-between">
          <VCol cols="auto">
            <span class="text-body-2 text-medium-emphasis">
              {{ $t('common.showing') }} {{ pagination.from }} {{ $t('common.to') }} {{ pagination.to }}
              {{ $t('common.of') }} {{ pagination.total }} {{ $t('common.results') }}
            </span>
          </VCol>
          <VCol cols="auto">
            <VPagination
              v-model="currentPage"
              :length="pagination.last_page"
              :total-visible="5"
            />
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Delete Confirmation Dialog -->
    <ConfirmModal
      v-model="showDeleteDialog"
      :title="$t('admin_produits_delete_title')"
      :message="$t('admin_produits_delete_message')"
      :loading="isDeleting"
      @confirm="confirmDelete"
    />
  </div>
</template>
