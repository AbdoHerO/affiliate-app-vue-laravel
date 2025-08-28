<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useApi } from '@/composables/useApi'
import { useNotifications } from '@/composables/useNotifications'
import { useQuickConfirm } from '@/composables/useConfirmAction'

const { t } = useI18n()
const { showSuccess, showError } = useNotifications()
const { confirmDelete } = useQuickConfirm()

// Types
type KycDocument = {
  id: string
  type_doc: 'cni' | 'passport' | 'rib' | 'contrat'
  url_fichier: string
  statut: 'en_attente' | 'valide' | 'refuse'
  motif_refus?: string
  created_at: string
  updated_at: string
}

// State
const loading = ref(false)
const documents = ref<KycDocument[]>([])
const showUploadDialog = ref(false)

// Upload form
const uploadForm = ref({
  type_doc: 'cni' as KycDocument['type_doc'],
  fichier: null as File | null
})

const uploadErrors = ref<Record<string, string[]>>({})

// Document types
const documentTypes = [
  { title: t('document_cni'), value: 'cni' },
  { title: t('document_passport'), value: 'passport' },
  { title: t('document_rib'), value: 'rib' },
  { title: t('document_contract'), value: 'contrat' },
]

// Fetch documents
const fetchDocuments = async () => {
  try {
    loading.value = true

    const { data, error } = await useApi<{ success: boolean; documents: KycDocument[] }>('/profile/kyc-documents')

    if (error.value) {
      showError(error.value.message || t('failed_to_load_documents'))
      return
    }

    if (data.value?.success) {
      documents.value = data.value.documents
    }
  } catch (err: any) {
    showError(err.message || t('failed_to_load_documents'))
  } finally {
    loading.value = false
  }
}

// Upload document
const uploadDocument = async () => {
  if (!uploadForm.value.fichier) return
  
  try {
    loading.value = true
    uploadErrors.value = {}
    
    const formData = new FormData()
    formData.append('type_doc', uploadForm.value.type_doc)
    formData.append('fichier', uploadForm.value.fichier)
    
    const baseUrl = import.meta.env.VITE_API_BASE_URL || '/api'
    const uploadUrl = `${baseUrl}/profile/kyc-documents`
    
    const response = await fetch(uploadUrl, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
        'Accept': 'application/json',
      },
      body: formData,
    })
    
    if (!response.ok) {
      const errorData = await response.json()
      if (response.status === 422 && errorData.errors) {
        uploadErrors.value = errorData.errors
      } else {
        showError(errorData.message || t('document_upload_failed'))
      }
      return
    }
    
    const responseData = await response.json()
    
    showUploadDialog.value = false
    resetUploadForm()
    await fetchDocuments()
    showSuccess(responseData.message || t('document_uploaded_successfully'))
    
  } catch (err: any) {
    showError(err.message || t('document_upload_failed'))
  } finally {
    loading.value = false
  }
}

// Download document
const downloadDocument = async (document: KycDocument) => {
  try {
    // Use direct storage URL instead of API endpoint
    const storageUrl = `/storage/kyc-documents/${document.chemin_fichier}`

    // Create a temporary link and trigger download
    const a = document.createElement('a')
    a.href = storageUrl
    a.download = `${getTypeLabel(document.type_doc)}_${document.nom_fichier}`
    a.target = '_blank'
    document.body.appendChild(a)
    a.click()
    document.body.removeChild(a)

  } catch (err: any) {
    showError(err.message || t('download_failed'))
  }
}

// Delete document
const deleteDocument = async (document: KycDocument) => {
  const confirmed = await confirmDelete(t('document'), getTypeLabel(document.type_doc))
  if (!confirmed) return
  
  try {
    loading.value = true
    
    const { data, error } = await useApi(`/profile/kyc-documents/${document.id}`, {
      method: 'DELETE',
    })
    
    if (error.value) {
      showError(error.value.message || t('document_deletion_failed'))
      return
    }
    
    if (data.value?.success) {
      await fetchDocuments()
      showSuccess(data.value.message || t('document_deleted_successfully'))
    }
  } catch (err: any) {
    showError(err.message || t('document_deletion_failed'))
  } finally {
    loading.value = false
  }
}

// Helper functions
const getTypeLabel = (type: string) => {
  const typeMap: Record<string, string> = {
    cni: t('document_cni'),
    passport: t('document_passport'),
    rib: t('document_rib'),
    contrat: t('document_contract'),
  }
  return typeMap[type] || type
}

const getStatusColor = (status: string) => {
  const colorMap: Record<string, string> = {
    en_attente: 'warning',
    valide: 'success',
    refuse: 'error',
  }
  return colorMap[status] || 'default'
}

const getStatusLabel = (status: string) => {
  const labelMap: Record<string, string> = {
    en_attente: t('document_pending'),
    valide: t('document_validated'),
    refuse: t('document_rejected'),
  }
  return labelMap[status] || status
}

const resetUploadForm = () => {
  uploadForm.value = {
    type_doc: 'cni',
    fichier: null
  }
  uploadErrors.value = {}
}

