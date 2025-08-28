<script setup lang="ts">
definePage({
  meta: {
    requiresAuth: true,
  },
})
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuth } from '@/composables/useAuth'
import { useRouter } from 'vue-router'
import { useApi } from '@/composables/useApi'
import UserProfileHeader from '@/components/profile/UserProfileHeader.vue'
import ProfileAbout from '@/components/profile/ProfileAbout.vue'
import ProfileInformation from '@/components/profile/ProfileInformation.vue'
import ChangePassword from '@/components/profile/ChangePassword.vue'
import KycDocuments from '@/components/profile/KycDocuments.vue'

// Route meta (handled by file-based routing)
// This page requires authentication

const { t } = useI18n()
const { user, isLoading, fetchUser } = useAuth()
const router = useRouter()

// Tab management
const activeTab = ref('profile')

// Fetch fresh profile data on mount
onMounted(async () => {
  console.log('🔄 Profile page mounted, fetching fresh user data...')
  try {
    await fetchUser()
    console.log('✅ User data refreshed successfully')
  } catch (error) {
    console.error('❌ Failed to refresh user data:', error)
  }
})

const tabs = computed(() => [
  {
    icon: 'tabler-user-check',
    title: t('profile_tab'),
    value: 'profile'
  },
  {
    icon: 'tabler-shield-lock',
    title: t('password_tab'),
    value: 'password'
  },
  {
    icon: 'tabler-file-certificate',
    title: t('documents_tab'),
    value: 'documents'
  }
])

// Loading state
const loading = ref(false)

// Refresh page function
const refreshPage = () => {
  window.location.reload()
}

onMounted(() => {
  // Any initialization logic if needed
})
</script>

<template>
  <div class="profile-container">
    <!-- Loading state with better animation -->
    <div v-if="isLoading" class="loading-container">
      <VCard class="loading-card" elevation="0">
        <VCardText class="text-center py-16">
          <div class="loading-content">
            <VProgressCircular
              indeterminate
              color="primary"
              size="56"
              width="4"
            />
            <h5 class="text-h5 mt-4 text-medium-emphasis">
              {{ t('loading_profile') }}
            </h5>
            <p class="text-body-2 text-disabled mt-2">
              {{ t('please_wait') }}
            </p>
          </div>
        </VCardText>
      </VCard>
    </div>

    <!-- Profile content with improved layout -->
    <div v-else-if="user" class="profile-content">
      <!-- Enhanced Profile Header -->
      <UserProfileHeader class="profile-header-enhanced" />

      <!-- Navigation Container -->
      <VCard class="navigation-card mb-6" elevation="0" variant="outlined">
        <VCardText class="pa-0">
          <VTabs
            v-model="activeTab"
            class="profile-tabs"
            color="primary"
            height="72"
            show-arrows
          >
            <VTab
              v-for="tab in tabs"
              :key="tab.value"
              :value="tab.value"
              class="profile-tab"
            >
              <div class="tab-content">
                <VIcon
                  size="24"
                  :icon="tab.icon"
                  class="tab-icon"
                />
                <span class="tab-title">{{ tab.title }}</span>
              </div>
            </VTab>
          </VTabs>
        </VCardText>
      </VCard>

      <!-- Enhanced Tab Content -->
      <VWindow v-model="activeTab" class="profile-window">
        <!-- Profile Information Tab -->
        <VWindowItem value="profile" class="window-item">
          <VFadeTransition mode="out-in">
            <VRow>
              <VCol
                cols="12"
                lg="4"
                order="2"
                order-lg="1"
              >
                <div class="sticky-sidebar">
                  <ProfileAbout />
                </div>
              </VCol>
              <VCol
                cols="12"
                lg="8"
                order="1"
                order-lg="2"
              >
                <ProfileInformation />
              </VCol>
            </VRow>
          </VFadeTransition>
        </VWindowItem>

        <!-- Change Password Tab -->
        <VWindowItem value="password" class="window-item">
          <VFadeTransition mode="out-in">
            <VRow justify="center">
              <VCol
                cols="12"
                md="10"
                lg="8"
                xl="6"
              >
                <ChangePassword />
              </VCol>
            </VRow>
          </VFadeTransition>
        </VWindowItem>

        <!-- KYC Documents Tab -->
        <VWindowItem value="documents" class="window-item">
          <VFadeTransition mode="out-in">
            <KycDocuments />
          </VFadeTransition>
        </VWindowItem>
      </VWindow>
    </div>

    <!-- Enhanced Error state -->
    <div v-else class="error-container">
      <VCard class="error-card" elevation="0">
        <VCardText class="text-center py-16">
          <VIcon
            icon="tabler-alert-circle"
            size="64"
            class="text-error mb-4"
          />
          <h4 class="text-h4 mb-2">
            {{ t('error_loading_profile') }}
          </h4>
          <p class="text-body-1 text-medium-emphasis mb-6">
            {{ t('profile_error_description') }}
          </p>
          <VBtn
            color="primary"
            variant="outlined"
            @click="refreshPage"
          >
            <VIcon start icon="tabler-refresh" />
            {{ t('retry') }}
          </VBtn>
        </VCardText>
      </VCard>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.profile-container {
  min-height: 100vh;
  padding: 0;
}

// Loading state styles
.loading-container {
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: 60vh;
  padding: 2rem;
}

.loading-card {
  max-width: 400px;
  width: 100%;
  border-radius: 16px;
  background: linear-gradient(135deg, rgba(var(--v-theme-surface), 0.8) 0%, rgba(var(--v-theme-surface), 1) 100%);
  backdrop-filter: blur(10px);
}

.loading-content {
  display: flex;
  flex-direction: column;
  align-items: center;
}

