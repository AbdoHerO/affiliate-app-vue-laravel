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
      { title: 'Dispensations', to: { name: 'admin-referrals-dispensations' } },
      { title: 'View Dispensation', disabled: true },
    ],
  },
})

const { t } = useI18n()
const route = useRoute()
const router = useRouter()

// Data
const loading = ref(false)
const dispensation = ref(null)

// Methods
const fetchDispensation = async () => {
  loading.value = true
  try {
    const response = await axios.get(`/admin/referrals/dispensations/${route.params.id}`)
    if (response.data.success) {
      dispensation.value = response.data.data
    }
  } catch (error) {
    console.error('Failed to fetch dispensation:', error)
    // Redirect back if not found
    if (error.response?.status === 404) {
      router.push({ name: 'admin-referrals-dispensations' })
    }
  } finally {
    loading.value = false
  }
}

const goBack = () => {
  router.push({ name: 'admin-referrals-dispensations' })
}

// Lifecycle
onMounted(() => {
  fetchDispensation()
})
</script>

<template>
  <div>
    <!-- Header -->
    <div class="d-flex justify-space-between align-center mb-6">
      <div>
        <h4 class="text-h4 mb-1">
          {{ t('dispensation_details') }}
        </h4>
        <p class="text-body-1 mb-0">
          {{ t('view_dispensation_details') }}
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

    <!-- Dispensation Details -->
    <VCard v-else-if="dispensation">
      <VCardText>
        <VRow>
          <VCol cols="12" md="6">
            <h6 class="text-h6 mb-4">{{ t('dispensation_information') }}</h6>
            
            <div class="mb-4">
              <span class="text-sm text-medium-emphasis">{{ t('reference') }}:</span>
              <p class="text-body-1 mb-0">{{ dispensation.reference }}</p>
            </div>
            
            <div class="mb-4">
              <span class="text-sm text-medium-emphasis">{{ t('points') }}:</span>
              <p class="text-h6 mb-0 text-primary">{{ dispensation.points }}</p>
            </div>
            
            <div class="mb-4">
              <span class="text-sm text-medium-emphasis">{{ t('comment') }}:</span>
              <p class="text-body-1 mb-0">{{ dispensation.comment }}</p>
            </div>
            
            <div class="mb-4">
              <span class="text-sm text-medium-emphasis">{{ t('created_at') }}:</span>
              <p class="text-body-1 mb-0">{{ new Date(dispensation.created_at).toLocaleString() }}</p>
            </div>
          </VCol>
          
          <VCol cols="12" md="6">
            <h6 class="text-h6 mb-4">{{ t('affiliate_information') }}</h6>
            
            <div class="mb-4" v-if="dispensation.referrer_affiliate">
              <span class="text-sm text-medium-emphasis">{{ t('affiliate_name') }}:</span>
              <p class="text-body-1 mb-0">{{ dispensation.referrer_affiliate.utilisateur?.nom_complet || 'N/A' }}</p>
            </div>
            
            <div class="mb-4" v-if="dispensation.referrer_affiliate">
              <span class="text-sm text-medium-emphasis">{{ t('affiliate_email') }}:</span>
              <p class="text-body-1 mb-0">{{ dispensation.referrer_affiliate.utilisateur?.email || 'N/A' }}</p>
            </div>
            
            <div class="mb-4" v-if="dispensation.created_by_admin">
              <span class="text-sm text-medium-emphasis">{{ t('created_by') }}:</span>
              <p class="text-body-1 mb-0">{{ dispensation.created_by_admin.nom_complet || 'N/A' }}</p>
            </div>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Not Found -->
    <VCard v-else>
      <VCardText class="text-center py-8">
        <VIcon size="64" color="error" class="mb-4">tabler-alert-circle</VIcon>
        <h6 class="text-h6 mb-2">{{ t('dispensation_not_found') }}</h6>
        <p class="text-body-1 mb-4">{{ t('dispensation_not_found_message') }}</p>
        <VBtn @click="goBack">{{ t('back_to_dispensations') }}</VBtn>
      </VCardText>
    </VCard>
  </div>
</template>
