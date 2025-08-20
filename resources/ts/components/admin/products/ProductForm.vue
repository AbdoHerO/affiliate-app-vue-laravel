<script setup lang="ts">
import { ref, watch, onMounted, computed, reactive, nextTick } from 'vue'
import { useProduitsStore, type Produit, type ProduitFormData, type ProduitRupture } from '@/stores/admin/produits'
import { useBoutiquesStore } from '@/stores/admin/boutiques'
import { useCategoriesStore } from '@/stores/admin/categories'
import { storeToRefs } from 'pinia'
import { useRouter, useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useNotifications } from '@/composables/useNotifications'
import { useFormErrors } from '@/composables/useFormErrors'
import { useQuickConfirm } from '@/composables/useConfirmAction'
import { useDebounceFn } from '@vueuse/core'
import { useApi } from '@/composables/useApi'

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
const { t } = useI18n()
const { showSuccess, showError, snackbar } = useNotifications()
const { confirmCreate, confirmUpdate, confirmDelete } = useQuickConfirm()

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
  copywriting: '',
  prix_achat: null,
  prix_vente: null,
  prix_affilie: null,
  quantite_min: 1,
  notes_admin: '',
  actif: true,
  rating_value: null,
  stock_total: null,
})

// Form errors handling
const { errors: productErrors, set: setProductErrors, clear: clearProductErrors } = useFormErrors<typeof form.value>()

// Propositions state
const propositions = ref<ProduitProposition[]>([])
const newProposition = reactive({
  titre: '',
  description: '',
  type: ''
})

// Ruptures state
const ruptures = ref<ProduitRupture[]>([])
const newRupture = reactive({
  variante_id: null as string | null,
  motif: '',
  started_at: '',
  expected_restock_at: ''
})
const ruptureValidationErrors = reactive({
  variante_id: [] as string[],
  motif: [] as string[],
  started_at: [] as string[],
  expected_restock_at: [] as string[]
})

// Video form state
const videoMode = ref<'url' | 'upload'>('url')
const newVideoTitle = ref('')
const newVideoUrl = ref('')

// Variant form state
const newVariant = reactive({
  attribut_id: '',
  valeur_id: '',
  valeur_ids: [] as string[], // For multi-select
  nom: '',
  valeur: '',
  prix_vente_variante: null as number | null
})

// Multi-select mode toggle
const isMultiSelectMode = ref(false)

// Variant catalog state
const availableAttributes = ref<any[]>([])
const availableValues = ref<any[]>([])
const loadingAttributes = ref(false)
const loadingValues = ref(false)

// Stock management state
const stockForm = reactive({
  stock_total: 0,
  variant_stocks: [] as Array<{ variante_id: string; qty: number; reserved?: number }>
})

// Stock computed properties
const sizeVariants = computed(() => {
  return variantes.value.filter(variant => {
    // Check the nom field which contains the attribute name
    const attributeName = variant.nom?.toLowerCase() || ''
    return ['taille', 'size'].includes(attributeName)
  })
})

const totalVariantStock = computed(() => {
  return stockForm.variant_stocks.reduce((sum, stock) => sum + (stock.qty || 0), 0)
})

const totalReservedStock = computed(() => {
  return stockForm.variant_stocks.reduce((sum, stock) => sum + (stock.reserved || 0), 0)
})

const totalAvailableStock = computed(() => {
  return totalVariantStock.value - totalReservedStock.value
})

const stockMismatch = computed(() => {
  return stockForm.stock_total !== totalVariantStock.value
})

// Computed
const readyForMedia = computed(() => !!localId.value)
const isEditMode = computed(() => props.mode === 'edit')
const pageTitle = computed(() => isEditMode.value ? 'Edit Product' : 'Create Product')

// Helper function to get variant display name
const getVariantDisplayName = (variantId: string | null | undefined) => {
  console.debug('[ProductForm] getVariantDisplayName called with:', variantId)
  console.debug('[ProductForm] Available variants:', variantes.value)

  if (!variantId) return 'All variants'
  const variant = variantes.value.find(v => v.id === variantId)
  console.debug('[ProductForm] Found variant:', variant)

  if (!variant) return 'Unknown variant'
  return `${variant.nom || 'Unknown'}: ${variant.valeur || 'Unknown'}`
}

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
          copywriting: (p as any).copywriting || '',
          prix_achat: p.prix_achat,
          prix_vente: p.prix_vente,
          prix_affilie: p.prix_affilie,
          quantite_min: (p as any).quantite_min || 1,
          notes_admin: (p as any).notes_admin || '',
          actif: p.actif,
          rating_value: (p as any).rating_value || null,
          stock_total: (p as any).stock_total || null,
        }

        // Initialize stock form after loading product
        nextTick(() => {
          initializeStockForm()
        })
        localId.value = p.id
        console.debug('[ProductForm] Edit mode - variants loaded:', variantes.value.map(v => ({ id: v.id, nom: v.nom, valeur: v.valeur, image_url: v.image_url })))

        // Sync rich text editor with loaded copywriting content
        await syncRichTextEditor()

        await loadPropositions()
        await loadRuptures()
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
  console.debug('[ProductForm] saveProduct called, mode:', props.mode, 'localId:', localId.value)

  // Prevent double-click/double-submit
  if (saving.value) {
    console.debug('[ProductForm] Save already in progress, ignoring')
    return
  }

  // Basic validation
  if (!form.value.titre.trim()) {
    showError('Product title is required')
    return
  }

  if (!form.value.boutique_id) {
    showError('Boutique selection is required')
    return
  }

  try {
    // Show confirm dialog before saving
    console.debug('[ProductForm] Showing confirm dialog...')
    const confirmed = props.mode === 'create' && !localId.value
      ? await confirmCreate('product')
      : await confirmUpdate('product', form.value.titre)

    console.debug('[ProductForm] Confirm dialog result:', confirmed)
    if (!confirmed) return
  } catch (error) {
    console.error('[ProductForm] Error in confirm dialog:', error)
    return
  }

  saving.value = true
  try {
    console.debug('[ProductForm] Form data before save:', form.value)

    if (props.mode === 'create' && !localId.value) {
      // Create new product
      console.debug('[ProductForm] Creating new product')
      const created = await produitsStore.createProduit(form.value)
      localId.value = created.id
      console.debug('[ProductForm] Created product with ID:', created.id)

      // Fetch full product data with relations
      await produitsStore.fetchProduit(created.id)
      console.debug('[ProductForm] Fetched product data, images:', images.value.length, 'videos:', videos.value.length, 'variants:', variantes.value.length)
      console.debug('[ProductForm] Variants with images:', variantes.value.map(v => ({ id: v.id, nom: v.nom, valeur: v.valeur, image_url: v.image_url })))

      // Switch to edit mode but stay on same component
      emit('created', created)
      activeTab.value = 'images'

      // Update URL without remounting component
      router.replace(`/admin/produits/${created.id}/edit?tab=images`)

      clearProductErrors()
      showSuccess('Product created successfully')
    } else if (localId.value) {
      // Update existing product
      console.debug('[ProductForm] Updating product with ID:', localId.value)
      const updated = await produitsStore.updateProduit(localId.value, form.value)
      emit('updated', updated)
      clearProductErrors()
      showSuccess('Product updated successfully')

      // Handle stock allocation if there are changes
      await handleStockAllocation()
    } else {
      console.error('[ProductForm] Invalid state: create mode but no localId and no create action')
      showError('Invalid form state')
    }
  } catch (error: any) {
    // Handle validation errors and other API errors
    if (error.errors) {
      setProductErrors(error.errors)
      showError(error.message || 'Validation failed')
      console.error('Product validation error:', error)
    } else {
      showError(error.message || 'Failed to save product')
      console.error('Error saving product:', error)
    }
  } finally {
    saving.value = false
  }
}

