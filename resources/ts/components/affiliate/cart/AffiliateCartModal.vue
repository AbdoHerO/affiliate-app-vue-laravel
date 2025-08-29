<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAffiliateCartStore } from '@/stores/affiliate/cart'
import { useNotifications } from '@/composables/useNotifications'
// Remove admin store import - we'll use cart store's fetchCities
import type { ClientFinalForm } from '@/stores/affiliate/cart'

interface Props {
  modelValue: boolean
}

interface Emits {
  (e: 'update:modelValue', value: boolean): void
  (e: 'close'): void
  (e: 'success'): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

// Composables
const { t } = useI18n()
const { showSuccess, showError } = useNotifications()
const cartStore = useAffiliateCartStore()

// Cities state
const cities = ref<Array<{ city_id: string; name: string; prices?: { delivered?: number } }>>([])
const citiesLoading = ref(false)

// State
const step = ref<'cart' | 'success'>('cart') // Remove checkout step
const submitting = ref(false)
const orderRef = ref('')

// Delivery calculation
const selectedCity = computed(() => {
  return cities.value.find(city => city.city_id === clientForm.value.city_id)
})

const deliveryFee = computed(() => {
  if (!selectedCity.value?.prices) return 0

  // Try different possible keys for delivery price
  const prices = selectedCity.value.prices
  const deliveryPrice = prices.delivered || prices.delivery || prices['delivered'] || prices['delivery'] || 0

  console.log('🚚 Delivery fee calculation:', {
    cityName: selectedCity.value.name,
    prices: prices,
    deliveryPrice: deliveryPrice
  })

  return Number(deliveryPrice) || 0
})

// New totals calculation
const subtotal = computed(() => {
  return cartStore.subtotal
})

const adjustedCommission = computed(() => {
  // Calculate commission based on command types
  let totalCommission = 0

  // Go through each cart item and calculate commission based on type
  cartStore.items.forEach(item => {
    if (item.type_command === 'exchange') {
      // For exchange orders, commission is 0
      totalCommission += 0
    } else {
      // For order_sample, use the item commission or calculate it
      const itemCommission = item.item_commission ||
        Math.max(0, (item.sell_price || item.product.prix_vente) - item.product.prix_achat)
      totalCommission += itemCommission * item.qty
    }
  })

  // Subtract delivery fee from total commission (can go negative)
  const finalCommission = totalCommission - deliveryFee.value

  console.log('💰 Commission calculation:', {
    items: cartStore.items.map(item => ({
      title: item.product.titre,
      type: item.type_command,
      qty: item.qty,
      commission: item.type_command === 'exchange' ? 0 :
        (item.item_commission || Math.max(0, (item.sell_price || item.product.prix_vente) - item.product.prix_achat))
    })),
    totalCommission,
    deliveryFee: deliveryFee.value,
    finalCommission
  })

  return finalCommission
})

const finalTotal = computed(() => {
  // For exchange orders, client only pays delivery fee
  // For order_sample, client pays products + delivery

  let exchangeTotal = 0
  let orderSampleTotal = 0

  cartStore.items.forEach(item => {
    const itemTotal = (item.sell_price || item.product.prix_vente) * item.qty

    if (item.type_command === 'exchange') {
      exchangeTotal += 0 // Exchange items are free for client
    } else {
      orderSampleTotal += itemTotal // Order sample items are paid
    }
  })

  const clientPayableTotal = orderSampleTotal + deliveryFee.value

  console.log('💰 Final total calculation:', {
    exchangeTotal: exchangeTotal,
    orderSampleTotal: orderSampleTotal,
    deliveryFee: deliveryFee.value,
    clientPayableTotal: clientPayableTotal
  })

  return clientPayableTotal
})

// Client form
const clientForm = ref<ClientFinalForm>({
  nom_complet: '',
  telephone: '',
  city_id: '',
  adresse: '',
  note: ''
})

// Form validation rules
const rules = {
  nom_complet: [
    (v: string) => !!v || 'Le nom complet est requis',
    (v: string) => v.length >= 3 || 'Le nom doit contenir au moins 3 caractères'
  ],
  telephone: [
    (v: string) => !!v || 'Le téléphone est requis',
    (v: string) => /^[0-9+\-\s()]+$/.test(v) || 'Format de téléphone invalide'
  ],
  city_id: [
    (v: string) => !!v || 'La ville est requise'
  ],
  adresse: [
    (v: string) => !!v || 'L\'adresse est requise',
    (v: string) => v.length >= 10 || 'L\'adresse doit contenir au moins 10 caractères'
  ]
}

// Computed
const isOpen = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
})

