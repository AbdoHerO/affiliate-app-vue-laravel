<script setup lang="ts">
interface Variant {
  id: string
  nom: string
  valeur: string
  prix_vente_variante?: number | null
  image_url?: string | null
}

interface Props {
  variants: Variant[]
}

defineProps<Props>()

const formatPrice = (price: number | string) => {
  return new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'MAD'
  }).format(Number(price))
}
</script>

<template>
  <VCard elevation="2">
    <VCardTitle class="d-flex align-center gap-2">
      <VIcon icon="tabler-palette" />
      Variants ({{ variants.length }})
    </VCardTitle>
    <VCardText>
      <div class="d-flex flex-wrap gap-3">
        <VCard
          v-for="variant in variants"
          :key="variant.id"
          class="variant-card"
          elevation="1"
          width="200"
        >
          <div v-if="variant.image_url" class="variant-image">
            <VImg
              :src="variant.image_url"
              :alt="`${variant.nom}: ${variant.valeur}`"
              height="120"
              cover
            >
              <template #placeholder>
                <div class="d-flex align-center justify-center fill-height bg-grey-lighten-4">
                  <VIcon icon="tabler-photo" size="32" color="grey" />
                </div>
              </template>
            </VImg>
          </div>
          
          <VCardText class="pa-3">
            <div class="text-subtitle-2 font-weight-bold mb-1">
              {{ variant.nom }}
            </div>
            <div class="text-body-2 text-medium-emphasis mb-2">
              {{ variant.valeur }}
            </div>
            <div v-if="variant.prix_vente_variante" class="text-caption text-primary font-weight-medium">
              {{ formatPrice(variant.prix_vente_variante) }}
            </div>
          </VCardText>
        </VCard>
      </div>
    </VCardText>
  </VCard>
</template>

<style scoped>
.variant-card {
  transition: transform 0.2s ease, box-shadow 0.2s ease;
  border: 1px solid rgba(0, 0, 0, 0.12);
}

.variant-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.variant-image {
  border-bottom: 1px solid rgba(0, 0, 0, 0.12);
}
</style>
