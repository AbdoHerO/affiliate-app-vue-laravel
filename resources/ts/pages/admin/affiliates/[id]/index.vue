<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAffiliatesStore } from '@/stores/admin/affiliates'
import { useConfirmAction } from '@/composables/useConfirmAction'
import { useNotifications } from '@/composables/useNotifications'

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
    await affiliatesStore.changeTier(affiliate.value.id, selectedTier.value, 'Changement de tier depuis l\'interface admin')
    showSuccess('Tier modifié avec succès')
    showTierDialog.value = false
    selectedTier.value = ''
  } catch (error: any) {
    showError(error.message || 'Erreur lors du changement de tier')
  }
}

const toggleBlock = async (action: 'block' | 'unblock') => {
  if (!affiliate.value) return

  try {
    await affiliatesStore.toggleBlock(
      affiliate.value.id, 
      action, 
      action === 'block' ? blockReason.value : 'Débloqué depuis l\'interface admin'
    )
    showSuccess(
      action === 'block' ? 'Affilié bloqué avec succès' : 'Affilié débloqué avec succès'
    )
    showBlockDialog.value = false
    blockReason.value = ''
  } catch (error: any) {
    showError(error.message || 'Erreur lors de l\'opération')
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
      return 'Actif'
    case 'inactif':
      return 'Inactif'
    case 'bloque':
      return 'Bloqué'
    case 'suspendu':
      return 'Suspendu'
    case 'resilie':
      return 'Résilié'
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
      return 'Validé'
    case 'en_attente':
      return 'En attente'
    case 'refuse':
      return 'Refusé'
    case 'non_requis':
      return 'Non requis'
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
      <p class="mt-4">Chargement de l'affilié...</p>
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
            Changer Tier
          </VBtn>
          
          <VBtn
            v-if="affiliate.statut !== 'bloque'"
            color="error"
            variant="outlined"
            @click="showBlockDialog = true"
          >
            <VIcon start icon="tabler-ban" />
            Bloquer
          </VBtn>

          <VBtn
            v-else
            color="success"
            variant="outlined"
            @click="toggleBlock('unblock')"
          >
            <VIcon start icon="tabler-check" />
            Débloquer
          </VBtn>

          <VBtn
            color="primary"
            variant="elevated"
            @click="router.push({ name: 'admin-affiliate-edit', params: { id: affiliate.id } })"
          >
            <VIcon start icon="tabler-edit" />
            Modifier
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
          Profil
        </VTab>
        <VTab value="performance">
          <VIcon start icon="tabler-chart-line" />
          Performance
        </VTab>
        <VTab value="orders">
          <VIcon start icon="tabler-shopping-cart" />
          Commandes
        </VTab>
        <VTab value="commissions">
          <VIcon start icon="tabler-currency-dollar" />
          Commissions
        </VTab>
      </VTabs>

      <!-- Tab Content -->
      <VWindow v-model="activeTab">
        <!-- Profile Tab -->
        <VWindowItem value="profile">
          <VRow>
            <VCol cols="12" md="8">
              <VCard>
                <VCardTitle>Informations Personnelles</VCardTitle>
                <VCardText>
                  <VRow>
                    <VCol cols="12" md="6">
                      <div class="mb-4">
                        <div class="text-body-2 text-medium-emphasis mb-1">Nom complet</div>
                        <div class="text-h6">{{ affiliate.nom_complet }}</div>
                      </div>
                      <div class="mb-4">
                        <div class="text-body-2 text-medium-emphasis mb-1">Email</div>
                        <div class="text-body-1">{{ affiliate.email }}</div>
                      </div>
                      <div class="mb-4">
                        <div class="text-body-2 text-medium-emphasis mb-1">Téléphone</div>
                        <div class="text-body-1">{{ affiliate.telephone || 'Non renseigné' }}</div>
                      </div>
                    </VCol>
                    <VCol cols="12" md="6">
                      <div class="mb-4">
                        <div class="text-body-2 text-medium-emphasis mb-1">Statut utilisateur</div>
                        <VChip :color="getStatusColor(affiliate.statut)" variant="tonal">
                          {{ getStatusText(affiliate.statut) }}
                        </VChip>
                      </div>
                      <div class="mb-4">
                        <div class="text-body-2 text-medium-emphasis mb-1">Statut KYC</div>
                        <VChip :color="getKycStatusColor(affiliate.kyc_statut)" variant="tonal">
                          {{ getKycStatusText(affiliate.kyc_statut) }}
                        </VChip>
                      </div>
                      <div class="mb-4">
                        <div class="text-body-2 text-medium-emphasis mb-1">Date d'inscription</div>
                        <div class="text-body-1">{{ formatDate(affiliate.created_at) }}</div>
                      </div>
                    </VCol>
                  </VRow>

                  <VDivider class="my-4" />

                  <div class="mb-4">
                    <div class="text-body-2 text-medium-emphasis mb-1">Adresse</div>
                    <div class="text-body-1">{{ affiliate.adresse || 'Non renseignée' }}</div>
                  </div>
                </VCardText>
              </VCard>

              <!-- Affiliate Profile Card -->
              <VCard class="mt-4">
                <VCardTitle>Profil Affilié</VCardTitle>
                <VCardText>
                  <VRow>
                    <VCol cols="12" md="6">
                      <div class="mb-4">
                        <div class="text-body-2 text-medium-emphasis mb-1">Tier</div>
                        <VChip
                          v-if="affiliate.profil_affilie?.gamme"
                          :color="affiliate.profil_affilie.gamme.code === 'BASIC' ? 'info' : affiliate.profil_affilie.gamme.code === 'SILVER' ? 'warning' : 'success'"
                          variant="tonal"
                        >
                          {{ affiliate.profil_affilie.gamme.libelle }}
                        </VChip>
                        <span v-else class="text-medium-emphasis">Non défini</span>
                      </div>
                      <div class="mb-4">
                        <div class="text-body-2 text-medium-emphasis mb-1">Statut affilié</div>
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
                        <div class="text-body-2 text-medium-emphasis mb-1">Points</div>
                        <div class="text-h6">{{ affiliate.profil_affilie?.points || 0 }}</div>
                      </div>
                      <div class="mb-4">
                        <div class="text-body-2 text-medium-emphasis mb-1">RIB</div>
                        <div class="text-body-1">{{ affiliate.profil_affilie?.rib || 'Non renseigné' }}</div>
                      </div>
                    </VCol>
                  </VRow>

                  <div v-if="affiliate.profil_affilie?.notes_interne" class="mb-4">
                    <div class="text-body-2 text-medium-emphasis mb-1">Notes internes</div>
                    <div class="text-body-1" style="white-space: pre-line;">
                      {{ affiliate.profil_affilie.notes_interne }}
                    </div>
                  </div>
                </VCardText>
              </VCard>
            </VCol>
            <VCol cols="12" md="4">
              <VCard>
                <VCardTitle>Statistiques Rapides</VCardTitle>
                <VCardText>
                  <div class="mb-4">
                    <div class="text-body-2 text-medium-emphasis mb-1">Total Commandes</div>
                    <div class="text-h5">{{ affiliate.orders_count || 0 }}</div>
                  </div>
                  <div class="mb-4">
                    <div class="text-body-2 text-medium-emphasis mb-1">Total Commissions</div>
                    <div class="text-h5">{{ affiliate.total_commissions ? formatCurrency(affiliate.total_commissions) : '0 MAD' }}</div>
                  </div>
                  <div class="mb-4">
                    <div class="text-body-2 text-medium-emphasis mb-1">Commissions en cours</div>
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
            <VCardTitle>Performance de l'Affilié</VCardTitle>
            <VCardText>
              <div v-if="performance">
                <VRow>
                  <VCol cols="12" md="4">
                    <VCard variant="tonal" color="primary">
                      <VCardText>
                        <div class="text-h4">{{ performance.orders.total }}</div>
                        <div class="text-body-2">Total Commandes</div>
                        <div class="text-caption">{{ performance.orders.this_month }} ce mois</div>
                      </VCardText>
                    </VCard>
                  </VCol>
                  <VCol cols="12" md="4">
                    <VCard variant="tonal" color="success">
                      <VCardText>
                        <div class="text-h4">{{ formatCurrency(performance.commissions.total) }}</div>
                        <div class="text-body-2">Total Commissions</div>
                        <div class="text-caption">{{ formatCurrency(performance.commissions.this_month) }} ce mois</div>
                      </VCardText>
                    </VCard>
                  </VCol>
                  <VCol cols="12" md="4">
                    <VCard variant="tonal" color="warning">
                      <VCardText>
                        <div class="text-h4">{{ formatCurrency(performance.payments.total_paid) }}</div>
                        <div class="text-body-2">Total Payé</div>
                        <div class="text-caption">{{ formatCurrency(performance.payments.pending) }} en attente</div>
                      </VCardText>
                    </VCard>
                  </VCol>
                </VRow>
              </div>
              <div v-else class="text-center py-8">
                <VProgressCircular indeterminate />
                <p class="mt-2">Chargement des performances...</p>
              </div>
            </VCardText>
          </VCard>
        </VWindowItem>

        <!-- Orders Tab -->
        <VWindowItem value="orders">
          <VCard>
            <VCardTitle>Commandes Récentes</VCardTitle>
            <VCardText>
              <div v-if="affiliate.profil_affilie?.commandes?.length">
                <VList>
                  <VListItem
                    v-for="order in affiliate.profil_affilie.commandes"
                    :key="order.id"
                  >
                    <VListItemTitle>Commande {{ order.id.slice(0, 8) }}</VListItemTitle>
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
                <h3 class="text-h6 mb-2">Aucune commande</h3>
                <p class="text-body-2 text-medium-emphasis">
                  Cet affilié n'a pas encore passé de commande
                </p>
              </div>
            </VCardText>
          </VCard>
        </VWindowItem>

        <!-- Commissions Tab -->
        <VWindowItem value="commissions">
          <VCard>
            <VCardTitle>Commissions Récentes</VCardTitle>
            <VCardText>
              <div v-if="affiliate.profil_affilie?.commissions?.length">
                <VList>
                  <VListItem
                    v-for="commission in affiliate.profil_affilie.commissions"
                    :key="commission.id"
                  >
                    <VListItemTitle>{{ formatCurrency(commission.montant) }}</VListItemTitle>
                    <VListItemSubtitle>{{ formatDate(commission.created_at) }}</VListItemSubtitle>
                    <template #append>
                      <VChip size="small" :color="getStatusColor(commission.statut)" variant="tonal">
                        {{ commission.statut }}
                      </VChip>
                    </template>
                  </VListItem>
                </VList>
              </div>
              <div v-else class="text-center py-8">
                <VIcon icon="tabler-currency-dollar-off" size="64" class="mb-4" color="disabled" />
                <h3 class="text-h6 mb-2">Aucune commission</h3>
                <p class="text-body-2 text-medium-emphasis">
                  Cet affilié n'a pas encore de commission
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
      <h3 class="text-h6 mb-2">Affilié introuvable</h3>
      <p class="text-body-2 text-medium-emphasis mb-4">
        L'affilié demandé n'existe pas ou a été supprimé
      </p>
      <VBtn
        color="primary"
        variant="elevated"
        @click="goBack"
      >
        Retour à la liste
      </VBtn>
    </div>

    <!-- Change Tier Dialog -->
    <VDialog
      v-model="showTierDialog"
      max-width="500"
    >
      <VCard>
        <VCardTitle>Changer le Tier</VCardTitle>
        <VCardText>
          <VSelect
            v-model="selectedTier"
            label="Nouveau tier"
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
            Annuler
          </VBtn>
          <VBtn
            color="primary"
            variant="elevated"
            :disabled="!selectedTier"
            @click="changeTier"
          >
            Confirmer
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
        <VCardTitle>Bloquer l'Affilié</VCardTitle>
        <VCardText>
          <VTextarea
            v-model="blockReason"
            label="Raison du blocage"
            placeholder="Expliquez pourquoi vous bloquez cet affilié..."
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
            Annuler
          </VBtn>
          <VBtn
            color="error"
            variant="elevated"
            @click="toggleBlock('block')"
          >
            Bloquer
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
