<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { AffiliateWithdrawal } from '@/stores/affiliate/payments'

const { t } = useI18n()

interface Props {
  isVisible: boolean
  withdrawal: AffiliateWithdrawal | null
}

interface Emits {
  (e: 'update:isVisible', value: boolean): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

// Computed
const dialogModel = computed({
  get: () => props.isVisible,
  set: (value) => emit('update:isVisible', value)
})

const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'MAD',
  }).format(amount)
}

const formatDate = (date: string) => {
  return new Date(date).toLocaleDateString('fr-FR', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

const getStatusColor = (status: string) => {
  const statusColors: Record<string, string> = {
    'pending': 'warning',
    'approved': 'info',
    'in_payment': 'primary',
    'paid': 'success',
    'rejected': 'error',
    'canceled': 'secondary',
  }
  return statusColors[status] || 'secondary'
}

const getOrderTypeColor = (type: string) => {
  const colors: Record<string, string> = {
    'order_sample': 'primary',
    'exchange': 'warning'
  }
  return colors[type] || 'secondary'
}

const getOrderTypeLabel = (type: string) => {
  const labels: Record<string, string> = {
    'order_sample': t('order_type_order_sample'),
    'exchange': t('order_type_exchange')
  }
  return labels[type] || type || 'N/A'
}

const getStatusText = (status: string) => {
  const statusTexts: Record<string, string> = {
    'pending': 'En attente',
    'approved': 'Approuvé',
    'in_payment': 'En cours de paiement',
    'paid': 'Payé',
    'rejected': 'Rejeté',
    'canceled': 'Annulé',
  }
  return statusTexts[status] || status
}

const getMethodText = (method: string) => {
  const methodTexts: Record<string, string> = {
    'bank_transfer': 'Virement bancaire',
    'check': 'Chèque',
    'cash': 'Espèces',
  }
  return methodTexts[method] || method
}
</script>

<template>
  <VDialog
    v-model="dialogModel"
    max-width="800"
    persistent
  >
    <VCard v-if="withdrawal">
      <VCardTitle class="d-flex align-center justify-space-between">
        <div>
          <h3 class="text-h5">{{ t('labels.withdrawalDetails') }}</h3>
          <p class="text-body-2 text-medium-emphasis mb-0">
            {{ t('reference') }}: #{{ withdrawal.id.slice(-8) }}
          </p>
        </div>
        <VBtn
          icon="tabler-x"
          variant="text"
          size="small"
          @click="dialogModel = false"
        />
      </VCardTitle>

      <VCardText>
        <VRow>
          <!-- Status and Amount -->
          <VCol cols="12" md="6">
            <VCard variant="tonal" color="primary" class="mb-4">
              <VCardText>
                <div class="text-center">
                  <h4 class="text-h4 mb-2">{{ formatCurrency(withdrawal.amount) }}</h4>
                  <VChip
                    :color="getStatusColor(withdrawal.status)"
                    variant="elevated"
                    size="large"
                  >
                    {{ getStatusText(withdrawal.status) }}
                  </VChip>
                </div>
              </VCardText>
            </VCard>
          </VCol>

          <!-- Commission Info -->
          <VCol cols="12" md="6">
            <VCard variant="tonal" color="success" class="mb-4">
              <VCardText>
                <div class="text-center">
                  <h4 class="text-h4 mb-2">{{ withdrawal.commission_count || 0 }}</h4>
                  <p class="text-body-1 mb-0">{{ t('commissions_included') }}</p>
                </div>
              </VCardText>
            </VCard>
          </VCol>

          <!-- Details -->
          <VCol cols="12">
            <VCard variant="outlined">
              <VCardTitle>{{ t('detailed_information') }}</VCardTitle>
              <VCardText>
                <VRow>
                  <VCol cols="12" sm="6">
                    <div class="mb-3">
                      <p class="text-caption text-medium-emphasis mb-1">{{ t('payment_method') }}</p>
                      <p class="text-body-1 font-weight-medium">{{ getMethodText(withdrawal.method) }}</p>
                    </div>
                  </VCol>
                  <VCol cols="12" sm="6">
                    <div class="mb-3">
                      <p class="text-caption text-medium-emphasis mb-1">{{ t('creation_date') }}</p>
                      <p class="text-body-1 font-weight-medium">{{ formatDate(withdrawal.created_at) }}</p>
                    </div>
                  </VCol>
                  <VCol v-if="withdrawal.approved_at" cols="12" sm="6">
                    <div class="mb-3">
                      <p class="text-caption text-medium-emphasis mb-1">{{ t('approval_date') }}</p>
                      <p class="text-body-1 font-weight-medium">{{ formatDate(withdrawal.approved_at) }}</p>
                    </div>
                  </VCol>
                  <VCol v-if="withdrawal.paid_at" cols="12" sm="6">
                    <div class="mb-3">
                      <p class="text-caption text-medium-emphasis mb-1">{{ t('payment_date') }}</p>
                      <p class="text-body-1 font-weight-medium">{{ formatDate(withdrawal.paid_at) }}</p>
                    </div>
                  </VCol>
                  <VCol v-if="withdrawal.payment_ref" cols="12">
                    <div class="mb-3">
                      <p class="text-caption text-medium-emphasis mb-1">{{ t('payment_reference') }}</p>
                      <p class="text-body-1 font-weight-medium">{{ withdrawal.payment_ref }}</p>
                    </div>
                  </VCol>
                  <VCol v-if="withdrawal.iban_rib" cols="12">
                    <div class="mb-3">
                      <p class="text-caption text-medium-emphasis mb-1">{{ t('rib_iban') }}</p>
                      <p class="text-body-1 font-weight-medium">{{ withdrawal.iban_rib }}</p>
                    </div>
                  </VCol>
                  <VCol v-if="withdrawal.notes" cols="12">
                    <div class="mb-3">
                      <p class="text-caption text-medium-emphasis mb-1">{{ t('notes') }}</p>
                      <p class="text-body-1">{{ withdrawal.notes }}</p>
                    </div>
                  </VCol>
                </VRow>
              </VCardText>
            </VCard>
          </VCol>

          <!-- Commission Items -->
          <VCol v-if="withdrawal.items && withdrawal.items.length > 0" cols="12">
            <VCard variant="outlined">
              <VCardTitle>{{ t('commissions_included') }}</VCardTitle>
              <VCardText>
                <VDataTable
                  :headers="[
                    { title: t('commission'), key: 'commission.id', sortable: false },
                    { title: t('order'), key: 'commission.commande.id', sortable: false },
                    { title: t('product'), key: 'commission.produit', sortable: false },
                    { title: t('amount'), key: 'amount', sortable: false },
                    { title: t('order_type'), key: 'commission.order_type', sortable: false },
                    { title: t('date'), key: 'commission.created_at', sortable: false },
                  ]"
                  :items="withdrawal.items"
                  items-per-page="5"
                  class="text-no-wrap"
                >
                  <template #item.commission.id="{ item }">
                    <span class="text-body-2 font-family-monospace">
                      #{{ item.commission?.id?.slice(-8) || 'N/A' }}
                    </span>
                  </template>
                  <template #item.commission.commande.id="{ item }">
                    <span class="text-body-2 font-family-monospace">
                      #{{ item.commission?.commande?.id?.slice(-8) || 'N/A' }}
                    </span>
                  </template>
                  <template #item.commission.produit="{ item }">
                    <div v-if="item.commission?.produit">
                      <div class="text-body-2 font-weight-medium">{{ item.commission.produit.titre }}</div>
                      <div v-if="item.commission.produit.sku" class="text-caption text-medium-emphasis">
                        <VChip
                          size="x-small"
                          color="secondary"
                          variant="outlined"
                          class="font-mono"
                        >
                          {{ item.commission.produit.sku }}
                        </VChip>
                      </div>
                    </div>
                    <span v-else class="text-medium-emphasis">N/A</span>
                  </template>
                  <template #item.amount="{ item }">
                    <span class="text-body-2 font-weight-medium">
                      {{ formatCurrency(item.amount) }}
                    </span>
                  </template>
                  <template #item.commission.order_type="{ item }">
                    <VChip size="small" variant="tonal">
                      {{ item.commission?.commande_article?.type_command || 'N/A' }}
                    </VChip>
                  </template>
                  <template #item.commission.created_at="{ item }">
                    <span class="text-body-2">
                      {{ item.commission?.created_at ? formatDate(item.commission.created_at) : 'N/A' }}
                    </span>
                  </template>
                </VDataTable>
              </VCardText>
            </VCard>
          </VCol>
        </VRow>
      </VCardText>

      <VCardActions>
        <VSpacer />
        <VBtn
          variant="outlined"
          @click="dialogModel = false"
        >
          {{ t('actions.close') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
