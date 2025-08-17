<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useWithdrawalsStore } from '@/stores/admin/withdrawals'
import { useNotifications } from '@/composables/useNotifications'

interface Commission {
  id: string
  amount: number
  status: string
  type: string
  created_at: string
  commande?: {
    id: string
    statut: string
    total_ttc: number
    created_at: string
  }
  produit?: {
    id: string
    titre: string
  }
}

interface Props {
  userId: string
  selectedCommissions?: string[]
  mode?: 'auto' | 'manual'
  targetAmount?: number
}

interface Emits {
  (e: 'update:selectedCommissions', value: string[]): void
  (e: 'update:targetAmount', value: number): void
  (e: 'update:mode', value: 'auto' | 'manual'): void
}

const props = withDefaults(defineProps<Props>(), {
  selectedCommissions: () => [],
  mode: 'manual',
  targetAmount: 0,
})

const emit = defineEmits<Emits>()

const withdrawalsStore = useWithdrawalsStore()
const { showError } = useNotifications()

// Local state
const commissions = ref<Commission[]>([])
const loading = ref(false)
const search = ref('')
const localSelectedCommissions = ref<string[]>([...props.selectedCommissions])
const localMode = ref(props.mode)
const localTargetAmount = ref(props.targetAmount)

// Computed
const filteredCommissions = computed(() => {
  if (!search.value) return commissions.value
  
  return commissions.value.filter(commission => 
    commission.id.toLowerCase().includes(search.value.toLowerCase()) ||
    commission.commande?.id.toLowerCase().includes(search.value.toLowerCase()) ||
    commission.produit?.titre?.toLowerCase().includes(search.value.toLowerCase())
  )
})

const selectedCommissionsData = computed(() => {
  return commissions.value.filter(c => localSelectedCommissions.value.includes(c.id))
})

const totalSelectedAmount = computed(() => {
  return selectedCommissionsData.value.reduce((sum, commission) => sum + commission.amount, 0)
})

const isAllSelected = computed(() => {
  return filteredCommissions.value.length > 0 && 
         filteredCommissions.value.every(c => localSelectedCommissions.value.includes(c.id))
})

const isSomeSelected = computed(() => {
  return localSelectedCommissions.value.length > 0 && !isAllSelected.value
})

// Watch for prop changes
watch(() => props.selectedCommissions, (newVal) => {
  localSelectedCommissions.value = [...newVal]
}, { deep: true })

watch(() => props.mode, (newVal) => {
  localMode.value = newVal
})

watch(() => props.targetAmount, (newVal) => {
  localTargetAmount.value = newVal
})

// Watch for local changes and emit
watch(localSelectedCommissions, (newVal) => {
  emit('update:selectedCommissions', newVal)
}, { deep: true })

watch(localMode, (newVal) => {
  emit('update:mode', newVal)
})

watch(localTargetAmount, (newVal) => {
  emit('update:targetAmount', newVal)
})

// Methods
const fetchCommissions = async () => {
  if (!props.userId) return
  
  loading.value = true
  try {
    const result = await withdrawalsStore.fetchEligibleCommissions(props.userId)
    if (result.success) {
      commissions.value = result.data.data || []
    } else {
      showError(result.message)
    }
  } catch (error) {
    showError('Erreur lors du chargement des commissions')
  } finally {
    loading.value = false
  }
}

const toggleCommission = (commissionId: string) => {
  const index = localSelectedCommissions.value.indexOf(commissionId)
  if (index > -1) {
    localSelectedCommissions.value.splice(index, 1)
  } else {
    localSelectedCommissions.value.push(commissionId)
  }
}

const toggleAll = () => {
  if (isAllSelected.value) {
    // Deselect all visible commissions
    filteredCommissions.value.forEach(commission => {
      const index = localSelectedCommissions.value.indexOf(commission.id)
      if (index > -1) {
        localSelectedCommissions.value.splice(index, 1)
      }
    })
  } else {
    // Select all visible commissions
    filteredCommissions.value.forEach(commission => {
      if (!localSelectedCommissions.value.includes(commission.id)) {
        localSelectedCommissions.value.push(commission.id)
      }
    })
  }
}

const autoSelectByAmount = () => {
  if (!localTargetAmount.value || localTargetAmount.value <= 0) return
  
  localSelectedCommissions.value = []
  let currentAmount = 0
  
  // Sort commissions by date (oldest first) and select until target amount is reached
  const sortedCommissions = [...commissions.value].sort((a, b) => 
    new Date(a.created_at).getTime() - new Date(b.created_at).getTime()
  )
  
  for (const commission of sortedCommissions) {
    if (currentAmount >= localTargetAmount.value) break
    
    localSelectedCommissions.value.push(commission.id)
    currentAmount += commission.amount
  }
}

const clearSelection = () => {
  localSelectedCommissions.value = []
}

// Initialize
watch(() => props.userId, fetchCommissions, { immediate: true })
</script>

