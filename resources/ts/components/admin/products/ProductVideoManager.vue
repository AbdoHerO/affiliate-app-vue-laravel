<template>
  <VCard>
    <VCardTitle class="d-flex align-center justify-space-between">
      <span>{{ $t('admin_produits_videos') }}</span>
      <VBtn
        color="primary"
        size="small"
        prepend-icon="tabler-plus"
        @click="showAddDialog = true"
      >
        {{ $t('admin_produits_add_video') }}
      </VBtn>
    </VCardTitle>

    <VCardText>
      <!-- Videos List -->
      <div v-if="videos.length > 0" class="mb-4">
        <VRow>
          <VCol
            v-for="(video, index) in videos"
            :key="video.id"
            cols="12"
            md="6"
            lg="4"
          >
            <VCard variant="outlined" class="h-100">
              <VCardText>
                <div class="d-flex align-center justify-space-between mb-2">
                  <VChip size="small" color="primary">
                    {{ $t('admin_produits_video') }} {{ index + 1 }}
                  </VChip>
                  <div>
                    <VBtn
                      icon="tabler-edit"
                      size="x-small"
                      variant="text"
                      @click="editVideo(video)"
                    />
                    <VBtn
                      icon="tabler-trash"
                      size="x-small"
                      variant="text"
                      color="error"
                      @click="deleteVideo(video)"
                    />
                  </div>
                </div>

                <div class="mb-2">
                  <strong>{{ video.titre || $t('admin_produits_no_title') }}</strong>
                </div>

                <div class="text-caption text-medium-emphasis mb-2">
                  {{ video.url }}
                </div>

                <!-- Video Preview -->
                <div class="video-preview">
                  <iframe
                    v-if="getVideoEmbedUrl(video.url)"
                    :src="getVideoEmbedUrl(video.url)"
                    width="100%"
                    height="150"
                    frameborder="0"
                    allowfullscreen
                  />
                  <div v-else class="d-flex align-center justify-center" style="height: 150px; background: #f5f5f5;">
                    <VIcon icon="tabler-video" size="48" color="disabled" />
                  </div>
                </div>
              </VCardText>
            </VCard>
          </VCol>
        </VRow>
      </div>

      <!-- Empty State -->
      <div v-else class="text-center py-8">
        <VIcon icon="tabler-video-off" size="64" color="disabled" class="mb-4" />
        <h3 class="text-h6 mb-2">{{ $t('admin_produits_no_videos') }}</h3>
        <p class="text-medium-emphasis mb-4">{{ $t('admin_produits_no_videos_desc') }}</p>
        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          @click="showAddDialog = true"
        >
          {{ $t('admin_produits_add_first_video') }}
        </VBtn>
      </div>
    </VCardText>

    <!-- Add/Edit Video Dialog -->
    <VDialog v-model="showAddDialog" max-width="600">
      <VCard>
        <VCardTitle>
          {{ editingVideo ? $t('admin_produits_edit_video') : $t('admin_produits_add_video') }}
        </VCardTitle>

        <VCardText>
          <VForm ref="formRef" @submit.prevent="saveVideo">
            <VTextField
              v-model="videoForm.url"
              :label="$t('admin_produits_video_url')"
              :placeholder="$t('admin_produits_video_url_placeholder')"
              :error-messages="errors.url"
              required
              class="mb-4"
            />

            <VTextField
              v-model="videoForm.titre"
              :label="$t('admin_produits_video_title')"
              :placeholder="$t('admin_produits_video_title_placeholder')"
              :error-messages="errors.titre"
              class="mb-4"
            />

            <VTextField
              v-model.number="videoForm.ordre"
              :label="$t('admin_produits_video_order')"
              type="number"
              min="0"
              :error-messages="errors.ordre"
              class="mb-4"
            />

            <!-- Video Preview -->
            <div v-if="videoForm.url && getVideoEmbedUrl(videoForm.url)" class="mb-4">
              <VLabel class="mb-2">{{ $t('admin_produits_video_preview') }}</VLabel>
              <iframe
                :src="getVideoEmbedUrl(videoForm.url)"
                width="100%"
                height="200"
                frameborder="0"
                allowfullscreen
              />
            </div>
          </VForm>
        </VCardText>

        <VCardActions>
          <VSpacer />
          <VBtn
            variant="outlined"
            @click="closeDialog"
          >
            {{ $t('common.cancel') }}
          </VBtn>
          <VBtn
            color="primary"
            :loading="isLoading"
            @click="saveVideo"
          >
            {{ editingVideo ? $t('common.update') : $t('common.add') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Delete Confirmation Dialog -->
    <VDialog v-model="showDeleteDialog" max-width="400">
      <VCard>
        <VCardTitle>{{ $t('admin_produits_delete_video') }}</VCardTitle>
        <VCardText>
          {{ $t('admin_produits_delete_video_confirm') }}
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="outlined" @click="showDeleteDialog = false">
            {{ $t('common.cancel') }}
          </VBtn>
          <VBtn
            color="error"
            :loading="isLoading"
            @click="confirmDeleteVideo"
          >
            {{ $t('common.delete') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </VCard>
</template>

<script setup lang="ts">
import { ref, reactive, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useApi } from '@/composables/useApi'
import { useNotifications } from '@/composables/useNotifications'

interface Video {
  id: string
  url: string
  titre?: string
  ordre: number
}

interface Props {
  productId: string
  modelValue: Video[]
}

interface Emits {
  (e: 'update:modelValue', value: Video[]): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const { t } = useI18n()
const { showSuccess, showError } = useNotifications()

// Local state
const videos = ref<Video[]>([...props.modelValue])
const showAddDialog = ref(false)
const showDeleteDialog = ref(false)
const isLoading = ref(false)
const editingVideo = ref<Video | null>(null)
const videoToDelete = ref<Video | null>(null)
const formRef = ref()

const videoForm = reactive({
  url: '',
  titre: '',
  ordre: 0
})

const errors = ref<Record<string, string[]>>({})

// Watch for external changes
watch(() => props.modelValue, (newValue) => {
  videos.value = [...newValue]
}, { deep: true })

// Emit changes
watch(videos, (newValue) => {
  emit('update:modelValue', newValue)
}, { deep: true })

// Methods
const getVideoEmbedUrl = (url: string): string | null => {
  // YouTube
  const youtubeMatch = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&\n?#]+)/)
  if (youtubeMatch) {
    return `https://www.youtube.com/embed/${youtubeMatch[1]}`
  }

  // Vimeo
  const vimeoMatch = url.match(/vimeo\.com\/(\d+)/)
  if (vimeoMatch) {
    return `https://player.vimeo.com/video/${vimeoMatch[1]}`
  }

  return null
}

const editVideo = (video: Video) => {
  editingVideo.value = video
  videoForm.url = video.url
  videoForm.titre = video.titre || ''
  videoForm.ordre = video.ordre
  showAddDialog.value = true
}

const deleteVideo = (video: Video) => {
  videoToDelete.value = video
  showDeleteDialog.value = true
}

const saveVideo = async () => {
  errors.value = {}
  isLoading.value = true

  try {
    const endpoint = editingVideo.value 
      ? `/admin/produits/${props.productId}/videos/${editingVideo.value.id}`
      : `/admin/produits/${props.productId}/videos`
    
    const method = editingVideo.value ? 'PUT' : 'POST'

    const { data, error } = await useApi(endpoint, {
      method,
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(videoForm)
    })

    if (error.value) {
      const apiError = error.value as any
      if (apiError.errors) {
        errors.value = apiError.errors
      } else {
        showError(apiError.message || 'Error saving video')
      }
      return
    }

    const response = data.value as any
    if (response.success) {
      if (editingVideo.value) {
        // Update existing video
        const index = videos.value.findIndex(v => v.id === editingVideo.value!.id)
        if (index !== -1) {
          videos.value[index] = response.data
        }
        showSuccess(t('admin_produits_video_updated'))
      } else {
        // Add new video
        videos.value.push(response.data)
        showSuccess(t('admin_produits_video_added'))
      }
      closeDialog()
    }
  } catch (err) {
    showError('Error saving video')
  } finally {
    isLoading.value = false
  }
}

const confirmDeleteVideo = async () => {
  if (!videoToDelete.value) return

  isLoading.value = true
  try {
    const { error } = await useApi(`/admin/produits/${props.productId}/videos/${videoToDelete.value.id}`, {
      method: 'DELETE'
    })

    if (error.value) {
      showError((error.value as any).message || 'Error deleting video')
      return
    }

    // Remove from local array
    const index = videos.value.findIndex(v => v.id === videoToDelete.value!.id)
    if (index !== -1) {
      videos.value.splice(index, 1)
    }

    showSuccess(t('admin_produits_video_deleted'))
    showDeleteDialog.value = false
    videoToDelete.value = null
  } catch (err) {
    showError('Error deleting video')
  } finally {
    isLoading.value = false
  }
}

const closeDialog = () => {
  showAddDialog.value = false
  editingVideo.value = null
  videoForm.url = ''
  videoForm.titre = ''
  videoForm.ordre = 0
  errors.value = {}
}
</script>

<style scoped>
.video-preview iframe {
  border-radius: 8px;
}
</style>
