<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCategoriesStore, type Category, type CategoryFormData } from '@/stores/admin/categories'

interface Props {
  modelValue: boolean
  category?: Category | null
  mode: 'create' | 'edit'
}

interface Emits {
  (e: 'update:modelValue', value: boolean): void
  (e: 'saved'): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

// Composables
const { t } = useI18n()
const categoriesStore = useCategoriesStore()

// Form state
const form = ref<CategoryFormData>({
  nom: '',
  slug: '',
  description: '',
  ordre: 0,
  actif: true,
  image: null
})

const formRef = ref()
const isSubmitting = ref(false)
const imagePreview = ref<string | null>(null)

// Computed properties
const isOpen = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
})

const dialogTitle = computed(() => 
  props.mode === 'create' 
    ? t('admin_categories_create') 
    : t('admin_categories_edit')
)

const submitButtonText = computed(() => 
  props.mode === 'create' 
    ? t('admin_categories_create') 
    : t('admin_categories_update')
)

// Form validation rules
const rules = {
  nom: [
    (v: string) => !!v || t('validation_required', { field: t('admin_categories_name') }),
    (v: string) => (v && v.length >= 2) || t('validation_min_length', { field: t('admin_categories_name'), min: 2 }),
    (v: string) => (v && v.length <= 100) || t('validation_max_length', { field: t('admin_categories_name'), max: 100 })
  ],
  slug: [
    (v: string) => !!v || t('validation_required', { field: t('admin_categories_slug') }),
    (v: string) => (v && /^[a-z0-9-_]+$/.test(v)) || t('validation_slug_format')
  ],
  description: [
    (v: string) => !v || v.length <= 500 || t('validation_max_length', { field: t('admin_categories_description'), max: 500 })
  ],
  ordre: [
    (v: number) => v >= 0 || t('validation_min_value', { field: t('admin_categories_order'), min: 0 })
  ]
}

// Methods
const generateSlug = (nom: string) => {
  if (!nom) return ''
  return nom
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9\s-]/g, '')
    .replace(/\s+/g, '-')
    .replace(/-+/g, '-')
    .trim()
}

const resetForm = () => {
  form.value = {
    nom: '',
    slug: '',
    description: '',
    ordre: 0,
    actif: true,
    image: null
  }
  imagePreview.value = null
  if (formRef.value) {
    formRef.value.resetValidation()
  }
}

const loadCategoryData = () => {
  if (props.category) {
    form.value = {
      nom: props.category.nom,
      slug: props.category.slug,
      description: props.category.description || '',
      ordre: props.category.ordre || 0,
      actif: props.category.actif,
      image: null
    }
    imagePreview.value = props.category.image_url || null
  }
}

const handleImageChange = (event: Event) => {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0]
  
  if (file) {
    if (file.size > 2 * 1024 * 1024) { // 2MB limit
      // Show error toast
      return
    }
    
    form.value.image = file
    
    // Create preview
    const reader = new FileReader()
    reader.onload = (e) => {
      imagePreview.value = e.target?.result as string
    }
    reader.readAsDataURL(file)
  }
}

const removeImage = () => {
  form.value.image = null
  imagePreview.value = null
  // Clear file input
  const fileInput = document.querySelector('input[type="file"]') as HTMLInputElement
  if (fileInput) {
    fileInput.value = ''
  }
}

const submitForm = async () => {
  if (!formRef.value) return
  
  const { valid } = await formRef.value.validate()
  if (!valid) return

  isSubmitting.value = true
  
  try {
    if (props.mode === 'create') {
      await categoriesStore.createCategory(form.value)
    } else if (props.category) {
      await categoriesStore.updateCategory(props.category.id, form.value)
    }
    
    emit('saved')
    isOpen.value = false
  } catch (error) {
    console.error('Form submission failed:', error)
  } finally {
    isSubmitting.value = false
  }
}

const closeDialog = () => {
  if (!isSubmitting.value) {
    isOpen.value = false
  }
}