const canSubmitOrder = computed(() => {
  return cartStore.hasItems &&
         clientForm.value.nom_complet.length >= 3 &&
         clientForm.value.telephone.length > 0 &&
         clientForm.value.city_id.length > 0 &&
         clientForm.value.adresse.length >= 10 &&
         !submitting.value
})

const activeCities = computed(() => {
  return cities.value
})

// Methods
const handleClose = () => {
  isOpen.value = false
  emit('close')
}

// Removed separate checkout step methods

const handleUpdateQty = async (itemKey: string, newQty: number) => {
  try {
    if (newQty <= 0) {
      await cartStore.removeItem(itemKey)
    } else {
      await cartStore.updateItem(itemKey, { qty: newQty })
    }
  } catch (error) {
    // Error is already handled in the store with proper backend messages
    console.error('Update quantity error:', error)
  }
}

const handleRemoveItem = async (itemKey: string) => {
  try {
    await cartStore.removeItem(itemKey)
  } catch (error) {
    // Error is already handled in the store with proper backend messages
    console.error('Remove item error:', error)
  }
}

const handleClearCart = async () => {
  await cartStore.clear()
}

const handleSubmitOrder = async () => {
  if (!canSubmitOrder.value) return

  submitting.value = true

  try {
    // Pass the calculated delivery fee and adjusted commission to backend
    const response = await cartStore.checkout(
      clientForm.value,
      deliveryFee.value,
      adjustedCommission.value
    )

    if (response.success) {
      orderRef.value = response.data.commande.id
      step.value = 'success'

      // Show success notification
      showSuccess(`Commande créée avec succès! Référence: ${response.data.commande.id}`)

      emit('success')
    }
  } catch (error) {
    console.error('Checkout error:', error)
    showError('Erreur lors de la création de la commande. Veuillez réessayer plus tard.')
  } finally {
    submitting.value = false
  }
}

const resetForm = () => {
  clientForm.value = {
    nom_complet: '',
    telephone: '',
    city_id: '',
    adresse: '',
    note: ''
  }
  step.value = 'cart'
  orderRef.value = ''
}

const loadCities = async () => {
  if (citiesLoading.value || cities.value.length > 0) return

  citiesLoading.value = true
  try {
    const citiesData = await cartStore.fetchCities()
    cities.value = citiesData
  } catch (error) {
    console.error('Failed to load cities:', error)
  } finally {
    citiesLoading.value = false
  }
}

// Watch for modal open/close
watch(isOpen, (newValue) => {
  if (newValue) {
    cartStore.fetchCart()
    loadCities()
  } else {
    resetForm()
  }
})

// Watch for city changes to recalculate totals
watch(() => clientForm.value.city_id, (newCityId) => {
  if (newCityId) {
    console.log('🔄 City changed, recalculating totals...', {
      cityId: newCityId,
      deliveryFee: deliveryFee.value,
      adjustedCommission: adjustedCommission.value
    })
  }
})

// Load cities on mount
onMounted(() => {
  loadCities()
})
</script>

