<script setup lang="ts">
import { onMounted } from 'vue'
import { useAffiliateCartStore } from '@/stores/affiliate/cart'
import { useAffiliateCartUi } from '@/composables/useAffiliateCartUi'
import AffiliateCartDrawer from './AffiliateCartDrawer.vue'

// Store and UI state
const cartStore = useAffiliateCartStore()
const { cartDrawerOpen, openCartDrawer, closeCartDrawer } = useAffiliateCartUi()

// Methods are now handled directly by the composable

// Load cart on mount
onMounted(() => {
  cartStore.fetchCart()
})
</script>

<template>
  <div class="affiliate-header-cart">
    <!-- Cart Icon Button -->
    <VBtn
      icon
      variant="text"
      color="default"
      @click="openCartDrawer"
    >
      <VBadge
        :content="cartStore.count"
        :model-value="cartStore.count > 0"
        color="error"
        offset-x="2"
        offset-y="2"
      >
        <VIcon icon="tabler-shopping-cart" />
      </VBadge>
    </VBtn>

    <!-- Cart Drawer -->
    <AffiliateCartDrawer
      v-model="cartDrawerOpen"
      @close="closeCartDrawer"
      @success="closeCartDrawer"
    />
  </div>
</template>

<style scoped>
.affiliate-header-cart {
  display: flex;
  align-items: center;
}
</style>
