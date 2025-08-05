<script lang="ts" setup>
import { useAuth } from '@/composables/useAuth'
import type { ProfileTab } from '@/types/profile'

const { user } = useAuth()

// Create profile data from authenticated user
const profileData = computed<ProfileTab>(() => {
  if (!user.value) {
    return {
      about: [],
      contacts: [],
      teams: [],
      overview: [],
      teamsTech: [],
      connections: []
    }
  }

  return {
    about: [
      {
        icon: 'tabler-user',
        property: $t('form_full_name'),
        value: user.value.nom_complet
      },
      {
        icon: 'tabler-check',
        property: $t('user_status'),
        value: user.value.statut === 'actif' ? $t('status_active') : 
               user.value.statut === 'inactif' ? $t('status_inactive') : $t('status_blocked')
      },
      {
        icon: 'tabler-crown',
        property: $t('user_role'),
        value: user.value.roles?.join(', ') || $t('no_role')
      },
      {
        icon: 'tabler-shield-check',
        property: $t('user_kyc_status'),
        value: user.value.kyc_statut === 'valide' ? $t('kyc_status_valide') :
               user.value.kyc_statut === 'en_attente' ? $t('kyc_status_en_attente') :
               user.value.kyc_statut === 'refuse' ? $t('kyc_status_refuse') : $t('kyc_status_non_requis')
      }
    ],
    contacts: [
      {
        icon: 'tabler-mail',
        property: $t('form_email'),
        value: user.value.email
      },
      {
        icon: 'tabler-phone',
        property: $t('form_phone'),
        value: user.value.telephone || 'Not provided'
      },
      {
        icon: 'tabler-map-pin',
        property: $t('form_address'),
        value: user.value.adresse || 'Not provided'
      }
    ],
    teams: [
      {
        icon: 'tabler-users',
        property: 'Team Role',
        value: user.value.roles?.includes('admin') ? 'Administrator' : 'Affiliate Partner',
        color: user.value.roles?.includes('admin') ? 'primary' : 'success'
      }
    ],
    overview: [
      {
        icon: 'tabler-calendar-plus',
        property: $t('user_created_at'),
        value: user.value.created_at 
          ? new Date(user.value.created_at).toLocaleDateString()
          : 'Unknown'
      },
      {
        icon: 'tabler-mail-check',
        property: 'Email Verified',
        value: user.value.email_verifie ? 'Yes' : 'No'
      },
      {
        icon: 'tabler-key',
        property: 'Permissions',
        value: user.value.permissions?.length ? `${user.value.permissions.length} permissions` : 'No permissions'
      }
    ],
    teamsTech: [],
    connections: []
  }
})
</script>

<template>
  <VCard class="mb-6">
    <VCardText>
      <p class="text-sm text-disabled">
        {{ $t('about').toUpperCase() }}
      </p>

      <VList class="card-list text-medium-emphasis">
        <VListItem
          v-for="item in profileData.about"
          :key="item.property"
        >
          <VListItemTitle>
            <span class="d-flex align-center">
              <VIcon
                :icon="item.icon"
                size="24"
                class="me-2"
              />
              <div class="text-body-1 font-weight-medium me-2">{{ item.property }}:</div>
              <div>{{ item.value }}</div>
            </span>
          </VListItemTitle>
        </VListItem>
      </VList>

      <p class="text-sm text-disabled mt-6">
        {{ $t('contacts').toUpperCase() }}
      </p>

      <VList class="card-list text-medium-emphasis">
        <VListItem
          v-for="item in profileData.contacts"
          :key="item.property"
        >
          <VListItemTitle>
            <span class="d-flex align-center">
              <VIcon
                :icon="item.icon"
                size="24"
                class="me-2"
              />
              <div class="text-body-1 font-weight-medium me-2">{{ item.property }}:</div>
              <div>{{ item.value }}</div>
            </span>
          </VListItemTitle>
        </VListItem>
      </VList>

      <p class="text-sm text-disabled mt-6">
        {{ $t('teams').toUpperCase() }}
      </p>

      <VList class="card-list text-medium-emphasis">
        <VListItem
          v-for="item in profileData.teams"
          :key="item.property"
        >
          <VListItemTitle>
            <span class="d-flex align-center">
              <div class="text-body-1 font-weight-medium me-2">{{ item.property }}:</div>
              <VChip
                :color="item.color"
                size="small"
                label
              >
                {{ item.value }}
              </VChip>
            </span>
          </VListItemTitle>
        </VListItem>
      </VList>
    </VCardText>
  </VCard>

  <VCard>
    <VCardText>
      <p class="text-sm text-disabled">
        {{ $t('overview').toUpperCase() }}
      </p>

      <VList class="card-list text-medium-emphasis">
        <VListItem
          v-for="item in profileData.overview"
          :key="item.property"
        >
          <VListItemTitle>
            <span class="d-flex align-center">
              <VIcon
                :icon="item.icon"
                size="24"
                class="me-2"
              />
              <div class="text-body-1 font-weight-medium me-2">{{ item.property }}:</div>
              <div>{{ item.value }}</div>
            </span>
          </VListItemTitle>
        </VListItem>
      </VList>
    </VCardText>
  </VCard>
</template>

<style lang="scss" scoped>
.card-list {
  --v-card-list-gap: 16px;
}
</style>
