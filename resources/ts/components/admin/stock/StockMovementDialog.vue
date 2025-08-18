<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useStockStore } from '@/stores/admin/stock'
import { useNotifications } from '@/composables/useNotifications'
import { useQuickConfirm } from '@/composables/useConfirmAction'
import { useFormErrors } from '@/composables/useFormErrors'
import type { StockItem, CreateStockMovementForm } from '@/types/admin/stock'
import { MOVEMENT_TYPES, MOVEMENT_REASONS } from '@/types/admin/stock'

interface Props {
  modelValue: boolean
  item?: StockItem | null
  movementType: 'in' | 'out' | 'adjust'
}

interface Emits {
  (e: 'update:modelValue', value: boolean): void
  (e: 'saved'): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

// Composables
const { t } = useI18n()
const { showError } = useNotifications()
const { confirmCreate } = useQuickConfirm()
const { errors, clear: clearErrors, set: setErrors } = useFormErrors()
const stockStore = useStockStore()

// Local state
const formRef = ref()
const isSaving = ref(false)
const isMounted = ref(true)

// Form data
const formData = ref<CreateStockMovementForm>({
  produit_id: '',
  variante_id: null,
  entrepot_id: null,
  type: 'in',
  quantity: 1,
  reason: 'manual',
  note: null,
  reference: null,
})

// Computed
const isOpen = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
})

const dialogTitle = computed(() => {
  const typeLabel = MOVEMENT_TYPES.find(t => t.value === props.movementType)?.label || ''
  return t('stock.dialog.movement.title') + ' - ' + typeLabel
})

const movementTypeOptions = computed(() => MOVEMENT_TYPES)
const reasonOptions = computed(() => MOVEMENT_REASONS)

const currentStock = computed(() => {
  if (!props.item) return null
  return {
    on_hand: props.item.metrics.on_hand,
    reserved: props.item.metrics.reserved,
    available: props.item.metrics.available,
  }
})

const isOutMovement = computed(() => props.movementType === 'out')
const maxQuantity = computed(() => {
  if (!isOutMovement.value || !currentStock.value) return undefined
  return currentStock.value.available
})

// Validation rules
const rules = {
  produit_id: [
    (v: string) => {
      console.log('🔍 [StockMovementDialog] Validating produit_id:', v, '-> valid:', !!v)
      return !!v || t('validation.required')
    },
  ],
  quantity: [
    (v: number) => !!v || t('validation.required'),
    (v: number) => v > 0 || t('validation.min_value', { min: 1 }),
    (v: number) => {
      if (isOutMovement.value && maxQuantity.value !== undefined) {
        return v <= maxQuantity.value || t('stock.validation.insufficient_stock', { available: maxQuantity.value })
      }
      return true
    },
  ],
  reason: [
    (v: string) => !!v || t('validation.required'),
  ],
}

// Methods
const resetForm = () => {
  const productId = props.item?.product?.id || ''
  console.log('🔧 [StockMovementDialog] Resetting form with:', {
    productId,
    movementType: props.movementType,
    hasItem: !!props.item,
    variantId: props.item?.variant?.id,
    itemStructure: props.item ? {
      product: props.item.product ? { id: props.item.product.id, titre: props.item.product.titre } : null,
      variant: props.item.variant ? { id: props.item.variant.id } : null
    } : null
  })

  // Only proceed if we have a valid product ID or the dialog is not open
  if (!productId && props.modelValue) {
    console.warn('⚠️ [StockMovementDialog] No product ID available when dialog is open, skipping reset')
    return
  }

  formData.value = {
    produit_id: productId,
    variante_id: props.item?.variant?.id || null,
    entrepot_id: null,
    type: props.movementType,
    quantity: 1,
    reason: 'manual',
    note: null,
    reference: null,
  }
  
  clearErrors()

  console.log('✅ [StockMovementDialog] Form data set to:', formData.value)
}

const validate = async () => {
  if (!formRef.value) {
    console.log('❌ [StockMovementDialog] No form ref available for validation')
    return false
  }
  
  console.log('🔍 [StockMovementDialog] Validating form with data:', formData.value)
  
  const { valid } = await formRef.value.validate()
  console.log('🔍 [StockMovementDialog] Form validation result:', valid)
  
  return valid
}

