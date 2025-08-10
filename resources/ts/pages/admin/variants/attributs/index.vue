<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useApi } from '@/composables/useApi'

// Page meta
definePage({
  meta: {
    action: 'read',
    subject: 'Admin',
    requiresAuth: true,
  },
})

// Types
interface VariantAttribut {
  id: string
  code: string
  nom: string
  actif: boolean
  created_at: string
}

// Composables
const router = useRouter()

// State
const attributs = ref<VariantAttribut[]>([])
const loading = ref(false)
const searchQuery = ref('')
const showCreateDialog = ref(false)
const showEditDialog = ref(false)
const showDeleteDialog = ref(false)
const selectedAttribut = ref<VariantAttribut | null>(null)
const isDeleting = ref(false)

// Form data
const formData = ref({
  code: '',
  nom: '',
  actif: true
})

const formErrors = ref<Record<string, string[]>>({})

// Computed
const filteredAttributs = computed(() => {
  if (!searchQuery.value) return attributs.value
  
  const query = searchQuery.value.toLowerCase()
  return attributs.value.filter(attr => 
    attr.code.toLowerCase().includes(query) ||
    attr.nom.toLowerCase().includes(query)
  )
})

// Methods
const fetchAttributs = async () => {
  try {
    loading.value = true
    const { data, error } = await useApi('/admin/variant-attributs')
    
    if (!error.value && data.value) {
      const response = data.value as any
      if (response.success) {
        attributs.value = response.data
      }
    }
  } catch (err) {
    console.error('Error fetching variant attributes:', err)
  } finally {
    loading.value = false
  }
}

const handleCreate = () => {
  formData.value = {
    code: '',
    nom: '',
    actif: true
  }
  formErrors.value = {}
  showCreateDialog.value = true
}

const handleEdit = (attribut: VariantAttribut) => {
  selectedAttribut.value = attribut
  formData.value = {
    code: attribut.code,
    nom: attribut.nom,
    actif: attribut.actif
  }
  formErrors.value = {}
  showEditDialog.value = true
}

const handleDelete = (attribut: VariantAttribut) => {
  selectedAttribut.value = attribut
  showDeleteDialog.value = true
}

const handleManageValues = (attribut: VariantAttribut) => {
  router.push(`/admin/variants/attributs/${attribut.id}/valeurs`)
}

const submitCreate = async () => {
  try {
    formErrors.value = {}
    const { data, error } = await useApi('/admin/variant-attributs', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(formData.value)
    })
    
    if (!error.value && data.value) {
      const response = data.value as any
      if (response.success) {
        attributs.value.push(response.data)
        showCreateDialog.value = false
      } else {
        if (response.errors) {
          formErrors.value = response.errors
        }
      }
    }
  } catch (err) {
    console.error('Error creating variant attribute:', err)
  }
}

const submitEdit = async () => {
  if (!selectedAttribut.value) return
  
  try {
    formErrors.value = {}
    const { data, error } = await useApi(`/admin/variant-attributs/${selectedAttribut.value.id}`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(formData.value)
    })
    
    if (!error.value && data.value) {
      const response = data.value as any
      if (response.success) {
        const index = attributs.value.findIndex(a => a.id === selectedAttribut.value!.id)
        if (index > -1) {
          attributs.value[index] = response.data
        }
        showEditDialog.value = false
      } else {
        if (response.errors) {
          formErrors.value = response.errors
        }
      }
    }
  } catch (err) {
    console.error('Error updating variant attribute:', err)
  }
}

const confirmDelete = async () => {
  if (!selectedAttribut.value) return
  
  try {
    isDeleting.value = true
    const { data, error } = await useApi(`/admin/variant-attributs/${selectedAttribut.value.id}`, {
      method: 'DELETE'
    })
    
    if (!error.value && data.value) {
      const response = data.value as any
      if (response.success) {
        attributs.value = attributs.value.filter(a => a.id !== selectedAttribut.value!.id)
        showDeleteDialog.value = false
      }
    }
  } catch (err) {
    console.error('Error deleting variant attribute:', err)
  } finally {
    isDeleting.value = false
  }
}

const toggleStatus = async (attribut: VariantAttribut) => {
  try {
    const { data, error } = await useApi(`/admin/variant-attributs/${attribut.id}/toggle-status`, {
      method: 'POST'
    })
    
    if (!error.value && data.value) {
      const response = data.value as any
      if (response.success) {
        const index = attributs.value.findIndex(a => a.id === attribut.id)
        if (index > -1) {
          attributs.value[index] = response.data
        }
      }
    }
  } catch (err) {
    console.error('Error toggling attribute status:', err)
  }
}

// Lifecycle
onMounted(() => {
  fetchAttributs()
})
</script>

