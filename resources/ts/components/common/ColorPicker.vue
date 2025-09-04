<script setup lang="ts">
import { ref, computed, watch } from 'vue'

interface Props {
  modelValue?: string
  label?: string
  placeholder?: string
  errorMessages?: string[]
  disabled?: boolean
  showPalette?: boolean
  showHexInput?: boolean
  variant?: 'outlined' | 'filled' | 'underlined' | 'plain' | 'solo' | 'solo-inverted' | 'solo-filled'
}

interface Emits {
  (e: 'update:modelValue', value: string): void
}

const props = withDefaults(defineProps<Props>(), {
  modelValue: '',
  label: 'Color',
  placeholder: '#000000',
  errorMessages: () => [],
  disabled: false,
  showPalette: true,
  showHexInput: true,
  variant: 'outlined'
})

const emit = defineEmits<Emits>()

// Local state
const hexInput = ref(props.modelValue || '')
const showColorPicker = ref(false)

// Predefined color palette for clothing
const colorPalette = [
  // Basic colors
  '#000000', '#FFFFFF', '#808080', '#C0C0C0',
  // Reds
  '#FF0000', '#DC143C', '#B22222', '#8B0000', '#FF6B6B', '#FF4757',
  // Blues
  '#0000FF', '#000080', '#4169E1', '#1E90FF', '#87CEEB', '#6C5CE7',
  // Greens
  '#008000', '#00FF00', '#228B22', '#32CD32', '#90EE90', '#00B894',
  // Yellows/Oranges
  '#FFFF00', '#FFD700', '#FFA500', '#FF8C00', '#FF7675', '#FDCB6E',
  // Purples/Pinks
  '#800080', '#9932CC', '#DA70D6', '#FF69B4', '#FFC0CB', '#A29BFE',
  // Browns/Beiges
  '#8B4513', '#D2691E', '#CD853F', '#F5DEB3', '#DDD6FE', '#F5F5DC',
  // Fashion colors
  '#2D3436', '#636E72', '#74B9FF', '#0984E3', '#00CEC9', '#00B894',
  '#FDCB6E', '#E17055', '#FD79A8', '#E84393', '#A29BFE', '#6C5CE7'
]

// Computed
const selectedColor = computed({
  get: () => props.modelValue || hexInput.value,
  set: (value: string) => {
    hexInput.value = value
    emit('update:modelValue', value)
  }
})

const isValidHex = computed(() => {
  const hex = hexInput.value
  return /^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/.test(hex)
})

const previewStyle = computed(() => ({
  backgroundColor: isValidHex.value ? hexInput.value : '#transparent',
  border: isValidHex.value ? 'none' : '2px dashed #ccc'
}))

// Methods
const selectPaletteColor = (color: string) => {
  selectedColor.value = color
  showColorPicker.value = false
}

const handleHexInput = (value: string) => {
  let hex = value.trim()
  
  // Add # if missing
  if (hex && !hex.startsWith('#')) {
    hex = '#' + hex
  }
  
  hexInput.value = hex
  
  // Only emit if valid hex
  if (isValidHex.value) {
    emit('update:modelValue', hex)
  }
}

const openColorPicker = () => {
  if (!props.disabled) {
    showColorPicker.value = true
  }
}

// Watch for external changes
watch(() => props.modelValue, (newValue) => {
  if (newValue !== hexInput.value) {
    hexInput.value = newValue || ''
  }
})
</script>

<template>
  <div class="color-picker">
    <!-- Color Preview and Hex Input -->
    <div class="d-flex gap-3 align-center">
      <!-- Color Preview -->
      <div
        class="color-preview cursor-pointer"
        :style="previewStyle"
        @click="openColorPicker"
      >
        <VIcon
          v-if="!isValidHex"
          icon="tabler-palette"
          size="20"
          color="grey"
        />
      </div>

      <!-- Hex Input -->
      <VTextField
        v-if="showHexInput"
        :model-value="hexInput"
        :label="label"
        :placeholder="placeholder"
        :error-messages="errorMessages"
        :disabled="disabled"
        :variant="variant"
        class="flex-grow-1"
        @update:model-value="handleHexInput"
      >
        <template #append-inner>
          <VBtn
            icon="tabler-palette"
            variant="text"
            size="small"
            :disabled="disabled"
            @click="openColorPicker"
          />
        </template>
      </VTextField>
    </div>

    <!-- Color Palette Dialog -->
    <VDialog
      v-model="showColorPicker"
      max-width="400"
      :disabled="disabled"
    >
      <VCard>
        <VCardTitle class="d-flex align-center justify-space-between">
          <span>Select Color</span>
          <VBtn
            icon="tabler-x"
            variant="text"
            size="small"
            @click="showColorPicker = false"
          />
        </VCardTitle>
        
        <VCardText>
          <!-- Vuetify Color Picker -->
          <VColorPicker
            v-model="selectedColor"
            mode="hex"
            :modes="['hex']"
            class="mb-4"
            @update:model-value="(color) => selectedColor = color"
          />

          <!-- Predefined Palette -->
          <div v-if="showPalette" class="mb-4">
            <div class="text-subtitle-2 mb-2">Quick Colors</div>
            <div class="color-palette">
              <div
                v-for="color in colorPalette"
                :key="color"
                class="palette-color cursor-pointer"
                :style="{ backgroundColor: color }"
                :class="{ 'palette-color--selected': selectedColor === color }"
                @click="selectPaletteColor(color)"
              />
            </div>
          </div>

          <!-- Hex Input in Dialog -->
          <VTextField
            :model-value="hexInput"
            label="Hex Code"
            placeholder="#000000"
            variant="outlined"
            density="compact"
            @update:model-value="handleHexInput"
          />
        </VCardText>

        <VCardActions>
          <VSpacer />
          <VBtn
            variant="text"
            @click="showColorPicker = false"
          >
            Cancel
          </VBtn>
          <VBtn
            color="primary"
            :disabled="!isValidHex"
            @click="showColorPicker = false"
          >
            Select
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.color-picker {
  width: 100%;
}

.color-preview {
  width: 40px;
  height: 40px;
  border-radius: 8px;
  border: 2px solid rgba(var(--v-border-color), var(--v-border-opacity));
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s ease;
  flex-shrink: 0;
}

.color-preview:hover {
  transform: scale(1.05);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.color-palette {
  display: grid;
  grid-template-columns: repeat(8, 1fr);
  gap: 8px;
}

.palette-color {
  width: 32px;
  height: 32px;
  border-radius: 6px;
  border: 2px solid transparent;
  transition: all 0.2s ease;
}

.palette-color:hover {
  transform: scale(1.1);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
}

.palette-color--selected {
  border-color: rgb(var(--v-theme-primary));
  box-shadow: 0 0 0 2px rgba(var(--v-theme-primary), 0.3);
}
</style>
