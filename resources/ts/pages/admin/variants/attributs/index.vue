<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useApi } from '@/composables/useApi'
import { useQuickConfirm } from '@/composables/useConfirmAction'
import { useSoftDelete } from '@/composables/useSoftDelete'
import SoftDeleteFilter from '@/components/common/SoftDeleteFilter.vue'
import SoftDeleteActions from '@/components/common/SoftDeleteActions.vue'

// Page meta
definePage({
  meta: {
    action: 'read',
    subject: 'Admin',
    requiresAuth: true,
  },
})

// Composables
const { t } = useI18n()
const router = useRouter()
const { confirmCreate, confirmUpdate, confirmDelete } = useQuickConfirm()

// Types
interface VariantAttribut {
  id: string
  code: string
  nom: string
  actif: boolean
  created_at: string
  deleted_at?: string
}

// Soft delete functionality
const {
  filter: softDeleteFilter,
  getQueryParams: getSoftDeleteQueryParams,
  isSoftDeleted,
  getStatusColor: getSoftDeleteStatusColor,
  getStatusText: getSoftDeleteStatusText
} = useSoftDelete({
  entityName: 'variant attribute',
  apiEndpoint: '/admin/variant-attributs',
  onSuccess: () => fetchAttributs(),
  onError: (error) => console.error('Soft delete error:', error)
})

// State
const attributs = ref<VariantAttribut[]>([])
const loading = ref(false)
const searchQuery = ref('')
const showCreateDialog = ref(false)
const showEditDialog = ref(false)
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

    // Build query parameters with soft delete filter
    const params = new URLSearchParams(getSoftDeleteQueryParams())
    const url = `/admin/variant-attributs?${params.toString()}`

    const { data, error } = await useApi(url)

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

const handleDelete = async (attribut: VariantAttribut) => {
  // Show confirm dialog before deleting
  const confirmed = await confirmDelete('attribut', attribut.nom)
  if (!confirmed) return

  try {
    isDeleting.value = true
    const { data, error } = await useApi(`/admin/variant-attributs/${attribut.id}`, {
      method: 'DELETE'
    })

    if (!error.value && data.value) {
      const response = data.value as any
      if (response.success) {
        attributs.value = attributs.value.filter(a => a.id !== attribut.id)
      }
    }
  } catch (err) {
    console.error('Error deleting variant attribute:', err)
  } finally {
    isDeleting.value = false
  }
}

const handleManageValues = (attribut: VariantAttribut) => {
  router.push(`/admin/variants/attributs/${attribut.id}/valeurs`)
}

const submitCreate = async () => {
  // Show confirm dialog before creating
  const confirmed = await confirmCreate('attribut')
  if (!confirmed) return

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

  // Show confirm dialog before updating
  const confirmed = await confirmUpdate('attribut', selectedAttribut.value.nom)
  if (!confirmed) return

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
        { title: t('variants'), disabled: true },
        { title: t('attributes'), disabled: true }
      ]"
      class="px-0"
    />

    <!-- Page Header -->
    <div class="d-flex justify-space-between align-center mb-6">
      <div>
        <h1 class="text-h4 font-weight-bold mb-1">
          {{ t('variant_attributes') }}
        </h1>
        <p class="text-body-1 text-medium-emphasis">
          {{ t('manage_variant_attributes_desc') }}
        </p>
      </div>
      
      <VBtn
        color="primary"
        prepend-icon="tabler-plus"
        @click="handleCreate"
      >
        {{ t('add_attribute') }}
      </VBtn>
    </div>

    <!-- Search and Filters -->
    <VCard class="mb-6">
      <VCardText>
        <VRow>
          <VCol cols="12" md="8">
            <VTextField
              v-model="searchQuery"
              :placeholder="t('search_attributes')"
              prepend-inner-icon="tabler-search"
              clearable
              variant="outlined"
              density="compact"
            />
          </VCol>
          <VCol cols="12" md="4">
            <SoftDeleteFilter
              v-model="softDeleteFilter"
              @update:model-value="fetchAttributs"
            />
          </VCol>
        </VRow>
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
          <h3 class="text-h6 mb-2">{{ t('no_attributes_found') }}</h3>
          <p class="text-body-2 text-medium-emphasis">
            {{ searchQuery ? t('try_adjusting_search') : t('create_first_variant_attribute') }}
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
                  <div class="d-flex flex-column gap-1">
                    <VChip
                      :color="attribut.actif ? 'success' : 'error'"
                      size="small"
                      variant="flat"
                    >
                      {{ attribut.actif ? t('active') : t('inactive') }}
                    </VChip>
                    <VChip
                      :color="getSoftDeleteStatusColor(attribut)"
                      size="small"
                      variant="elevated"
                    >
                      {{ getSoftDeleteStatusText(attribut) }}
                    </VChip>
                  </div>
                </div>
              </VCardText>
              
              <VCardActions class="pt-0">
                <VBtn
                  size="small"
                  variant="text"
                  color="primary"
                  @click="handleManageValues(attribut)"
                >
                  {{ t('manage_values') }}
                </VBtn>

                <VSpacer />

                <SoftDeleteActions
                  :item="attribut"
                  entity-name="variant attribute"
                  api-endpoint="/admin/variant-attributs"
                  item-name-field="nom"
                  @deleted="fetchAttributs"
                  @restored="fetchAttributs"
                  @permanently-deleted="fetchAttributs"
                  @edit="handleEdit"
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
        <VCardTitle>{{ t('create_variant_attribute') }}</VCardTitle>
        <VCardText>
          <VForm @submit.prevent="submitCreate">
            <VTextField
              v-model="formData.code"
              :label="t('code')"
              :placeholder="t('code_placeholder')"
              :error-messages="formErrors.code"
              variant="outlined"
              class="mb-4"
              required
            />
            
            <VTextField
              v-model="formData.nom"
              :label="t('name')"
              :placeholder="t('name_placeholder')"
              :error-messages="formErrors.nom"
              variant="outlined"
              class="mb-4"
              required
            />
            
            <VCheckbox
              v-model="formData.actif"
              :label="t('active')"
            />
          </VForm>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn type="button" @click="showCreateDialog = false">{{ t('cancel') }}</VBtn>
          <VBtn color="primary" type="button" @click="submitCreate">{{ t('create') }}</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Edit Dialog -->
    <VDialog v-model="showEditDialog" max-width="500">
      <VCard>
        <VCardTitle>{{ t('edit_variant_attribute') }}</VCardTitle>
        <VCardText>
          <VForm @submit.prevent="submitEdit">
            <VTextField
              v-model="formData.code"
              :label="t('code')"
              :error-messages="formErrors.code"
              variant="outlined"
              class="mb-4"
              required
            />
            
            <VTextField
              v-model="formData.nom"
              :label="t('name')"
              :error-messages="formErrors.nom"
              variant="outlined"
              class="mb-4"
              required
            />
            
            <VCheckbox
              v-model="formData.actif"
              :label="t('active')"
            />
          </VForm>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn type="button" @click="showEditDialog = false">{{ t('cancel') }}</VBtn>
          <VBtn color="primary" type="button" @click="submitEdit">{{ t('update') }}</VBtn>
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
