<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAffiliatesStore } from '@/stores/admin/affiliates'
import { useConfirmAction } from '@/composables/useConfirmAction'
import { useNotifications } from '@/composables/useNotifications'
import { useI18n } from 'vue-i18n'

definePage({
  meta: {
    requiresAuth: true,
    requiresRole: 'admin',
    layout: 'default',
  },
})

const route = useRoute()
const router = useRouter()
const affiliatesStore = useAffiliatesStore()
const { confirm } = useConfirmAction()
const { showSuccess, showError } = useNotifications()
const { t } = useI18n()

// Local state
const activeTab = ref('profile')
const showTierDialog = ref(false)
const showBlockDialog = ref(false)
const selectedTier = ref('')
const blockReason = ref('')

// Computed
const isLoading = computed(() => affiliatesStore.isLoading)
const affiliate = computed(() => affiliatesStore.currentAffiliate)
const performance = computed(() => affiliatesStore.affiliatePerformance)
const tiers = computed(() => affiliatesStore.affiliateTiers)
const affiliateId = computed(() => route.params.id as string)

// Methods
const fetchAffiliate = async () => {
  await affiliatesStore.fetchAffiliate(affiliateId.value)
}

const fetchPerformance = async () => {
  await affiliatesStore.fetchPerformance(affiliateId.value)
}

const changeTier = async () => {
  if (!selectedTier.value || !affiliate.value) return

  try {
    await affiliatesStore.changeTier(affiliate.value.id, selectedTier.value, t('tier_change_from_admin'))
    showSuccess(t('tier_updated_successfully'))
    showTierDialog.value = false
    selectedTier.value = ''
  } catch (error: any) {
    showError(error.message || t('tier_change_error'))
  }
}

const toggleBlock = async (action: 'block' | 'unblock') => {
  if (!affiliate.value) return

  try {
    await affiliatesStore.toggleBlock(
      affiliate.value.id, 
      action, 
      action === 'block' ? blockReason.value : t('unblocked_from_admin')
    )
    showSuccess(
      action === 'block' ? t('affiliate_blocked_successfully') : t('affiliate_unblocked_successfully')
    )
    showBlockDialog.value = false
    blockReason.value = ''
  } catch (error: any) {
    showError(error.message || t('operation_error'))
  }
}

const getStatusColor = (status: string) => {
  switch (status) {
    case 'actif':
      return 'success'
    case 'inactif':
      return 'warning'
    case 'bloque':
      return 'error'
    case 'suspendu':
      return 'error'
    case 'resilie':
      return 'secondary'
    default:
      return 'default'
  }
}

const getStatusText = (status: string) => {
  switch (status) {
    case 'actif':
      return t('status.active')
    case 'inactif':
      return t('status.inactive')
    case 'bloque':
      return t('status.blocked')
    case 'suspendu':
      return t('status.suspended')
    case 'resilie':
      return t('status.terminated')
    default:
      return status
  }
}

const getKycStatusColor = (status: string) => {
  switch (status) {
    case 'valide':
      return 'success'
    case 'en_attente':
      return 'warning'
    case 'refuse':
      return 'error'
    case 'non_requis':
      return 'info'
    default:
      return 'default'
  }
}

const getKycStatusText = (status: string) => {
  switch (status) {
    case 'valide':
      return t('kyc_status_valid')
    case 'en_attente':
      return t('kyc_status_pending')
    case 'refuse':
      return t('kyc_status_rejected')
    case 'non_requis':
      return t('kyc_status_not_required')
    default:
      return status
  }
}

const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('fr-MA', {
    style: 'currency',
    currency: 'MAD',
  }).format(amount)
}