const cancelEdit = () => {
  router.push({ name: 'admin-produits' })
}

// Rating methods
const clampRatingValue = () => {
  if (form.value.rating_value !== null && form.value.rating_value !== undefined) {
    form.value.rating_value = Math.max(0, Math.min(5, Number(form.value.rating_value)))
    form.value.rating_value = Math.round(form.value.rating_value * 10) / 10 // Round to 1 decimal place
  }
}

// Rich text editor methods
const richTextEditor = ref<HTMLElement>()

// Watch for form.copywriting changes to update the rich text editor
watch(() => form.value.copywriting, async (newValue) => {
  await nextTick()
  if (richTextEditor.value && richTextEditor.value.innerHTML !== newValue) {
    richTextEditor.value.innerHTML = newValue || ''
  }
}, { immediate: true })

const formatText = (command: string) => {
  document.execCommand(command, false, '')
  richTextEditor.value?.focus()
}

const isFormatActive = (command: string): boolean => {
  return document.queryCommandState(command)
}

const changeFontSize = (size: 'small' | 'normal' | 'large') => {
  const sizeMap = {
    small: '12px',
    normal: '14px',
    large: '18px'
  }
  document.execCommand('fontSize', false, '7')
  const fontElements = document.getElementsByTagName('font')
  for (let i = 0; i < fontElements.length; i++) {
    if (fontElements[i].size === '7') {
      fontElements[i].removeAttribute('size')
      fontElements[i].style.fontSize = sizeMap[size]
    }
  }
  richTextEditor.value?.focus()
}

const handleRichTextInput = (event: Event) => {
  const target = event.target as HTMLElement
  form.value.copywriting = target.innerHTML
}

// Sync rich text editor content with form data
const syncRichTextEditor = async () => {
  await nextTick()
  if (richTextEditor.value) {
    richTextEditor.value.innerHTML = form.value.copywriting || ''
  }
}

const handleKeyDown = (event: KeyboardEvent) => {
  // Handle Ctrl+B for bold
  if (event.ctrlKey && event.key === 'b') {
    event.preventDefault()
    formatText('bold')
  }
  // Handle Ctrl+I for italic
  if (event.ctrlKey && event.key === 'i') {
    event.preventDefault()
    formatText('italic')
  }
  // Handle Ctrl+U for underline
  if (event.ctrlKey && event.key === 'u') {
    event.preventDefault()
    formatText('underline')
  }
}

// Copywriting formatting method (for display)
const formatCopywriting = (text: string): string => {
  if (!text) return ''

  return text
    // Convert line breaks to <br> tags
    .replace(/\n/g, '<br>')
    // Convert **text** to bold
    .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
    // Convert *text* to italic
    .replace(/\*(.*?)\*/g, '<em>$1</em>')
    // Preserve emojis and special characters
    .trim()
}

const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleDateString('fr-FR', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
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
  if (!localId.value) {
    console.warn('[ProductForm] Cannot upload images: no product ID')
    return
  }
  console.debug('[ProductForm] [productId] add-image:', localId.value)
  const fileList = Array.from(files)
  for (const file of fileList) {
    const fd = new FormData()
    fd.append('file', file)
    try {
      const { data, error: apiError } = await useApi(`/admin/produits/${localId.value}/images/upload`, {
        method: 'POST',
        body: fd
      })

      if (apiError.value) {
        console.error('Image upload error details:', apiError.value)
        showError(apiError.value.message || 'Failed to upload image')
        console.error('Image upload error:', apiError.value)
      } else if (data.value) {
        const response = data.value as any
        if (response.success) {
          images.value.push(response.data)
          console.debug('[ProductForm] Image uploaded successfully, total images:', images.value.length)
        }
      }
    } catch (error: any) {
      console.error('Error uploading image:', error)
      showError(error.message || 'Failed to upload image')
    }
  }
}

const handleDeleteImage = async (imageId: string) => {
  if (!localId.value) return

  // Show confirm dialog before deleting
  const confirmed = await confirmDelete('image', 'cette image')
  if (!confirmed) return

  try {
    const { error: apiError } = await useApi(`/admin/produits/${localId.value}/images/${imageId}`, {
      method: 'DELETE'
    })

    if (apiError.value) {
      showError(apiError.value.message || 'Failed to delete image')
      console.error('Delete image error:', apiError.value)
    } else {
      const idx = images.value.findIndex(img => img.id === imageId)
      if (idx > -1) {
        images.value.splice(idx, 1)
        showSuccess('Image deleted successfully')
      }
    }
  } catch (error: any) {
    console.error('Error deleting image:', error)
    showError(error.message || 'Failed to delete image')
  }
}

