<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuth } from '@/composables/useAuth'

const { t } = useI18n()
const { user } = useAuth()

// We'll use a CSS gradient instead of an image

// Computed properties for user info
const userDisplayName = computed(() => user?.nom_complet || 'User')
const userEmail = computed(() => user?.email || '')
const userPhone = computed(() => user?.telephone || '')
const userAddress = computed(() => user?.adresse || '')
const userJoinDate = computed(() => {
  if (user?.created_at) {
    return new Date(user.created_at).toLocaleDateString('fr-FR', {
      year: 'numeric',
      month: 'long'
    })
  }
  return ''
})

// User roles
const userRoles = computed(() => {
  if (user?.roles && Array.isArray(user.roles)) {
    return user.roles.join(', ')
  }
  return ''
})

// User status
const userStatus = computed(() => {
  const status = user?.statut
  return status === 'actif' ? t('active') : status === 'inactif' ? t('inactive') : t('blocked')
})

const userStatusColor = computed(() => {
  const status = user?.statut
  return status === 'actif' ? 'success' : status === 'inactif' ? 'warning' : 'error'
})

// Profile image with fallback
const profileImage = computed(() => {
  if (user?.photo_profil) {
    return user.photo_profil
  }
  // Generate avatar with initials
  const initials = userDisplayName.value.split(' ').map(n => n[0]).join('').toUpperCase()
  return `https://ui-avatars.com/api/?name=${encodeURIComponent(userDisplayName.value)}&background=7367f0&color=fff&size=140`
})
</script>

<template>
  <VCard v-if="user" class="profile-header-card">
    <!-- Enhanced Cover Background with gradient overlay -->
    <div class="profile-cover">
      <div class="cover-overlay" />
      <div class="cover-pattern" />
    </div>

    <VCardText class="profile-content">
      <!-- Profile Avatar Section -->
      <div class="avatar-section">
        <div class="avatar-container">
          <VAvatar
            rounded
            size="140"
            class="user-profile-avatar"
          >
            <VImg
              v-if="user?.photo_profil"
              :src="user.photo_profil"
              :alt="userDisplayName"
              cover
            />
            <VImg
              v-else
              :src="profileImage"
              :alt="userDisplayName"
              cover
            />
          </VAvatar>
          
          <!-- Online Status Indicator -->
          <div class="status-indicator">
            <VIcon
              icon="tabler-circle-filled"
              size="16"
              class="text-success"
            />
          </div>
        </div>
      </div>

      <!-- User Information Section -->
      <div class="user-info-section">
        <!-- Name and Status -->
        <div class="name-status-row">
          <div class="name-container">
            <h2 class="user-name">
              {{ userDisplayName }}
            </h2>
            <VChip
              v-if="user?.statut !== 'bloque'"
              :color="userStatusColor"
              size="small"
              variant="flat"
              class="status-chip"
            >
              <VIcon
                start
                :icon="userStatusColor === 'success' ? 'tabler-check' : userStatusColor === 'warning' ? 'tabler-clock' : 'tabler-x'"
                size="14"
              />
              {{ userStatus }}
            </VChip>
          </div>
          
          <!-- Role Badge -->
          <VChip
            v-if="userRoles"
            color="primary"
            variant="tonal"
            prepend-icon="tabler-shield-check"
            class="role-chip"
          >
            {{ userRoles }}
          </VChip>
        </div>

        <!-- Contact Information Grid -->
        <div class="contact-grid">
          <!-- Email -->
          <div v-if="userEmail" class="contact-item">
            <div class="contact-icon">
              <VIcon
                icon="tabler-mail"
                size="20"
              />
            </div>
            <div class="contact-content">
              <span class="contact-label">{{ t('email') }}</span>
              <span class="contact-value">{{ userEmail }}</span>
            </div>
          </div>

          <!-- Phone -->
          <div v-if="userPhone" class="contact-item">
            <div class="contact-icon">
              <VIcon
                icon="tabler-phone"
                size="20"
              />
            </div>
            <div class="contact-content">
              <span class="contact-label">{{ t('phone') }}</span>
              <span class="contact-value">{{ userPhone }}</span>
            </div>
          </div>

          <!-- Address -->
          <div v-if="userAddress" class="contact-item">
            <div class="contact-icon">
              <VIcon
                icon="tabler-map-pin"
                size="20"
              />
            </div>
            <div class="contact-content">
              <span class="contact-label">{{ t('address') }}</span>
              <span class="contact-value">{{ userAddress }}</span>
            </div>
          </div>

          <!-- Join Date -->
          <div v-if="userJoinDate" class="contact-item">
            <div class="contact-icon">
              <VIcon
                icon="tabler-calendar"
                size="20"
              />
            </div>
            <div class="contact-content">
              <span class="contact-label">{{ t('member_since') }}</span>
              <span class="contact-value">{{ userJoinDate }}</span>
            </div>
          </div>
        </div>

        <!-- Quick Actions removed as per requirements -->
      </div>
    </VCardText>
  </VCard>
</template>

<style lang="scss" scoped>
.profile-header-card {
  overflow: hidden;
  border-radius: 24px;
  background: white;
  backdrop-filter: none;
  border: 1px solid rgba(var(--v-theme-outline), 0.08);
  box-shadow: 0 8px 40px rgba(var(--v-theme-on-surface), 0.08);
}

.profile-cover {
  position: relative;
  height: 180px;
  background: linear-gradient(135deg, 
    rgb(var(--v-theme-primary)) 0%, 
    rgba(var(--v-theme-primary), 0.8) 50%,
    rgb(var(--v-theme-secondary)) 100%
  );
  overflow: hidden;
}

