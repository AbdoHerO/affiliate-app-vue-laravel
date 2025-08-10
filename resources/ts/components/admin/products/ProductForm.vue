<script setup lang="ts">
import { ref, watch, onMounted, computed, reactive } from 'vue'
import { useProduitsStore, type Produit, type ProduitFormData } from '@/stores/admin/produits'
import { useBoutiquesStore } from '@/stores/admin/boutiques'
import { useCategoriesStore } from '@/stores/admin/categories'
import { storeToRefs } from 'pinia'
import { useRouter, useRoute } from 'vue-router'
import { useNotifications } from '@/composables/useNotifications'
import { useDebounceFn } from '@vueuse/core'

interface Props {
  mode: 'create' | 'edit'
  id?: string
}

interface ProduitProposition {
  id?: string
  titre: string
  description: string
  type: string
  statut: string
  image_url?: string
  auteur?: {
    id: string
    nom_complet: string
    email: string
  }
}

const props = defineProps<Props>()
const emit = defineEmits(['created', 'updated'])

const router = useRouter()
const route = useRoute()
const { showSuccess, showError } = useNotifications()

const produitsStore = useProduitsStore()
const boutiquesStore = useBoutiquesStore()
const categoriesStore = useCategoriesStore()

const { items: boutiques } = storeToRefs(boutiquesStore)
const { categories } = storeToRefs(categoriesStore)
const { currentProduit, images, videos, variantes } = storeToRefs(produitsStore)

// State
const activeTab = ref(route.query.tab as string || 'details')
const loading = ref(false)
const saving = ref(false)
const localId = ref<string | null>(props.mode === 'edit' ? (props.id || null) : null)

// Form data
const form = ref<ProduitFormData>({
  boutique_id: '',
  categorie_id: null,
  titre: '',
  description: '',
  prix_achat: null,
  prix_vente: null,
  prix_affilie: null,
  quantite_min: 1,
  notes_admin: '',
  actif: true,
})

// Propositions state
const propositions = ref<ProduitProposition[]>([])
const newProposition = reactive({
  titre: '',
  description: '',
  type: ''
})

// Video form state
const videoMode = ref<'url' | 'upload'>('url')
const newVideoTitle = ref('')
const newVideoUrl = ref('')

// Variant form state
const newVariant = reactive({
  nom: '',
  valeur: '',
  prix_vente_variante: null as number | null
})

// Computed
const readyForMedia = computed(() => !!localId.value)
const isEditMode = computed(() => props.mode === 'edit')
const pageTitle = computed(() => isEditMode.value ? 'Edit Product' : 'Create Product')

// Methods
const loadLookups = async () => {
  await Promise.all([
    boutiquesStore.fetchBoutiques(),
    categoriesStore.fetchCategories()
  ])
}

const loadProduct = async () => {
  if (props.mode === 'edit' && props.id) {
    loading.value = true
    try {
      await produitsStore.fetchProduit(props.id)
      if (currentProduit.value) {
        const p = currentProduit.value
        form.value = {
          boutique_id: p.boutique_id,
          categorie_id: p.categorie_id,
          titre: p.titre,
          description: p.description || '',
          prix_achat: p.prix_achat,
          prix_vente: p.prix_vente,
          prix_affilie: p.prix_affilie,
          quantite_min: (p as any).quantite_min || 1,
          notes_admin: (p as any).notes_admin || '',
          actif: p.actif,
        }
        localId.value = p.id
        await loadPropositions()
      }
    } catch (error) {
      showError('Failed to load product')
      console.error('Error loading product:', error)
    } finally {
      loading.value = false
    }
  }
}

const saveProduct = async () => {
  if (saving.value) return

  saving.value = true
  try {
    if (props.mode === 'create') {
      const created = await produitsStore.createProduit(form.value)
      localId.value = created.id
      await produitsStore.fetchProduit(created.id)
      emit('created', created)
      activeTab.value = 'images'
      router.replace({
        name: 'admin-produits-id-edit',
        params: { id: created.id },
        query: { tab: 'images' }
      })
      showSuccess('Product created successfully')
    } else if (localId.value) {
      const updated = await produitsStore.updateProduit(localId.value, form.value)
      emit('updated', updated)
      showSuccess('Product updated successfully')
    }
  } catch (error) {
    showError('Failed to save product')
    console.error('Error saving product:', error)
  } finally {
    saving.value = false
  }
}

