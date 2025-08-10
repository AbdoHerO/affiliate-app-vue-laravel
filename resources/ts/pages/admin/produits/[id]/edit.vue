<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'
import { useProduitsStore } from '@/stores/admin/produits'
import Breadcrumbs from '@/components/common/Breadcrumbs.vue'
import ProductForm from '@/components/admin/products/ProductForm.vue'

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
const { currentProduit } = storeToRefs(produitsStore)

// Component state
const produitId = route.params.id as string
const isLoading = ref(true)

// Computed
const breadcrumbs = computed(() => [
  { title: t('title_admin_dashboard'), to: '/admin' },
  { title: t('admin_produits_title'), to: '/admin/produits' },
  { title: currentProduit.value?.titre || t('admin_produits_edit'), active: true }
])

// Methods
const goBack = () => {
  router.push('/admin/produits')
}

const loadProduit = async () => {
  try {
    isLoading.value = true
    console.log('[Edit] Loading product with ID:', produitId)
    await produitsStore.fetchProduit(produitId)
    console.log('[Edit] Product loaded successfully:', currentProduit.value?.titre)
  } catch (err) {
    console.error('[Edit] Error loading product:', err)
    router.push('/admin/produits')
  } finally {
    isLoading.value = false
  }
}

const handleProductCreated = (product: any) => {
  // This won't be called in edit mode, but keeping for consistency
  console.log('Product created:', product)
}

const handleProductUpdated = (product: any) => {
  console.log('Product updated:', product)
  // Stay on the edit page after update - no redirect
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
              {{ $t('admin_produits_edit') }}
            </h1>
            <p class="text-body-1 text-medium-emphasis">
              {{ currentProduit?.titre || $t('admin_produits_edit_subtitle') }}
            </p>
          </div>
          <VBtn
            variant="outlined"
            prepend-icon="tabler-arrow-left"
            @click="goBack"
          >
            {{ $t('common.back') }}
          </VBtn>
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

    <!-- Product Form -->
    <ProductForm
      v-else-if="currentProduit"
      mode="edit"
      :id="produitId"
      @created="handleProductCreated"
      @updated="handleProductUpdated"
    />

    <!-- Error State -->
    <VCard v-else>
      <VCardText class="text-center py-8">
        <VIcon icon="tabler-alert-circle" size="48" color="error" class="mb-4" />
        <h3 class="text-h5 mb-2">{{ $t('admin_produits_not_found') }}</h3>
        <p class="text-body-1 text-medium-emphasis mb-4">
          {{ $t('admin_produits_not_found_message') }}
        </p>
        <VBtn color="primary" @click="goBack">
          {{ $t('common.back_to_list') }}
        </VBtn>
      </VCardText>
    </VCard>
  </div>
</template>