<template>
  <div>
    <!-- Breadcrumbs -->
    <VBreadcrumbs
      :items="[
        { title: 'Admin', disabled: true },
        { title: 'Variants', disabled: true },
        { title: 'Attributes', disabled: true }
      ]"
      class="px-0"
    />

    <!-- Page Header -->
    <div class="d-flex justify-space-between align-center mb-6">
      <div>
        <h1 class="text-h4 font-weight-bold mb-1">
          Variant Attributes
        </h1>
        <p class="text-body-1 text-medium-emphasis">
          Manage variant attributes like Size, Color, Material
        </p>
      </div>
      
      <VBtn
        color="primary"
        prepend-icon="tabler-plus"
        @click="handleCreate"
      >
        Add Attribute
      </VBtn>
    </div>

    <!-- Search -->
    <VCard class="mb-6">
      <VCardText>
        <VTextField
          v-model="searchQuery"
          placeholder="Search attributes..."
          prepend-inner-icon="tabler-search"
          clearable
          variant="outlined"
          density="compact"
        />
      </VCardText>
    </VCard>

    <!-- Attributes List -->
    <VCard>
      <VCardText>
        <div v-if="loading" class="text-center py-8">
          <VProgressCircular indeterminate color="primary" />
        </div>
        
        <div v-else-if="filteredAttributs.length === 0" class="text-center py-8">
          <VIcon icon="tabler-palette" size="64" color="grey" class="mb-4" />
          <h3 class="text-h6 mb-2">No attributes found</h3>
          <p class="text-body-2 text-medium-emphasis">
            {{ searchQuery ? 'Try adjusting your search' : 'Create your first variant attribute' }}
          </p>
        </div>
        
        <VRow v-else>
          <VCol
            v-for="attribut in filteredAttributs"
            :key="attribut.id"
            cols="12"
            sm="6"
            md="4"
            lg="3"
          >
            <VCard
              class="attribute-card h-100"
              :class="{ 'opacity-60': !attribut.actif }"
              elevation="2"
            >
              <VCardText class="pb-2">
                <div class="d-flex justify-space-between align-start mb-3">
                  <div>
                    <h4 class="text-h6 font-weight-bold">{{ attribut.nom }}</h4>
                    <p class="text-caption text-medium-emphasis">{{ attribut.code }}</p>
                  </div>
                  <VChip
                    :color="attribut.actif ? 'success' : 'error'"
                    size="small"
                    variant="flat"
                  >
                    {{ attribut.actif ? 'Active' : 'Inactive' }}
                  </VChip>
                </div>
              </VCardText>
              
              <VCardActions class="pt-0">
                <VBtn
                  size="small"
                  variant="text"
                  color="primary"
                  @click="handleManageValues(attribut)"
                >
                  Manage Values
                </VBtn>
                
                <VSpacer />
                
                <VBtn
                  icon="tabler-edit"
                  size="small"
                  variant="text"
                  @click="handleEdit(attribut)"
                />
                <VBtn
                  :icon="attribut.actif ? 'tabler-eye-off' : 'tabler-eye'"
                  size="small"
                  variant="text"
                  @click="toggleStatus(attribut)"
                />
                <VBtn
                  icon="tabler-trash"
                  size="small"
                  variant="text"
                  color="error"
                  @click="handleDelete(attribut)"
                />
              </VCardActions>
            </VCard>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Create Dialog -->
    <VDialog v-model="showCreateDialog" max-width="500">
      <VCard>
        <VCardTitle>Create Variant Attribute</VCardTitle>
        <VCardText>
          <VForm @submit.prevent="submitCreate">
            <VTextField
              v-model="formData.code"
              label="Code"
              placeholder="e.g., size, color"
              :error-messages="formErrors.code"
              variant="outlined"
              class="mb-4"
              required
            />
            
            <VTextField
              v-model="formData.nom"
              label="Name"
              placeholder="e.g., Size, Color"
              :error-messages="formErrors.nom"
              variant="outlined"
              class="mb-4"
              required
            />
            
            <VCheckbox
              v-model="formData.actif"
              label="Active"
            />
          </VForm>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn @click="showCreateDialog = false">Cancel</VBtn>
          <VBtn color="primary" @click="submitCreate">Create</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Edit Dialog -->
    <VDialog v-model="showEditDialog" max-width="500">
      <VCard>
        <VCardTitle>Edit Variant Attribute</VCardTitle>
        <VCardText>
          <VForm @submit.prevent="submitEdit">
            <VTextField
              v-model="formData.code"
              label="Code"
              :error-messages="formErrors.code"
              variant="outlined"
              class="mb-4"
              required
            />
            
            <VTextField
              v-model="formData.nom"
              label="Name"
              :error-messages="formErrors.nom"
              variant="outlined"
              class="mb-4"
              required
            />
            
            <VCheckbox
              v-model="formData.actif"
              label="Active"
            />
          </VForm>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn @click="showEditDialog = false">Cancel</VBtn>
          <VBtn color="primary" @click="submitEdit">Update</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Delete Dialog -->
    <VDialog v-model="showDeleteDialog" max-width="400">
      <VCard>
        <VCardTitle>Delete Attribute</VCardTitle>
        <VCardText>
          Are you sure you want to delete the attribute "{{ selectedAttribut?.nom }}"?
          This action cannot be undone.
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn @click="showDeleteDialog = false">Cancel</VBtn>
          <VBtn
            color="error"
            :loading="isDeleting"
            @click="confirmDelete"
          >
            Delete
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.attribute-card {
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.attribute-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}
</style>
