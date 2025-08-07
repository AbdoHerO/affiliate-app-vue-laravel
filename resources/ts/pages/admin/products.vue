<script setup lang="ts">
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()

definePage({
  meta: {
    requiresAuth: true,
    requiresRole: 'admin',
  },
})

// Redirect to the new produits structure
onMounted(() => {
  router.replace('/admin/produits')
})
</script>

<template>
  <div class="d-flex justify-content-center align-items-center" style="height: 200px;">
    <div class="spinner-border" role="status">
      <span class="visually-hidden">Loading...</span>
    </div>
  </div>
</template>
