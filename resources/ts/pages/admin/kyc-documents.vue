<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuth } from '@/composables/useAuth'
import { useApi } from '@/composables/useApi'
import { useNotifications } from '@/composables/useNotifications'
import { normalizeFromResponse } from '@/services/ErrorService'

definePage({
  meta: {
    requiresAuth: true,
    requiresRole: 'admin',
  },
})

const { t } = useI18n()
const { hasPermission } = useAuth()
const { showSuccess, showError, showConfirm, snackbar, confirmDialog } = useNotifications()

// Types
type KycDocument = {
  id: string
  utilisateur_id: string
  type_doc: 'cni' | 'passport' | 'rib' | 'contrat'
  url_fichier: string
  statut: 'en_attente' | 'valide' | 'refuse'
  motif_refus?: string
  utilisateur: {
    id: string
    nom_complet: string
    email: string
  }
  created_at: string
  updated_at: string
}

// State
const loading = ref(false)
const error = ref<string | null>(null)
const documents = ref<KycDocument[]>([])
const selectedDocument = ref<KycDocument | null>(null)
const users = ref<Array<{ title: string; value: string }>>([])
const loadingUsers = ref(false)

// Dialogs
const showUploadDialog = ref(false)
const showReviewDialog = ref(false)

// Pagination
const pagination = ref({
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 0
})

// Filters
const filters = ref({
  search: '',
  type_doc: '',
  statut: '',
  user_id: ''
})

// Form data
const uploadForm = ref({
  utilisateur_id: '',
  type_doc: 'cni' as KycDocument['type_doc'],
  fichier: null as File | null
})

const reviewForm = ref({
  statut: 'en_attente' as KycDocument['statut'],
  motif_refus: ''
})

// Options
const documentTypes = [
  { title: t('all_types'), value: '' },
  { title: 'CNI', value: 'cni' },
  { title: 'Passeport', value: 'passport' },
  { title: 'RIB', value: 'rib' },
  { title: 'Contrat', value: 'contrat' },
]

const statusOptions = [
  { title: t('all_status'), value: '' },
  { title: t('pending'), value: 'en_attente' },
  { title: t('approved'), value: 'valide' },
  { title: t('rejected'), value: 'refuse' },
]

const reviewStatusOptions = [
  { title: t('pending'), value: 'en_attente' },
  { title: t('approved'), value: 'valide' },
  { title: t('rejected'), value: 'refuse' },
]

// Computed
const filteredDocuments = computed(() => {
  return documents.value.filter(doc => {
    const matchesSearch = !filters.value.search || 
      doc.utilisateur.nom_complet.toLowerCase().includes(filters.value.search.toLowerCase()) ||
      doc.utilisateur.email.toLowerCase().includes(filters.value.search.toLowerCase())
    
    const matchesType = !filters.value.type_doc || doc.type_doc === filters.value.type_doc
    const matchesStatus = !filters.value.statut || doc.statut === filters.value.statut
    
    return matchesSearch && matchesType && matchesStatus
  })
})