<template>
  <div class="commission-selector">
    <!-- Mode Selection -->
    <VRow class="mb-4">
      <VCol cols="12">
        <VRadioGroup
          v-model="localMode"
          inline
          class="mb-4"
        >
          <VRadio
            label="Sélection manuelle"
            value="manual"
          />
          <VRadio
            label="Sélection automatique par montant"
            value="auto"
          />
        </VRadioGroup>
      </VCol>
    </VRow>

    <!-- Auto Selection -->
    <VRow v-if="localMode === 'auto'" class="mb-4">
      <VCol cols="12" md="6">
        <VTextField
          v-model.number="localTargetAmount"
          label="Montant cible"
          type="number"
          min="0"
          step="0.01"
          suffix="MAD"
          @blur="autoSelectByAmount"
        />
      </VCol>
      <VCol cols="12" md="6" class="d-flex align-center">
        <VBtn
          color="primary"
          variant="tonal"
          @click="autoSelectByAmount"
        >
          Sélectionner automatiquement
        </VBtn>
      </VCol>
    </VRow>

    <!-- Manual Selection -->
    <div v-if="localMode === 'manual'">
      <!-- Search and Actions -->
      <VRow class="mb-4">
        <VCol cols="12" md="6">
          <VTextField
            v-model="search"
            label="Rechercher..."
            prepend-inner-icon="tabler-search"
            clearable
          />
        </VCol>
        <VCol cols="12" md="6" class="d-flex align-center gap-2">
          <VBtn
            variant="tonal"
            size="small"
            @click="toggleAll"
          >
            {{ isAllSelected ? 'Tout désélectionner' : 'Tout sélectionner' }}
          </VBtn>
          <VBtn
            variant="tonal"
            color="error"
            size="small"
            @click="clearSelection"
          >
            Effacer
          </VBtn>
        </VCol>
      </VRow>

      <!-- Commissions List -->
      <VCard>
        <VCardText>
          <div v-if="loading" class="text-center py-4">
            <VProgressCircular indeterminate />
            <p class="mt-2">Chargement des commissions...</p>
          </div>

          <div v-else-if="filteredCommissions.length === 0" class="text-center py-4">
            <VIcon icon="tabler-inbox" size="48" class="mb-2" />
            <p>Aucune commission éligible trouvée</p>
          </div>

          <div v-else>
            <!-- Header -->
            <VRow class="mb-2 font-weight-medium">
              <VCol cols="1">
                <VCheckbox
                  :model-value="isAllSelected"
                  :indeterminate="isSomeSelected"
                  @click="toggleAll"
                />
              </VCol>
              <VCol cols="3">Commission</VCol>
              <VCol cols="3">Commande</VCol>
              <VCol cols="2">Montant</VCol>
              <VCol cols="2">Statut</VCol>
              <VCol cols="1">Date</VCol>
            </VRow>

            <VDivider class="mb-2" />

            <!-- Commission Items -->
            <div class="commission-list" style="max-height: 400px; overflow-y: auto;">
              <VRow
                v-for="commission in filteredCommissions"
                :key="commission.id"
                class="mb-1 align-center"
                :class="{ 'bg-primary-lighten-5': localSelectedCommissions.includes(commission.id) }"
              >
                <VCol cols="1">
                  <VCheckbox
                    :model-value="localSelectedCommissions.includes(commission.id)"
                    @click="toggleCommission(commission.id)"
                  />
                </VCol>
                <VCol cols="3">
                  <div class="text-body-2">{{ commission.id.substring(0, 8) }}...</div>
                  <div class="text-caption text-medium-emphasis">{{ commission.type }}</div>
                </VCol>
                <VCol cols="3">
                  <div class="text-body-2">{{ commission.commande?.id.substring(0, 8) }}...</div>
                  <div class="text-caption text-medium-emphasis">{{ commission.produit?.titre }}</div>
                </VCol>
                <VCol cols="2">
                  <div class="text-body-2 font-weight-medium">{{ Number(commission.amount).toFixed(2) }} MAD</div>
                </VCol>
                <VCol cols="2">
                  <VChip
                    :color="commission.status === 'approved' ? 'success' : 'info'"
                    variant="tonal"
                    size="small"
                  >
                    {{ commission.status }}
                  </VChip>
                </VCol>
                <VCol cols="1">
                  <div class="text-caption">
                    {{ new Date(commission.created_at).toLocaleDateString() }}
                  </div>
                </VCol>
              </VRow>
            </div>
          </div>
        </VCardText>
      </VCard>
    </div>

    <!-- Selection Summary -->
    <VCard v-if="localSelectedCommissions.length > 0" class="mt-4" color="primary" variant="tonal">
      <VCardText>
        <VRow>
          <VCol cols="12" md="6">
            <div class="text-h6">{{ localSelectedCommissions.length }} commission(s) sélectionnée(s)</div>
          </VCol>
          <VCol cols="12" md="6" class="text-md-end">
            <div class="text-h6">Total: {{ Number(totalSelectedAmount).toFixed(2) }} MAD</div>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>
  </div>
</template>
