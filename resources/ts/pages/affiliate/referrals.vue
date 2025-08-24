<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import axios from '@/plugins/axios'

definePage({
  meta: {
    requiresAuth: true,
    requiresRole: 'affiliate',
  },
})

const { t } = useI18n()

// State
const loading = ref(false)
const dashboardData = ref({
  stats: {
    clicks: 0,
    signups: 0,
    verified_signups: 0,
    conversion_rate: 0,
    verified_conversion_rate: 0,
    total_points: 0,
    referral_url: '',
  },
  recent_activity: [],
})

const referralLink = ref('')
const showCopySuccess = ref(false)

const dateRange = ref({
  start_date: new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
  end_date: new Date().toISOString().split('T')[0],
})

// Computed
const statsCards = computed(() => [
  {
    title: t('referral_clicks'),
    value: dashboardData.value.stats.clicks,
    icon: 'tabler-click',
    color: 'primary',
  },
  {
    title: t('signups'),
    value: dashboardData.value.stats.signups,
    icon: 'tabler-user-plus',
    color: 'success',
  },
  {
    title: t('verified_signups'),
    value: dashboardData.value.stats.verified_signups,
    icon: 'tabler-user-check',
    color: 'info',
  },
  {
    title: t('conversion_rate'),
    value: `${dashboardData.value.stats.conversion_rate}%`,
    icon: 'tabler-trending-up',
    color: 'warning',
  },
  {
    title: t('total_points'),
    value: dashboardData.value.stats.total_points,
    icon: 'tabler-award',
    color: 'secondary',
  },
])

// Methods
const fetchDashboard = async () => {
  loading.value = true
  try {
    const response = await axios.get('/affiliate/referrals/dashboard', {
      params: {
        start_date: dateRange.value.start_date,
        end_date: dateRange.value.end_date,
      },
    })

    if (response.data.success) {
      dashboardData.value = response.data.data
      referralLink.value = dashboardData.value.stats.referral_url
    }
  } catch (error) {
    console.error('Error fetching referral dashboard:', error)
  } finally {
    loading.value = false
  }
}

const fetchReferralLink = async () => {
  try {
    const response = await axios.get('/affiliate/referrals/link')
    if (response.data.success) {
      referralLink.value = response.data.data.referral_url
    }
  } catch (error) {
    console.error('Error fetching referral link:', error)
  }
}

const copyReferralLink = async () => {
  try {
    // Ensure we have a referral link
    if (!referralLink.value) {
      await fetchReferralLink()
    }

    await navigator.clipboard.writeText(referralLink.value)
    showCopySuccess.value = true
    setTimeout(() => {
      showCopySuccess.value = false
    }, 2000)
  } catch (error) {
    console.error('Error copying link:', error)
  }
}

const shareReferralLink = async () => {
  try {
    // Ensure we have a referral link
    if (!referralLink.value) {
      await fetchReferralLink()
    }

    if (navigator.share) {
      await navigator.share({
        title: t('referral_link_share_title'),
        text: t('referral_link_share_text'),
        url: referralLink.value,
      })
    } else {
      await copyReferralLink()
    }
  } catch (error) {
    console.error('Error sharing link:', error)
    // Fallback to copy if share fails
    await copyReferralLink()
  }
}

const onDateRangeChange = () => {
  fetchDashboard()
}

const formatActivityDate = (dateString: string) => {
  return new Date(dateString).toLocaleDateString()
}

const getActivityIcon = (type: string) => {
  return type === 'referral' ? 'tabler-user-plus' : 'tabler-gift'
}

const getActivityColor = (type: string) => {
  return type === 'referral' ? 'success' : 'secondary'
}

// Lifecycle
onMounted(async () => {
  // Always ensure we have a referral link first
  await fetchReferralLink()
  // Then fetch the full dashboard
  await fetchDashboard()
})
</script>

