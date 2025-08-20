<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useAffiliateCartStore } from '@/stores/affiliate/cart'
import AffiliateCartModal from './AffiliateCartModal.vue'

// Store
const cartStore = useAffiliateCartStore()

// State
const showCartModal = ref(false)

// Methods
const openCart = () => {
  showCartModal.value = true
}

const handleCartClose = () => {
  showCartModal.value = false
}

const handleCartSuccess = () => {
  showCartModal.value = false
}

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
      @click="openCart"
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

    <!-- Cart Modal -->
    <AffiliateCartModal
      v-model="showCartModal"
      @close="handleCartClose"
      @success="handleCartSuccess"
    />
  </div>
</template>

<style scoped>
.affiliate-header-cart {
  display: flex;
  align-items: center;
}
</style>
