<script lang="ts" setup>
import { useNavigation } from '@/composables/useNavigation'
import { useAuth } from '@/composables/useAuth'
import { themeConfig } from '@themeConfig'

// Components
import Footer from '@/layouts/components/Footer.vue'
import NavbarThemeSwitcher from '@/layouts/components/NavbarThemeSwitcher.vue'
import UserProfile from '@/layouts/components/UserProfile.vue'
import NavBarI18n from '@core/components/I18n.vue'

// @layouts plugin
import { VerticalNavLayout } from '@layouts'

// Get auth state and dynamic navigation
const { isAuthenticated, user, isLoading } = useAuth()
const { navItems } = useNavigation()

// Show loading only while auth is initializing
const isReady = computed(() => {
  return !isLoading.value
})
</script>

<template>
  <!-- Loading state until auth is ready -->
  <div v-if="!isReady" class="d-flex justify-center align-center" style="height: 100vh;">
    <div class="text-center">
      <VProgressCircular
        indeterminate
        color="primary"
        size="64"
      />
      <p class="mt-4">Loading...</p>
    </div>
  </div>

  <!-- Main layout with navigation -->
  <VerticalNavLayout v-else :nav-items="navItems">
    <!-- 👉 navbar -->
    <template #navbar="{ toggleVerticalOverlayNavActive }">
      <div class="d-flex h-100 align-center">
        <IconBtn
          id="vertical-nav-toggle-btn"
          class="ms-n3 d-lg-none"
          @click="toggleVerticalOverlayNavActive(true)"
        >
          <VIcon
            size="26"
            icon="tabler-menu-2"
          />
        </IconBtn>

        <NavbarThemeSwitcher />

        <VSpacer />

        <NavBarI18n
          v-if="themeConfig.app.i18n.enable && themeConfig.app.i18n.langConfig?.length"
          :languages="themeConfig.app.i18n.langConfig"
        />
        <UserProfile />
      </div>
    </template>

    <!-- 👉 Pages -->
    <slot />

    <!-- 👉 Footer -->
    <template #footer>
      <Footer />
    </template>
  </VerticalNavLayout>
</template>
