<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import axios from '@/plugins/axios'

definePage({
  meta: {
    requiresAuth: true,
    requiresRole: 'admin',
    breadcrumb: [
      { title: 'Dashboard', to: { name: 'admin-dashboard' } },
      { title: 'Referrers Management', disabled: true },
    ],
  },
})

const { t } = useI18n()

// State
const loading = ref(false)
const referrers = ref([])
const statistics = ref({})

// Dispensation Modal
const showDispensationModal = ref(false)
const selectedAffiliate = ref(null)
const dispensationForm = ref({
  affiliate_id: '',
  points: '',
  comment: '',
  reference: '',
})
const dispensationLoading = ref(false)

// History Modal
const showHistoryModal = ref(false)
const historyData = ref({
  dispensations: [],
  pagination: {},
  summary: {},
})
const historyLoading = ref(false)

// Headers for the referrers table
const headers = computed(() => [
  { title: t('affiliate'), key: 'user', sortable: false },
  { title: t('verified_signups'), key: 'verified_signups', sortable: true },
  { title: t('total_signups'), key: 'total_signups', sortable: true },
  { title: t('points_earned'), key: 'points_earned', sortable: true },
  { title: t('points_dispensed'), key: 'points_dispensed', sortable: true },
  { title: t('points_balance'), key: 'points_balance', sortable: true },
  { title: t('actions'), key: 'actions', sortable: false },
])

// Methods
const fetchReferrers = async () => {
  loading.value = true
  try {
    const response = await axios.get('/admin/referrers')
    if (response.data.success) {
      referrers.value = response.data.data
    }
  } catch (error) {
    console.error('Failed to fetch referrers:', error)
  } finally {
    loading.value = false
  }
}

const fetchStatistics = async () => {
  try {
    const response = await axios.get('/admin/referrers/statistics')
    if (response.data.success) {
      statistics.value = response.data.data
    }
  } catch (error) {
    console.error('Failed to fetch statistics:', error)
  }
}

const openDispensationModal = (affiliate) => {
  selectedAffiliate.value = affiliate
  dispensationForm.value = {
    affiliate_id: affiliate.id,
    points: affiliate.points_balance.toString(), // Default to full balance
    comment: `Points dispensation for ${affiliate.user.nom_complet}`,
    reference: `REF-${Date.now()}`,
  }
  showDispensationModal.value = true
}

const createDispensation = async () => {
  dispensationLoading.value = true
  try {
    const response = await axios.post('/admin/referrers/dispensations', dispensationForm.value)
    
    if (response.data.success) {
      showDispensationModal.value = false
      
      // Update the affiliate's data in the table
      const affiliateIndex = referrers.value.findIndex(r => r.id === selectedAffiliate.value.id)
      if (affiliateIndex !== -1) {
        const updatedSummary = response.data.data.updated_summary
        referrers.value[affiliateIndex].points_dispensed += dispensationForm.value.points
        referrers.value[affiliateIndex].points_balance = updatedSummary.balance
      }
      
      // Refresh statistics
      fetchStatistics()
      
      // Show success message
      // TODO: Add toast notification
      console.log('Dispensation created successfully')
    }
  } catch (error) {
    console.error('Failed to create dispensation:', error)
    // TODO: Show error message
  } finally {
    dispensationLoading.value = false
  }
}

const openHistoryModal = async (affiliate) => {
  selectedAffiliate.value = affiliate
  showHistoryModal.value = true
  
  historyLoading.value = true
  try {
    const response = await axios.get(`/admin/referrers/${affiliate.id}/dispensations`)
    if (response.data.success) {
      historyData.value = response.data.data
    }
  } catch (error) {
    console.error('Failed to fetch dispensation history:', error)
  } finally {
    historyLoading.value = false
  }
}

const formatCurrency = (amount) => {
  return new Intl.NumberFormat('fr-FR').format(amount)
}

