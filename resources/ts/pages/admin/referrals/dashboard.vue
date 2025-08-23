<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import axios from '@/plugins/axios'

definePage({
  meta: {
    requiresAuth: true,
    requiresRole: 'admin',
  },
})

const { t } = useI18n()

// State
const loading = ref(false)
const stats = ref({
  overview: {
    total_clicks: 0,
    total_signups: 0,
    verified_signups: 0,
    conversion_rate: 0,
    verified_conversion_rate: 0,
    total_points_awarded: 0,
    active_referrers: 0,
  },
  top_referrers: [],
  date_range: {
    start_date: '',
    end_date: '',
  },
})

const dateRange = ref({
  start_date: new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
  end_date: new Date().toISOString().split('T')[0],
})

// Computed
const overviewCards = computed(() => [
  {
    title: t('referral_total_clicks'),
    value: stats.value.overview.total_clicks,
    icon: 'tabler-click',
    color: 'primary',
  },
  {
    title: t('referral_total_signups'),
    value: stats.value.overview.total_signups,
    icon: 'tabler-user-plus',
    color: 'success',
  },
  {
    title: t('referral_verified_signups'),
    value: stats.value.overview.verified_signups,
    icon: 'tabler-user-check',
    color: 'info',
  },
  {
    title: t('referral_conversion_rate'),
    value: `${stats.value.overview.conversion_rate}%`,
    icon: 'tabler-trending-up',
    color: 'warning',
  },
  {
    title: t('referral_points_awarded'),
    value: stats.value.overview.total_points_awarded,
    icon: 'tabler-award',
    color: 'secondary',
  },
  {
    title: t('referral_active_referrers'),
    value: stats.value.overview.active_referrers,
    icon: 'tabler-users',
    color: 'error',
  },
])

// Methods
const fetchStats = async () => {
  loading.value = true
  try {
    const response = await axios.get('/admin/referrals/dashboard/stats', {
      params: {
        start_date: dateRange.value.start_date,
        end_date: dateRange.value.end_date,
      },
    })

    if (response.data.success) {
      stats.value = response.data.data
    }
  } catch (error) {
    console.error('Failed to fetch referral stats:', error)
  } finally {
    loading.value = false
  }
}

const onDateRangeChange = () => {
  fetchStats()
}

// Lifecycle
onMounted(() => {
  fetchStats()
})
</script>

