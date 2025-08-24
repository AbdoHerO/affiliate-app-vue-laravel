<script setup lang="ts">
import { useTheme } from 'vuetify'
import { hexToRgb } from '@layouts/utils'
import { computed } from 'vue'

const vuetifyTheme = useTheme()

interface Props {
  data: {
    verified: number
    pending: number
    centerMetric: number
    centerLabel: string
  }
  loading?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  loading: false,
})

const series = computed(() => [props.data.verified, props.data.pending])

const chartOptions = computed(() => {
  const currentTheme = vuetifyTheme.current.value.colors
  const variableTheme = vuetifyTheme.current.value.variables

  return {
    chart: {
      type: 'donut',
      height: 200,
      parentHeightOffset: 0,
    },
    labels: ['Verified', 'Pending'],
    colors: [
      'rgba(var(--v-theme-success), 1)',
      'rgba(var(--v-theme-warning), 1)',
    ],
    stroke: {
      width: 0,
    },
    dataLabels: {
      enabled: false,
    },
    legend: {
      show: false,
    },
    tooltip: {
      y: {
        formatter: (val: number) => val.toLocaleString(),
      },
    },
    plotOptions: {
      pie: {
        donut: {
          size: '70%',
          labels: {
            show: true,
            name: {
              show: false,
            },
            value: {
              show: true,
              fontSize: '24px',
              fontWeight: 600,
              color: `rgba(${hexToRgb(currentTheme['on-background'])},${variableTheme['high-emphasis-opacity']})`,
              formatter: () => `${props.data.centerMetric}%`,
            },
            total: {
              show: true,
              label: props.data.centerLabel,
              fontSize: '12px',
              color: `rgba(${hexToRgb(currentTheme['on-surface'])},${variableTheme['disabled-opacity']})`,
              formatter: () => props.data.centerLabel,
            },
          },
        },
      },
    },
    responsive: [
      {
        breakpoint: 1280,
        options: {
          chart: {
            height: 180,
          },
        },
      },
      {
        breakpoint: 960,
        options: {
          chart: {
            height: 220,
          },
        },
      },
    ],
  }
})

const sessionStats = computed(() => [
  {
    icon: 'tabler-check-circle',
    color: 'success',
    title: 'Verified Signups',
    value: props.data.verified.toLocaleString(),
    percentage: Math.round((props.data.verified / (props.data.verified + props.data.pending)) * 100),
  },
  {
    icon: 'tabler-clock',
    color: 'warning',
    title: 'Pending Signups',
    value: props.data.pending.toLocaleString(),
    percentage: Math.round((props.data.pending / (props.data.verified + props.data.pending)) * 100),
  },
])
</script>

<template>
  <div class="advanced-chart-container">
    <VCard class="session-analytics-donut">
    <VCardItem class="pb-3">
      <VCardTitle>
        Session Analytics
      </VCardTitle>
      <VCardSubtitle>
        Signup Performance
      </VCardSubtitle>
    </VCardItem>

    <VCardText>
      <VueApexCharts
        v-if="!loading"
        :options="chartOptions"
        :series="series"
        type="donut"
        :height="200"
        class="mb-4"
      />
      
      <!-- Loading State -->
      <div
        v-else
        class="d-flex align-center justify-center mb-4 chart-loading"
        style="height: 200px;"
      >
        <VProgressCircular
          indeterminate
          color="primary"
          size="48"
        />
      </div>

      <!-- Stats List -->
      <VList class="card-list">
        <VListItem
          v-for="stat in sessionStats"
          :key="stat.title"
          class="px-0"
        >
          <template #prepend>
            <VAvatar
              size="38"
              :color="stat.color"
              variant="tonal"
              rounded
              class="me-3"
            >
              <VIcon
                :icon="stat.icon"
                size="22"
              />
            </VAvatar>
          </template>

          <VListItemTitle class="font-weight-medium">
            {{ stat.title }}
          </VListItemTitle>
          <VListItemSubtitle>
            {{ stat.percentage }}% of total
          </VListItemSubtitle>

          <template #append>
            <div class="text-end">
              <h6 class="text-h6 font-weight-medium">
                {{ stat.value }}
              </h6>
            </div>
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
