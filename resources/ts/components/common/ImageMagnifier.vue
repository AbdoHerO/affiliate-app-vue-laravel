<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue'

interface Props {
  src: string
  alt?: string
  magnifierSize?: number
  zoomLevel?: number
  width?: number | string
  height?: number | string
  borderRadius?: number
  showMagnifier?: boolean
}

interface Emits {
  (e: 'click'): void
}

const props = withDefaults(defineProps<Props>(), {
  alt: '',
  magnifierSize: 150,    // Subtle/Professional default size
  zoomLevel: 2.2,        // Subtle/Professional default zoom
  width: '100%',
  height: 'auto',
  borderRadius: 8,
  showMagnifier: true
})

const emit = defineEmits<Emits>()

// Refs
const imageRef = ref<HTMLImageElement>()
const containerRef = ref<HTMLDivElement>()
const magnifierRef = ref<HTMLDivElement>()
const isHovering = ref(false)
const mousePosition = ref({ x: 0, y: 0 })
const imageLoaded = ref(false)

// Computed
const magnifierStyle = computed(() => {
  if (!isHovering.value || !props.showMagnifier || !imageRef.value) return { display: 'none' }

  const { x, y } = mousePosition.value
  const size = props.magnifierSize

  // Get image dimensions
  const imageRect = imageRef.value.getBoundingClientRect()
  const naturalWidth = imageRef.value.naturalWidth || imageRect.width
  const naturalHeight = imageRef.value.naturalHeight || imageRect.height
  
  // Calculate scale factors
  const scaleX = naturalWidth / imageRect.width
  const scaleY = naturalHeight / imageRect.height
  
  // Calculate the exact position in the natural image that the mouse is pointing to
  const mouseXInNaturalImage = x * scaleX
  const mouseYInNaturalImage = y * scaleY
  
  // Calculate background size (zoomed natural image size)
  const bgWidth = naturalWidth * props.zoomLevel
  const bgHeight = naturalHeight * props.zoomLevel
  
  // Calculate background position to center the mouse point in the magnifier
  const bgX = -(mouseXInNaturalImage * props.zoomLevel - size / 2)
  const bgY = -(mouseYInNaturalImage * props.zoomLevel - size / 2)

  return {
    position: 'absolute' as const,
    left: `${x - size / 2}px`,
    top: `${y - size / 2}px`,
    width: `${size}px`,
    height: `${size}px`,
    border: '3px solid rgba(255, 255, 255, 0.95)',
    borderRadius: '50%',
    cursor: 'none' as const,
    pointerEvents: 'none' as const,
    zIndex: 1000,
    boxShadow: '0 8px 32px rgba(0, 0, 0, 0.3), inset 0 0 0 1px rgba(0, 0, 0, 0.1)',
    backgroundImage: `url(${props.src})`,
    backgroundRepeat: 'no-repeat' as const,
    backgroundSize: `${bgWidth}px ${bgHeight}px`,
    backgroundPosition: `${bgX}px ${bgY}px`,
    transition: 'all 0.1s ease-out',
    backdropFilter: 'blur(0.5px)'
  }
})

const imageStyle = computed(() => ({
  width: typeof props.width === 'number' ? `${props.width}px` : props.width,
  height: typeof props.height === 'number' ? `${props.height}px` : props.height,
  borderRadius: `${props.borderRadius}px`,
  cursor: props.showMagnifier ? 'crosshair' : 'pointer',
  display: 'block',
  maxWidth: '100%',
  transition: 'all 0.2s ease',
  userSelect: 'none' as const
}))

// Methods
const handleMouseMove = (event: MouseEvent) => {
  if (!containerRef.value || !imageRef.value) return

  const imageRect = imageRef.value.getBoundingClientRect()

  // Calculate precise mouse position relative to the image
  const x = event.clientX - imageRect.left
  const y = event.clientY - imageRect.top

  // Ensure coordinates are within image bounds and account for any potential padding/border
  const relativeX = Math.max(0, Math.min(x, imageRect.width))
  const relativeY = Math.max(0, Math.min(y, imageRect.height))

  mousePosition.value = { 
    x: relativeX, 
    y: relativeY
  }
}

const handleMouseEnter = () => {
  if (props.showMagnifier && imageLoaded.value && imageRef.value) {
    // Ensure image dimensions are available
    if (imageRef.value.naturalWidth && imageRef.value.naturalHeight) {
      isHovering.value = true
    }
  }
}

const handleMouseLeave = () => {
  isHovering.value = false
}

const handleImageLoad = () => {
  imageLoaded.value = true
  // Trigger a reflow to ensure dimensions are calculated
  if (imageRef.value) {
    nextTick(() => {
      // Force recalculation of image dimensions
      const rect = imageRef.value?.getBoundingClientRect()
    })
  }
}

const handleClick = () => {
  emit('click')
}

