<script setup lang="ts">
import { useAuth } from '@/composables/useAuth'
import { useI18n } from 'vue-i18n'

const { user } = useAuth()
const { t } = useI18n()

// Mock activity data - in a real app, this would come from an API
const activities = ref([
  {
    id: 1,
    type: 'profile_update',
    title: t('profile_updated'),
    description: t('profile_information_updated'),
    timestamp: new Date(Date.now() - 3600000), // 1 hour ago
    icon: 'tabler-user-edit',
    color: 'primary'
  },
  {
    id: 2,
    type: 'login',
    title: t('successful_login'),
    description: t('logged_in_from_new_device'),
    timestamp: new Date(Date.now() - 86400000), // 1 day ago
    icon: 'tabler-login',
    color: 'success'
  },
  {
    id: 3,
    type: 'password_change',
    title: t('password_changed'),
    description: t('password_successfully_updated'),
    timestamp: new Date(Date.now() - 172800000), // 2 days ago
    icon: 'tabler-key',
    color: 'warning'
  },
  {
    id: 4,
    type: 'kyc_update',
    title: t('kyc_status_updated'),
    description: t('kyc_documents_reviewed'),
    timestamp: new Date(Date.now() - 259200000), // 3 days ago
    icon: 'tabler-shield-check',
    color: 'info'
  },
  {
    id: 5,
    type: 'account_created',
    title: t('account_created'),
    description: t('welcome_to_platform'),
    timestamp: user.value?.created_at ? new Date(user.value.created_at) : new Date(Date.now() - 604800000),
    icon: 'tabler-user-plus',
    color: 'success'
  }
])

// Format relative time
const formatRelativeTime = (date: Date) => {
  const now = new Date()
  const diffInSeconds = Math.floor((now.getTime() - date.getTime()) / 1000)
  
  if (diffInSeconds < 60) {
    return t('just_now')
  } else if (diffInSeconds < 3600) {
    const minutes = Math.floor(diffInSeconds / 60)
    return t('minutes_ago', { count: minutes })
  } else if (diffInSeconds < 86400) {
    const hours = Math.floor(diffInSeconds / 3600)
    return t('hours_ago', { count: hours })
  } else if (diffInSeconds < 604800) {
    const days = Math.floor(diffInSeconds / 86400)
    return t('days_ago', { count: days })
  } else {
    return date.toLocaleDateString()
  }
}

// Get activity icon based on type
const getActivityIcon = (activity: any) => {
  const iconMap: Record<string, string> = {
    profile_update: 'tabler-user-edit',
    login: 'tabler-login',
    password_change: 'tabler-key',
    kyc_update: 'tabler-shield-check',
    account_created: 'tabler-user-plus',
    email_verified: 'tabler-mail-check',
    role_changed: 'tabler-crown',
    settings_updated: 'tabler-settings'
  }
  
  return iconMap[activity.type] || 'tabler-circle'
}

// Get activity color based on type
const getActivityColor = (activity: any) => {
  const colorMap: Record<string, string> = {
    profile_update: 'primary',
    login: 'success',
    password_change: 'warning',
    kyc_update: 'info',
    account_created: 'success',
    email_verified: 'success',
    role_changed: 'secondary',
    settings_updated: 'primary'
  }
  
  return colorMap[activity.type] || 'secondary'
}
</script>

<template>
  <VCard>
    <VCardItem>
      <VCardTitle>{{ t('activity_timeline') }}</VCardTitle>
      <VCardSubtitle>{{ t('recent_account_activity') }}</VCardSubtitle>
    </VCardItem>

    <VCardText>
      <VTimeline
        side="end"
        align="start"
        line-inset="9"
        truncate-line="start"
        density="compact"
      >
        <VTimelineItem
          v-for="activity in activities"
          :key="activity.id"
          :dot-color="getActivityColor(activity)"
          size="x-small"
        >
          <template #icon>
            <VIcon
              :icon="getActivityIcon(activity)"
              size="16"
            />
          </template>

          <template #opposite>
            <span class="text-caption text-disabled">
              {{ formatRelativeTime(activity.timestamp) }}
            </span>
          </template>

          <div class="d-flex justify-space-between align-center flex-wrap mb-2">
            <h6 class="text-h6 me-2">
              {{ activity.title }}
            </h6>
          </div>

          <p class="text-body-2 mb-1">
            {{ activity.description }}
          </p>

          <span class="text-caption text-disabled">
            {{ activity.timestamp.toLocaleString() }}
          </span>
        </VTimelineItem>
      </VTimeline>
    </VCardText>

    <VCardActions>
      <VBtn
        variant="outlined"
        size="small"
        prepend-icon="tabler-refresh"
      >
        {{ t('refresh_activity') }}
      </VBtn>
      
      <VSpacer />
      
      <VBtn
        variant="text"
        size="small"
        append-icon="tabler-arrow-right"
      >
        {{ t('view_all_activity') }}
      </VBtn>
    </VCardActions>
  </VCard>
</template>

<style lang="scss" scoped>
.v-timeline {
  .v-timeline-item {
    .v-timeline-item__body {
      padding-inline-start: 1rem;
    }
  }
}
</style>
