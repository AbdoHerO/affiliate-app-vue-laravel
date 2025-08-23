<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import axios from '@/plugins/axios'

definePage({
  meta: {
    requiresAuth: true,
    requiresRole: 'admin',
    breadcrumb: [
      { title: 'Dashboard', to: { name: 'admin-dashboard' } },
      { title: 'Referrals', to: { name: 'admin-referrals-dashboard' } },
      { title: 'Referred Users', to: { name: 'admin-referrals-referred-users' } },
      { title: 'View User', disabled: true },
    ],
  },
})

const { t } = useI18n()
const route = useRoute()
const router = useRouter()

// Data
const loading = ref(false)
const user = ref(null)

// Methods
const fetchUser = async () => {
  loading.value = true
  try {
    // For now, we'll fetch from the referred users list and find the specific one
    // In a real app, you'd have a dedicated endpoint for single user details
    const response = await axios.get('/admin/referrals/referred-users', {
      params: { per_page: 1000 } // Get all to find the specific one
    })
    
    if (response.data.success) {
      const foundUser = response.data.data.find(u => u.id === route.params.id)
      if (foundUser) {
        user.value = foundUser
      } else {
        router.push({ name: 'admin-referrals-referred-users' })
      }
    }
  } catch (error) {
    console.error('Failed to fetch user:', error)
    router.push({ name: 'admin-referrals-referred-users' })
  } finally {
    loading.value = false
  }
}

const goBack = () => {
  router.push({ name: 'admin-referrals-referred-users' })
}

const getVerificationStatus = (verified) => {
  return verified ? 'verified' : 'pending_verification'
}

const getVerificationColor = (verified) => {
  return verified ? 'success' : 'warning'
}

// Lifecycle
onMounted(() => {
  fetchUser()
})
</script>

<template>
  <div>
    <!-- Header -->
    <div class="d-flex justify-space-between align-center mb-6">
      <div>
        <h4 class="text-h4 mb-1">
          {{ t('referred_user_details') }}
        </h4>
        <p class="text-body-1 mb-0">
          {{ t('view_referred_user_details') }}
        </p>
      </div>
      
      <VBtn
        variant="outlined"
        @click="goBack"
      >
        <VIcon start icon="tabler-arrow-left" />
        {{ t('back') }}
      </VBtn>
    </div>

    <!-- Loading -->
    <VCard v-if="loading">
      <VCardText class="text-center py-8">
        <VProgressCircular indeterminate />
        <p class="mt-4 mb-0">{{ t('loading') }}...</p>
      </VCardText>
    </VCard>

    <!-- User Details -->
    <VCard v-else-if="user">
      <VCardText>
        <VRow>
          <VCol cols="12" md="6">
            <h6 class="text-h6 mb-4">{{ t('user_information') }}</h6>
            
            <div class="mb-4">
              <span class="text-sm text-medium-emphasis">{{ t('name') }}:</span>
              <p class="text-body-1 mb-0">{{ user.new_user?.nom_complet || 'N/A' }}</p>
            </div>
            
            <div class="mb-4">
              <span class="text-sm text-medium-emphasis">{{ t('email') }}:</span>
              <p class="text-body-1 mb-0">{{ user.new_user?.email || 'N/A' }}</p>
            </div>
            
            <div class="mb-4">
              <span class="text-sm text-medium-emphasis">{{ t('verification_status') }}:</span>
              <VChip
                :color="getVerificationColor(user.verified)"
                size="small"
                class="mt-1"
              >
                {{ t(getVerificationStatus(user.verified)) }}
              </VChip>
            </div>
            
            <div class="mb-4">
              <span class="text-sm text-medium-emphasis">{{ t('signup_date') }}:</span>
              <p class="text-body-1 mb-0">{{ new Date(user.attributed_at).toLocaleString() }}</p>
            </div>
            
            <div class="mb-4" v-if="user.verified_at">
              <span class="text-sm text-medium-emphasis">{{ t('verified_at') }}:</span>
              <p class="text-body-1 mb-0">{{ new Date(user.verified_at).toLocaleString() }}</p>
            </div>
          </VCol>
          
          <VCol cols="12" md="6">
            <h6 class="text-h6 mb-4">{{ t('referral_information') }}</h6>
            
            <div class="mb-4">
              <span class="text-sm text-medium-emphasis">{{ t('referral_code') }}:</span>
              <p class="text-body-1 mb-0">{{ user.referral_code }}</p>
            </div>
            
            <div class="mb-4" v-if="user.referrer_affiliate">
              <span class="text-sm text-medium-emphasis">{{ t('referred_by') }}:</span>
              <p class="text-body-1 mb-0">{{ user.referrer_affiliate.utilisateur?.nom_complet || 'N/A' }}</p>
            </div>
            
            <div class="mb-4" v-if="user.referrer_affiliate">
              <span class="text-sm text-medium-emphasis">{{ t('affiliate_email') }}:</span>
              <p class="text-body-1 mb-0">{{ user.referrer_affiliate.utilisateur?.email || 'N/A' }}</p>
            </div>
            
            <div class="mb-4">
              <span class="text-sm text-medium-emphasis">{{ t('source') }}:</span>
              <p class="text-body-1 mb-0">{{ user.source || 'web' }}</p>
            </div>
            
            <div class="mb-4">
              <span class="text-sm text-medium-emphasis">{{ t('ip_hash') }}:</span>
              <p class="text-body-1 mb-0 font-mono">{{ user.ip_hash?.substring(0, 16) }}...</p>
            </div>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Not Found -->
    <VCard v-else>
      <VCardText class="text-center py-8">
        <VIcon size="64" color="error" class="mb-4">tabler-alert-circle</VIcon>
        <h6 class="text-h6 mb-2">{{ t('user_not_found') }}</h6>
        <p class="text-body-1 mb-4">{{ t('user_not_found_message') }}</p>
        <VBtn @click="goBack">{{ t('back_to_users') }}</VBtn>
      </VCardText>
    </VCard>
  </div>
</template>
