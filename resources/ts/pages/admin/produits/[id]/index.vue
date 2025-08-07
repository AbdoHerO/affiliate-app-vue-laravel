<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'
import { useProduitsStore, type Produit } from '@/stores/admin/produits'
import Breadcrumbs from '@/components/common/Breadcrumbs.vue'

// ⚠️ Ne PAS changer la meta layout sous peine de casser la sidebar. Voir ticket #123.
definePage({
  meta: {
    requiresAuth: true,
    requiresRole: 'admin',
  },
})

// Composables
const router = useRouter()
const route = useRoute()
const { t } = useI18n()
const produitsStore = useProduitsStore()

// Store state
const { loading, error } = storeToRefs(produitsStore)

// Component state
const isLoading = ref(true)
const produit = ref<Produit | null>(null)
const produitId = route.params.id as string

// Computed
const breadcrumbs = computed(() => [
  { title: t('title_admin_dashboard'), to: '/admin' },
  { title: t('admin_produits_title'), to: '/admin/produits' },
  { title: produit.value?.titre || t('admin_produits_show'), active: true }
])

// Methods
const loadProduit = async () => {
  try {
    isLoading.value = true
    produit.value = await produitsStore.fetchProduit(produitId)
  } catch (err) {
    console.error('Error loading product:', err)
    router.push({ name: 'admin-produits-index' })
  } finally {
    isLoading.value = false
  }
}

const goBack = () => {
  router.push({ name: 'admin-produits-index' })
}