// Lifecycle
onMounted(() => {
  if (containerRef.value) {
    containerRef.value.addEventListener('mousemove', handleMouseMove)
    containerRef.value.addEventListener('mouseenter', handleMouseEnter)
    containerRef.value.addEventListener('mouseleave', handleMouseLeave)
  }
})

onUnmounted(() => {
  if (containerRef.value) {
    containerRef.value.removeEventListener('mousemove', handleMouseMove)
    containerRef.value.removeEventListener('mouseenter', handleMouseEnter)
    containerRef.value.removeEventListener('mouseleave', handleMouseLeave)
  }
})
</script>

<template>
  <div 
    ref="containerRef"
    class="image-magnifier-container"
    :style="{ position: 'relative', display: 'inline-block' }"
  >
    <img
      ref="imageRef"
      :src="src"
      :alt="alt"
      :style="imageStyle"
      class="image-magnifier-image"
      @click="handleClick"
      @load="handleImageLoad"
    />
    
    <!-- Magnifier lens -->
    <div
      v-if="showMagnifier"
      ref="magnifierRef"
      class="image-magnifier-lens"
      :style="magnifierStyle"
    />
    
    <!-- Hover indicator -->
    <div
      v-if="showMagnifier && !isHovering"
      class="image-magnifier-hint"
    >
      <VIcon
        icon="tabler-zoom-in"
        size="18"
        color="white"
      />
      <span class="text-caption text-white ml-1">Survoler pour agrandir</span>
    </div>

    <!-- Click indicator -->
    <div
      v-if="showMagnifier"
      class="image-magnifier-click-hint"
      @click="handleClick"
    >
      <VIcon
        icon="tabler-fullscreen"
        size="16"
        color="white"
      />
    </div>
  </div>
</template>

<style scoped>
.image-magnifier-container {
  position: relative;
  display: inline-block;
  overflow: hidden;
  border-radius: 8px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  transition: all 0.3s ease;
}

.image-magnifier-container:hover {
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
}

.image-magnifier-image {
  display: block;
  max-width: 100%;
  height: auto;
  transition: transform 0.2s ease;
  image-rendering: -webkit-optimize-contrast;
  image-rendering: crisp-edges;
}

.image-magnifier-container:hover .image-magnifier-image {
  transform: scale(1.01);
}

.image-magnifier-lens {
  border: 3px solid rgba(255, 255, 255, 0.95) !important;
  box-shadow: 
    0 8px 32px rgba(0, 0, 0, 0.3), 
    inset 0 0 0 1px rgba(0, 0, 0, 0.1),
    0 0 0 1px rgba(255, 255, 255, 0.2) !important;
  backdrop-filter: blur(0.5px);
  transform: scale(1);
  animation: magnifierPulse 0.3s ease-out;
}

@keyframes magnifierPulse {
  0% {
    transform: scale(0.8);
    opacity: 0;
  }
  100% {
    transform: scale(1);
    opacity: 1;
  }
}

.image-magnifier-hint {
  position: absolute;
  top: 12px;
  left: 12px;
  background: linear-gradient(135deg, rgba(0, 0, 0, 0.8), rgba(0, 0, 0, 0.6));
  padding: 8px 12px;
  border-radius: 20px;
  display: flex;
  align-items: center;
  backdrop-filter: blur(8px);
  transition: all 0.3s ease;
  border: 1px solid rgba(255, 255, 255, 0.1);
  opacity: 0;
  transform: translateY(-4px);
  animation: hintFadeIn 0.5s ease-out 0.3s forwards;
}

@keyframes hintFadeIn {
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.image-magnifier-click-hint {
  position: absolute;
  top: 12px;
  right: 12px;
  background: linear-gradient(135deg, rgba(0, 0, 0, 0.8), rgba(0, 0, 0, 0.6));
  padding: 8px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  backdrop-filter: blur(8px);
  transition: all 0.3s ease;
  cursor: pointer;
  border: 1px solid rgba(255, 255, 255, 0.1);
  opacity: 0.8;
}

.image-magnifier-click-hint:hover {
  background: linear-gradient(135deg, rgba(0, 0, 0, 0.9), rgba(0, 0, 0, 0.7));
  transform: scale(1.05);
  opacity: 1;
}

.image-magnifier-image {
  transition: all 0.2s ease;
  will-change: transform;
}

.image-magnifier-lens {
  background-color: rgba(255, 255, 255, 0.05);
  backdrop-filter: blur(1px);
  will-change: transform, left, top;
}

@media (max-width: 768px) {
  .image-magnifier-hint {
    display: none;
  }
  
  .image-magnifier-lens {
    display: none;
  }
  
  .image-magnifier-click-hint {
    padding: 6px;
  }
}

@media (hover: none) {
  .image-magnifier-lens {
    display: none;
  }
  
  .image-magnifier-hint {
    display: none;
  }
}
</style>