<template>
  <div>
    <!-- Page Header -->
    <div class="d-flex justify-space-between align-center mb-6">
      <div>
        <h1 class="text-h4 font-weight-bold mb-1">
          {{ t('referral_dashboard') }}
        </h1>
        <p class="text-body-1 mb-0">
          {{ t('referral_dashboard_subtitle') }}
        </p>
      </div>

      <!-- Date Range Picker -->
      <VCard class="pa-4" style="min-width: 300px;">
        <VRow no-gutters>
          <VCol cols="5">
            <VTextField
              v-model="dateRange.start_date"
              type="date"
              :label="t('start_date')"
              density="compact"
              variant="outlined"
              @change="onDateRangeChange"
            />
          </VCol>
          <VCol cols="2" class="d-flex align-center justify-center">
            <VIcon icon="tabler-arrow-right" size="20" />
          </VCol>
          <VCol cols="5">
            <VTextField
              v-model="dateRange.end_date"
              type="date"
              :label="t('end_date')"
              density="compact"
              variant="outlined"
              @change="onDateRangeChange"
            />
          </VCol>
        </VRow>
      </VCard>
    </div>

    <!-- Overview Cards -->
    <VRow class="mb-6">
      <VCol
        v-for="card in overviewCards"
        :key="card.title"
        cols="12"
        sm="6"
        lg="4"
        xl="2"
      >
        <VCard>
          <VCardText class="d-flex align-center">
            <VAvatar
              :color="card.color"
              variant="tonal"
              size="40"
              class="me-4"
            >
              <VIcon :icon="card.icon" size="24" />
            </VAvatar>
            <div>
              <h6 class="text-h6 mb-0">
                {{ card.value }}
              </h6>
              <p class="text-body-2 mb-0">
                {{ card.title }}
              </p>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Top Referrers Leaderboard -->
    <VCard class="mb-6">
      <VCardTitle class="d-flex align-center">
        <VIcon icon="tabler-trophy" class="me-2" />
        {{ t('referral_top_referrers') }}
      </VCardTitle>
      <VCardText>
        <VDataTable
          :headers="[
            { title: t('affiliate'), key: 'affiliate_name' },
            { title: t('email'), key: 'affiliate_email' },
            { title: t('total_referrals'), key: 'total_referrals' },
            { title: t('verified_referrals'), key: 'verified_referrals' },
            { title: t('total_points'), key: 'total_points' },
          ]"
          :items="stats.top_referrers"
          :loading="loading"
          class="text-no-wrap"
        >
          <template #item.affiliate_name="{ item }">
            <div class="d-flex align-center">
              <VAvatar size="32" class="me-3">
                <VIcon icon="tabler-user" />
              </VAvatar>
              <span class="font-weight-medium">{{ item.affiliate_name }}</span>
            </div>
          </template>

          <template #item.total_referrals="{ item }">
            <VChip
              :color="item.total_referrals > 10 ? 'success' : 'default'"
              size="small"
            >
              {{ item.total_referrals }}
            </VChip>
          </template>

          <template #item.verified_referrals="{ item }">
            <VChip
              :color="item.verified_referrals > 5 ? 'info' : 'default'"
              size="small"
            >
              {{ item.verified_referrals }}
            </VChip>
          </template>

          <template #item.total_points="{ item }">
            <span class="font-weight-medium text-success">
              {{ item.total_points || 0 }} pts
            </span>
          </template>

          <template #no-data>
            <div class="text-center py-8">
              <VIcon icon="tabler-users-off" size="64" class="mb-4" color="disabled" />
              <h6 class="text-h6 mb-2">{{ t('no_referrers_yet') }}</h6>
              <p class="text-body-2">{{ t('no_referrers_description') }}</p>
            </div>
          </template>
        </VDataTable>
      </VCardText>
    </VCard>

    <!-- Quick Actions -->
    <VRow>
      <VCol cols="12" md="6">
        <VCard>
          <VCardTitle>{{ t('quick_actions') }}</VCardTitle>
          <VCardText>
            <VBtn
              block
              color="primary"
              class="mb-3"
              :to="{ name: 'admin-referrals-referred-users' }"
            >
              <VIcon start icon="tabler-users" />
              {{ t('view_referred_users') }}
            </VBtn>
            <VBtn
              block
              color="secondary"
              class="mb-3"
              :to="{ name: 'admin-referrals-dispensations' }"
            >
              <VIcon start icon="tabler-gift" />
              {{ t('manage_dispensations') }}
            </VBtn>
            <VBtn
              block
              color="info"
              :to="{ name: 'admin-referrals-performance' }"
            >
              <VIcon start icon="tabler-chart-line" />
              {{ t('performance_analysis') }}
            </VBtn>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" md="6">
        <VCard>
          <VCardTitle>{{ t('system_info') }}</VCardTitle>
          <VCardText>
            <div class="d-flex justify-space-between mb-2">
              <span>{{ t('attribution_window') }}:</span>
              <span class="font-weight-medium">30 {{ t('days') }}</span>
            </div>
            <div class="d-flex justify-space-between mb-2">
              <span>{{ t('verification_required') }}:</span>
              <VChip color="success" size="small">{{ t('yes') }}</VChip>
            </div>
            <div class="d-flex justify-space-between">
              <span>{{ t('reward_type') }}:</span>
              <VChip color="info" size="small">{{ t('manual_points') }}</VChip>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>
