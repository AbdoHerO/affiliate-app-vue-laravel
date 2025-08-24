<script setup lang="ts">
import { useTheme } from 'vuetify'
import { hexToRgb } from '@layouts/utils'
import { computed } from 'vue'

const vuetifyTheme = useTheme()

interface Props {
  data: {
    totalAmount: string
    growth: string
    weeklyData: number[]
    reports: Array<{
      color: string
      icon: string
      title: string
      amount: string
      progress: number
    }>
  }
  loading?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  loading: false,
})

const isValidData = computed(() => !props.loading && Array.isArray(props.data?.weeklyData) && props.data.weeklyData.length > 0)
const safeWeekly = computed<number[]>(() => Array.isArray(props.data?.weeklyData) ? props.data.weeklyData.filter(v => typeof v === 'number' && isFinite(v)) : [])
const series = computed(() => [
  {
    data: safeWeekly.value.length ? safeWeekly.value : [0,0,0,0,0,0,0],
  },
])

const chartOptions = computed(() => {
  const currentTheme = vuetifyTheme.current.value.colors
  const variableTheme = vuetifyTheme.current.value.variables

  return {
    chart: {
      parentHeightOffset: 0,
      type: 'bar',
      toolbar: {
        show: false,
      },
    },
    plotOptions: {
      bar: {
        barHeight: '60%',
        columnWidth: '38%',
        startingShape: 'rounded',
        endingShape: 'rounded',
        borderRadius: 4,
        distributed: true,
      },
    },
    grid: {
      show: false,
      padding: {
        top: -30,
        bottom: 0,
        left: -10,
        right: -10,
      },
    },
    colors: [
      `rgba(${hexToRgb(currentTheme.primary)},${variableTheme['dragged-opacity']})`,
      `rgba(${hexToRgb(currentTheme.primary)},${variableTheme['dragged-opacity']})`,
      `rgba(${hexToRgb(currentTheme.primary)},${variableTheme['dragged-opacity']})`,
      `rgba(${hexToRgb(currentTheme.primary)},${variableTheme['dragged-opacity']})`,
      `rgba(${hexToRgb(currentTheme.primary)}, 1)`,
      `rgba(${hexToRgb(currentTheme.primary)},${variableTheme['dragged-opacity']})`,
      `rgba(${hexToRgb(currentTheme.primary)},${variableTheme['dragged-opacity']})`,
    ],
    dataLabels: {
      enabled: false,
    },
    legend: {
      show: false,
    },
    xaxis: {
      categories: ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'],
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
    yaxis: {
      labels: {
        show: false,
      },
    },
    tooltip: {
      enabled: true,
      y: {
        formatter: (val: number) => `$${val}`,
      },
    },
    responsive: [
      {
        breakpoint: 1025,
        options: {
          chart: {
            height: 199,
          },
        },
      },
    ],
  }
})

const moreList = [
  { title: 'View More', value: 'View More' },
  { title: 'Export Data', value: 'Export Data' },
]
</script>

<template>
  <div class="advanced-chart-container">
    <VCard class="earning-reports-weekly">
      <VCardItem class="pb-sm-0">
        <VCardTitle>Earning Reports</VCardTitle>
        <VCardSubtitle>Weekly Earnings Overview</VCardSubtitle>

        <template #append>
          <div class="mt-n4 me-n2">
            <MoreBtn
              size="small"
              :menu-list="moreList"
            />
          </div>
        </template>
      </VCardItem>

  <VCardText v-if="!loading && isValidData">
        <VRow>
          <VCol
            cols="12"
            sm="5"
            lg="6"
            class="d-flex flex-column align-self-center"
          >
            <div class="d-flex align-center gap-2 mb-3 flex-wrap">
              <h2 class="text-h2">
                {{ data.totalAmount }}
              </h2>
              <VChip
                label
                size="small"
                :color="data.growth.startsWith('+') ? 'success' : 'error'"
              >
                {{ data.growth }}
              </VChip>
            </div>

            <span class="text-sm text-medium-emphasis">
              You informed of this week compared to last week
            </span>
          </VCol>

          <VCol
            cols="12"
            sm="7"
            lg="6"
          >
            <VueApexCharts
              :options="chartOptions"
              :series="series"
              height="161"
            />
          </VCol>
        </VRow>

        <div class="border rounded mt-5 pa-5">
          <VRow>
            <VCol
              v-for="report in data.reports"
              :key="report.title"
              cols="12"
              sm="4"
            >
              <div class="d-flex align-center">
                <VAvatar
                  rounded
                  size="26"
                  :color="report.color"
                  variant="tonal"
                  class="me-2"
                >
                  <VIcon
                    size="18"
                    :icon="report.icon"
                  />
                </VAvatar>

                <h6 class="text-base font-weight-regular">
                  {{ report.title }}
                </h6>
              </div>
              <h6 class="text-h4 my-2">
                {{ report.amount }}
              </h6>
              <VProgressLinear
                :model-value="report.progress"
                :color="report.color"
                height="4"
                rounded
                rounded-bar
              />
            </VCol>
          </VRow>
        </div>
      </VCardText>

      <!-- Loading State -->
      <VCardText
        v-else
        class="d-flex align-center justify-center chart-loading"
        style="height: 300px;"
      >
        <VProgressCircular
          indeterminate
          color="primary"
          size="48"
        />
      </VCardText>
    </VCard>
  </div>
</template>

<style lang="scss" scoped>
.earning-reports-weekly {
  .v-progress-linear {
    transition: all 0.3s ease;
    
    &:hover {
      transform: scaleY(1.5);
    }
  }

  .v-chip {
    transition: all 0.3s ease;
    
    &:hover {
      transform: scale(1.05);
    }
  }
}
</style>