// VIDEO handlers (URL or upload)
const handleAddVideoUrl = async () => {
  if (!localId.value || !newVideoUrl.value) {
    console.warn('[ProductForm] Cannot add video URL: missing product ID or URL')
    return
  }

  // Show confirm dialog before adding
  const confirmed = await confirmCreate('vidéo')
  if (!confirmed) return

  console.debug('[ProductForm] [productId] add-video-url:', localId.value)
  try {
    const { data, error: apiError } = await useApi(`/admin/produits/${localId.value}/videos`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ url: newVideoUrl.value, titre: newVideoTitle.value })
    })

    if (apiError.value) {
      showError(apiError.value.message || 'Failed to add video')
      console.error('Add video URL error:', apiError.value)
    } else if (data.value) {
      const response = data.value as any
      if (response.success) {
        videos.value.push(response.data)
        newVideoUrl.value = ''
        newVideoTitle.value = ''
        showSuccess('Video added successfully')
      }
    }
  } catch (error: any) {
    console.error('Error adding video URL:', error)
    showError(error.message || 'Failed to add video')
  }
}
const handleAddVideoUpload = async (files: FileList | File[]) => {
  if (!localId.value) {
    console.warn('[ProductForm] Cannot upload videos: no product ID')
    return
  }
  console.debug('[ProductForm] [productId] add-video-upload:', localId.value)
  const fileList = Array.from(files)
  for (const file of fileList) {
    const fd = new FormData()
    fd.append('file', file)
    try {
      const { data, error: apiError } = await useApi(`/admin/produits/${localId.value}/videos/upload`, {
        method: 'POST',
        body: fd
      })

      if (apiError.value) {
        showError(apiError.value.message || 'Failed to upload video')
        console.error('Video upload error:', apiError.value)
      } else if (data.value) {
        const response = data.value as any
        if (response.success) {
          videos.value.push(response.data)
          showSuccess('Video uploaded successfully')
        }
      }
    } catch (error: any) {
      console.error('Error uploading video:', error)
      showError(error.message || 'Failed to upload video')
    }
  }
}
const deleteVideo = async (id: string) => {
  if (!localId.value) return

  // Show confirm dialog before deleting
  const confirmed = await confirmDelete('vidéo', 'cette vidéo')
  if (!confirmed) return

  try {
    const { error: apiError } = await useApi(`/admin/produits/${localId.value}/videos/${id}`, {
      method: 'DELETE'
    })

    if (apiError.value) {
      showError(apiError.value.message || 'Failed to delete video')
      console.error('Delete video error:', apiError.value)
    } else {
      const idx = videos.value.findIndex(v => v.id === id)
      if (idx > -1) {
        videos.value.splice(idx, 1)
        showSuccess('Video deleted successfully')
      }
    }
  } catch (error: any) {
    console.error('Error deleting video:', error)
    showError(error.message || 'Failed to delete video')
  }
}

// VARIANT CATALOG
const fetchAttributes = async () => {
  try {
    loadingAttributes.value = true
    const { data, error } = await useApi('/admin/variant-attributs?actif=1')
    if (!error.value && data.value) {
      const response = data.value as any
      if (response.success) {
        availableAttributes.value = response.data
      }
    }
  } catch (err) {
    console.error('Error fetching variant attributes:', err)
  } finally {
    loadingAttributes.value = false
  }
}

const fetchValues = async (attributId: string) => {
  try {
    loadingValues.value = true
    const { data, error } = await useApi(`/admin/variant-attributs/${attributId}/valeurs?actif=1`)
    if (!error.value && data.value) {
      const response = data.value as any
      if (response.success) {
        availableValues.value = response.data
      }
    }
  } catch (err) {
    console.error('Error fetching variant values:', err)
  } finally {
    loadingValues.value = false
  }
}

const onAttributeChange = () => {
  newVariant.valeur_id = ''
  newVariant.valeur_ids = []
  availableValues.value = []
  if (newVariant.attribut_id) {
    fetchValues(newVariant.attribut_id)
  }
}

// VARIANTS
const addVariant = async () => {
  if (!localId.value || !newVariant.attribut_id || !newVariant.valeur_id) {
    console.warn('[ProductForm] Cannot add variant: missing product ID, attribute, or value')
    return
  }

  // Show confirm dialog before adding
  const confirmed = await confirmCreate('variante')
  if (!confirmed) return

  console.debug('[ProductForm] [productId] add-variant:', localId.value)
  try {
    const { data, error: apiError } = await useApi(`/admin/produits/${localId.value}/variantes`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        attribut_id: newVariant.attribut_id,
        valeur_id: newVariant.valeur_id,
        prix_vente_variante: newVariant.prix_vente_variante,
        actif: true
      })
    })

    if (apiError.value) {
      showError(apiError.value.message || 'Failed to add variant')
      console.error('Add variant error:', apiError.value)
    } else if (data.value) {
      const response = data.value as any
      if (response.success) {
        variantes.value.push(response.data)
        resetVariantForm()
        showSuccess('Variant added successfully')
      }
    }
  } catch (error: any) {
    console.error('Error adding variant:', error)
    showError(error.message || 'Failed to add variant')
  }
}

const addVariantsBulk = async () => {
  if (!localId.value || !newVariant.attribut_id || !newVariant.valeur_ids.length) {
    console.warn('[ProductForm] Cannot add variants: missing product ID, attribute, or values')
    return
  }

  // Show confirm dialog before adding
  const confirmed = await confirmCreate(`${newVariant.valeur_ids.length} variantes`)
  if (!confirmed) return

  console.debug('[ProductForm] [productId] add-variants-bulk:', localId.value, newVariant.valeur_ids)
  try {
    const { data, error: apiError } = await useApi(`/admin/produits/${localId.value}/variantes/bulk`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        attribut_id: newVariant.attribut_id,
        valeur_ids: newVariant.valeur_ids,
        prix_vente_variante: newVariant.prix_vente_variante,
        actif: true
      })
    })

    if (apiError.value) {
      showError(apiError.value.message || 'Failed to add variants')
      console.error('Add variants bulk error:', apiError.value)
    } else if (data.value) {
      const response = data.value as any
      if (response.success) {
        // Add created variants to the list
        if (response.data.created) {
          variantes.value.push(...response.data.created)
        }
        resetVariantForm()
        showSuccess(response.message || 'Variants added successfully')

        // Show additional info if some were skipped
        if (response.data.skipped && response.data.skipped.length > 0) {
          console.info('Skipped variants:', response.data.skipped)
        }
      }
    }
  } catch (error: any) {
    console.error('Error adding variants bulk:', error)
    showError(error.message || 'Failed to add variants')
  }
}

const resetVariantForm = () => {
  Object.assign(newVariant, {
    attribut_id: '',
    valeur_id: '',
    valeur_ids: [],
    nom: '',
    valeur: '',
    prix_vente_variante: null
  })
  availableValues.value = []
}
const deleteVariant = async (id: string) => {
  if (!localId.value) return

  // Show confirm dialog before deleting
  const confirmed = await confirmDelete('variante', 'cette variante')
  if (!confirmed) return

  try {
    const { error: apiError } = await useApi(`/admin/produits/${localId.value}/variantes/${id}`, {
      method: 'DELETE'
    })

    if (apiError.value) {
      showError(apiError.value.message || 'Failed to delete variant')
      console.error('Delete variant error:', apiError.value)
    } else {
      const idx = variantes.value.findIndex(v => v.id === id)
      if (idx > -1) {
        variantes.value.splice(idx, 1)
        showSuccess('Variant deleted successfully')
      }
    }
  } catch (error: any) {
    console.error('Error deleting variant:', error)
    showError(error.message || 'Failed to delete variant')
  }
}
const uploadVariantImage = async (id: string, file: File) => {
  if (!localId.value || !file) {
    console.warn('[ProductForm] Cannot upload variant image: missing product ID or file', { localId: localId.value, file })
    return
  }
  console.debug('[ProductForm] [productId] upload-variant-image:', localId.value, id, 'file:', file.name, 'size:', file.size)
  const fd = new FormData()
  fd.append('file', file)
  try {
    const { data, error } = await useApi(`/admin/produits/${localId.value}/variantes/${id}/image`, {
      method: 'POST',
      body: fd
    })
    console.debug('[ProductForm] Upload response:', { data: data.value, error: error.value })
    if (!error.value && data.value) {
      const response = data.value as any
      if (response.success) {
        const variant: any = variantes.value.find(v => v.id === id)
        if (variant) {
          variant.image_url = response.data.image_url
          console.debug('[ProductForm] Variant image updated:', variant.image_url)
          showSuccess('Variant image uploaded successfully')
        }
      } else {
        console.error('[ProductForm] Upload failed:', response.message)
        showError(response.message || 'Failed to upload variant image')
      }
    } else {
      console.error('[ProductForm] API error:', error.value)
      showError('Failed to upload variant image')
    }
  } catch (error) {
    console.error('Error uploading variant image:', error)
    showError('Failed to upload variant image')
  }
}

