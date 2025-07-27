<script setup lang="ts">
import { useAuth } from '@/composables/useAuth'

definePage({
  meta: {
    layout: 'blank',
    public: true,
  },
})

const { user, logout } = useAuth()

const handleLogout = async () => {
  await logout()
}

const goBack = () => {
  window.history.back()
}
</script>

<template>
  <div class="misc-wrapper">
    <ErrorHeader />

    <!-- 👉 Error -->
    <div class="misc-avatar w-100 text-center">
      <VAvatar
        variant="tonal"
        color="error"
        class="misc-avatar-size"
      >
        <VIcon
          icon="tabler-lock"
          size="5rem"
        />
      </VAvatar>
    </div>

    <div class="text-center">
      <h1 class="text-h1 mb-4">
        403 - Unauthorized
      </h1>
      <p class="text-lg">
        You don't have permission to access this page.
      </p>
      
      <div v-if="user" class="my-6">
        <VAlert
          type="warning"
          variant="tonal"
          class="text-start"
        >
          <div>
            <strong>Current User:</strong> {{ user.name }}<br>
            <strong>Role:</strong> {{ user.roles.join(', ') }}<br>
            <strong>Permissions:</strong> {{ user.permissions.join(', ') }}
          </div>
        </VAlert>
      </div>

      <div class="d-flex justify-center gap-4 flex-wrap">
        <VBtn
          color="primary"
          @click="goBack"
        >
          <VIcon
            start
            icon="tabler-arrow-left"
          />
          Go Back
        </VBtn>
        
        <VBtn
          v-if="user"
          color="error"
          variant="outlined"
          @click="handleLogout"
        >
          <VIcon
            start
            icon="tabler-logout"
          />
          Logout
        </VBtn>
        
        <VBtn
          v-else
          color="success"
          variant="outlined"
          to="/login"
        >
          <VIcon
            start
            icon="tabler-login"
          />
          Login
        </VBtn>
      </div>
    </div>
  </div>
</template>

<style lang="scss">
@use "@core-scss/template/pages/misc";
</style>
