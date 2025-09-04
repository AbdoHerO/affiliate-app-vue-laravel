<template>
  <div class="pa-6">
    <h1 class="text-h4 mb-6">Debug Translation Keys</h1>
    
    <VCard class="mb-6">
      <VCardTitle>Current Language: {{ currentLocale }}</VCardTitle>
      <VCardText>
        <VBtn 
          v-for="locale in availableLocales" 
          :key="locale"
          :variant="currentLocale === locale ? 'flat' : 'outlined'"
          :color="currentLocale === locale ? 'primary' : 'default'"
          class="me-2"
          @click="changeLocale(locale)"
        >
          {{ locale.toUpperCase() }}
        </VBtn>
      </VCardText>
    </VCard>

    <VCard class="mb-6">
      <VCardTitle>Affiliate Orders Translations Test</VCardTitle>
      <VCardText>
        <div class="d-flex flex-column gap-3">
          <div>
            <strong>affiliate.orders.articles:</strong>
            <VChip class="ms-2" color="primary">{{ t('affiliate.orders.articles') }}</VChip>
          </div>
          <div>
            <strong>affiliate.orders.shipping:</strong>
            <VChip class="ms-2" color="primary">{{ t('affiliate.orders.shipping') }}</VChip>
          </div>
          <div>
            <strong>affiliate.orders.createdOn (with placeholder):</strong>
            <VChip class="ms-2" color="primary">{{ t('affiliate.orders.createdOn', { date: '2024-09-04' }) }}</VChip>
          </div>
          <div>
            <strong>affiliate.orders.deliveryAddress:</strong>
            <VChip class="ms-2" color="primary">{{ t('affiliate.orders.deliveryAddress') }}</VChip>
          </div>
          <div>
            <strong>affiliate.orders.product:</strong>
            <VChip class="ms-2" color="primary">{{ t('affiliate.orders.product') }}</VChip>
          </div>
          <div>
            <strong>affiliate.orders.variant:</strong>
            <VChip class="ms-2" color="primary">{{ t('affiliate.orders.variant') }}</VChip>
          </div>
          <div>
            <strong>affiliate.orders.quantity:</strong>
            <VChip class="ms-2" color="primary">{{ t('affiliate.orders.quantity') }}</VChip>
          </div>
          <div>
            <strong>affiliate.orders.unitPrice:</strong>
            <VChip class="ms-2" color="primary">{{ t('affiliate.orders.unitPrice') }}</VChip>
          </div>
          <div>
            <strong>affiliate.orders.total:</strong>
            <VChip class="ms-2" color="primary">{{ t('affiliate.orders.total') }}</VChip>
          </div>
          <div>
            <strong>affiliate.orders.commissions:</strong>
            <VChip class="ms-2" color="primary">{{ t('affiliate.orders.commissions') }}</VChip>
          </div>
        </div>
      </VCardText>
    </VCard>

    <VCard class="mb-6">
      <VCardTitle>Common Keys Test</VCardTitle>
      <VCardText>
        <div class="d-flex flex-column gap-3">
          <div>
            <strong>common.details:</strong>
            <VChip class="ms-2" color="secondary">{{ t('common.details') }}</VChip>
          </div>
          <div>
            <strong>my_orders:</strong>
            <VChip class="ms-2" color="secondary">{{ t('my_orders') }}</VChip>
          </div>
          <div>
            <strong>order_details:</strong>
            <VChip class="ms-2" color="secondary">{{ t('order_details') }}</VChip>
          </div>
          <div>
            <strong>actions.back:</strong>
            <VChip class="ms-2" color="secondary">{{ t('actions.back') }}</VChip>
          </div>
        </div>
      </VCardText>
    </VCard>

    <VCard>
      <VCardTitle>Tab Simulation</VCardTitle>
      <VCardText>
        <VTabs v-model="activeTab" class="mb-4">
          <VTab value="details">{{ t('common.details') }}</VTab>
          <VTab value="articles">{{ t('affiliate.orders.articles') }}</VTab>
          <VTab value="shipping">{{ t('affiliate.orders.shipping') }}</VTab>
          <VTab value="commissions">{{ t('affiliate.orders.commissions') }}</VTab>
        </VTabs>
        
        <VAlert 
          v-if="activeTab === 'articles'" 
          type="info"
          class="mb-4"
        >
          Current tab: {{ activeTab }} - Translation: {{ t('affiliate.orders.articles') }}
        </VAlert>
        <VAlert 
          v-if="activeTab === 'shipping'" 
          type="info"
          class="mb-4"
        >
          Current tab: {{ activeTab }} - Translation: {{ t('affiliate.orders.shipping') }}
        </VAlert>
      </VCardText>
    </VCard>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

definePage({
  meta: {
    title: 'Debug Translations',
    requiresAuth: false,
  },
})

const { t, locale } = useI18n()
const activeTab = ref('details')

const availableLocales = ['en', 'fr', 'ar']
const currentLocale = ref(locale.value)

const changeLocale = (newLocale: string) => {
  locale.value = newLocale
  currentLocale.value = newLocale
}
</script>