// Stock management methods
const getVariantStock = (variantId: string) => {
  let stock = stockForm.variant_stocks.find(s => s.variante_id === variantId)
  if (!stock) {
    stock = { variante_id: variantId, qty: 0, reserved: 0 }
    stockForm.variant_stocks.push(stock)
  }
  return stock
}

const updateVariantStock = (variantId: string, qty: string | number) => {
  const stock = getVariantStock(variantId)
  stock.qty = Number(qty) || 0
}

const distributeStock = () => {
  if (!stockForm.stock_total || !sizeVariants.value.length) return

  const perVariant = Math.floor(stockForm.stock_total / sizeVariants.value.length)
  const remainder = stockForm.stock_total % sizeVariants.value.length

  sizeVariants.value.forEach((variant, index) => {
    const qty = perVariant + (index === 0 ? remainder : 0)
    updateVariantStock(variant.id, qty)
  })

  showSuccess('Stock distributed across size variants')
}

const initializeStockForm = () => {
  if (form.value.stock_total) {
    stockForm.stock_total = form.value.stock_total
  }

  // Initialize variant stocks from existing data
  sizeVariants.value.forEach(variant => {
    if (!stockForm.variant_stocks.find(s => s.variante_id === variant.id)) {
      stockForm.variant_stocks.push({
        variante_id: variant.id,
        qty: 0,
        reserved: 0
      })
    }
  })
}

const handleStockAllocation = async () => {
  if (!localId.value || !stockForm.variant_stocks.length) return

  // Check if there are any stock changes
  const hasStockChanges = stockForm.variant_stocks.some(stock => stock.qty > 0) || stockForm.stock_total > 0
  if (!hasStockChanges) return

  // Show confirmation if there's a stock mismatch
  if (stockMismatch.value) {
    const confirmed = await confirmUpdate('stock allocation', 'Stock totals do not match. Continue anyway?')
    if (!confirmed) return
  }

  try {
    const { data, error } = await useApi(`/admin/produits/${localId.value}/stock/allocate`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        stock_total: stockForm.stock_total,
        variant_stocks: stockForm.variant_stocks.filter(stock => stock.qty > 0)
      })
    })

    if (error.value) {
      showError(error.value.message || 'Failed to allocate stock')
    } else if (data.value && (data.value as any).success) {
      showSuccess('Stock allocated successfully')
    }
  } catch (error: any) {
    showError(error.message || 'Failed to allocate stock')
  }
}

// Proposition methods
const addProposition = async (propositionData: { titre: string; description: string; type: string }) => {
  if (!localId.value) return
  try {
    const { data, error: apiError } = await useApi(`/admin/produits/${localId.value}/propositions`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(propositionData)
    })

    if (apiError.value) {
      showError(apiError.value.message || 'Failed to add proposition')
      console.error('Add proposition error:', apiError.value)
    } else if (data.value) {
      const response = data.value as any
      if (response.success) {
        propositions.value.push(response.data)
        showSuccess('Proposition added successfully')
        return response.data
      }
    }
  } catch (error: any) {
    console.error('Error adding proposition:', error)
    showError(error.message || 'Failed to add proposition')
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
  if (!localId.value || !file) {
    console.warn('[ProductForm] Cannot upload proposition image: missing product ID or file', { localId: localId.value, file })
    return
  }
  console.debug('[ProductForm] [productId] upload-proposition-image:', localId.value, propositionId, 'file:', file.name, 'size:', file.size)
  const fd = new FormData()
  fd.append('file', file)
  try {
    const { data, error } = await useApi(`/admin/produits/${localId.value}/propositions/${propositionId}/image`, {
      method: 'POST',
      body: fd
    })
    console.debug('[ProductForm] Proposition upload response:', { data: data.value, error: error.value })
    if (!error.value && data.value) {
      const response = data.value as any
      if (response.success) {
        const proposition = propositions.value.find(p => p.id === propositionId)
        if (proposition) {
          proposition.image_url = response.data.image_url
          console.debug('[ProductForm] Proposition image updated:', proposition.image_url)
          showSuccess('Proposition image uploaded successfully')
        }
      } else {
        console.error('[ProductForm] Proposition upload failed:', response.message)
        showError(response.message || 'Failed to upload proposition image')
      }
    } else {
      console.error('[ProductForm] Proposition API error:', error.value)
      showError('Failed to upload proposition image')
    }
  } catch (error) {
    console.error('Error uploading proposition image:', error)
    showError('Failed to upload proposition image')
  }
}

// Proposition handlers
const handleAddProposition = async () => {
  if (!newProposition.titre || !newProposition.description || !newProposition.type) return

  // Show confirm dialog before adding
  const confirmed = await confirmCreate('proposition')
  if (!confirmed) return

  const result = await addProposition(newProposition)
  if (result) {
    Object.assign(newProposition, { titre: '', description: '', type: '' })
  }
}

