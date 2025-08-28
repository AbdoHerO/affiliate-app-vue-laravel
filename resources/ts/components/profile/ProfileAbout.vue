<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuth } from '@/composables/useAuth'

const { t } = useI18n()
const { user } = useAuth()

// About information
const aboutInfo = computed(() => {
  if (!user) return []
  
  return [
    {
      property: t('form_full_name'),
      value: user.nom_complet || t('not_provided'),
      icon: 'tabler-user'
    },
    {
      property: t('form_email'),
      value: user.email || t('not_provided'),
      icon: 'tabler-mail'
    },
    {
      property: t('form_phone'),
      value: user.telephone || t('not_provided'),
      icon: 'tabler-phone'
    },
    {
      property: t('form_address'),
      value: user.adresse || t('not_provided'),
      icon: 'tabler-map-pin'
    },
    {
      property: t('cin_number'),
      value: user.cin || t('not_provided'),
      icon: 'tabler-id'
    }
  ].filter(item => item.value !== t('not_provided'))
})

// Account information
const accountInfo = computed(() => {
  if (!user) return []
  
  const info = []
  
  // Always show status
  info.push({
    property: t('status'),
    value: user.statut === 'actif' ? t('active') : user.statut === 'inactif' ? t('inactive') : t('blocked'),
    icon: 'tabler-circle-check',
    color: user.statut === 'actif' ? 'success' : user.statut === 'inactif' ? 'warning' : 'error'
  })
  
  // Always show email verification
  info.push({
    property: t('email_verification'),
    value: user.email_verifie ? t('verified') : t('not_verified'),
    icon: user.email_verifie ? 'tabler-shield-check' : 'tabler-shield-x',
    color: user.email_verifie ? 'success' : 'warning'
  })
  
  // Only show KYC status if it exists and is not null/undefined
  if (user.kyc_statut && user.kyc_statut !== null && user.kyc_statut !== undefined) {
    info.push({
      property: t('kyc_status'),
      value: getKycStatusLabel(user.kyc_statut),
      icon: 'tabler-file-certificate',
      color: getKycStatusColor(user.kyc_statut)
    })
  }
  
  return info
})

// Banking information
const bankingInfo = computed(() => {
  if (!user) return []
  
  const info = []
  
  if (user.rib) {
    info.push({
      property: t('rib'),
      value: user.rib,
      icon: 'tabler-credit-card'
    })
  }
  
  if (user.bank_type) {
    info.push({
      property: t('bank_type'),
      value: user.bank_type,
      icon: 'tabler-building-bank'
    })
  }
  
  return info
})

// Roles and permissions
const rolesInfo = computed(() => {
  if (!user?.roles) return []
  
  return [
    {
      property: t('roles'),
      value: Array.isArray(user.roles) ? user.roles.join(', ') : user.roles,
      icon: 'tabler-shield'
    }
  ]
})

// Helper functions
const getKycStatusLabel = (status: string) => {
  const statusMap: Record<string, string> = {
    'non_requis': t('not_required'),
    'en_attente': t('pending'),
    'valide': t('validated'),
    'refuse': t('rejected')
  }
  return statusMap[status] || status
}

const getKycStatusColor = (status: string) => {
  const colorMap: Record<string, string> = {
    'non_requis': 'info',
    'en_attente': 'warning',
    'valide': 'success',
    'refuse': 'error'
  }
  return colorMap[status] || 'default'
}
</script>

