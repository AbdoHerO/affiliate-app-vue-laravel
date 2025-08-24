<script setup lang="ts">
import { useTheme } from 'vuetify'
import { hexToRgb } from '@layouts/utils'
import { computed } from 'vue'

const vuetifyTheme = useTheme()

interface Props {
  data: {
    title: string
    subtitle: string
    lineData: number[]
    barData: number[]
    labels: string[]
    colors?: {
      line: string
      bar: string
    }
  }
  loading?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  loading: false,
})

const isValidData = computed(() => {
  return !props.loading && Array.isArray(props.data?.barData) && Array.isArray(props.data?.lineData)
})
const safeBar = computed<number[]>(() => Array.isArray(props.data?.barData) ? props.data.barData.filter(v => typeof v === 'number' && isFinite(v)) : [])
const safeLine = computed<number[]>(() => Array.isArray(props.data?.lineData) ? props.data.lineData.filter(v => typeof v === 'number' && isFinite(v)) : [])
const series = computed(() => [
  {
    name: 'Revenue',
    type: 'column',
    data: safeBar.value.length ? safeBar.value : [0],
  },
  {
    name: 'Growth',
    type: 'line',
    data: safeLine.value.length ? safeLine.value : [0],
  },
])

const chartOptions = computed(() => {
  const currentTheme = vuetifyTheme.current.value.colors
  const variableTheme = vuetifyTheme.current.value.variables

  return {
    chart: {
      height: 300,
      type: 'line',
      parentHeightOffset: 0,
      toolbar: {
        show: false,
      },
    },
    stroke: {
      width: [0, 4],
      curve: 'smooth',
    },
    plotOptions: {
      bar: {
        columnWidth: '50%',
        borderRadius: 4,
      },
    },
    colors: [
      props.data.colors?.bar || currentTheme.primary,
      props.data.colors?.line || currentTheme.success,
    ],
    dataLabels: {
      enabled: true,
      enabledOnSeries: [1],
      style: {
        fontSize: '12px',
        fontWeight: 600,
        colors: [currentTheme['on-surface']],
      },
      background: {
        enabled: true,
        foreColor: currentTheme.surface,
        borderRadius: 4,
        padding: 4,
        opacity: 0.9,
      },
    },
    legend: {
      show: true,
      position: 'top',
      horizontalAlign: 'left',
      fontSize: '13px',
      fontFamily: 'Public Sans',
      labels: {
        colors: currentTheme['on-surface'],
      },
      markers: {
        width: 10,
        height: 10,
        radius: 12,
      },
    },
    grid: {
      borderColor: `rgba(${hexToRgb(String(variableTheme['border-color']))},${variableTheme['border-opacity']})`,
      strokeDashArray: 6,
      padding: {
        top: 0,
        bottom: -8,
        left: 20,
        right: 20,
      },
    },
    xaxis: {
      categories: props.data.labels,
      axisBorder: {
        show: false,
      },
      axisTicks: {
        show: false,
      },
      labels: {
        style: {
          colors: `rgba(${hexToRgb(currentTheme['on-surface'])},${variableTheme['disabled-opacity']})`,
          fontSize: '13px',
          fontFamily: 'Public Sans',
        },
      },
    },
    yaxis: [
      {
        title: {
          text: 'Revenue',
          style: {
            color: `rgba(${hexToRgb(currentTheme['on-surface'])},${variableTheme['high-emphasis-opacity']})`,
            fontSize: '13px',
            fontFamily: 'Public Sans',
          },
        },
        labels: {
          style: {
            colors: `rgba(${hexToRgb(currentTheme['on-surface'])},${variableTheme['disabled-opacity']})`,
            fontSize: '13px',
            fontFamily: 'Public Sans',
          },
          formatter: (val: number) => `$${val}k`,
        },
      },
      {
        opposite: true,
        title: {
          text: 'Growth %',
          style: {
            color: `rgba(${hexToRgb(currentTheme['on-surface'])},${variableTheme['high-emphasis-opacity']})`,
            fontSize: '13px',
            fontFamily: 'Public Sans',
          },
        },
        labels: {
          style: {
            colors: `rgba(${hexToRgb(currentTheme['on-surface'])},${variableTheme['disabled-opacity']})`,
            fontSize: '13px',
            fontFamily: 'Public Sans',
          },
          formatter: (val: number) => `${val}%`,
        },
      },
    ],
    tooltip: {
      shared: true,
      intersect: false,
      y: [
        {
          formatter: (val: number) => `$${val}k`,
        },
        {
          formatter: (val: number) => `${val}%`,
        },
      ],
    },
    responsive: [
      {
        breakpoint: 768,
        options: {
          chart: {
            height: 250,
          },
          legend: {
            position: 'bottom',
          },
        },
      },
    ],
  }
})
</script>

<template>
  <div class="advanced-chart-container">
    <VCard class="mixed-chart">
      <VCardItem class="pb-3">
        <VCardTitle>{{ data.title }}</VCardTitle>
        <VCardSubtitle>{{ data.subtitle }}</VCardSubtitle>
      </VCardItem>

      <VCardText>
        <VueApexCharts
          v-if="isValidData"
          :options="chartOptions"
          :series="series"
          height="300"
        />

        <!-- Loading State -->
        <div
          v-else
          class="d-flex align-center justify-center chart-loading"
          style="height: 300px;"
        >
          <VProgressCircular
            indeterminate
            color="primary"
            size="48"
          />
        </div>
      </VCardText>
    </VCard>
  </div>
</template>

<style lang="scss" scoped>
.mixed-chart {
  .apexcharts-legend {
    transition: all 0.3s ease;
    
    &:hover {
      transform: translateY(-2px);
    }
  }

  .apexcharts-bar-area {
    transition: all 0.3s ease;
    
    &:hover {
      filter: brightness(1.1);
    }
  }

  .apexcharts-line {
    transition: all 0.3s ease;
    
    &:hover {
      stroke-width: 5;
    }
  }
}
</style>