.cover-overlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: linear-gradient(45deg, 
    rgba(var(--v-theme-primary), 0.1) 0%,
    transparent 50%,
    rgba(var(--v-theme-secondary), 0.1) 100%
  );
}

.cover-pattern {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-image: 
    radial-gradient(circle at 20% 80%, rgba(var(--v-theme-surface), 0.1) 0%, transparent 50%),
    radial-gradient(circle at 80% 20%, rgba(var(--v-theme-surface), 0.1) 0%, transparent 50%),
    radial-gradient(circle at 40% 40%, rgba(var(--v-theme-surface), 0.05) 0%, transparent 50%);
}

.profile-content {
  position: relative;
  padding: 0;
}

.avatar-section {
  display: flex;
  justify-content: center;
  padding: 0 2rem;
  margin-top: -70px;
  margin-bottom: 2rem;
}

.avatar-container {
  position: relative;
  display: inline-block;
}

.user-profile-avatar {
  border: 6px solid rgba(var(--v-theme-surface), 1);
  background: rgba(var(--v-theme-surface), 1);
  box-shadow: 0 8px 32px rgba(var(--v-theme-on-surface), 0.15);
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  
  &:hover {
    transform: scale(1.05);
    box-shadow: 0 12px 40px rgba(var(--v-theme-on-surface), 0.2);
  }

  :deep(.v-img__img) {
    border-radius: 50%;
  }
}

.status-indicator {
  position: absolute;
  bottom: 10px;
  right: 10px;
  background: rgba(var(--v-theme-surface), 1);
  border-radius: 50%;
  padding: 4px;
  box-shadow: 0 2px 8px rgba(var(--v-theme-on-surface), 0.12);
}

.user-info-section {
  padding: 0 2rem 2rem;
}

.name-status-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 2rem;
  flex-wrap: wrap;
  gap: 1rem;
}

.name-container {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.75rem;
}

.user-name {
  font-size: 2rem;
  font-weight: 700;
  line-height: 1.2;
  letter-spacing: -0.025em;
  margin: 0;
  background: linear-gradient(135deg, 
    rgb(var(--v-theme-on-surface)) 0%, 
    rgba(var(--v-theme-on-surface), 0.8) 100%
  );
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.status-chip {
  border-radius: 12px;
  font-weight: 500;
  font-size: 0.75rem;
  letter-spacing: 0.025em;
}

.role-chip {
  border-radius: 12px;
  font-weight: 500;
  font-size: 0.875rem;
}

.contact-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.contact-item {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1rem;
  background: white;
  border-radius: 16px;
  border: 1px solid #e0e0e0;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  
  &:hover {
    background: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(var(--v-theme-on-surface), 0.08);
    border-color: #bdbdbd;
  }
}

.contact-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 44px;
  height: 44px;
  background: rgba(var(--v-theme-primary), 0.1);
  border-radius: 12px;
  color: rgb(var(--v-theme-primary));
  flex-shrink: 0;
}

.contact-content {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  min-width: 0;
}

.contact-label {
  font-size: 0.75rem;
  font-weight: 500;
  color: rgba(var(--v-theme-on-surface), 0.6);
  text-transform: uppercase;
  letter-spacing: 0.075em;
}

.contact-value {
  font-size: 0.875rem;
  font-weight: 500;
  color: rgb(var(--v-theme-on-surface));
  word-break: break-all;
}

.quick-actions {
  display: flex;
  gap: 1rem;
  justify-content: center;
  flex-wrap: wrap;
}

.action-btn {
  border-radius: 12px;
  font-weight: 500;
  letter-spacing: 0.025em;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  
  &:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(var(--v-theme-on-surface), 0.12);
  }
}

// Responsive Design
@media (max-width: 960px) {
  .profile-cover {
    height: 140px;
  }
  
  .avatar-section {
    margin-top: -60px;
    margin-bottom: 1.5rem;
    padding: 0 1.5rem;
  }
  
  .user-profile-avatar {
    border-width: 4px;
  }
  
  .user-info-section {
    padding: 0 1.5rem 1.5rem;
  }
  
  .user-name {
    font-size: 1.75rem;
  }
  
  .name-status-row {
    flex-direction: column;
    align-items: center;
    text-align: center;
    margin-bottom: 1.5rem;
  }
  
  .name-container {
    align-items: center;
  }
  
  .contact-grid {
    grid-template-columns: 1fr;
    gap: 1rem;
  }
}

@media (max-width: 600px) {
  .profile-cover {
    height: 120px;
  }
  
  .avatar-section {
    margin-top: -50px;
    margin-bottom: 1rem;
    padding: 0 1rem;
  }
  
  .user-profile-avatar {
    width: 100px !important;
    height: 100px !important;
    border-width: 3px;
  }
  
  .user-info-section {
    padding: 0 1rem 1rem;
  }
  
  .user-name {
    font-size: 1.5rem;
  }
  
  .contact-item {
    padding: 0.75rem;
  }
  
  .contact-icon {
    width: 36px;
    height: 36px;
  }
  
  .quick-actions {
    flex-direction: column;
    align-items: center;
  }
  
  .action-btn {
    width: 100%;
    max-width: 240px;
  }
}

// Dark mode adjustments
@media (prefers-color-scheme: dark) {
  .profile-header-card {
    background: white;
    border-color: rgba(var(--v-theme-outline), 0.12);
  }
  
  .contact-item {
    background: white;
    border-color: #e0e0e0;
    
    &:hover {
      background: white;
      border-color: #bdbdbd;
    }
  }
}
</style>
