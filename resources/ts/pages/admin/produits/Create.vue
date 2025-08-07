<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
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
const { t } = useI18n()
const produitsStore = useProduitsStore()
const boutiquesStore = useBoutiquesStore()
const categoriesStore = useCategoriesStore()

// Store state
const { items: boutiques } = storeToRefs(boutiquesStore)
const { categories } = storeToRefs(categoriesStore)

// Form state
const formRef = ref()
const isSubmitting = ref(false)
const errors = ref<Record<string, string[]>>({})
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
  quantite_min: null,
  notes_admin: '',
  actif: true
})

// Computed
const breadcrumbs = computed(() => [
  { title: t('title_admin_dashboard'), to: '/admin' },
  { title: t('admin_produits_title'), to: '/admin/produits' },
  { title: t('admin_produits_create'), active: true }
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
    await produitsStore.createProduit(form.value)
    router.push({ name: 'admin-produits-index' })
  } catch (err: any) {
    if (err.errors) {
      errors.value = err.errors
    } else {
      console.error('Error creating product:', err)
    }
  } finally {
    isSubmitting.value = false
  }
}

// Lifecycle
onMounted(async () => {
  await loadFilterOptions()
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

    <!-- Comprehensive Form with Tabs -->
    <VCard>
      <VTabs v-model="currentTab" bg-color="primary">
        <VTab value="basic">
          <VIcon icon="tabler-info-circle" class="me-2" />
          {{ $t('admin_produits_basic_info') }}
        </VTab>
        <VTab value="images">
          <VIcon icon="tabler-photo" class="me-2" />
          {{ $t('admin_produits_images') }}
        </VTab>
        <VTab value="videos">
          <VIcon icon="tabler-video" class="me-2" />
          {{ $t('admin_produits_videos') }}
        </VTab>
        <VTab value="variants">
          <VIcon icon="tabler-versions" class="me-2" />
          {{ $t('admin_produits_variantes') }}
        </VTab>
        <VTab value="features">
          <VIcon icon="tabler-list-check" class="me-2" />
          {{ $t('admin_produits_features') }}
        </VTab>
        <VTab value="seo">
          <VIcon icon="tabler-search" class="me-2" />
          {{ $t('admin_produits_seo') }}
        </VTab>
      </VTabs>

      <VTabsWindow v-model="currentTab">
        <!-- Basic Information Tab -->
        <VTabsWindowItem value="basic">
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
                    prepend-inner-icon="tabler-building-store"
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
                    prepend-inner-icon="tabler-category"
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
                    prepend-inner-icon="tabler-tag"
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
                    prepend-inner-icon="tabler-file-text"
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
                    prepend-inner-icon="tabler-shopping-cart"
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
                    prepend-inner-icon="tabler-tag"
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
                    prepend-inner-icon="tabler-users"
                  />
                </VCol>

                <!-- Status -->
                <VCol cols="12" md="6">
                  <VSwitch
                    v-model="form.actif"
                    :label="$t('admin_produits_actif')"
                    color="primary"
                    inset
                  />
                </VCol>

                <!-- Admin Notes -->
                <VCol cols="12">
                  <VTextarea
                    v-model="form.notes_admin"
                    :label="$t('admin_produits_notes_admin')"
                    :placeholder="$t('admin_produits_notes_admin_placeholder')"
                    :error-messages="errors.notes_admin"
                    variant="outlined"
                    rows="3"
                    prepend-inner-icon="tabler-note"
                  />
                </VCol>
              </VRow>
            </VForm>
          </VCardText>
        </VTabsWindowItem>

        <!-- Images Tab -->
        <VTabsWindowItem value="images">
          <VCardText>
            <div class="d-flex justify-space-between align-center mb-4">
              <h3>{{ $t('admin_produits_images_management') }}</h3>
              <VBtn
                color="primary"
                prepend-icon="tabler-plus"
                @click="productImages.push({ url: '', alt_text: '', ordre: productImages.length + 1 })"
              >
                {{ $t('admin_produits_add_image') }}
              </VBtn>
            </div>

            <VAlert v-if="productImages.length === 0" type="info" variant="tonal" class="mb-4">
              {{ $t('admin_produits_no_images') }}
            </VAlert>

            <VRow v-for="(image, index) in productImages" :key="index" class="mb-3">
              <VCol cols="12" md="6">
                <VTextField
                  v-model="image.url"
                  :label="$t('admin_produits_image_url')"
                  variant="outlined"
                  prepend-inner-icon="tabler-link"
                />
              </VCol>
              <VCol cols="12" md="4">
                <VTextField
                  v-model="image.alt_text"
                  :label="$t('admin_produits_image_alt')"
                  variant="outlined"
                  prepend-inner-icon="tabler-text-caption"
                />
              </VCol>
              <VCol cols="12" md="2">
                <VBtn
                  color="error"
                  variant="outlined"
                  icon="tabler-trash"
                  @click="productImages.splice(index, 1)"
                />
              </VCol>
            </VRow>
          </VCardText>
        </VTabsWindowItem>

        <!-- Other tabs will be added here -->
        <VTabsWindowItem value="videos">
          <VCardText>
            <VAlert type="info" variant="tonal">
              Videos management - Coming soon
            </VAlert>
          </VCardText>
        </VTabsWindowItem>

        <VTabsWindowItem value="variants">
          <VCardText>
            <VAlert type="info" variant="tonal">
              Variants management - Coming soon
            </VAlert>
          </VCardText>
        </VTabsWindowItem>

        <VTabsWindowItem value="features">
          <VCardText>
            <VAlert type="info" variant="tonal">
              Features management - Coming soon
            </VAlert>
          </VCardText>
        </VTabsWindowItem>

        <VTabsWindowItem value="seo">
          <VCardText>
            <VAlert type="info" variant="tonal">
              SEO management - Coming soon
            </VAlert>
          </VCardText>
        </VTabsWindowItem>
      </VTabsWindow>

      <!-- Form Actions -->
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
          {{ $t('admin_produits_create') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </div>
</template>
