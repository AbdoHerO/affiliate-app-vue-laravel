<template>
  <VDialog
    :model-value="modelValue"
    max-width="500"
    persistent
    @update:model-value="updateModelValue"
    @keydown.esc="onCancel"
  >
    <VCard class="text-center px-10 py-6">
      <VCardText>
        <!-- Icon -->
        <VBtn
          icon="tabler-truck"
          variant="outlined"
          color="primary"
          class="my-4"
          style="block-size: 88px; inline-size: 88px; pointer-events: none;"
          size="large"
        >
          <VIcon
            icon="tabler-truck"
            size="38"
          />
        </VBtn>

        <!-- Title -->
        <h6 class="text-lg font-weight-medium mb-2">
          {{ title }}
        </h6>

        <!-- Message -->
        <p class="text-body-1 text-medium-emphasis mb-4">
          {{ text }}
        </p>

        <!-- Order Type Toggle -->
        <VCard variant="outlined" class="mb-4">
          <VCardText class="py-4">
            <div class="text-body-2 font-weight-medium mb-3">
              Type de commande :
            </div>
            
            <VBtnToggle
              v-model="selectedMode"
              mandatory
              variant="outlined"
              divided
              class="w-100"
            >
              <VBtn
                value="ramassage"
                class="flex-1"
                :color="selectedMode === 'ramassage' ? 'success' : 'default'"
              >
                <VIcon start icon="tabler-package" />
                Ramassage
                <VTooltip activator="parent" location="bottom">
                  Colis à récupérer chez le client (COD)
                </VTooltip>
              </VBtn>
              
              <VBtn
                value="stock"
                class="flex-1"
                :color="selectedMode === 'stock' ? 'info' : 'default'"
              >
                <VIcon start icon="tabler-building-warehouse" />
                Stock
                <VTooltip activator="parent" location="bottom">
                  Colis en stock/entrepôt
                </VTooltip>
              </VBtn>
            </VBtnToggle>

            <!-- Mode Description -->
            <div class="mt-3 text-caption text-medium-emphasis">
              <template v-if="selectedMode === 'ramassage'">
                <VIcon icon="tabler-info-circle" size="14" class="me-1" />
                Le colis apparaîtra dans "Colis Ramassage" sur OzonExpress
              </template>
              <template v-else>
                <VIcon icon="tabler-info-circle" size="14" class="me-1" />
                Le colis apparaîtra dans la gestion de stock sur OzonExpress
              </template>
            </div>
          </VCardText>
        </VCard>
      </VCardText>

      <!-- Actions -->
      <VCardText class="d-flex align-center justify-center gap-3">
        <VBtn
          variant="outlined"
          color="secondary"
          :disabled="loading"
          @click="onCancel"
        >
          {{ cancelText }}
        </VBtn>
        
        <VBtn
          color="primary"
          :loading="loading"
          variant="elevated"
          @click="onConfirm"
        >
          {{ confirmText }}
        </VBtn>
      </VCardText>
    </VCard>
  </VDialog>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'

interface Props {
  modelValue: boolean
  title?: string
  text?: string
  confirmText?: string
  cancelText?: string
  loading?: boolean
  defaultMode?: 'ramassage' | 'stock'
}

interface Emit {
  (e: 'update:modelValue', value: boolean): void
  (e: 'confirm', mode: 'ramassage' | 'stock'): void
  (e: 'cancel'): void
}

const props = withDefaults(defineProps<Props>(), {
  title: 'Envoyer vers OzonExpress',
  text: 'Êtes-vous sûr de vouloir envoyer cette commande vers OzonExpress ?',
  confirmText: 'Envoyer',
  cancelText: 'Annuler',
  loading: false,
  defaultMode: 'ramassage',
})

const emit = defineEmits<Emit>()

// Local state
const selectedMode = ref<'ramassage' | 'stock'>(props.defaultMode)

// Reset mode when dialog opens
watch(() => props.modelValue, (newValue) => {
  if (newValue) {
    selectedMode.value = props.defaultMode
  }
})

const updateModelValue = (val: boolean) => {
  emit('update:modelValue', val)
}

const onConfirm = () => {
  emit('confirm', selectedMode.value)
  updateModelValue(false)
}

const onCancel = () => {
  emit('cancel')
  updateModelValue(false)
}
</script>

<style scoped>
.flex-1 {
  flex: 1;
}
</style>