// Methods
const fetchUsers = async () => {
  try {
    loadingUsers.value = true
    console.log('🔍 Fetching users...')

    // Try to fetch users with error handling
    const baseUrl = import.meta.env.VITE_API_BASE_URL || '/api'
    const usersUrl = `${baseUrl}/admin/users?per_page=1000`

    console.log('🔍 Fetching users from:', usersUrl)

    const response = await fetch(usersUrl, {
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      }
    })

    console.log('🔍 Users API response status:', response.status)

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}: ${response.statusText}`)
    }

    const responseData = await response.json()
    console.log('🔍 Users API response data:', responseData)

    // Handle the correct API response format
    let usersData = []
    if (responseData.users && Array.isArray(responseData.users)) {
      usersData = responseData.users
      console.log('✅ Found users in responseData.users:', usersData.length)
    } else if (responseData.data && Array.isArray(responseData.data)) {
      usersData = responseData.data
      console.log('✅ Found users in responseData.data:', usersData.length)
    } else if (Array.isArray(responseData)) {
      usersData = responseData
      console.log('✅ Found users in responseData array:', usersData.length)
    } else {
      console.error('❌ Unexpected users data format:', responseData)
      console.error('❌ Expected: { users: [...] } or { data: [...] } or [...]')
      showError('Unexpected users data format')
      return
    }

    console.log('🔍 Processing users data:', usersData)

    if (usersData.length === 0) {
      console.log('⚠️ No users found in response')
      users.value = []
      return
    }

    users.value = usersData.map((user: any) => {
      const userOption = {
        title: `${user.nom_complet} (${user.email}) - ${user.kyc_statut}`,
        value: user.id
      }
      console.log('🔍 Created user option:', userOption)
      return userOption
    })

    console.log('✅ Users loaded successfully:', users.value.length, users.value)

  } catch (err: any) {
    console.error('❌ Users fetch error:', err)
    showError('Failed to load users: ' + err.message)
  } finally {
    loadingUsers.value = false
  }
}

const fetchDocuments = async (page = 1) => {
  try {
    loading.value = true
    error.value = null

    const params = new URLSearchParams({
      page: page.toString(),
      per_page: pagination.value.per_page.toString(),
    })

    if (filters.value.search) params.set('search', filters.value.search)
    if (filters.value.type_doc) params.set('type_doc', filters.value.type_doc)
    if (filters.value.statut) params.set('statut', filters.value.statut)

    const url = `/admin/kyc-documents?${params.toString()}`
    const { data, error: apiError } = await useApi<any>(url)

    if (apiError.value) {
      error.value = apiError.value.message
      showError(apiError.value.message)
      console.error('KYC documents fetch error:', apiError.value)
    } else if (data.value) {
      documents.value = data.value.data || []
      pagination.value = {
        current_page: data.value.current_page || 1,
        last_page: data.value.last_page || 1,
        per_page: data.value.per_page || 15,
        total: data.value.total || 0
      }
      console.log('✅ KYC documents loaded successfully:', documents.value.length)
    }
  } catch (err: any) {
    console.error('KYC documents fetch error:', err)
    const errorMessage = err.message || 'Failed to load KYC documents'
    error.value = errorMessage
    showError(errorMessage)
  } finally {
    loading.value = false
  }
}

const uploadDocument = async () => {
  if (!uploadForm.value.fichier) {
    showError('Please select a file')
    return
  }

  if (!uploadForm.value.utilisateur_id) {
    showError('Please select a user')
    return
  }

  if (!uploadForm.value.type_doc) {
    showError('Please select a document type')
    return
  }

  try {
    loading.value = true

    console.log('🔍 Upload form data:', {
      utilisateur_id: uploadForm.value.utilisateur_id,
      type_doc: uploadForm.value.type_doc,
      fichier: uploadForm.value.fichier?.name
    })

    const formData = new FormData()
    formData.append('utilisateur_id', uploadForm.value.utilisateur_id)
    formData.append('type_doc', uploadForm.value.type_doc)
    formData.append('fichier', uploadForm.value.fichier)

    // Debug FormData contents
    console.log('🔍 FormData contents:')
    for (const [key, value] of formData.entries()) {
      console.log(`  ${key}:`, value)
    }

    // Use direct fetch for file upload instead of useApi
    const baseUrl = import.meta.env.VITE_API_BASE_URL || '/api'
    const uploadUrl = `${baseUrl}/admin/kyc-documents`

    console.log('🔍 Upload URL:', uploadUrl)

    const response = await fetch(uploadUrl, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
        'Accept': 'application/json',
        // Don't set Content-Type for FormData - let browser set it with boundary
      },
      body: formData,
    })

    console.log('🔍 Upload response status:', response.status)

    if (!response.ok) {
      const nerr = await normalizeFromResponse(response)
      console.error('❌ Upload error response:', nerr)
      showError(nerr.message) // e.g., 409 -> "User already has a document of this type"
      return
    }

    const responseData = await response.json()
    console.log('✅ Upload success response:', responseData)

    showUploadDialog.value = false
    resetUploadForm()
    await fetchDocuments()
    showSuccess(t('document_uploaded_successfully'))

  } catch (err: any) {
    console.error('❌ Upload document error:', err)
    showError(err.message || t('failed_to_upload_document'))
  } finally {
    loading.value = false
  }
}

const openReviewDialog = (document: KycDocument) => {
  selectedDocument.value = document
  reviewForm.value = {
    statut: document.statut,
    motif_refus: document.motif_refus || ''
  }
  showReviewDialog.value = true
}

const reviewDocument = async () => {
  if (!selectedDocument.value) return

  try {
    loading.value = true

    const { data, error: apiError } = await useApi<any>(`/admin/kyc-documents/${selectedDocument.value.id}`, {
      method: 'PUT',
      body: JSON.stringify({
        statut: reviewForm.value.statut,
        motif_refus: reviewForm.value.motif_refus,
      }),
      headers: {
        'Content-Type': 'application/json',
      },
    })

    if (apiError.value) {
      showError(apiError.value.message)
      console.error('Review document error:', apiError.value)
    } else if (data.value) {
      showReviewDialog.value = false
      resetReviewForm()
      await fetchDocuments()
      showSuccess(t('document_reviewed_successfully'))
    }
  } catch (err: any) {
    showError(err.message || t('failed_to_review_document'))
    console.error('Review document error:', err)
  } finally {
    loading.value = false
  }
}

const downloadDocument = async (doc: KycDocument) => {
  try {
    console.log('🔍 Downloading document:', doc.id)

    const baseUrl = import.meta.env.VITE_API_BASE_URL || '/api'
    const downloadUrl = `${baseUrl}/admin/kyc-documents/${doc.id}/download`

    console.log('🔍 Download URL:', downloadUrl)

    const response = await fetch(downloadUrl, {
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
      },
    })

    console.log('🔍 Download response status:', response.status)
    console.log('🔍 Download response headers:', Object.fromEntries(response.headers.entries()))

    if (response.ok) {
      const blob = await response.blob()
      console.log('🔍 Downloaded blob:', { size: blob.size, type: blob.type })

      // Get filename from Content-Disposition header or create one
      const contentDisposition = response.headers.get('Content-Disposition')
      let filename = `kyc-${doc.type_doc}-${doc.utilisateur.nom_complet}`

      if (contentDisposition) {
        const filenameMatch = contentDisposition.match(/filename="([^"]+)"/)
        if (filenameMatch) {
          filename = filenameMatch[1]
        }
      } else {
        // Determine extension from blob type or file path
        const fileExtension = getFileExtensionFromPath(doc.url_fichier) || getExtensionFromMimeType(blob.type)
        filename += `.${fileExtension}`
      }

      console.log('🔍 Download filename:', filename)

      const url = window.URL.createObjectURL(blob)
      const a = document.createElement('a')
      a.href = url
      a.download = filename
      document.body.appendChild(a)
      a.click()
      window.URL.revokeObjectURL(url)
      document.body.removeChild(a)

      console.log('✅ Download completed successfully')
    } else {
      const errorText = await response.text()
      console.error('❌ Download failed:', response.status, errorText)
      showError('Failed to download document')
    }
  } catch (err) {
    console.error('❌ Download error:', err)
    showError('Failed to download document')
  }
}

// Helper functions
const getFileExtensionFromPath = (filePath: string): string | null => {
  const match = filePath.match(/\.([^.]+)$/)
  return match ? match[1] : null
}

const getExtensionFromMimeType = (mimeType: string): string => {
  switch (mimeType) {
    case 'application/pdf': return 'pdf'
    case 'image/jpeg': return 'jpg'
    case 'image/png': return 'png'
    default: return 'bin'
  }
}

const viewDocument = (doc: KycDocument) => {
  const baseUrl = import.meta.env.VITE_API_BASE_URL || '/api'
  const viewUrl = `${baseUrl}/admin/kyc-documents/${doc.id}/file`

  console.log('🔍 Opening view URL:', viewUrl)

  // Simple approach: open in new tab
  window.open(viewUrl, '_blank')
}

const deleteDocument = (document: KycDocument) => {
  showConfirm(
    t('confirm_delete'),
    t('confirm_delete_document_desc', { type: getTypeLabel(document.type_doc), user: document.utilisateur.nom_complet }),
    async () => {
      try {
        loading.value = true

        const { data, error: apiError } = await useApi<any>(`/admin/kyc-documents/${document.id}`, {
          method: 'DELETE',
        })

        if (apiError.value) {
          showError(apiError.value.message)
          console.error('Delete document error:', apiError.value)
        } else if (data.value) {
          await fetchDocuments()
          showSuccess(t('document_deleted_successfully'))
        }
      } catch (err: any) {
        showError(err.message || t('failed_to_delete_document'))
        console.error('Delete document error:', err)
      } finally {
        loading.value = false
      }
    }
  )
}

// Form helpers
const resetUploadForm = () => {
  uploadForm.value = {
    utilisateur_id: '',
    type_doc: 'cni',
    fichier: null
  }
}

const resetReviewForm = () => {
  reviewForm.value = {
    statut: 'en_attente',
    motif_refus: ''
  }
  selectedDocument.value = null
}

const handleFileChange = (event: Event) => {
  const target = event.target as HTMLInputElement
  if (target.files && target.files[0]) {
    uploadForm.value.fichier = target.files[0]
    console.log('🔍 File selected:', {
      name: target.files[0].name,
      size: target.files[0].size,
      type: target.files[0].type
    })
  } else {
    console.log('❌ No file selected')
  }
}

const getStatusColor = (status: string) => {
  switch (status) {
    case 'valide': return 'success'
    case 'refuse': return 'error'
    case 'en_attente': return 'warning'
    default: return 'default'
  }
}

const getTypeLabel = (type: string) => {
  switch (type) {
    case 'cni': return 'CNI'
    case 'passport': return 'Passeport'
    case 'rib': return 'RIB'
    case 'contrat': return 'Contrat'
    default: return type
  }
}

// Lifecycle
onMounted(async () => {
  await fetchDocuments()
  await fetchUsers()
})
</script>

<template>
  <div>
    <!-- Page Header -->
    <div class="d-flex justify-space-between align-center mb-6">
      <div>
        <h1 class="text-h4 font-weight-bold mb-1">{{ t('kyc_documents') }}</h1>
        <p class="text-body-1 text-medium-emphasis">{{ t('manage_kyc_documents_desc') }}</p>
      </div>
      <VBtn
        color="primary"
        @click="showUploadDialog = true"
      >
        <VIcon start icon="tabler-upload" />
        {{ t('upload_document') }}
      </VBtn>
    </div>

    <!-- Filters -->
    <VCard class="mb-6">
      <VCardText>
        <VRow>
          <VCol cols="12" md="3">
            <VTextField
              v-model="filters.search"
              :label="t('search')"
              :placeholder="t('search_users')"
              prepend-inner-icon="tabler-search"
              clearable
              @input="fetchDocuments(1)"
            />
          </VCol>
          <VCol cols="12" md="3">
            <VSelect
              v-model="filters.type_doc"
              :items="documentTypes"
              :label="t('document_type')"
              clearable
              @update:model-value="fetchDocuments(1)"
            />
          </VCol>
          <VCol cols="12" md="2">
            <VSelect
              v-model="filters.statut"
              :items="statusOptions"
              :label="t('status')"
              clearable
              @update:model-value="fetchDocuments(1)"
            />
          </VCol>
          <VCol cols="12" md="3">
            <VSelect
              v-model="filters.user_id"
              :items="users"
              :label="t('filter_by_user')"
              :loading="loadingUsers"
              clearable
              @update:model-value="fetchDocuments(1)"
            />
          </VCol>
          <VCol cols="12" md="2" class="d-flex align-center">
            <VBtn
              variant="outlined"
              @click="fetchDocuments(1)"
              :loading="loading"
            >
              <VIcon start icon="tabler-refresh" />
              {{ t('refresh') }}
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Documents Table -->
    <VCard>
      <VCardText>
        <VTable v-if="!loading && documents.length">
          <thead>
            <tr>
              <th>{{ t('user') }}</th>
              <th>{{ t('document_type') }}</th>
              <th>{{ t('status') }}</th>
              <th>{{ t('uploaded') }}</th>
              <th>{{ t('actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="document in filteredDocuments" :key="document.id">
              <td>
                <div>
                  <div class="font-weight-medium">{{ document.utilisateur.nom_complet }}</div>
                  <div class="text-caption text-medium-emphasis">{{ document.utilisateur.email }}</div>
                </div>
              </td>
              <td>
                <VChip size="small" variant="tonal">
                  {{ getTypeLabel(document.type_doc) }}
                </VChip>
              </td>
              <td>
                <VChip
                  :color="getStatusColor(document.statut)"
                  size="small"
                >
                  {{ document.statut }}
                </VChip>
                <div v-if="document.motif_refus" class="text-caption text-error mt-1">
                  {{ document.motif_refus }}
                </div>
              </td>
              <td>{{ new Date(document.created_at).toLocaleDateString() }}</td>
              <td>
                <div class="d-flex gap-2">
                  <VBtn icon size="small" color="success" variant="text" @click="viewDocument(document)">
                    <VIcon icon="tabler-eye" />
                    <VTooltip activator="parent">{{ t('view') }}</VTooltip>
                  </VBtn>
                  <VBtn icon size="small" color="primary" variant="text" @click="downloadDocument(document)">
                    <VIcon icon="tabler-download" />
                    <VTooltip activator="parent">{{ t('download') }}</VTooltip>
                  </VBtn>
                  <VBtn icon size="small" color="info" variant="text" @click="openReviewDialog(document)">
                    <VIcon icon="tabler-edit" />
                    <VTooltip activator="parent">{{ t('review') }}</VTooltip>
                  </VBtn>
                  <VBtn icon size="small" color="error" variant="text" @click="deleteDocument(document)">
                    <VIcon icon="tabler-trash" />
                    <VTooltip activator="parent">{{ t('delete') }}</VTooltip>
                  </VBtn>
                </div>
              </td>
            </tr>
          </tbody>
        </VTable>

        <!-- Loading State -->
        <div v-else-if="loading" class="text-center py-8">
          <VProgressCircular indeterminate color="primary" />
          <p class="mt-4">{{ t('loading') }}...</p>
        </div>

        <!-- Empty State -->
        <div v-else-if="!documents.length" class="text-center py-8">
          <VIcon icon="tabler-file-x" size="64" class="text-disabled mb-4" />
          <h3 class="text-h6 mb-2">{{ t('no_documents_found') }}</h3>
          <p class="text-body-2 text-medium-emphasis">{{ t('no_documents_desc') }}</p>
        </div>

        <!-- Error State -->
        <div v-else-if="error" class="text-center py-8">
          <VIcon icon="tabler-alert-circle" size="64" class="text-error mb-4" />
          <h3 class="text-h6 mb-2">{{ t('error_loading_documents') }}</h3>
          <p class="text-body-2 text-medium-emphasis">{{ error }}</p>
          <VBtn color="primary" variant="outlined" @click="fetchDocuments()" class="mt-4">
            {{ t('try_again') }}
          </VBtn>
        </div>
      </VCardText>
    </VCard>

    <!-- Upload Document Dialog -->
    <VDialog v-model="showUploadDialog" max-width="600">
      <VCard>
        <VCardTitle>{{ t('upload_kyc_document') }}</VCardTitle>
        <VCardText>
          <VForm @submit.prevent="uploadDocument">
            <VSelect
              v-model="uploadForm.utilisateur_id"
              :items="users"
              :label="t('select_user')"
              :placeholder="t('choose_user')"
              :loading="loadingUsers"
              required
              clearable
              class="mb-4"
            >
              <template #no-data>
                <VListItem>
                  <VListItemTitle>{{ t('no_users_found') }}</VListItemTitle>
                </VListItem>
              </template>
            </VSelect>
            <VSelect
              v-model="uploadForm.type_doc"
              :items="documentTypes.slice(1)"
              :label="t('document_type')"
              required
              class="mb-4"
            />
            <VFileInput
              :label="t('select_file')"
              accept=".pdf,.jpg,.jpeg,.png"
              @change="handleFileChange"
              required
              class="mb-4"
            />
            <p class="text-caption text-medium-emphasis">
              {{ t('supported_formats') }}: PDF, JPG, PNG ({{ t('max_size') }}: 5MB)
            </p>
          </VForm>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="outlined" @click="showUploadDialog = false">{{ t('cancel') }}</VBtn>
          <VBtn color="primary" @click="uploadDocument" :loading="loading">{{ t('upload') }}</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Review Document Dialog -->
    <VDialog v-model="showReviewDialog" max-width="600">
      <VCard>
        <VCardTitle>{{ t('review_document') }}</VCardTitle>
        <VCardText v-if="selectedDocument">
          <div class="mb-4">
            <h4 class="text-h6 mb-2">{{ t('document_info') }}</h4>
            <p><strong>{{ t('user') }}:</strong> {{ selectedDocument.utilisateur.nom_complet }}</p>
            <p><strong>{{ t('type') }}:</strong> {{ getTypeLabel(selectedDocument.type_doc) }}</p>
            <p><strong>{{ t('current_status') }}:</strong> {{ selectedDocument.statut }}</p>
          </div>
          
          <VForm @submit.prevent="reviewDocument">
            <VSelect
              v-model="reviewForm.statut"
              :items="reviewStatusOptions"
              :label="t('new_status')"
              required
              class="mb-4"
            />
            <VTextarea
              v-model="reviewForm.motif_refus"
              :label="t('rejection_reason')"
              :placeholder="t('enter_rejection_reason')"
              :required="reviewForm.statut === 'refuse'"
              rows="3"
              class="mb-4"
            />
          </VForm>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="outlined" @click="showReviewDialog = false">{{ t('cancel') }}</VBtn>
          <VBtn color="primary" @click="reviewDocument" :loading="loading">{{ t('save') }}</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Confirmation Dialog -->
    <VDialog v-model="confirmDialog.show" max-width="500">
      <VCard class="text-center px-10 py-6">
        <VCardText>
          <VBtn
            icon
            variant="outlined"
            color="warning"
            class="my-4"
            style="block-size: 88px; inline-size: 88px; pointer-events: none;"
          >
            <span class="text-5xl">!</span>
          </VBtn>
          <h6 class="text-lg font-weight-medium">
            {{ confirmDialog.title }}
          </h6>
          <p class="mt-2">{{ confirmDialog.message }}</p>
        </VCardText>
        <VCardText class="d-flex align-center justify-center gap-2">
          <VBtn variant="elevated" @click="confirmDialog.onConfirm">
            {{ confirmDialog.confirmText }}
          </VBtn>
          <VBtn color="secondary" variant="tonal" @click="confirmDialog.onCancel">
            {{ confirmDialog.cancelText }}
          </VBtn>
        </VCardText>
      </VCard>
    </VDialog>

    <!-- Success/Error Snackbar -->
    <VSnackbar
      v-model="snackbar.show"
      :color="snackbar.color"
      :timeout="snackbar.timeout"
      location="top end"
    >
      {{ snackbar.message }}
    </VSnackbar>

    <!-- Confirmation Dialog -->
    <VDialog v-model="confirmDialog.show" max-width="500">
      <VCard class="text-center px-10 py-6">
        <VCardText>
          <VIcon icon="tabler-help" size="50" class="text-warning mb-4" />
          <h6 class="text-h6 mb-4">{{ confirmDialog.title }}</h6>
          <p class="text-body-1 mb-6">{{ confirmDialog.message }}</p>
          <div class="d-flex gap-4 justify-center">
            <VBtn color="error" @click="confirmDialog.onConfirm">{{ confirmDialog.confirmText }}</VBtn>
            <VBtn variant="outlined" @click="confirmDialog.onCancel">{{ confirmDialog.cancelText }}</VBtn>
          </div>
        </VCardText>
      </VCard>
    </VDialog>
  </div>
</template>
