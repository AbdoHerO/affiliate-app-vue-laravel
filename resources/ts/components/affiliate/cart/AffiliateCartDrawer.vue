<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAffiliateCartStore } from '@/stores/affiliate/cart'
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
const cartStore = useAffiliateCartStore()

// Cities state
const cities = ref<Array<{ city_id: string; name: string; prices?: { delivered?: number } }>>([])
const citiesLoading = ref(false)

// State
const submitting = ref(false)
const orderRef = ref('')

// Delivery calculation
const selectedCity = computed(() => {
  return cities.value.find(city => city.city_id === clientForm.value.city_id)
})

const deliveryFee = computed(() => {
  if (!selectedCity.value?.prices?.delivered) return 0
  return selectedCity.value.prices.delivered
})

// New totals calculation
const subtotal = computed(() => {
  return cartStore.subtotal
})

const adjustedCommission = computed(() => {
  const originalCommission = cartStore.estimatedCommission
  return Math.max(0, originalCommission - deliveryFee.value)
})

const finalTotal = computed(() => {
  return subtotal.value + deliveryFee.value
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
         clientForm.value.nom_complet.trim() !== '' &&
         clientForm.value.telephone.trim() !== '' &&
         clientForm.value.city_id !== '' &&
         clientForm.value.adresse.trim() !== '' &&
         !submitting.value
})

// Methods
const updateQuantity = async (itemKey: string, newQty: number) => {
  if (newQty < 1) return
  try {
    await cartStore.updateItem(itemKey, { qty: newQty })
  } catch (error) {
    // Error is already handled in the store with proper backend messages
    console.error('Update quantity error:', error)
  }
}

const removeItem = async (itemKey: string) => {
  try {
    await cartStore.removeItem(itemKey)
  } catch (error) {
    // Error is already handled in the store with proper backend messages
    console.error('Remove item error:', error)
  }
}

const clearCart = async () => {
  await cartStore.clear()
}