const formatDate = (date: string) => {
  return new Date(date).toLocaleDateString('fr-FR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

const goBack = () => {
  router.push({ name: 'admin-affiliates' })
}

// Lifecycle
onMounted(async () => {
  await affiliatesStore.fetchTiers()
  await fetchAffiliate()
  await fetchPerformance()
})
</script>

<template>
  <div class="d-flex flex-column gap-6">
    <!-- Loading State -->
    <div v-if="isLoading" class="text-center py-8">
      <VProgressCircular
        indeterminate
        color="primary"
        size="64"
      />
      <p class="mt-4">{{ t('loading_affiliate') }}</p>
    </div>

    <!-- Affiliate Content -->
    <div v-else-if="affiliate">
      <!-- Header -->
      <div class="d-flex justify-space-between align-center mb-6">
        <div class="d-flex align-center gap-4">
          <VBtn
            icon="tabler-arrow-left"
            variant="text"
            @click="goBack"
          />
          <div>
            <h1 class="text-h4 font-weight-bold mb-1">
              {{ affiliate.nom_complet }}
            </h1>
            <div class="d-flex align-center gap-2">
              <VChip
                size="small"
                :color="getStatusColor(affiliate.statut)"
                variant="tonal"
              >
                {{ getStatusText(affiliate.statut) }}
              </VChip>
              <VChip
                size="small"
                :color="getKycStatusColor(affiliate.kyc_statut)"
                variant="tonal"
              >
                {{ getKycStatusText(affiliate.kyc_statut) }}
              </VChip>
              <VChip
                v-if="affiliate.profil_affilie?.gamme"
                size="small"
                :color="affiliate.profil_affilie.gamme.code === 'BASIC' ? 'info' : affiliate.profil_affilie.gamme.code === 'SILVER' ? 'warning' : 'success'"
                variant="tonal"
              >
                {{ affiliate.profil_affilie.gamme.libelle }}
              </VChip>
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="d-flex gap-2">
          <VBtn
            color="secondary"
            variant="outlined"
            @click="showTierDialog = true"
          >
            <VIcon start icon="tabler-medal" />
            {{ t('change_tier') }}
          </VBtn>
          
          <VBtn
            v-if="affiliate.statut !== 'bloque'"
            color="error"
            variant="outlined"
            @click="showBlockDialog = true"
          >
            <VIcon start icon="tabler-ban" />
            {{ t('block') }}
          </VBtn>

          <VBtn
            v-else
            color="success"
            variant="outlined"
            @click="toggleBlock('unblock')"
          >
            <VIcon start icon="tabler-check" />
            {{ t('unblock') }}
          </VBtn>

          <VBtn
            color="primary"
            variant="elevated"
            @click="router.push({ name: 'admin-affiliate-edit', params: { id: affiliate.id } })"
          >
            <VIcon start icon="tabler-edit" />
            {{ t('actions.edit') }}
          </VBtn>
        </div>
      </div>

      <!-- Tabs -->
      <VTabs
        v-model="activeTab"
        class="mb-6"
      >
        <VTab value="profile">
          <VIcon start icon="tabler-user" />
          {{ t('profile') }}
        </VTab>
        <VTab value="performance">
          <VIcon start icon="tabler-chart-line" />
          {{ t('performance') }}
        </VTab>
        <VTab value="orders">
          <VIcon start icon="tabler-shopping-cart" />
          {{ t('orders') }}
        </VTab>
        <VTab value="commissions">
          <VIcon start icon="tabler-currency-dollar" />
          {{ t('commissions') }}
        </VTab>
      </VTabs>

      <!-- Tab Content -->
      <VWindow v-model="activeTab">
        <!-- Profile Tab -->
        <VWindowItem value="profile">
          <VRow>
            <VCol cols="12" md="8">
              <VCard>
                <VCardTitle>{{ t('personal_information') }}</VCardTitle>
                <VCardText>
                  <VRow>
                    <VCol cols="12" md="6">
                      <div class="mb-4">
                        <div class="text-body-2 text-medium-emphasis mb-1">{{ t('form.fullName') }}</div>
                        <div class="text-h6">{{ affiliate.nom_complet }}</div>
                      </div>
                      <div class="mb-4">
                        <div class="text-body-2 text-medium-emphasis mb-1">{{ t('form.email') }}</div>
                        <div class="text-body-1">{{ affiliate.email }}</div>
                      </div>
                      <div class="mb-4">
                        <div class="text-body-2 text-medium-emphasis mb-1">{{ t('form.phone') }}</div>
                        <div class="text-body-1">{{ affiliate.telephone || t('not_provided') }}</div>
                      </div>
                    </VCol>
                    <VCol cols="12" md="6">
                      <div class="mb-4">
                        <div class="text-body-2 text-medium-emphasis mb-1">{{ t('user_status') }}</div>
                        <VChip :color="getStatusColor(affiliate.statut)" variant="tonal">
                          {{ getStatusText(affiliate.statut) }}
                        </VChip>
                      </div>
                      <div class="mb-4">
                        <div class="text-body-2 text-medium-emphasis mb-1">{{ t('kyc_status') }}</div>
                        <VChip :color="getKycStatusColor(affiliate.kyc_statut)" variant="tonal">
                          {{ getKycStatusText(affiliate.kyc_statut) }}
                        </VChip>
                      </div>
                      <div class="mb-4">
                        <div class="text-body-2 text-medium-emphasis mb-1">{{ t('registration_date') }}</div>
                        <div class="text-body-1">{{ formatDate(affiliate.created_at) }}</div>
                      </div>
                    </VCol>
                  </VRow>

                  <VDivider class="my-4" />

                  <div class="mb-4">
                    <div class="text-body-2 text-medium-emphasis mb-1">{{ t('form.address') }}</div>
                    <div class="text-body-1">{{ affiliate.adresse || t('common.notProvided') }}</div>
                  </div>
                </VCardText>
              </VCard>

              <!-- Affiliate Profile Card -->
              <VCard class="mt-4">
                <VCardTitle>{{ t('affiliate_profile') }}</VCardTitle>
                <VCardText>
                  <VRow>
                    <VCol cols="12" md="6">
                      <div class="mb-4">
                        <div class="text-body-2 text-medium-emphasis mb-1">{{ t('tier') }}</div>
                        <VChip
                          v-if="affiliate.profil_affilie?.gamme"
                          :color="affiliate.profil_affilie.gamme.code === 'BASIC' ? 'info' : affiliate.profil_affilie.gamme.code === 'SILVER' ? 'warning' : 'success'"
                          variant="tonal"
                        >
                          {{ affiliate.profil_affilie.gamme.libelle }}
                        </VChip>
                        <span v-else class="text-medium-emphasis">{{ t('not_defined') }}</span>
                      </div>
                      <div class="mb-4">
                        <div class="text-body-2 text-medium-emphasis mb-1">{{ t('affiliate_status') }}</div>
                        <VChip
                          v-if="affiliate.profil_affilie"
                          :color="getStatusColor(affiliate.profil_affilie.statut)"
                          variant="tonal"
                        >
                          {{ getStatusText(affiliate.profil_affilie.statut) }}
                        </VChip>
                      </div>
                    </VCol>
                    <VCol cols="12" md="6">
                      <div class="mb-4">
                        <div class="text-body-2 text-medium-emphasis mb-1">{{ t('points') }}</div>
                        <div class="text-h6">{{ affiliate.profil_affilie?.points || 0 }}</div>
                      </div>
                      <div class="mb-4">
                        <div class="text-body-2 text-medium-emphasis mb-1">{{ t('rib') }}</div>
                        <div class="text-body-1">{{ affiliate.profil_affilie?.rib || t('not_provided') }}</div>
                      </div>
                    </VCol>
                  </VRow>

                  <div v-if="affiliate.profil_affilie?.notes_interne" class="mb-4">
                    <div class="text-body-2 text-medium-emphasis mb-1">{{ t('internal_notes') }}</div>
                    <div class="text-body-1" style="white-space: pre-line;">
                      {{ affiliate.profil_affilie.notes_interne }}
                    </div>
                  </div>
                </VCardText>
              </VCard>
            </VCol>
            <VCol cols="12" md="4">
              <VCard>
                <VCardTitle>{{ t('quick_stats') }}</VCardTitle>
                <VCardText>
                  <div class="mb-4">
                    <div class="text-body-2 text-medium-emphasis mb-1">{{ t('total_orders') }}</div>
                    <div class="text-h5">{{ affiliate.orders_count || 0 }}</div>
                  </div>
                  <div class="mb-4">
                    <div class="text-body-2 text-medium-emphasis mb-1">{{ t('total_commissions') }}</div>
                    <div class="text-h5">{{ affiliate.total_commissions ? formatCurrency(affiliate.total_commissions) : '0 MAD' }}</div>
                  </div>
                  <div class="mb-4">
                    <div class="text-body-2 text-medium-emphasis mb-1">{{ t('pending_commissions') }}</div>
                    <div class="text-h6">{{ affiliate.commissions_count || 0 }}</div>
                  </div>
                </VCardText>
              </VCard>
            </VCol>
          </VRow>
        </VWindowItem>

        <!-- Performance Tab -->
        <VWindowItem value="performance">
          <VCard>
            <VCardTitle>{{ t('affiliate_performance') }}</VCardTitle>
            <VCardText>
              <div v-if="performance">
                <VRow>
                  <VCol cols="12" md="4">
                    <VCard variant="tonal" color="primary">
                      <VCardText>
                        <div class="text-h4">{{ performance.orders.total }}</div>
                        <div class="text-body-2">{{ t('total_orders') }}</div>
                        <div class="text-caption">{{ performance.orders.this_month }} {{ t('this_month') }}</div>
                      </VCardText>
                    </VCard>
                  </VCol>
                  <VCol cols="12" md="4">
                    <VCard variant="tonal" color="success">
                      <VCardText>
                        <div class="text-h4">{{ formatCurrency(performance.commissions.total) }}</div>
                        <div class="text-body-2">{{ t('total_commissions') }}</div>
                        <div class="text-caption">{{ formatCurrency(performance.commissions.this_month) }} {{ t('this_month') }}</div>
                      </VCardText>
                    </VCard>
                  </VCol>
                  <VCol cols="12" md="4">
                    <VCard variant="tonal" color="warning">
                      <VCardText>
                        <div class="text-h4">{{ formatCurrency(performance.payments.total_paid) }}</div>
                        <div class="text-body-2">{{ t('total_paid') }}</div>
                        <div class="text-caption">{{ formatCurrency(performance.payments.pending) }} {{ t('pending') }}</div>
                      </VCardText>
                    </VCard>
                  </VCol>
                </VRow>
              </div>
              <div v-else class="text-center py-8">
                <VProgressCircular indeterminate />
                <p class="mt-2">{{ t('loading_performance') }}</p>
              </div>
            </VCardText>
          </VCard>
        </VWindowItem>

        <!-- Orders Tab -->
        <VWindowItem value="orders">
          <VCard>
            <VCardTitle>{{ t('recent_orders') }}</VCardTitle>
            <VCardText>
              <div v-if="affiliate.profil_affilie?.commandes?.length">
                <VList>
                  <VListItem
                    v-for="order in affiliate.profil_affilie.commandes"
                    :key="order.id"
                  >
                    <VListItemTitle>{{ t('order') }} {{ order.id.slice(0, 8) }}</VListItemTitle>
                    <VListItemSubtitle>{{ formatDate(order.created_at) }}</VListItemSubtitle>
                    <template #append>
                      <VChip size="small" :color="getStatusColor(order.statut)" variant="tonal">
                        {{ order.statut }}
                      </VChip>
                    </template>
                  </VListItem>
                </VList>
              </div>
              <div v-else class="text-center py-8">
                <VIcon icon="tabler-shopping-cart-off" size="64" class="mb-4" color="disabled" />
                <h3 class="text-h6 mb-2">{{ t('no_orders') }}</h3>
                <p class="text-body-2 text-medium-emphasis">
                  {{ t('affiliate_no_orders_yet') }}
                </p>
              </div>
            </VCardText>
          </VCard>
        </VWindowItem>

        <!-- Commissions Tab -->
        <VWindowItem value="commissions">
          <VCard>
            <VCardTitle>{{ t('recent_commissions') }}</VCardTitle>
            <VCardText>
              <div v-if="affiliate.commissions?.length">
                <VList>
                  <VListItem
                    v-for="commission in affiliate.commissions"
                    :key="commission.id"
                  >
                    <VListItemTitle>{{ formatCurrency(commission.amount) }}</VListItemTitle>
                    <VListItemSubtitle>{{ formatDate(commission.created_at) }}</VListItemSubtitle>
                    <template #append>
                      <VChip size="small" :color="getStatusColor(commission.status)" variant="tonal">
                        {{ commission.status }}
                      </VChip>
                    </template>
                  </VListItem>
                </VList>
              </div>
              <div v-else class="text-center py-8">
                <VIcon icon="tabler-currency-dollar-off" size="64" class="mb-4" color="disabled" />
                <h3 class="text-h6 mb-2">{{ t('no_commissions') }}</h3>
                <p class="text-body-2 text-medium-emphasis">
                  {{ t('affiliate_no_commissions_yet') }}
                </p>
              </div>
            </VCardText>
          </VCard>
        </VWindowItem>
      </VWindow>
    </div>

    <!-- Error State -->
    <div v-else class="text-center py-8">
      <VIcon
        icon="tabler-alert-circle"
        size="64"
        class="mb-4"
        color="error"
      />
      <h3 class="text-h6 mb-2">{{ t('affiliate_not_found') }}</h3>
      <p class="text-body-2 text-medium-emphasis mb-4">
        {{ t('affiliate_not_found_description') }}
      </p>
      <VBtn
        color="primary"
        variant="elevated"
        @click="goBack"
      >
        {{ t('actions.backToList') }}
      </VBtn>
    </div>

    <!-- Change Tier Dialog -->
    <VDialog
      v-model="showTierDialog"
      max-width="500"
    >
      <VCard>
        <VCardTitle>{{ t('change_tier') }}</VCardTitle>
        <VCardText>
          <VSelect
            v-model="selectedTier"
            :label="t('new_tier')"
            :items="tiers.map(t => ({ title: t.libelle, value: t.id }))"
            variant="outlined"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            color="secondary"
            variant="text"
            @click="showTierDialog = false"
          >
            {{ t('actions.cancel') }}
          </VBtn>
          <VBtn
            color="primary"
            variant="elevated"
            :disabled="!selectedTier"
            @click="changeTier"
          >
            {{ t('confirm') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Block Dialog -->
    <VDialog
      v-model="showBlockDialog"
      max-width="500"
    >
      <VCard>
        <VCardTitle>{{ t('block_affiliate') }}</VCardTitle>
        <VCardText>
          <VTextarea
            v-model="blockReason"
            :label="t('block_reason')"
            :placeholder="t('block_reason_placeholder')"
            variant="outlined"
            rows="3"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            color="secondary"
            variant="text"
            @click="showBlockDialog = false"
          >
            {{ t('actions.cancel') }}
          </VBtn>
          <VBtn
            color="error"
            variant="elevated"
            @click="toggleBlock('block')"
          >
            {{ t('block') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
