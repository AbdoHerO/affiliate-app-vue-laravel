<script setup lang="ts">
import { useTheme } from 'vuetify'
import { computed } from 'vue'
import { useSafeApexChart } from '@/composables/useSafeApexChart'

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
})

const currentTheme = vuetifyTheme.current.value.colors

// Use safe chart composable
const { shouldRender, containerRef, containerStyle } = useSafeApexChart(
  computed(() => props.loading),
  computed(() => props.data),
  { minWidth: 200, minHeight: 68 }
)

const series = computed(() => [
  {
    name: 'Sales',
    data: Array.isArray(props.data?.chartData) && props.data.chartData.length > 0
      ? props.data.chartData.filter(v => typeof v === 'number' && isFinite(v))
      : [0],
  },
])

const chartOptions = computed(() => ({
  chart: {
    type: 'area',
    parentHeightOffset: 0,
    toolbar: {
      show: false,
    },
    sparkline: {
      enabled: true,
    },
  },
  markers: {
    colors: 'transparent',
    strokeColors: 'transparent',
  },
  grid: {
    show: false,
  },
  colors: [currentTheme[props.data.color || 'success']],
  fill: {
    type: 'gradient',
    gradient: {
      shadeIntensity: 0.9,
      opacityFrom: 0.5,
      opacityTo: 0.07,
      stops: [0, 80, 100],
    },
  },
  dataLabels: {
    enabled: false,
  },
  stroke: {
    width: 2,
    curve: 'smooth',
  },
  xaxis: {
    show: true,
    lines: {
      show: false,
    },
    labels: {
      show: false,
    },
    stroke: {
      width: 0,
    },
    axisBorder: {
      show: false,
    },
  },
  yaxis: {
    stroke: {
      width: 0,
    },
    show: false,
  },
  tooltip: {
    enabled: true,
    y: {
      formatter: (val: number) => val.toLocaleString(),
    },
  },
  responsive: [
    { breakpoint: 1200, options: { chart: { height: 68 } } },
    { breakpoint: 768, options: { chart: { height: 60 } } },
    { breakpoint: 480, options: { chart: { height: 56 } } },
  ],
}))
</script>

<template>
  <div
    ref="containerRef"
    class="advanced-chart-container"
    :style="containerStyle"
  >
    <VCard class="sales-area-chart">
      <VCardItem class="pb-3">
        <VCardTitle>
          {{ data.title }}
        </VCardTitle>
        <VCardSubtitle>
          {{ data.subtitle }}
        </VCardSubtitle>
      </VCardItem>

      <VueApexCharts
        v-if="shouldRender"
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
          :color="data.color || 'success'"
          size="32"
        />
      </div>

      <VCardText class="pt-1">
        <div class="d-flex align-center justify-space-between gap-x-2">
          <h4 class="text-h4 text-center">
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
.sales-area-chart {
  transition: all 0.3s ease;
  
  &:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
  }

  .apexcharts-canvas {
    transition: all 0.3s ease;
    
    &:hover {
      transform: scale(1.02);
    }
  }
}
</style>