const cancelEdit = () => {
  router.push({ name: 'admin-produits' })
}

// Propositions methods
const loadPropositions = async () => {
  if (!localId.value) return
  try {
    const { data, error } = await useApi(`/admin/produits/${localId.value}/propositions`)
    if (!error.value && data.value) {
      const response = data.value as any
      if (response.success) {
        propositions.value = response.data
      }
    }
  } catch (error) {
    console.error('Error loading propositions:', error)
  }
}

// IMAGE upload handlers (upload-only)
const handleImageUpload = async (files: FileList | File[]) => {
  if (!localId.value) return
  const fileList = Array.from(files)
  for (const file of fileList) {
    const fd = new FormData()
    fd.append('file', file)
    try {
      const { data, error } = await useApi(`/admin/produits/${localId.value}/images/upload`, {
        method: 'POST',
        body: fd
      })
      if (!error.value && data.value) {
        const response = data.value as any
        if (response.success) {
          images.value.push(response.data)
        }
      }
    } catch (error) {
      console.error('Error uploading image:', error)
      showError('Failed to upload image')
    }
  }
}

// VIDEO handlers (URL or upload)
const handleAddVideoUrl = async () => {
  if (!localId.value || !newVideoUrl.value) return
  try {
    const { data, error } = await useApi(`/admin/produits/${localId.value}/videos`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ url: newVideoUrl.value, titre: newVideoTitle.value })
    })
    if (!error.value && data.value) {
      const response = data.value as any
      if (response.success) {
        videos.value.push(response.data)
        newVideoUrl.value = ''
        newVideoTitle.value = ''
        showSuccess('Video added successfully')
      }
    }
  } catch (error) {
    console.error('Error adding video URL:', error)
    showError('Failed to add video')
  }
}
const handleAddVideoUpload = async (files: FileList | File[]) => {
  if (!localId.value) return
  const fileList = Array.from(files)
  for (const file of fileList) {
    const fd = new FormData()
    fd.append('file', file)
    try {
      const { data, error } = await useApi(`/admin/produits/${localId.value}/videos/upload`, {
        method: 'POST',
        body: fd
      })
      if (!error.value && data.value) {
        const response = data.value as any
        if (response.success) {
          videos.value.push(response.data)
          showSuccess('Video uploaded successfully')
        }
      }
    } catch (error) {
      console.error('Error uploading video:', error)
      showError('Failed to upload video')
    }
  }
}
const deleteVideo = async (id: string) => {
  if (!localId.value) return
  try {
    const { data, error } = await useApi(`/admin/produits/${localId.value}/videos/${id}`, {
      method: 'DELETE'
    })
    if (!error.value) {
      const idx = videos.value.findIndex(v => v.id === id)
      if (idx > -1) {
        videos.value.splice(idx, 1)
        showSuccess('Video deleted successfully')
      }
    }
  } catch (error) {
    console.error('Error deleting video:', error)
    showError('Failed to delete video')
  }
}

// VARIANTS
const addVariant = async () => {
  if (!localId.value || !newVariant.nom || !newVariant.valeur) return
  try {
    const { data, error } = await useApi(`/admin/produits/${localId.value}/variantes`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(newVariant)
    })
    if (!error.value && data.value) {
      const response = data.value as any
      if (response.success) {
        variantes.value.push(response.data)
        Object.assign(newVariant, { nom: '', valeur: '', prix_vente_variante: null })
        showSuccess('Variant added successfully')
      }
    }
  } catch (error) {
    console.error('Error adding variant:', error)
    showError('Failed to add variant')
  }
}
const deleteVariant = async (id: string) => {
  if (!localId.value) return
  try {
    const { error } = await useApi(`/admin/produits/${localId.value}/variantes/${id}`, {
      method: 'DELETE'
    })
    if (!error.value) {
      const idx = variantes.value.findIndex(v => v.id === id)
      if (idx > -1) {
        variantes.value.splice(idx, 1)
        showSuccess('Variant deleted successfully')
      }
    }
  } catch (error) {
    console.error('Error deleting variant:', error)
    showError('Failed to delete variant')
  }
}
const uploadVariantImage = async (id: string, file: File) => {
  if (!localId.value || !file) return
  const fd = new FormData()
  fd.append('file', file)
  try {
    const { data, error } = await useApi(`/admin/produits/${localId.value}/variantes/${id}/image`, {
      method: 'POST',
      body: fd
    })
    if (!error.value && data.value) {
      const response = data.value as any
      if (response.success) {
        const variant: any = variantes.value.find(v => v.id === id)
        if (variant) {
          variant.image_url = response.data.image_url
          showSuccess('Variant image uploaded successfully')
        }
      }
    }
  } catch (error) {
    console.error('Error uploading variant image:', error)
    showError('Failed to upload variant image')
  }
}

