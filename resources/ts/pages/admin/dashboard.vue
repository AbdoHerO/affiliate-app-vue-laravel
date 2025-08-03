<script setup lang="ts">
import { useAuth } from '@/composables/useAuth'
import { useRouter } from 'vue-router'

definePage({
  meta: {
    requiresAuth: true,
    requiresRole: 'admin',
  },
})

const { user, logout, hasPermission } = useAuth()
const router = useRouter()

// Mock statistics for now
const stats = ref({
  totalAffiliates: 150,
  totalOrders: 1250,
  totalRevenue: 45000,
  pendingOrders: 12,
})

const handleLogout = async () => {
  await logout()
}

// Navigation helpers
const navigateTo = (routeName: string) => {
  try {
    router.push({ name: routeName })
  } catch (error) {
    console.error('Navigation error:', error)
  }
}
</script>

<template>
  <div>
    <!-- Header -->
    <VCard class="mb-6">
      <VCardText>
        <div class="d-flex justify-space-between align-center">
          <div>
            <h2 class="text-h4 mb-2">{{ $t('title_admin_dashboard') }}</h2>
            <p class="text-body-1 mb-0">{{ $t('welcome_admin', { name: user?.nom_complet }) }}</p>
          </div>
          <VBtn
            color="error"
            variant="outlined"
            prepend-icon="tabler-logout"
            @click="handleLogout"
          >
            {{ $t('action_logout') }}
          </VBtn>
        </div>
      </VCardText>
    </VCard>

    <!-- Stats Cards -->
    <VRow class="mb-6">
      <VCol cols="12" sm="6" md="3">
        <VCard>
          <VCardText>
            <div class="d-flex align-center">
              <VAvatar color="primary" variant="tonal" class="me-4">
                <VIcon icon="tabler-users" />
              </VAvatar>
              <div>
                <h6 class="text-h6">{{ stats.totalAffiliates }}</h6>
                <p class="text-body-2 mb-0">{{ $t('stats_total_affiliates') }}</p>
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="3">
        <VCard>
          <VCardText>
            <div class="d-flex align-center">
              <VAvatar color="success" variant="tonal" class="me-4">
                <VIcon icon="tabler-shopping-cart" />
              </VAvatar>
              <div>
                <h6 class="text-h6">{{ stats.totalOrders }}</h6>
                <p class="text-body-2 mb-0">{{ $t('stats_total_orders') }}</p>
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="3">
        <VCard>
          <VCardText>
            <div class="d-flex align-center">
              <VAvatar color="warning" variant="tonal" class="me-4">
                <VIcon icon="tabler-currency-dollar" />
              </VAvatar>
              <div>
                <h6 class="text-h6">${{ stats.totalRevenue.toLocaleString() }}</h6>
                <p class="text-body-2 mb-0">{{ $t('stats_revenue') }}</p>
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="3">
        <VCard>
          <VCardText>
            <div class="d-flex align-center">
              <VAvatar color="error" variant="tonal" class="me-4">
                <VIcon icon="tabler-clock" />
              </VAvatar>
              <div>
                <h6 class="text-h6">{{ stats.pendingOrders }}</h6>
                <p class="text-body-2 mb-0">{{ $t('stats_pending_orders') }}</p>
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Quick Actions -->
    <VCard class="mb-6">
      <VCardText>
        <h5 class="text-h5 mb-4">{{ $t('quick_actions') }}</h5>
        <VRow>
          <VCol cols="12" sm="6" md="3">
            <VBtn
              block
              color="primary"
              variant="elevated"
              :disabled="!hasPermission('manage users')"
              @click="navigateTo('admin-users')"
            >
              <VIcon start icon="tabler-users" />
              {{ $t('manage_users') }}
            </VBtn>
          </VCol>
          <VCol cols="12" sm="6" md="3">
            <VBtn
              block
              color="success"
              variant="elevated"
              :disabled="!hasPermission('manage affiliates')"
              @click="navigateTo('admin-affiliates')"
            >
              <VIcon start icon="tabler-user-star" />
              {{ $t('manage_affiliates') }}
            </VBtn>
          </VCol>
          <VCol cols="12" sm="6" md="3">
            <VBtn
              block
              color="warning"
              variant="elevated"
              :disabled="!hasPermission('manage orders')"
              @click="navigateTo('admin-orders')"
            >
              <VIcon start icon="tabler-shopping-cart" />
              {{ $t('order_management') }}
            </VBtn>
          </VCol>
          <VCol cols="12" sm="6" md="3">
            <VBtn
              block
              color="info"
              variant="elevated"
              :disabled="!hasPermission('view reports')"
              @click="navigateTo('admin-reports-sales')"
            >
              <VIcon start icon="tabler-chart-bar" />
              {{ $t('reports') }}
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- User Info -->
    <VCard>
      <VCardText>
        <h5 class="text-h5 mb-4">{{ $t('user_info') }}</h5>
        <VRow>
          <VCol cols="12">
            <div class="text-body-1">
              <strong>{{ $t('user_name') }}:</strong> {{ user?.nom_complet }}<br>
              <strong>{{ $t('user_email') }}:</strong> {{ user?.email }}<br>
              <strong>{{ $t('user_role') }}:</strong> {{ user?.roles?.join(', ') }}<br>
              <strong>{{ $t('user_permissions') }}:</strong> {{ user?.permissions?.join(', ') }}
            </div>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>
  </div>
</template>

