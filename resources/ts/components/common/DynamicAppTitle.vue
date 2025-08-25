<script setup lang="ts">
import { computed, onMounted, onUnmounted } from 'vue'
import { useSettingsStore } from '@/stores/settings'

interface Props {
  tag?: string
  class?: string
}

const props = withDefaults(defineProps<Props>(), {
  tag: 'h1',
  class: 'app-title font-weight-bold leading-normal text-xl text-capitalize'
})

const settingsStore = useSettingsStore()

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
  <component
    :is="props.tag"
    :class="props.class"
  >
    {{ appName }}
  </component>
</template>