// Proposition methods
const addProposition = async (propositionData: { titre: string; description: string; type: string }) => {
  if (!localId.value) return
  try {
    const { data, error } = await useApi(`/admin/produits/${localId.value}/propositions`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(propositionData)
    })
    if (!error.value && data.value) {
      const response = data.value as any
      if (response.success) {
        propositions.value.push(response.data)
        showSuccess('Proposition added successfully')
        return response.data
      }
    }
  } catch (error) {
    console.error('Error adding proposition:', error)
    showError('Failed to add proposition')
  }
}

const updateProposition = async (propositionId: string, propositionData: any) => {
  if (!localId.value) return
  try {
    const { data, error } = await useApi(`/admin/produits/${localId.value}/propositions/${propositionId}`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(propositionData)
    })
    if (!error.value && data.value) {
      const response = data.value as any
      if (response.success) {
        const idx = propositions.value.findIndex(p => p.id === propositionId)
        if (idx > -1) {
          propositions.value[idx] = response.data
        }
        showSuccess('Proposition updated successfully')
        return response.data
      }
    }
  } catch (error) {
    console.error('Error updating proposition:', error)
    showError('Failed to update proposition')
  }
}

const deleteProposition = async (propositionId: string) => {
  if (!localId.value) return
  try {
    const { error } = await useApi(`/admin/produits/${localId.value}/propositions/${propositionId}`, {
      method: 'DELETE'
    })
    if (!error.value) {
      const idx = propositions.value.findIndex(p => p.id === propositionId)
      if (idx > -1) {
        propositions.value.splice(idx, 1)
        showSuccess('Proposition deleted successfully')
      }
    }
  } catch (error) {
    console.error('Error deleting proposition:', error)
    showError('Failed to delete proposition')
  }
}

const uploadPropositionImage = async (propositionId: string, file: File) => {
  if (!localId.value || !file) return
  const fd = new FormData()
  fd.append('file', file)
  try {
    const { data, error } = await useApi(`/admin/produits/${localId.value}/propositions/${propositionId}/image`, {
      method: 'POST',
      body: fd
    })
    if (!error.value && data.value) {
      const response = data.value as any
      if (response.success) {
        const proposition = propositions.value.find(p => p.id === propositionId)
        if (proposition) {
          proposition.image_url = response.data.image_url
          showSuccess('Proposition image uploaded successfully')
        }
      }
    }
  } catch (error) {
    console.error('Error uploading proposition image:', error)
    showError('Failed to upload proposition image')
  }
}

// Proposition handlers
const handleAddProposition = async () => {
  if (!newProposition.titre || !newProposition.description || !newProposition.type) return
  const result = await addProposition(newProposition)
  if (result) {
    Object.assign(newProposition, { titre: '', description: '', type: '' })
  }
}

const handleDeleteProposition = async (propositionId: string) => {
  await deleteProposition(propositionId)
}

const handleUploadPropositionImage = (propositionId: string) => {
  const input = document.createElement('input')
  input.type = 'file'
  input.accept = 'image/*'
  input.onchange = async (e) => {
    const file = (e.target as HTMLInputElement).files?.[0]
    if (file) {
      await uploadPropositionImage(propositionId, file)
    }
  }
  input.click()
}

const getPropositionStatusColor = (statut: string) => {
  switch (statut) {
    case 'en_attente': return 'warning'
    case 'approuve': return 'success'
    case 'refuse': return 'error'
    default: return 'grey'
  }
}

// Lifecycle
onMounted(async () => {
  await loadLookups()
  await loadProduct()
})
</script>

