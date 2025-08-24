<script setup lang="ts">
import { computed } from 'vue'
import { Line } from 'vue-chartjs'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend,
  Filler,
} from 'chart.js'
import type { ChartData, ChartOptions } from 'chart.js'
import type { TimeSeriesData } from '@/types/dashboard'

// Register Chart.js components
ChartJS.register(
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend,
  Filler
)

interface Props {
  data: TimeSeriesData
  title?: string
  height?: number
  responsive?: boolean
  maintainAspectRatio?: boolean
  showLegend?: boolean
  showTooltip?: boolean
  tension?: number
  pointRadius?: number
  borderWidth?: number
  fillOpacity?: number
}

const props = withDefaults(defineProps<Props>(), {
  title: '',
  height: 400,
  responsive: true,
  maintainAspectRatio: false,
  showLegend: true,
  showTooltip: true,
  tension: 0.4,
  pointRadius: 0,
  borderWidth: 3,
  fillOpacity: 0.1,
})

const chartData = computed<ChartData<'line'>>(() => ({
  labels: props.data.labels,
  datasets: props.data.datasets.map((dataset, index) => {
    // Create gradient fill
    const canvas = document.createElement('canvas')
    const ctx = canvas.getContext('2d')
    const gradient = ctx?.createLinearGradient(0, 0, 0, 400)
    
    if (gradient && dataset.borderColor) {
      const color = dataset.borderColor as string
      gradient.addColorStop(0, color.replace('rgb', 'rgba').replace(')', `, ${props.fillOpacity})`))
      gradient.addColorStop(1, color.replace('rgb', 'rgba').replace(')', ', 0)'))
    }

    return {
      ...dataset,
      fill: true,
      backgroundColor: gradient || dataset.backgroundColor,
      tension: props.tension,
      pointRadius: props.pointRadius,
      pointHoverRadius: 6,
      borderWidth: props.borderWidth,
      pointBackgroundColor: dataset.borderColor,
      pointBorderColor: '#fff',
      pointBorderWidth: 2,
    }
  }),
}))

const chartOptions = computed<ChartOptions<'line'>>(() => ({
  responsive: props.responsive,
  maintainAspectRatio: props.maintainAspectRatio,
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
    filler: {
      propagate: false,
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
    axis: 'x',
    intersect: false,
  },
  elements: {
    point: {
      hoverRadius: 8,
    },
    line: {
      tension: props.tension,
    },
  },
  animation: {
    duration: 1000,
    easing: 'easeInOutQuart',
  },
}))
</script>

<template>
  <div class="dashboard-area-chart">
    <Line
      :data="chartData"
      :options="chartOptions"
      :height="height"
    />
  </div>
</template>

<style scoped>
.dashboard-area-chart {
  position: relative;
  width: 100%;
}

.dashboard-area-chart canvas {
  max-height: 100%;
}
</style>
