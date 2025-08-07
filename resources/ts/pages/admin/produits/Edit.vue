<template>
  <div>
    <!-- Breadcrumbs -->
    <VBreadcrumbs
      :items="[
        { title: $t('breadcrumb_home'), to: '/' },
        { title: $t('admin_produits_title'), to: '/admin/produits' },
        { title: $t('admin_produits_edit') },
      ]"
      class="pa-0 mb-4"
    />

    <!-- Loading State -->
    <VRow v-if="isLoadingProduit" justify="center">
      <VCol cols="12" class="text-center">
        <VProgressCircular indeterminate size="64" />
        <div class="mt-4">{{ $t('admin_common_loading') }}...</div>
      </VCol>
    </VRow>

    <!-- Main Content -->
    <VRow v-else-if="produit">
      <VCol cols="12">
        <VTabs v-model="activeTab">
          <VTab value="details">
            <VIcon start>mdi-information</VIcon>
            {{ $t('admin_produits_details') }}
          </VTab>
          <VTab value="images">
            <VIcon start>mdi-image-multiple</VIcon>
            {{ $t('admin_produits_images') }}
            <VChip class="ml-2" size="x-small">{{ produit.images?.length || 0 }}</VChip>
            </VTab>
          </VTabs>

          <VTabsWindow v-model="activeTab">
            <!-- Details Tab -->
            <VTabsWindowItem value="details">
              <VCard>
                <VCardTitle>
                  {{ $t('admin_produits_details') }}
                </VCardTitle>
                
                <VDivider />
                
                <VCardText>
                  <VForm ref="formRef" @submit.prevent="submit">
                    <VRow>
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
                      
                      <VCol cols="12" md="6">
                        <VSelect
                          v-model="form.categorie_id"
                          :items="categories"
                          item-title="nom"
                          item-value="id"
                          :label="$t('admin_produits_categorie')"
                          :error-messages="errors.categorie_id"
                          variant="outlined"
                          clearable
                        />
                      </VCol>
                      
                      <VCol cols="12" md="8">
                        <VTextField
                          v-model="form.titre"
                          :label="$t('admin_produits_titre')"
                          :error-messages="errors.titre"
                          required
                          variant="outlined"
                        />
                      </VCol>
                      
                      <VCol cols="12" md="4">
                        <VTextField
                          v-model="form.slug"
                          :label="$t('admin_produits_slug')"
                          :error-messages="errors.slug"
                          variant="outlined"
                          readonly
                          :hint="$t('admin_produits_slug_hint')"
                        />
                      </VCol>
                      
                      <VCol cols="12">
                        <VTextarea
                          v-model="form.description"
                          :label="$t('admin_produits_description')"
                          :error-messages="errors.description"
                          variant="outlined"
                          rows="4"
                        />
                      </VCol>
                      
                      <VCol cols="12" md="4">
                        <VTextField
                          v-model="form.prix_achat"
                          :label="$t('admin_produits_prix_achat')"
                          :error-messages="errors.prix_achat"
                          type="number"
                          step="0.01"
                          min="0"
                          variant="outlined"
                          suffix="MAD"
                        />
                      </VCol>
                      
                      <VCol cols="12" md="4">
                        <VTextField
                          v-model="form.prix_vente"
                          :label="$t('admin_produits_prix_vente')"
                          :error-messages="errors.prix_vente"
                          type="number"
                          step="0.01"
                          min="0"
                          required
                          variant="outlined"
                          suffix="MAD"
                        />
                      </VCol>
                      
                      <VCol cols="12" md="4">
                        <VTextField
                          v-model="form.prix_affilie"
                          :label="$t('admin_produits_prix_affilie')"
                          :error-messages="errors.prix_affilie"
                          type="number"
                          step="0.01"
                          min="0"
                          variant="outlined"
                          suffix="MAD"
                        />
                      </VCol>
                      
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
                    {{ $t('admin_common_cancel') }}
                  </VBtn>
                  <VBtn
                    color="primary"
                    :loading="loading"
                    @click="submit"
                  >
                    {{ $t('admin_common_update') }}
                  </VBtn>
                </VCardActions>
              </VCard>
            </VTabsWindowItem>

            <!-- Images Tab -->
            <VTabsWindowItem value="images">
              <VCard>
                <VCardTitle>
                  {{ $t('admin_produits_images') }}
                </VCardTitle>
                
                <VDivider />
                
                <VCardText>
                  <ProductImageGallery 
                    :produit-id="produit.id"
                    :images="produit.images || []"
                    @images-updated="handleImagesUpdated"
                  />
                </VCardText>
              </VCard>
            </VTabsWindowItem>
          </VTabsWindow>
        </VCol>
      </VRow>

    <!-- Error State -->
    <VRow v-else>
      <VCol cols="12">
        <VAlert type="error">
          {{ $t('admin_common_error_loading') }}
        </VAlert>
      </VCol>
    </VRow>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useProduitsStore } from '@/stores/admin/produits'
