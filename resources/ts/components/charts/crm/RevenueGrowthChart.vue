<script setup lang="ts">
import { useTheme } from 'vuetify'
import { hexToRgb } from '@layouts/utils'
import { computed } from 'vue'

const vuetifyTheme = useTheme()

interface Props {
  data: {
    values: number[]
    labels: string[]
    total: number
    growth: number
    period: string
  }
  loading?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  loading: false,
})

const series = computed(() => [
  {
    data: props.data.values,
  },
])

const chartOptions = computed(() => {
  const currentTheme = vuetifyTheme.current.value.colors
  const variableTheme = vuetifyTheme.current.value.variables

  const labelSuccessColor = `rgba(${hexToRgb(currentTheme.success)},0.2)`
  const labelColor = `rgba(${hexToRgb(currentTheme['on-surface'])},${variableTheme['disabled-opacity']})`

  return {
    chart: {
      height: 162,
      type: 'bar',
      parentHeightOffset: 0,
      toolbar: {
        show: false,
      },
    },
    plotOptions: {
      bar: {
        barHeight: '80%',
        columnWidth: '30%',
        startingShape: 'rounded',
        endingShape: 'rounded',
        borderRadius: 6,
        distributed: true,
      },
    },
    tooltip: {
      enabled: true,
      y: {
        formatter: (val: number) => `$${val.toLocaleString()}`,
      },
    },
    grid: {
      show: false,
      padding: {
        top: -20,
        bottom: -12,
        left: -10,
        right: 0,
      },
    },
    colors: [
      labelSuccessColor,
      labelSuccessColor,
      labelSuccessColor,
      labelSuccessColor,
      currentTheme.success,
      labelSuccessColor,
      labelSuccessColor,
    ],
    dataLabels: {
      enabled: false,
    },
    legend: {
      show: false,
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
          colors: labelColor,
          fontSize: '13px',
          fontFamily: 'Public sans',
        },
      },
    },
    yaxis: {
      labels: {
        show: false,
      },
    },
    states: {
      hover: {
        filter: {
          type: 'lighten',
          value: 0.1,
        },
      },
    },
    responsive: [
      {
        breakpoint: 1640,
        options: {
          plotOptions: {
            bar: {
              columnWidth: '40%',
              borderRadius: 6,
            },
          },
        },
      },
      {
        breakpoint: 1279,
        options: {
          plotOptions: {
            bar: {
              columnWidth: '50%',
              borderRadius: 6,
            },
          },
        },
      },
      {
        breakpoint: 600,
        options: {
          plotOptions: {
            bar: {
              borderRadius: 8,
              columnWidth: '40%',
            },
          },
        },
      },
    ],
  }
})
</script>

<template>
  <div class="advanced-chart-container">
    <VCard class="revenue-growth-chart">
    <VCardText class="d-flex justify-space-between">
      <div class="d-flex flex-column">
        <div class="mb-auto">
          <h5 class="text-h5 text-no-wrap mb-2">
            Revenue Growth
          </h5>
          <div class="text-body-1">
            {{ data.period }} Report
          </div>
        </div>

        <div>
          <h5 class="text-h3 mb-2">
            ${{ data.total.toLocaleString() }}
          </h5>
          <VChip
            label
            :color="data.growth >= 0 ? 'success' : 'error'"
            size="small"
          >
            {{ data.growth >= 0 ? '+' : '' }}{{ data.growth }}%
          </VChip>
        </div>
      </div>
      <div>
        <VueApexCharts
          v-if="!loading"
          :options="chartOptions"
          :series="series"
          :height="162"
        />
        
        <!-- Loading State -->
        <div
          v-else
          class="d-flex align-center justify-center chart-loading"
          style="height: 162px; width: 120px;"
        >
          <VProgressCircular
            indeterminate
            color="primary"
            size="32"
          />
        </div>
      </div>
    </VCardText>
    </VCard>
  </div>
</template>
