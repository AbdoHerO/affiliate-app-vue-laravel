<script setup lang="ts">
import { useTheme } from 'vuetify'
import { computed } from 'vue'
import type { TimeSeriesData } from '@/types/dashboard'
import { useSafeApexChart } from '@/composables/useSafeApexChart'
import { useI18n } from 'vue-i18n'

const vuetifyTheme = useTheme()
const { t } = useI18n()

interface Props {
  data: {
    revenue: number[]
    commissions: number[]
    labels: string[]
    totalRevenue: number
    totalCommissions: number
    growth: number
  }
  loading?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  loading: false,
})

// Use safe chart composable
const { shouldRender, containerRef, containerStyle } = useSafeApexChart(
  computed(() => props.loading),
  computed(() => props.data),
  { minWidth: 200, minHeight: 400 }
)

const series = computed(() => [
  {
    name: t('charts.series.revenue'),
    data: Array.isArray(props.data?.revenue) ? props.data.revenue.filter(v => typeof v === 'number' && isFinite(v)) : [0],
  },
  {
    name: t('charts.series.commissions'),
    data: Array.isArray(props.data?.commissions)
      ? props.data.commissions.filter(v => typeof v === 'number' && isFinite(v)).map(val => -val)
      : [0], // Negative for stacked effect
  },
])

const chartOptions = computed(() => {
  const currentTheme = vuetifyTheme.current.value.colors

  return {
    chart: {
      parentHeightOffset: 0,
      stacked: true,
      type: 'bar',
      toolbar: { show: false },
    },
    tooltip: {
      enabled: true,
      y: {
        formatter: (val: number) => `$${Math.abs(val).toLocaleString()}`,
      },
    },
    legend: {
      show: false,
    },
    stroke: {
      curve: 'smooth',
      width: 6,
      lineCap: 'round',
      colors: [currentTheme.surface],
    },
    plotOptions: {
      bar: {
        horizontal: false,
        columnWidth: '45%',
        borderRadius: 8,
        borderRadiusApplication: 'around',
        borderRadiusWhenStacked: 'all',
      },
    },
    colors: ['rgba(var(--v-theme-primary),1)', 'rgba(var(--v-theme-secondary),1)'],
    dataLabels: {
      enabled: false,
    },
    grid: {
      show: false,
      padding: {
        top: -40,
        bottom: -20,
        left: -10,
        right: -2,
      },
    },
    xaxis: {
      categories: props.data.labels,
      labels: {
        show: true,
        style: {
          fontSize: '11px',
          colors: 'rgba(var(--v-theme-on-surface), 0.6)',
        },
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
    responsive: [
      {
        breakpoint: 1600,
        options: {
          plotOptions: {
            bar: {
              columnWidth: '50%',
              borderRadius: 8,
            },
          },
        },
      },
      {
        breakpoint: 1279,
        options: {
          plotOptions: {
            bar: {
              columnWidth: '35%',
              borderRadius: 8,
            },
          },
        },
      },
    ],
    states: {
      hover: {
        filter: {
          type: 'lighten',
          value: 0.1,
        },
      },
      active: {
        filter: {
          type: 'darken',
          value: 0.1,
        },
      },
    },
  }
})

const totalEarnings = computed(() => [
  {
    avatar: 'tabler-currency-dollar',
    avatarColor: 'primary',
    title: 'Total Revenue',
    subtitle: 'Platform Revenue',
    earning: `+$${props.data.totalRevenue.toLocaleString()}`,
  },
  {
    avatar: 'tabler-chart-line',
    avatarColor: 'secondary',
    title: 'Total Commissions',
    subtitle: 'Affiliate Earnings',
    earning: `+$${props.data.totalCommissions.toLocaleString()}`,
  },
])

const moreList = [
  { title: 'View More', value: 'View More' },
  { title: 'Export Data', value: 'Export Data' },
]
</script>

<template>
  <div
    ref="containerRef"
    class="advanced-chart-container"
    :style="containerStyle"
  >
    <VCard class="total-earning-chart">
    <VCardItem class="pb-0">
      <VCardTitle>Total Earning</VCardTitle>

      <div class="d-flex align-center mt-2">
        <h2 class="text-h2 me-2">
          ${{ (data.totalRevenue + data.totalCommissions).toLocaleString() }}
        </h2>
        <div :class="data.growth >= 0 ? 'text-success' : 'text-error'">
          <VIcon
            size="20"
            :icon="data.growth >= 0 ? 'tabler-chevron-up' : 'tabler-chevron-down'"
          />
          <span class="text-base">{{ Math.abs(data.growth) }}%</span>
        </div>
      </div>

      <template #append>
        <div class="mt-n10 me-n2">
          <MoreBtn
            size="small"
            :menu-list="moreList"
          />
        </div>
      </template>
    </VCardItem>

    <VCardText>
      <VueApexCharts
        v-if="shouldRender"
        :options="chartOptions"
        :series="series"
        height="191"
        class="my-2"
      />

      <!-- Loading State -->
      <div
        v-else
        class="d-flex align-center justify-center my-2 chart-loading"
        style="height: 191px;"
      >
        <VProgressCircular
          indeterminate
          color="primary"
          size="48"
        />
      </div>

      <VList class="card-list">
        <VListItem
          v-for="earning in totalEarnings"
          :key="earning.title"
        >
          <VListItemTitle class="font-weight-medium">
            {{ earning.title }}
          </VListItemTitle>
          <VListItemSubtitle>
            {{ earning.subtitle }}
          </VListItemSubtitle>
          <template #prepend>
            <VAvatar
              size="38"
              :color="earning.avatarColor"
              variant="tonal"
              rounded
              class="me-1"
            >
              <VIcon
                :icon="earning.avatar"
                size="22"
              />
            </VAvatar>
          </template>

          <template #append>
            <span class="text-success font-weight-medium">{{ earning.earning }}</span>
          </template>
        </VListItem>
      </VList>
    </VCardText>
    </VCard>
  </div>
</template>

<style lang="scss" scoped>
.card-list {
  --v-card-list-gap: 16px;
}
</style>
