<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useProduitsStore, type Produit } from '@/stores/admin/produits'
import Breadcrumbs from '@/components/common/Breadcrumbs.vue'
import ConfirmModal from '@/components/common/ConfirmModal.vue'

definePage({
  meta: {
    requiresAuth: true,
    requiresRole: 'admin',
    layout: 'default',
  },
})

// Composables
const { t } = useI18n()
const router = useRouter()
const produitsStore = useProduitsStore()

// Reactive state
const searchQuery = ref('')
const boutiqueFilter = ref('')
const categorieFilter = ref('')
const statusFilter = ref('')
const sortBy = ref('created_at')
const sortDesc = ref(true)
const showDeleteDialog = ref(false)
const selectedProduit = ref<Produit | null>(null)
const isDeleting = ref(false)

// Available boutiques and categories for filters
const boutiques = ref([])
const categories = ref([])

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
  { text: t('dashboard'), to: { name: 'admin-dashboard' } },
  { text: t('admin_produits_title'), active: true }
])

const produits = computed(() => produitsStore.produits)
const loading = computed(() => produitsStore.loading)
const totalProduits = computed(() => produitsStore.pagination.total)
const activeProduits = computed(() => produits.value.filter(p => p.actif).length)
const inactiveProduits = computed(() => produits.value.filter(p => !p.actif).length)

// Table headers
const headers = computed(() => [
  { title: t('admin_produits_title'), key: 'titre', sortable: true },
  { title: t('admin_produits_slug'), key: 'slug' },
  { title: t('admin_produits_boutique'), key: 'boutique', sortable: false },
  { title: t('admin_produits_category'), key: 'categorie', sortable: false },
  { title: t('admin_produits_price'), key: 'prix_vente', sortable: true, align: 'center' },
  { title: t('admin_produits_status'), key: 'actif', align: 'center', width: 120 },
  { title: t('common_actions'), key: 'actions', align: 'center', width: 150, sortable: false }
])

// Status options for filter
const statusOptions = computed(() => [
  { title: t('status_active'), value: 'true' },
  { title: t('status_inactive'), value: 'false' }
])

// Sort options
const sortOptions = computed(() => [
  { title: t('admin_produits_title'), value: 'titre' },
  { title: t('admin_produits_price'), value: 'prix_vente' },
  { title: t('common_created_at'), value: 'created_at' }
])

const deleteMessage = computed(() => 
  selectedProduit.value 
    ? t('admin_produits_delete_confirm', { name: selectedProduit.value.titre })
    : ''
)

// Methods
const fetchProduits = async () => {
  await produitsStore.fetchProduits({
    q: searchQuery.value || undefined,
    boutique_id: boutiqueFilter.value || undefined,
    categorie_id: categorieFilter.value || undefined,
    actif: statusFilter.value || undefined,
    sort: sortBy.value,
    direction: sortDesc.value ? 'desc' : 'asc',
    per_page: 15
  })
}

const fetchFilterOptions = async () => {
  try {
    // Fetch boutiques for filter
    const boutiquesResponse = await fetch('/api/admin/boutiques?per_page=100')
    if (boutiquesResponse.ok) {
      const boutiquesData = await boutiquesResponse.json()
      boutiques.value = boutiquesData.data || []
    }

    // Fetch categories for filter
    const categoriesResponse = await fetch('/api/admin/categories?per_page=100')
    if (categoriesResponse.ok) {
      const categoriesData = await categoriesResponse.json()
      categories.value = categoriesData.data || []
    }
  } catch (error) {
    console.error('Failed to fetch filter options:', error)
  }
}

const applyFilters = () => {
  fetchProduits()
}

const resetFilters = () => {
  searchQuery.value = ''
  boutiqueFilter.value = ''
  categorieFilter.value = ''
  statusFilter.value = ''
  sortBy.value = 'created_at'
  sortDesc.value = true
  fetchProduits()
}

const createProduit = () => {
  router.push({ name: 'admin-produits-create' })
}

const editProduit = (produit: Produit) => {
  router.push({ name: 'admin-produits-edit', params: { id: produit.id } })
}

const viewProduit = (produit: Produit) => {
  router.push({ name: 'admin-produits-show', params: { id: produit.id } })
}

const deleteProduit = (produit: Produit) => {
  selectedProduit.value = produit
  showDeleteDialog.value = true
}

const confirmDelete = async () => {
  if (!selectedProduit.value) return

  isDeleting.value = true
  try {
    await produitsStore.deleteProduit(selectedProduit.value.id)
    showDeleteDialog.value = false
    selectedProduit.value = null
  } catch (error) {
    console.error('Delete failed:', error)
  } finally {
    isDeleting.value = false
  }
}

const formatPrice = (price: number) => {
  return new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'MAD'
  }).format(price)
}

// Watchers
watch([searchQuery, boutiqueFilter, categorieFilter, statusFilter], () => {
  applyFilters()
})

watch([sortBy, sortDesc], () => {
  applyFilters()
})

// Lifecycle
onMounted(() => {
  fetchFilterOptions()
  fetchProduits()
})
</script>