<template>
  <div class="product-form">
    <!-- Header -->
    <VCard class="mb-6">
      <VCardTitle class="d-flex align-center justify-space-between pa-6">
        <div>
          <h1 class="text-h4 font-weight-bold">{{ pageTitle }}</h1>
          <p v-if="isEditMode && currentProduit" class="text-body-1 text-medium-emphasis mt-1">
            {{ currentProduit.titre }}
          </p>
        </div>
        <VChip
          :color="isEditMode ? 'primary' : 'success'"
          variant="tonal"
          size="large"
        >
          {{ isEditMode ? 'Edit Mode' : 'Create Mode' }}
        </VChip>
      </VCardTitle>
    </VCard>

    <!-- Main Form -->
    <VCard>
      <!-- Tabs -->
      <VTabs
        v-model="activeTab"
        bg-color="grey-lighten-4"
        color="primary"
        grow
      >
        <VTab value="details">
          <VIcon icon="tabler-info-circle" class="me-2" />
          Details
        </VTab>
        <VTab :disabled="!readyForMedia" value="images">
          <VIcon icon="tabler-photo" class="me-2" />
          Images
        </VTab>
        <VTab :disabled="!readyForMedia" value="videos">
          <VIcon icon="tabler-video" class="me-2" />
          Videos
        </VTab>
        <VTab :disabled="!readyForMedia" value="variants">
          <VIcon icon="tabler-versions" class="me-2" />
          Variants
        </VTab>
        <VTab :disabled="!readyForMedia" value="propositions">
          <VIcon icon="tabler-list-details" class="me-2" />
          Propositions
        </VTab>
      </VTabs>

      <!-- Tab Content -->
      <VTabsWindow v-model="activeTab" class="pa-6">
        <!-- Details Tab -->
        <VTabsWindowItem value="details">
          <VForm @submit.prevent="saveProduct">
            <VRow>
              <VCol cols="12" md="6">
                <VSelect
                  v-model="form.boutique_id"
                  :items="boutiques"
                  item-title="nom"
                  item-value="id"
                  label="Boutique"
                  variant="outlined"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VSelect
                  v-model="form.categorie_id"
                  :items="categories"
                  item-title="nom"
                  item-value="id"
                  label="Categorie"
                  variant="outlined"
                  clearable
                />
              </VCol>
              <VCol cols="12">
                <VTextField
                  v-model="form.titre"
                  label="Product Title"
                  variant="outlined"
                  required
                />
              </VCol>
              <VCol cols="12">
                <VTextarea
                  v-model="form.description"
                  label="Description"
                  variant="outlined"
                  rows="4"
                />
              </VCol>
              <VCol cols="12" md="4">
                <VTextField
                  v-model.number="form.prix_achat"
                  label="Purchase Price"
                  type="number"
                  variant="outlined"
                  step="0.01"
                  suffix="MAD"
                />
              </VCol>
              <VCol cols="12" md="4">
                <VTextField
                  v-model.number="form.prix_vente"
                  label="Sale Price"
                  type="number"
                  variant="outlined"
                  step="0.01"
                  suffix="MAD"
                  required
                />
              </VCol>
              <VCol cols="12" md="4">
                <VTextField
                  v-model.number="form.prix_affilie"
                  label="Affiliate Price"
                  type="number"
                  variant="outlined"
                  step="0.01"
                  suffix="MAD"
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model.number="form.quantite_min"
                  label="Minimum Quantity"
                  type="number"
                  variant="outlined"
                  min="1"
                />
              </VCol>
              <VCol cols="12" md="6">
                <VSwitch
                  v-model="form.actif"
                  label="Active"
                  color="success"
                />
              </VCol>
              <VCol cols="12">
                <VTextarea
                  v-model="form.notes_admin"
                  label="Admin Notes"
                  variant="outlined"
                  rows="3"
                />
              </VCol>
            </VRow>
          </VForm>
        </VTabsWindowItem>
        <!-- Images Tab -->
        <VTabsWindowItem value="images">
          <div v-if="!readyForMedia" class="text-center py-12">
            <VIcon icon="tabler-photo-off" size="64" color="grey-lighten-1" class="mb-4" />
            <h3 class="text-h6 mb-2">Save Product First</h3>
            <p class="text-body-2 text-medium-emphasis">
              You need to save the product details before adding images.
            </p>
          </div>
          <div v-else>
            <div class="mb-6">
              <h3 class="text-h6 mb-2">Product Images</h3>
              <p class="text-body-2 text-medium-emphasis">
                Upload multiple images for this product. Drag and drop or click to select files.
              </p>
            </div>

            <VFileInput
              multiple
              show-size
              label="Upload Images"
              accept="image/*"
              variant="outlined"
              prepend-icon="tabler-upload"
              @change="(e: any) => handleImageUpload(e.target?.files || e)"
            />

            <VRow v-if="images.length" class="mt-6">
              <VCol v-for="img in images" :key="img.id" cols="6" md="3" lg="2">
                <VCard>
                  <VImg :src="img.url" aspect-ratio="1" cover />
                  <VCardActions>
                    <VSpacer />
                    <VBtn
                      icon="tabler-trash"
                      size="small"
                      color="error"
                      variant="text"
                      @click="() => {/* TODO: delete image */}"
                    />
                  </VCardActions>
                </VCard>
              </VCol>
            </VRow>

            <VAlert v-if="!images.length" type="info" variant="tonal" class="mt-6">
              No images uploaded yet. Add some images to showcase your product.
            </VAlert>
          </div>
        </VTabsWindowItem>

        <!-- Videos Tab -->
        <VTabsWindowItem value="videos">
          <div v-if="!readyForMedia" class="text-center py-12">
            <VIcon icon="tabler-video-off" size="64" color="grey-lighten-1" class="mb-4" />
            <h3 class="text-h6 mb-2">Save Product First</h3>
            <p class="text-body-2 text-medium-emphasis">
              You need to save the product details before adding videos.
            </p>
          </div>
          <div v-else>
            <div class="mb-6">
              <h3 class="text-h6 mb-2">Product Videos</h3>
              <p class="text-body-2 text-medium-emphasis">
                Add videos by URL (YouTube, Vimeo) or upload video files directly.
              </p>
            </div>

            <VBtnToggle v-model="videoMode" mandatory class="mb-6" color="primary">
              <VBtn value="url">
                <VIcon icon="tabler-link" class="me-2" />
                URL
              </VBtn>
              <VBtn value="upload">
                <VIcon icon="tabler-upload" class="me-2" />
                Upload
              </VBtn>
            </VBtnToggle>

            <div v-if="videoMode === 'url'" class="mb-6">
              <VRow>
                <VCol cols="12" md="6">
                  <VTextField
                    v-model="newVideoTitle"
                    label="Video Title"
                    variant="outlined"
                    placeholder="Enter video title"
                  />
                </VCol>
                <VCol cols="12" md="6">
                  <VTextField
                    v-model="newVideoUrl"
                    label="Video URL"
                    variant="outlined"
                    placeholder="https://youtube.com/watch?v=..."
                  />
                </VCol>
                <VCol cols="12">
                  <VBtn
                    color="primary"
                    prepend-icon="tabler-plus"
                    @click="handleAddVideoUrl"
                  >
                    Add Video URL
                  </VBtn>
                </VCol>
              </VRow>
            </div>

            <div v-else class="mb-6">
              <VFileInput
                multiple
                show-size
                label="Upload Videos"
                accept="video/*"
                variant="outlined"
                prepend-icon="tabler-upload"
                @change="(e: any) => handleAddVideoUpload(e.target?.files || e)"
              />
            </div>

            <div v-if="videos.length">
              <h4 class="text-subtitle-1 mb-4">Uploaded Videos</h4>
              <VList>
                <VListItem v-for="v in videos" :key="v.id">
                  <VListItemTitle>{{ v.titre || 'Untitled Video' }}</VListItemTitle>
                  <VListItemSubtitle>{{ v.url }}</VListItemSubtitle>
                  <template #append>
                    <VBtn
                      icon="tabler-trash"
                      size="small"
                      color="error"
                      variant="text"
                      @click="deleteVideo(v.id)"
                    />
                  </template>
                </VListItem>
              </VList>
            </div>

            <VAlert v-else type="info" variant="tonal">
              No videos added yet. Add some videos to showcase your product in action.
            </VAlert>
          </div>
        </VTabsWindowItem>

        <!-- Variants Tab -->
        <VTabsWindowItem value="variants">
          <div v-if="!readyForMedia" class="text-center py-12">
            <VIcon icon="tabler-versions-off" size="64" color="grey-lighten-1" class="mb-4" />
            <h3 class="text-h6 mb-2">Save Product First</h3>
            <p class="text-body-2 text-medium-emphasis">
              You need to save the product details before adding variants.
            </p>
          </div>
          <div v-else>
            <div class="mb-6">
              <h3 class="text-h6 mb-2">Product Variants</h3>
              <p class="text-body-2 text-medium-emphasis">
                Create different variants of your product (size, color, etc.) with optional images.
              </p>
            </div>

            <VCard variant="outlined" class="mb-6">
              <VCardTitle>Add New Variant</VCardTitle>
              <VCardText>
                <VRow>
                  <VCol cols="12" md="3">
                    <VTextField
                      v-model="newVariant.nom"
                      label="Variant Name"
                      variant="outlined"
                      placeholder="e.g., Size, Color"
                    />
                  </VCol>
                  <VCol cols="12" md="3">
                    <VTextField
                      v-model="newVariant.valeur"
                      label="Variant Value"
                      variant="outlined"
                      placeholder="e.g., Large, Red"
                    />
                  </VCol>
                  <VCol cols="12" md="3">
                    <VTextField
                      v-model.number="newVariant.prix_vente_variante"
                      label="Price Override"
                      type="number"
                      variant="outlined"
                      step="0.01"
                      suffix="MAD"
                      placeholder="Optional"
                    />
                  </VCol>
                  <VCol cols="12" md="3" class="d-flex align-end">
                    <VBtn
                      color="primary"
                      block
                      @click="addVariant"
                    >
                      Add Variant
                    </VBtn>
                  </VCol>
                </VRow>
              </VCardText>
            </VCard>

            <div v-if="variantes.length">
              <h4 class="text-subtitle-1 mb-4">Product Variants</h4>
              <VDataTable
                :headers="[
                  { title: 'Name', key: 'nom' },
                  { title: 'Value', key: 'valeur' },
                  { title: 'Price', key: 'prix_vente_variante' },
                  { title: 'Image', key: 'image', sortable: false },
                  { title: 'Actions', key: 'actions', sortable: false }
                ]"
                :items="variantes"
                class="elevation-1"
              >
                <template #item.prix_vente_variante="{ item }">
                  {{ item.prix_vente_variante ? `${item.prix_vente_variante} MAD` : 'Default' }}
                </template>
                <template #item.image="{ item }">
                  <div class="d-flex align-center gap-2">
                    <VImg
                      v-if="(item as any).image_url"
                      :src="(item as any).image_url"
                      width="40"
                      height="40"
                      cover
                      class="rounded"
                    />
                    <VFileInput
                      density="compact"
                      hide-details
                      accept="image/*"
                      variant="plain"
                      @change="(e: any) => uploadVariantImage(item.id, e.target?.files?.[0])"
                    />
                  </div>
                </template>
                <template #item.actions="{ item }">
                  <VBtn
                    icon="tabler-trash"
                    size="small"
                    color="error"
                    variant="text"
                    @click="deleteVariant(item.id)"
                  />
                </template>
              </VDataTable>
            </div>

            <VAlert v-else type="info" variant="tonal">
              No variants created yet. Add variants to offer different options for your product.
            </VAlert>
          </div>
        </VTabsWindowItem>

        <!-- Propositions Tab -->
        <VTabsWindowItem value="propositions">
          <div v-if="!readyForMedia" class="text-center py-12">
            <VIcon icon="tabler-list-details-off" size="64" color="grey-lighten-1" class="mb-4" />
            <h3 class="text-h6 mb-2">Save Product First</h3>
            <p class="text-body-2 text-medium-emphasis">
              You need to save the product details before adding propositions.
            </p>
          </div>
          <div v-else>
            <div class="mb-6">
              <h3 class="text-h6 mb-2">Product Propositions</h3>
              <p class="text-body-2 text-medium-emphasis">
                Create marketing propositions and selling points for your product.
              </p>
            </div>

            <!-- Add New Proposition Form -->
            <VCard class="mb-6">
              <VCardTitle>Add New Proposition</VCardTitle>
              <VCardText>
                <VForm @submit.prevent="handleAddProposition">
                  <VRow>
                    <VCol cols="12" md="6">
                      <VTextField
                        v-model="newProposition.titre"
                        label="Title"
                        variant="outlined"
                        required
                      />
                    </VCol>
                    <VCol cols="12" md="6">
                      <VSelect
                        v-model="newProposition.type"
                        :items="[
                          { title: 'New', value: 'nouveau' },
                          { title: 'Modification', value: 'modification' },
                          { title: 'Deletion', value: 'suppression' }
                        ]"
                        label="Type"
                        variant="outlined"
                        required
                      />
                    </VCol>
                    <VCol cols="12">
                      <VTextarea
                        v-model="newProposition.description"
                        label="Description"
                        variant="outlined"
                        rows="3"
                        required
                      />
                    </VCol>
                    <VCol cols="12">
                      <VBtn
                        type="submit"
                        color="primary"
                        :disabled="!newProposition.titre || !newProposition.description || !newProposition.type"
                      >
                        Add Proposition
                      </VBtn>
                    </VCol>
                  </VRow>
                </VForm>
              </VCardText>
            </VCard>

            <!-- Propositions List -->
            <VCard v-if="propositions.length > 0">
              <VCardTitle>Existing Propositions</VCardTitle>
              <VCardText>
                <VRow>
                  <VCol
                    v-for="proposition in propositions"
                    :key="proposition.id"
                    cols="12"
                    md="6"
                  >
                    <VCard variant="outlined" class="h-100">
                      <VCardTitle class="d-flex align-center justify-space-between">
                        <span>{{ proposition.titre }}</span>
                        <VChip
                          :color="getPropositionStatusColor(proposition.statut)"
                          size="small"
                        >
                          {{ proposition.statut }}
                        </VChip>
                      </VCardTitle>
                      <VCardText>
                        <p class="mb-2">{{ proposition.description }}</p>
                        <VChip size="small" variant="outlined" class="mb-2">
                          {{ proposition.type }}
                        </VChip>
                        <div v-if="proposition.image_url" class="mt-2">
                          <VImg
                            :src="proposition.image_url"
                            height="100"
                            class="rounded"
                          />
                        </div>
                      </VCardText>
                      <VCardActions>
                        <VBtn
                          size="small"
                          variant="outlined"
                          :disabled="!proposition.id"
                          @click="proposition.id && handleUploadPropositionImage(proposition.id)"
                        >
                          {{ proposition.image_url ? 'Change Image' : 'Add Image' }}
                        </VBtn>
                        <VSpacer />
                        <VBtn
                          size="small"
                          color="error"
                          variant="outlined"
                          :disabled="!proposition.id"
                          @click="proposition.id && handleDeleteProposition(proposition.id)"
                        >
                          Delete
                        </VBtn>
                      </VCardActions>
                    </VCard>
                  </VCol>
                </VRow>
              </VCardText>
            </VCard>

            <!-- Empty State -->
            <VAlert v-else type="info" variant="tonal">
              No propositions created yet. Add propositions to create compelling selling points for your product.
            </VAlert>
          </div>
        </VTabsWindowItem>
      </VTabsWindow>
    </VCard>

    <!-- Sticky Footer -->
    <VCard class="sticky-footer mt-6">
      <VCardActions class="pa-6">
        <VSpacer />
        <VBtn
          variant="outlined"
          size="large"
          @click="cancelEdit"
        >
          Cancel
        </VBtn>
        <VBtn
          color="primary"
          size="large"
          :loading="saving"
          @click="saveProduct"
        >
          {{ isEditMode ? 'Update Product' : 'Create Product' }}
        </VBtn>
      </VCardActions>
    </VCard>
  </div>
</template>

<style scoped>
.product-form {
  padding-bottom: 120px; /* Space for sticky footer */
}

.sticky-footer {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  z-index: 1000;
  border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  backdrop-filter: blur(10px);
  background: rgba(255, 255, 255, 0.95);
}

.v-theme--dark .sticky-footer {
  background: rgba(33, 33, 33, 0.95);
}

:deep(.v-tabs) {
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

:deep(.v-tab) {
  text-transform: none;
  font-weight: 500;
}

:deep(.v-card) {
  border-radius: 12px;
}

:deep(.v-text-field .v-field),
:deep(.v-textarea .v-field),
:deep(.v-select .v-field),
:deep(.v-file-input .v-field) {
  border-radius: 8px;
}
</style>

<style scoped>
</style>
