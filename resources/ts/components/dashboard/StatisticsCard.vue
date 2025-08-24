<script setup lang="ts">
import { computed } from 'vue'

interface Props {
  title: string
  value: string | number
  subtitle?: string
  icon?: string
  color?: string
  trend?: {
    value: number
    label?: string
    isPositive?: boolean
  }
  loading?: boolean
  error?: string
  prefix?: string
  suffix?: string
  size?: 'small' | 'medium' | 'large'
}

const props = withDefaults(defineProps<Props>(), {
  subtitle: '',
  icon: '',
  color: 'primary',
  loading: false,
  error: '',
  prefix: '',
  suffix: '',
  size: 'medium',
})

const formattedValue = computed(() => {
  if (props.loading) return '...'
  if (props.error) return 'Error'
  
  let value = props.value
  
  // Format numbers
  if (typeof value === 'number') {
    if (value >= 1000000) {
      value = (value / 1000000).toFixed(1) + 'M'
    } else if (value >= 1000) {
      value = (value / 1000).toFixed(1) + 'K'
    } else {
      value = value.toLocaleString()
    }
  }
  
  return `${props.prefix}${value}${props.suffix}`
})

const trendIcon = computed(() => {
  if (!props.trend) return ''
  
  if (props.trend.isPositive === undefined) {
    return props.trend.value > 0 ? 'tabler-trending-up' : 'tabler-trending-down'
  }
  
  return props.trend.isPositive ? 'tabler-trending-up' : 'tabler-trending-down'
})

const trendColor = computed(() => {
  if (!props.trend) return ''
  
  if (props.trend.isPositive === undefined) {
    return props.trend.value > 0 ? 'success' : 'error'
  }
  
  return props.trend.isPositive ? 'success' : 'error'
})

const cardClasses = computed(() => {
  const classes = ['statistics-card']
  
  if (props.size) {
    classes.push(`statistics-card--${props.size}`)
  }
  
  return classes
})

const iconSize = computed(() => {
  switch (props.size) {
    case 'small':
      return 32
    case 'large':
      return 48
    default:
      return 40
  }
})

const titleSize = computed(() => {
  switch (props.size) {
    case 'small':
      return 'text-body-2'
    case 'large':
      return 'text-h6'
    default:
      return 'text-body-1'
  }
})

const valueSize = computed(() => {
  switch (props.size) {
    case 'small':
      return 'text-h6'
    case 'large':
      return 'text-h3'
    default:
      return 'text-h4'
  }
})
</script>

<template>
  <VCard
    :class="cardClasses"
    :loading="loading"
  >
    <VCardText>
      <div class="d-flex align-center justify-space-between">
        <div class="flex-grow-1">
          <!-- Title -->
          <div
            :class="[titleSize, 'text-medium-emphasis mb-1']"
          >
            {{ title }}
          </div>
          
          <!-- Value -->
          <div
            :class="[valueSize, 'font-weight-bold mb-1']"
            :style="{ color: error ? 'rgb(var(--v-theme-error))' : 'rgb(var(--v-theme-on-surface))' }"
          >
            {{ formattedValue }}
          </div>
          
          <!-- Subtitle -->
          <div
            v-if="subtitle"
            class="text-caption text-medium-emphasis"
          >
            {{ subtitle }}
          </div>
          
          <!-- Trend -->
          <div
            v-if="trend && !loading && !error"
            class="d-flex align-center mt-2"
          >
            <VIcon
              :icon="trendIcon"
              :color="trendColor"
              size="16"
              class="me-1"
            />
            <span
              :class="[`text-${trendColor}`, 'text-caption font-weight-medium']"
            >
              {{ Math.abs(trend.value) }}%
            </span>
            <span
              v-if="trend.label"
              class="text-caption text-medium-emphasis ms-1"
            >
              {{ trend.label }}
            </span>
          </div>
        </div>
        
        <!-- Icon -->
        <div
          v-if="icon"
          class="flex-shrink-0 ms-4"
        >
          <VAvatar
            :color="color"
            :size="iconSize + 8"
            variant="tonal"
          >
            <VIcon
              :icon="icon"
              :size="iconSize"
            />
          </VAvatar>
        </div>
      </div>
      
      <!-- Error Message -->
      <div
        v-if="error"
        class="mt-2"
      >
        <VAlert
          type="error"
          variant="tonal"
          density="compact"
          class="text-caption"
        >
          {{ error }}
        </VAlert>
      </div>
    </VCardText>
  </VCard>
</template>

<style scoped>
.statistics-card {
  height: 100%;
  transition: all 0.2s ease-in-out;
}

.statistics-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
}

.statistics-card--small .v-card-text {
  padding: 1rem;
}

.statistics-card--medium .v-card-text {
  padding: 1.25rem;
}

.statistics-card--large .v-card-text {
  padding: 1.5rem;
}

.statistics-card .v-card-text {
  height: 100%;
}
</style>