<template>
  <div class="profile-about">
    <!-- About Information -->
    <VCard v-if="aboutInfo.length" class="about-card mb-6">
      <VCardText class="about-content">
        <div class="section-header">
          <VIcon icon="tabler-user-circle" size="24" class="section-icon" />
          <h5 class="section-title">{{ t('about').toUpperCase() }}</h5>
        </div>

        <div class="info-list">
          <div
            v-for="item in aboutInfo"
            :key="item.property"
            class="info-item"
          >
            <div class="info-icon">
              <VIcon
                :icon="item.icon"
                size="20"
              />
            </div>
            <div class="info-content">
              <span class="info-label">{{ item.property }}</span>
              <span class="info-value">{{ item.value }}</span>
            </div>
          </div>
        </div>
      </VCardText>
    </VCard>

    <!-- Account Information -->
    <VCard v-if="accountInfo.length" class="account-card mb-6">
      <VCardText class="account-content">
        <div class="section-header">
          <VIcon icon="tabler-settings" size="24" class="section-icon" />
          <h5 class="section-title">{{ t('account_information').toUpperCase() }}</h5>
        </div>

        <div class="info-list">
          <div
            v-for="item in accountInfo"
            :key="item.property"
            class="info-item"
          >
            <div class="info-icon" :class="item.color ? `info-icon--${item.color}` : ''">
              <VIcon
                :icon="item.icon"
                size="20"
                :color="item.color"
              />
            </div>
            <div class="info-content">
              <span class="info-label">{{ item.property }}</span>
              <VChip
                v-if="item.color"
                :color="item.color"
                size="small"
                variant="flat"
                class="info-chip"
              >
                {{ item.value }}
              </VChip>
              <span v-else class="info-value">{{ item.value }}</span>
            </div>
          </div>
        </div>
      </VCardText>
    </VCard>

    <!-- Banking Information -->
    <VCard v-if="bankingInfo.length" class="banking-card mb-6">
      <VCardText class="banking-content">
        <div class="section-header">
          <VIcon icon="tabler-building-bank" size="24" class="section-icon" />
          <h5 class="section-title">{{ t('banking_information').toUpperCase() }}</h5>
        </div>

        <div class="info-list">
          <div
            v-for="item in bankingInfo"
            :key="item.property"
            class="info-item"
          >
            <div class="info-icon">
              <VIcon
                :icon="item.icon"
                size="20"
              />
            </div>
            <div class="info-content">
              <span class="info-label">{{ item.property }}</span>
              <span class="info-value">{{ item.value }}</span>
            </div>
          </div>
        </div>
      </VCardText>
    </VCard>

    <!-- Roles Information -->
    <VCard v-if="rolesInfo.length" class="roles-card">
      <VCardText class="roles-content">
        <div class="section-header">
          <VIcon icon="tabler-shield" size="24" class="section-icon" />
          <h5 class="section-title">{{ t('roles_permissions').toUpperCase() }}</h5>
        </div>

        <div class="info-list">
          <div
            v-for="item in rolesInfo"
            :key="item.property"
            class="info-item"
          >
            <div class="info-icon">
              <VIcon
                :icon="item.icon"
                size="20"
              />
            </div>
            <div class="info-content">
              <span class="info-label">{{ item.property }}</span>
              <VChip
                color="primary"
                size="small"
                variant="tonal"
                class="info-chip"
              >
                {{ item.value }}
              </VChip>
            </div>
          </div>
        </div>
      </VCardText>
    </VCard>
  </div>
</template>

<style lang="scss" scoped>
.profile-about {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.form-section {
  background: rgba(var(--v-theme-surface-variant), 0.1);
  border-radius: 16px;
  padding: 1.5rem;
  border: 1px solid rgba(var(--v-theme-outline), 0.06);
  transition: all 0.3s ease;
  
  &:hover {
    background: rgba(var(--v-theme-surface-variant), 0.15);
    border-color: rgba(var(--v-theme-outline), 0.12);
  }
  
  &.readonly-section {
    background: rgba(var(--v-theme-surface-variant), 0.05);
    border-style: dashed;
    
    &:hover {
      background: rgba(var(--v-theme-surface-variant), 0.08);
    }
  }
}.about-content,
.account-content,
.banking-content,
.roles-content {
  padding: 1.5rem;
}

.section-header {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 1.5rem;
  padding-bottom: 0.75rem;
  border-bottom: 1px solid rgba(var(--v-theme-outline), 0.08);
}

.section-icon {
  padding: 0.5rem;
  background: rgba(var(--v-theme-primary), 0.1);
  border-radius: 8px;
  color: rgb(var(--v-theme-primary));
}

.section-title {
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.1em;
  color: rgba(var(--v-theme-on-surface), 0.6);
  margin: 0;
}

.info-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.info-item {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1rem;
  background: rgba(var(--v-theme-surface-variant), 0.08);
  border-radius: 12px;
  border: 1px solid rgba(var(--v-theme-outline), 0.06);
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  
  &:hover {
    background: rgba(var(--v-theme-surface-variant), 0.12);
    border-color: rgba(var(--v-theme-outline), 0.12);
    transform: translateX(4px);
  }
}

.info-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  background: rgba(var(--v-theme-primary), 0.1);
  border-radius: 10px;
  color: rgba(var(--v-theme-on-surface), 0.6);
  flex-shrink: 0;
  transition: all 0.3s ease;
  
  &--success {
    background: rgba(var(--v-theme-success), 0.1);
    color: rgb(var(--v-theme-success));
  }
  
  &--warning {
    background: rgba(var(--v-theme-warning), 0.1);
    color: rgb(var(--v-theme-warning));
  }
  
  &--error {
    background: rgba(var(--v-theme-error), 0.1);
    color: rgb(var(--v-theme-error));
  }
  
  &--info {
    background: rgba(var(--v-theme-info), 0.1);
    color: rgb(var(--v-theme-info));
  }
}

