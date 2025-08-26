<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'
import { useAffiliateOrdersStore } from '@/stores/affiliate/orders'
import { useNotifications } from '@/composables/useNotifications'
import Breadcrumbs from '@/components/common/Breadcrumbs.vue'

definePage({
  meta: {
    requiresAuth: true,
    requiresRole: 'affiliate',
  },
})

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const { showError } = useNotifications()

// Store
const ordersStore = useAffiliateOrdersStore()
const { currentOrder, loading, error } = storeToRefs(ordersStore)

// Local state
const activeTab = ref('details')

// Computed
const breadcrumbs = computed(() => [
  { title: t('dashboard'), to: { name: 'affiliate-dashboard' } },
  { title: t('my_orders'), to: { name: 'affiliate-orders' } },
  { title: currentOrder.value?.numero_commande || t('order_details'), active: true },
])

const orderTotal = computed(() => {
  if (!currentOrder.value?.articles) return 0
  return currentOrder.value.articles.reduce((total, article) => {
    return total + (article.quantite * article.prix_unitaire)
  }, 0)
})

// Methods
const fetchOrder = async () => {
  try {
    await ordersStore.fetchOrder(route.params.id as string)
  } catch (err) {
    showError(t('order_loading_error'))
    router.push({ name: 'affiliate-orders' })
  }
}

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

const goBack = () => {
  router.push({ name: 'affiliate-orders' })
}

// Lifecycle
onMounted(() => {
  fetchOrder()
})
</script>

