<script setup lang="ts">
import { computed } from 'vue'
import avatar1 from '@images/avatars/avatar-1.png'
import { useAuth } from '@/composables/useAuth'
import { useI18n } from 'vue-i18n'
import { getAvatarUrl } from '@/utils/imageUtils'

const { user, isAuthenticated, logout } = useAuth()
const { t } = useI18n()

// Computed property for user avatar URL
const userAvatarUrl = computed(() => getAvatarUrl(user.value?.photo_profil))

const handleLogout = async () => {
  await logout()
}
</script>

<template>
  <VBadge
    dot
    location="bottom right"
    offset-x="3"
    offset-y="3"
    bordered
    color="success"
  >
    <VAvatar
      class="cursor-pointer"
      :color="!user?.photo_profil ? 'primary' : undefined"
      :variant="!user?.photo_profil ? 'tonal' : undefined"
    >
      <VImg :src="userAvatarUrl" />

      <!-- SECTION Menu -->
      <VMenu
        activator="parent"
        width="230"
        location="bottom end"
        offset="14px"
      >
        <VList>
          <!-- 👉 User Avatar & Name -->
          <VListItem>
            <template #prepend>
              <VListItemAction start>
                <VBadge
                  dot
                  location="bottom right"
                  offset-x="3"
                  offset-y="3"
                  color="success"
                >
                  <VAvatar
                    :color="!user?.photo_profil ? 'primary' : undefined"
                    :variant="!user?.photo_profil ? 'tonal' : undefined"
                  >
                    <VImg :src="userAvatarUrl" />
                  </VAvatar>
                </VBadge>
              </VListItemAction>
            </template>

            <VListItemTitle class="font-weight-semibold">
              {{ user?.nom_complet || 'Guest' }}
            </VListItemTitle>
            <VListItemSubtitle>{{ user?.roles?.join(', ') || 'No Role' }}</VListItemSubtitle>
          </VListItem>

          <VDivider class="my-2" />

          <!-- 👉 Profile -->
          <VListItem
            link
            to="/profile"
          >
            <template #prepend>
              <VIcon
                class="me-2"
                icon="tabler-user"
                size="22"
              />
            </template>

            <VListItemTitle>{{ t('profile') }}</VListItemTitle>
          </VListItem>

          <!-- 👉 Settings -->
          <VListItem link>
            <template #prepend>
              <VIcon
                class="me-2"
                icon="tabler-settings"
                size="22"
              />
            </template>

            <VListItemTitle>{{ t('settings') }}</VListItemTitle>
          </VListItem>

          <!-- 👉 Pricing -->
          <VListItem link>
            <template #prepend>
              <VIcon
                class="me-2"
                icon="tabler-currency-dollar"
                size="22"
              />
            </template>

            <VListItemTitle>{{ t('pricing') }}</VListItemTitle>
          </VListItem>

          <!-- 👉 FAQ -->
          <VListItem link>
            <template #prepend>
              <VIcon
                class="me-2"
                icon="tabler-help"
                size="22"
              />
            </template>

            <VListItemTitle>{{ t('faq') }}</VListItemTitle>
          </VListItem>

          <!-- Divider -->
          <VDivider class="my-2" />

          <!-- 👉 Logout -->
          <VListItem
            v-if="isAuthenticated"
            @click="handleLogout"
          >
            <template #prepend>
              <VIcon
                class="me-2"
                icon="tabler-logout"
                size="22"
              />
            </template>

            <VListItemTitle>{{ t('logout') }}</VListItemTitle>
          </VListItem>

          <!-- 👉 Login -->
          <VListItem
            v-else
            to="/login"
          >
            <template #prepend>
              <VIcon
                class="me-2"
                icon="tabler-login"
                size="22"
              />
            </template>

            <VListItemTitle>{{ t('login') }}</VListItemTitle>
          </VListItem>
        </VList>
      </VMenu>
      <!-- !SECTION -->
    </VAvatar>
  </VBadge>
</template>
