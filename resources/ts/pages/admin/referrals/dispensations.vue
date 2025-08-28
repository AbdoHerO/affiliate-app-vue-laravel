<script setup lang="ts">
import { ref, onMounted, computed, onBeforeUnmount } from 'vue'
import { useI18n } from 'vue-i18n'
import axios from '@/plugins/axios'

definePage({
  meta: {
    requiresAuth: true,
    requiresRole: 'admin',
  },
})

const { t } = useI18n()

// State (removed unused dispensations state since we only show affiliates now)

const showCreateDialog = ref(false)
const createForm = ref({
  affiliate_id: '',
  points: '',
  comment: '',
  reference: '',
})

const affiliates = ref([])
const affiliateOptions = ref([])
const loadingAffiliates = ref(false)

// History Modal
const showHistoryModal = ref(false)
const selectedAffiliate = ref(null)
const historyData = ref([])
const historyLoading = ref(false)
const historyPagination = ref({
  current_page: 1,
  last_page: 1,
  per_page: 10,
  total: 0,
})

// State management
let abortController: AbortController | null = null

// Headers for the unified affiliates table
const headers = computed(() => [
  { title: t('affiliate'), key: 'affiliate', sortable: false },
  { title: t('available_points'), key: 'profil_affilie.points', sortable: true },
  { title: t('total_signups'), key: 'total_signups', sortable: true },
  { title: t('verified_signups'), key: 'verified_signups', sortable: true },
  { title: t('actions'), key: 'actions', sortable: false },
])

// Methods

const fetchAffiliates = async () => {
  loadingAffiliates.value = true
  try {
    const response = await axios.get('/admin/rewards', {
      params: { per_page: 100 }
    })

    if (response.data.success) {
      // Store full affiliate data for the table
      affiliates.value = response.data.data

      // Also create dropdown options for the create form
      affiliateOptions.value = response.data.data.map((affiliate: any) => ({
        value: affiliate.profil_affilie?.id,
        title: `${affiliate.nom_complet} (${affiliate.email})`,
      }))
    }
  } catch (error) {
    console.error('Failed to fetch affiliates:', error)
  } finally {
    loadingAffiliates.value = false
  }
}



const openCreateDialog = (affiliate: any = null) => {
  createForm.value = {
    affiliate_id: affiliate?.profil_affilie?.id || '',
    points: '100', // Default points
    comment: affiliate ? `Récompense pour ${affiliate.nom_complet}` : '',
    reference: `REWARD-${Date.now()}`,
  }
  showCreateDialog.value = true
}

const openHistoryModal = async (affiliate: any) => {
  selectedAffiliate.value = affiliate
  showHistoryModal.value = true
  await fetchAffiliateHistory(affiliate.profil_affilie?.id)
}

const fetchAffiliateHistory = async (affiliateId: string, page = 1) => {
  historyLoading.value = true
  try {
    const response = await axios.get(`/admin/rewards/${affiliateId}/history`, {
      params: {
        page,
        per_page: historyPagination.value.per_page,
      }
    })

    if (response.data.success) {
      historyData.value = response.data.data.rewards
      historyPagination.value = response.data.data.pagination
    }
  } catch (error) {
    console.error('Failed to fetch affiliate history:', error)
  } finally {
    historyLoading.value = false
  }
}

const createReward = async () => {
  try {
    const response = await axios.post('/admin/rewards', createForm.value)

    if (response.data.success) {
      showCreateDialog.value = false
      fetchAffiliates() // Refresh affiliates list to show updated points
      // Show success message
      console.log('Reward created successfully:', response.data.data)
    }
  } catch (error) {
    console.error('Failed to create reward:', error)
    // Show error message
  }
}

const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleDateString()
}

const formatPoints = (points: number) => {
  return `${points} pts`
}

// Lifecycle
onMounted(() => {
  fetchAffiliates()
})
</script>

