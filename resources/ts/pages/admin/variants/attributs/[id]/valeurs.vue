<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useApi } from '@/composables/useApi'
import { useQuickConfirm } from '@/composables/useConfirmAction'
import ColorPicker from '@/components/common/ColorPicker.vue'

// Page meta
definePage({
  meta: {
    requiresAuth: true,
    requiresRole: 'admin',
  },
})

// Composables
const { t } = useI18n()
const route = useRoute()
const { confirmCreate, confirmUpdate, confirmDelete } = useQuickConfirm()
const router = useRouter()

// Types
interface VariantAttribut {
  id: string
  code: string
  nom: string
  actif: boolean
}

interface VariantValeur {
  id: string
  attribut_id: string
  code: string
  libelle: string
  actif: boolean
  ordre: number
  hex_color?: string
}

// State
const attribut = ref<VariantAttribut | null>(null)
const valeurs = ref<VariantValeur[]>([])
const loading = ref(false)
const searchQuery = ref('')
const showCreateDialog = ref(false)
const showEditDialog = ref(false)
const selectedValeur = ref<VariantValeur | null>(null)
const isDeleting = ref(false)

// Form data
const formData = ref({
  code: '',
  libelle: '',
  actif: true,
  ordre: 0,
  hex_color: ''
})

const formErrors = ref<Record<string, string[]>>({})

// Computed
const attributId = computed(() => route.params.id as string)

const filteredValeurs = computed(() => {
  if (!searchQuery.value) return valeurs.value

  const query = searchQuery.value.toLowerCase()
  return valeurs.value.filter(val =>
    val.code.toLowerCase().includes(query) ||
    val.libelle.toLowerCase().includes(query)
  )
})

const isColorAttribute = computed(() => {
  return attribut.value && ['couleur', 'color'].includes(attribut.value.code.toLowerCase())
})

// Methods
const fetchAttribut = async () => {
  try {
    const { data, error } = await useApi(`/admin/variant-attributs/${attributId.value}`)
    
    if (!error.value && data.value) {
      const response = data.value as any
      if (response.success) {
        attribut.value = response.data
      }
    }
  } catch (err) {
    console.error('Error fetching variant attribute:', err)
  }
}

const fetchValeurs = async () => {
  try {
    loading.value = true
    const { data, error } = await useApi(`/admin/variant-attributs/${attributId.value}/valeurs`)
    
    if (!error.value && data.value) {
      const response = data.value as any
      if (response.success) {
        valeurs.value = response.data
      }
    }
  } catch (err) {
    console.error('Error fetching variant values:', err)
  } finally {
    loading.value = false
  }
}

const handleCreate = () => {
  const maxOrdre = valeurs.value.length > 0 ? Math.max(...valeurs.value.map(v => v.ordre)) : 0
  formData.value = {
    code: '',
    libelle: '',
    actif: true,
    ordre: maxOrdre + 1,
    hex_color: ''
  }
  formErrors.value = {}
  showCreateDialog.value = true
}

const handleEdit = (valeur: VariantValeur) => {
  selectedValeur.value = valeur
  formData.value = {
    code: valeur.code,
    libelle: valeur.libelle,
    actif: valeur.actif,
    ordre: valeur.ordre,
    hex_color: valeur.hex_color || ''
  }
  formErrors.value = {}
  showEditDialog.value = true
}

const handleDelete = async (valeur: VariantValeur) => {
  // Show confirm dialog before deleting
  const confirmed = await confirmDelete('valeur', valeur.libelle)
  if (!confirmed) return

  try {
    isDeleting.value = true
    const { data, error } = await useApi(`/admin/variant-attributs/${attributId.value}/valeurs/${valeur.id}`, {
      method: 'DELETE'
    })

    if (!error.value && data.value) {
      const response = data.value as any
      if (response.success) {
        valeurs.value = valeurs.value.filter(v => v.id !== valeur.id)
      }
    }
  } catch (err) {
    console.error('Error deleting variant value:', err)
  } finally {
    isDeleting.value = false
  }
}

