<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

// Props
interface Props {
  data: Record<string, any>
  loading: boolean
}

const props = defineProps<Props>()

// Emits
const emit = defineEmits<{
  change: [key: string, value: any]
  save: [data: Record<string, any>]
}>()

// Local state
const localData = ref({
  // Commission Settings
  default_commission_rate: 10,
  min_commission_threshold: 50,
  commission_calculation_method: 'per_order',
  tiered_commissions_enabled: false,
  tier_1_rate: 8,
  tier_1_threshold: 1000,
  tier_2_rate: 12,
  tier_2_threshold: 5000,
  tier_3_rate: 15,
  tier_3_threshold: 10000,
  
  // Order Management
  auto_confirm_timeout: 24,
  order_number_prefix: 'ORD',
  order_number_format: 'YYYYMMDD-####',
  return_window_days: 7,
  refund_processing_days: 3,
  
  // Financial Settings
  min_withdrawal_amount: 100,
  max_withdrawal_amount: 10000,
  withdrawal_fee_percentage: 2,
  withdrawal_fee_fixed: 5,
  withdrawal_processing_days: 3,
  tax_rate: 20,
  tax_included_in_prices: true,
  
  // Payment Settings
  payment_delay_days: 30,
  auto_payout_enabled: false,
  auto_payout_threshold: 500,
  
  ...props.data
})

// Available options
const commissionMethods = [
  { title: t('per_order'), value: 'per_order', description: t('commission_per_completed_order') },
  { title: t('per_product'), value: 'per_product', description: t('commission_per_product_sold') },
  { title: t('percentage_of_sales'), value: 'percentage', description: t('percentage_of_total_sales') }
]

const orderNumberFormats = [
  { title: 'YYYYMMDD-#### (20241225-0001)', value: 'YYYYMMDD-####' },
  { title: 'ORD-###### (ORD-000001)', value: 'ORD-######' },
  { title: 'YYYY-MM-#### (2024-12-0001)', value: 'YYYY-MM-####' },
  { title: '############ (202412250001)', value: '############' }
]

// Watch for changes and emit
watch(localData, (newData) => {
  Object.keys(newData).forEach(key => {
    if (newData[key] !== props.data[key]) {
      emit('change', key, newData[key])
    }
  })
}, { deep: true })

// Handle save
const handleSave = () => {
  emit('save', localData.value)
}

// Computed values
const totalWithdrawalFee = computed(() => {
  const percentage = (localData.value.min_withdrawal_amount * localData.value.withdrawal_fee_percentage) / 100
  return percentage + localData.value.withdrawal_fee_fixed
})

const effectiveCommissionRate = computed(() => {
  if (!localData.value.tiered_commissions_enabled) {
    return localData.value.default_commission_rate
  }
  return `${localData.value.tier_1_rate}% - ${localData.value.tier_3_rate}%`
})
</script>

