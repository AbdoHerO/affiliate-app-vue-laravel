<script setup lang="ts">
import { useAuth } from '@/composables/useAuth'

definePage({
  meta: {
    requiresAuth: true,
    requiresRole: 'affiliate',
  },
})

const { user, logout, hasPermission } = useAuth()

const stats = ref({
  myOrders: 25,
  pendingOrders: 3,
  totalCommissions: 1250.50,
  thisMonthEarnings: 350.75,
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
              {{ $t('title_affiliate_dashboard') }}
            </h2>
            <p class="text-body-1 mb-0">
              {{ $t('welcome_affiliate', { name: user?.nom_complet }) }}
            </p>
          </div>
          <VBtn
            color="error"
            variant="outlined"
            @click="handleLogout"
          >
            {{ $t('action_logout') }}
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
                <VIcon icon="tabler-shopping-cart" />
              </VAvatar>
              <div>
                <h6 class="text-h6">
                  {{ stats.myOrders }}
                </h6>
                <p class="text-body-2 mb-0">
                  {{ $t('stats_my_orders_count') }}
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
                <VIcon icon="tabler-clock" />
              </VAvatar>
              <div>
                <h6 class="text-h6">
                  {{ stats.pendingOrders }}
                </h6>
                <p class="text-body-2 mb-0">
                  {{ $t('stats_pending_orders_count') }}
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
                <VIcon icon="tabler-currency-dollar" />
              </VAvatar>
              <div>
                <h6 class="text-h6">
                  ${{ stats.totalCommissions.toFixed(2) }}
                </h6>
                <p class="text-body-2 mb-0">
                  {{ $t('stats_total_earnings') }}
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
                color="info"
                variant="tonal"
                class="me-4"
              >
                <VIcon icon="tabler-trending-up" />
              </VAvatar>
              <div>
                <h6 class="text-h6">
                  ${{ stats.thisMonthEarnings.toFixed(2) }}
                </h6>
                <p class="text-body-2 mb-0">
                  {{ $t('stats_this_month_earnings') }}
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
          {{ $t('quick_actions') }}
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
              :disabled="!hasPermission('create orders')"
              @click="() => alert('Create Order - Coming Soon!')"
            >
              <VIcon
                start
                icon="tabler-plus"
              />
              {{ $t('create_order') }}
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
              :disabled="!hasPermission('view own orders')"
              @click="() => alert('Order History - Coming Soon!')"
            >
              <VIcon
                start
                icon="tabler-list"
              />
              {{ $t('order_history') }}
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
              :disabled="!hasPermission('view own commissions')"
              @click="() => alert('Commissions - Coming Soon!')"
            >
              <VIcon
                start
                icon="tabler-coins"
              />
              {{ $t('view_commissions') }}
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
              :disabled="!hasPermission('view marketing materials')"
              @click="() => alert('Marketing Materials - Coming Soon!')"
            >
              <VIcon
                start
                icon="tabler-photo"
              />
              {{ $t('view_marketing_materials') }}
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Recent Orders -->
    <VCard class="mb-6">
      <VCardText>
        <h5 class="text-h5 mb-4">
          {{ $t('table_recent_orders') }}
        </h5>
        <VTable>
          <thead>
            <tr>
              <th>{{ $t('table_order_id') }}</th>
              <th>{{ $t('table_product') }}</th>
              <th>{{ $t('table_customer') }}</th>
              <th>{{ $t('table_status') }}</th>
              <th>{{ $t('table_commission') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>#ORD-001</td>
              <td>Product A</td>
              <td>John Doe</td>
              <td>
                <VChip
                  color="success"
                  size="small"
                >
                  {{ $t('status_delivered') }}
                </VChip>
              </td>
              <td>$25.00</td>
            </tr>
            <tr>
              <td>#ORD-002</td>
              <td>Product B</td>
              <td>Jane Smith</td>
              <td>
                <VChip
                  color="warning"
                  size="small"
                >
                  {{ $t('status_pending') }}
                </VChip>
              </td>
              <td>$15.50</td>
            </tr>
            <tr>
              <td>#ORD-003</td>
              <td>Product C</td>
              <td>Bob Johnson</td>
              <td>
                <VChip
                  color="info"
                  size="small"
                >
                  {{ $t('status_shipped') }}
                </VChip>
              </td>
              <td>$30.25</td>
            </tr>
          </tbody>
        </VTable>
      </VCardText>
    </VCard>

    <!-- User Info -->
    <VCard>
      <VCardText>
        <h5 class="text-h5 mb-4">
          {{ $t('user_info') }}
        </h5>
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
