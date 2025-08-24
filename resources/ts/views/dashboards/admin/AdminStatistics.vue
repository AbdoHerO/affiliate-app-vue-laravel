<script setup lang="ts">
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const statistics = [
  {
    title: t('admin.dashboard.sales'),
    stats: '230k',
    icon: 'tabler-chart-pie-2',
    color: 'primary',
  },
  {
    title: t('admin.dashboard.customers'),
    stats: '8.549k',
    icon: 'tabler-users',
    color: 'info',
  },
  {
    title: t('admin.dashboard.products'),
    stats: '1.423k',
    icon: 'tabler-shopping-cart',
    color: 'error',
  },
  {
    title: t('admin.dashboard.revenue'),
    stats: '$9745',
    icon: 'tabler-currency-dollar',
    color: 'success',
  },
]
</script>

<template>
  <VCard :title="t('admin.dashboard.statistics')">
    <template #append>
      <span class="text-sm text-disabled">{{ t('admin.dashboard.updatedAgo', { time: '1 mois' }) }}</span>
    </template>

    <VCardText>
      <VRow>
        <VCol
          v-for="item in statistics"
          :key="item.title"
          cols="6"
          md="3"
        >
          <div class="d-flex align-center gap-4 mt-md-9 mt-0">
            <VAvatar
              :color="item.color"
              variant="tonal"
              rounded
              size="40"
            >
              <VIcon :icon="item.icon" />
            </VAvatar>

            <div class="d-flex flex-column">
              <h5 class="text-h5">
                {{ item.stats }}
              </h5>
              <div class="text-sm">
                {{ item.title }}
              </div>
            </div>
          </div>
        </VCol>
      </VRow>
    </VCardText>
  </VCard>
</template>
