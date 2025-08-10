<script setup lang="ts">
import { ref } from 'vue'

interface Video {
  id: string
  url: string
  titre?: string
  ordre: number
}

interface Props {
  videos: Video[]
}

defineProps<Props>()

// State
const selectedVideo = ref<string | null>(null)
const videoDialogOpen = ref(false)

// Methods
const openVideoDialog = (videoUrl: string) => {
  selectedVideo.value = videoUrl
  videoDialogOpen.value = true
}

const closeVideoDialog = () => {
  videoDialogOpen.value = false
  selectedVideo.value = null
}

const getVideoThumbnail = (videoUrl: string) => {
  // For now, return a placeholder. In a real app, you might generate thumbnails
  return '/placeholder-video-thumbnail.jpg'
}

const isYouTubeUrl = (url: string) => {
  return url.includes('youtube.com') || url.includes('youtu.be')
}

const getYouTubeEmbedUrl = (url: string) => {
  const videoId = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&\n?#]+)/)
  return videoId ? `https://www.youtube.com/embed/${videoId[1]}` : url
}
</script>

<template>
  <VCard elevation="2">
    <VCardTitle class="d-flex align-center gap-2">
      <VIcon icon="tabler-video" />
      Videos ({{ videos.length }})
    </VCardTitle>
    <VCardText>
      <VRow>
        <VCol
          v-for="video in videos"
          :key="video.id"
          cols="6"
          sm="4"
          md="3"
        >
          <VCard
            class="video-card"
            elevation="1"
            @click="openVideoDialog(video.url)"
          >
            <div class="video-thumbnail">
              <VImg
                v-if="isYouTubeUrl(video.url)"
                :src="`https://img.youtube.com/vi/${video.url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&\n?#]+)/)?.[1]}/maxresdefault.jpg`"
                aspect-ratio="16/9"
                cover
                class="cursor-pointer"
              >
                <template #placeholder>
                  <div class="d-flex align-center justify-center fill-height bg-grey-lighten-4">
                    <VIcon icon="tabler-video" size="48" color="grey" />
                  </div>
                </template>
              </VImg>
              <div v-else class="d-flex align-center justify-center bg-grey-lighten-4 cursor-pointer" style="aspect-ratio: 16/9;">
                <VIcon icon="tabler-video" size="48" color="grey" />
              </div>
              
              <!-- Play overlay -->
              <VOverlay
                :model-value="false"
                contained
                class="align-center justify-center"
                opacity="0.3"
              >
                <VIcon icon="tabler-player-play-filled" size="48" color="white" />
              </VOverlay>
            </div>
            
            <VCardText v-if="video.titre" class="pa-2">
              <div class="text-caption text-truncate">
                {{ video.titre }}
              </div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>
    </VCardText>
  </VCard>

  <!-- Video Dialog -->
  <VDialog
    v-model="videoDialogOpen"
    max-width="90vw"
    max-height="90vh"
  >
    <VCard v-if="selectedVideo">
      <VCardActions class="justify-end pa-2">
        <VBtn
          icon="tabler-x"
          variant="text"
          @click="closeVideoDialog"
        />
      </VCardActions>
      <VCardText class="pa-4">
        <div class="video-container">
          <iframe
            v-if="isYouTubeUrl(selectedVideo)"
            :src="getYouTubeEmbedUrl(selectedVideo)"
            frameborder="0"
            allowfullscreen
            class="video-iframe"
          />
          <video
            v-else
            :src="selectedVideo"
            controls
            class="video-player"
          >
            Your browser does not support the video tag.
          </video>
        </div>
      </VCardText>
    </VCard>
  </VDialog>
</template>

<style scoped>
.video-card {
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.video-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.video-thumbnail {
  position: relative;
}

.cursor-pointer {
  cursor: pointer;
}

.video-container {
  position: relative;
  width: 100%;
  aspect-ratio: 16/9;
}

.video-iframe,
.video-player {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  border-radius: 8px;
}
</style>
