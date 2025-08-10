<template>
  <div class="public-layout">
    <!-- Simple header -->
    <VAppBar
      color="white"
      elevation="1"
      height="64"
      class="border-b"
    >
      <VContainer class="d-flex align-center">
        <VAppBarTitle class="text-h6 font-weight-bold text-primary">
          COD Platform
        </VAppBarTitle>
        <VSpacer />
        <VBtn
          variant="outlined"
          color="primary"
          size="small"
          href="/admin"
        >
          Admin Panel
        </VBtn>
      </VContainer>
    </VAppBar>

    <!-- Main content -->
    <VMain>
      <slot />
    </VMain>

    <!-- Simple footer -->
    <VFooter
      color="grey-lighten-5"
      class="text-center pa-4"
    >
      <VContainer>
        <p class="text-body-2 text-medium-emphasis mb-0">
          © {{ new Date().getFullYear() }} COD Platform. All rights reserved.
        </p>
      </VContainer>
    </VFooter>
  </div>
</template>

<script setup lang="ts">
// Simple public layout with minimal styling
</script>

<style scoped>
.public-layout {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

.v-main {
  flex: 1;
}
</style>
