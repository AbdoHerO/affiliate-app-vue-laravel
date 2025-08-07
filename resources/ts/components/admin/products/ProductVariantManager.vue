<template>
  <VCard>
    <VCardTitle class="d-flex align-center justify-space-between">
      <span>{{ $t('admin_produits_variants') }}</span>
      <VBtn
        color="primary"
        size="small"
        prepend-icon="tabler-plus"
        @click="showAddDialog = true"
      >
        {{ $t('admin_produits_add_variant') }}
      </VBtn>
    </VCardTitle>

    <VCardText>
      <!-- Variants List -->
      <div v-if="variants.length > 0">
        <VDataTable
          :headers="headers"
          :items="variants"
          :loading="isLoading"
          class="elevation-1"
        >
          <template #item.nom="{ item }">
            <VChip size="small" color="primary">
              {{ item.nom }}
            </VChip>
          </template>

          <template #item.valeur="{ item }">
            <strong>{{ item.valeur }}</strong>
          </template>

          <template #item.prix_vente_variante="{ item }">
            <span v-if="item.prix_vente_variante">
              {{ formatPrice(item.prix_vente_variante) }} DH
            </span>
            <span v-else class="text-medium-emphasis">
              {{ $t('admin_produits_default_price') }}
            </span>
          </template>

          <template #item.sku_variante="{ item }">
            <VChip v-if="item.sku_variante" size="small" variant="outlined">
              {{ item.sku_variante }}
            </VChip>
            <span v-else class="text-medium-emphasis">-</span>
          </template>

          <template #item.actif="{ item }">
            <VChip
              :color="item.actif ? 'success' : 'error'"
              size="small"
            >
              {{ item.actif ? $t('common.active') : $t('common.inactive') }}
            </VChip>
          </template>

          <template #item.actions="{ item }">
            <VBtn
              icon="tabler-edit"
              size="small"
              variant="text"
              @click="editVariant(item)"
            />
            <VBtn
              icon="tabler-trash"
              size="small"
              variant="text"
              color="error"
              @click="deleteVariant(item)"
            />
          </template>
        </VDataTable>
      </div>

      <!-- Empty State -->
      <div v-else class="text-center py-8">
        <VIcon icon="tabler-versions-off" size="64" color="disabled" class="mb-4" />
        <h3 class="text-h6 mb-2">{{ $t('admin_produits_no_variants') }}</h3>
        <p class="text-medium-emphasis mb-4">{{ $t('admin_produits_no_variants_desc') }}</p>
        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          @click="showAddDialog = true"
        >
          {{ $t('admin_produits_add_first_variant') }}
        </VBtn>
      </div>
    </VCardText>

    <!-- Add/Edit Variant Dialog -->
    <VDialog v-model="showAddDialog" max-width="600">
      <VCard>
        <VCardTitle>
          {{ editingVariant ? $t('admin_produits_edit_variant') : $t('admin_produits_add_variant') }}
        </VCardTitle>

        <VCardText>
          <VForm ref="formRef" @submit.prevent="saveVariant">
            <VRow>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="variantForm.nom"
                  :label="$t('admin_produits_variant_name')"
                  :placeholder="$t('admin_produits_variant_name_placeholder')"
                  :error-messages="errors.nom"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="variantForm.valeur"
                  :label="$t('admin_produits_variant_value')"
                  :placeholder="$t('admin_produits_variant_value_placeholder')"
                  :error-messages="errors.valeur"
                  required
                />
              </VCol>
            </VRow>

            <VRow>
              <VCol cols="12" md="6">
                <VTextField
                  v-model.number="variantForm.prix_vente_variante"
                  :label="$t('admin_produits_variant_price')"
                  :placeholder="$t('admin_produits_variant_price_placeholder')"
                  type="number"
                  step="0.01"
                  min="0"
                  :error-messages="errors.prix_vente_variante"
                  suffix="DH"
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="variantForm.sku_variante"
                  :label="$t('admin_produits_variant_sku')"
                  :placeholder="$t('admin_produits_variant_sku_placeholder')"
                  :error-messages="errors.sku_variante"
                />
              </VCol>
            </VRow>

            <VRow>
              <VCol cols="12">
                <VSwitch
                  v-model="variantForm.actif"
                  :label="$t('admin_produits_variant_active')"
                  color="primary"
                />
              </VCol>
            </VRow>
          </VForm>
        </VCardText>

        <VCardActions>
          <VSpacer />
          <VBtn
            variant="outlined"
            @click="closeDialog"
          >
            {{ $t('common.cancel') }}
          </VBtn>
          <VBtn
            color="primary"
            :loading="isLoading"
            @click="saveVariant"
          >
            {{ editingVariant ? $t('common.update') : $t('common.add') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Delete Confirmation Dialog -->
    <VDialog v-model="showDeleteDialog" max-width="400">
      <VCard>
        <VCardTitle>{{ $t('admin_produits_delete_variant') }}</VCardTitle>
        <VCardText>
          {{ $t('admin_produits_delete_variant_confirm') }}
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="outlined" @click="showDeleteDialog = false">
            {{ $t('common.cancel') }}
          </VBtn>
          <VBtn
            color="error"
            :loading="isLoading"
            @click="confirmDeleteVariant"
          >
            {{ $t('common.delete') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </VCard>
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useApi } from '@/composables/useApi'
import { useNotifications } from '@/composables/useNotifications'

interface Variant {
  id: string
  nom: string
  valeur: string
  prix_vente_variante?: number
  sku_variante?: string
  actif: boolean
}

interface Props {
  productId: string
  modelValue: Variant[]
}

interface Emits {
  (e: 'update:modelValue', value: Variant[]): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const { t } = useI18n()
const { showSuccess, showError } = useNotifications()

// Local state
const variants = ref<Variant[]>([...props.modelValue])
const showAddDialog = ref(false)
const showDeleteDialog = ref(false)
const isLoading = ref(false)
const editingVariant = ref<Variant | null>(null)
const variantToDelete = ref<Variant | null>(null)
const formRef = ref()

const variantForm = reactive({
  nom: '',
  valeur: '',
  prix_vente_variante: null as number | null,
  sku_variante: '',
  actif: true
})

const errors = ref<Record<string, string[]>>({})

// Computed
const headers = computed(() => [
  { title: t('admin_produits_variant_name'), key: 'nom', sortable: true },
  { title: t('admin_produits_variant_value'), key: 'valeur', sortable: true },
  { title: t('admin_produits_variant_price'), key: 'prix_vente_variante', sortable: true },
  { title: t('admin_produits_variant_sku'), key: 'sku_variante', sortable: true },
  { title: t('common.status'), key: 'actif', sortable: true },
  { title: t('common.actions'), key: 'actions', sortable: false, width: 100 }
])

// Watch for external changes
watch(() => props.modelValue, (newValue) => {
  variants.value = [...newValue]
}, { deep: true })

// Emit changes
watch(variants, (newValue) => {
  emit('update:modelValue', newValue)
}, { deep: true })

// Methods
const formatPrice = (price: number): string => {
  return new Intl.NumberFormat('fr-MA', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  }).format(price)
}

const editVariant = (variant: Variant) => {
  editingVariant.value = variant
  variantForm.nom = variant.nom
  variantForm.valeur = variant.valeur
  variantForm.prix_vente_variante = variant.prix_vente_variante || null
  variantForm.sku_variante = variant.sku_variante || ''
  variantForm.actif = variant.actif
  showAddDialog.value = true
}

const deleteVariant = (variant: Variant) => {
  variantToDelete.value = variant
  showDeleteDialog.value = true
}

const saveVariant = async () => {
  errors.value = {}
  isLoading.value = true

  try {
    const endpoint = editingVariant.value 
      ? `/admin/produits/${props.productId}/variantes/${editingVariant.value.id}`
      : `/admin/produits/${props.productId}/variantes`
    
    const method = editingVariant.value ? 'PUT' : 'POST'

    const { data, error } = await useApi(endpoint, {
      method,
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(variantForm)
    })

    if (error.value) {
      const apiError = error.value as any
      if (apiError.errors) {
        errors.value = apiError.errors
      } else {
        showError(apiError.message || 'Error saving variant')
      }
      return
    }

    const response = data.value as any
    if (response.success) {
      if (editingVariant.value) {
        // Update existing variant
        const index = variants.value.findIndex(v => v.id === editingVariant.value!.id)
        if (index !== -1) {
          variants.value[index] = response.data
        }
        showSuccess(t('admin_produits_variant_updated'))
      } else {
        // Add new variant
        variants.value.push(response.data)
        showSuccess(t('admin_produits_variant_added'))
      }
      closeDialog()
    }
  } catch (err) {
    showError('Error saving variant')
  } finally {
    isLoading.value = false
  }
}

const confirmDeleteVariant = async () => {
  if (!variantToDelete.value) return

  isLoading.value = true
  try {
    const { error } = await useApi(`/admin/produits/${props.productId}/variantes/${variantToDelete.value.id}`, {
      method: 'DELETE'
    })

    if (error.value) {
      showError((error.value as any).message || 'Error deleting variant')
      return
    }

    // Remove from local array
    const index = variants.value.findIndex(v => v.id === variantToDelete.value!.id)
    if (index !== -1) {
      variants.value.splice(index, 1)
    }

    showSuccess(t('admin_produits_variant_deleted'))
    showDeleteDialog.value = false
    variantToDelete.value = null
  } catch (err) {
    showError('Error deleting variant')
  } finally {
    isLoading.value = false
  }
}

const closeDialog = () => {
  showAddDialog.value = false
  editingVariant.value = null
  variantForm.nom = ''
  variantForm.valeur = ''
  variantForm.prix_vente_variante = null
  variantForm.sku_variante = ''
  variantForm.actif = true
  errors.value = {}
}
</script>