// Watchers
watch(() => props.modelValue, (newValue) => {
  if (newValue) {
    nextTick(() => {
      if (props.mode === 'edit') {
        loadCategoryData()
      } else {
        resetForm()
      }
    })
  }
})

watch(() => form.value.nom, (newNom) => {
  if (props.mode === 'create' && newNom) {
    form.value.slug = generateSlug(newNom)
  }
})
</script>

<template>
  <VDialog
    v-model="isOpen"
    max-width="600"
    persistent
    scrollable
  >
    <VCard>
      <VCardTitle class="d-flex align-center justify-space-between">
        <span>{{ dialogTitle }}</span>
        <VBtn
          icon="tabler-x"
          variant="text"
          size="small"
          @click="closeDialog"
        />
      </VCardTitle>

      <VDivider />

      <VCardText>
        <VForm ref="formRef" @submit.prevent="submitForm">
          <VRow>
            <!-- Image Upload -->
            <VCol cols="12">
              <div class="mb-4">
                <VLabel class="mb-2">{{ $t('admin_categories_image') }}</VLabel>
                
                <div v-if="imagePreview" class="mb-4">
                  <div class="d-flex align-center gap-4">
                    <VImg
                      :src="imagePreview"
                      width="80"
                      height="80"
                      class="rounded"
                      cover
                    />
                    <VBtn
                      color="error"
                      variant="outlined"
                      size="small"
                      prepend-icon="tabler-trash"
                      @click="removeImage"
                    >
                      {{ $t('common_remove') }}
                    </VBtn>
                  </div>
                </div>
                
                <VFileInput
                  accept="image/*"
                  :label="imagePreview ? $t('admin_categories_change_image') : $t('admin_categories_select_image')"
                  variant="outlined"
                  prepend-icon="tabler-camera"
                  show-size
                  @change="handleImageChange"
                />
                <div class="text-caption text-medium-emphasis mt-1">
                  {{ $t('admin_categories_image_help') }}
                </div>
              </div>
            </VCol>

            <!-- Name -->
            <VCol cols="12" md="8">
              <VTextField
                v-model="form.nom"
                :label="$t('admin_categories_name')"
                :rules="rules.nom"
                variant="outlined"
                required
                counter="100"
              />
            </VCol>

            <!-- Order -->
            <VCol cols="12" md="4">
              <VTextField
                v-model.number="form.ordre"
                :label="$t('admin_categories_order')"
                :rules="rules.ordre"
                variant="outlined"
                type="number"
                min="0"
              />
            </VCol>

            <!-- Slug -->
            <VCol cols="12">
              <VTextField
                v-model="form.slug"
                :label="$t('admin_categories_slug')"
                :rules="rules.slug"
                variant="outlined"
                required
                counter="100"
              />
              <div class="text-caption text-medium-emphasis mt-1">
                {{ $t('admin_categories_slug_help') }}
              </div>
            </VCol>

            <!-- Description -->
            <VCol cols="12">
              <VTextarea
                v-model="form.description"
                :label="$t('admin_categories_description')"
                :rules="rules.description"
                variant="outlined"
                rows="4"
                counter="500"
              />
            </VCol>

            <!-- Status -->
            <VCol cols="12">
              <VSwitch
                v-model="form.actif"
                :label="$t('admin_categories_status')"
                :true-value="true"
                :false-value="false"
                color="success"
                inset
              />
              <div class="text-caption text-medium-emphasis mt-1">
                {{ form.actif ? $t('admin_categories_active_help') : $t('admin_categories_inactive_help') }}
              </div>
            </VCol>
          </VRow>
        </VForm>
      </VCardText>

      <VDivider />

      <VCardActions class="justify-end gap-2">
        <VBtn
          variant="outlined"
          @click="closeDialog"
          :disabled="isSubmitting"
        >
          {{ $t('common_cancel') }}
        </VBtn>
        <VBtn
          color="primary"
          :loading="isSubmitting"
          @click="submitForm"
        >
          {{ submitButtonText }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
