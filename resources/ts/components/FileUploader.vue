<template>
  <div class="file-uploader">
    <!-- Toggle between URL and File Upload -->
    <div class="d-flex align-center mb-3">
      <VBtnToggle
        v-model="uploadMode"
        variant="outlined"
        divided
        mandatory
      >
        <VBtn value="url" size="small">
          <VIcon icon="tabler-link" class="me-1" />
          URL
        </VBtn>
        <VBtn value="file" size="small">
          <VIcon icon="tabler-upload" class="me-1" />
          Upload
        </VBtn>
      </VBtnToggle>
    </div>

    <!-- URL Input Mode -->
    <div v-if="uploadMode === 'url'">
      <VTextField
        :model-value="url"
        :label="urlLabel"
        :placeholder="urlPlaceholder"
        variant="outlined"
        prepend-inner-icon="tabler-link"
        @update:model-value="$emit('update:url', $event)"
      />
    </div>

    <!-- File Upload Mode -->
    <div v-else>
      <VFileInput
        :model-value="file"
        :label="fileLabel"
        :accept="accept"
        variant="outlined"
        prepend-inner-icon="tabler-upload"
        show-size
        @update:model-value="handleFileSelect"
      />
      
      <!-- Upload Progress -->
      <VProgressLinear
        v-if="uploading"
        :model-value="uploadProgress"
        color="primary"
        height="4"
        class="mt-2"
      />
      
      <!-- Upload Status -->
      <VAlert
        v-if="uploadStatus"
        :type="uploadStatus.type"
        variant="tonal"
        class="mt-2"
        closable
        @click:close="uploadStatus = null"
      >
        {{ uploadStatus.message }}
      </VAlert>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { useApi } from '@/composables/useApi'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

interface Props {
  url?: string
  urlLabel?: string
  urlPlaceholder?: string
  fileLabel?: string
  accept?: string
  uploadEndpoint?: string
  productId?: string
}

interface Emits {
  (e: 'update:url', value: string): void
  (e: 'file-uploaded', data: { url: string, file: File }): void
  (e: 'upload-error', error: any): void
}

const props = withDefaults(defineProps<Props>(), {
  urlLabel: 'URL',
  urlPlaceholder: 'Enter URL...',
  fileLabel: 'Upload File',
  accept: '*/*',
})

const emit = defineEmits<Emits>()

// State
const uploadMode = ref<'url' | 'file'>('url')
const file = ref<File | null>(null)
const uploading = ref(false)
const uploadProgress = ref(0)
const uploadStatus = ref<{ type: 'success' | 'error', message: string } | null>(null)

// Methods
const handleFileSelect = (selectedFile: File | null) => {
  file.value = selectedFile
  if (selectedFile && props.uploadEndpoint && props.productId) {
    uploadFile(selectedFile)
  }
}

const uploadFile = async (fileToUpload: File) => {
  if (!props.uploadEndpoint || !props.productId) {
    uploadStatus.value = { type: 'error', message: t('components_upload_configuration_missing') }
    return
  }

  uploading.value = true
  uploadProgress.value = 0
  uploadStatus.value = null

  try {
    const formData = new FormData()
    formData.append('file', fileToUpload)

    const endpoint = props.uploadEndpoint.replace('{id}', props.productId)
    
    const { data, error } = await useApi(endpoint, {
      method: 'POST',
      body: formData,
      // Don't set Content-Type header, let browser set it with boundary
    })

    if (error.value) {
      throw error.value
    }

    const response = data.value as any
    if (response.success) {
      uploadStatus.value = { type: 'success', message: response.message || t('components_file_uploaded_successfully') }
      emit('file-uploaded', { url: response.url, file: fileToUpload })
      emit('update:url', response.url)
    } else {
      throw new Error(response.message || t('components_upload_failed'))
    }
  } catch (err: any) {
    console.error('Upload error:', err)
    uploadStatus.value = { 
      type: 'error', 
      message: err.message || t('components_upload_failed_try_again') 
    }
    emit('upload-error', err)
  } finally {
    uploading.value = false
    uploadProgress.value = 0
  }
}

// Reset file when switching to URL mode
watch(uploadMode, (newMode) => {
  if (newMode === 'url') {
    file.value = null
    uploadStatus.value = null
  }
})
</script>

<style scoped>
.file-uploader {
  width: 100%;
}
</style>
