<script setup lang="ts">
import { useTheme } from 'vuetify'
import { hexToRgb } from '@layouts/utils'
import { computed } from 'vue'

const vuetifyTheme = useTheme()

interface Props {
  data: {
    title: string
    subtitle: string
    value: string
    growth: string
    chartData: number[]
    color?: string
  }
  loading?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  loading: false,
  data: {
    color: 'info'
  }
})

const isValidData = computed(() => !props.loading && Array.isArray(props.data?.chartData) && props.data.chartData.length > 0)
const safeChartData = computed<number[]>(() => Array.isArray(props.data?.chartData) ? props.data.chartData.filter(v => typeof v === 'number' && isFinite(v)) : [])
const series = computed(() => [
  {
    data: safeChartData.value.length ? safeChartData.value : [0],
  },
])

const chartOptions = computed(() => {
  const currentTheme = vuetifyTheme.current.value.colors
  const variableTheme = vuetifyTheme.current.value.variables

  return {
    chart: {
      height: 90,
      type: 'line',
      parentHeightOffset: 0,
      toolbar: {
        show: false,
      },
    },
    grid: {
      borderColor: `rgba(${hexToRgb(String(variableTheme['border-color']))},${variableTheme['border-opacity']})`,
      strokeDashArray: 6,
      xaxis: {
        lines: {
          show: true,
        },
      },
      yaxis: {
        lines: {
          show: false,
        },
      },
      padding: {
        top: -18,
        left: -4,
        right: 7,
        bottom: -10,
      },
    },
    colors: [currentTheme[props.data.color || 'info']],
    stroke: {
      width: 2,
    },
    tooltip: {
      enabled: true,
      shared: false,
      intersect: true,
      x: {
        show: false,
      },
      y: {
        formatter: (val: number) => `$${val.toLocaleString()}`,
      },
    },
    xaxis: {
      labels: {
        show: false,
      },
      axisTicks: {
        show: false,
      },
      axisBorder: {
        show: false,
      },
    },
    yaxis: {
      labels: {
        show: false,
      },
    },
    markers: {
      size: 3.5,
      fillColor: currentTheme[props.data.color || 'info'],
      strokeColors: 'transparent',
      strokeWidth: 3.2,
      discrete: [
        {
          seriesIndex: 0,
          dataPointIndex: props.data.chartData.length - 1,
          fillColor: currentTheme.surface,
          strokeColor: currentTheme[props.data.color || 'info'],
          size: 5,
          shape: 'circle',
        },
      ],
      hover: {
        size: 5.5,
      },
    },
    responsive: [
      {
        breakpoint: 960,
        options: {
          chart: {
            height: 110,
          },
        },
      },
    ],
  }
})
</script>

<template>
  <div class="advanced-chart-container">
    <VCard class="profit-line-chart">
      <VCardItem class="pb-3">
        <VCardTitle>
          {{ data.title }}
        </VCardTitle>
        <VCardSubtitle>
          {{ data.subtitle }}
        </VCardSubtitle>
      </VCardItem>
      
      <VCardText>
        <VueApexCharts
          v-if="isValidData"
          type="line"
          :options="chartOptions"
          :series="series"
          :height="68"
        />

        <!-- Loading State -->
        <div
          v-else
          class="d-flex align-center justify-center chart-loading"
          style="height: 68px;"
        >
          <VProgressCircular
            indeterminate
            :color="data.color || 'info'"
            size="32"
          />
        </div>

        <div class="d-flex align-center justify-space-between gap-x-2 mt-3">
          <h4 class="text-h4 text-center font-weight-medium">
            {{ data.value }}
          </h4>
          <span 
            class="text-sm"
            :class="data.growth.startsWith('+') ? 'text-success' : 'text-error'"
          >
            {{ data.growth }}
          </span>
        </div>
      </VCardText>
    </VCard>
  </div>
</template>

<style lang="scss" scoped>
.profit-line-chart {
  transition: all 0.3s ease;
  
  &:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
  }

  .apexcharts-line {
    transition: all 0.3s ease;
    
    &:hover {
      stroke-width: 3;
    }
  }

  .apexcharts-marker {
    transition: all 0.3s ease;
    
    &:hover {
      transform: scale(1.2);
    }
  }
}
</style>