<template>
  <div class="d-flex flex-column gap-6">
    <!-- Page Header & Breadcrumbs -->
    <Breadcrumbs 
      :items="breadcrumbs"
      :title="$t('admin_produits_title')"
    />

    <!-- Stats Cards Row -->
    <VRow>
      <VCol cols="12" sm="6" md="3">
        <VCard class="text-center">
          <VCardText>
            <VIcon icon="tabler-package" size="48" class="mb-4 text-primary" />
            <div class="text-h4 font-weight-bold">{{ totalProduits }}</div>
            <div class="text-body-2 text-medium-emphasis">{{ $t('admin_produits_total') }}</div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" sm="6" md="3">
        <VCard class="text-center">
          <VCardText>
            <VIcon icon="tabler-check" size="48" class="mb-4 text-success" />
            <div class="text-h4 font-weight-bold text-success">{{ activeProduits }}</div>
            <div class="text-body-2 text-medium-emphasis">{{ $t('admin_produits_active') }}</div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" sm="6" md="3">
        <VCard class="text-center">
          <VCardText>
            <VIcon icon="tabler-x" size="48" class="mb-4 text-error" />
            <div class="text-h4 font-weight-bold text-error">{{ inactiveProduits }}</div>
            <div class="text-body-2 text-medium-emphasis">{{ $t('admin_produits_inactive') }}</div>
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
                @click="createProduit"
              >
                {{ $t('admin_produits_create') }}
              </VBtn>
            </div>
            <div class="text-body-2 text-medium-emphasis">{{ $t('admin_produits_add_new') }}</div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Products Table -->
    <VCard>
      <VCardTitle class="d-flex align-center justify-space-between">
        <span>{{ $t('admin_produits_list') }}</span>
        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          @click="createProduit"
        >
          {{ $t('admin_produits_create') }}
        </VBtn>
      </VCardTitle>
      
      <VCardText>
        <!-- Filters Row -->
        <VRow class="mb-6">
          <VCol cols="12" md="3">
            <VTextField
              :label="$t('common_search')"
              prepend-inner-icon="tabler-search"
              clearable
              variant="outlined"
              density="compact"
              @input="debouncedSearch"
            />
          </VCol>
          <VCol cols="12" md="2">
            <VSelect
              v-model="boutiqueFilter"
              :items="boutiques"
              item-title="nom"
              item-value="id"
              :label="$t('admin_produits_boutique')"
              variant="outlined"
              density="compact"
              clearable
              @update:model-value="applyFilters"
            />
          </VCol>
          <VCol cols="12" md="2">
            <VSelect
              v-model="categorieFilter"
              :items="categories"
              item-title="nom"
              item-value="id"
              :label="$t('admin_produits_category')"
              variant="outlined"
              density="compact"
              clearable
              @update:model-value="applyFilters"
            />
          </VCol>
          <VCol cols="12" md="2">
            <VSelect
              v-model="statusFilter"
              :items="statusOptions"
              :label="$t('admin_produits_status')"
              variant="outlined"
              density="compact"
              clearable
              @update:model-value="applyFilters"
            />
          </VCol>
          <VCol cols="12" md="2">
            <VSelect
              v-model="sortBy"
              :items="sortOptions"
              :label="$t('common_sort_by')"
              variant="outlined"
              density="compact"
              @update:model-value="applyFilters"
            />
          </VCol>
          <VCol cols="12" md="1">
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
          :items="produits"
          :loading="loading"
          :no-data-text="$t('common_no_data')"
          :loading-text="$t('common_loading')"
          :items-per-page="15"
          :sort-by="[{ key: sortBy, order: sortDesc ? 'desc' : 'asc' }]"
          class="elevation-1"
          item-value="id"
        >
          <!-- Title Column -->
          <template #[`item.titre`]="{ item }">
            <div class="d-flex align-center gap-3">
              <VAvatar
                v-if="item.images && item.images.length > 0"
                :image="item.images[0].url"
                size="32"
                rounded
              />
              <VAvatar
                v-else
                size="32"
                color="secondary"
                variant="tonal"
              >
                <VIcon icon="tabler-package" />
              </VAvatar>
              <div>
                <div class="font-weight-medium">{{ item.titre }}</div>
                <div class="text-caption text-medium-emphasis">{{ item.slug }}</div>
              </div>
            </div>
          </template>

          <!-- Boutique Column -->
          <template #[`item.boutique`]="{ item }">
            <VChip
              v-if="item.boutique"
              size="small"
              color="info"
              variant="tonal"
            >
              {{ item.boutique.nom }}
            </VChip>
            <span v-else class="text-medium-emphasis">-</span>
          </template>

          <!-- Category Column -->
          <template #[`item.categorie`]="{ item }">
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

          <!-- Price Column -->
          <template #[`item.prix_vente`]="{ item }">
            <div class="text-end font-weight-medium">
              {{ formatPrice(item.prix_vente) }}
            </div>
          </template>

          <!-- Status Column -->
          <template #[`item.actif`]="{ item }">
            <VChip
              :color="item.actif ? 'success' : 'error'"
              size="small"
              variant="tonal"
            >
              {{ item.actif ? $t('status_active') : $t('status_inactive') }}
            </VChip>
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
                    @click="viewProduit(item)"
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
                    @click="editProduit(item)"
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
                    @click="deleteProduit(item)"
                  />
                </template>
              </VTooltip>
            </div>
          </template>
        </VDataTable>
      </VCardText>
    </VCard>

    <!-- Delete Confirmation -->
    <ConfirmModal
      v-model="showDeleteDialog"
      :title="$t('admin_produits_delete_title')"
      :message="deleteMessage"
      :loading="isDeleting"
      @confirm="confirmDelete"
    />
  </div>
</template>
