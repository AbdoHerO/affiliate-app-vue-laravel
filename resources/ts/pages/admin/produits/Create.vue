<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
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
const { t } = useI18n()

// Component state
const createdProductId = ref<string | null>(null)

// Computed
const breadcrumbs = computed(() => [
  { title: t('title_admin_dashboard'), to: '/admin' },
  { title: t('admin_produits_title'), to: '/admin/produits' },
  { title: t('admin_produits_create'), active: true }
])

// Methods
const goBack = () => {
  router.push({ name: 'admin-produits' })
}

const handleProductCreated = (product: any) => {
  // Stay on the same page and switch to edit mode
  createdProductId.value = product.id
  console.log('Product created, staying on page with ID:', product.id)
}

const handleProductUpdated = (product: any) => {
  // This won't be called in create mode, but keeping for consistency
  console.log('Product updated:', product)
}

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
              {{ $t('admin_produits_create') }}
            </h1>
            <p class="text-body-1 text-medium-emphasis">
              {{ $t('admin_produits_create_subtitle') }}
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

    <!-- Product Form -->
    <ProductForm
      :mode="createdProductId ? 'edit' : 'create'"
      :id="createdProductId"
      @created="handleProductCreated"
      @updated="handleProductUpdated"
    />
  </div>
</template>










