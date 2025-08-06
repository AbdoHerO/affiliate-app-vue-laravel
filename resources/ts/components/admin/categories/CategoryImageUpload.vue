<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'

interface Props {
  modelValue?: string
  label?: string
  placeholder?: string
  errorMessages?: string[]
  disabled?: boolean
  required?: boolean
}

interface Emits {
  (e: 'update:modelValue', value: string): void
}

const props = withDefaults(defineProps<Props>(), {
  label: 'Category Image',
  placeholder: 'Choose category image',
  disabled: false,
  required: false
})

const emit = defineEmits<Emits>()

const { t } = useI18n()
const authStore = useAuthStore()

const fileInput = ref<HTMLInputElement>()
const selectedFile = ref<File | null>(null)
const previewUrl = ref<string>('')
const isUploading = ref(false)

// Computed properties
const hasImage = computed(() => !!previewUrl.value || !!props.modelValue)
const displayUrl = computed(() => previewUrl.value || props.modelValue || '')

// Watch for external changes to modelValue
watch(() => props.modelValue, (newValue) => {
  if (newValue && newValue !== previewUrl.value) {
    previewUrl.value = newValue
    selectedFile.value = null
  }
}, { immediate: true })

const handleFileSelect = () => {
  fileInput.value?.click()
}

const onFileChange = async (event: Event) => {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0]
  
  if (!file) return

  // Validate file type
  if (!file.type.startsWith('image/')) {
    console.error('Please select an image file')
    return
  }

  // Validate file size (max 5MB)
  if (file.size > 5 * 1024 * 1024) {
    console.error('File size must be less than 5MB')
    return
  }

  selectedFile.value = file
  
  // Create preview URL
  const reader = new FileReader()
  reader.onload = (e) => {
    previewUrl.value = e.target?.result as string
  }
  reader.readAsDataURL(file)

  // Upload the file
  await uploadFile(file)
}

const uploadFile = async (file: File) => {
  isUploading.value = true

  try {
    // Create FormData with the file
    const formData = new FormData()
    formData.append('category_image', file)

    // Send POST request to upload endpoint
    const response = await fetch('/api/upload/category-image', {
      method: 'POST',
      body: formData,
      headers: {
        'Authorization': `Bearer ${authStore.token}`,
        'Accept': 'application/json',
      }
    })

    if (!response.ok) {
      throw new Error(`Upload failed: ${response.statusText}`)
    }

    const result = await response.json()

    if (result.success) {
      // Emit the uploaded file URL
      emit('update:modelValue', result.url)
      console.log('Category image uploaded successfully:', file.name)
    } else {
      throw new Error(result.message || 'Upload failed')
    }

  } catch (error) {
    console.error('Upload error:', error)
    // Reset on error
    selectedFile.value = null
    previewUrl.value = props.modelValue || ''
  } finally {
    isUploading.value = false
  }
}

const removeImage = () => {
  selectedFile.value = null
  previewUrl.value = ''
  emit('update:modelValue', '')
  
  // Clear file input
  if (fileInput.value) {
    fileInput.value.value = ''
  }
}
</script>

<template>
  <div class="category-image-upload">
    <!-- Hidden file input -->
    <input
      ref="fileInput"
      type="file"
      accept="image/*"
      style="display: none"
      @change="onFileChange"
    />

    <!-- Label -->
    <VLabel v-if="label" class="mb-2">
      {{ label }}
      <span v-if="required" class="text-error">*</span>
    </VLabel>

    <!-- Image Preview and Upload Controls -->
    <div class="d-flex align-center gap-4">
      <!-- Image Preview -->
      <div 
        v-if="hasImage"
        class="flex-shrink-0"
      >
        <VImg
          :src="displayUrl"
          width="80"
          height="80"
          class="rounded border"
          cover
        />
      </div>

      <!-- Placeholder when no image -->
      <div 
        v-else
        class="flex-shrink-0 d-flex align-center justify-center rounded border"
        style="width: 80px; height: 80px; background-color: var(--v-theme-surface-variant);"
      >
        <VIcon 
          icon="tabler-photo" 
          size="32" 
          color="disabled"
        />
      </div>

      <!-- Upload Controls -->
      <div class="flex-grow-1">
        <div class="d-flex gap-2 mb-2">
          <VBtn
            size="small"
            color="primary"
            prepend-icon="tabler-upload"
            :disabled="disabled || isUploading"
            :loading="isUploading"
            @click="handleFileSelect"
          >
            {{ selectedFile ? t('common.change') : t('common.upload') }}
          </VBtn>
          
          <VBtn
            v-if="hasImage"
            size="small"
            variant="outlined"
            color="error"
            prepend-icon="tabler-trash"
            :disabled="disabled || isUploading"
            @click="removeImage"
          >
            {{ t('common.remove') }}
          </VBtn>
        </div>

        <div class="text-caption text-disabled">
          {{ t('admin_categories_image_help') }}
        </div>

        <!-- File name display -->
        <div 
          v-if="selectedFile" 
          class="text-caption text-success mt-1"
        >
          {{ selectedFile.name }}
        </div>

        <!-- Upload progress -->
        <div 
          v-if="isUploading"
          class="text-caption text-primary mt-1"
        >
          {{ t('common_uploading') }}...
        </div>
      </div>
    </div>

    <!-- Error messages -->
    <div 
      v-if="errorMessages && errorMessages.length > 0"
      class="text-error text-caption mt-1"
    >
      <div v-for="error in errorMessages" :key="error">
        {{ error }}
      </div>
    </div>
  </div>
</template>

<style scoped>
.category-image-upload {
  width: 100%;
}
</style>