const save = async () => {
  console.log('💾 [StockMovementDialog] Save triggered')
  console.log('🔍 [StockMovementDialog] Current props state:', {
    item: props.item,
    movementType: props.movementType,
    modelValue: props.modelValue,
    productId: props.item?.product?.id,
    productTitle: props.item?.product?.titre
  })
  console.log('🔍 [StockMovementDialog] Current form data:', formData.value)
  
  if (!isMounted.value) {
    console.log('❌ [StockMovementDialog] Component not mounted, aborting')
    return
  }

  // Fix missing produit_id before validation if possible
  if (!formData.value.produit_id && props.item?.product?.id) {
    console.log('🔧 [StockMovementDialog] Fixing missing produit_id before validation')
    formData.value.produit_id = props.item.product.id
  }

  // Validate form first
  const isValid = await validate()
  console.log('🔍 [StockMovementDialog] Form validation result:', isValid)
  if (!isValid) {
    console.log('❌ [StockMovementDialog] Form validation failed, cannot proceed')
    return
  }

  // Final check for produit_id
  if (!formData.value.produit_id) {
    console.log('❌ [StockMovementDialog] Missing produit_id after validation, cannot proceed')
    showError(t('stock.errors.missing_product') || 'Product is required')
    return
  }

  // Show confirm dialog - using same pattern as users.vue
  console.log('🤔 [StockMovementDialog] Showing confirmation dialog...')
  const confirmed = await confirmCreate(t('stock.movement') || 'movement')
  console.log('✅ [StockMovementDialog] Confirmation result:', confirmed)
  if (!confirmed) return

  isSaving.value = true
  try {
    // Prepare payload - ensure all IDs are strings and numbers are numbers
    const payload = {
      produit_id: String(formData.value.produit_id),
      variante_id: formData.value.variante_id ? String(formData.value.variante_id) : null,
      entrepot_id: formData.value.entrepot_id ? String(formData.value.entrepot_id) : null,
      type: formData.value.type,
      quantity: Number(formData.value.quantity),
      reason: formData.value.reason,
      note: formData.value.note || null,
      reference: formData.value.reference || null,
    }

    console.log('🚀 [StockMovementDialog] Submitting movement payload:', payload)
    
    const response = await stockStore.createMovement(payload)
    console.log('✅ [StockMovementDialog] Movement created successfully:', response)
    
    if (isMounted.value) {
      emit('saved')
      close()
    }
  } catch (error: any) {
    console.error('🚨 [StockMovementDialog] Save failed:', error)

    if (isMounted.value) {
      // Handle validation errors from backend
      if (error.status === 422 && error.data?.errors) {
        console.log('🔴 [StockMovementDialog] Setting validation errors:', error.data.errors)
        setErrors(error.data.errors)
      } else {
        const errorMessage = error.message || t('stock.errors.movement_failed')
        console.log('🔴 [StockMovementDialog] Showing error notification:', errorMessage)
        showError(errorMessage)
      }
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
watch(
  () => props.modelValue,
  (newValue) => {
    if (newValue) {
      console.log('🔍 [StockMovementDialog] Dialog opening with props:', {
        item: props.item,
        movementType: props.movementType,
        productId: props.item?.product?.id,
        productTitle: props.item?.product?.titre
      })
      // Use nextTick to ensure props are fully updated
      nextTick(() => {
        resetForm()
      })
    }
  }
)

// Watch for item changes (when parent sets selectedItem)
watch(
  () => props.item,
  (newItem) => {
    if (newItem && props.modelValue) {
      console.log('🔄 [StockMovementDialog] Item changed, resetting form with new item:', {
        productId: newItem.product?.id,
        productTitle: newItem.product?.titre
      })
      nextTick(() => {
        resetForm()
      })
    }
  },
  { deep: true }
)

watch(
  () => props.movementType,
  (newType) => {
    console.log('🔄 [StockMovementDialog] Movement type changed to:', newType)
    console.log('🔍 [StockMovementDialog] Props when movement type changed:', {
      item: props.item,
      modelValue: props.modelValue,
      productId: props.item?.product?.id
    })
    formData.value.type = newType
  },
  { immediate: true }
)

// Lifecycle
onBeforeUnmount(() => {
  isMounted.value = false

  // Clear any pending operations
  if (formRef.value) {
    formRef.value = null
  }

  // Reset form data
  formData.value = {
    produit_id: '',
    variante_id: null,
    entrepot_id: null,
    type: 'in',
    quantity: 1,
    reason: 'manual',
    note: null,
    reference: null,
  }

  clearErrors()
})
</script>

<template>
  <VDialog
    v-model="isOpen"
    max-width="600"
    persistent
  >
    <VCard>
      <VCardTitle class="d-flex align-center">
        <VIcon
          :icon="stockStore.getMovementTypeIcon(movementType)"
          :color="stockStore.getMovementTypeColor(movementType)"
          class="me-2"
        />
        {{ dialogTitle }}
      </VCardTitle>

      <VCardText>
        <!-- Product Info -->
        <VAlert
          v-if="item"
          type="info"
          variant="tonal"
          class="mb-4"
        >
          <div class="d-flex align-center">
            <div class="flex-grow-1">
              <div class="font-weight-medium">{{ item.product.titre }}</div>
              <div v-if="item.variant" class="text-caption">{{ item.variant.libelle }}</div>
            </div>
            <div class="text-end">
              <div class="text-caption">{{ t('stock.current_stock') }}</div>
              <div class="d-flex gap-2">
                <VChip size="x-small" color="success">{{ t('stock.on_hand') }}: {{ currentStock?.on_hand }}</VChip>
                <VChip size="x-small" color="warning">{{ t('stock.reserved') }}: {{ currentStock?.reserved }}</VChip>
                <VChip size="x-small" color="info">{{ t('stock.available') }}: {{ currentStock?.available }}</VChip>
              </div>
            </div>
          </div>
        </VAlert>

        <!-- Form -->
        <VForm ref="formRef" id="stock-movement-form">
          <!-- Hidden field for produit_id validation -->
          <VTextField
            v-model="formData.produit_id"
            :rules="rules.produit_id"
            style="display: none;"
          />

          <VRow>
            <!-- Movement Type (readonly) -->
            <VCol cols="12">
              <VSelect
                v-model="formData.type"
                :items="movementTypeOptions"
                :label="t('stock.fields.type')"
                item-title="label"
                item-value="value"
                variant="outlined"
                readonly
              >
                <template #item="{ props: itemProps, item: selectItem }">
                  <VListItem v-bind="itemProps">
                    <template #prepend>
                      <VIcon
                        :icon="selectItem.raw.icon"
                        :color="selectItem.raw.color"
                      />
                    </template>
                  </VListItem>
                </template>
                <template #selection="{ item: selectItem }">
                  <div class="d-flex align-center">
                    <VIcon
                      :icon="selectItem.raw.icon"
                      :color="selectItem.raw.color"
                      class="me-2"
                    />
                    {{ selectItem.raw.label }}
                  </div>
                </template>
              </VSelect>
            </VCol>

            <!-- Quantity -->
            <VCol cols="12" md="6">
              <VTextField
                v-model.number="formData.quantity"
                :label="t('stock.fields.quantity')"
                :rules="rules.quantity"
                :error-messages="errors.quantity"
                :max="maxQuantity"
                type="number"
                min="1"
                variant="outlined"
                required
              />
            </VCol>

            <!-- Reason -->
            <VCol cols="12" md="6">
              <VSelect
                v-model="formData.reason"
                :items="reasonOptions"
                :label="t('stock.fields.reason')"
                :rules="rules.reason"
                :error-messages="errors.reason"
                item-title="label"
                item-value="value"
                variant="outlined"
                required
              />
            </VCol>

            <!-- Reference -->
            <VCol cols="12" md="6">
              <VTextField
                v-model="formData.reference"
                :label="t('stock.fields.reference')"
                :error-messages="errors.reference"
                variant="outlined"
                placeholder="PO-2025-001, RET-123..."
              />
            </VCol>

            <!-- Note -->
            <VCol cols="12">
              <VTextarea
                v-model="formData.note"
                :label="t('stock.fields.note')"
                :error-messages="errors.note"
                variant="outlined"
                rows="3"
                :placeholder="t('stock.fields.note_placeholder')"
              />
            </VCol>
          </VRow>
        </VForm>
      </VCardText>

      <VCardActions>
        <VSpacer />
        <VBtn
          variant="outlined"
          @click="close"
        >
          {{ t('common.cancel') }}
        </VBtn>
        <VBtn
          color="primary"
          :loading="isSaving"
          @click="save"
        >
          {{ t('stock.actions.create_movement') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
