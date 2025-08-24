<script setup lang="ts">
import { computed } from 'vue'
import { Bar } from 'vue-chartjs'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  BarElement,
  Title,
  Tooltip,
  Legend,
} from 'chart.js'
import type { ChartData, ChartOptions } from 'chart.js'
import type { TimeSeriesData } from '@/types/dashboard'

// Register Chart.js components
ChartJS.register(
  CategoryScale,
  LinearScale,
  BarElement,
  Title,
  Tooltip,
  Legend
)

interface Props {
  data: TimeSeriesData
  title?: string
  height?: number
  responsive?: boolean
  maintainAspectRatio?: boolean
  showLegend?: boolean
  showTooltip?: boolean
  horizontal?: boolean
  borderRadius?: number
  borderWidth?: number
}

const props = withDefaults(defineProps<Props>(), {
  title: '',
  height: 400,
  responsive: true,
  maintainAspectRatio: false,
  showLegend: true,
  showTooltip: true,
  horizontal: false,
  borderRadius: 4,
  borderWidth: 0,
})

const chartData = computed<ChartData<'bar'>>(() => ({
  labels: props.data.labels,
  datasets: props.data.datasets.map(dataset => ({
    ...dataset,
    borderRadius: props.borderRadius,
    borderWidth: props.borderWidth,
    borderSkipped: false,
  })),
}))

const chartOptions = computed<ChartOptions<'bar'>>(() => ({
  responsive: props.responsive,
  maintainAspectRatio: props.maintainAspectRatio,
  indexAxis: props.horizontal ? 'y' : 'x',
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
      position: 'top',
      align: 'end',
      labels: {
        usePointStyle: true,
        padding: 20,
        font: {
          size: 12,
        },
      },
    },
    tooltip: {
      enabled: props.showTooltip,
      mode: 'index',
      intersect: false,
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
          const label = context.dataset.label || ''
          const value = context.parsed.y
          return `${label}: ${typeof value === 'number' ? value.toLocaleString() : value}`
        },
      },
    },
  },
  scales: {
    x: {
      display: true,
      grid: {
        display: false,
      },
      ticks: {
        font: {
          size: 11,
        },
        color: 'rgba(var(--v-theme-on-surface), 0.6)',
      },
    },
    y: {
      display: true,
      grid: {
        color: 'rgba(var(--v-theme-on-surface), 0.1)',
        borderDash: [5, 5],
      },
      ticks: {
        font: {
          size: 11,
        },
        color: 'rgba(var(--v-theme-on-surface), 0.6)',
        callback: (value) => {
          if (typeof value === 'number') {
            return value.toLocaleString()
          }
          return value
        },
      },
    },
  },
  interaction: {
    mode: 'nearest',
    axis: props.horizontal ? 'y' : 'x',
    intersect: false,
  },
  animation: {
    duration: 1000,
    easing: 'easeInOutQuart',
  },
}))
</script>

<template>
  <div class="dashboard-bar-chart">
    <Bar
      :data="chartData"
      :options="chartOptions"
      :height="height"
    />
  </div>
</template>

<style scoped>
.dashboard-bar-chart {
  position: relative;
  width: 100%;
}

.dashboard-bar-chart canvas {
  max-height: 100%;
}
</style>