<template>
  <div>
    <!-- Breadcrumbs -->
    <Breadcrumbs :items="breadcrumbs" />

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-8">
      <VProgressCircular
        indeterminate
        color="primary"
        size="64"
      />
      <p class="text-body-1 mt-4">Chargement de la commande...</p>
    </div>

    <!-- Order Details -->
    <div v-else-if="currentOrder">
      <!-- Header -->
      <div class="d-flex justify-space-between align-center mb-6">
        <div>
          <h1 class="text-h4 font-weight-bold mb-1">
            Commande #{{ currentOrder.id.toString().slice(-8) }}
          </h1>
          <div class="d-flex align-center gap-4">
            <VChip
              :color="ordersStore.getStatusColor(currentOrder.statut)"
              variant="tonal"
            >
              {{ ordersStore.getStatusLabel(currentOrder.statut) }}
            </VChip>
            <span class="text-body-2 text-medium-emphasis">
              {{ t('affiliate.orders.createdOn', { date: formatDate(currentOrder.created_at) }) }}
            </span>
          </div>
        </div>
        <VBtn
          variant="outlined"
          prepend-icon="tabler-arrow-left"
          @click="goBack"
        >
          {{ t('actions.back') }}
        </VBtn>
      </div>

      <!-- Tabs -->
      <VTabs v-model="activeTab" class="mb-6">
        <VTab value="details">{{ t('common.details') }}</VTab>
        <VTab value="articles">{{ t('affiliate.orders.articles') }}</VTab>
        <VTab value="shipping">{{ t('affiliate.orders.shipping') }}</VTab>
        <VTab value="commissions">Commissions</VTab>
      </VTabs>

      <VWindow v-model="activeTab">
        <!-- Details Tab -->
        <VWindowItem value="details">
          <VRow>
            <!-- Order Information -->
            <VCol cols="12" md="6">
              <VCard>
                <VCardTitle>Informations de la commande</VCardTitle>
                <VCardText>
                  <VList>
                    <VListItem>
                      <VListItemTitle>Référence</VListItemTitle>
                      <VListItemSubtitle>#{{ currentOrder.id }}</VListItemSubtitle>
                    </VListItem>
                    <VListItem>
                      <VListItemTitle>{{ t('form.status') }}</VListItemTitle>
                      <VListItemSubtitle>
                        <VChip
                          :color="ordersStore.getStatusColor(currentOrder.statut)"
                          size="small"
                          variant="tonal"
                        >
                          {{ ordersStore.getStatusLabel(currentOrder.statut) }}
                        </VChip>
                      </VListItemSubtitle>
                    </VListItem>
                    <VListItem>
                      <VListItemTitle>Boutique</VListItemTitle>
                      <VListItemSubtitle>{{ currentOrder.boutique?.nom || 'N/A' }}</VListItemSubtitle>
                    </VListItem>
                    <VListItem>
                      <VListItemTitle>Mode de paiement</VListItemTitle>
                      <VListItemSubtitle>{{ currentOrder.mode_paiement || 'N/A' }}</VListItemSubtitle>
                    </VListItem>
                    <VListItem>
                      <VListItemTitle>Total HT</VListItemTitle>
                      <VListItemSubtitle>{{ formatCurrency(currentOrder.total_ht) }}</VListItemSubtitle>
                    </VListItem>
                    <VListItem>
                      <VListItemTitle>Total TTC</VListItemTitle>
                      <VListItemSubtitle class="font-weight-bold">{{ formatCurrency(currentOrder.total_ttc) }}</VListItemSubtitle>
                    </VListItem>
                  </VList>
                </VCardText>
              </VCard>
            </VCol>

            <!-- Client Information -->
            <VCol cols="12" md="6">
              <VCard>
                <VCardTitle>Informations client</VCardTitle>
                <VCardText>
                  <VList>
                    <VListItem v-if="currentOrder.client">
                      <VListItemTitle>Client</VListItemTitle>
                      <VListItemSubtitle>{{ currentOrder.client.nom_complet }}</VListItemSubtitle>
                    </VListItem>
                    <VListItem v-if="currentOrder.clientFinal">
                      <VListItemTitle>Client final</VListItemTitle>
                      <VListItemSubtitle>
                        {{ currentOrder.clientFinal.nom_complet }}<br>
                        {{ currentOrder.clientFinal.telephone }}<br>
                        {{ currentOrder.clientFinal.email }}
                      </VListItemSubtitle>
                    </VListItem>
                    <VListItem v-if="currentOrder.adresse">
                      <VListItemTitle>{{ t('affiliate.orders.deliveryAddress') }}</VListItemTitle>
                      <VListItemSubtitle>
                        {{ currentOrder.adresse.adresse }}<br>
                        {{ currentOrder.adresse.ville }}
                      </VListItemSubtitle>
                    </VListItem>
                  </VList>
                </VCardText>
              </VCard>
            </VCol>
          </VRow>

          <!-- Notes -->
          <VCard v-if="currentOrder.notes" class="mt-6">
            <VCardTitle>Notes</VCardTitle>
            <VCardText>
              <p class="text-body-1">{{ currentOrder.notes }}</p>
            </VCardText>
          </VCard>
        </VWindowItem>

        <!-- Articles Tab -->
        <VWindowItem value="articles">
          <VCard>
            <VCardTitle>Articles commandés</VCardTitle>
            <VDataTable
              :headers="[
                { title: t('affiliate.orders.product'), key: 'produit.titre' },
                { title: t('affiliate.orders.variant'), key: 'variante.nom' },
                { title: t('affiliate.orders.quantity'), key: 'quantite' },
                { title: t('affiliate.orders.unitPrice'), key: 'prix_unitaire' },
                { title: t('affiliate.orders.total'), key: 'total' },
              ]"
              :items="currentOrder.articles || []"
              hide-default-footer
            >
              <template #item.produit.titre="{ item }">
                <div class="font-weight-medium">{{ item.produit?.titre || 'N/A' }}</div>
              </template>
              <template #item.variante.nom="{ item }">
                <VChip
                  v-if="item.variante"
                  size="small"
                  variant="tonal"
                  color="info"
                >
                  {{ item.variante.nom }}
                </VChip>
                <span v-else class="text-medium-emphasis">-</span>
              </template>
              <template #item.quantite="{ item }">
                <span class="font-weight-medium">{{ item.quantite }}</span>
              </template>
              <template #item.prix_unitaire="{ item }">
                {{ formatCurrency(item.prix_unitaire) }}
              </template>
              <template #item.total="{ item }">
                <span class="font-weight-bold">
                  {{ formatCurrency(item.quantite * item.prix_unitaire) }}
                </span>
              </template>
            </VDataTable>
          </VCard>
        </VWindowItem>

        <!-- Shipping Tab -->
        <VWindowItem value="shipping">
          <VCard>
            <VCardTitle>Informations d'expédition</VCardTitle>
            <VCardText>
              <div v-if="currentOrder.shippingParcel">
                <p class="text-body-1 mb-4">Colis créé et en cours de traitement</p>
                <!-- Add shipping details here -->
              </div>
              <div v-else>
                <VAlert
                  type="info"
                  variant="tonal"
                >
                  Cette commande n'a pas encore été expédiée.
                </VAlert>
              </div>
            </VCardText>
          </VCard>
        </VWindowItem>

        <!-- Commissions Tab -->
        <VWindowItem value="commissions">
          <VCard>
            <VCardTitle>Commissions</VCardTitle>
            <VCardText>
              <div v-if="currentOrder.commissions?.length">
                <VDataTable
                  :headers="[
                    { title: t('affiliate.orders.type'), key: 'type' },
                    { title: t('affiliate.orders.baseAmount'), key: 'base_amount' },
                    { title: t('affiliate.orders.rate'), key: 'rate' },
                    { title: t('affiliate.orders.commission'), key: 'amount' },
                    { title: t('form.status'), key: 'status' },
                  ]"
                  :items="currentOrder.commissions"
                  hide-default-footer
                >
                  <template #item.base_amount="{ item }">
                    {{ formatCurrency(item.base_amount) }}
                  </template>
                  <template #item.rate="{ item }">
                    {{ (item.rate * 100).toFixed(2) }}%
                  </template>
                  <template #item.amount="{ item }">
                    <span class="font-weight-bold">{{ formatCurrency(item.amount) }}</span>
                  </template>
                  <template #item.status="{ item }">
                    <VChip
                      size="small"
                      variant="tonal"
                      :color="item.status === 'paid' ? 'success' : item.status === 'eligible' ? 'info' : 'warning'"
                    >
                      {{ item.status }}
                    </VChip>
                  </template>
                </VDataTable>
              </div>
              <div v-else>
                <VAlert
                  type="info"
                  variant="tonal"
                >
                  Aucune commission générée pour cette commande.
                </VAlert>
              </div>
            </VCardText>
          </VCard>
        </VWindowItem>
      </VWindow>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="text-center py-8">
      <VIcon
        icon="tabler-alert-circle"
        size="64"
        class="text-error mb-4"
      />
      <h3 class="text-h6 mb-2">{{ t('affiliate_order_error_title') }}</h3>
      <p class="text-body-2 text-medium-emphasis mb-4">{{ error }}</p>
      <VBtn
        color="primary"
        @click="goBack"
      >
        {{ t('affiliate.orders.backToOrders') }}
      </VBtn>
    </div>
  </div>
</template>