const submitOrder = async () => {
  if (!canSubmitOrder.value) return

  submitting.value = true
  try {
    const response = await cartStore.checkout(clientForm.value)
    
    if (response.success) {
      orderRef.value = response.data.commande.id
      emit('success')
      // Reset form
      clientForm.value = {
        nom_complet: '',
        telephone: '',
        city_id: '',
        adresse: '',
        note: ''
      }
    }
  } catch (error) {
    console.error('Checkout error:', error)
  } finally {
    submitting.value = false
  }
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

// Watch for drawer open/close
watch(isOpen, (newValue) => {
  if (newValue) {
    cartStore.fetchCart()
    loadCities()
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
  <VNavigationDrawer
    v-model="isOpen"
    location="end"
    width="500"
    temporary
    class="cart-drawer"
  >
    <!-- Header -->
    <div class="d-flex align-center justify-space-between pa-4 border-b">
      <div class="d-flex align-center">
        <VIcon icon="tabler-shopping-cart" class="me-2" />
        <h3 class="text-h6">Votre panier</h3>
        <VBadge
          v-if="cartStore.count > 0"
          :content="cartStore.count"
          color="primary"
          class="ms-2"
        />
      </div>
      <VBtn
        icon="tabler-x"
        variant="text"
        size="small"
        @click="isOpen = false"
      />
    </div>

    <!-- Loading State -->
    <div v-if="cartStore.loading" class="pa-4">
      <VSkeleton type="list-item" />
      <VSkeleton type="list-item" />
      <VSkeleton type="list-item" />
    </div>

    <!-- Empty State -->
    <div v-else-if="!cartStore.hasItems" class="pa-4 text-center">
      <VIcon icon="tabler-shopping-cart-off" size="64" color="disabled" class="mb-4" />
      <p class="text-body-1 text-disabled">Votre panier est vide</p>
      <p class="text-body-2 text-disabled">Ajoutez des produits depuis le catalogue</p>
    </div>

    <!-- Cart Content -->
    <div v-else class="d-flex flex-column" style="height: calc(100vh - 80px);">
      <!-- Client Form Section (Top) -->
      <div class="pa-4 border-b">
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
              <VSelect
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

      <!-- Items List Section (Middle) -->
      <div class="flex-grow-1 overflow-y-auto">
        <div class="pa-4">
          <h4 class="text-h6 mb-4">Articles dans le panier</h4>
          <div
            v-for="item in cartStore.items"
            :key="item.key"
            class="cart-item mb-4 pa-3 border rounded"
          >
            <!-- Item Header -->
            <div class="d-flex align-start mb-2">
              <VImg
                :src="item.product.image || '/placeholder.jpg'"
                width="60"
                height="60"
                class="rounded me-3"
                cover
              />
              <div class="flex-grow-1">
                <h4 class="text-subtitle-1 mb-1">{{ item.product.titre }}</h4>
                <div v-if="item.variant" class="text-body-2 text-medium-emphasis">
                  {{ item.variant.attribut_principal }}: {{ item.variant.valeur }}
                </div>
                <div class="text-body-2 text-success">
                  Commission: {{ item.product.prix_affilie }} MAD
                </div>
              </div>
              <VBtn
                icon="tabler-trash"
                variant="text"
                size="small"
                color="error"
                @click="removeItem(item.key)"
              />
            </div>

            <!-- Quantity and Price -->
            <div class="d-flex align-center justify-space-between">
              <div class="d-flex align-center">
                <VBtn
                  icon="tabler-minus"
                  variant="outlined"
                  size="small"
                  :disabled="item.qty <= 1"
                  @click="updateQuantity(item.key, item.qty - 1)"
                />
                <span class="mx-3 text-body-1 font-weight-medium">{{ item.qty }}</span>
                <VBtn
                  icon="tabler-plus"
                  variant="outlined"
                  size="small"
                  :disabled="item.qty >= item.stock_available"
                  @click="updateQuantity(item.key, item.qty + 1)"
                />
              </div>
              <div class="text-end">
                <div class="text-body-1 font-weight-medium">{{ item.item_total }} MAD</div>
                <div class="text-body-2 text-medium-emphasis">{{ item.product.prix_vente }} MAD/unité</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Sticky Footer with Totals and Submit -->
      <div class="border-t pa-4" style="position: sticky; bottom: 0; background: white; z-index: 10;">
        <!-- New Totals Calculation -->
        <div class="mb-4">
          <div class="d-flex justify-space-between mb-2">
            <span>Sous-total:</span>
            <span class="font-weight-medium">{{ subtotal.toFixed(2) }} MAD</span>
          </div>
          <div v-if="selectedCity" class="d-flex justify-space-between mb-2 text-info">
            <span>{{ selectedCity.name }} (livraison):</span>
            <span class="font-weight-medium">+{{ deliveryFee.toFixed(2) }} MAD</span>
          </div>
          <div class="d-flex justify-space-between mb-2 text-success">
            <span>Commission estimée:</span>
            <span class="font-weight-medium">+{{ adjustedCommission.toFixed(2) }} MAD</span>
          </div>
          <VDivider class="my-2" />
          <div class="d-flex justify-space-between text-h6">
            <span>Total:</span>
            <span class="font-weight-bold text-primary">{{ finalTotal.toFixed(2) }} MAD</span>
          </div>
        </div>

        <!-- Actions -->
        <div class="d-flex gap-2">
          <VBtn
            variant="outlined"
            color="error"
            @click="clearCart"
            :disabled="!cartStore.hasItems"
          >
            Vider le panier
          </VBtn>
          <VSpacer />
          <VBtn
            color="primary"
            :disabled="!canSubmitOrder"
            :loading="submitting"
            @click="submitOrder"
          >
            Valider la commande
          </VBtn>
        </div>
      </div>
    </div>
  </VNavigationDrawer>
</template>

<style scoped>
.cart-drawer {
  z-index: 1000;
}

.cart-item {
  transition: all 0.2s ease;
}

.cart-item:hover {
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}
</style>
