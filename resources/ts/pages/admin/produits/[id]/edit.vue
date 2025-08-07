<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'
import { useProduitsStore, type ProduitFormData } from '@/stores/admin/produits'
import { useBoutiquesStore } from '@/stores/admin/boutiques'
import { useCategoriesStore } from '@/stores/admin/categories'
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
const boutiquesStore = useBoutiquesStore()
const categoriesStore = useCategoriesStore()

// Store state
const { loading, error } = storeToRefs(produitsStore)
const { items: boutiques } = storeToRefs(boutiquesStore)
const { items: categories } = storeToRefs(categoriesStore)

// Form state
const formRef = ref()
const isSubmitting = ref(false)
const isLoading = ref(true)
const errors = ref<Record<string, string[]>>({})
const produitId = route.params.id as string
const currentTab = ref('basic')

// Additional state for comprehensive form
const productImages = ref<Array<{
  id?: string
  url: string
  alt_text: string
  ordre: number
  file?: File
}>>([])

const productVideos = ref<Array<{
  id?: string
  url: string
  titre: string
  ordre: number
}>>([])

const productVariants = ref<Array<{
  id?: string
  nom: string
  valeur: string
  prix_vente_variante: number | null
  image_url: string | null
  sku_variante: string
  actif: boolean
}>>([])

const productFeatures = ref<string[]>([''])
const metaDescription = ref('')
const metaKeywords = ref('')

const form = ref<ProduitFormData>({
  boutique_id: '',
  categorie_id: '',
  titre: '',
  description: '',
  prix_achat: null,
  prix_vente: null,
  prix_affilie: null,
  quantite_min: 1,
  notes_admin: '',
  actif: true
})

// Computed
const breadcrumbs = computed(() => [
  { title: t('title_admin_dashboard'), to: '/admin' },
  { title: t('admin_produits_title'), to: '/admin/produits' },
  { title: t('admin_produits_edit'), active: true }
])

// Methods
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

const loadProduit = async () => {
  try {
    isLoading.value = true
    const produit = await produitsStore.fetchProduit(produitId)
    
    // Populate form with existing data
    form.value = {
      boutique_id: produit.boutique_id,
      categorie_id: produit.categorie_id || '',
      titre: produit.titre,
      description: produit.description || '',
      prix_achat: produit.prix_achat,
      prix_vente: produit.prix_vente,
      prix_affilie: produit.prix_affilie,
      quantite_min: produit.quantite_min || 1,
      notes_admin: produit.notes_admin || '',
      actif: produit.actif
    }
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

const submit = async () => {
  if (!formRef.value) return

  const { valid } = await formRef.value.validate()
  if (!valid) return

  isSubmitting.value = true
  errors.value = {}

  try {
    await produitsStore.updateProduit(produitId, form.value)
    router.push({ name: 'admin-produits-index' })
  } catch (err: any) {
    if (err.errors) {
      errors.value = err.errors
    } else {
      console.error('Error updating product:', err)
    }
  } finally {
    isSubmitting.value = false
  }
}

// Lifecycle
onMounted(async () => {
  await loadFilterOptions()
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
              {{ $t('admin_produits_edit_subtitle') }}
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

    <!-- Form -->
    <VCard v-else>
      <VCardText>
        <VForm ref="formRef" @submit.prevent="submit">
          <VRow>
            <!-- Boutique Selection -->
            <VCol cols="12" md="6">
              <VSelect
                v-model="form.boutique_id"
                :items="boutiques"
                item-title="nom"
                item-value="id"
                :label="$t('admin_produits_boutique')"
                :error-messages="errors.boutique_id"
                required
                variant="outlined"
              />
            </VCol>

            <!-- Category Selection -->
            <VCol cols="12" md="6">
              <VSelect
                v-model="form.categorie_id"
                :items="categories"
                item-title="nom"
                item-value="id"
                :label="$t('admin_produits_categorie')"
                :error-messages="errors.categorie_id"
                variant="outlined"
              />
            </VCol>

            <!-- Product Title -->
            <VCol cols="12">
              <VTextField
                v-model="form.titre"
                :label="$t('admin_produits_titre')"
                :placeholder="$t('admin_produits_titre_placeholder')"
                :error-messages="errors.titre"
                required
                variant="outlined"
              />
            </VCol>

            <!-- Product Description -->
            <VCol cols="12">
              <VTextarea
                v-model="form.description"
                :label="$t('admin_produits_description')"
                :placeholder="$t('admin_produits_description_placeholder')"
                :error-messages="errors.description"
                variant="outlined"
                rows="4"
              />
            </VCol>

            <!-- Pricing -->
            <VCol cols="12" md="4">
              <VTextField
                v-model.number="form.prix_achat"
                :label="$t('admin_produits_prix_achat')"
                :placeholder="$t('admin_produits_prix_achat_placeholder')"
                :error-messages="errors.prix_achat"
                type="number"
                step="0.01"
                min="0"
                suffix="DH"
                variant="outlined"
              />
            </VCol>

            <VCol cols="12" md="4">
              <VTextField
                v-model.number="form.prix_vente"
                :label="$t('admin_produits_prix_vente')"
                :placeholder="$t('admin_produits_prix_vente_placeholder')"
                :error-messages="errors.prix_vente"
                type="number"
                step="0.01"
                min="0"
                suffix="DH"
                variant="outlined"
                required
              />
            </VCol>

            <VCol cols="12" md="4">
              <VTextField
                v-model.number="form.prix_affilie"
                :label="$t('admin_produits_prix_affilie')"
                :placeholder="$t('admin_produits_prix_affilie_placeholder')"
                :error-messages="errors.prix_affilie"
                type="number"
                step="0.01"
                min="0"
                suffix="DH"
                variant="outlined"
              />
            </VCol>

            <!-- Status -->
            <VCol cols="12">
              <VSwitch
                v-model="form.actif"
                :label="$t('admin_produits_actif')"
                color="primary"
              />
            </VCol>
          </VRow>
        </VForm>
      </VCardText>

      <VDivider />

      <VCardActions>
        <VSpacer />
        <VBtn
          variant="outlined"
          @click="goBack"
        >
          {{ $t('common.cancel') }}
        </VBtn>
        <VBtn
          color="primary"
          :loading="isSubmitting"
          @click="submit"
        >
          {{ $t('admin_produits_update') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </div>
</template>
