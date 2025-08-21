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
const cities = ref<Array<{ city_id: string; name: string }>>([])
const citiesLoading = ref(false)

// State
const step = ref<'cart' | 'checkout' | 'success'>('cart')
const submitting = ref(false)
const orderRef = ref('')

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

const canProceedToCheckout = computed(() => {
  return cartStore.hasItems
})

const canSubmitOrder = computed(() => {
  return clientForm.value.nom_complet.length >= 3 &&
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

const handleBackToCart = () => {
  step.value = 'cart'
}

const handleProceedToCheckout = () => {
  step.value = 'checkout'
}

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
    const response = await cartStore.checkout(clientForm.value)

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
          <VIcon 
            v-if="step === 'checkout'" 
            icon="tabler-arrow-left" 
            @click="handleBackToCart"
            class="cursor-pointer"
          />
          <span>
            {{ step === 'cart' ? 'Mon Panier' : step === 'checkout' ? 'Informations Client' : 'Commande Confirmée' }}
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

          <!-- Cart Items -->
          <div v-else>
            <!-- Items List -->
            <div class="mb-6">
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

            <!-- Summary -->
            <VCard variant="outlined" class="mb-6">
              <VCardText>
                <div class="d-flex justify-space-between mb-2">
                  <span>Sous-total:</span>
                  <span class="font-weight-medium">{{ cartStore.subtotal }} MAD</span>
                </div>
                <div class="d-flex justify-space-between mb-2">
                  <span>Commission estimée:</span>
                  <span class="text-success font-weight-medium">+{{ cartStore.estimatedCommission }} MAD</span>
                </div>
                <VDivider class="my-3" />
                <div class="d-flex justify-space-between">
                  <span class="text-h6">Total articles:</span>
                  <span class="text-h6">{{ cartStore.totalQty }}</span>
                </div>
              </VCardText>
            </VCard>
          </div>
        </div>

        <!-- Checkout Step -->
        <div v-else-if="step === 'checkout'">
          <VForm @submit.prevent="handleSubmitOrder">
            <VRow>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="clientForm.nom_complet"
                  label="Nom complet *"
                  :rules="rules.nom_complet"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="clientForm.telephone"
                  label="Téléphone *"
                  :rules="rules.telephone"
                  required
                />
              </VCol>
              <VCol cols="12">
                <VSelect
                  v-model="clientForm.city_id"
                  :items="activeCities"
                  item-title="name"
                  item-value="city_id"
                  label="Ville *"
                  :rules="rules.city_id"
                  required
                />
              </VCol>
              <VCol cols="12">
                <VTextarea
                  v-model="clientForm.adresse"
                  label="Adresse complète *"
                  :rules="rules.adresse"
                  rows="3"
                  required
                />
              </VCol>
              <VCol cols="12">
                <VTextarea
                  v-model="clientForm.note"
                  label="Note (optionnel)"
                  rows="2"
                />
              </VCol>
            </VRow>
          </VForm>
        </div>

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
          <!-- Cart Step Actions -->
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
              :disabled="!canProceedToCheckout"
              @click="handleProceedToCheckout"
            >
              Valider la commande
            </VBtn>
          </template>

          <!-- Checkout Step Actions -->
          <template v-else-if="step === 'checkout'">
            <VBtn
              variant="outlined"
              @click="handleBackToCart"
            >
              Retour au panier
            </VBtn>
            <VSpacer />
            <VBtn
              color="primary"
              :disabled="!canSubmitOrder"
              :loading="submitting"
              @click="handleSubmitOrder"
            >
              Envoyer la commande
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
