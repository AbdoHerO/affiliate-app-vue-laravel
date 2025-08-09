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

// Propositions affiliés state
const productPropositions = ref<Array<{
  id?: string
  affiliate_id: string
  commission_percentage: number
  commission_fixe: number | null
  date_debut: string
  date_fin: string | null
  actif: boolean
  notes: string
}>>([])

// Stock et ruptures state
const stockAlerts = ref({
  seuil_alerte: 10,
  email_notification: true,
  auto_disable: false,
  message_rupture: ''
})

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

// File upload handlers
const handleImageUploaded = (index: number, data: { url: string, file: File }) => {
  productImages.value[index].url = data.url
}

const handleVideoUploaded = (index: number, data: { url: string, file: File }) => {
  productVideos.value[index].url = data.url
}

// Helper methods
const getVideoEmbedUrl = (url: string): string => {
  if (!url) return ''

  // YouTube
  const youtubeMatch = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&\n?#]+)/)
  if (youtubeMatch) {
    return `https://www.youtube.com/embed/${youtubeMatch[1]}`
  }

  // Vimeo
  const vimeoMatch = url.match(/vimeo\.com\/(\d+)/)
  if (vimeoMatch) {
    return `https://player.vimeo.com/video/${vimeoMatch[1]}`
  }

  return ''
}

const moveFeature = (fromIndex: number, toIndex: number) => {
  const features = [...productFeatures.value]
  const [movedFeature] = features.splice(fromIndex, 1)
  features.splice(toIndex, 0, movedFeature)
  productFeatures.value = features
}

const getSeoScore = (): number => {
  let score = 0

  // Title (30 points)
  if (form.value.titre) {
    score += 30
    if (form.value.titre.length >= 30 && form.value.titre.length <= 60) score += 10
  }

  // Description (20 points)
  if (form.value.description) {
    score += 20
    if (form.value.description.length >= 120) score += 10
  }

  // Meta description (20 points)
  if (metaDescription.value) {
    score += 20
    if (metaDescription.value.length >= 120 && metaDescription.value.length <= 160) score += 10
  }

  // Meta keywords (10 points)
  if (metaKeywords.value) score += 10

  // Images (10 points)
  if (productImages.value.length > 0) score += 10

  return Math.min(score, 100)
}

const getSeoRecommendations = (): string => {
  const recommendations = []

  if (!form.value.titre) recommendations.push('Add a product title')
  else if (form.value.titre.length < 30) recommendations.push('Title should be at least 30 characters')
  else if (form.value.titre.length > 60) recommendations.push('Title should be less than 60 characters')

  if (!form.value.description) recommendations.push('Add a product description')
  else if (form.value.description.length < 120) recommendations.push('Description should be at least 120 characters')

  if (!metaDescription.value) recommendations.push('Add a meta description')
  else if (metaDescription.value.length < 120) recommendations.push('Meta description should be at least 120 characters')
  else if (metaDescription.value.length > 160) recommendations.push('Meta description should be less than 160 characters')

  if (!metaKeywords.value) recommendations.push('Add meta keywords')
  if (productImages.value.length === 0) recommendations.push('Add at least one image')

  return recommendations.length > 0 ? recommendations.join(', ') : 'Great! Your SEO is optimized.'
}

