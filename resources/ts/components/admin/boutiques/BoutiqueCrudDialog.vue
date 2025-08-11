<template>
  <VDialog
    v-model="isOpen"
    max-width="600"
    persistent
  >
    <VCard>
      <VCardTitle class="d-flex align-center justify-space-between">
        <span>
          {{ isEditMode ? $t('admin_boutiques_edit_title') : $t('admin_boutiques_create_title') }}
        </span>
        <VBtn
          icon="tabler-x"
          variant="text"
          size="small"
          @click="close"
        />
      </VCardTitle>

      <VCardText>
        <VForm ref="form" @submit.prevent="save">
          <VRow>
            <!-- Nom -->
            <VCol cols="12">
              <VTextField
                v-model="formData.nom"
                :label="$t('admin_boutiques_name') + ' *'"
                :error-messages="errors.nom"
                variant="outlined"
                required
                @blur="generateSlug"
              />
            </VCol>

            <!-- Slug -->
            <VCol cols="12">
              <VTextField
                v-model="formData.slug"
                :label="$t('admin_boutiques_slug')"
                :error-messages="errors.slug"
                variant="outlined"
                :hint="$t('admin_boutiques_slug_hint')"
                persistent-hint
              />
            </VCol>

            <!-- Owner Selection -->
            <VCol cols="12">
              <VAutocomplete
                v-model="formData.proprietaire_id"
                :label="$t('admin_boutiques_owner') + ' *'"
                :items="ownerOptions"
                item-title="text"
                item-value="value"
                :error-messages="errors.proprietaire_id"
                variant="outlined"
                required
                :loading="loadingOwners"
                @update:search="searchOwners"
              />
            </VCol>

            <!-- Email Pro -->
            <VCol cols="12">
              <VTextField
                v-model="formData.email_pro"
                :label="$t('admin_boutiques_email')"
                :error-messages="errors.email_pro"
                type="email"
                variant="outlined"
              />
            </VCol>

            <!-- Adresse -->
            <VCol cols="12">
              <VTextarea
                v-model="formData.adresse"
                :label="$t('admin_boutiques_address')"
                :error-messages="errors.adresse"
                variant="outlined"
                rows="3"
              />
            </VCol>

            <!-- Status -->
            <VCol cols="12" sm="6">
              <VSelect
                v-model="formData.statut"
                :label="$t('admin_boutiques_status') + ' *'"
                :items="statusOptions"
                :error-messages="errors.statut"
                variant="outlined"
                required
              />
            </VCol>

            <!-- Commission -->
            <VCol cols="12" sm="6">
              <VTextField
                v-model.number="formData.commission_par_defaut"
                :label="$t('admin_boutiques_commission_rate')"
                :error-messages="errors.commission_par_defaut"
                type="number"
                min="0"
                max="100"
                step="0.01"
                variant="outlined"
                suffix="%"
              />
            </VCol>
          </VRow>
        </VForm>
      </VCardText>

      <VCardActions class="px-6 pb-6">
        <VSpacer />
        <VBtn
          variant="outlined"
          @click="close"
        >
          {{ $t('common.cancel') }}
        </VBtn>
        <VBtn
          color="primary"
          type="button"
          :loading="isSaving"
          @click="save"
        >
          {{ $t('common.save') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<script setup lang="ts">
import { computed, ref, watch, nextTick } from 'vue'
import { useI18n } from 'vue-i18n'
import { useBoutiquesStore, type Boutique, type BoutiqueFormData } from '@/stores/admin/boutiques'
import { useQuickConfirm } from '@/composables/useConfirmAction'

interface Props {
  modelValue: boolean
  boutique?: Boutique | null
  mode: 'create' | 'edit'
}

const props = defineProps<Props>()
const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  'saved': []
}>()

const { t } = useI18n()
const boutiquesStore = useBoutiquesStore()
const { confirmCreate, confirmUpdate } = useQuickConfirm()
const form = ref<any>(null)

// State
const isSaving = ref(false)
const errors = ref<Record<string, string[]>>({})
const loadingOwners = ref(false)
const ownerOptions = ref<Array<{ text: string; value: string }>>([])

const formData = ref<BoutiqueFormData>({
  nom: '',
  slug: '',
  proprietaire_id: '',
  email_pro: '',
  adresse: '',
  statut: 'actif',
  commission_par_defaut: 5.0
})

// Computed
const isOpen = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
})