const submitCreate = async () => {
  // Show confirm dialog before creating
  const confirmed = await confirmCreate('valeur')
  if (!confirmed) return

  try {
    formErrors.value = {}

    // Prepare form data - convert empty hex_color to null
    const submitData = {
      ...formData.value,
      hex_color: formData.value.hex_color?.trim() || null
    }

    const { data, error } = await useApi(`/admin/variant-attributs/${attributId.value}/valeurs`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(submitData)
    })
    
    if (!error.value && data.value) {
      const response = data.value as any
      if (response.success) {
        valeurs.value.push(response.data)
        valeurs.value.sort((a, b) => a.ordre - b.ordre)
        showCreateDialog.value = false
      } else {
        if (response.errors) {
          formErrors.value = response.errors
        }
      }
    }
  } catch (err) {
    console.error('Error creating variant value:', err)
  }
}

const submitEdit = async () => {
  if (!selectedValeur.value) return

  // Show confirm dialog before updating
  const confirmed = await confirmUpdate('valeur', selectedValeur.value.libelle)
  if (!confirmed) return

  try {
    formErrors.value = {}

    // Prepare form data - convert empty hex_color to null
    const submitData = {
      ...formData.value,
      hex_color: formData.value.hex_color?.trim() || null
    }

    const { data, error } = await useApi(`/admin/variant-attributs/${attributId.value}/valeurs/${selectedValeur.value.id}`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(submitData)
    })
    
    if (!error.value && data.value) {
      const response = data.value as any
      if (response.success) {
        const index = valeurs.value.findIndex(v => v.id === selectedValeur.value!.id)
        if (index > -1) {
          valeurs.value[index] = response.data
        }
        valeurs.value.sort((a, b) => a.ordre - b.ordre)
        showEditDialog.value = false
      } else {
        if (response.errors) {
          formErrors.value = response.errors
        }
      }
    }
  } catch (err) {
    console.error('Error updating variant value:', err)
  }
}



const goBack = () => {
  router.push('/admin/variants/attributs')
}

// Lifecycle
onMounted(() => {
  fetchAttribut()
  fetchValeurs()
})
</script>