const handleFileChange = (event: Event) => {
  const target = event.target as HTMLInputElement
  if (target.files && target.files.length > 0) {
    uploadForm.value.fichier = target.files[0]
  }
}

// Computed
const canDelete = (document: KycDocument) => {
  return document.statut !== 'valide'
}

// Lifecycle
onMounted(() => {
  fetchDocuments()
})
</script>

<template>
  <VCard>
    <VCardTitle class="d-flex justify-space-between align-center">
      <span>
        <VIcon start icon="tabler-file-certificate" />
        {{ t('kyc_documents') }}
      </span>
      <VBtn
        color="primary"
        @click="showUploadDialog = true"
      >
        <VIcon start icon="tabler-upload" />
        {{ t('upload_document') }}
      </VBtn>
    </VCardTitle>

    <VCardText>
      <!-- Documents List -->
      <div v-if="!loading && documents.length">
        <VRow>
          <VCol
            v-for="document in documents"
            :key="document.id"
            cols="12"
            md="6"
            lg="4"
          >
            <VCard variant="outlined" class="h-100">
              <VCardText>
                <div class="d-flex align-center justify-space-between mb-3">
                  <VChip
                    :color="getStatusColor(document.statut)"
                    size="small"
                  >
                    {{ getStatusLabel(document.statut) }}
                  </VChip>
                  <VIcon icon="tabler-file" size="24" />
                </div>
                
                <h6 class="text-h6 mb-2">{{ getTypeLabel(document.type_doc) }}</h6>
                <p class="text-caption text-medium-emphasis mb-3">
                  {{ t('uploaded') }}: {{ new Date(document.created_at).toLocaleDateString() }}
                </p>
                
                <div v-if="document.motif_refus" class="mb-3">
                  <VAlert
                    type="error"
                    variant="tonal"
                    density="compact"
                  >
                    <div class="text-caption">
                      <strong>{{ t('admin_comment') }}:</strong><br>
                      {{ document.motif_refus }}
                    </div>
                  </VAlert>
                </div>
                
                <div class="d-flex gap-2">
                  <VBtn
                    size="small"
                    color="primary"
                    variant="outlined"
                    @click="downloadDocument(document)"
                  >
                    <VIcon start icon="tabler-download" />
                    {{ t('download') }}
                  </VBtn>
                  
                  <VBtn
                    v-if="canDelete(document)"
                    size="small"
                    color="error"
                    variant="outlined"
                    @click="deleteDocument(document)"
                  >
                    <VIcon start icon="tabler-trash" />
                    {{ t('delete') }}
                  </VBtn>
                </div>
              </VCardText>
            </VCard>
          </VCol>
        </VRow>
      </div>

      <!-- Loading State -->
      <div v-else-if="loading" class="text-center py-8">
        <VProgressCircular indeterminate color="primary" />
        <p class="mt-4">{{ t('loading') }}...</p>
      </div>

      <!-- Empty State -->
      <div v-else class="text-center py-8">
        <VIcon icon="tabler-file-x" size="64" class="text-medium-emphasis mb-4" />
        <h6 class="text-h6 mb-2">{{ t('no_documents_uploaded') }}</h6>
        <p class="text-body-2 text-medium-emphasis mb-4">
          {{ t('upload_first_document') }}
        </p>
        <VBtn
          color="primary"
          @click="showUploadDialog = true"
        >
          <VIcon start icon="tabler-upload" />
          {{ t('upload_document') }}
        </VBtn>
      </div>
    </VCardText>

    <!-- Upload Dialog -->
    <VDialog v-model="showUploadDialog" max-width="500">
      <VCard>
        <VCardTitle>{{ t('upload_kyc_document') }}</VCardTitle>
        <VCardText>
          <VForm @submit.prevent="uploadDocument">
            <VSelect
              v-model="uploadForm.type_doc"
              :items="documentTypes"
              :label="t('select_document_type')"
              :error-messages="uploadErrors.type_doc"
              variant="outlined"
              required
              class="mb-4"
            />
            
            <VFileInput
              :label="t('select_file')"
              accept=".pdf,.jpg,.jpeg,.png"
              :error-messages="uploadErrors.fichier"
              variant="outlined"
              required
              @change="handleFileChange"
              class="mb-4"
            />
            
            <VAlert
              type="info"
              variant="tonal"
              density="compact"
              class="mb-4"
            >
              {{ t('allowed_file_types') }}
            </VAlert>
            
            <div class="d-flex gap-3">
              <VBtn
                type="submit"
                color="primary"
                :loading="loading"
                :disabled="!uploadForm.fichier"
              >
                <VIcon start icon="tabler-upload" />
                {{ t('upload') }}
              </VBtn>
              
              <VBtn
                color="secondary"
                variant="outlined"
                @click="showUploadDialog = false"
              >
                {{ t('cancel') }}
              </VBtn>
            </div>
          </VForm>
        </VCardText>
      </VCard>
    </VDialog>
  </VCard>
</template>