import { useBoutiquesStore } from '@/stores/admin/boutiques'
import { useCategoriesStore } from '@/stores/admin/categories'
import ProductImageGallery from '@/components/admin/ProductImageGallery.vue'

definePage({
  meta: {
    requiresAuth: true,
    requiresRole: 'admin',
  },
})

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const produitsStore = useProduitsStore()
const boutiquesStore = useBoutiquesStore()
const categoriesStore = useCategoriesStore()

const formRef = ref()
const loading = ref(false)
const isLoadingProduit = ref(true)
const errors = ref<Record<string, string[]>>({})
const activeTab = ref('details')

const form = reactive({
  boutique_id: '',
  categorie_id: null,
  titre: '',
  description: null,
  prix_achat: 0,
  prix_vente: 0,
  prix_affilie: null,
  slug: '',
  actif: true,
  quantite_min: 1,
  notes_admin: null,
})

const boutiques = computed(() => boutiquesStore.items)
const categories = computed(() => categoriesStore.categories)
const produit = computed(() => produitsStore.currentProduit)

const breadcrumbs = computed(() => [
  { title: t('admin_sidebar_dashboard'), to: '/admin' },
  { title: t('admin_sidebar_produits'), to: '/admin/produits' },
  { title: t('admin_produits_edit'), to: '' }
])

// Auto-generate slug from titre
watch(() => form.titre, (newValue) => {
  if (newValue) {
    form.slug = newValue
      .toLowerCase()
      .replace(/[^\w\s-]/g, '')
      .replace(/[\s_-]+/g, '-')
      .replace(/^-+|-+$/g, '')
  }
})

onMounted(async () => {
  try {
    isLoadingProduit.value = true
    
    // Load produit details
    await produitsStore.fetchProduit(route.params.id as string)
    
    // Load boutiques and categories for dropdowns
    await Promise.all([
      boutiquesStore.fetchBoutiques(),
      categoriesStore.fetchCategories()
    ])
    
    // Populate form with produit data
    if (produit.value) {
      Object.assign(form, {
        boutique_id: produit.value.boutique_id,
        categorie_id: produit.value.categorie_id,
        titre: produit.value.titre,
        slug: produit.value.slug,
        description: produit.value.description,
        prix_achat: produit.value.prix_achat,
        prix_vente: produit.value.prix_vente,
        prix_affilie: produit.value.prix_affilie,
        actif: produit.value.actif
      })
    }
  } catch (error) {
    console.error('Error loading produit:', error)
  } finally {
    isLoadingProduit.value = false
  }
})

const submit = async () => {
  if (!formRef.value) return
  
  const { valid } = await formRef.value.validate()
  if (!valid) return
  
  loading.value = true
  errors.value = {}
  
  try {
    await produitsStore.updateProduit(route.params.id as string, form)
    await router.push('/admin/produits')
  } catch (error: any) {
    if (error.response?.status === 422) {
      errors.value = error.response.data.errors || {}
    }
  } finally {
    loading.value = false
  }
}

const goBack = () => {
  router.push('/admin/produits')
}

const handleImagesUpdated = () => {
  // Refresh produit data to update images list
  produitsStore.fetchProduit(route.params.id as string)
}
</script>