<template>
  <div>
    <!-- Breadcrumbs -->
    <VBreadcrumbs
      :items="[
        { title: 'Admin', disabled: true },
        { title: 'Variants', disabled: true },
        { title: t('admin_variants_attributes'), to: '/admin/variants/attributs' },
        { title: attribut?.nom || t('admin_variants_values'), disabled: true }
      ]"
      class="px-0"
    />

    <!-- Page Header -->
    <div class="d-flex justify-space-between align-center mb-6">
      <div>
        <div class="d-flex align-center gap-3 mb-2">
          <VBtn
            icon="tabler-arrow-left"
            variant="text"
            @click="goBack"
          />
          <h1 class="text-h4 font-weight-bold">
            {{ t('admin_variants_attribute_values', { name: attribut?.nom }) }}
          </h1>
        </div>
        <p class="text-body-1 text-medium-emphasis">
          {{ t('admin_variants_manage_values_desc', { name: attribut?.nom }) }}
        </p>
      </div>
      
      <VBtn
        color="primary"
        prepend-icon="tabler-plus"
        @click="handleCreate"
      >
        {{ t('admin_variants_add_value') }}
      </VBtn>
    </div>

    <!-- Search -->
    <VCard class="mb-6">
      <VCardText>
        <VTextField
          v-model="searchQuery"
          placeholder="Search values..."
          prepend-inner-icon="tabler-search"
          clearable
          variant="outlined"
          density="compact"
        />
      </VCardText>
    </VCard>

    <!-- Values List -->
    <VCard>
      <VCardText>
        <div v-if="loading" class="text-center py-8">
          <VProgressCircular indeterminate color="primary" />
        </div>
        
        <div v-else-if="filteredValeurs.length === 0" class="text-center py-8">
          <VIcon icon="tabler-list" size="64" color="grey" class="mb-4" />
          <h3 class="text-h6 mb-2">{{ t('no_values_found') }}</h3>
          <p class="text-body-2 text-medium-emphasis">
            {{ searchQuery ? t('try_adjusting_search') : t('create_first_value_attribute') }}
          </p>
        </div>
        
        <VRow v-else>
          <VCol
            v-for="valeur in filteredValeurs"
            :key="valeur.id"
            cols="12"
            sm="6"
            md="4"
            lg="3"
          >
            <VCard
              class="value-card h-100"
              :class="{ 'opacity-60': !valeur.actif }"
              elevation="2"
            >
              <VCardText class="pb-2">
                <div class="d-flex justify-space-between align-start mb-3">
                  <div class="flex-grow-1">
                    <div class="d-flex align-center gap-2 mb-2">
                      <h4 class="text-h6 font-weight-bold">{{ valeur.libelle }}</h4>
                      <!-- Color Swatch -->
                      <div
                        v-if="valeur.hex_color && isColorAttribute"
                        class="color-swatch"
                        :style="{ backgroundColor: valeur.hex_color }"
                        :title="valeur.hex_color"
                      />
                    </div>
                    <p class="text-caption text-medium-emphasis">{{ valeur.code }}</p>
                    <div class="d-flex gap-1 flex-wrap mt-1">
                      <VChip size="x-small" variant="outlined">
                        {{ t('order') }}: {{ valeur.ordre }}
                      </VChip>
                      <VChip
                        v-if="valeur.hex_color && isColorAttribute"
                        size="x-small"
                        variant="outlined"
                        color="primary"
                      >
                        {{ valeur.hex_color }}
                      </VChip>
                    </div>
                  </div>
                  <VChip
                    :color="valeur.actif ? 'success' : 'error'"
                    size="small"
                    variant="flat"
                  >
                    {{ valeur.actif ? t('active') : t('inactive') }}
                  </VChip>
                </div>
              </VCardText>
              
              <VCardActions class="pt-0">
                <VBtn
                  icon="tabler-edit"
                  size="small"
                  variant="text"
                  @click="handleEdit(valeur)"
                />
                <VBtn
                  icon="tabler-trash"
                  size="small"
                  variant="text"
                  color="error"
                  @click="handleDelete(valeur)"
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
        <VCardTitle>{{ t('create_value') }}</VCardTitle>
        <VCardText>
          <VForm @submit.prevent="submitCreate">
            <VTextField
              v-model="formData.code"
              label="Code"
              placeholder="e.g., l, blue, cotton"
              :error-messages="formErrors.code"
              variant="outlined"
              class="mb-4"
              required
            />
            
            <VTextField
              v-model="formData.libelle"
              label="Label"
              placeholder="e.g., Large, Blue, Cotton"
              :error-messages="formErrors.libelle"
              variant="outlined"
              class="mb-4"
              required
            />
            
            <VTextField
              v-model.number="formData.ordre"
              label="Order"
              type="number"
              :error-messages="formErrors.ordre"
              variant="outlined"
              class="mb-4"
            />

            <!-- Color Picker for Color Attributes -->
            <ColorPicker
              v-if="isColorAttribute"
              v-model="formData.hex_color"
              label="Color"
              :error-messages="formErrors.hex_color"
              class="mb-4"
            />

            <VCheckbox
              v-model="formData.actif"
              label="Active"
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
        <VCardTitle>{{ t('edit_value') }}</VCardTitle>
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
              v-model="formData.libelle"
              :label="t('label')"
              :error-messages="formErrors.libelle"
              variant="outlined"
              class="mb-4"
              required
            />
            
            <VTextField
              v-model.number="formData.ordre"
              :label="t('order')"
              type="number"
              :error-messages="formErrors.ordre"
              variant="outlined"
              class="mb-4"
            />

            <!-- Color Picker for Color Attributes -->
            <ColorPicker
              v-if="isColorAttribute"
              v-model="formData.hex_color"
              label="Color"
              :error-messages="formErrors.hex_color"
              class="mb-4"
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
.value-card {
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.value-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.color-swatch {
  width: 24px;
  height: 24px;
  border-radius: 50%;
  border: 2px solid rgba(255, 255, 255, 0.9);
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2), inset 0 0 0 1px rgba(0, 0, 0, 0.1);
  flex-shrink: 0;
  cursor: pointer;
  transition: transform 0.2s ease;
}

.color-swatch:hover {
  transform: scale(1.1);
}
</style>