const handleEdit = () => {
  router.push({ name: 'admin-produits-edit', params: { id: produitId } })
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
  await loadProduit()
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
              {{ produit?.titre || $t('admin_produits_show') }}
            </h1>
            <p class="text-body-1 text-medium-emphasis">
              {{ $t('admin_produits_show_subtitle') }}
            </p>
          </div>
          <div class="d-flex gap-2">
            <VBtn
              variant="outlined"
              prepend-icon="tabler-arrow-left"
              @click="goBack"
            >
              {{ $t('common.back') }}
            </VBtn>
            <VBtn
              color="primary"
              prepend-icon="tabler-edit"
              @click="handleEdit"
            >
              {{ $t('common.edit') }}
            </VBtn>
          </div>
        </div>
      </VCol>
    </VRow>

    <!-- Loading State -->
    <VCard v-if="isLoading">
      <VCardText class="text-center py-8">
        <VProgressCircular indeterminate color="primary" />
        <p class="mt-4">{{ $t('common.loading') }}</p>
      </VCardText>
    </VCard>

    <!-- Product Details -->
    <div v-else-if="produit">
      <VRow>
        <!-- Main Information -->
        <VCol cols="12" md="8">
          <VCard class="mb-6">
            <VCardTitle>{{ $t('admin_produits_basic_info') }}</VCardTitle>
            <VDivider />
            <VCardText>
              <VRow>
                <VCol cols="12" md="6">
                  <div class="mb-4">
                    <h6 class="text-h6 mb-2">{{ $t('admin_produits_titre') }}</h6>
                    <p class="text-body-1">{{ produit.titre }}</p>
                  </div>
                  <div class="mb-4">
                    <h6 class="text-h6 mb-2">{{ $t('admin_produits_slug') }}</h6>
                    <p class="text-body-1 text-medium-emphasis">{{ produit.slug }}</p>
                  </div>
                  <div class="mb-4">
                    <h6 class="text-h6 mb-2">{{ $t('admin_produits_boutique') }}</h6>
                    <VChip
                      v-if="produit.boutique"
                      color="primary"
                      variant="tonal"
                    >
                      {{ produit.boutique.nom }}
                    </VChip>
                    <span v-else class="text-medium-emphasis">-</span>
                  </div>
                </VCol>
                <VCol cols="12" md="6">
                  <div class="mb-4">
                    <h6 class="text-h6 mb-2">{{ $t('admin_produits_categorie') }}</h6>
                    <VChip
                      v-if="produit.categorie"
                      color="secondary"
                      variant="tonal"
                    >
                      {{ produit.categorie.nom }}
                    </VChip>
                    <span v-else class="text-medium-emphasis">-</span>
                  </div>
                  <div class="mb-4">
                    <h6 class="text-h6 mb-2">{{ $t('common.status') }}</h6>
                    <VChip
                      :color="getStatusColor(produit.actif)"
                      size="small"
                    >
                      {{ getStatusText(produit.actif) }}
                    </VChip>
                  </div>
                  <div class="mb-4">
                    <h6 class="text-h6 mb-2">{{ $t('common.created_at') }}</h6>
                    <p class="text-body-1">{{ new Date(produit.created_at).toLocaleDateString() }}</p>
                  </div>
                </VCol>
              </VRow>
              
              <div v-if="produit.description" class="mt-4">
                <h6 class="text-h6 mb-2">{{ $t('admin_produits_description') }}</h6>
                <p class="text-body-1">{{ produit.description }}</p>
              </div>
            </VCardText>
          </VCard>

          <!-- Images -->
          <VCard v-if="produit.images && produit.images.length > 0" class="mb-6">
            <VCardTitle>{{ $t('admin_produits_images') }}</VCardTitle>
            <VDivider />
            <VCardText>
              <VRow>
                <VCol
                  v-for="image in produit.images"
                  :key="image.id"
                  cols="6"
                  md="4"
                  lg="3"
                >
                  <VImg
                    :src="image.url"
                    :alt="image.alt_text || produit.titre"
                    aspect-ratio="1"
                    cover
                    class="rounded"
                  />
                </VCol>
              </VRow>
            </VCardText>
          </VCard>

          <!-- Videos -->
          <VCard v-if="produit.videos && produit.videos.length > 0" class="mb-6">
            <VCardTitle>{{ $t('admin_produits_videos') }}</VCardTitle>
            <VDivider />
            <VCardText>
              <VRow>
                <VCol
                  v-for="video in produit.videos"
                  :key="video.id"
                  cols="12"
                  md="6"
                >
                  <div class="mb-4">
                    <h6 v-if="video.titre" class="text-h6 mb-2">{{ video.titre }}</h6>
                    <a :href="video.url" target="_blank" class="text-primary">
                      {{ video.url }}
                    </a>
                  </div>
                </VCol>
              </VRow>
            </VCardText>
          </VCard>

          <!-- Variants -->
          <VCard v-if="produit.variantes && produit.variantes.length > 0">
            <VCardTitle>{{ $t('admin_produits_variantes') }}</VCardTitle>
            <VDivider />
            <VCardText>
              <VDataTable
                :headers="[
                  { title: $t('admin_produits_variante_nom'), key: 'nom' },
                  { title: $t('admin_produits_variante_valeur'), key: 'valeur' },
                  { title: $t('admin_produits_prix_vente'), key: 'prix_vente_variante' },
                  { title: $t('common.status'), key: 'actif' }
                ]"
                :items="produit.variantes"
                :no-data-text="$t('common_no_data')"
              >
                <template #item.prix_vente_variante="{ item }">
                  {{ formatPrice(item.prix_vente_variante) }}
                </template>
                <template #item.actif="{ item }">
                  <VChip
                    :color="getStatusColor(item.actif)"
                    size="small"
                  >
                    {{ getStatusText(item.actif) }}
                  </VChip>
                </template>
              </VDataTable>
            </VCardText>
          </VCard>
        </VCol>

        <!-- Sidebar -->
        <VCol cols="12" md="4">
          <!-- Pricing Information -->
          <VCard class="mb-6">
            <VCardTitle>{{ $t('admin_produits_pricing') }}</VCardTitle>
            <VDivider />
            <VCardText>
              <div class="mb-4">
                <h6 class="text-h6 mb-2">{{ $t('admin_produits_prix_achat') }}</h6>
                <p class="text-h5 font-weight-bold">{{ formatPrice(produit.prix_achat) }}</p>
              </div>
              <div class="mb-4">
                <h6 class="text-h6 mb-2">{{ $t('admin_produits_prix_vente') }}</h6>
                <p class="text-h5 font-weight-bold text-primary">{{ formatPrice(produit.prix_vente) }}</p>
              </div>
              <div class="mb-4">
                <h6 class="text-h6 mb-2">{{ $t('admin_produits_prix_affilie') }}</h6>
                <p class="text-h5 font-weight-bold text-success">{{ formatPrice(produit.prix_affilie) }}</p>
              </div>
            </VCardText>
          </VCard>

          <!-- Quick Actions -->
          <VCard>
            <VCardTitle>{{ $t('common.actions') }}</VCardTitle>
            <VDivider />
            <VCardText>
              <VBtn
                color="primary"
                variant="outlined"
                block
                class="mb-3"
                @click="handleEdit"
              >
                {{ $t('common.edit') }}
              </VBtn>
              <VBtn
                color="secondary"
                variant="outlined"
                block
                @click="goBack"
              >
                {{ $t('common.back_to_list') }}
              </VBtn>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>
    </div>
  </div>
</template>
