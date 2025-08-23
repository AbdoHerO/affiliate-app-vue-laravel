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
const dispensationsData = ref({
  dispensations: [],
  total_points: 0,
  pagination: {
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 0,
  },
})

const filters = ref({
  start_date: '',
  end_date: '',
})

// Headers for the data table
const headers = computed(() => [
  { title: t('points'), key: 'points', sortable: true },
  { title: t('comment'), key: 'comment', sortable: false },
  { title: t('reference'), key: 'reference', sortable: false },
  { title: t('date'), key: 'created_at', sortable: true },
])

// Methods
const fetchDispensations = async (page = 1) => {
  loading.value = true
  try {
    const params = {
      page,
      per_page: dispensationsData.value.pagination.per_page,
      ...filters.value,
    }

    // Remove empty filters
    Object.keys(params).forEach(key => {
      if (params[key] === '' || params[key] === null) {
        delete params[key]
      }
    })

    const response = await axios.get('/affiliate/referrals/dispensations', { params })

    if (response.data.success) {
      dispensationsData.value = response.data.data
    }
  } catch (error) {
    console.error('Failed to fetch dispensations:', error)
  } finally {
    loading.value = false
  }
}

const onSearch = () => {
  dispensationsData.value.pagination.current_page = 1
  fetchDispensations()
}

const clearFilters = () => {
  filters.value = {
    start_date: '',
    end_date: '',
  }
  fetchDispensations()
}

const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleDateString()
}

const formatPoints = (points: number) => {
  return `+${points} pts`
}

// Lifecycle
onMounted(() => {
  fetchDispensations()
})
</script>

<template>
  <div>
    <!-- Page Header -->
    <div class="d-flex justify-space-between align-center mb-6">
      <div>
        <h1 class="text-h4 font-weight-bold mb-1">
          {{ t('my_rewards_history') }}
        </h1>
        <p class="text-body-1 mb-0">
          {{ t('rewards_history_subtitle') }}
        </p>
      </div>

      <VBtn
        color="primary"
        to="/affiliate/referrals"
      >
        <VIcon start icon="tabler-arrow-left" />
        {{ t('back_to_dashboard') }}
      </VBtn>
    </div>

    <!-- Total Points Card -->
    <VCard class="mb-6">
      <VCardText class="text-center">
        <VAvatar
          color="success"
          variant="tonal"
          size="64"
          class="mb-4"
        >
          <VIcon icon="tabler-award" size="32" />
        </VAvatar>
        <h2 class="text-h2 font-weight-bold text-success mb-2">
          {{ dispensationsData.total_points }}
        </h2>
        <p class="text-h6 mb-0">
          {{ t('total_points_earned') }}
        </p>
      </VCardText>
    </VCard>

    <!-- Filters -->
    <VCard class="mb-6">
      <VCardTitle>{{ t('filters') }}</VCardTitle>
      <VCardText>
        <VRow>
          <VCol cols="12" md="4">
            <VTextField
              v-model="filters.start_date"
              type="date"
              :label="t('start_date')"
              @change="onSearch"
            />
          </VCol>

          <VCol cols="12" md="4">
            <VTextField
              v-model="filters.end_date"
              type="date"
              :label="t('end_date')"
              @change="onSearch"
            />
          </VCol>

          <VCol cols="12" md="4">
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
      </VCardText>
    </VCard>

    <!-- Data Table -->
    <VCard>
      <VCardTitle class="d-flex align-center">
        <VIcon icon="tabler-gift" class="me-2" />
        {{ t('rewards_history') }}
      </VCardTitle>
      <VCardText>
        <VDataTableServer
          v-model:page="dispensationsData.pagination.current_page"
          :headers="headers"
          :items="dispensationsData.dispensations"
          :items-length="dispensationsData.pagination.total"
          :items-per-page="dispensationsData.pagination.per_page"
          :loading="loading"
          class="text-no-wrap"
          @update:page="fetchDispensations"
        >
          <template #item.points="{ item }">
            <VChip
              color="success"
              size="small"
            >
              {{ formatPoints(item.points) }}
            </VChip>
          </template>

          <template #item.comment="{ item }">
            <div class="text-wrap" style="max-width: 300px;">
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

          <template #item.created_at="{ item }">
            {{ formatDate(item.created_at) }}
          </template>

          <template #no-data>
            <div class="text-center py-8">
              <VIcon icon="tabler-gift-off" size="64" class="mb-4" color="disabled" />
              <h6 class="text-h6 mb-2">{{ t('no_rewards_yet') }}</h6>
              <p class="text-body-2">{{ t('no_rewards_yet_description') }}</p>
            </div>
          </template>
        </VDataTableServer>
      </VCardText>
    </VCard>

    <!-- Info Card -->
    <VCard class="mt-6">
      <VCardTitle>{{ t('about_rewards') }}</VCardTitle>
      <VCardText>
        <VAlert
          type="info"
          variant="tonal"
        >
          {{ t('rewards_explanation') }}
        </VAlert>
        
        <div class="mt-4">
          <h6 class="text-h6 mb-2">{{ t('how_to_earn_more') }}</h6>
          <ul class="text-body-2">
            <li>{{ t('earn_tip_1') }}</li>
            <li>{{ t('earn_tip_2') }}</li>
            <li>{{ t('earn_tip_3') }}</li>
          </ul>
        </div>
      </VCardText>
    </VCard>
  </div>
</template>