const submit = async () => {
  if (!formRef.value) return

  const { valid } = await formRef.value.validate()
  if (!valid) return

  isSubmitting.value = true
  errors.value = {}

  try {
    // Ensure quantite_min has a default value
    const formData = { ...form.value }
    if (!formData.quantite_min || formData.quantite_min < 1) {
      formData.quantite_min = 1
    }

    await produitsStore.createProduit(formData)
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

                <!-- Minimum Quantity -->
                <VCol cols="12" md="6">
                  <VTextField
                    v-model.number="form.quantite_min"
                    :label="$t('admin_produits_quantite_min')"
                    :placeholder="$t('admin_produits_quantite_min_placeholder')"
                    :error-messages="errors.quantite_min"
                    type="number"
                    min="1"
                    variant="outlined"
                    prepend-inner-icon="tabler-package"
                    required
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

            <VRow v-for="(image, index) in productImages" :key="index" class="mb-4">
              <VCol cols="12">
                <VCard variant="outlined" class="pa-4">
                  <div class="d-flex justify-space-between align-center mb-3">
                    <h4 class="text-subtitle-1">{{ $t('admin_produits_image') }} {{ index + 1 }}</h4>
                    <VBtn
                      color="error"
                      variant="text"
                      size="small"
                      icon="tabler-trash"
                      @click="productImages.splice(index, 1)"
                    />
                  </div>

                  <VRow>
                    <VCol cols="12" md="8">
                      <FileUploader
                        :url="image.url"
                        :url-label="$t('admin_produits_image_url')"
                        :url-placeholder="$t('admin_produits_image_url_placeholder')"
                        :file-label="$t('admin_produits_upload_image')"
                        accept="image/*"
                        :upload-endpoint="'/admin/produits/{id}/images/upload'"
                        :product-id="'temp'"
                        @update:url="image.url = $event"
                        @file-uploaded="handleImageUploaded(index, $event)"
                      />
                    </VCol>
                    <VCol cols="12" md="3">
                      <VTextField
                        v-model="image.alt_text"
                        :label="$t('admin_produits_image_alt')"
                        variant="outlined"
                        prepend-inner-icon="tabler-text-caption"
                        placeholder="Image description"
                      />
                    </VCol>
                    <VCol cols="12" md="1">
                      <VTextField
                        v-model.number="image.ordre"
                        :label="$t('admin_produits_image_order')"
                        variant="outlined"
                        type="number"
                        min="1"
                        prepend-inner-icon="tabler-sort-ascending"
                      />
                    </VCol>
                  </VRow>

                  <!-- Image Preview -->
                  <VRow v-if="image.url" class="mt-3">
                    <VCol cols="12">
                      <VDivider class="mb-3" />
                      <p class="text-body-2 mb-2">{{ $t('admin_produits_image_preview') }}:</p>
                      <VImg
                        :src="image.url"
                        :alt="image.alt_text"
                        max-width="200"
                        max-height="150"
                        class="rounded"
                      />
                    </VCol>
                  </VRow>
                </VCard>
              </VCol>
            </VRow>
          </VCardText>
        </VTabsWindowItem>

        <!-- Videos Tab -->
        <VTabsWindowItem value="videos">
          <VCardText>
            <div class="d-flex justify-space-between align-center mb-4">
              <div>
                <h3 class="text-h6 mb-1">{{ $t('admin_produits_videos_management') }}</h3>
                <p class="text-body-2 text-medium-emphasis">{{ $t('admin_produits_videos_help') }}</p>
              </div>
              <VBtn
                color="primary"
                prepend-icon="tabler-plus"
                @click="productVideos.push({ url: '', titre: '', ordre: productVideos.length + 1 })"
              >
                {{ $t('admin_produits_add_video') }}
              </VBtn>
            </div>

            <VAlert v-if="productVideos.length === 0" type="info" variant="tonal" class="mb-4">
              <VIcon icon="tabler-info-circle" class="me-2" />
              {{ $t('admin_produits_no_videos') }}
            </VAlert>

            <VRow v-for="(video, index) in productVideos" :key="index" class="mb-4">
              <VCol cols="12">
                <VCard variant="outlined" class="pa-4">
                  <VRow>
                    <VCol cols="12" md="5">
                      <VTextField
                        v-model="video.url"
                        :label="$t('admin_produits_video_url')"
                        variant="outlined"
                        prepend-inner-icon="tabler-link"
                        placeholder="https://youtube.com/watch?v=... ou https://vimeo.com/..."
                      />
                    </VCol>
                    <VCol cols="12" md="4">
                      <VTextField
                        v-model="video.titre"
                        :label="$t('admin_produits_video_title')"
                        variant="outlined"
                        prepend-inner-icon="tabler-text-caption"
                      />
                    </VCol>
                    <VCol cols="12" md="2">
                      <VTextField
                        v-model.number="video.ordre"
                        :label="$t('admin_produits_video_order')"
                        variant="outlined"
                        type="number"
                        min="1"
                        prepend-inner-icon="tabler-sort-ascending"
                      />
                    </VCol>
                    <VCol cols="12" md="1">
                      <VBtn
                        color="error"
                        variant="outlined"
                        icon="tabler-trash"
                        @click="productVideos.splice(index, 1)"
                      />
                    </VCol>
                  </VRow>

                  <!-- Video Preview -->
                  <VRow v-if="video.url && /^https:\/\/(www\.)?(youtube\.com\/watch\?v=|youtu\.be\/|vimeo\.com\/)/.test(video.url)" class="mt-3">
                    <VCol cols="12">
                      <VDivider class="mb-3" />
                      <p class="text-body-2 mb-2">{{ $t('admin_produits_video_preview') }}:</p>
                      <div class="video-preview">
                        <iframe
                          :src="getVideoEmbedUrl(video.url)"
                          width="300"
                          height="200"
                          frameborder="0"
                          allowfullscreen
                        />
                      </div>
                    </VCol>
                  </VRow>
                </VCard>
              </VCol>
            </VRow>
          </VCardText>
        </VTabsWindowItem>

        <!-- Variants Tab -->
        <VTabsWindowItem value="variants">
          <VCardText>
            <div class="d-flex justify-space-between align-center mb-4">
              <div>
                <h3 class="text-h6 mb-1">{{ $t('admin_produits_variants_management') }}</h3>
                <p class="text-body-2 text-medium-emphasis">{{ $t('admin_produits_variants_help') }}</p>
              </div>
              <VBtn
                color="primary"
                prepend-icon="tabler-plus"
                @click="productVariants.push({ nom: '', valeur: '', prix_vente_variante: null, image_url: null, sku_variante: '', actif: true })"
              >
                {{ $t('admin_produits_add_variant') }}
              </VBtn>
            </div>

            <VAlert v-if="productVariants.length === 0" type="info" variant="tonal" class="mb-4">
              <VIcon icon="tabler-info-circle" class="me-2" />
              {{ $t('admin_produits_no_variants') }}
            </VAlert>

            <VRow v-for="(variant, index) in productVariants" :key="index" class="mb-4">
              <VCol cols="12">
                <VCard variant="outlined" class="pa-4">
                  <div class="d-flex justify-space-between align-center mb-3">
                    <h4 class="text-subtitle-1">{{ $t('admin_produits_variant') }} {{ index + 1 }}</h4>
                    <VBtn
                      color="error"
                      variant="text"
                      size="small"
                      icon="tabler-trash"
                      @click="productVariants.splice(index, 1)"
                    />
                  </div>

                  <VRow>
                    <VCol cols="12" md="3">
                      <VTextField
                        v-model="variant.nom"
                        :label="$t('admin_produits_variant_name')"
                        variant="outlined"
                        placeholder="Couleur, Taille, Matière..."
                        prepend-inner-icon="tabler-tag"
                      />
                    </VCol>
                    <VCol cols="12" md="3">
                      <VTextField
                        v-model="variant.valeur"
                        :label="$t('admin_produits_variant_value')"
                        variant="outlined"
                        placeholder="Rouge, XL, Coton..."
                        prepend-inner-icon="tabler-palette"
                      />
                    </VCol>
                    <VCol cols="12" md="2">
                      <VTextField
                        v-model.number="variant.prix_vente_variante"
                        :label="$t('admin_produits_variant_price')"
                        variant="outlined"
                        type="number"
                        step="0.01"
                        min="0"
                        suffix="DH"
                        prepend-inner-icon="tabler-currency-dirham"
                      />
                    </VCol>
                    <VCol cols="12" md="2">
                      <VTextField
                        v-model="variant.sku_variante"
                        :label="$t('admin_produits_variant_sku')"
                        variant="outlined"
                        placeholder="SKU-001"
                        prepend-inner-icon="tabler-barcode"
                      />
                    </VCol>
                    <VCol cols="12" md="2">
                      <div class="d-flex align-center h-100">
                        <VSwitch
                          v-model="variant.actif"
                          :label="$t('common.active')"
                          color="primary"
                          hide-details
                        />
                      </div>
                    </VCol>
                  </VRow>
                </VCard>
              </VCol>
            </VRow>
          </VCardText>
        </VTabsWindowItem>

        <!-- Features Tab -->
        <VTabsWindowItem value="features">
          <VCardText>
            <div class="d-flex justify-space-between align-center mb-4">
              <div>
                <h3 class="text-h6 mb-1">{{ $t('admin_produits_features_management') }}</h3>
                <p class="text-body-2 text-medium-emphasis">{{ $t('admin_produits_features_help') }}</p>
              </div>
              <VBtn
                color="primary"
                prepend-icon="tabler-plus"
                @click="productFeatures.push('')"
              >
                {{ $t('admin_produits_add_feature') }}
              </VBtn>
            </div>

            <VAlert type="info" variant="tonal" class="mb-4">
              <VIcon icon="tabler-lightbulb" class="me-2" />
              {{ $t('admin_produits_features_examples') }}
            </VAlert>

            <VRow v-for="(feature, index) in productFeatures" :key="index" class="mb-3">
              <VCol cols="12" md="10">
                <VTextField
                  v-model="productFeatures[index]"
                  :label="`${$t('admin_produits_feature')} ${index + 1}`"
                  variant="outlined"
                  prepend-inner-icon="tabler-check"
                  :placeholder="$t('admin_produits_feature_placeholder')"
                />
              </VCol>
              <VCol cols="12" md="2">
                <div class="d-flex gap-2">
                  <VBtn
                    v-if="productFeatures.length > 1"
                    color="error"
                    variant="outlined"
                    icon="tabler-trash"
                    @click="productFeatures.splice(index, 1)"
                  />
                  <VBtn
                    v-if="index > 0"
                    color="primary"
                    variant="outlined"
                    icon="tabler-arrow-up"
                    @click="moveFeature(index, index - 1)"
                  />
                  <VBtn
                    v-if="index < productFeatures.length - 1"
                    color="primary"
                    variant="outlined"
                    icon="tabler-arrow-down"
                    @click="moveFeature(index, index + 1)"
                  />
                </div>
              </VCol>
            </VRow>

            <!-- Features Preview -->
            <VDivider class="my-4" />
            <div class="mb-4">
              <h4 class="text-subtitle-1 mb-3">{{ $t('admin_produits_features_preview') }}</h4>
              <VCard variant="outlined" class="pa-4">
                <ul v-if="productFeatures.some(f => f.trim())" class="features-list">
                  <li v-for="(feature, index) in productFeatures.filter(f => f.trim())" :key="index" class="mb-2">
                    <VIcon icon="tabler-check" color="success" size="16" class="me-2" />
                    {{ feature }}
                  </li>
                </ul>
                <p v-else class="text-medium-emphasis">{{ $t('admin_produits_no_features_preview') }}</p>
              </VCard>
            </div>
          </VCardText>
        </VTabsWindowItem>

        <!-- Propositions Affiliés Tab -->
        <VTabsWindowItem value="propositions">
          <VCardText>
            <div class="d-flex justify-space-between align-center mb-4">
              <div>
                <h3 class="text-h6 mb-1">{{ $t('admin_produits_propositions_management') }}</h3>
                <p class="text-body-2 text-medium-emphasis">{{ $t('admin_produits_propositions_help') }}</p>
              </div>
              <VBtn
                color="primary"
                prepend-icon="tabler-plus"
                @click="productPropositions.push({ affiliate_id: '', commission_percentage: 10, commission_fixe: null, date_debut: new Date().toISOString().split('T')[0], date_fin: null, actif: true, notes: '' })"
              >
                {{ $t('admin_produits_add_proposition') }}
              </VBtn>
            </div>

            <VAlert v-if="productPropositions.length === 0" type="info" variant="tonal" class="mb-4">
              <VIcon icon="tabler-info-circle" class="me-2" />
              {{ $t('admin_produits_no_propositions') }}
            </VAlert>

            <VRow v-for="(proposition, index) in productPropositions" :key="index" class="mb-4">
              <VCol cols="12">
                <VCard variant="outlined" class="pa-4">
                  <div class="d-flex justify-space-between align-center mb-3">
                    <h4 class="text-subtitle-1">{{ $t('admin_produits_proposition') }} {{ index + 1 }}</h4>
                    <VBtn
                      color="error"
                      variant="text"
                      size="small"
                      icon="tabler-trash"
                      @click="productPropositions.splice(index, 1)"
                    />
                  </div>

                  <VRow>
                    <VCol cols="12" md="4">
                      <VSelect
                        v-model="proposition.affiliate_id"
                        :label="$t('admin_produits_affiliate')"
                        variant="outlined"
                        prepend-inner-icon="tabler-user"
                        :items="[
                          { title: 'Affilié 1', value: '1' },
                          { title: 'Affilié 2', value: '2' },
                          { title: 'Affilié 3', value: '3' }
                        ]"
                        :rules="[v => !!v || $t('validation_required')]"
                      />
                    </VCol>
                    <VCol cols="12" md="3">
                      <VTextField
                        v-model.number="proposition.commission_percentage"
                        :label="$t('admin_produits_commission_percentage')"
                        variant="outlined"
                        type="number"
                        step="0.1"
                        min="0"
                        max="100"
                        suffix="%"
                        prepend-inner-icon="tabler-percentage"
                        :rules="[v => v >= 0 && v <= 100 || $t('admin_produits_commission_invalid')]"
                      />
                    </VCol>
                    <VCol cols="12" md="3">
                      <VTextField
                        v-model.number="proposition.commission_fixe"
                        :label="$t('admin_produits_commission_fixe')"
                        variant="outlined"
                        type="number"
                        step="0.01"
                        min="0"
                        suffix="DH"
                        prepend-inner-icon="tabler-currency-dirham"
                        hint="Commission fixe (optionnel)"
                      />
                    </VCol>
                    <VCol cols="12" md="2">
                      <div class="d-flex align-center h-100">
                        <VSwitch
                          v-model="proposition.actif"
                          :label="$t('common.active')"
                          color="primary"
                          hide-details
                        />
                      </div>
                    </VCol>
                  </VRow>

                  <VRow class="mt-3">
                    <VCol cols="12" md="4">
                      <VTextField
                        v-model="proposition.date_debut"
                        :label="$t('admin_produits_date_debut')"
                        variant="outlined"
                        type="date"
                        prepend-inner-icon="tabler-calendar"
                        :rules="[v => !!v || $t('validation_required')]"
                      />
                    </VCol>
                    <VCol cols="12" md="4">
                      <VTextField
                        v-model="proposition.date_fin"
                        :label="$t('admin_produits_date_fin')"
                        variant="outlined"
                        type="date"
                        prepend-inner-icon="tabler-calendar-off"
                        hint="Laissez vide pour pas de limite"
                      />
                    </VCol>
                    <VCol cols="12" md="4">
                      <VTextField
                        v-model="proposition.notes"
                        :label="$t('admin_produits_proposition_notes')"
                        variant="outlined"
                        placeholder="Notes sur cette proposition..."
                        prepend-inner-icon="tabler-note"
                      />
                    </VCol>
                  </VRow>

                  <!-- Commission Preview -->
                  <VDivider class="my-3" />
                  <VAlert type="info" variant="tonal">
                    <div class="d-flex align-center">
                      <VIcon icon="tabler-calculator" class="me-2" />
                      <div>
                        <strong>{{ $t('admin_produits_commission_preview') }}:</strong>
                        <span v-if="form.prix_vente && proposition.commission_percentage" class="ms-2">
                          {{ ((form.prix_vente * proposition.commission_percentage) / 100).toFixed(2) }} DH
                          <span v-if="proposition.commission_fixe"> + {{ proposition.commission_fixe }} DH</span>
                          = {{ ((form.prix_vente * proposition.commission_percentage) / 100 + (proposition.commission_fixe || 0)).toFixed(2) }} DH
                        </span>
                        <span v-else class="text-medium-emphasis">{{ $t('admin_produits_commission_preview_empty') }}</span>
                      </div>
                    </div>
                  </VAlert>
                </VCard>
              </VCol>
            </VRow>
          </VCardText>
        </VTabsWindowItem>

        <!-- SEO Tab -->
        <VTabsWindowItem value="seo">
          <VCardText>
            <div class="mb-4">
              <h3 class="text-h6 mb-1">{{ $t('admin_produits_seo') }}</h3>
              <p class="text-body-2 text-medium-emphasis">{{ $t('admin_produits_seo_help') }}</p>
            </div>

            <VRow>
              <VCol cols="12">
                <VTextField
                  :model-value="form.titre ? form.titre.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '') : ''"
                  :label="$t('admin_produits_slug')"
                  variant="outlined"
                  prepend-inner-icon="tabler-link"
                  readonly
                  hint="Auto-generated from product title"
                  persistent-hint
                />
              </VCol>

              <VCol cols="12">
                <VTextarea
                  v-model="metaDescription"
                  :label="$t('admin_produits_meta_description')"
                  :placeholder="$t('admin_produits_meta_description_placeholder')"
                  variant="outlined"
                  rows="3"
                  counter="160"
                  prepend-inner-icon="tabler-file-description"
                  hint="Recommended: 120-160 characters"
                  persistent-hint
                />
              </VCol>

              <VCol cols="12">
                <VTextField
                  v-model="metaKeywords"
                  :label="$t('admin_produits_meta_keywords')"
                  :placeholder="$t('admin_produits_meta_keywords_placeholder')"
                  variant="outlined"
                  prepend-inner-icon="tabler-tags"
                  hint="Separate keywords with commas"
                  persistent-hint
                />
              </VCol>
            </VRow>

            <!-- SEO Preview -->
            <VDivider class="my-6" />
            <div class="mb-4">
              <h4 class="text-subtitle-1 mb-3">{{ $t('admin_produits_seo_preview') }}</h4>
              <VCard variant="outlined" class="pa-4">
                <div class="seo-preview">
                  <h3 class="text-primary text-h6 mb-1">
                    {{ form.titre || $t('admin_produits_seo_preview_title') }}
                  </h3>
                  <p class="text-success text-body-2 mb-2">
                    {{ `example.com/products/${form.titre ? form.titre.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '') : 'product-name'}` }}
                  </p>
                  <p class="text-body-2">
                    {{ metaDescription || form.description?.substring(0, 160) || $t('admin_produits_seo_preview_description') }}
                  </p>
                </div>
              </VCard>
            </div>

            <!-- SEO Score -->
            <VAlert
              :type="getSeoScore() >= 80 ? 'success' : getSeoScore() >= 60 ? 'warning' : 'error'"
              variant="tonal"
              class="mb-4"
            >
              <div class="d-flex align-center">
                <VIcon :icon="getSeoScore() >= 80 ? 'tabler-check-circle' : getSeoScore() >= 60 ? 'tabler-alert-triangle' : 'tabler-x-circle'" class="me-2" />
                <div>
                  <strong>{{ $t('admin_produits_seo_score') }}: {{ getSeoScore() }}/100</strong>
                  <p class="mb-0 mt-1">{{ getSeoRecommendations() }}</p>
                </div>
              </div>
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

<style scoped>
.tabs-header {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 8px 8px 0 0;
}

.tab-item {
  font-weight: 500;
  text-transform: none;
  letter-spacing: 0.5px;
  transition: all 0.3s ease;
}

.tab-item:hover {
  background-color: rgba(255, 255, 255, 0.1);
}

.tab-text {
  font-size: 0.875rem;
}

.features-list {
  list-style: none;
  padding: 0;
}

.features-list li {
  display: flex;
  align-items: center;
  padding: 8px 0;
  border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}

.features-list li:last-child {
  border-bottom: none;
}

.video-preview {
  border-radius: 8px;
  overflow: hidden;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.video-preview iframe {
  border-radius: 8px;
}

.seo-preview {
  font-family: Arial, sans-serif;
  line-height: 1.4;
}

.seo-preview h3 {
  color: #1a0dab;
  text-decoration: underline;
  cursor: pointer;
  margin-bottom: 4px;
}

.seo-preview h3:hover {
  text-decoration: none;
}

/* Custom tab styling */
:deep(.v-tabs) {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
}

:deep(.v-tab) {
  color: rgba(255, 255, 255, 0.8) !important;
  font-weight: 500 !important;
  text-transform: none !important;
  letter-spacing: 0.5px !important;
  transition: all 0.3s ease !important;
}

:deep(.v-tab--selected) {
  color: white !important;
  background-color: rgba(255, 255, 255, 0.15) !important;
}

:deep(.v-tab:hover) {
  background-color: rgba(255, 255, 255, 0.1) !important;
}

:deep(.v-tabs-slider) {
  background-color: white !important;
  height: 3px !important;
}

/* Card improvements */
:deep(.v-card) {
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08) !important;
  border-radius: 12px !important;
}

:deep(.v-card-text) {
  padding: 24px !important;
}

/* Form improvements */
:deep(.v-text-field .v-field) {
  border-radius: 8px !important;
}

:deep(.v-textarea .v-field) {
  border-radius: 8px !important;
}

:deep(.v-select .v-field) {
  border-radius: 8px !important;
}

/* Button improvements */
:deep(.v-btn) {
  border-radius: 8px !important;
  text-transform: none !important;
  font-weight: 500 !important;
}

/* Alert improvements */
:deep(.v-alert) {
  border-radius: 8px !important;
}
</style>