const formatDate = (dateString) => {
  return new Date(dateString).toLocaleDateString('fr-FR', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

const getBalanceColor = (balance) => {
  if (balance === 0) return 'grey'
  if (balance < 100) return 'orange'
  if (balance < 500) return 'blue'
  return 'green'
}

// Lifecycle
onMounted(() => {
  fetchReferrers()
  fetchStatistics()
})
</script>

<template>
  <div>
    <!-- Header -->
    <div class="d-flex justify-space-between align-center mb-6">
      <div>
        <h4 class="text-h4 mb-1">
          {{ t('referrers_management') }}
        </h4>
        <p class="text-body-1 mb-0">
          {{ t('manage_affiliate_points_and_dispensations') }}
        </p>
      </div>
    </div>

    <!-- Statistics Cards -->
    <VRow class="mb-6">
      <VCol cols="12" md="3">
        <VCard>
          <VCardText>
            <div class="d-flex align-center">
              <VIcon color="primary" size="40" class="me-4">tabler-users</VIcon>
              <div>
                <p class="text-sm text-medium-emphasis mb-0">{{ t('total_referrers') }}</p>
                <h6 class="text-h6">{{ statistics.total_referrers || 0 }}</h6>
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
      
      <VCol cols="12" md="3">
        <VCard>
          <VCardText>
            <div class="d-flex align-center">
              <VIcon color="success" size="40" class="me-4">tabler-coins</VIcon>
              <div>
                <p class="text-sm text-medium-emphasis mb-0">{{ t('total_points_earned') }}</p>
                <h6 class="text-h6">{{ formatCurrency(statistics.total_points_earned || 0) }}</h6>
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
      
      <VCol cols="12" md="3">
        <VCard>
          <VCardText>
            <div class="d-flex align-center">
              <VIcon color="warning" size="40" class="me-4">tabler-gift</VIcon>
              <div>
                <p class="text-sm text-medium-emphasis mb-0">{{ t('total_points_dispensed') }}</p>
                <h6 class="text-h6">{{ formatCurrency(statistics.total_points_dispensed || 0) }}</h6>
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
      
      <VCol cols="12" md="3">
        <VCard>
          <VCardText>
            <div class="d-flex align-center">
              <VIcon color="info" size="40" class="me-4">tabler-wallet</VIcon>
              <div>
                <p class="text-sm text-medium-emphasis mb-0">{{ t('total_points_balance') }}</p>
                <h6 class="text-h6">{{ formatCurrency(statistics.total_points_balance || 0) }}</h6>
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Referrers Table -->
    <VCard>
      <VCardTitle>{{ t('referrers_list') }}</VCardTitle>
      <VCardText>
        <VDataTable
          :headers="headers"
          :items="referrers"
          :loading="loading"
          item-value="id"
          class="text-no-wrap"
        >
          <template #item.user="{ item }">
            <div class="d-flex align-center">
              <VAvatar size="32" class="me-3">
                <VImg v-if="item.user.photo_profil" :src="item.user.photo_profil" />
                <span v-else>{{ item.user.nom_complet?.charAt(0) || 'A' }}</span>
              </VAvatar>
              <div>
                <p class="font-weight-medium mb-0">{{ item.user.nom_complet }}</p>
                <p class="text-sm text-medium-emphasis mb-0">{{ item.user.email }}</p>
              </div>
            </div>
          </template>

          <template #item.verified_signups="{ item }">
            <VChip color="success" size="small">
              {{ item.verified_signups }}
            </VChip>
          </template>

          <template #item.total_signups="{ item }">
            <VChip color="info" size="small">
              {{ item.total_signups }}
            </VChip>
          </template>

          <template #item.points_earned="{ item }">
            <span class="font-weight-medium text-success">
              {{ formatCurrency(item.points_earned) }} pts
            </span>
          </template>

          <template #item.points_dispensed="{ item }">
            <span class="font-weight-medium text-warning">
              {{ formatCurrency(item.points_dispensed) }} pts
            </span>
          </template>

          <template #item.points_balance="{ item }">
            <VChip :color="getBalanceColor(item.points_balance)" size="small">
              {{ formatCurrency(item.points_balance) }} pts
            </VChip>
          </template>

          <template #item.actions="{ item }">
            <div class="d-flex gap-2">
              <VBtn
                color="primary"
                size="small"
                :disabled="item.points_balance === 0"
                @click="openDispensationModal(item)"
              >
                <VIcon start icon="tabler-gift" />
                {{ t('dispense') }}
              </VBtn>
              
              <VBtn
                color="info"
                variant="outlined"
                size="small"
                @click="openHistoryModal(item)"
              >
                <VIcon start icon="tabler-history" />
                {{ t('history') }}
              </VBtn>
            </div>
          </template>

          <template #no-data>
            <div class="text-center py-8">
              <VIcon size="64" color="grey" class="mb-4">tabler-users-off</VIcon>
              <h6 class="text-h6 mb-2">{{ t('no_referrers_found') }}</h6>
              <p class="text-body-1">{{ t('no_referrers_found_message') }}</p>
            </div>
          </template>
        </VDataTable>
      </VCardText>
    </VCard>

    <!-- Dispensation Modal -->
    <VDialog
      v-model="showDispensationModal"
      max-width="600"
    >
      <VCard>
        <VCardTitle>{{ t('dispense_points') }}</VCardTitle>
        <VCardText>
          <VForm @submit.prevent="createDispensation">
            <VRow>
              <VCol cols="12">
                <VTextField
                  :model-value="selectedAffiliate?.user?.nom_complet"
                  :label="t('affiliate')"
                  readonly
                  variant="outlined"
                />
              </VCol>

              <VCol cols="12" md="6">
                <VTextField
                  v-model="dispensationForm.points"
                  :label="t('points_to_dispense')"
                  :hint="`${t('max_balance')}: ${selectedAffiliate?.points_balance || 0} pts`"
                  type="number"
                  :min="1"
                  :max="selectedAffiliate?.points_balance || 0"
                  variant="outlined"
                  required
                />
              </VCol>

              <VCol cols="12" md="6">
                <VTextField
                  v-model="dispensationForm.reference"
                  :label="t('reference')"
                  variant="outlined"
                />
              </VCol>

              <VCol cols="12">
                <VTextarea
                  v-model="dispensationForm.comment"
                  :label="t('comment')"
                  :placeholder="t('dispensation_comment_placeholder')"
                  variant="outlined"
                  rows="3"
                  required
                />
              </VCol>
            </VRow>
          </VForm>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="outlined"
            @click="showDispensationModal = false"
          >
            {{ t('cancel') }}
          </VBtn>
          <VBtn
            color="primary"
            :loading="dispensationLoading"
            @click="createDispensation"
          >
            {{ t('dispense_points') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- History Modal -->
    <VDialog
      v-model="showHistoryModal"
      max-width="800"
    >
      <VCard>
        <VCardTitle>
          {{ t('dispensation_history') }} - {{ selectedAffiliate?.user?.nom_complet }}
        </VCardTitle>
        <VCardText>
          <!-- Summary -->
          <VRow class="mb-4">
            <VCol cols="4">
              <VCard variant="outlined">
                <VCardText class="text-center">
                  <p class="text-sm text-medium-emphasis mb-1">{{ t('points_earned') }}</p>
                  <h6 class="text-h6 text-success">{{ formatCurrency(historyData.summary?.earned || 0) }}</h6>
                </VCardText>
              </VCard>
            </VCol>
            <VCol cols="4">
              <VCard variant="outlined">
                <VCardText class="text-center">
                  <p class="text-sm text-medium-emphasis mb-1">{{ t('points_dispensed') }}</p>
                  <h6 class="text-h6 text-warning">{{ formatCurrency(historyData.summary?.dispensed || 0) }}</h6>
                </VCardText>
              </VCard>
            </VCol>
            <VCol cols="4">
              <VCard variant="outlined">
                <VCardText class="text-center">
                  <p class="text-sm text-medium-emphasis mb-1">{{ t('points_balance') }}</p>
                  <h6 class="text-h6 text-info">{{ formatCurrency(historyData.summary?.balance || 0) }}</h6>
                </VCardText>
              </VCard>
            </VCol>
          </VRow>

          <!-- History Table -->
          <VDataTable
            :headers="[
              { title: t('date'), key: 'created_at' },
              { title: t('points'), key: 'points' },
              { title: t('comment'), key: 'comment' },
              { title: t('reference'), key: 'reference' },
              { title: t('admin'), key: 'created_by_admin' },
            ]"
            :items="historyData.dispensations"
            :loading="historyLoading"
            item-value="id"
            class="text-no-wrap"
          >
            <template #item.created_at="{ item }">
              {{ formatDate(item.created_at) }}
            </template>

            <template #item.points="{ item }">
              <VChip color="warning" size="small">
                {{ formatCurrency(item.points) }} pts
              </VChip>
            </template>

            <template #item.comment="{ item }">
              <span class="text-truncate" style="max-width: 200px;">
                {{ item.comment }}
              </span>
            </template>

            <template #item.created_by_admin="{ item }">
              {{ item.created_by_admin?.nom_complet || 'N/A' }}
            </template>

            <template #no-data>
              <div class="text-center py-4">
                <p>{{ t('no_dispensations_found') }}</p>
              </div>
            </template>
          </VDataTable>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn @click="showHistoryModal = false">
            {{ t('close') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
