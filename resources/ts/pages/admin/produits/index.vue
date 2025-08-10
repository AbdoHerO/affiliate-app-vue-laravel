<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'
import { useProduitsStore, type Produit } from '@/stores/admin/produits'
import { useBoutiquesStore } from '@/stores/admin/boutiques'
import { useCategoriesStore } from '@/stores/admin/categories'

import { useDebounceFn } from '@vueuse/core'
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

// Local state
const showDeleteDialog = ref(false)
const selectedProduit = ref<Produit | null>(null)
const isDeleting = ref(false)

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
  { title: t('common.active'), value: '1' },
  { title: t('common.inactive'), value: '0' }
])

const perPageOptions = [10, 15, 25, 50, 100]



// Debounced fetch function
const debouncedFetch = useDebounceFn(async () => {
  try {
    await fetchProduits({ ...filters.value })
  } catch (err: any) {
    if (err.name !== 'AbortError') {
      console.error('Error loading products:', err)
    }
  }
}, 300)

// Single watcher for all filter changes
watch(filters, () => {
  debouncedFetch()
}, { deep: true })

// Methods
const handleTableUpdate = (options: any) => {
  filters.value.page = options.page
  filters.value.perPage = options.itemsPerPage
  if (options.sortBy && options.sortBy.length > 0) {
    filters.value.sort = options.sortBy[0].key
    filters.value.dir = options.sortBy[0].order === 'desc' ? 'desc' : 'asc'
  }
}

// Filter change handlers
const handleSearchChange = (value: string) => {
  filters.value.q = value
  filters.value.page = 1
}

const handleBoutiqueChange = (value: string) => {
  filters.value.boutique_id = value
  filters.value.page = 1
}

const handleCategorieChange = (value: string) => {
  filters.value.categorie_id = value
  filters.value.page = 1
}

const handleStatusChange = (value: string) => {
  filters.value.actif = value
  filters.value.page = 1
}

const handlePerPageChange = (value: number) => {
  filters.value.perPage = value
  filters.value.page = 1
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
  router.push('/admin/produits/create')
}

const handleEdit = (produit: Produit) => {
  console.log('[List] Navigating to edit page for product:', produit.id)
  router.push(`/admin/produits/${produit.id}/edit`)
}

const handleView = (produit: Produit) => {
  router.push(`/admin/produits/${produit.id}`)
}

const handleShare = async (produit: Produit) => {
  try {
    const publicUrl = `${window.location.origin}/p/${produit.slug}`

    // Copy to clipboard
    await navigator.clipboard.writeText(publicUrl)

    // Show success message (you could use a toast notification here)
    console.log('Share link copied to clipboard:', publicUrl)

    // Optionally open in new tab
    window.open(publicUrl, '_blank')
  } catch (error) {
    console.error('Failed to copy share link:', error)
    // Fallback: just open the link
    const publicUrl = `${window.location.origin}/p/${produit.slug}`
    window.open(publicUrl, '_blank')
  }
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
    await fetchProduits({ ...filters.value }) // Reload the list
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

// Lifecycle
onMounted(async () => {
  await loadFilterOptions()
  await fetchProduits({ ...filters.value })
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
              v-model="filters.q"
              :label="$t('common.search')"
              :placeholder="$t('admin_produits_search_placeholder')"
              prepend-inner-icon="tabler-search"
              clearable
              @update:model-value="handleSearchChange"
            />
          </VCol>
          <VCol cols="12" md="2">
            <VSelect
              v-model="filters.boutique_id"
              :items="boutiques"
              item-title="nom"
              item-value="id"
              :label="$t('admin_produits_boutique')"
              clearable
              @update:model-value="handleBoutiqueChange"
            />
          </VCol>
          <VCol cols="12" md="2">
            <VSelect
              v-model="filters.categorie_id"
              :items="categories"
              item-title="nom"
              item-value="id"
              :label="$t('admin_produits_categorie')"
              clearable
              @update:model-value="handleCategorieChange"
            />
          </VCol>
          <VCol cols="12" md="2">
            <VSelect
              v-model="filters.actif"
              :items="statusOptions"
              item-title="title"
              item-value="value"
              :label="$t('common.status')"
              clearable
              @update:model-value="handleStatusChange"
            />
          </VCol>
          <VCol cols="12" md="2">
            <VSelect
              v-model="filters.perPage"
              :items="perPageOptions"
              :label="$t('common.items_per_page')"
              @update:model-value="handlePerPageChange"
            />
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Data Table -->
    <VCard>
      <VDataTableServer
        :headers="headers"
        :items="produits"
        :loading="loading"
        :no-data-text="$t('common_no_data')"
        :items-per-page="filters.perPage"
        :page="filters.page"
        :items-length="pagination.total"
        @update:options="handleTableUpdate"
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
              icon="tabler-share"
              size="small"
              variant="text"
              color="primary"
              @click="handleShare(item)"
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
      </VDataTableServer>

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
              v-model="filters.page"
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
