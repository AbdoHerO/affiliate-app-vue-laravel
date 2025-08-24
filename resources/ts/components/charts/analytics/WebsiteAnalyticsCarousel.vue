<script setup lang="ts">
import { VIcon } from 'vuetify/components/VIcon'
import { computed } from 'vue'
import type { TimeSeriesData } from '@/types/dashboard'

interface Props {
  data: {
    totalSignups: number
    verifiedSignups: number
    growthRate: number
    chartData: TimeSeriesData
  }
  loading?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  loading: false,
})

const websiteAnalytics = computed(() => [
  {
    name: 'Signups',
    data: [
      {
        number: props.data.totalSignups.toLocaleString(),
        text: 'Total Signups',
      },
      {
        number: props.data.verifiedSignups.toLocaleString(),
        text: 'Verified',
      },
      {
        number: `${Math.round(props.data.growthRate)}%`,
        text: 'Growth Rate',
      },
      {
        number: `${Math.round((props.data.verifiedSignups / props.data.totalSignups) * 100)}%`,
        text: 'Conversion',
      },
    ],
  },
  {
    name: 'Performance',
    data: [
      {
        number: '2.4k',
        text: 'Clicks',
      },
      {
        number: '1.8k',
        text: 'Visits',
      },
      {
        number: '892',
        text: 'Leads',
      },
      {
        number: '15%',
        text: 'CTR',
      },
    ],
  },
  {
    name: 'Revenue Sources',
    data: [
      {
        number: '268',
        text: 'Direct',
      },
      {
        number: '890',
        text: 'Organic',
      },
      {
        number: '622',
        text: 'Referral',
      },
      {
        number: '1.2k',
        text: 'Campaign',
      },
    ],
  },
])
</script>

<template>
  <div class="advanced-chart-container">
    <VCard
      color="primary"
      height="260"
      class="web-analytics-carousel"
    >
    <VCarousel
      v-if="!loading"
      cycle
      :continuous="false"
      :show-arrows="false"
      hide-delimiter-background
      :delimiter-icon="() => h(VIcon, { icon: 'fa-circle', size: '8' })"
      height="260"
      class="carousel-delimiter-top-end web-analytics-carousel"
    >
      <VCarouselItem
        v-for="item in websiteAnalytics"
        :key="item.name"
      >
        <VCardText class="position-relative">
          <VRow>
            <VCol cols="12">
              <h5 class="text-h5 text-white">
                Affiliate Analytics
              </h5>
              <p class="text-sm mb-0">
                Total Performance Overview
              </p>
            </VCol>

            <VCol
              cols="12"
              sm="6"
              order="2"
              order-sm="1"
            >
              <VRow>
                <VCol
                  cols="12"
                  class="pb-0 pt-1"
                >
                  <h6 class="text-h6 text-white mb-1 mt-5">
                    {{ item.name }}
                  </h6>
                </VCol>

                <VCol
                  v-for="d in item.data"
                  :key="d.number"
                  cols="6"
                  class="text-no-wrap pb-2"
                >
                  <VChip
                    label
                    variant="flat"
                    size="default"
                    color="rgb(var(--v-theme-primary-darken-1))"
                    class="font-weight-medium text-white rounded me-2 px-2"
                    style="block-size: 30px;"
                  >
                    <span class="text-base">{{ d.number }}</span>
                  </VChip>
                  <span class="d-inline-block">{{ d.text }}</span>
                </VCol>
              </VRow>
            </VCol>

            <VCol
              cols="12"
              sm="6"
              order="1"
              order-sm="2"
              class="text-center"
            >
              <VIcon
                icon="tabler-chart-line"
                size="120"
                class="text-white opacity-50"
                style="filter: drop-shadow(0 4px 60px rgba(0, 0, 0, 50%));"
              />
            </VCol>
          </VRow>
        </VCardText>
      </VCarouselItem>
    </VCarousel>

    <!-- Loading State -->
    <VCardText
      v-else
      class="d-flex align-center justify-center chart-loading"
      style="height: 260px;"
    >
      <VProgressCircular
        indeterminate
        color="white"
        size="48"
      />
    </VCardText>
    </VCard>
  </div>
</template>

<style lang="scss">
.web-analytics-carousel {
  .v-carousel__controls {
    .v-carousel__controls__item {
      &.v-btn--active {
        .v-icon {
          opacity: 1 !important;
        }
      }
    }
  }
}
</style>