const handleDeleteProposition = async (propositionId: string) => {
  // Show confirm dialog before deleting
  const confirmed = await confirmDelete('proposition', 'cette proposition')
  if (!confirmed) return

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

// Ruptures methods
const loadRuptures = async () => {
  if (!localId.value) return
  try {
    const { data, error } = await useApi(`/admin/produits/${localId.value}/ruptures`)
    if (!error.value && data.value) {
      const response = data.value as any
      if (response.success) {
        console.debug('[ProductForm] Loaded ruptures:', response.data)
        ruptures.value = response.data
      }
    }
  } catch (error) {
    console.error('Error loading ruptures:', error)
  }
}

const handleAddRupture = async () => {
  if (!localId.value) {
    console.warn('[ProductForm] Cannot add rupture: no product ID')
    return
  }

  // Show confirm dialog before adding
  const confirmed = await confirmCreate('rupture de stock')
  if (!confirmed) return

  // Clear previous validation errors
  Object.keys(ruptureValidationErrors).forEach(key => {
    ruptureValidationErrors[key as keyof typeof ruptureValidationErrors] = []
  })

  // Client-side validation
  if (!newRupture.motif) {
    ruptureValidationErrors.motif = ['Reason is required']
    return
  }
  if (!newRupture.started_at) {
    ruptureValidationErrors.started_at = ['Started date is required']
    return
  }

  // Check if variant is required (when product has variants)
  if (variantes.value.length > 0 && !newRupture.variante_id) {
    ruptureValidationErrors.variante_id = ['Variant selection is required when product has variants']
    return
  }

  console.debug('[ProductForm] [productId] add-rupture:', localId.value, {
    hasVariants: variantes.value.length > 0,
    selectedVariant: newRupture.variante_id
  })

  try {
    const { data, error: apiError } = await useApi(`/admin/produits/${localId.value}/ruptures`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(newRupture)
    })

    if (apiError.value) {
      // Handle validation errors using the normalized error format
      if (apiError.value.errors) {
        Object.keys(apiError.value.errors).forEach(key => {
          if (key in ruptureValidationErrors) {
            ruptureValidationErrors[key as keyof typeof ruptureValidationErrors] = apiError.value.errors[key]
          }
        })
      }
      showError(apiError.value.message || 'Failed to report stock issue')
      console.error('Add rupture error:', apiError.value)
      return
    }

    if (data.value) {
      const response = data.value as any
      if (response.success) {
        console.debug('[ProductForm] Added rupture response:', response.data)
        ruptures.value.push(response.data)
        Object.assign(newRupture, { variante_id: null, motif: '', started_at: '', expected_restock_at: '' })
        showSuccess('Stock issue reported successfully')
      }
    }
  } catch (error: any) {
    console.error('Error adding rupture:', error)
    showError(error.message || 'Failed to report stock issue')
  }
}

const handleResolveRupture = async (ruptureId: string) => {
  if (!localId.value) return
  try {
    const { data, error } = await useApi(`/admin/produits/${localId.value}/ruptures/${ruptureId}/resolve`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' }
    })
    if (!error.value && data.value) {
      const response = data.value as any
      if (response.success) {
        const idx = ruptures.value.findIndex(r => r.id === ruptureId)
        if (idx > -1) {
          ruptures.value[idx] = response.data
        }
        showSuccess('Stock issue resolved successfully')
      }
    }
  } catch (error) {
    console.error('Error resolving rupture:', error)
    showError('Failed to resolve stock issue')
  }
}

const handleDeleteRupture = async (ruptureId: string) => {
  if (!localId.value) return

  // Show confirm dialog before deleting
  const confirmed = await confirmDelete('rupture de stock', 'cette rupture de stock')
  if (!confirmed) return

  try {
    const { error } = await useApi(`/admin/produits/${localId.value}/ruptures/${ruptureId}`, {
      method: 'DELETE'
    })
    if (!error.value) {
      const idx = ruptures.value.findIndex(r => r.id === ruptureId)
      if (idx > -1) {
        ruptures.value.splice(idx, 1)
        showSuccess('Stock issue deleted successfully')
      }
    }
  } catch (error) {
    console.error('Error deleting rupture:', error)
    showError('Failed to delete stock issue')
  }
}

