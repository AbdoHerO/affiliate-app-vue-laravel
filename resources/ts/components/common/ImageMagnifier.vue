<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'

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
  magnifierSize: 150,
  zoomLevel: 2.5,
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

// Computed
const magnifierStyle = computed(() => {
  if (!isHovering.value || !props.showMagnifier) return { display: 'none' }

  const { x, y } = mousePosition.value
  const size = props.magnifierSize

  return {
    position: 'absolute',
    left: `${x - size / 2}px`,
    top: `${y - size / 2}px`,
    width: `${size}px`,
    height: `${size}px`,
    border: '3px solid rgba(255, 255, 255, 0.8)',
    borderRadius: '50%',
    cursor: 'none',
    pointerEvents: 'none',
    zIndex: 1000,
    boxShadow: '0 4px 12px rgba(0, 0, 0, 0.3)',
    backgroundImage: `url(${props.src})`,
    backgroundRepeat: 'no-repeat',
    backgroundSize: `${(imageRef.value?.naturalWidth || 0) * props.zoomLevel}px ${(imageRef.value?.naturalHeight || 0) * props.zoomLevel}px`,
    backgroundPosition: `${-x * props.zoomLevel + size / 2}px ${-y * props.zoomLevel + size / 2}px`
  }
})

const imageStyle = computed(() => ({
  width: typeof props.width === 'number' ? `${props.width}px` : props.width,
  height: typeof props.height === 'number' ? `${props.height}px` : props.height,
  borderRadius: `${props.borderRadius}px`,
  cursor: props.showMagnifier ? 'none' : 'pointer',
  display: 'block',
  maxWidth: '100%'
}))

// Methods
const handleMouseMove = (event: MouseEvent) => {
  if (!containerRef.value || !imageRef.value) return

  const rect = containerRef.value.getBoundingClientRect()
  const x = event.clientX - rect.left
  const y = event.clientY - rect.top

  // Ensure coordinates are within image bounds
  const imageRect = imageRef.value.getBoundingClientRect()
  const containerRect = containerRef.value.getBoundingClientRect()
  
  const relativeX = Math.max(0, Math.min(x, imageRect.width))
  const relativeY = Math.max(0, Math.min(y, imageRect.height))

  mousePosition.value = { x: relativeX, y: relativeY }
}

const handleMouseEnter = () => {
  if (props.showMagnifier) {
    isHovering.value = true
  }
}

const handleMouseLeave = () => {
  isHovering.value = false
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
        size="24"
        color="white"
      />
      <span class="text-caption text-white ml-1">Hover to magnify</span>
    </div>
  </div>
</template>

<style scoped>
.image-magnifier-container {
  position: relative;
  display: inline-block;
}

.image-magnifier-image {
  transition: all 0.2s ease;
}

.image-magnifier-lens {
  background-color: rgba(255, 255, 255, 0.1);
  backdrop-filter: blur(1px);
}

.image-magnifier-hint {
  position: absolute;
  top: 8px;
  right: 8px;
  background: rgba(0, 0, 0, 0.6);
  border-radius: 4px;
  padding: 4px 8px;
  display: flex;
  align-items: center;
  opacity: 0;
  transition: opacity 0.3s ease;
  pointer-events: none;
}

.image-magnifier-container:hover .image-magnifier-hint {
  opacity: 1;
}

@media (max-width: 768px) {
  .image-magnifier-hint {
    display: none;
  }
}
</style>
