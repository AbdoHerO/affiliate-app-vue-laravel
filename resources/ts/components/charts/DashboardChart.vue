<script setup lang="ts">
import { computed, defineAsyncComponent } from 'vue'
import type { TimeSeriesData } from '@/types/dashboard'

// Lazy load chart components
const DashboardLineChart = defineAsyncComponent(() => import('./DashboardLineChart.vue'))
const DashboardBarChart = defineAsyncComponent(() => import('./DashboardBarChart.vue'))
const DashboardAreaChart = defineAsyncComponent(() => import('./DashboardAreaChart.vue'))
const DashboardDoughnutChart = defineAsyncComponent(() => import('./DashboardDoughnutChart.vue'))

interface Props {
  type: 'line' | 'bar' | 'area' | 'doughnut' | 'pie'
  data: TimeSeriesData | {
    labels: string[]
    datasets: Array<{
      label: string
      data: number[]
      backgroundColor?: string | string[]
      borderColor?: string | string[]
      borderWidth?: number
    }>
  }
  title?: string
  height?: number
  loading?: boolean
  error?: string
  responsive?: boolean
  maintainAspectRatio?: boolean
  showLegend?: boolean
  showTooltip?: boolean
  // Line/Area specific props
  tension?: number
  pointRadius?: number
  borderWidth?: number
  fill?: boolean
  fillOpacity?: number
  // Bar specific props
  horizontal?: boolean
  borderRadius?: number
  // Doughnut/Pie specific props
  cutout?: string | number
}

const props = withDefaults(defineProps<Props>(), {
  title: '',
  height: 400,
  loading: false,
  error: '',
  responsive: true,
  maintainAspectRatio: false,
  showLegend: true,
  showTooltip: true,
  tension: 0.4,
  pointRadius: 4,
  borderWidth: 2,
  fill: true,
  fillOpacity: 0.1,
  horizontal: false,
  borderRadius: 4,
  cutout: '60%',
})

const chartComponent = computed(() => {
  switch (props.type) {
    case 'line':
      return DashboardLineChart
    case 'bar':
      return DashboardBarChart
    case 'area':
      return DashboardAreaChart
    case 'doughnut':
    case 'pie':
      return DashboardDoughnutChart
    default:
      return DashboardLineChart
  }
})

const chartProps = computed(() => {
  const baseProps = {
    data: props.data,
    title: props.title,
    height: props.height,
    responsive: props.responsive,
    maintainAspectRatio: props.maintainAspectRatio,
    showLegend: props.showLegend,
    showTooltip: props.showTooltip,
  }

  switch (props.type) {
    case 'line':
      return {
        ...baseProps,
        tension: props.tension,
        pointRadius: props.pointRadius,
        borderWidth: props.borderWidth,
        fill: props.fill,
      }
    case 'bar':
      return {
        ...baseProps,
        horizontal: props.horizontal,
        borderRadius: props.borderRadius,
        borderWidth: props.borderWidth,
      }
    case 'area':
      return {
        ...baseProps,
        tension: props.tension,
        pointRadius: props.pointRadius,
        borderWidth: props.borderWidth,
        fillOpacity: props.fillOpacity,
      }
    case 'doughnut':
    case 'pie':
      return {
        ...baseProps,
        cutout: props.type === 'pie' ? 0 : props.cutout,
        borderWidth: props.borderWidth,
      }
    default:
      return baseProps
  }
})

const hasData = computed(() => {
  return props.data && props.data.datasets && props.data.datasets.length > 0
})
</script>

<template>
  <VCard class="dashboard-chart-card">
    <VCardText>
      <!-- Loading State -->
      <div
        v-if="loading"
        class="d-flex align-center justify-center"
        :style="{ height: `${height}px` }"
      >
        <VProgressCircular
          indeterminate
          color="primary"
          size="48"
        />
      </div>

      <!-- Error State -->
      <div
        v-else-if="error"
        class="d-flex flex-column align-center justify-center text-center"
        :style="{ height: `${height}px` }"
      >
        <VIcon
          icon="tabler-alert-circle"
          size="48"
          color="error"
          class="mb-4"
        />
        <h6 class="text-h6 mb-2">
          Error Loading Chart
        </h6>
        <p class="text-body-2 text-medium-emphasis">
          {{ error }}
        </p>
      </div>

      <!-- No Data State -->
      <div
        v-else-if="!hasData"
        class="d-flex flex-column align-center justify-center text-center"
        :style="{ height: `${height}px` }"
      >
        <VIcon
          icon="tabler-chart-line"
          size="48"
          color="disabled"
          class="mb-4"
        />
        <h6 class="text-h6 mb-2">
          No Data Available
        </h6>
        <p class="text-body-2 text-medium-emphasis">
          There's no data to display for this chart.
        </p>
      </div>

      <!-- Chart Component -->
      <Suspense v-else>
        <component
          :is="chartComponent"
          v-bind="chartProps"
        />
        
        <template #fallback>
          <div
            class="d-flex align-center justify-center"
            :style="{ height: `${height}px` }"
          >
            <VProgressCircular
              indeterminate
              color="primary"
              size="32"
            />
          </div>
        </template>
      </Suspense>
    </VCardText>
  </VCard>
</template>

<style scoped>
.dashboard-chart-card {
  height: 100%;
}

.dashboard-chart-card .v-card-text {
  height: 100%;
  padding: 1.5rem;
}
</style>
