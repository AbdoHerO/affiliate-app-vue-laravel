<script setup lang="ts">
import { useTheme } from 'vuetify'
import { hexToRgb } from '@layouts/utils'
import { computed } from 'vue'

const vuetifyTheme = useTheme()

interface Props {
  data: {
    percentage: number
    value: number
    label: string
    subtitle?: string
  }
  loading?: boolean
  color?: string
}

const props = withDefaults(defineProps<Props>(), {
  loading: false,
  color: 'warning',
})

const series = computed(() => [props.data.percentage])

const chartOptions = computed(() => {
  const currentTheme = vuetifyTheme.current.value.colors
  const variableTheme = vuetifyTheme.current.value.variables

  return {
    chart: {
      sparkline: {
        enabled: true,
      },
      parentHeightOffset: 0,
      type: 'radialBar',
    },
    colors: [`rgba(var(--v-theme-${props.color}), 1)`],
    plotOptions: {
      radialBar: {
        offsetY: 0,
        startAngle: -90,
        endAngle: 90,
        hollow: {
          size: '65%',
        },
        track: {
          strokeWidth: '45%',
          background: 'rgba(var(--v-track-bg))',
        },
        dataLabels: {
          name: {
            show: false,
          },
          value: {
            fontSize: '24px',
            color: `rgba(${hexToRgb(currentTheme['on-background'])},${variableTheme['high-emphasis-opacity']})`,
            fontWeight: 600,
            offsetY: -5,
            formatter: (val: number) => `${val}%`,
          },
        },
      },
    },
    grid: {
      show: false,
      padding: {
        bottom: 5,
      },
    },
    stroke: {
      lineCap: 'round',
    },
    labels: ['Progress'],
    responsive: [
      {
        breakpoint: 1442,
        options: {
          chart: {
            height: 140,
          },
          plotOptions: {
            radialBar: {
              dataLabels: {
                value: {
                  fontSize: '24px',
                },
              },
              hollow: {
                size: '60%',
              },
            },
          },
        },
      },
      {
        breakpoint: 1280,
        options: {
          chart: {
            height: 200,
          },
          plotOptions: {
            radialBar: {
              dataLabels: {
                value: {
                  fontSize: '18px',
                },
              },
              hollow: {
                size: '70%',
              },
            },
          },
        },
      },
      {
        breakpoint: 960,
        options: {
          chart: {
            height: 250,
          },
          plotOptions: {
            radialBar: {
              hollow: {
                size: '70%',
              },
              dataLabels: {
                value: {
                  fontSize: '24px',
                },
              },
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
    <VCard class="expenses-radial-chart">
    <VCardItem class="pb-3">
      <VCardTitle>
        {{ data.value.toLocaleString() }}
      </VCardTitle>
      <VCardSubtitle>
        {{ data.label }}
      </VCardSubtitle>
    </VCardItem>
    <VCardText>
      <VueApexCharts
        v-if="!loading"
        :options="chartOptions"
        :series="series"
        type="radialBar"
        :height="135"
      />
      
      <!-- Loading State -->
      <div
        v-else
        class="d-flex align-center justify-center chart-loading"
        style="height: 135px;"
      >
        <VProgressCircular
          indeterminate
          :color="color"
          size="48"
        />
      </div>

      <div class="text-sm text-center clamp-text text-disabled mt-3">
        {{ data.subtitle || `${data.percentage}% completion rate` }}
      </div>
    </VCardText>
    </VCard>
  </div>
</template>