// Profile content styles
.profile-content {
  position: relative;
}

.profile-header-enhanced {
  margin-bottom: 2rem;
  
  :deep(.v-card) {
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 8px 32px rgba(var(--v-theme-on-surface), 0.08);
  }
}

// Navigation styles
.navigation-card {
  border-radius: 16px;
  background: rgba(var(--v-theme-surface), 0.9);
  backdrop-filter: blur(20px);
  border: 1px solid rgba(var(--v-theme-outline), 0.12);
  box-shadow: 0 4px 20px rgba(var(--v-theme-on-surface), 0.05);
}

.profile-tabs {
  .v-tabs-bar {
    background: transparent;
  }
}

.profile-tab {
  min-height: 72px;
  border-radius: 16px;
  margin: 0.5rem;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  position: relative;
  overflow: hidden;

  &::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(var(--v-theme-primary), 0.04);
    border-radius: 16px;
    opacity: 0;
    transition: opacity 0.3s ease;
  }

  &:hover {
    background: rgba(var(--v-theme-primary), 0.08);
    transform: translateY(-3px);
    box-shadow: 0 8px 24px rgba(var(--v-theme-primary), 0.15);
    border-radius: 18px;

    &::before {
      opacity: 1;
    }

    .tab-icon {
      transform: scale(1.1);
    }
  }

  &.v-tab--selected {
    background: linear-gradient(135deg, rgb(var(--v-theme-primary)), rgba(var(--v-theme-primary), 0.8));
    color: rgb(var(--v-theme-on-primary));
    box-shadow: 0 6px 20px rgba(var(--v-theme-primary), 0.35);
    transform: translateY(-2px);
    border-radius: 18px;

    &::before {
      opacity: 1;
      background: rgba(255, 255, 255, 0.1);
    }

    .tab-icon, .tab-title {
      color: rgb(var(--v-theme-on-primary));
    }

    .tab-icon {
      transform: scale(1.05);
    }
  }
}

.tab-content {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem;
}

.tab-icon {
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  transform-origin: center;
}

.tab-title {
  font-size: 0.875rem;
  font-weight: 500;
  letter-spacing: 0.025em;
}

// Window content styles
.profile-window {
  .window-item {
    padding-top: 1rem;
  }
}

.sticky-sidebar {
  position: sticky;
  top: 2rem;
  z-index: 1;
}

// Enhanced card styles for components
:deep(.v-card) {
  background: white;
  border-radius: 16px;
  border: 1px solid rgba(var(--v-theme-outline), 0.08);
  box-shadow: 0 2px 16px rgba(var(--v-theme-on-surface), 0.04);
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  
  &:hover {
    box-shadow: 0 8px 32px rgba(var(--v-theme-on-surface), 0.08);
    transform: translateY(-2px);
  }
}

:deep(.v-card-title) {
  padding: 1.5rem 1.5rem 1rem;
  font-size: 1.25rem;
  font-weight: 600;
  letter-spacing: -0.025em;
}

:deep(.v-card-text) {
  padding: 0 1.5rem 1.5rem;
}

// Form enhancements
:deep(.v-text-field) {
  .v-field {
    border-radius: 12px;
    background: rgba(var(--v-theme-surface), 0.6);
    backdrop-filter: blur(10px);
  }
  
  &.v-text-field--focused .v-field {
    box-shadow: 0 0 0 2px rgba(var(--v-theme-primary), 0.2);
  }
}

:deep(.v-btn) {
  border-radius: 12px;
  font-weight: 500;
  letter-spacing: 0.025em;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  
  &:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 16px rgba(var(--v-theme-on-surface), 0.12);
  }
  
  &.v-btn--variant-elevated {
    box-shadow: 0 2px 8px rgba(var(--v-theme-on-surface), 0.12);
  }
}

// Error state styles
.error-container {
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: 60vh;
  padding: 2rem;
}

.error-card {
  max-width: 500px;
  width: 100%;
  border-radius: 20px;
  background: linear-gradient(135deg, rgba(var(--v-theme-error-container), 0.1) 0%, rgba(var(--v-theme-surface), 1) 100%);
  border: 1px solid rgba(var(--v-theme-error), 0.12);
}

// Responsive improvements
@media (max-width: 960px) {
  .profile-container {
    padding: 1rem;
  }
  
  .sticky-sidebar {
    position: static;
  }
  
  .tab-content {
    flex-direction: row;
    gap: 0.75rem;
  }
  
  .tab-title {
    font-size: 0.8rem;
  }
}

@media (max-width: 600px) {
  .profile-container {
    padding: 0.5rem;
  }
  
  .profile-header-enhanced {
    margin-bottom: 1rem;
  }
  
  .navigation-card {
    margin-bottom: 1rem;
  }
  
  .profile-tab {
    min-height: 64px;
    margin: 0.25rem;
  }
  
  .tab-content {
    gap: 0.5rem;
  }
  
  .tab-title {
    display: none;
  }
  
  :deep(.v-card-title) {
    padding: 1rem 1rem 0.5rem;
    font-size: 1.125rem;
  }
  
  :deep(.v-card-text) {
    padding: 0 1rem 1rem;
  }
}

// Dark mode enhancements
@media (prefers-color-scheme: dark) {
  .navigation-card {
    background: white;
    border-color: rgba(var(--v-theme-outline), 0.16);
  }
  
  :deep(.v-card) {
    background: white;
    border-color: rgba(var(--v-theme-outline), 0.12);
  }
}

// Animation classes
.v-enter-active,
.v-leave-active {
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.v-enter-from {
  opacity: 0;
  transform: translateY(20px);
}

.v-leave-to {
  opacity: 0;
  transform: translateY(-20px);
}
</style>