<template>
  <VRow>
    <!-- Commission Structure -->
    <VCol cols="12">
      <VCard :title="t('commission_structure')">
        <VCardText>
          <VRow>
            <!-- Commission Calculation Method -->
            <VCol cols="12">
              <AppSelect
                v-model="localData.commission_calculation_method"
                :items="commissionMethods"
                :label="t('commission_calculation_method')"
              >
                <template #item="{ props, item }">
                  <VListItem v-bind="props">
                    <VListItemTitle>{{ item.raw.title }}</VListItemTitle>
                    <VListItemSubtitle>{{ item.raw.description }}</VListItemSubtitle>
                  </VListItem>
                </template>
              </AppSelect>
            </VCol>

            <!-- Default Commission Rate -->
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model.number="localData.default_commission_rate"
                :label="t('default_commission_rate')"
                type="number"
                min="0"
                max="100"
                step="0.1"
                suffix="%"
              />
            </VCol>

            <!-- Minimum Commission Threshold -->
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model.number="localData.min_commission_threshold"
                :label="t('minimum_commission_threshold')"
                type="number"
                min="0"
                step="0.01"
                suffix="MAD"
              />
            </VCol>

            <!-- Tiered Commissions -->
            <VCol cols="12">
              <VSwitch
                v-model="localData.tiered_commissions_enabled"
                :label="t('enable_tiered_commissions')"
                color="success"
                inset
              />

              <VExpandTransition>
                <div v-if="localData.tiered_commissions_enabled" class="mt-4">
                  <VAlert
                    type="info"
                    variant="tonal"
                    class="mb-4"
                    prepend-icon="tabler-info-circle"
                  >
                    {{ t('tiered_commissions_description') }}
                  </VAlert>

                  <!-- Tier 1 -->
                  <VCard class="mb-3" variant="tonal">
                    <VCardTitle class="text-body-1">{{ t('tier_1_basic') }}</VCardTitle>
                    <VCardText>
                      <VRow>
                        <VCol cols="6">
                          <AppTextField
                            v-model.number="localData.tier_1_rate"
                            :label="t('commission_rate')"
                            type="number"
                            suffix="%"
                          />
                        </VCol>
                        <VCol cols="6">
                          <AppTextField
                            v-model.number="localData.tier_1_threshold"
                            :label="t('sales_threshold')"
                            type="number"
                            suffix="MAD"
                          />
                        </VCol>
                      </VRow>
                    </VCardText>
                  </VCard>

                  <!-- Tier 2 -->
                  <VCard class="mb-3" variant="tonal">
                    <VCardTitle class="text-body-1">{{ t('tier_2_silver') }}</VCardTitle>
                    <VCardText>
                      <VRow>
                        <VCol cols="6">
                          <AppTextField
                            v-model.number="localData.tier_2_rate"
                            :label="t('commission_rate')"
                            type="number"
                            suffix="%"
                          />
                        </VCol>
                        <VCol cols="6">
                          <AppTextField
                            v-model.number="localData.tier_2_threshold"
                            :label="t('sales_threshold')"
                            type="number"
                            suffix="MAD"
                          />
                        </VCol>
                      </VRow>
                    </VCardText>
                  </VCard>

                  <!-- Tier 3 -->
                  <VCard variant="tonal">
                    <VCardTitle class="text-body-1">{{ t('tier_3_gold') }}</VCardTitle>
                    <VCardText>
                      <VRow>
                        <VCol cols="6">
                          <AppTextField
                            v-model.number="localData.tier_3_rate"
                            :label="t('commission_rate')"
                            type="number"
                            suffix="%"
                          />
                        </VCol>
                        <VCol cols="6">
                          <AppTextField
                            v-model.number="localData.tier_3_threshold"
                            :label="t('sales_threshold')"
                            type="number"
                            suffix="MAD"
                          />
                        </VCol>
                      </VRow>
                    </VCardText>
                  </VCard>
                </div>
              </VExpandTransition>
            </VCol>
          </VRow>
        </VCardText>
      </VCard>
    </VCol>

    <!-- Order Management -->
    <VCol cols="12">
      <VCard :title="t('order_management')">
        <VCardText>
          <VRow>
            <!-- Auto Confirmation Timeout -->
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model.number="localData.auto_confirm_timeout"
                :label="t('auto_confirmation_timeout')"
                type="number"
                min="1"
                max="168"
                suffix="heures"
              />
            </VCol>

            <!-- Order Number Prefix -->
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="localData.order_number_prefix"
                :label="t('order_number_prefix')"
                placeholder="ORD"
              />
            </VCol>

            <!-- Order Number Format -->
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="localData.order_number_format"
                :items="orderNumberFormats"
                :label="t('order_number_format')"
              />
            </VCol>

            <!-- Return Window -->
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model.number="localData.return_window_days"
                :label="t('return_window')"
                type="number"
                min="0"
                max="30"
                suffix="jours"
              />
            </VCol>
          </VRow>
        </VCardText>
      </VCard>
    </VCol>

    <!-- Save Button -->
    <VCol cols="12">
      <VBtn
        color="success"
        prepend-icon="tabler-device-floppy"
        :loading="loading"
        @click="handleSave"
      >
        {{ t('save_business_settings') }}
      </VBtn>
    </VCol>
  </VRow>
</template>
