<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { Category } from '@/stores/admin/categories'

interface Props {
  modelValue: boolean
  category?: Category | null
}

interface Emits {
  (e: 'update:modelValue', value: boolean): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

// Composables
const { t, d } = useI18n()

// Computed properties
const isOpen = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
})

const statusColor = computed(() => 
  props.category?.actif ? 'success' : 'error'
)

const statusText = computed(() => 
  props.category?.actif ? t('status_active') : t('status_inactive')
)

const formatDate = (date: string | null) => {
  if (!date) return t('common_not_available')
  return d(new Date(date), 'long')
}
</script>

<template>
  <VDialog
    v-model="isOpen"
    max-width="600"
    scrollable
  >
    <VCard v-if="category">
      <VCardTitle class="d-flex align-center justify-space-between">
        <span>{{ $t('admin_categories_view') }}</span>
        <VBtn
          icon="tabler-x"
          variant="text"
          size="small"
          @click="isOpen = false"
        />
      </VCardTitle>

      <VDivider />

      <VCardText>
        <div class="d-flex flex-column gap-6">
          <!-- Category Image -->
          <div v-if="category.image_url" class="text-center">
            <VImg
              :src="category.image_url"
              max-width="200"
              max-height="200"
              class="mx-auto rounded-lg"
              cover
            />
          </div>

          <!-- Category Info Grid -->
          <VRow>
            <!-- Name -->
            <VCol cols="12" md="8">
              <div class="d-flex flex-column gap-1">
                <VLabel class="text-subtitle-2 text-medium-emphasis">
                  {{ $t('admin_categories_name') }}
                </VLabel>
                <div class="text-h6">{{ category.nom }}</div>
              </div>
            </VCol>

            <!-- Order -->
            <VCol cols="12" md="4">
              <div class="d-flex flex-column gap-1">
                <VLabel class="text-subtitle-2 text-medium-emphasis">
                  {{ $t('admin_categories_order') }}
                </VLabel>
                <VChip
                  color="primary"
                  size="small"
                  variant="tonal"
                >
                  {{ category.ordre || 0 }}
                </VChip>
              </div>
            </VCol>

            <!-- Slug -->
            <VCol cols="12" md="8">
              <div class="d-flex flex-column gap-1">
                <VLabel class="text-subtitle-2 text-medium-emphasis">
                  {{ $t('admin_categories_slug') }}
                </VLabel>
                <code class="text-body-1 bg-grey-lighten-4 pa-2 rounded">{{ category.slug }}</code>
              </div>
            </VCol>

            <!-- Status -->
            <VCol cols="12" md="4">
              <div class="d-flex flex-column gap-1">
                <VLabel class="text-subtitle-2 text-medium-emphasis">
                  {{ $t('admin_categories_status') }}
                </VLabel>
                <VChip
                  :color="statusColor"
                  size="small"
                  variant="tonal"
                >
                  <VIcon 
                    :icon="category.actif ? 'tabler-check' : 'tabler-x'" 
                    start 
                  />
                  {{ statusText }}
                </VChip>
              </div>
            </VCol>

            <!-- Description -->
            <VCol cols="12" v-if="category.description">
              <div class="d-flex flex-column gap-1">
                <VLabel class="text-subtitle-2 text-medium-emphasis">
                  {{ $t('admin_categories_description') }}
                </VLabel>
                <div class="text-body-1">{{ category.description }}</div>
              </div>
            </VCol>

            <!-- Timestamps -->
            <VCol cols="12" md="6">
              <div class="d-flex flex-column gap-1">
                <VLabel class="text-subtitle-2 text-medium-emphasis">
                  {{ $t('common_created_at') }}
                </VLabel>
                <div class="text-body-2">{{ formatDate(category.created_at) }}</div>
              </div>
            </VCol>

            <VCol cols="12" md="6">
              <div class="d-flex flex-column gap-1">
                <VLabel class="text-subtitle-2 text-medium-emphasis">
                  {{ $t('common_updated_at') }}
                </VLabel>
                <div class="text-body-2">{{ formatDate(category.updated_at) }}</div>
              </div>
            </VCol>
          </VRow>

          <!-- Additional Info -->
          <VDivider />
          
          <div class="d-flex flex-column gap-4">
            <VLabel class="text-subtitle-1">{{ $t('admin_categories_additional_info') }}</VLabel>
            
            <VRow>
              <!-- ID -->
              <VCol cols="12" md="6">
                <div class="d-flex align-center gap-2">
                  <VIcon icon="tabler-id" size="16" class="text-medium-emphasis" />
                  <span class="text-body-2 text-medium-emphasis">ID:</span>
                  <code class="text-caption">{{ category.id }}</code>
                </div>
              </VCol>

              <!-- Products Count (if available) -->
              <VCol cols="12" md="6" v-if="category.products_count !== undefined">
                <div class="d-flex align-center gap-2">
                  <VIcon icon="tabler-package" size="16" class="text-medium-emphasis" />
                  <span class="text-body-2 text-medium-emphasis">{{ $t('admin_categories_products_count') }}:</span>
                  <VChip size="x-small" color="info" variant="tonal">
                    {{ category.products_count }}
                  </VChip>
                </div>
              </VCol>
            </VRow>
          </div>
        </div>
      </VCardText>

      <VDivider />

      <VCardActions class="justify-end">
        <VBtn
          variant="outlined"
          @click="isOpen = false"
        >
          {{ $t('common_close') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
