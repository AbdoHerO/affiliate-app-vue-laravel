<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import { useApi } from '@/composables/useApi'
import PublicLayout from '@/layouts/PublicLayout.vue'
import PublicImageGallery from '@/components/public/PublicImageGallery.vue'
import PublicVideoGallery from '@/components/public/PublicVideoGallery.vue'
import VariantChips from '@/components/public/VariantChips.vue'
import PropositionCards from '@/components/public/PropositionCards.vue'
import StockIssues from '@/components/public/StockIssues.vue'

// Page meta
definePage({
  meta: {
    layout: 'public',
    requiresAuth: false,
  },
})

// Composables
const route = useRoute()

// State
const product = ref<any>(null)
const loading = ref(true)
const error = ref<string | null>(null)

// Computed
const pageTitle = computed(() => product.value?.titre || 'Product')
const shareUrl = computed(() => window.location.href)

// Methods
const loadProduct = async () => {
  try {
    loading.value = true
    error.value = null
    
    const slug = route.params.slug as string
    const { data, error: apiError } = await useApi(`/public/produits/${slug}`)
    
    if (apiError.value) {
      error.value = 'Product not found'
      return
    }
    
    const response = data.value as any
    if (response.success) {
      product.value = response.data
      
      // Update page title
      if (product.value.titre) {
        document.title = `${product.value.titre} - COD Platform`
      }
    } else {
      error.value = response.message || 'Product not found'
    }
  } catch (err) {
    console.error('Error loading product:', err)
    error.value = 'Failed to load product'
  } finally {
    loading.value = false
  }
}

const copyShareLink = async () => {
  try {
    await navigator.clipboard.writeText(shareUrl.value)
    // You could add a toast notification here
    console.log('Link copied to clipboard')
  } catch (err) {
    console.error('Failed to copy link:', err)
  }
}

const formatPrice = (price: number | string) => {
  return new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'MAD'
  }).format(Number(price))
}

// Format copywriting with line breaks and bold text
const formatCopywriting = (text: string): string => {
  if (!text) return ''

  return text
    // Convert line breaks to <br> tags
    .replace(/\n/g, '<br>')
    // Convert **text** to bold
    .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
    // Convert *text* to italic
    .replace(/\*(.*?)\*/g, '<em>$1</em>')
    // Preserve emojis and special characters
    .trim()
}

// Lifecycle
onMounted(() => {
  loadProduct()
})
</script>