<template>
  <VDialog
    v-model="isOpen"
    max-width="900"
    persistent
    scrollable
  >
    <VCard>
      <!-- Header -->
      <VCardTitle class="d-flex align-center justify-space-between">
        <div class="d-flex align-center gap-3">
          <span>
            {{ step === 'cart' ? 'Mon Panier & Commande' : 'Commande Confirmée' }}
          </span>
        </div>
        <VBtn
          icon="tabler-x"
          variant="text"
          size="small"
          @click="handleClose"
        />
      </VCardTitle>

      <VDivider />

      <!-- Content -->
      <VCardText class="pa-6">
        <!-- Cart Step -->
        <div v-if="step === 'cart'">
          <!-- Empty Cart -->
          <div v-if="!cartStore.hasItems" class="text-center py-8">
            <VIcon icon="tabler-shopping-cart-off" size="64" class="mb-4" color="grey" />
            <h3 class="text-h6 mb-2">Votre panier est vide</h3>
            <p class="text-body-2 text-medium-emphasis">Ajoutez des produits depuis le catalogue</p>
          </div>

          <!-- Unified Interface: Client Form + Cart Items -->
          <div v-else>
            <!-- Client Form Section (TOP) -->
            <div class="mb-6">
              <h4 class="text-h6 mb-4">Informations du client final</h4>
              <VForm>
                <VRow>
                  <VCol cols="12" md="6">
                    <VTextField
                      v-model="clientForm.nom_complet"
                      label="Nom complet *"
                      variant="outlined"
                      density="compact"
                      :rules="rules.nom_complet"
                      required
                    />
                  </VCol>
                  <VCol cols="12" md="6">
                    <VTextField
                      v-model="clientForm.telephone"
                      label="Téléphone *"
                      variant="outlined"
                      density="compact"
                      :rules="rules.telephone"
                      required
                    />
                  </VCol>
                  <VCol cols="12" md="6">
                    <VAutocomplete
                      v-model="clientForm.city_id"
                      label="Ville *"
                      variant="outlined"
                      density="compact"
                      :items="cities"
                      item-title="name"
                      item-value="city_id"
                      :loading="citiesLoading"
                      :rules="rules.city_id"
                      required
                      clearable
                      no-data-text="Aucune ville trouvée"
                      placeholder="Tapez pour rechercher une ville..."
                      :menu-props="{ maxHeight: '300px' }"
                      hide-details="auto"
                    />
                  </VCol>
                  <VCol cols="12" md="6">
                    <VTextField
                      v-model="clientForm.adresse"
                      label="Adresse *"
                      variant="outlined"
                      density="compact"
                      :rules="rules.adresse"
                      required
                    />
                  </VCol>
                  <VCol cols="12">
                    <VTextarea
                      v-model="clientForm.note"
                      label="Note (optionnel)"
                      variant="outlined"
                      density="compact"
                      rows="2"
                    />
                  </VCol>
                </VRow>
              </VForm>
            </div>

            <VDivider class="my-6" />

            <!-- Cart Items Section (BOTTOM) -->

            <!-- Items List -->
            <div class="mb-6">
              <h4 class="text-h6 mb-4">Articles dans le panier</h4>
              <div
                v-for="item in cartStore.items"
                :key="item.key"
                class="d-flex align-center gap-4 pa-4 border rounded mb-3"
              >
                <!-- Product Image -->
                <VImg
                  :src="item.product.image || '/images/placeholder.jpg'"
                  width="80"
                  height="80"
                  cover
                  class="rounded"
                />

                <!-- Product Info -->
                <div class="flex-grow-1">
                  <h4 class="text-subtitle-1 mb-1">{{ item.product.titre }}</h4>
                  <div v-if="item.variant" class="text-caption text-medium-emphasis mb-2">
                    {{ item.variant.attribut_principal }}: {{ item.variant.valeur }}
                  </div>
                  <div class="d-flex align-center gap-4">
                    <span class="text-body-2">{{ item.sell_price || item.product.prix_vente }} MAD</span>
                    <span class="text-caption text-success">+{{ ((item.item_commission || 0) / item.qty).toFixed(2) }} MAD commission</span>
                  </div>
                </div>

                <!-- Quantity Controls -->
                <div class="d-flex align-center gap-2">
                  <VBtn
                    icon="tabler-minus"
                    size="small"
                    variant="outlined"
                    @click="handleUpdateQty(item.key, item.qty - 1)"
                  />
                  <span class="px-3">{{ item.qty }}</span>
                  <VBtn
                    icon="tabler-plus"
                    size="small"
                    variant="outlined"
                    :disabled="item.qty >= item.stock_available"
                    @click="handleUpdateQty(item.key, item.qty + 1)"
                  />
                </div>

                <!-- Line Total -->
                <div class="text-right">
                  <div class="text-subtitle-2">{{ item.item_total }} MAD</div>
                </div>

                <!-- Remove Button -->
                <VBtn
                  icon="tabler-trash"
                  size="small"
                  variant="text"
                  color="error"
                  @click="handleRemoveItem(item.key)"
                />
              </div>
            </div>

            <!-- New Totals Calculation -->
            <VCard variant="outlined" class="mb-6">
              <VCardText>
                <!-- Show breakdown by command type -->
                <div v-if="cartStore.items.some(item => item.type_command === 'exchange')" class="mb-3">
                  <div class="text-caption text-medium-emphasis mb-2">Détail par type de commande:</div>

                  <!-- Order Sample Items -->
                  <div v-if="cartStore.items.some(item => item.type_command !== 'exchange')" class="d-flex justify-space-between mb-1">
                    <span class="text-body-2">Articles normaux:</span>
                    <span class="text-body-2">{{ cartStore.items.filter(item => item.type_command !== 'exchange').reduce((sum, item) => sum + ((item.sell_price || item.product.prix_vente) * item.qty), 0).toFixed(2) }} MAD</span>
                  </div>

                  <!-- Exchange Items -->
                  <div v-if="cartStore.items.some(item => item.type_command === 'exchange')" class="d-flex justify-space-between mb-1">
                    <span class="text-body-2">Articles d'échange:</span>
                    <span class="text-body-2 text-warning">Gratuit (échange)</span>
                  </div>

                  <VDivider class="my-2" />
                </div>

                <div class="d-flex justify-space-between mb-2">
                  <span>Sous-total payable:</span>
                  <span class="font-weight-medium">{{ (finalTotal - deliveryFee).toFixed(2) }} MAD</span>
                </div>
                <div v-if="selectedCity" class="d-flex justify-space-between mb-2 text-info">
                  <span>{{ selectedCity.name }} (livraison):</span>
                  <span class="font-weight-medium">+{{ deliveryFee.toFixed(2) }} MAD</span>
                </div>
                <div class="d-flex justify-space-between mb-2" :class="adjustedCommission >= 0 ? 'text-success' : 'text-error'">
                  <span>Commission estimée:</span>
                  <span class="font-weight-medium">
                    {{ adjustedCommission >= 0 ? '+' : '' }}{{ adjustedCommission.toFixed(2) }} MAD
                  </span>
                </div>
                <VDivider class="my-2" />
                <div class="d-flex justify-space-between text-h6">
                  <span>Total à payer par le client:</span>
                  <span class="font-weight-bold text-primary">{{ finalTotal.toFixed(2) }} MAD</span>
                </div>
              </VCardText>
            </VCard>
          </div>
        </div>

        <!-- Checkout step removed - now integrated above -->

        <!-- Success Step -->
        <div v-else-if="step === 'success'" class="text-center py-8">
          <VIcon icon="tabler-check-circle" size="64" class="mb-4" color="success" />
          <h3 class="text-h5 mb-2">Commande créée avec succès!</h3>
          <p class="text-body-1 mb-4">Référence: {{ orderRef }}</p>
          <p class="text-body-2 text-medium-emphasis">
            Votre commande a été transmise et sera traitée par l'équipe admin.
          </p>
        </div>
      </VCardText>

      <!-- Actions -->
      <VCardActions v-if="step !== 'success'" class="pa-6 pt-0">
        <div class="d-flex w-100 gap-3">
          <!-- Unified Actions -->
          <template v-if="step === 'cart'">
            <VBtn
              v-if="cartStore.hasItems"
              variant="outlined"
              @click="handleClearCart"
            >
              Vider le panier
            </VBtn>
            <VSpacer />
            <VBtn
              color="primary"
              :disabled="!canSubmitOrder"
              :loading="submitting"
              @click="handleSubmitOrder"
            >
              Valider la commande
            </VBtn>
          </template>
        </div>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.cursor-pointer {
  cursor: pointer;
}
</style>