<template>
  <div>
    <!-- Page Header -->
    <div class="d-flex justify-space-between align-center mb-6">
      <div>
        <h1 class="text-h4 font-weight-bold mb-1">
          {{ t('rewards_management') }}
        </h1>
        <p class="text-body-1 mb-0">
          {{ t('rewards_subtitle') }}
        </p>
      </div>
    </div>

    <!-- Unified Affiliates Table -->
    <VCard>
      <VCardTitle>{{ t('affiliates_rewards_management') }}</VCardTitle>
      <VCardText>
        <VDataTable
          :headers="headers"
          :items="affiliates"
          :loading="loadingAffiliates"
          item-value="id"
          class="text-no-wrap"
        >
          <template #item.affiliate="{ item }">
            <div class="d-flex align-center">
              <VAvatar size="32" class="me-3" color="primary" variant="tonal">
                <VIcon icon="tabler-user-star" />
              </VAvatar>
              <div>
                <div class="font-weight-medium">{{ item.nom_complet }}</div>
                <div class="text-body-2 text-medium-emphasis">{{ item.email }}</div>
              </div>
            </div>
          </template>

          <template #item.profil_affilie.points="{ item }">
            <VChip color="primary" size="small">
              {{ item.profil_affilie?.points || 0 }} pts
            </VChip>
          </template>

          <template #item.total_signups="{ item }">
            <VChip color="info" size="small" variant="outlined">
              {{ item.total_signups || 0 }}
            </VChip>
          </template>

          <template #item.verified_signups="{ item }">
            <VChip color="success" size="small" variant="outlined">
              {{ item.verified_signups || 0 }}
            </VChip>
          </template>

          <template #item.actions="{ item }">
            <div class="d-flex gap-2">
              <VBtn
                color="primary"
                size="small"
                :disabled="!item.profil_affilie?.points || item.profil_affilie.points === 0"
                @click="openCreateDialog(item)"
              >
                <VIcon start icon="tabler-gift" />
                {{ t('create_reward') }}
              </VBtn>

              <VBtn
                color="info"
                variant="outlined"
                size="small"
                @click="openHistoryModal(item)"
              >
                <VIcon start icon="tabler-history" />
                {{ t('rewards_history') }}
              </VBtn>
            </div>
          </template>

          <template #no-data>
            <div class="text-center py-8">
              <VIcon icon="tabler-users-off" size="64" class="mb-4" color="disabled" />
              <h6 class="text-h6 mb-2">{{ t('no_affiliates') }}</h6>
              <p class="text-body-2">{{ t('no_affiliates_description') }}</p>
            </div>
          </template>
        </VDataTable>
      </VCardText>
    </VCard>



    <!-- Create Dispensation Dialog -->
    <VDialog
      v-model="showCreateDialog"
      max-width="600"
    >
      <VCard>
        <VCardTitle>{{ t('create_reward') }}</VCardTitle>
        <VCardText>
          <VForm @submit.prevent="createReward">
            <VRow>
              <VCol cols="12">
                <VSelect
                  v-model="createForm.affiliate_id"
                  :items="affiliateOptions"
                  :label="t('affiliate')"
                  :rules="[v => !!v || t('affiliate_required')]"
                  required
                />
              </VCol>

              <VCol cols="12">
                <VTextField
                  v-model="createForm.points"
                  type="number"
                  :label="t('points')"
                  :rules="[
                    v => !!v || t('points_required'),
                    v => v > 0 || t('points_must_be_positive'),
                    v => v <= 10000 || t('points_max_limit')
                  ]"
                  required
                />
              </VCol>

              <VCol cols="12">
                <VTextarea
                  v-model="createForm.comment"
                  :label="t('comment')"
                  :rules="[
                    v => !!v || t('comment_required'),
                    v => v.length >= 10 || t('comment_min_length')
                  ]"
                  rows="3"
                  required
                />
              </VCol>

              <VCol cols="12">
                <VTextField
                  v-model="createForm.reference"
                  :label="t('reference_optional')"
                  :placeholder="t('campaign_code_or_reference')"
                />
              </VCol>
            </VRow>
          </VForm>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="outlined"
            @click="showCreateDialog = false"
          >
            {{ t('cancel') }}
          </VBtn>
          <VBtn
            color="primary"
            @click="createReward"
          >
            {{ t('create') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- History Modal -->
    <VDialog
      v-model="showHistoryModal"
      max-width="900"
    >
      <VCard>
        <VCardTitle>
          <div class="d-flex align-center">
            <VIcon icon="tabler-history" class="me-2" />
            {{ t('rewards_history') }}
            <span v-if="selectedAffiliate" class="text-body-1 ms-2">
              - {{ selectedAffiliate.nom_complet }}
            </span>
          </div>
        </VCardTitle>
        <VCardText>
          <VDataTable
            :headers="[
              { title: t('date'), key: 'created_at' },
              { title: t('points'), key: 'points' },
              { title: t('comment'), key: 'comment' },
              { title: t('reference'), key: 'reference' },
              { title: t('admin'), key: 'created_by_admin' },
            ]"
            :items="historyData"
            :loading="historyLoading"
            item-value="id"
            class="text-no-wrap"
          >
            <template #item.created_at="{ item }">
              {{ formatDate(item.created_at) }}
            </template>

            <template #item.points="{ item }">
              <VChip color="warning" size="small">
                {{ formatPoints(item.points) }}
              </VChip>
            </template>

            <template #item.comment="{ item }">
              <span class="text-truncate" style="max-width: 200px;">
                {{ item.comment }}
              </span>
            </template>

            <template #item.reference="{ item }">
              <VChip
                v-if="item.reference"
                color="info"
                size="small"
                variant="outlined"
              >
                {{ item.reference }}
              </VChip>
              <span v-else class="text-medium-emphasis">-</span>
            </template>

            <template #item.created_by_admin="{ item }">
              <div class="d-flex align-center">
                <VAvatar size="24" class="me-2" color="secondary" variant="tonal">
                  <VIcon icon="tabler-shield" size="16" />
                </VAvatar>
                <span class="text-body-2">{{ item.created_by_admin?.nom_complet || 'System' }}</span>
              </div>
            </template>

            <template #no-data>
              <div class="text-center py-8">
                <VIcon icon="tabler-history-off" size="64" class="mb-4" color="disabled" />
                <h6 class="text-h6 mb-2">{{ t('no_rewards_history') }}</h6>
                <p class="text-body-2">{{ t('no_rewards_history_description') }}</p>
              </div>
            </template>
          </VDataTable>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="outlined"
            @click="showHistoryModal = false"
          >
            {{ t('close') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
