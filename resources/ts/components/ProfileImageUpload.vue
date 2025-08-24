<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { getAvatarUrl } from '@/utils/imageUtils'

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
  label: 'Profile Image',
  placeholder: 'Choose profile image',
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

// Initialize preview URL from modelValue
watch(() => props.modelValue, (newValue) => {
  if (newValue && !selectedFile.value) {
    previewUrl.value = newValue
  }
}, { immediate: true })

const displayImage = computed(() => {
  return getAvatarUrl(previewUrl.value)
})

const handleFileSelect = () => {
  fileInput.value?.click()
}

const onFileChange = async (event: Event) => {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0]
  
  if (!file) return

  // Validate file type
  if (!file.type.startsWith('image/')) {
    // You could emit an error event here
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

  // Here you would typically upload the file to your server
  // For now, we'll simulate an upload and emit the file URL
  await uploadFile(file)
}

const uploadFile = async (file: File) => {
  isUploading.value = true

  try {
    // Create FormData with the file
    const formData = new FormData()
    formData.append('profile_image', file)

    // Send POST request to upload endpoint
    const response = await fetch('/api/upload/profile-image', {
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
      console.log('File uploaded successfully:', file.name)
    } else {
      throw new Error(result.message || t('components_upload_failed'))
    }

  } catch (error) {
    console.error('Upload failed:', error)
    // Reset on error
    selectedFile.value = null
    previewUrl.value = props.modelValue || ''

    // You could emit an error event here for better UX
    alert(t('components_upload_failed') + ': ' + (error instanceof Error ? error.message : t('components_unknown_error')))
  } finally {
    isUploading.value = false
  }
}

const removeImage = () => {
  selectedFile.value = null
  previewUrl.value = ''
  emit('update:modelValue', '')
  
  // Reset file input
  if (fileInput.value) {
    fileInput.value.value = ''
  }
}
</script>

<template>
  <div class="profile-image-upload">
    <!-- Label -->
    <label 
      v-if="label" 
      class="text-body-2 text-high-emphasis mb-2 d-block"
    >
      {{ label }}
      <span v-if="required" class="text-error">*</span>
    </label>

    <!-- Image Preview and Upload Area -->
    <div class="d-flex align-center gap-4">
      <!-- Image Preview -->
      <div class="position-relative">
        <VAvatar
          size="80"
          :image="displayImage"
          class="border"
        />
        
        <!-- Loading overlay -->
        <div 
          v-if="isUploading"
          class="position-absolute top-0 left-0 w-100 h-100 d-flex align-center justify-center"
          style="background: rgba(0,0,0,0.5); border-radius: 50%;"
        >
          <VProgressCircular
            indeterminate
            color="white"
            size="24"
          />
        </div>
      </div>

      <!-- Upload Controls -->
      <div class="flex-grow-1">
        <div class="d-flex gap-2 mb-2">
          <VBtn
            size="small"
            color="primary"
            prepend-icon="tabler-upload"
            :disabled="disabled || isUploading"
            @click="handleFileSelect"
          >
            {{ selectedFile ? t('change_image') : t('upload_image') }}
          </VBtn>
          
          <VBtn
            v-if="previewUrl"
            size="small"
            variant="outlined"
            color="error"
            prepend-icon="tabler-trash"
            :disabled="disabled || isUploading"
            @click="removeImage"
          >
            {{ t('remove') }}
          </VBtn>
        </div>

        <div class="text-caption text-disabled">
          {{ t('upload_image_hint') }}
        </div>

        <!-- File name display -->
        <div 
          v-if="selectedFile" 
          class="text-caption text-success mt-1"
        >
          {{ selectedFile.name }}
        </div>
      </div>
    </div>

    <!-- Error Messages -->
    <div 
      v-if="errorMessages?.length" 
      class="text-error text-caption mt-2"
    >
      <div 
        v-for="error in errorMessages" 
        :key="error"
      >
        {{ error }}
      </div>
    </div>

    <!-- Hidden file input -->
    <input
      ref="fileInput"
      type="file"
      accept="image/*"
      style="display: none;"
      @change="onFileChange"
    >
  </div>
</template>

<style lang="scss" scoped>
.profile-image-upload {
  .v-avatar {
    border: 2px solid rgb(var(--v-border-color));
  }
}
</style>
