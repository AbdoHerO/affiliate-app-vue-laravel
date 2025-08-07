<template>
  <div class="product-image-gallery">
    <!-- Upload Section -->
    <div class="card mb-4">
      <div class="card-header">
        <h6 class="card-title mb-0">
          <i class="fas fa-cloud-upload-alt me-2"></i>
          {{ $t('admin_produits_upload_image') }}
        </h6>
      </div>
      <div class="card-body">
        <form @submit.prevent="handleUpload" class="mb-3">
          <div class="row">
            <div class="col-md-8 mb-3">
              <label for="image_url" class="form-label">
                {{ $t('admin_produits_image_url') }} <span class="text-danger">*</span>
              </label>
              <input 
                id="image_url" 
                v-model="uploadForm.image_url"
                type="url" 
                class="form-control"
                :class="{ 'is-invalid': uploadErrors.image_url }"
                :placeholder="$t('admin_produits_image_url')"
                required
              >
              <div v-if="uploadErrors.image_url" class="invalid-feedback">
                {{ uploadErrors.image_url[0] }}
              </div>
            </div>
            <div class="col-md-4 mb-3">
              <label for="order" class="form-label">
                {{ $t('admin_produits_image_order') }}
              </label>
              <input 
                id="order" 
                v-model="uploadForm.order"
                type="number" 
                min="1"
                class="form-control"
                :class="{ 'is-invalid': uploadErrors.order }"
                :placeholder="$t('admin_produits_image_order')"
              >
              <div v-if="uploadErrors.order" class="invalid-feedback">
                {{ uploadErrors.order[0] }}
              </div>
            </div>
          </div>
          <button 
            type="submit" 
            class="btn btn-primary"
            :disabled="isUploading"
          >
            <span v-if="isUploading" class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
            <i v-else class="fas fa-plus me-2"></i>
            {{ $t('common_add') }}
          </button>
        </form>
      </div>
    </div>

    <!-- Images Gallery -->
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="card-title mb-0">
          <i class="fas fa-images me-2"></i>
          {{ $t('admin_produits_gallery') }}
          <span class="badge bg-secondary ms-2">{{ sortedImages.length }}</span>
        </h6>
        <button 
          v-if="sortedImages.length > 1"
          @click="handleBulkSort" 
          class="btn btn-sm btn-outline-primary"
          :disabled="isSorting"
        >
          <span v-if="isSorting" class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
          <i v-else class="fas fa-sort me-2"></i>
          {{ $t('common_save_order') }}
        </button>
      </div>
      <div class="card-body">
        <div v-if="sortedImages.length > 0">
          <div class="alert alert-info mb-3" v-if="sortedImages.length > 1">
            <i class="fas fa-info-circle me-2"></i>
            {{ $t('admin_produits_drag_sort') }}
          </div>
          
          <div 
            ref="sortableContainer"
            class="row"
          >
            <div 
              v-for="(image, index) in sortedImages" 
              :key="image.id"
              :data-id="image.id"
              class="col-md-3 col-sm-6 mb-4 sortable-item"
            >
              <div class="card position-relative">
                <div class="position-absolute top-0 start-0 m-2">
                  <span class="badge bg-primary">{{ image.order }}</span>
                </div>
                <div class="position-absolute top-0 end-0 m-2">
                  <button 
                    @click="handleDeleteImage(image.id)"
                    class="btn btn-sm btn-danger"
                    :disabled="isDeletingImage"
                  >
                    <i class="fas fa-trash"></i>
                  </button>
                </div>
                <img 
                  :src="image.image_url" 
                  :alt="`Product image ${image.order}`"
                  class="card-img-top"
                  style="height: 200px; object-fit: cover; cursor: grab;"
                  @dragstart="handleDragStart($event, index)"
                  @dragover="handleDragOver"
                  @drop="handleDrop($event, index)"
                  draggable="true"
                >
                <div class="card-body p-2">
                  <small class="text-muted d-block">
                    {{ $t('admin_produits_image_order') }}: {{ image.order }}
                  </small>
                  <small class="text-muted">
                    <a :href="image.image_url" target="_blank" class="text-decoration-none">
                      <i class="fas fa-external-link-alt me-1"></i>
                      {{ $t('common_view_original') }}
                    </a>
                  </small>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div v-else class="text-center py-4">
          <i class="fas fa-images fa-3x text-muted mb-3"></i>
          <p class="text-muted">{{ $t('admin_produits_no_images') }}</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, nextTick } from 'vue'