const isEditMode = computed(() => props.mode === 'edit')

const statusOptions = computed(() => [
  { title: t('status_active'), value: 'actif' },
  { title: t('status_inactive'), value: 'suspendu' },
  { title: t('status_cancelled'), value: 'desactive' }
])

// Methods
const resetForm = () => {
  formData.value = {
    nom: '',
    slug: '',
    proprietaire_id: '',
    email_pro: '',
    adresse: '',
    statut: 'actif',
    commission_par_defaut: 5.0
  }
  errors.value = {}
}

const loadFormData = () => {
  if (props.boutique && isEditMode.value) {
    formData.value = {
      nom: props.boutique.nom,
      slug: props.boutique.slug,
      proprietaire_id: props.boutique.proprietaire.id,
      email_pro: props.boutique.email_pro || '',
      adresse: props.boutique.adresse || '',
      statut: props.boutique.statut,
      commission_par_defaut: props.boutique.commission_par_defaut
    }
    
    // Add current owner to options
    ownerOptions.value = [{
      text: `${props.boutique.proprietaire.nom_complet} (${props.boutique.proprietaire.email})`,
      value: props.boutique.proprietaire.id
    }]
  }
}

const generateSlug = () => {
  if (formData.value.nom && (!formData.value.slug || !isEditMode.value)) {
    formData.value.slug = formData.value.nom
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/(^-|-$)/g, '')
  }
}

const searchOwners = async (search: string) => {
  if (!search || search.length < 2) return
  
  loadingOwners.value = true
  try {
    // This would call an API to search for users with role 'vendeur'
    // For now, simulate the response
    await new Promise(resolve => setTimeout(resolve, 300))
    ownerOptions.value = [
      { text: `John Doe (john@example.com)`, value: '1' },
      { text: `Jane Smith (jane@example.com)`, value: '2' },
    ].filter(option => 
      option.text.toLowerCase().includes(search.toLowerCase())
    )
  } catch (error) {
    console.error('Error searching owners:', error)
  } finally {
    loadingOwners.value = false
  }
}

const validate = () => {
  const newErrors: Record<string, string[]> = {}

  if (!formData.value.nom.trim()) {
    newErrors.nom = [t('validation_required', { field: t('admin_boutiques_name') })]
  }

  if (!formData.value.proprietaire_id) {
    newErrors.proprietaire_id = [t('validation_required', { field: t('admin_boutiques_owner') })]
  }

  if (formData.value.email_pro && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.value.email_pro)) {
    newErrors.email_pro = [t('validation_email')]
  }

  if (!formData.value.statut) {
    newErrors.statut = [t('validation_required', { field: t('admin_boutiques_status') })]
  }

  if (formData.value.commission_par_defaut < 0 || formData.value.commission_par_defaut > 100) {
    newErrors.commission_par_defaut = [t('validation_between', { field: t('admin_boutiques_commission_rate'), min: 0, max: 100 })]
  }

  errors.value = newErrors
  return Object.keys(newErrors).length === 0
}

const save = async () => {
  if (!validate()) return

  // Show confirm dialog before saving
  const confirmed = isEditMode.value && props.boutique
    ? await confirmUpdate(t('boutique'), props.boutique.nom)
    : await confirmCreate(t('boutique'))
  if (!confirmed) return

  isSaving.value = true
  try {
    if (isEditMode.value && props.boutique) {
      await boutiquesStore.update(props.boutique.id, formData.value)
    } else {
      await boutiquesStore.create(formData.value)
    }

    emit('saved')
    close()
  } catch (error: any) {
    console.error('Save failed:', error)

    // Handle validation errors from backend
    if (error.status === 422 && error.data?.errors) {
      errors.value = error.data.errors
    }
  } finally {
    isSaving.value = false
  }
}

const close = () => {
  isOpen.value = false
  resetForm()
}

// Watchers
watch(() => props.modelValue, async (newValue) => {
  if (newValue) {
    await nextTick()
    resetForm()
    loadFormData()
  }
})
</script>
