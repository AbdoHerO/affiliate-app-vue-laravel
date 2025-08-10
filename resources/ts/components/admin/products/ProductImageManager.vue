<template>
  <div>
    <div class="d-flex justify-space-between align-center mb-4">
      <h3 class="text-h6">{{ $t('admin_produits_images') }}</h3>
      <VBtn
        color="primary"
        prepend-icon="tabler-plus"
        @click="triggerFileInput"
      >
        {{ $t('admin_produits_add_image') }}
      </VBtn>
    </div>

    <!-- Hidden file input -->
    <input
      ref="fileInput"
      type="file"
      multiple
      accept="image/*"
      style="display: none"
      @change="handleFileUpload"
    />

    <!-- Images Grid -->
    <VRow v-if="localImages.length > 0">
      <VCol
        v-for="(image, index) in localImages"
        :key="image.id"
        cols="12"
        sm="6"
        md="4"
        lg="3"
      >
        <VCard>
          <VImg
            :src="image.url"
            aspect-ratio="1"
            cover
            class="cursor-pointer"
          />
          <VCardActions class="pa-2">
            <VTextField
              v-model="image.ordre"
              type="number"
              label="Order"
              density="compact"
              hide-details
              style="max-width: 80px"
              @blur="updateImageOrder(image)"
            />
            <VSpacer />
            <VBtn
              icon="tabler-trash"
              size="small"
              color="error"
              variant="text"
              @click="deleteImage(image)"
            />
          </VCardActions>
        </VCard>
      </VCol>
    </VRow>

    <!-- Empty State -->
    <VCard v-else class="text-center pa-8">
      <VIcon
        icon="tabler-photo"
        size="64"
        color="grey-lighten-1"
        class="mb-4"
      />
      <h4 class="text-h6 mb-2">{{ $t('admin_produits_no_images') }}</h4>
      <p class="text-body-2 text-medium-emphasis mb-4">
        {{ $t('admin_produits_no_images_subtitle') }}
      </p>
      <VBtn
        color="primary"
        prepend-icon="tabler-upload"
        @click="triggerFileInput"
      >
        {{ $t('admin_produits_upload_images') }}
      </VBtn>
    </VCard>

    <!-- Loading Overlay -->
    <VOverlay
      v-model="isUploading"
      contained
      class="align-center justify-center"
    >
      <VProgressCircular
        indeterminate
        size="64"
        color="primary"
      />
      <div class="mt-4 text-center">
        <p class="text-body-1">{{ $t('admin_produits_uploading') }}</p>
      </div>
    </VOverlay>
  </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useApi } from '@/composables/useApi'
import { useNotifications } from '@/composables/useNotifications'

interface Image {
  id: string
  url: string
  ordre: number
}

interface Props {
  productId: string
  modelValue: Image[]
}

interface Emits {
  (e: 'update:modelValue', value: Image[]): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const { t } = useI18n()
const { showSuccess, showError } = useNotifications()

// Local state
const fileInput = ref<HTMLInputElement>()
const localImages = ref<Image[]>([...props.modelValue])
const isUploading = ref(false)

// Watch for external changes
watch(() => props.modelValue, (newValue) => {
  localImages.value = [...newValue]
}, { deep: true })

// Emit changes
watch(localImages, (newValue) => {
  emit('update:modelValue', newValue)
}, { deep: true })

// Methods
const triggerFileInput = () => {
  fileInput.value?.click()
}

const handleFileUpload = async (event: Event) => {
  const target = event.target as HTMLInputElement
  const files = target.files
  
  if (!files || files.length === 0) return

  isUploading.value = true

  try {
    for (const file of Array.from(files)) {
      await uploadImage(file)
    }
    showSuccess(t('admin_produits_images_uploaded'))
  } catch (error) {
    console.error('Error uploading images:', error)
    showError(t('admin_produits_upload_error'))
  } finally {
    isUploading.value = false
    // Reset file input
    if (target) target.value = ''
  }
}

const uploadImage = async (file: File) => {
  const formData = new FormData()
  formData.append('file', file)

  const { data, error } = await useApi(`/admin/produits/${props.productId}/images/upload`, {
    method: 'POST',
    body: formData
  })

  if (error.value) {
    throw error.value
  }

  const response = data.value as any
  if (response.success) {
    localImages.value.push(response.data)
  } else {
    throw new Error(response.message)
  }
}

const updateImageOrder = async (image: Image) => {
  try {
    const { data, error } = await useApi(`/admin/produits/${props.productId}/images/sort`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify([{ id: image.id, ordre: image.ordre }])
    })

    if (error.value) {
      throw error.value
    }

    const response = data.value as any
    if (!response.success) {
      throw new Error(response.message)
    }
  } catch (error) {
    console.error('Error updating image order:', error)
    showError(t('admin_produits_order_update_error'))
  }
}

const deleteImage = async (image: Image) => {
  try {
    const { error } = await useApi(`/admin/produits/${props.productId}/images/${image.id}`, {
      method: 'DELETE'
    })

    if (error.value) {
      throw error.value
    }

    // Remove from local state
    const index = localImages.value.findIndex(img => img.id === image.id)
    if (index > -1) {
      localImages.value.splice(index, 1)
    }

    showSuccess(t('admin_produits_image_deleted'))
  } catch (error) {
    console.error('Error deleting image:', error)
    showError(t('admin_produits_delete_error'))
  }
}
</script>
