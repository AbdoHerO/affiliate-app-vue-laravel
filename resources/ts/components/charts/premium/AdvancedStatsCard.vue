<script setup lang="ts">
import { computed } from 'vue'

interface Props {
  data: {
    title: string
    value: string
    subtitle?: string
    icon: string
    color: string
    trend?: {
      value: string
      direction: 'up' | 'down'
      period: string
    }
    progress?: {
      value: number
      label: string
    }
    comparison?: {
      current: string
      previous: string
      label: string
    }
  }
  loading?: boolean
  size?: 'small' | 'medium' | 'large'
}

const props = withDefaults(defineProps<Props>(), {
  loading: false,
  size: 'medium',
})

// Data validation to prevent runtime errors / NaN propagation
const isValidData = computed(() => {
  if (props.loading) return false
  const d: any = props.data
  if (!d || typeof d !== 'object') return false
  const hasValue = d.value !== undefined && d.value !== null && String(d.value).length > 0
  return hasValue
})

// Provide a safe color + icon fallback so loading state doesn't crash when data missing
const safeColor = computed(() => props.data?.color || 'primary')
const safeIcon = computed(() => props.data?.icon || 'tabler-chart-bar')
</script>

<template>
  <div class="advanced-chart-container">
    <VCard
      class="advanced-stats-card"
      :class="`stats-card-${size}`"
    >
      <VCardText v-if="!loading && isValidData">
        <div class="d-flex align-center justify-space-between mb-3">
          <VAvatar
            :color="data.color || safeColor"
            variant="tonal"
            :size="size === 'large' ? 48 : size === 'medium' ? 40 : 32"
            rounded
          >
            <VIcon
              :icon="data.icon || safeIcon"
              :size="size === 'large' ? 24 : size === 'medium' ? 20 : 16"
            />
          </VAvatar>

          <div 
            v-if="data.trend"
            class="d-flex align-center"
            :class="data.trend.direction === 'up' ? 'text-success' : 'text-error'"
          >
            <VIcon
              :icon="data.trend.direction === 'up' ? 'tabler-trending-up' : 'tabler-trending-down'"
              size="20"
              class="me-1"
            />
            <span class="text-sm font-weight-medium">{{ data.trend.value }}</span>
          </div>
        </div>

        <div class="mb-2">
          <h3 
            class="font-weight-bold"
            :class="size === 'large' ? 'text-h3' : size === 'medium' ? 'text-h4' : 'text-h5'"
          >
            {{ data.value }}
          </h3>
          <div class="text-body-1 font-weight-medium">
            {{ data.title }}
          </div>
          <div 
            v-if="data.subtitle"
            class="text-body-2 text-disabled"
          >
            {{ data.subtitle }}
          </div>
        </div>

        <!-- Progress Section -->
        <div 
          v-if="data.progress"
          class="mt-4"
        >
          <div class="d-flex align-center justify-space-between mb-2">
            <span class="text-sm">{{ data.progress.label }}</span>
            <span class="text-sm font-weight-medium">{{ data.progress.value }}%</span>
          </div>
          <VProgressLinear
            :model-value="data.progress.value"
            :color="data.color"
            height="6"
            rounded
            rounded-bar
          />
        </div>

        <!-- Comparison Section -->
        <div 
          v-if="data.comparison"
          class="mt-4"
        >
          <div class="d-flex align-center justify-space-between">
            <div>
              <div class="text-body-2 text-disabled">{{ data.comparison.label }}</div>
              <div class="text-sm font-weight-medium">{{ data.comparison.current }}</div>
            </div>
            <div class="text-end">
              <div class="text-body-2 text-disabled">Previous</div>
              <div class="text-sm">{{ data.comparison.previous }}</div>
            </div>
          </div>
        </div>

        <!-- Trend Period -->
        <div 
          v-if="data.trend"
          class="mt-3"
        >
          <div class="text-caption text-disabled">
            {{ data.trend.period }}
          </div>
        </div>
      </VCardText>

      <!-- Loading State -->
      <VCardText
        v-else
        class="d-flex align-center justify-center chart-loading"
        :style="`height: ${size === 'large' ? '200px' : size === 'medium' ? '150px' : '120px'};`"
      >
        <VProgressCircular
          indeterminate
          :color="safeColor"
          :size="size === 'large' ? 48 : size === 'medium' ? 40 : 32"
        />
      </VCardText>
    </VCard>
  </div>
</template>

<style lang="scss" scoped>
.advanced-chart-container { // unified container for consistent heights
  height: 100%;
  .v-card { height: 100%; display: flex; flex-direction: column; }
  @media (max-width: 768px) { min-height: 180px; }
  @media (max-width: 480px) { min-height: 160px; }
}

.advanced-stats-card {
  transition: all 0.3s ease;
  position: relative;
  overflow: hidden;
  
  &:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
  }

  &::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, 
      rgba(var(--v-theme-primary), 1) 0%, 
      rgba(var(--v-theme-secondary), 1) 100%);
    opacity: 0;
    transition: opacity 0.3s ease;
  }

  &:hover::before {
    opacity: 1;
  }

  .v-avatar {
    transition: all 0.3s ease;
    
    &:hover {
      transform: scale(1.1) rotate(5deg);
    }
  }

  .v-progress-linear {
    transition: all 0.3s ease;
    
    &:hover {
      transform: scaleY(1.2);
    }
  }

  &.stats-card-small {
    min-height: 120px;
  }

  &.stats-card-medium {
    min-height: 150px;
  }

  &.stats-card-large {
    min-height: 200px;
  }
}

// Pulse animation for loading
@keyframes pulse {
  0%, 100% {
    opacity: 1;
  }
  50% {
    opacity: 0.5;
  }
}

.chart-loading .v-progress-circular {
  animation: pulse 2s ease-in-out infinite;
}
</style>
