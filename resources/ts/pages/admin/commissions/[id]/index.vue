<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'
import { useCommissionsStore, type Commission } from '@/stores/admin/commissions'
import Breadcrumbs from '@/components/common/Breadcrumbs.vue'
import { useQuickConfirm } from '@/composables/useConfirmAction'
import { useNotifications } from '@/composables/useNotifications'

definePage({
  meta: {
    requiresAuth: true,
    requiresRole: 'admin',
  },
})

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const { showSuccess, showError } = useNotifications()
const { quickConfirm } = useQuickConfirm()

// Store
const commissionsStore = useCommissionsStore()
const { currentCommission, loading } = storeToRefs(commissionsStore)

// Local state
const showRejectDialog = ref(false)
const showAdjustDialog = ref(false)
const rejectReason = ref('')
const adjustAmount = ref(0)
const adjustNote = ref('')

// Computed
const breadcrumbs = computed(() => [
  { title: t('dashboard'), to: '/admin/dashboard' },
  { title: t('commissions'), to: '/admin/commissions' },
  { title: `Commission ${route.params.id}`, to: `/admin/commissions/${route.params.id}` },
])

const commission = computed(() => currentCommission.value)

// Methods
const fetchCommission = async () => {
  await commissionsStore.fetchCommission(route.params.id as string)
}

const handleApprove = async () => {
  if (!commission.value) return

  const confirmed = await quickConfirm({
    title: 'Approuver la commission',
    message: `Êtes-vous sûr de vouloir approuver cette commission de ${commission.value.amount} ${commission.value.currency} ?`,
    confirmText: 'Approuver',
    confirmColor: 'success',
  })

  if (confirmed) {
    const result = await commissionsStore.approveCommission(commission.value.id)
    if (result.success) {
      showSuccess(result.message)
      await fetchCommission() // Refresh data
    } else {
      showError(result.message)
    }
  }
}

const openRejectDialog = () => {
  if (!commission.value) return
  rejectReason.value = ''
  showRejectDialog.value = true
}

const handleReject = async () => {
  if (!commission.value || !rejectReason.value.trim()) return

  const result = await commissionsStore.rejectCommission(commission.value.id, rejectReason.value)
  if (result.success) {
    showSuccess(result.message)
    showRejectDialog.value = false
    await fetchCommission() // Refresh data
  } else {
    showError(result.message)
  }
}

const openAdjustDialog = () => {
  if (!commission.value) return
  adjustAmount.value = commission.value.amount
  adjustNote.value = ''
  showAdjustDialog.value = true
}

const handleAdjust = async () => {
  if (!commission.value || !adjustNote.value.trim()) return

  const result = await commissionsStore.adjustCommission(
    commission.value.id,
    adjustAmount.value,
    adjustNote.value
  )
  if (result.success) {
    showSuccess(result.message)
    showAdjustDialog.value = false
    await fetchCommission() // Refresh data
  } else {
    showError(result.message)
  }
}

const goBack = () => {
  router.push('/admin/commissions')
}

// Load data on mount
onMounted(async () => {
  await fetchCommission()
})
</script>

