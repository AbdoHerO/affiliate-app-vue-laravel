<script setup lang="ts">
interface Props {
  data: {
    title: string
    amount: string
    growth: string
    orderPercentage: number
    orderCount: number
    visitPercentage: number
    visitCount: number
    progressValue: number
  }
  loading?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  loading: false,
})
</script>

<template>
  <div class="advanced-chart-container">
    <VCard class="sales-overview-card">
      <VCardText>
        <div class="d-flex align-center justify-space-between">
          <div class="text-body-1">
            {{ data.title }}
          </div>
          <div class="text-success font-weight-medium">
            {{ data.growth }}
          </div>
        </div>
        <h4 class="text-h4 mt-2">
          {{ data.amount }}
        </h4>
      </VCardText>

      <VCardText>
        <VRow no-gutters>
          <VCol cols="5">
            <div class="py-2">
              <div class="d-flex align-center mb-3">
                <VAvatar
                  color="info"
                  variant="tonal"
                  :size="24"
                  rounded
                  class="me-2"
                >
                  <VIcon
                    size="18"
                    icon="tabler-shopping-cart"
                  />
                </VAvatar>
                <span>Orders</span>
              </div>
              <h5 class="text-h5">
                {{ data.orderPercentage }}%
              </h5>
              <div class="text-body-2 text-disabled">
                {{ data.orderCount.toLocaleString() }}
              </div>
            </div>
          </VCol>

          <VCol cols="2">
            <div class="d-flex flex-column align-center justify-center h-100">
              <VDivider
                vertical
                class="mx-auto"
              />

              <VAvatar
                size="24"
                color="rgba(var(--v-theme-on-surface), var(--v-hover-opacity))"
                class="my-2"
              >
                <div class="text-overline text-disabled">
                  VS
                </div>
              </VAvatar>

              <VDivider
                vertical
                class="mx-auto"
              />
            </div>
          </VCol>

          <VCol
            cols="5"
            class="text-end"
          >
            <div class="py-2">
              <div class="d-flex align-center justify-end mb-3">
                <span class="me-2">Visits</span>
                <VAvatar
                  color="primary"
                  variant="tonal"
                  :size="24"
                  rounded
                >
                  <VIcon
                    size="18"
                    icon="tabler-link"
                  />
                </VAvatar>
              </div>
              <h5 class="text-h5">
                {{ data.visitPercentage }}%
              </h5>
              <div class="text-body-2 text-disabled">
                {{ data.visitCount.toLocaleString() }}
              </div>
            </div>
          </VCol>
        </VRow>

        <div class="mt-6">
          <VProgressLinear
            :model-value="data.progressValue"
            color="#00CFE8"
            height="10"
            bg-color="primary"
            :rounded-bar="false"
            rounded
          />
        </div>
      </VCardText>

      <!-- Loading State -->
      <VCardText
        v-if="loading"
        class="d-flex align-center justify-center chart-loading"
        style="height: 200px;"
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
.sales-overview-card {
  .v-progress-linear {
    transition: all 0.3s ease;
    
    &:hover {
      transform: scaleY(1.2);
    }
  }

  .v-avatar {
    transition: all 0.3s ease;
    
    &:hover {
      transform: scale(1.1);
    }
  }
}
</style>