// Lifecycle
onMounted(async () => {
  await loadLookups()
  await loadProduct()
  await fetchAttributes()

  // Initialize rich text editor content after product is loaded
  await nextTick()
  if (richTextEditor.value && form.value.copywriting) {
    richTextEditor.value.innerHTML = form.value.copywriting
  }
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
        <VTab value="notation">
          <VIcon icon="tabler-star" class="me-2" />
          {{ $t('products.rating.tab') }}
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
        <VTab :disabled="!readyForMedia" value="stock">
          <VIcon icon="tabler-package" class="me-2" />
          {{ $t('product.tabs.stock') }}
        </VTab>
        <VTab :disabled="!readyForMedia" value="propositions">
          <VIcon icon="tabler-list-details" class="me-2" />
          Propositions
        </VTab>
        <VTab :disabled="!readyForMedia" value="ruptures">
          <VIcon icon="tabler-alert-triangle" class="me-2" />
          Stock Issues
        </VTab>
      </VTabs>

      <!-- Tab Content -->
      <VForm @submit.prevent="saveProduct">
        <VTabsWindow v-model="activeTab" class="pa-6">
          <!-- Details Tab -->
          <VTabsWindowItem value="details">
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
                  :error-messages="productErrors.boutique_id"
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
                  :error-messages="productErrors.categorie_id"
                />
              </VCol>
              <VCol cols="12">
                <VTextField
                  v-model="form.titre"
                  label="Product Title"
                  variant="outlined"
                  required
                  :error-messages="productErrors.titre"
                />
              </VCol>
              <VCol cols="12">
                <VTextarea
                  v-model="form.description"
                  label="Description"
                  variant="outlined"
                  rows="4"
                  :error-messages="productErrors.description"
                />
              </VCol>
              <VCol cols="12">
                <div class="copywriting-field">
                  <VLabel class="mb-2">Copywriting (Marketing Text with Rich Formatting)</VLabel>

                  <!-- Rich Text Editor -->
                  <div class="rich-text-editor">
                    <div class="editor-toolbar">
                      <VBtnGroup variant="outlined" size="small" class="mb-2">
                        <VBtn
                          @click="formatText('bold')"
                          :color="isFormatActive('bold') ? 'primary' : 'default'"
                          icon="tabler-bold"
                          size="small"
                        />
                        <VBtn
                          @click="formatText('italic')"
                          :color="isFormatActive('italic') ? 'primary' : 'default'"
                          icon="tabler-italic"
                          size="small"
                        />
                        <VBtn
                          @click="formatText('underline')"
                          :color="isFormatActive('underline') ? 'primary' : 'default'"
                          icon="tabler-underline"
                          size="small"
                        />
                      </VBtnGroup>

                      <VBtnGroup variant="outlined" size="small" class="mb-2 ms-2">
                        <VBtn
                          @click="changeFontSize('small')"
                          size="small"
                          text="A"
                          style="font-size: 12px;"
                        />
                        <VBtn
                          @click="changeFontSize('normal')"
                          size="small"
                          text="A"
                          style="font-size: 14px;"
                        />
                        <VBtn
                          @click="changeFontSize('large')"
                          size="small"
                          text="A"
                          style="font-size: 16px;"
                        />
                      </VBtnGroup>
                    </div>

                    <div
                      ref="richTextEditor"
                      class="rich-text-content"
                      contenteditable="true"
                      @input="handleRichTextInput"
                      @keydown="handleKeyDown"
                      @focus="syncRichTextEditor"
                      :placeholder="'🌸 جديد عندنا 🌸\n\nكسوة صيفية بنقشة مميزة وراحة لا مثيل لها\n\n✅ القماش: توب لولان\n✅ المقاسات: L / XL / XXL / XXXL\n💰 الثمن: غير بـ130 درهم😍\n\nخلي الأناقة ديالك تبدا من هنا 💃'"
                    >{{ form.copywriting }}</div>
                  </div>

                  <div class="text-caption text-medium-emphasis mt-1">
                    <VIcon icon="tabler-info-circle" size="14" class="me-1" />
                    Rich text editor with bold, italic, underline, and font size controls. Supports emojis and line breaks.
                  </div>

                  <!-- Error Messages -->
                  <div v-if="productErrors.copywriting" class="text-error text-caption mt-1">
                    {{ productErrors.copywriting }}
                  </div>
                </div>
              </VCol>
              <VCol cols="12" md="4">
                <VTextField
                  v-model.number="form.prix_achat"
                  label="Purchase Price"
                  type="number"
                  variant="outlined"
                  step="0.01"
                  suffix="MAD"
                  :error-messages="productErrors.prix_achat"
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
                  :error-messages="productErrors.prix_vente"
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
                  :error-messages="productErrors.prix_affilie"
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model.number="form.quantite_min"
                  label="Minimum Quantity"
                  type="number"
                  variant="outlined"
                  min="1"
                  :error-messages="productErrors.quantite_min"
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
                  :error-messages="productErrors.notes_admin"
                />
              </VCol>
            </VRow>
        </VTabsWindowItem>

        <!-- Notation Tab -->
        <VTabsWindowItem value="notation">
          <div class="mb-6">
            <h3 class="text-h6 mb-2">{{ $t('products.rating.tab') }}</h3>
            <p class="text-body-2 text-medium-emphasis">
              {{ $t('products.rating.help') }}
            </p>
          </div>

          <VRow>
            <VCol cols="12" md="6">
              <VCard variant="outlined" class="pa-4">
                <div class="mb-4">
                  <label class="text-subtitle-2 mb-2 d-block">{{ $t('products.rating.value') }}</label>
                  <VRating
                    :model-value="form.rating_value || 0"
                    @update:model-value="(value) => form.rating_value = Number(value) || null"
                    half-increments
                    length="5"
                    color="warning"
                    background-color="grey-lighten-2"
                    size="large"
                    class="mb-3"
                  />
                  <VTextField
                    v-model.number="form.rating_value"
                    type="number"
                    min="0"
                    max="5"
                    step="0.1"
                    variant="outlined"
                    density="compact"
                    :label="$t('products.rating.value')"
                    :error-messages="productErrors.rating_value"
                    @blur="clampRatingValue"
                  />
                </div>
              </VCard>
            </VCol>

            <VCol cols="12" md="6">
              <VCard variant="outlined" class="pa-4">
                <h4 class="text-subtitle-1 mb-3">Metadata</h4>

                <div v-if="currentProduit?.rating?.updated_at" class="mb-3">
                  <VChip
                    size="small"
                    color="info"
                    variant="tonal"
                    prepend-icon="tabler-clock"
                  >
                    {{ $t('products.rating.last_update') }}: {{ formatDate(currentProduit.rating.updated_at) }}
                  </VChip>
                </div>

                <div v-if="currentProduit?.rating?.updated_by" class="mb-3">
                  <VChip
                    size="small"
                    color="primary"
                    variant="tonal"
                    prepend-icon="tabler-user"
                  >
                    {{ $t('products.rating.updated_by') }}: {{ currentProduit.rating.updated_by }}
                  </VChip>
                </div>

                <div v-if="!currentProduit?.rating?.updated_at" class="text-center py-4">
                  <VIcon icon="tabler-star-off" size="48" color="grey-lighten-1" class="mb-2" />
                  <p class="text-body-2 text-medium-emphasis">
                    Aucune note définie
                  </p>
                </div>
              </VCard>
            </VCol>
          </VRow>
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
                      @click="handleDeleteImage(img.id)"
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

            <!-- Improved Video Mode Toggle -->
            <div class="mb-6">
              <VChipGroup
                v-model="videoMode"
                selected-class="text-primary"
                mandatory
                class="mb-4"
              >
                <VChip
                  value="url"
                  size="large"
                  prepend-icon="tabler-link"
                  :color="videoMode === 'url' ? 'primary' : 'default'"
                  :variant="videoMode === 'url' ? 'flat' : 'outlined'"
                  class="me-2"
                >
                  Add by URL
                </VChip>
                <VChip
                  value="upload"
                  size="large"
                  prepend-icon="tabler-upload"
                  :color="videoMode === 'upload' ? 'primary' : 'default'"
                  :variant="videoMode === 'upload' ? 'flat' : 'outlined'"
                >
                  Upload Files
                </VChip>
              </VChipGroup>
            </div>

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
              <VCardTitle class="d-flex justify-space-between align-center">
                <div class="d-flex align-center gap-4">
                  <span>Add New Variant</span>
                  <VSwitch
                    v-model="isMultiSelectMode"
                    label="Multi-select"
                    color="primary"
                    density="compact"
                    hide-details
                  />
                </div>
                <VBtn
                  variant="text"
                  size="small"
                  color="primary"
                  prepend-icon="tabler-settings"
                  to="/admin/variants/attributs"
                  target="_blank"
                >
                  Manage Catalog
                </VBtn>
              </VCardTitle>
              <VCardText>
                <VRow>
                  <VCol cols="12" md="3">
                    <VSelect
                      v-model="newVariant.attribut_id"
                      label="Attribute"
                      variant="outlined"
                      :items="availableAttributes"
                      item-title="nom"
                      item-value="id"
                      placeholder="Select attribute"
                      @update:model-value="onAttributeChange"
                    />
                  </VCol>
                  <VCol cols="12" md="3">
                    <!-- Single Select Mode -->
                    <VSelect
                      v-if="!isMultiSelectMode"
                      v-model="newVariant.valeur_id"
                      label="Value"
                      variant="outlined"
                      :items="availableValues"
                      item-title="libelle"
                      item-value="id"
                      placeholder="Select value"
                      :disabled="!newVariant.attribut_id"
                    />
                    <!-- Multi Select Mode -->
                    <VSelect
                      v-else
                      v-model="newVariant.valeur_ids"
                      label="Values"
                      variant="outlined"
                      :items="availableValues"
                      item-title="libelle"
                      item-value="id"
                      placeholder="Select multiple values"
                      :disabled="!newVariant.attribut_id"
                      multiple
                      chips
                      closable-chips
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
                    <!-- Single Mode Button -->
                    <VBtn
                      v-if="!isMultiSelectMode"
                      color="primary"
                      block
                      :disabled="!newVariant.attribut_id || !newVariant.valeur_id"
                      @click="addVariant"
                    >
                      Add Variant
                    </VBtn>
                    <!-- Multi Mode Button -->
                    <VBtn
                      v-else
                      color="primary"
                      block
                      :disabled="!newVariant.attribut_id || !newVariant.valeur_ids.length"
                      @click="addVariantsBulk"
                    >
                      Add {{ newVariant.valeur_ids.length || 0 }} Variants
                    </VBtn>
                  </VCol>
                </VRow>
              </VCardText>
            </VCard>

            <div v-if="variantes.length">
              <h4 class="text-subtitle-1 mb-4">Product Variants</h4>
              <VDataTable
                :headers="[
                  { title: 'Attribute', key: 'attribut' },
                  { title: 'Value', key: 'valeur' },
                  { title: 'Price', key: 'prix_vente_variante' },
                  { title: 'Image', key: 'image', sortable: false },
                  { title: 'Actions', key: 'actions', sortable: false }
                ]"
                :items="variantes"
                class="elevation-1"
              >
                <template #item.attribut="{ item }">
                  <div>
                    <div class="font-weight-medium">
                      {{ (item as any).attribut?.nom || item.nom }}
                    </div>
                    <div v-if="(item as any).attribut" class="text-caption text-medium-emphasis">
                      {{ (item as any).attribut.code }}
                    </div>
                  </div>
                </template>
                <template #item.valeur="{ item }">
                  <div>
                    <div class="font-weight-medium">
                      {{ (item as any).valeur?.libelle || item.valeur }}
                    </div>
                    <div v-if="(item as any).valeur" class="text-caption text-medium-emphasis">
                      {{ (item as any).valeur.code }}
                    </div>
                  </div>
                </template>
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
                      :alt="`Variant ${item.nom} ${item.valeur}`"
                    />
                    <span v-else class="text-caption text-medium-emphasis">No image</span>
                    <VFileInput
                      density="compact"
                      hide-details
                      accept="image/*"
                      variant="plain"
                      @change="(e: any) => e.target?.files?.[0] && uploadVariantImage(item.id, e.target.files[0])"
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

        <!-- Stock Tab -->
        <VTabsWindowItem value="stock">
          <div v-if="!readyForMedia" class="text-center py-12">
            <VIcon icon="tabler-package-off" size="64" color="grey-lighten-1" class="mb-4" />
            <h3 class="text-h6 mb-2">Save Product First</h3>
            <p class="text-body-2 text-medium-emphasis">
              You need to save the product details before managing stock.
            </p>
          </div>

          <div v-else>
            <!-- Global Stock Section -->
            <VCard variant="outlined" class="mb-6">
              <VCardTitle>{{ $t('product.stock.global') }}</VCardTitle>
              <VCardText>
                <VRow>
                  <VCol cols="12" md="6">
                    <VTextField
                      v-model.number="stockForm.stock_total"
                      :label="$t('product.stock.total')"
                      type="number"
                      variant="outlined"
                      min="0"
                      suffix="unités"
                    />
                  </VCol>
                  <VCol cols="12" md="6" class="d-flex align-end">
                    <VBtn
                      color="primary"
                      variant="outlined"
                      :disabled="!sizeVariants.length || !stockForm.stock_total"
                      @click="distributeStock"
                    >
                      <VIcon icon="tabler-arrows-split" start />
                      {{ $t('product.stock.distribute') }}
                    </VBtn>
                  </VCol>
                </VRow>
              </VCardText>
            </VCard>

            <!-- Per-Variant Stock Section -->
            <VCard variant="outlined" class="mb-6">
              <VCardTitle>{{ $t('product.stock.per_variant') }}</VCardTitle>
              <VCardText>
                <div v-if="!sizeVariants.length" class="text-center py-8">
                  <VIcon icon="tabler-versions-off" size="48" color="grey-lighten-1" class="mb-4" />
                  <p class="text-body-2 text-medium-emphasis">
                    No size variants found. Create size variants first to manage stock per variant.
                  </p>
                </div>

                <div v-else>
                  <!-- Stock Table -->
                  <VTable density="compact">
                    <thead>
                      <tr>
                        <th>{{ $t('product.stock.size') }}</th>
                        <th>{{ $t('product.stock.qty') }}</th>
                        <th>{{ $t('product.stock.reserved') }}</th>
                        <th>{{ $t('product.stock.available') }}</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="variant in sizeVariants" :key="variant.id">
                        <td class="font-weight-medium">{{ variant.valeur }}</td>
                        <td>
                          <VTextField
                            v-model.number="getVariantStock(variant.id).qty"
                            type="number"
                            variant="outlined"
                            density="compact"
                            min="0"
                            style="width: 100px;"
                            hide-details
                            @update:model-value="updateVariantStock(variant.id, $event)"
                          />
                        </td>
                        <td class="text-medium-emphasis">
                          {{ getVariantStock(variant.id).reserved || 0 }}
                        </td>
                        <td class="font-weight-medium">
                          {{ Math.max(0, (getVariantStock(variant.id).qty || 0) - (getVariantStock(variant.id).reserved || 0)) }}
                        </td>
                      </tr>
                    </tbody>
                    <tfoot>
                      <tr class="bg-grey-lighten-4">
                        <td class="font-weight-bold">Total</td>
                        <td class="font-weight-bold">{{ totalVariantStock }}</td>
                        <td class="font-weight-bold">{{ totalReservedStock }}</td>
                        <td class="font-weight-bold">{{ totalAvailableStock }}</td>
                      </tr>
                    </tfoot>
                  </VTable>

                  <!-- Stock Mismatch Warning -->
                  <VAlert
                    v-if="stockMismatch"
                    type="warning"
                    variant="tonal"
                    class="mt-4"
                  >
                    <VIcon icon="tabler-alert-triangle" start />
                    {{ $t('product.stock.mismatch_warning') }}
                    <br>
                    <strong>Stock total: {{ stockForm.stock_total }}</strong> |
                    <strong>Somme variantes: {{ totalVariantStock }}</strong>
                  </VAlert>
                </div>
              </VCardText>
            </VCard>
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
                            :alt="`Proposition ${proposition.titre || 'image'}`"
                            cover
                          />
                        </div>
                        <div v-else class="mt-2">
                          <VAlert type="info" variant="tonal" density="compact">
                            No image uploaded
                          </VAlert>
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

        <!-- Ruptures Tab -->
        <VTabsWindowItem value="ruptures">
          <div v-if="!readyForMedia" class="text-center py-12">
            <VIcon icon="tabler-alert-triangle-off" size="64" color="grey-lighten-1" class="mb-4" />
            <h3 class="text-h6 mb-2">Save Product First</h3>
            <p class="text-body-2 text-medium-emphasis">
              You need to save the product details before managing stock issues.
            </p>
          </div>
          <div v-else>
            <div class="mb-6">
              <h3 class="text-h6 mb-2">Stock Issues (Ruptures)</h3>
              <p class="text-body-2 text-medium-emphasis">
                Manage stock shortages and expected restock dates for this product.
              </p>
            </div>

            <!-- Add New Rupture Form -->
            <VCard class="mb-6">
              <VCardTitle>Report Stock Issue</VCardTitle>
              <VCardText>
                <VForm @submit.prevent="handleAddRupture">
                  <VRow>
                    <VCol cols="12" md="6" v-if="variantes.length > 0">
                      <VSelect
                        v-model="newRupture.variante_id"
                        :items="[
                          { title: 'Product-level issue (all variants)', value: null },
                          ...variantes.map(v => {
                            console.debug('[ProductForm] Variant for select:', v)
                            return {
                              title: `${v.nom || 'Unknown'}: ${v.valeur || 'Unknown'}`,
                              value: v.id
                            }
                          })
                        ]"
                        :label="variantes.length > 0 ? 'Variant (Required)' : 'Variant (Optional)'"
                        variant="outlined"
                        :required="variantes.length > 0"
                        :error-messages="ruptureValidationErrors.variante_id"
                      />
                    </VCol>
                    <VCol cols="12" md="6" v-else>
                      <VAlert type="info" variant="tonal" class="mb-0">
                        This product has no variants. The stock issue will apply to the entire product.
                      </VAlert>
                    </VCol>
                    <VCol cols="12" md="6">
                      <VTextField
                        v-model="newRupture.motif"
                        label="Reason"
                        variant="outlined"
                        placeholder="e.g., Supplier delay, High demand"
                        required
                      />
                    </VCol>
                    <VCol cols="12" md="6">
                      <VTextField
                        v-model="newRupture.started_at"
                        label="Started At"
                        type="datetime-local"
                        variant="outlined"
                        required
                      />
                    </VCol>
                    <VCol cols="12" md="6">
                      <VTextField
                        v-model="newRupture.expected_restock_at"
                        label="Expected Restock (Optional)"
                        type="datetime-local"
                        variant="outlined"
                      />
                    </VCol>
                    <VCol cols="12">
                      <VBtn
                        type="submit"
                        color="primary"
                        :disabled="!newRupture.motif || !newRupture.started_at"
                      >
                        Report Issue
                      </VBtn>
                    </VCol>
                  </VRow>
                </VForm>
              </VCardText>
            </VCard>

            <!-- Ruptures List -->
            <VCard v-if="ruptures.length > 0">
              <VCardTitle>Current Stock Issues</VCardTitle>
              <VCardText>
                <VDataTable
                  :items="ruptures"
                  :headers="[
                    { title: 'Variant', key: 'variante_id' },
                    { title: 'Reason', key: 'motif' },
                    { title: 'Started', key: 'started_at' },
                    { title: 'Expected Restock', key: 'expected_restock_at' },
                    { title: 'Status', key: 'active' },
                    { title: 'Actions', key: 'actions', sortable: false }
                  ]"
                  class="elevation-1"
                >
                  <template #item.variante_id="{ item }">
                    <span :class="item.variante_id ? '' : 'text-medium-emphasis'">
                      {{ getVariantDisplayName(item.variante_id) }}
                    </span>
                  </template>
                  <template #item.started_at="{ item }">
                    {{ new Date(item.started_at).toLocaleDateString() }}
                  </template>
                  <template #item.expected_restock_at="{ item }">
                    <span v-if="item.expected_restock_at">
                      {{ new Date(item.expected_restock_at).toLocaleDateString() }}
                    </span>
                    <span v-else class="text-medium-emphasis">Not set</span>
                  </template>
                  <template #item.active="{ item }">
                    <VChip
                      :color="item.active ? 'error' : 'success'"
                      size="small"
                    >
                      {{ item.active ? 'Active' : 'Resolved' }}
                    </VChip>
                  </template>
                  <template #item.actions="{ item }">
                    <VBtn
                      v-if="item.active"
                      size="small"
                      color="success"
                      variant="outlined"
                      @click="handleResolveRupture(item.id)"
                    >
                      Resolve
                    </VBtn>
                    <VBtn
                      size="small"
                      color="error"
                      variant="outlined"
                      class="ml-2"
                      @click="handleDeleteRupture(item.id)"
                    >
                      Delete
                    </VBtn>
                  </template>
                </VDataTable>
              </VCardText>
            </VCard>

            <!-- Empty State -->
            <VAlert v-else type="info" variant="tonal">
              No stock issues reported yet. Report issues when products are out of stock or delayed.
            </VAlert>
          </div>
        </VTabsWindowItem>
      </VTabsWindow>
      </VForm>
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
          type="button"
          :loading="saving"
          @click="saveProduct"
        >
          {{ isEditMode ? 'Update Product' : 'Create Product' }}
        </VBtn>
      </VCardActions>
    </VCard>

    <!-- Success/Error Snackbar -->
    <VSnackbar
      v-model="snackbar.show"
      :color="snackbar.color"
      :timeout="snackbar.timeout"
      location="top end"
    >
      {{ snackbar.message }}
    </VSnackbar>
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
.copywriting-field {
  position: relative;
}

