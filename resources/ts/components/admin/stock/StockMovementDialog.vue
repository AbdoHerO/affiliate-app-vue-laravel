<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
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
    (v: string) => !!v || t('validation.required'),
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
  const productId = props.item?.product.id || ''
  console.log('🔧 [StockMovementDialog] Resetting form with product ID:', productId, 'Item:', props.item)

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
}

const validate = async () => {
  if (!formRef.value) return false
  const { valid } = await formRef.value.validate()
  return valid
}

const save = async () => {
  if (!isMounted.value || !await validate()) return

  // Show confirm dialog
  const typeLabel = MOVEMENT_TYPES.find(t => t.value === props.movementType)?.label || ''
  const confirmText = t('stock.dialog.movement.confirm_text', {
    type: typeLabel.toLowerCase(),
    quantity: formData.value.quantity,
    product: props.item?.product.titre || '',
  })
  const confirmed = await confirmCreate(t('stock.dialog.movement.confirm_title') + ' - ' + typeLabel + '\n\n' + confirmText)
  if (!confirmed) return

  isSaving.value = true
  try {
    console.log('🚀 [StockMovementDialog] Submitting movement:', formData.value)
    await stockStore.createMovement(formData.value)
    if (isMounted.value) {
      emit('saved')
      close()
    }
  } catch (error: any) {
    console.error('Save failed:', error)

    if (isMounted.value) {
      // Handle validation errors from backend
      if (error.status === 422 && error.data?.errors) {
        setErrors(error.data.errors)
      } else {
        showError(error.message || t('stock.errors.movement_failed'))
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
      resetForm()
    }
  }
)

watch(
  () => props.movementType,
  (newType) => {
    formData.value.type = newType
  }
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
        <VForm ref="formRef" @submit.prevent="save">
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
