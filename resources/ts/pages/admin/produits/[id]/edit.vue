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
const { currentProduit, images, videos, variantes, loading } = storeToRefs(produitsStore)

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
const loadProduit = async () => {
  try {
    isLoading.value = true
    await produitsStore.fetchProduit(produitId)
  } catch (err) {
    console.error('Error loading product:', err)
    router.push({ name: 'admin-produits' })
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
  router.push({ name: 'admin-produits' })
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
  </div>
</template>
