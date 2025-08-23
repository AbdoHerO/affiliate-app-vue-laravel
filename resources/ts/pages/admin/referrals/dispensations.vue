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

// State
const loading = ref(false)
const dispensations = ref([])
const pagination = ref({
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 0,
})

const filters = ref({
  search: '',
  affiliate_id: '',
  start_date: '',
  end_date: '',
  min_points: '',
  max_points: '',
})

const showCreateDialog = ref(false)
const createForm = ref({
  affiliate_id: '',
  points: '',
  comment: '',
  reference: '',
})

const affiliates = ref([])

// State management
let abortController: AbortController | null = null

// Headers for the data table
const headers = computed(() => [
  { title: t('affiliate'), key: 'referrer_affiliate', sortable: false },
  { title: t('points'), key: 'points', sortable: true },
  { title: t('comment'), key: 'comment', sortable: false },
  { title: t('reference'), key: 'reference', sortable: false },
  { title: t('created_by'), key: 'created_by_admin', sortable: false },
  { title: t('date'), key: 'created_at', sortable: true },
  { title: t('actions'), key: 'actions', sortable: false },
])

// Methods
const fetchDispensations = async (page = 1) => {
  loading.value = true
  try {
    const params = {
      page,
      per_page: pagination.value.per_page,
      ...filters.value,
    }

    // Remove empty filters
    Object.keys(params).forEach(key => {
      if (params[key] === '' || params[key] === null) {
        delete params[key]
      }
    })

    const response = await axios.get('/admin/referrals/dispensations', { params })

    if (response.data.success) {
      dispensations.value = response.data.data
      pagination.value = response.data.pagination
    }
  } catch (error) {
    console.error('Failed to fetch dispensations:', error)
  } finally {
    loading.value = false
  }
}

const fetchAffiliates = async () => {
  try {
    const response = await axios.get('/admin/affiliates', {
      params: { per_page: 100 }
    })

    if (response.data.success) {
      affiliates.value = response.data.data.map(affiliate => ({
        value: affiliate.id,
        title: `${affiliate.nom_complet} (${affiliate.email})`,
      }))
    }
  } catch (error) {
    console.error('Failed to fetch affiliates:', error)
  }
}

const onSearch = () => {
  pagination.value.current_page = 1
  fetchDispensations()
}

const clearFilters = () => {
  filters.value = {
    search: '',
    affiliate_id: '',
    start_date: '',
    end_date: '',
    min_points: '',
    max_points: '',
  }
  fetchDispensations()
}

const openCreateDialog = () => {
  createForm.value = {
    affiliate_id: '',
    points: '',
    comment: '',
    reference: '',
  }
  showCreateDialog.value = true
}

const createDispensation = async () => {
  try {
    const response = await axios.post('/admin/referrals/dispensations', createForm.value)

    if (response.data.success) {
      showCreateDialog.value = false
      fetchDispensations()
      // Show success message
    }
  } catch (error) {
    console.error('Failed to create dispensation:', error)
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
  fetchDispensations()
  fetchAffiliates()
})
</script>