.info-content {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  flex: 1;
  min-width: 0;
}

.info-label {
  font-size: 0.75rem;
  font-weight: 500;
  color: rgba(var(--v-theme-on-surface), 0.6);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.info-value {
  font-size: 0.875rem;
  font-weight: 500;
  color: rgb(var(--v-theme-on-surface));
  word-break: break-word;
}

.info-chip {
  align-self: flex-start;
  border-radius: 8px;
  font-weight: 500;
  font-size: 0.75rem;
  letter-spacing: 0.025em;
}

// Special styling for different card types
.about-card,
.account-card,
.banking-card,
.roles-card {
  background: white;
  border-radius: 20px;
  border: 1px solid rgba(var(--v-theme-outline), 0.08);
  box-shadow: 0 8px 32px rgba(var(--v-theme-on-surface), 0.06);
  padding-top: 1rem;
}

.account-card {
  border-left: 4px solid rgb(var(--v-theme-primary));
}

.banking-card {
  border-left: 4px solid rgb(var(--v-theme-success));
  
  .section-icon {
    background: rgba(var(--v-theme-success), 0.1);
    color: rgb(var(--v-theme-success));
  }
}

.roles-card {
  border-left: 4px solid rgb(var(--v-theme-secondary));
  
  .section-icon {
    background: rgba(var(--v-theme-secondary), 0.1);
    color: rgb(var(--v-theme-secondary));
  }
}

// Responsive Design
@media (max-width: 768px) {
  .profile-about {
    gap: 1rem;
  }
  
  .about-content,
  .account-content,
  .banking-content,
  .roles-content {
    padding: 1rem;
  }
  
  .section-header {
    margin-bottom: 1rem;
  }
  
  .info-item {
    padding: 0.75rem;
    flex-direction: column;
    align-items: flex-start;
    gap: 0.75rem;
    
    &:hover {
      transform: none;
    }
  }
  
  .info-icon {
    width: 36px;
    height: 36px;
  }
  
  .info-content {
    width: 100%;
  }
}

@media (max-width: 480px) {
  .about-content,
  .account-content,
  .banking-content,
  .roles-content {
    padding: 0.75rem;
  }
  
  .section-header {
    gap: 0.5rem;
  }
  
  .section-icon {
    padding: 0.375rem;
  }
  
  .section-title {
    font-size: 0.7rem;
  }
  
  .info-item {
    padding: 0.5rem;
  }
  
  .info-icon {
    width: 32px;
    height: 32px;
  }
}

// Dark mode adjustments
@media (prefers-color-scheme: dark) {
  .about-card,
  .account-card,
  .banking-card,
  .roles-card {
    background: white;
    border-color: rgba(var(--v-theme-outline), 0.12);
  }
  
  .info-item {
    background: rgba(var(--v-theme-surface-variant), 0.1);
    border-color: rgba(var(--v-theme-outline), 0.08);
    
    &:hover {
      background: rgba(var(--v-theme-surface-variant), 0.2);
      border-color: rgba(var(--v-theme-outline), 0.16);
    }
  }
}

// Animation for loading states
.info-item {
  animation: fadeInUp 0.3s ease-out;
}

@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

// Stagger animation for multiple items
.info-item:nth-child(1) { animation-delay: 0.1s; }
.info-item:nth-child(2) { animation-delay: 0.2s; }
.info-item:nth-child(3) { animation-delay: 0.3s; }
.info-item:nth-child(4) { animation-delay: 0.4s; }
.info-item:nth-child(5) { animation-delay: 0.5s; }
</style>