<template>
  <div>
    <!-- Breadcrumbs -->
    <Breadcrumbs :items="breadcrumbs" />

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-8">
      <VProgressCircular indeterminate color="primary" />
    </div>

    <!-- Commission Details -->
    <div v-else-if="commission">
      <!-- Header -->
      <div class="d-flex justify-space-between align-center mb-6">
        <div>
          <h1 class="text-h4 font-weight-bold mb-1">
            Commission {{ commission.id.slice(-8) }}
          </h1>
          <div class="d-flex align-center gap-2">
            <VChip
              :color="commission.status_badge.color"
              variant="tonal"
            >
              {{ commission.status_badge.text }}
            </VChip>
            <span class="text-h5 font-weight-bold text-success">
              {{ commission.amount }} {{ commission.currency }}
            </span>
          </div>
        </div>
        
        <div class="d-flex gap-3">
          <VBtn
            color="grey"
            variant="outlined"
            prepend-icon="tabler-arrow-left"
            @click="goBack"
          >
            Retour
          </VBtn>
          
          <VBtn
            v-if="commission.can_be_approved"
            color="success"
            variant="elevated"
            prepend-icon="tabler-check"
            @click="handleApprove"
          >
            Approuver
          </VBtn>
          
          <VBtn
            v-if="commission.can_be_rejected"
            color="error"
            variant="elevated"
            prepend-icon="tabler-x"
            @click="openRejectDialog"
          >
            Rejeter
          </VBtn>
          
          <VBtn
            v-if="commission.can_be_adjusted"
            color="warning"
            variant="elevated"
            prepend-icon="tabler-edit"
            @click="openAdjustDialog"
          >
            Ajuster
          </VBtn>
        </div>
      </div>

      <!-- Content Cards -->
      <VRow>
        <!-- Commission Details -->
        <VCol cols="12" md="6">
          <VCard>
            <VCardTitle>
              <VIcon icon="tabler-percentage" class="me-2" />
              Détails de la commission
            </VCardTitle>
            <VCardText>
              <VList>
                <VListItem>
                  <VListItemTitle>Montant de base</VListItemTitle>
                  <VListItemSubtitle>{{ commission.base_amount }} {{ commission.currency }}</VListItemSubtitle>
                </VListItem>
                
                <VListItem v-if="commission.rate">
                  <VListItemTitle>Taux</VListItemTitle>
                  <VListItemSubtitle>{{ commission.rate }}%</VListItemSubtitle>
                </VListItem>
                
                <VListItem v-if="commission.qty">
                  <VListItemTitle>Quantité</VListItemTitle>
                  <VListItemSubtitle>{{ commission.qty }}</VListItemSubtitle>
                </VListItem>
                
                <VListItem>
                  <VListItemTitle>Montant final</VListItemTitle>
                  <VListItemSubtitle class="text-success font-weight-bold">
                    {{ commission.amount }} {{ commission.currency }}
                  </VListItemSubtitle>
                </VListItem>
                
                <VListItem v-if="commission.rule_code">
                  <VListItemTitle>Règle appliquée</VListItemTitle>
                  <VListItemSubtitle>{{ commission.rule_code }}</VListItemSubtitle>
                </VListItem>
                
                <VListItem v-if="commission.eligible_at">
                  <VListItemTitle>Éligible le</VListItemTitle>
                  <VListItemSubtitle>
                    {{ new Date(commission.eligible_at).toLocaleString('fr-FR') }}
                  </VListItemSubtitle>
                </VListItem>
                
                <VListItem v-if="commission.approved_at">
                  <VListItemTitle>Approuvée le</VListItemTitle>
                  <VListItemSubtitle>
                    {{ new Date(commission.approved_at).toLocaleString('fr-FR') }}
                  </VListItemSubtitle>
                </VListItem>
                
                <VListItem v-if="commission.paid_at">
                  <VListItemTitle>Payée le</VListItemTitle>
                  <VListItemSubtitle>
                    {{ new Date(commission.paid_at).toLocaleString('fr-FR') }}
                  </VListItemSubtitle>
                </VListItem>
              </VList>
            </VCardText>
          </VCard>
        </VCol>

        <!-- Affiliate Info -->
        <VCol cols="12" md="6">
          <VCard>
            <VCardTitle>
              <VIcon icon="tabler-user" class="me-2" />
              Informations de l'affilié
            </VCardTitle>
            <VCardText>
              <div v-if="commission.affiliate" class="d-flex align-center mb-4">
                <VAvatar size="48" class="me-4">
                  <VIcon icon="tabler-user" />
                </VAvatar>
                <div>
                  <h6 class="text-h6">{{ commission.affiliate.nom_complet }}</h6>
                  <p class="text-body-2 mb-0">{{ commission.affiliate.email }}</p>
                  <p v-if="commission.affiliate.telephone" class="text-caption mb-0">
                    {{ commission.affiliate.telephone }}
                  </p>
                </div>
              </div>
            </VCardText>
          </VCard>
        </VCol>

        <!-- Order Info -->
        <VCol cols="12" md="6">
          <VCard>
            <VCardTitle>
              <VIcon icon="tabler-shopping-cart" class="me-2" />
              Informations de la commande
            </VCardTitle>
            <VCardText>
              <div v-if="commission.commande">
                <VList>
                  <VListItem>
                    <VListItemTitle>ID Commande</VListItemTitle>
                    <VListItemSubtitle>
                      <VChip
                        :to="`/admin/orders/${commission.commande.id}`"
                        color="primary"
                        variant="outlined"
                        size="small"
                      >
                        {{ commission.commande.id.slice(-8) }}
                      </VChip>
                    </VListItemSubtitle>
                  </VListItem>
                  
                  <VListItem>
                    <VListItemTitle>Statut</VListItemTitle>
                    <VListItemSubtitle>{{ commission.commande.statut }}</VListItemSubtitle>
                  </VListItem>
                  
                  <VListItem>
                    <VListItemTitle>Total TTC</VListItemTitle>
                    <VListItemSubtitle>
                      {{ commission.commande.total_ttc }} {{ commission.commande.devise }}
                    </VListItemSubtitle>
                  </VListItem>
                  
                  <VListItem>
                    <VListItemTitle>Date de création</VListItemTitle>
                    <VListItemSubtitle>
                      {{ new Date(commission.commande.created_at).toLocaleString('fr-FR') }}
                    </VListItemSubtitle>
                  </VListItem>
                </VList>
              </div>
            </VCardText>
          </VCard>
        </VCol>

        <!-- Notes -->
        <VCol v-if="commission.notes" cols="12" md="6">
          <VCard>
            <VCardTitle>
              <VIcon icon="tabler-notes" class="me-2" />
              Notes
            </VCardTitle>
            <VCardText>
              <pre class="text-wrap">{{ commission.notes }}</pre>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>
    </div>

    <!-- Not Found -->
    <div v-else class="text-center py-8">
      <VIcon icon="tabler-alert-circle" size="64" class="mb-4" color="error" />
      <h3 class="text-h6 mb-2">Commission non trouvée</h3>
      <p class="text-body-2 text-medium-emphasis mb-4">
        La commission demandée n'existe pas ou a été supprimée.
      </p>
      <VBtn color="primary" @click="goBack">
        Retour aux commissions
      </VBtn>
    </div>

    <!-- Reject Dialog -->
    <VDialog v-model="showRejectDialog" max-width="500">
      <VCard>
        <VCardTitle>
          <span class="text-h6">Rejeter la commission</span>
        </VCardTitle>
        
        <VCardText>
          <p class="mb-4">
            Vous êtes sur le point de rejeter cette commission. Veuillez indiquer la raison :
          </p>
          
          <VTextarea
            v-model="rejectReason"
            label="Raison du rejet"
            placeholder="Expliquez pourquoi cette commission est rejetée..."
            rows="3"
            required
          />
        </VCardText>
        
        <VCardActions>
          <VSpacer />
          <VBtn
            color="grey"
            variant="text"
            @click="showRejectDialog = false"
          >
            Annuler
          </VBtn>
          <VBtn
            color="error"
            variant="elevated"
            :disabled="!rejectReason.trim()"
            @click="handleReject"
          >
            Rejeter
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Adjust Dialog -->
    <VDialog v-model="showAdjustDialog" max-width="500">
      <VCard>
        <VCardTitle>
          <span class="text-h6">Ajuster la commission</span>
        </VCardTitle>
        
        <VCardText>
          <p class="mb-4">
            Montant actuel : <strong>{{ commission?.amount }} {{ commission?.currency }}</strong>
          </p>
          
          <VTextField
            v-model.number="adjustAmount"
            label="Nouveau montant"
            type="number"
            step="0.01"
            min="0"
            suffix="MAD"
            required
            class="mb-4"
          />
          
          <VTextarea
            v-model="adjustNote"
            label="Raison de l'ajustement"
            placeholder="Expliquez pourquoi cette commission est ajustée..."
            rows="3"
            required
          />
        </VCardText>
        
        <VCardActions>
          <VSpacer />
          <VBtn
            color="grey"
            variant="text"
            @click="showAdjustDialog = false"
          >
            Annuler
          </VBtn>
          <VBtn
            color="warning"
            variant="elevated"
            :disabled="!adjustNote.trim()"
            @click="handleAdjust"
          >
            Ajuster
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