.copywriting-textarea {
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.copywriting-preview {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
}

.copywriting-content {
  line-height: 1.6;
  word-wrap: break-word;
  font-size: 14px;
}

.copywriting-content strong {
  font-weight: 600;
  color: rgb(var(--v-theme-primary));
}

.copywriting-content em {
  font-style: italic;
  color: rgb(var(--v-theme-secondary));
}

.rich-text-editor {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
  overflow: hidden;
}

.editor-toolbar {
  background: rgba(var(--v-theme-surface), 0.8);
  padding: 8px 12px;
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.rich-text-content {
  min-height: 120px;
  max-height: 300px;
  overflow-y: auto;
  padding: 12px;
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  font-size: 14px;
  line-height: 1.6;
  background: rgb(var(--v-theme-surface));
  outline: none;
  border: none;
}

.rich-text-content:empty:before {
  content: attr(placeholder);
  color: rgba(var(--v-theme-on-surface), 0.6);
  font-style: italic;
  white-space: pre-line;
}

.rich-text-content:focus {
  background: rgb(var(--v-theme-surface));
}

.rich-text-content strong {
  font-weight: 600;
  color: rgb(var(--v-theme-primary));
}

.rich-text-content em {
  font-style: italic;
  color: rgb(var(--v-theme-secondary));
}

.rich-text-content u {
  text-decoration: underline;
  color: rgb(var(--v-theme-info));
}
</style>
