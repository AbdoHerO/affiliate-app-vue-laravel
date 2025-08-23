<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import axios from '@/plugins/axios'

definePage({
  meta: {
    requiresAuth: true,
    requiresRole: 'admin',
  },
})

const { t } = useI18n()
const router = useRouter()

// State
const loading = ref(false)
const referredUsers = ref([])
const pagination = ref({
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 0,
})

const filters = ref({
  search: '',
  referrer_id: '',
  verified: null,
  start_date: '',
  end_date: '',
  source: '',
})

const referrers = ref([])

// Headers for the data table
const headers = computed(() => [
  { title: t('referred_user'), key: 'new_user', sortable: false },
  { title: t('referrer'), key: 'referrer', sortable: false },
  { title: t('signup_date'), key: 'attributed_at', sortable: true },
  { title: t('verification_status'), key: 'verified', sortable: true },
  { title: t('source'), key: 'source', sortable: true },
  { title: t('actions'), key: 'actions', sortable: false },
])

// Methods
const fetchReferredUsers = async (page = 1) => {
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

    const response = await axios.get('/admin/referrals/referred-users', { params })

    if (response.data.success) {
      referredUsers.value = response.data.data
      pagination.value = response.data.pagination
    }
  } catch (error) {
    console.error('Failed to fetch referred users:', error)
  } finally {
    loading.value = false
  }
}

const fetchReferrers = async () => {
  try {
    const response = await axios.get('/admin/affiliates', {
      params: { per_page: 100 }
    })

    if (response.data.success) {
      referrers.value = response.data.data.map(affiliate => ({
        value: affiliate.id,
        title: `${affiliate.nom_complet} (${affiliate.email})`,
      }))
    }
  } catch (error) {
    console.error('Failed to fetch referrers:', error)
  }
}

const onSearch = () => {
  pagination.value.current_page = 1
  fetchReferredUsers()
}

const clearFilters = () => {
  filters.value = {
    search: '',
    referrer_id: '',
    verified: null,
    start_date: '',
    end_date: '',
    source: '',
  }
  fetchReferredUsers()
}

const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleDateString()
}

const getVerificationColor = (verified: boolean) => {
  return verified ? 'success' : 'warning'
}

const getVerificationText = (verified: boolean) => {
  return verified ? t('verified') : t('pending_verification')
}

const getSourceColor = (source: string) => {
  return source === 'mobile' ? 'info' : 'primary'
}

// Lifecycle
onMounted(() => {
  fetchReferredUsers()
  fetchReferrers()
})
</script>

<template>
  <div>
    <!-- Page Header -->
    <div class="d-flex justify-space-between align-center mb-6">
      <div>
        <h1 class="text-h4 font-weight-bold mb-1">
          {{ t('referred_users') }}
        </h1>
        <p class="text-body-1 mb-0">
          {{ t('referred_users_subtitle') }}
        </p>
      </div>
    </div>

    <!-- Filters -->
    <VCard class="mb-6">
      <VCardTitle>{{ t('filters') }}</VCardTitle>
      <VCardText>
        <VRow>
          <VCol cols="12" md="3">
            <VTextField
              v-model="filters.search"
              :label="t('search_users')"
              :placeholder="t('search_by_name_email')"
              prepend-inner-icon="tabler-search"
              clearable
              @keyup.enter="onSearch"
            />
          </VCol>

          <VCol cols="12" md="3">
            <VSelect
              v-model="filters.referrer_id"
              :items="referrers"
              :label="t('referrer')"
              clearable
              @update:model-value="onSearch"
            />
          </VCol>

          <VCol cols="12" md="2">
            <VSelect
              v-model="filters.verified"
              :items="[
                { value: true, title: t('verified') },
                { value: false, title: t('unverified') },
              ]"
              :label="t('verification_status')"
              clearable
              @update:model-value="onSearch"
            />
          </VCol>

          <VCol cols="12" md="2">
            <VSelect
              v-model="filters.source"
              :items="[
                { value: 'web', title: t('web') },
                { value: 'mobile', title: t('mobile') },
              ]"
              :label="t('source')"
              clearable
              @update:model-value="onSearch"
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
          :items="referredUsers"
          :items-length="pagination.total"
          :items-per-page="pagination.per_page"
          :loading="loading"
          class="text-no-wrap"
          @update:page="fetchReferredUsers"
        >
          <template #item.new_user="{ item }">
            <div class="d-flex align-center">
              <VAvatar size="32" class="me-3">
                <VIcon icon="tabler-user" />
              </VAvatar>
              <div>
                <div class="font-weight-medium">{{ item.new_user.nom_complet }}</div>
                <div class="text-body-2 text-medium-emphasis">{{ item.new_user.email }}</div>
              </div>
            </div>
          </template>

          <template #item.referrer="{ item }">
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

          <template #item.attributed_at="{ item }">
            {{ formatDate(item.attributed_at) }}
          </template>

          <template #item.verified="{ item }">
            <VChip
              :color="getVerificationColor(item.verified)"
              size="small"
            >
              {{ getVerificationText(item.verified) }}
            </VChip>
          </template>

          <template #item.source="{ item }">
            <VChip
              :color="getSourceColor(item.source)"
              size="small"
            >
              {{ item.source }}
            </VChip>
          </template>

          <template #item.actions="{ item }">
            <VBtn
              icon="tabler-eye"
              size="small"
              variant="text"
              @click="() => router.push({ name: 'admin-referrals-referred-users-id', params: { id: item.id } })"
            />
          </template>

          <template #no-data>
            <div class="text-center py-8">
              <VIcon icon="tabler-users-off" size="64" class="mb-4" color="disabled" />
              <h6 class="text-h6 mb-2">{{ t('no_referred_users') }}</h6>
              <p class="text-body-2">{{ t('no_referred_users_description') }}</p>
            </div>
          </template>
        </VDataTableServer>
      </VCardText>
    </VCard>
  </div>
</template>
