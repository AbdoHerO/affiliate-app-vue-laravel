<script setup lang="ts">
import { computed, onMounted, onUnmounted } from 'vue'
import { useSettingsStore } from '@/stores/settings'

// Use a fallback logo path
const defaultLogo = '/images/logo.svg'

interface Props {
  height?: string
  width?: string
  alt?: string
  class?: string
}

const props = withDefaults(defineProps<Props>(), {
  height: '55px',
  width: 'auto',
  alt: 'Logo',
  class: ''
})

const settingsStore = useSettingsStore()

// Computed logo source with fallback
const logoSrc = computed(() => {
  return settingsStore.appLogo || defaultLogo
})

// Computed app name with fallback
const appName = computed(() => {
  return settingsStore.appName || 'Affiliate Platform'
})

// Listen for settings updates
const handleSettingsUpdate = () => {
  // Force reactivity update
  settingsStore.fetchSettings()
}

onMounted(() => {
  window.addEventListener('settings:updated', handleSettingsUpdate)
})

onUnmounted(() => {
  window.removeEventListener('settings:updated', handleSettingsUpdate)
})
</script>

<template>
  <img
    :src="logoSrc"
    :alt="props.alt || appName"
    :class="props.class"
    :style="{
      height: props.height,
      width: props.width,
      objectFit: 'contain'
    }"
  >
</template>
