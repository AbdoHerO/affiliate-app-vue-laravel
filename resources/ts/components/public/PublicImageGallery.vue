<script setup lang="ts">
import { ref } from 'vue'

interface Image {
  id: string
  url: string
  ordre: number
}

interface Props {
  images: Image[]
}

defineProps<Props>()

// State
const selectedImage = ref<string | null>(null)
const lightboxOpen = ref(false)

// Methods
const openLightbox = (imageUrl: string) => {
  selectedImage.value = imageUrl
  lightboxOpen.value = true
}

const closeLightbox = () => {
  lightboxOpen.value = false
  selectedImage.value = null
}
</script>

<template>
  <VCard elevation="2">
    <VCardTitle class="d-flex align-center gap-2">
      <VIcon icon="tabler-photo" />
      Images ({{ images.length }})
    </VCardTitle>
    <VCardText>
      <VRow>
        <VCol
          v-for="image in images"
          :key="image.id"
          cols="6"
          sm="4"
          md="3"
        >
          <VCard
            class="image-card"
            elevation="1"
            @click="openLightbox(image.url)"
          >
            <VImg
              :src="image.url"
              :alt="`Image ${image.ordre + 1}`"
              aspect-ratio="1"
              cover
              class="cursor-pointer"
            >
              <template #placeholder>
                <div class="d-flex align-center justify-center fill-height">
                  <VProgressCircular indeterminate color="primary" />
                </div>
              </template>
            </VImg>
            <VOverlay
              :model-value="false"
              contained
              class="align-center justify-center"
              opacity="0"
            >
              <VIcon icon="tabler-eye" size="32" color="white" />
            </VOverlay>
          </VCard>
        </VCol>
      </VRow>
    </VCardText>
  </VCard>

  <!-- Lightbox Dialog -->
  <VDialog
    v-model="lightboxOpen"
    max-width="90vw"
    max-height="90vh"
  >
    <VCard v-if="selectedImage">
      <VCardActions class="justify-end pa-2">
        <VBtn
          icon="tabler-x"
          variant="text"
          @click="closeLightbox"
        />
      </VCardActions>
      <VCardText class="pa-0">
        <VImg
          :src="selectedImage"
          max-height="80vh"
          contain
        />
      </VCardText>
    </VCard>
  </VDialog>
</template>

<style scoped>
.image-card {
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.image-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.cursor-pointer {
  cursor: pointer;
}
</style>
