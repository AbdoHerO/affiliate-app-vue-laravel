<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useWithdrawalsStore } from '@/stores/admin/withdrawals'
import { useNotifications } from '@/composables/useNotifications'

interface Props {
  isVisible: boolean
  withdrawal: any | null
  action: 'approve' | 'reject' | 'mark_in_payment' | 'mark_paid'
}

interface Emits {
  (e: 'update:isVisible', value: boolean): void
  (e: 'success'): void
  (e: 'closed'): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const withdrawalsStore = useWithdrawalsStore()
const { showSuccess, showError } = useNotifications()

// Local state
const loading = ref(false)
const note = ref('')
const reason = ref('')
const paymentRef = ref('')
const paidAt = ref('')
const evidenceFile = ref<File | null>(null)

// Computed
const dialogTitle = computed(() => {
  switch (props.action) {
    case 'approve': return 'Approuver le retrait'
    case 'reject': return 'Rejeter le retrait'
    case 'mark_in_payment': return 'Marquer en cours de paiement'
    case 'mark_paid': return 'Marquer comme payé'
    default: return 'Action'
  }
})

const dialogIcon = computed(() => {
  switch (props.action) {
    case 'approve': return 'tabler-check'
    case 'reject': return 'tabler-x'
    case 'mark_in_payment': return 'tabler-clock'
    case 'mark_paid': return 'tabler-credit-card'
    default: return 'tabler-help'
  }
})

const dialogColor = computed(() => {
  switch (props.action) {
    case 'approve': return 'success'
    case 'reject': return 'error'
    case 'mark_in_payment': return 'primary'
    case 'mark_paid': return 'success'
    default: return 'primary'
  }
})

const confirmText = computed(() => {
  switch (props.action) {
    case 'approve': return 'Approuver'
    case 'reject': return 'Rejeter'
    case 'mark_in_payment': return 'Marquer en cours'
    case 'mark_paid': return 'Marquer payé'
    default: return 'Confirmer'
  }
})

const isFormValid = computed(() => {
  switch (props.action) {
    case 'reject':
      return reason.value.trim().length > 0
    default:
      return true
  }
})

// Watch for dialog visibility changes
watch(() => props.isVisible, (newVal) => {
  if (newVal) {
    resetForm()
  }
})

// Methods
const resetForm = () => {
  note.value = ''
  reason.value = ''
  paymentRef.value = ''
  paidAt.value = ''
  evidenceFile.value = null
}

const closeDialog = () => {
  emit('update:isVisible', false)
  emit('closed')
}

const handleFileChange = (event: Event) => {
  const target = event.target as HTMLInputElement
  if (target.files && target.files.length > 0) {
    evidenceFile.value = target.files[0]
  }
}

const handleSubmit = async () => {
  if (!isFormValid.value || !props.withdrawal) return

  loading.value = true
  try {
    let result

    switch (props.action) {
      case 'approve':
        result = await withdrawalsStore.approve(props.withdrawal.id, {
          note: note.value || undefined,
        })
        break

      case 'reject':
        result = await withdrawalsStore.reject(props.withdrawal.id, {
          reason: reason.value,
        })
        break

      case 'mark_in_payment':
        result = await withdrawalsStore.markInPayment(props.withdrawal.id, {
          payment_ref: paymentRef.value || undefined,
        })
        break

      case 'mark_paid':
        result = await withdrawalsStore.markPaid(props.withdrawal.id, {
          payment_ref: paymentRef.value || undefined,
          paid_at: paidAt.value || undefined,
          evidence: evidenceFile.value || undefined,
        })
        break

      default:
        throw new Error('Action non supportée')
    }

    if (result.success) {
      showSuccess(result.message)
      emit('success')
      closeDialog()
    } else {
      showError(result.message)
    }
  } catch (error) {
    showError('Une erreur est survenue')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <VDialog
    :model-value="isVisible"
    max-width="600"
    persistent
    @update:model-value="emit('update:isVisible', $event)"
    @click:outside="closeDialog"
  >
    <VCard>
      <VCardTitle class="d-flex align-center gap-2">
        <VIcon :icon="dialogIcon" :color="dialogColor" />
        {{ dialogTitle }}
      </VCardTitle>

      <VCardText>
        <!-- Withdrawal Info -->
        <VAlert
          v-if="withdrawal"
          :color="dialogColor"
          variant="tonal"
          class="mb-4"
        >
          <div class="d-flex justify-space-between align-center">
            <div>
              <div class="text-body-1 font-weight-medium">{{ withdrawal.user?.nom_complet }}</div>
              <div class="text-body-2">{{ withdrawal.user?.email }}</div>
            </div>
            <div class="text-end">
              <div class="text-h6">{{ withdrawal.amount }} MAD</div>
              <div class="text-caption">{{ withdrawal.commission_count }} commission(s)</div>
            </div>
          </div>
        </VAlert>

        <!-- Form Fields -->
        <VForm @submit.prevent="handleSubmit">
          <!-- Approve Action -->
          <div v-if="action === 'approve'">
            <VTextarea
              v-model="note"
              label="Note (optionnelle)"
              placeholder="Ajouter une note..."
              rows="3"
              auto-grow
            />
          </div>

          <!-- Reject Action -->
          <div v-else-if="action === 'reject'">
            <VTextarea
              v-model="reason"
              label="Raison du rejet *"
              placeholder="Expliquer la raison du rejet..."
              rows="3"
              auto-grow
              :rules="[v => !!v || 'La raison est requise']"
              required
            />
          </div>

          <!-- Mark In Payment Action -->
          <div v-else-if="action === 'mark_in_payment'">
            <VTextField
              v-model="paymentRef"
              label="Référence de paiement (optionnelle)"
              placeholder="REF-12345"
            />
          </div>

          <!-- Mark Paid Action -->
          <div v-else-if="action === 'mark_paid'">
            <VRow>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="paymentRef"
                  label="Référence de paiement"
                  placeholder="REF-12345"
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="paidAt"
                  label="Date de paiement"
                  type="datetime-local"
                />
              </VCol>
            </VRow>

            <VFileInput
              label="Preuve de paiement (optionnelle)"
              accept="image/*,.pdf"
              prepend-icon="tabler-paperclip"
              @change="handleFileChange"
            />

            <VAlert
              color="info"
              variant="tonal"
              class="mt-2"
            >
              <VIcon icon="tabler-info-circle" class="me-2" />
              Formats acceptés: PDF, JPG, PNG (max 5MB)
            </VAlert>
          </div>
        </VForm>
      </VCardText>

      <VCardActions>
        <VSpacer />
        <VBtn
          variant="tonal"
          @click="closeDialog"
        >
          Annuler
        </VBtn>
        <VBtn
          :color="dialogColor"
          :loading="loading"
          :disabled="!isFormValid"
          @click="handleSubmit"
        >
          {{ confirmText }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
