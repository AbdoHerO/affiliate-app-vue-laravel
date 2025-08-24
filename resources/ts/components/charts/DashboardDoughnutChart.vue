<script setup lang="ts">
import { computed } from 'vue'
import { Doughnut } from 'vue-chartjs'
import {
  Chart as ChartJS,
  ArcElement,
  Tooltip,
  Legend,
} from 'chart.js'
import type { ChartData, ChartOptions } from 'chart.js'

// Register Chart.js components
ChartJS.register(ArcElement, Tooltip, Legend)

interface Props {
  data: {
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
  responsive?: boolean
  maintainAspectRatio?: boolean
  showLegend?: boolean
  showTooltip?: boolean
  cutout?: string | number
  borderWidth?: number
}

const props = withDefaults(defineProps<Props>(), {
  title: '',
  height: 400,
  responsive: true,
  maintainAspectRatio: false,
  showLegend: true,
  showTooltip: true,
  cutout: '60%',
  borderWidth: 2,
})

const chartData = computed<ChartData<'doughnut'>>(() => ({
  labels: props.data.labels,
  datasets: props.data.datasets.map(dataset => ({
    ...dataset,
    borderWidth: props.borderWidth,
    borderColor: '#fff',
    hoverBorderWidth: 3,
    hoverOffset: 4,
  })),
}))

const chartOptions = computed<ChartOptions<'doughnut'>>(() => ({
  responsive: props.responsive,
  maintainAspectRatio: props.maintainAspectRatio,
  cutout: props.cutout,
  plugins: {
    title: {
      display: !!props.title,
      text: props.title,
      font: {
        size: 16,
        weight: 'bold',
      },
      padding: {
        bottom: 20,
      },
    },
    legend: {
      display: props.showLegend,
      position: 'bottom',
      labels: {
        usePointStyle: true,
        padding: 20,
        font: {
          size: 12,
        },
        generateLabels: (chart) => {
          const data = chart.data
          if (data.labels && data.datasets.length) {
            return data.labels.map((label, i) => {
              const dataset = data.datasets[0]
              const value = dataset.data[i] as number
              const backgroundColor = Array.isArray(dataset.backgroundColor) 
                ? dataset.backgroundColor[i] 
                : dataset.backgroundColor
              
              return {
                text: `${label}: ${value.toLocaleString()}`,
                fillStyle: backgroundColor,
                strokeStyle: backgroundColor,
                lineWidth: 0,
                pointStyle: 'circle',
                hidden: false,
                index: i,
              }
            })
          }
          return []
        },
      },
    },
    tooltip: {
      enabled: props.showTooltip,
      backgroundColor: 'rgba(0, 0, 0, 0.8)',
      titleColor: '#fff',
      bodyColor: '#fff',
      borderColor: 'rgba(255, 255, 255, 0.1)',
      borderWidth: 1,
      cornerRadius: 8,
      padding: 12,
      displayColors: true,
      callbacks: {
        label: (context) => {
          const label = context.label || ''
          const value = context.parsed
          const total = context.dataset.data.reduce((a: number, b: number) => a + b, 0)
          const percentage = ((value / total) * 100).toFixed(1)
          return `${label}: ${value.toLocaleString()} (${percentage}%)`
        },
      },
    },
  },
  animation: {
    animateRotate: true,
    animateScale: true,
    duration: 1000,
    easing: 'easeInOutQuart',
  },
  elements: {
    arc: {
      borderWidth: props.borderWidth,
    },
  },
}))

// Calculate total for center display
const total = computed(() => {
  if (props.data.datasets.length > 0) {
    return props.data.datasets[0].data.reduce((a, b) => a + b, 0)
  }
  return 0
})
</script>

<template>
  <div class="dashboard-doughnut-chart">
    <div class="chart-container">
      <Doughnut
        :data="chartData"
        :options="chartOptions"
        :height="height"
      />
      
      <!-- Center text overlay -->
      <div 
        v-if="cutout && total > 0"
        class="chart-center-text"
      >
        <div class="total-value">
          {{ total.toLocaleString() }}
        </div>
        <div class="total-label">
          Total
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.dashboard-doughnut-chart {
  position: relative;
  width: 100%;
}

.chart-container {
  position: relative;
  width: 100%;
}

.dashboard-doughnut-chart canvas {
  max-height: 100%;
}

.chart-center-text {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  text-align: center;
  pointer-events: none;
}

.total-value {
  font-size: 1.5rem;
  font-weight: 600;
  color: rgb(var(--v-theme-on-surface));
  line-height: 1.2;
}

.total-label {
  font-size: 0.875rem;
  color: rgba(var(--v-theme-on-surface), 0.6);
  margin-top: 0.25rem;
}
</style>
