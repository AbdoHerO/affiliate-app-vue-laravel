<script setup lang="ts">
import { useAuth } from '@/composables/useAuth'

definePage({
  meta: {
    requiresAuth: true,
    requiresRole: 'admin',
  },
})

const { user, logout, hasPermission } = useAuth()

const stats = ref({
  totalAffiliates: 150,
  totalOrders: 1250,
  totalRevenue: 45000,
  pendingPayments: 12,
})

const handleLogout = async () => {
  await logout()
}
</script>

<template>
  <div>
    <!-- Header -->
    <VCard class="mb-6">
      <VCardText>
        <div class="d-flex justify-space-between align-center">
          <div>
            <h2 class="text-h4 mb-2">
              {{ $t('Admin Dashboard') }}
            </h2>
            <p class="text-body-1 mb-0">
              {{ $t('Welcome') }}, {{ user?.name }}! 👋
            </p>
          </div>
          <VBtn
            color="error"
            variant="outlined"
            @click="handleLogout"
          >
            {{ $t('Logout') }}
          </VBtn>
        </div>
      </VCardText>
    </VCard>

    <!-- Stats Cards -->
    <VRow class="mb-6">
      <VCol
        cols="12"
        sm="6"
        md="3"
      >
        <VCard>
          <VCardText>
            <div class="d-flex align-center">
              <VAvatar
                color="primary"
                variant="tonal"
                class="me-4"
              >
                <VIcon icon="tabler-users" />
              </VAvatar>
              <div>
                <h6 class="text-h6">
                  {{ stats.totalAffiliates }}
                </h6>
                <p class="text-body-2 mb-0">
                  {{ $t('Affiliates') }}
                </p>
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol
        cols="12"
        sm="6"
        md="3"
      >
        <VCard>
          <VCardText>
            <div class="d-flex align-center">
              <VAvatar
                color="success"
                variant="tonal"
                class="me-4"
              >
                <VIcon icon="tabler-shopping-cart" />
              </VAvatar>
              <div>
                <h6 class="text-h6">
                  {{ stats.totalOrders }}
                </h6>
                <p class="text-body-2 mb-0">
                  {{ $t('Orders') }}
                </p>
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol
        cols="12"
        sm="6"
        md="3"
      >
        <VCard>
          <VCardText>
            <div class="d-flex align-center">
              <VAvatar
                color="warning"
                variant="tonal"
                class="me-4"
              >
                <VIcon icon="tabler-currency-dollar" />
              </VAvatar>
              <div>
                <h6 class="text-h6">
                  ${{ stats.totalRevenue.toLocaleString() }}
                </h6>
                <p class="text-body-2 mb-0">
                  Total Revenue
                </p>
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol
        cols="12"
        sm="6"
        md="3"
      >
        <VCard>
          <VCardText>
            <div class="d-flex align-center">
              <VAvatar
                color="error"
                variant="tonal"
                class="me-4"
              >
                <VIcon icon="tabler-clock" />
              </VAvatar>
              <div>
                <h6 class="text-h6">
                  {{ stats.pendingPayments }}
                </h6>
                <p class="text-body-2 mb-0">
                  {{ $t('Pending Orders') }}
                </p>
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Quick Actions -->
    <VCard class="mb-6">
      <VCardText>
        <h5 class="text-h5 mb-4">
          Quick Actions
        </h5>
        <VRow>
          <VCol
            cols="12"
            sm="6"
            md="3"
          >
            <VBtn
              block
              color="primary"
              :disabled="!hasPermission('manage affiliates')"
              @click="() => alert('Manage Affiliates - Coming Soon!')"
            >
              <VIcon
                start
                icon="tabler-users"
              />
              {{ $t('Manage Affiliates') }}
            </VBtn>
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="3"
          >
            <VBtn
              block
              color="success"
              :disabled="!hasPermission('manage orders')"
              @click="() => alert('Order Management - Coming Soon!')"
            >
              <VIcon
                start
                icon="tabler-shopping-cart"
              />
              {{ $t('Order Management') }}
            </VBtn>
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="3"
          >
            <VBtn
              block
              color="warning"
              :disabled="!hasPermission('manage payments')"
              @click="() => alert('Payment Management - Coming Soon!')"
            >
              <VIcon
                start
                icon="tabler-credit-card"
              />
              {{ $t('Payment Management') }}
            </VBtn>
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="3"
          >
            <VBtn
              block
              color="info"
              :disabled="!hasPermission('view reports')"
              @click="() => alert('Reports - Coming Soon!')"
            >
              <VIcon
                start
                icon="tabler-chart-bar"
              />
              {{ $t('Reports') }}
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- User Info -->
    <VCard>
      <VCardText>
        <h5 class="text-h5 mb-4">
          User Information
        </h5>
        <VRow>
          <VCol cols="12">
            <div class="text-body-1">
              <strong>Name:</strong> {{ user?.name }}<br>
              <strong>Email:</strong> {{ user?.email }}<br>
              <strong>Roles:</strong> {{ user?.roles.join(', ') }}<br>
              <strong>Permissions:</strong> {{ user?.permissions.join(', ') }}
            </div>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>
  </div>
</template>
