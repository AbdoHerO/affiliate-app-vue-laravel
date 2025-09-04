<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import ImageMagnifier from '@/components/common/ImageMagnifier.vue'

interface Image {
  url: string
  alt?: string
}

interface Props {
  modelValue: boolean
  images: Image[]
  initialIndex?: number
}

interface Emits {
  (e: 'update:modelValue', value: boolean): void
}

const props = withDefaults(defineProps<Props>(), {
  initialIndex: 0
})

const emit = defineEmits<Emits>()

// State
const currentIndex = ref(props.initialIndex)
const isZoomed = ref(false)
const imageRef = ref<HTMLImageElement>()
const useMagnifier = ref(true) // Toggle between magnifier and simple zoom

// Computed
const isOpen = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
})

const currentImage = computed(() => {
  return props.images[currentIndex.value] || null
})

const hasPrevious = computed(() => currentIndex.value > 0)
const hasNext = computed(() => currentIndex.value < props.images.length - 1)

const canNavigate = computed(() => props.images.length > 1)

// Methods
const close = () => {
  isOpen.value = false
  isZoomed.value = false
}

const previous = () => {
  if (hasPrevious.value) {
    currentIndex.value--
    isZoomed.value = false
  }
}

const next = () => {
  if (hasNext.value) {
    currentIndex.value++
    isZoomed.value = false
  }
}

const toggleZoom = () => {
  isZoomed.value = !isZoomed.value
}

const toggleMagnifier = () => {
  useMagnifier.value = !useMagnifier.value
}

const handleKeydown = (event: KeyboardEvent) => {
  if (!isOpen.value) return

  switch (event.key) {
    case 'Escape':
      close()
      break
    case 'ArrowLeft':
      previous()
      break
    case 'ArrowRight':
      next()
      break
    case ' ':
    case 'Enter':
      event.preventDefault()
      toggleZoom()
      break
  }
}

// Watchers
watch(() => props.modelValue, (newValue) => {
  if (newValue) {
    currentIndex.value = props.initialIndex
    isZoomed.value = false
  }
})

watch(() => props.initialIndex, (newIndex) => {
  if (props.modelValue) {
    currentIndex.value = newIndex
    isZoomed.value = false
  }
})

// Lifecycle
onMounted(() => {
  document.addEventListener('keydown', handleKeydown)
})

onUnmounted(() => {
  document.removeEventListener('keydown', handleKeydown)
})
</script>

<template>
  <VDialog
    v-model="isOpen"
    fullscreen
    :scrim="true"
    transition="dialog-bottom-transition"
    class="image-zoom-modal"
  >
    <VCard v-if="currentImage" class="image-zoom-modal__card">
      <!-- Header with controls -->
      <VCardActions class="image-zoom-modal__header pa-4">
        <div class="d-flex align-center justify-space-between w-100">
          <!-- Navigation info -->
          <div v-if="canNavigate" class="text-white">
            {{ currentIndex + 1 }} / {{ images.length }}
          </div>
          <VSpacer v-else />

          <!-- Action buttons -->
          <div class="d-flex align-center gap-2">
            <VBtn
              :icon="useMagnifier ? 'tabler-focus-2' : 'tabler-zoom-in'"
              variant="text"
              color="white"
              @click="toggleMagnifier"
              :title="useMagnifier ? 'Switch to Simple Zoom' : 'Switch to Magnifier'"
            />
            <VBtn
              v-if="canNavigate"
              icon="tabler-chevron-left"
              variant="text"
              color="white"
              :disabled="!hasPrevious"
              @click="previous"
            />
            <VBtn
              v-if="canNavigate"
              icon="tabler-chevron-right"
              variant="text"
              color="white"
              :disabled="!hasNext"
              @click="next"
            />
            <VBtn
              :icon="isZoomed ? 'tabler-zoom-out' : 'tabler-zoom-in'"
              variant="text"
              color="white"
              @click="toggleZoom"
            />
            <VBtn
              icon="tabler-x"
              variant="text"
              color="white"
              @click="close"
            />
          </div>
        </div>
      </VCardActions>

      <!-- Image container -->
      <VCardText class="image-zoom-modal__content pa-0">
        <div 
          class="image-zoom-modal__image-container"
          :class="{ 'image-zoom-modal__image-container--zoomed': isZoomed }"
        >
          <!-- Magnifier Mode -->
          <div v-if="useMagnifier" class="magnifier-container">
            <ImageMagnifier
              :src="currentImage.url"
              :alt="currentImage.alt || `Image ${currentIndex + 1}`"
              :width="'100%'"
              :height="'100vh'"
              :magnifier-size="250"
              :zoom-level="3.5"
              :border-radius="0"
              class="modal-magnifier"
            />
          </div>

          <!-- Simple Zoom Mode -->
          <div v-else @click="toggleZoom">
            <VImg
              ref="imageRef"
              :src="currentImage.url"
              :alt="currentImage.alt || `Image ${currentIndex + 1}`"
              class="image-zoom-modal__image"
              :class="{ 'image-zoom-modal__image--zoomed': isZoomed }"
              contain
            >
              <template #placeholder>
                <div class="d-flex align-center justify-center fill-height">
                  <VProgressCircular indeterminate color="white" />
                </div>
              </template>
            </VImg>
          </div>
        </div>
      </VCardText>

      <!-- Footer with navigation dots -->
      <VCardActions v-if="canNavigate" class="image-zoom-modal__footer pa-4 justify-center">
        <div class="d-flex gap-2">
          <VBtn
            v-for="(_, index) in images"
            :key="index"
            :icon="index === currentIndex ? 'tabler-circle-filled' : 'tabler-circle'"
            variant="text"
            color="white"
            size="small"
            @click="currentIndex = index; isZoomed = false"
          />
        </div>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.image-zoom-modal__card {
  background: rgba(0, 0, 0, 0.95) !important;
  height: 100vh;
  display: flex;
  flex-direction: column;
}

.image-zoom-modal__header {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  z-index: 10;
  background: linear-gradient(to bottom, rgba(0, 0, 0, 0.7), transparent);
}

.image-zoom-modal__footer {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  z-index: 10;
  background: linear-gradient(to top, rgba(0, 0, 0, 0.7), transparent);
}

.image-zoom-modal__content {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}

.image-zoom-modal__image-container {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: zoom-in;
  transition: all 0.3s ease;
}

.magnifier-container {
  width: 100%;
  height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(0, 0, 0, 0.9);
}

.modal-magnifier {
  max-width: 90%;
  max-height: 90%;
  object-fit: contain;
}

.image-zoom-modal__image-container--zoomed {
  cursor: zoom-out;
  overflow: auto;
}

.image-zoom-modal__image {
  max-width: 90vw;
  max-height: 80vh;
  transition: all 0.3s ease;
}

.image-zoom-modal__image--zoomed {
  max-width: none;
  max-height: none;
  width: 200%;
  height: auto;
  cursor: zoom-out;
}

/* Smooth transitions */
.dialog-bottom-transition-enter-active,
.dialog-bottom-transition-leave-active {
  transition: transform 0.3s ease;
}

.dialog-bottom-transition-enter-from {
  transform: translateY(100%);
}

.dialog-bottom-transition-leave-to {
  transform: translateY(100%);
}
</style>