<template>
  <div>
    <!-- Page Header -->
    <div class="d-flex justify-space-between align-center mb-6">
      <div>
        <h1 class="text-h4 font-weight-bold mb-1">
          {{ t('dispensations_management') }}
        </h1>
        <p class="text-body-1 mb-0">
          {{ t('dispensations_subtitle') }}
        </p>
      </div>

      <VBtn
        color="primary"
        @click="openCreateDialog"
      >
        <VIcon start icon="tabler-plus" />
        {{ t('create_dispensation') }}
      </VBtn>
    </div>

    <!-- Filters -->
    <VCard class="mb-6">
      <VCardTitle>{{ t('filters') }}</VCardTitle>
      <VCardText>
        <VRow>
          <VCol cols="12" md="3">
            <VTextField
              v-model="filters.search"
              :label="t('search')"
              :placeholder="t('search_comment_reference')"
              prepend-inner-icon="tabler-search"
              clearable
              @keyup.enter="onSearch"
            />
          </VCol>

          <VCol cols="12" md="3">
            <VSelect
              v-model="filters.affiliate_id"
              :items="affiliates"
              :label="t('affiliate')"
              clearable
              @update:model-value="onSearch"
            />
          </VCol>

          <VCol cols="12" md="2">
            <VTextField
              v-model="filters.min_points"
              type="number"
              :label="t('min_points')"
              @change="onSearch"
            />
          </VCol>

          <VCol cols="12" md="2">
            <VTextField
              v-model="filters.max_points"
              type="number"
              :label="t('max_points')"
              @change="onSearch"
            />
          </VCol>

          <VCol cols="12" md="2">
            <div class="d-flex gap-2">
              <VBtn
                color="primary"
                @click="onSearch"
              >
                {{ t('search') }}
              </VBtn>
              <VBtn
                variant="outlined"
                @click="clearFilters"
              >
                {{ t('clear') }}
              </VBtn>
            </div>
          </VCol>
        </VRow>

        <!-- Date Range -->
        <VRow class="mt-2">
          <VCol cols="12" md="3">
            <VTextField
              v-model="filters.start_date"
              type="date"
              :label="t('start_date')"
              @change="onSearch"
            />
          </VCol>
          <VCol cols="12" md="3">
            <VTextField
              v-model="filters.end_date"
              type="date"
              :label="t('end_date')"
              @change="onSearch"
            />
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Data Table -->
    <VCard>
      <VCardText>
        <VDataTableServer
          v-model:page="pagination.current_page"
          :headers="headers"
          :items="dispensations"
          :items-length="pagination.total"
          :items-per-page="pagination.per_page"
          :loading="loading"
          class="text-no-wrap"
          @update:page="fetchDispensations"
        >
          <template #item.referrer_affiliate="{ item }">
            <div class="d-flex align-center">
              <VAvatar size="32" class="me-3" color="primary" variant="tonal">
                <VIcon icon="tabler-user-star" />
              </VAvatar>
              <div>
                <div class="font-weight-medium">{{ item.referrer_affiliate.utilisateur.nom_complet }}</div>
                <div class="text-body-2 text-medium-emphasis">{{ item.referrer_affiliate.utilisateur.email }}</div>
              </div>
            </div>
          </template>

          <template #item.points="{ item }">
            <VChip
              color="success"
              size="small"
            >
              {{ formatPoints(item.points) }}
            </VChip>
          </template>

          <template #item.comment="{ item }">
            <div class="text-truncate" style="max-width: 200px;">
              {{ item.comment }}
            </div>
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
              <span class="text-body-2">{{ item.created_by_admin.nom_complet }}</span>
            </div>
          </template>

          <template #item.created_at="{ item }">
            {{ formatDate(item.created_at) }}
          </template>

          <template #item.actions="{ item }">
            <VBtn
              icon="tabler-eye"
              size="small"
              variant="text"
              @click="() => $router.push({ name: 'admin-referrals-dispensation-view', params: { id: item.id } })"
            />
          </template>

          <template #no-data>
            <div class="text-center py-8">
              <VIcon icon="tabler-gift-off" size="64" class="mb-4" color="disabled" />
              <h6 class="text-h6 mb-2">{{ t('no_dispensations') }}</h6>
              <p class="text-body-2">{{ t('no_dispensations_description') }}</p>
            </div>
          </template>
        </VDataTableServer>
      </VCardText>
    </VCard>

    <!-- Create Dispensation Dialog -->
    <VDialog
      v-model="showCreateDialog"
      max-width="600"
    >
      <VCard>
        <VCardTitle>{{ t('create_dispensation') }}</VCardTitle>
        <VCardText>
          <VForm @submit.prevent="createDispensation">
            <VRow>
              <VCol cols="12">
                <VSelect
                  v-model="createForm.affiliate_id"
                  :items="affiliates"
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
            @click="createDispensation"
          >
            {{ t('create') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