import { useI18n } from 'vue-i18n'
import { useProduitStore } from '@/stores/admin/produits'
import type { ProduitImage } from '@/types/admin/produits'

interface Props {
  produitId: number
  images: ProduitImage[]
}

interface Emits {
  (e: 'images-updated'): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const { t } = useI18n()
const produitStore = useProduitStore()

const isUploading = ref(false)
const isDeletingImage = ref(false)
const isSorting = ref(false)
const uploadErrors = ref<Record<string, string[]>>({})
const draggedIndex = ref<number | null>(null)
const localImages = ref<ProduitImage[]>([...props.images])

const uploadForm = reactive({
  image_url: '',
  order: localImages.value.length + 1
})

const sortedImages = computed(() => {
  return [...localImages.value].sort((a, b) => a.order - b.order)
})

const handleUpload = async () => {
  try {
    isUploading.value = true
    uploadErrors.value = {}

    await produitStore.addProduitImage(props.produitId, uploadForm)
    
    // Reset form
    uploadForm.image_url = ''
    uploadForm.order = localImages.value.length + 2
    
    // Refresh images
    await refreshImages()
    
  } catch (error: any) {
    if (error.response?.status === 422) {
      uploadErrors.value = error.response.data.errors
    }
    console.error('Error uploading image:', error)
  } finally {
    isUploading.value = false
  }
}

const handleDeleteImage = async (imageId: number) => {
  if (!confirm(t('common_delete_confirm'))) return
  
  try {
    isDeletingImage.value = true
    
    await produitStore.deleteProduitImage(props.produitId, imageId)
    
    // Remove from local array
    localImages.value = localImages.value.filter(img => img.id !== imageId)
    
    emit('images-updated')
    
  } catch (error) {
    console.error('Error deleting image:', error)
  } finally {
    isDeletingImage.value = false
  }
}

const handleDragStart = (event: DragEvent, index: number) => {
  draggedIndex.value = index
  if (event.dataTransfer) {
    event.dataTransfer.effectAllowed = 'move'
  }
}

const handleDragOver = (event: DragEvent) => {
  event.preventDefault()
  if (event.dataTransfer) {
    event.dataTransfer.dropEffect = 'move'
  }
}

const handleDrop = (event: DragEvent, dropIndex: number) => {
  event.preventDefault()
  
  if (draggedIndex.value === null || draggedIndex.value === dropIndex) {
    return
  }
  
  // Reorder images locally
  const newImages = [...sortedImages.value]
  const draggedImage = newImages[draggedIndex.value]
  
  // Remove dragged item and insert at new position
  newImages.splice(draggedIndex.value, 1)
  newImages.splice(dropIndex, 0, draggedImage)
  
  // Update order values
  newImages.forEach((image, index) => {
    image.order = index + 1
  })
  
  localImages.value = newImages
  draggedIndex.value = null
}

const handleBulkSort = async () => {
  try {
    isSorting.value = true
    
    const imageOrders = sortedImages.value.map(image => ({
      id: image.id,
      order: image.order
    }))
    
    await produitStore.bulkSortProduitImages(props.produitId, imageOrders)
    
    emit('images-updated')
    
  } catch (error) {
    console.error('Error sorting images:', error)
  } finally {
    isSorting.value = false
  }
}

const refreshImages = async () => {
  try {
    const response = await produitStore.fetchProduitImages(props.produitId)
    localImages.value = response
    emit('images-updated')
  } catch (error) {
    console.error('Error refreshing images:', error)
  }
}

// Watch for props changes
onMounted(() => {
  localImages.value = [...props.images]
  uploadForm.order = localImages.value.length + 1
})
</script>

<style scoped>
.sortable-item {
  transition: transform 0.2s ease;
}

.sortable-item:hover {
  transform: translateY(-2px);
}

.card img[draggable="true"]:active {
  cursor: grabbing;
}
</style>