<template>
  <PublicLayout>
    <!-- Loading State -->
    <div v-if="loading" class="d-flex justify-center align-center" style="min-height: 400px;">
      <VProgressCircular indeterminate color="primary" size="64" />
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="d-flex flex-column justify-center align-center" style="min-height: 400px;">
      <VIcon icon="tabler-alert-circle" size="64" color="error" class="mb-4" />
      <h2 class="text-h4 mb-2">Product Not Found</h2>
      <p class="text-body-1 text-medium-emphasis">{{ error }}</p>
    </div>

    <!-- Product Content -->
    <div v-else-if="product">
      <!-- Sticky Header -->
      <VAppBar
        color="white"
        elevation="1"
        class="border-b"
        style="top: 64px;"
        app
      >
        <VContainer class="d-flex align-center">
          <div class="flex-grow-1">
            <h1 class="text-h6 font-weight-bold text-truncate">
              {{ product.titre }}
            </h1>
          </div>
          <VBtn
            variant="outlined"
            color="primary"
            size="small"
            prepend-icon="tabler-share"
            @click="copyShareLink"
          >
            Copy Link
          </VBtn>
        </VContainer>
      </VAppBar>

      <VContainer class="py-8" style="margin-top: 64px;">
        <VRow>
          <!-- Main Content -->
          <VCol cols="12" lg="8">
            <!-- Hero Section -->
            <VCard class="mb-6" elevation="2">
              <VCardText class="pa-6">
                <div class="d-flex flex-column flex-md-row gap-6">
                  <!-- Primary Image -->
                  <div class="flex-shrink-0">
                    <VImg
                      v-if="product.images?.[0]"
                      :src="product.images[0].url"
                      :alt="product.titre"
                      width="300"
                      height="300"
                      class="rounded-lg"
                      cover
                    />
                    <div v-else class="d-flex align-center justify-center bg-grey-lighten-4 rounded-lg" style="width: 300px; height: 300px;">
                      <VIcon icon="tabler-photo" size="64" color="grey" />
                    </div>
                  </div>
                  
                  <!-- Product Info -->
                  <div class="flex-grow-1">
                    <h1 class="text-h3 font-weight-bold mb-4">
                      {{ product.titre }}
                    </h1>
                    
                    <div class="d-flex align-center gap-4 mb-4">
                      <div class="text-h4 font-weight-bold text-primary">
                        {{ formatPrice(product.prix_vente) }}
                      </div>
                      <div v-if="product.prix_affilie" class="text-body-1 text-medium-emphasis">
                        Affiliate: {{ formatPrice(product.prix_affilie) }}
                      </div>
                    </div>
                    
                    <div class="d-flex gap-2 mb-4">
                      <VChip v-if="product.boutique" color="primary" variant="outlined" size="small">
                        {{ product.boutique.nom }}
                      </VChip>
                      <VChip v-if="product.categorie" color="secondary" variant="outlined" size="small">
                        {{ product.categorie.nom }}
                      </VChip>
                    </div>

                    <!-- Rating Section -->
                    <div v-if="product.rating?.value" class="rating-section mb-4">
                      <div class="d-flex align-center gap-2">
                        <VRating
                          :model-value="product.rating.value"
                          readonly
                          length="5"
                          half-increments
                          color="warning"
                          size="small"
                        />
                        <span class="text-body-2 text-medium-emphasis">
                          ({{ product.rating.value }}/{{ product.rating.max || 5 }})
                        </span>
                      </div>
                    </div>
                    
                    <div v-if="product.description" class="text-body-1 mb-4">
                      <div v-html="product.description"></div>
                    </div>

                    <!-- Copywriting Section -->
                    <div v-if="product.copywriting" class="copywriting-section">
                      <VCard variant="outlined" class="pa-4 bg-grey-lighten-5">
                        <div class="text-body-1 copywriting-content" v-html="formatCopywriting(product.copywriting)"></div>
                      </VCard>
                    </div>
                  </div>
                </div>
              </VCardText>
            </VCard>

            <!-- Image Gallery -->
            <PublicImageGallery
              v-if="product.images?.length > 1"
              :images="product.images"
              class="mb-6"
            />

            <!-- Video Gallery -->
            <PublicVideoGallery
              v-if="product.videos?.length"
              :videos="product.videos"
              class="mb-6"
            />

            <!-- Variants -->
            <VariantChips
              v-if="product.variantes?.length"
              :variants="product.variantes"
              class="mb-6"
            />

            <!-- Propositions -->
            <PropositionCards
              v-if="product.propositions?.length"
              :propositions="product.propositions"
              class="mb-6"
            />

            <!-- Stock Issues -->
            <StockIssues
              v-if="product.ruptures?.length || product.variantes?.length"
              :stock-issues="product.ruptures || []"
              :variants="product.variantes || []"
              class="mb-6"
            />
          </VCol>

          <!-- Sidebar -->
          <VCol cols="12" lg="4">
            <VCard elevation="2">
              <VCardTitle>Product Details</VCardTitle>
              <VCardText>
                <div class="d-flex flex-column gap-3">
                  <div v-if="product.boutique">
                    <div class="text-caption text-medium-emphasis">Store</div>
                    <div class="font-weight-medium">{{ product.boutique.nom }}</div>
                  </div>
                  
                  <div v-if="product.categorie">
                    <div class="text-caption text-medium-emphasis">Category</div>
                    <div class="font-weight-medium">{{ product.categorie.nom }}</div>
                  </div>
                  
                  <div>
                    <div class="text-caption text-medium-emphasis">Price</div>
                    <div class="text-h6 font-weight-bold text-primary">
                      {{ formatPrice(product.prix_vente) }}
                    </div>
                  </div>
                  
                  <div v-if="product.prix_affilie">
                    <div class="text-caption text-medium-emphasis">Affiliate Price</div>
                    <div class="font-weight-medium">
                      {{ formatPrice(product.prix_affilie) }}
                    </div>
                  </div>
                </div>
              </VCardText>
            </VCard>
          </VCol>
        </VRow>
      </VContainer>
    </div>
  </PublicLayout>
</template>

<style scoped>
.v-app-bar {
  z-index: 1000;
}

.copywriting-section {
  margin-top: 16px;
}

.copywriting-content {
  line-height: 1.6;
  word-wrap: break-word;
}

.copywriting-content strong {
  font-weight: 600;
  color: rgb(var(--v-theme-primary));
}

.copywriting-content em {
  font-style: italic;
  color: rgb(var(--v-theme-secondary));
}
</style>