<template>
  <div>
    <!-- Page Header -->
    <div class="d-flex justify-space-between align-center mb-6">
      <div>
        <h1 class="text-h4 font-weight-bold mb-1">
          {{ t('my_referrals') }}
        </h1>
        <p class="text-body-1 mb-0">
          {{ t('referrals_subtitle') }}
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

    <!-- Referral Link Card -->
    <VCard class="mb-6">
      <VCardTitle class="d-flex align-center">
        <VIcon icon="tabler-link" class="me-2" />
        {{ t('your_referral_link') }}
      </VCardTitle>
      <VCardText>
        <VAlert
          type="info"
          variant="tonal"
          class="mb-4"
        >
          {{ t('referral_link_instructions') }}
        </VAlert>

        <div class="d-flex align-center gap-2">
          <VTextField
            :model-value="referralLink"
            readonly
            variant="outlined"
            class="flex-grow-1"
          />
          <VBtn
            color="primary"
            @click="copyReferralLink"
          >
            <VIcon icon="tabler-copy" />
          </VBtn>
          <VBtn
            color="secondary"
            @click="shareReferralLink"
          >
            <VIcon icon="tabler-share" />
          </VBtn>
        </div>

        <VSnackbar
          v-model="showCopySuccess"
          color="success"
          timeout="2000"
        >
          {{ t('link_copied_successfully') }}
        </VSnackbar>
      </VCardText>
    </VCard>

    <!-- Stats Cards -->
    <VRow class="mb-6">
      <VCol
        v-for="card in statsCards"
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

    <VRow>
      <!-- Recent Activity -->
      <VCol cols="12" md="6">
        <VCard>
          <VCardTitle class="d-flex align-center">
            <VIcon icon="tabler-activity" class="me-2" />
            {{ t('recent_activity') }}
          </VCardTitle>
          <VCardText>
            <div v-if="dashboardData.recent_activity.length > 0">
              <div
                v-for="(activity, index) in dashboardData.recent_activity"
                :key="index"
                class="d-flex align-center mb-3"
              >
                <VAvatar
                  :color="getActivityColor(activity.type)"
                  variant="tonal"
                  size="32"
                  class="me-3"
                >
                  <VIcon :icon="getActivityIcon(activity.type)" size="16" />
                </VAvatar>
                <div class="flex-grow-1">
                  <div class="font-weight-medium">{{ activity.message }}</div>
                  <div class="text-body-2 text-medium-emphasis">
                    {{ formatActivityDate(activity.date) }}
                  </div>
                </div>
                <VChip
                  v-if="activity.type === 'referral' && activity.verified"
                  color="success"
                  size="small"
                >
                  {{ t('verified') }}
                </VChip>
              </div>
            </div>
            <div v-else class="text-center py-8">
              <VIcon icon="tabler-activity" size="48" class="mb-4" color="disabled" />
              <p class="text-body-2">{{ t('no_activity_yet') }}</p>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Quick Actions -->
      <VCol cols="12" md="6">
        <VCard>
          <VCardTitle>{{ t('quick_actions') }}</VCardTitle>
          <VCardText>
            <VBtn
              block
              color="primary"
              class="mb-3"
              to="/affiliate/referrals/users"
            >
              <VIcon start icon="tabler-users" />
              {{ t('view_referred_users') }}
            </VBtn>
            <VBtn
              block
              color="secondary"
              class="mb-3"
              to="/affiliate/referrals/rewards"
            >
              <VIcon start icon="tabler-gift" />
              {{ t('view_rewards_history') }}
            </VBtn>
            <VBtn
              block
              color="info"
              @click="shareReferralLink"
            >
              <VIcon start icon="tabler-share" />
              {{ t('share_referral_link') }}
            </VBtn>
          </VCardText>
        </VCard>

        <!-- How It Works -->
        <VCard class="mt-4">
          <VCardTitle>{{ t('how_it_works') }}</VCardTitle>
          <VCardText>
            <div class="d-flex align-start mb-3">
              <VAvatar color="primary" variant="tonal" size="24" class="me-3 mt-1">
                <span class="text-caption font-weight-bold">1</span>
              </VAvatar>
              <div>
                <div class="font-weight-medium">{{ t('share_your_link') }}</div>
                <div class="text-body-2 text-medium-emphasis">{{ t('share_link_description') }}</div>
              </div>
            </div>
            <div class="d-flex align-start mb-3">
              <VAvatar color="success" variant="tonal" size="24" class="me-3 mt-1">
                <span class="text-caption font-weight-bold">2</span>
              </VAvatar>
              <div>
                <div class="font-weight-medium">{{ t('friend_signs_up') }}</div>
                <div class="text-body-2 text-medium-emphasis">{{ t('signup_description') }}</div>
              </div>
            </div>
            <div class="d-flex align-start">
              <VAvatar color="secondary" variant="tonal" size="24" class="me-3 mt-1">
                <span class="text-caption font-weight-bold">3</span>
              </VAvatar>
              <div>
                <div class="font-weight-medium">{{ t('earn_rewards') }}</div>
                <div class="text-body-2 text-medium-emphasis">{{ t('rewards_description') }}</div>
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>
