<template>
  <VDialog
    v-model="isOpen"
    max-width="700"
  >
    <VCard>
      <VCardTitle class="d-flex align-center justify-space-between">
        <span>{{ $t('admin_boutiques_view_title') }}</span>
        <VBtn
          icon="tabler-x"
          variant="text"
          size="small"
          @click="close"
        />
      </VCardTitle>

      <VCardText v-if="boutique">
        <VRow>
          <!-- Boutique Info Section -->
          <VCol cols="12">
            <div class="text-h6 mb-4">{{ $t('admin_boutiques_general_info') }}</div>
            
            <VRow>
              <VCol cols="12" md="6">
                <div class="mb-4">
                  <div class="text-body-2 text-medium-emphasis">{{ $t('admin_boutiques_name') }}</div>
                  <div class="text-body-1 font-weight-medium">{{ boutique.nom }}</div>
                </div>
              </VCol>
              
              <VCol cols="12" md="6">
                <div class="mb-4">
                  <div class="text-body-2 text-medium-emphasis">{{ $t('admin_boutiques_slug') }}</div>
                  <div class="text-body-1">
                    <VChip size="small" variant="outlined">{{ boutique.slug }}</VChip>
                  </div>
                </div>
              </VCol>

              <VCol cols="12" md="6">
                <div class="mb-4">
                  <div class="text-body-2 text-medium-emphasis">{{ $t('admin_boutiques_status') }}</div>
                  <div>
                    <VChip
                      :color="getStatusColor(boutique.statut)"
                      size="small"
                      variant="elevated"
                    >
                      {{ boutique.statut === 'actif' ? $t('status_active') : boutique.statut === 'suspendu' ? $t('status_inactive') : $t('status_cancelled') }}
                    </VChip>
                  </div>
                </div>
              </VCol>

              <VCol cols="12" md="6">
                <div class="mb-4">
                  <div class="text-body-2 text-medium-emphasis">{{ $t('admin_boutiques_commission_rate') }}</div>
                  <div class="text-body-1">{{ boutique.commission_par_defaut }}%</div>
                </div>
              </VCol>
            </VRow>
          </VCol>

          <!-- Owner Section -->
          <VCol cols="12">
            <VDivider class="mb-4" />
            <div class="text-h6 mb-4">{{ $t('admin_boutiques_owner_info') }}</div>
            
            <div class="d-flex align-center gap-4">
              <VAvatar size="48" :color="getAvatarColor(boutique.proprietaire.nom_complet)">
                {{ getInitials(boutique.proprietaire.nom_complet) }}
              </VAvatar>
              <div>
                <div class="text-body-1 font-weight-medium">{{ boutique.proprietaire.nom_complet }}</div>
                <div class="text-body-2 text-medium-emphasis">{{ boutique.proprietaire.email }}</div>
              </div>
            </div>
          </VCol>

          <!-- Contact Info Section -->
          <VCol cols="12" v-if="boutique.email_pro || boutique.adresse">
            <VDivider class="mb-4" />
            <div class="text-h6 mb-4">{{ $t('admin_boutiques_contact_info') }}</div>
            
            <VRow>
              <VCol cols="12" md="6" v-if="boutique.email_pro">
                <div class="mb-4">
                  <div class="text-body-2 text-medium-emphasis">{{ $t('admin_boutiques_email') }}</div>
                  <div class="text-body-1">
                    <a :href="`mailto:${boutique.email_pro}`" class="text-primary">
                      {{ boutique.email_pro }}
                    </a>
                  </div>
                </div>
              </VCol>
              
              <VCol cols="12" md="6" v-if="boutique.adresse">
                <div class="mb-4">
                  <div class="text-body-2 text-medium-emphasis">{{ $t('admin_boutiques_address') }}</div>
                  <div class="text-body-1">{{ boutique.adresse }}</div>
                </div>
              </VCol>
            </VRow>
          </VCol>

          <!-- Metadata Section -->
          <VCol cols="12">
            <VDivider class="mb-4" />
            <div class="text-h6 mb-4">{{ $t('admin_boutiques_metadata') }}</div>
            
            <VRow>
              <VCol cols="12" md="6">
                <div class="mb-4">
                  <div class="text-body-2 text-medium-emphasis">{{ $t('common.created_at') }}</div>
                  <div class="text-body-1">{{ formatDate(boutique.created_at) }}</div>
                </div>
              </VCol>
              
              <VCol cols="12" md="6">
                <div class="mb-4">
                  <div class="text-body-2 text-medium-emphasis">{{ $t('common.updated_at') }}</div>
                  <div class="text-body-1">{{ formatDate(boutique.updated_at) }}</div>
                </div>
              </VCol>
            </VRow>
          </VCol>
        </VRow>
      </VCardText>

      <VCardActions class="px-6 pb-6">
        <VSpacer />
        <VBtn
          variant="outlined"
          @click="close"
        >
          {{ $t('common.close') }}
        </VBtn>
        <!-- <VBtn
          color="primary"
          prepend-icon="tabler-edit"
          @click="editBoutique"
        >
          {{ $t('common.edit') }}
        </VBtn> -->
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useBoutiquesStore, type Boutique } from '@/stores/admin/boutiques'

interface Props {
  modelValue: boolean
  boutique?: Boutique | null
}

const props = defineProps<Props>()
const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  'edit': [boutique: Boutique]
}>()

const { t } = useI18n()
const boutiquesStore = useBoutiquesStore()

// Computed
const isOpen = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
})

// Methods
const close = () => {
  isOpen.value = false
}

const editBoutique = () => {
  if (props.boutique) {
    emit('edit', props.boutique)
    close()
  }
}

const getStatusColor = (status: string) => {
  return boutiquesStore.getStatusBadgeColor(status)
}

const getAvatarColor = (name: string) => {
  const colors = ['primary', 'secondary', 'success', 'info', 'warning', 'error']
  const index = name.length % colors.length
  return colors[index]
}

const getInitials = (name: string) => {
  return name.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2)
}

const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleString()
}
</script>
