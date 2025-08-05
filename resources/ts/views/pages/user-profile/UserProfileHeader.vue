<script lang="ts" setup>
import { useAuth } from '@/composables/useAuth'
import { getAvatarUrl } from '@/utils/imageUtils'
import type { ProfileHeader } from '@/types/profile'
import defaultCoverImg from '@images/pages/user-profile-header-bg.png'
import defaultAvatar from '@images/avatars/avatar-1.png'

const { user } = useAuth()

// Create profile header data from authenticated user
const profileHeaderData = computed<ProfileHeader>(() => {
  if (!user.value) {
    return {
      fullName: '',
      location: '',
      joiningDate: '',
      designation: '',
      profileImg: defaultAvatar,
      coverImg: defaultCoverImg,
    }
  }

  return {
    fullName: user.value.nom_complet,
    location: user.value.adresse || 'Location not specified',
    joiningDate: user.value.created_at 
      ? `Joined ${new Date(user.value.created_at).toLocaleDateString('en-US', { month: 'long', year: 'numeric' })}`
      : 'Join date unknown',
    designation: user.value.roles?.includes('admin') ? 'Administrator' : 'Affiliate Partner',
    profileImg: getAvatarUrl(user.value.photo_profil),
    coverImg: defaultCoverImg,
  }
})

const isEditing = ref(false)
const fileInput = ref<HTMLInputElement>()

const handleImageUpload = () => {
  fileInput.value?.click()
}

const onFileChange = (event: Event) => {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0]
  
  if (file) {
    // Here you would typically upload the file to your server
    // For now, we'll just create a local URL for preview
    const reader = new FileReader()
    reader.onload = (e) => {
      // Update user profile image
      // This would be an API call in a real implementation
      console.log('File uploaded:', file.name)
      console.log('Preview URL:', e.target?.result)
    }
    reader.readAsDataURL(file)
  }
}
</script>

<template>
  <VCard v-if="profileHeaderData">
    <div
      class="profile-cover-bg"
      style="min-height: 200px; background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, rgb(var(--v-theme-secondary)) 100%);"
    >
      <div class="d-flex align-end justify-end pa-4">
        <VChip
          :color="user?.statut === 'actif' ? 'success' : 'error'"
          size="small"
          class="text-capitalize"
        >
          {{ user?.statut }}
        </VChip>
      </div>
    </div>

    <VCardText class="d-flex align-bottom flex-sm-row flex-column justify-center gap-x-6">
      <div class="d-flex h-0 position-relative">
        <VAvatar
          rounded
          size="130"
          :image="profileHeaderData.profileImg"
          class="user-profile-avatar mx-auto"
        />
        
        <!-- Edit Profile Image Button -->
        <VBtn
          v-if="isEditing"
          icon
          size="small"
          color="primary"
          class="position-absolute"
          style="bottom: 0; right: 0;"
          @click="handleImageUpload"
        >
          <VIcon icon="tabler-camera" />
        </VBtn>
        
        <!-- Hidden file input -->
        <input
          ref="fileInput"
          type="file"
          accept="image/*"
          style="display: none;"
          @change="onFileChange"
        >
      </div>

      <div class="user-profile-info w-100 mt-16 pt-6 pt-sm-0 mt-sm-0">
        <h4 class="text-h4 text-center text-sm-start font-weight-medium mb-2">
          {{ profileHeaderData?.fullName }}
        </h4>

        <div class="d-flex align-center justify-center justify-sm-space-between flex-wrap gap-5">
          <div class="d-flex flex-wrap justify-center justify-sm-start flex-grow-1 gap-6">
            <span class="d-flex gap-x-2 align-center">
              <VIcon
                size="24"
                icon="tabler-user-star"
              />
              <div class="text-body-1 font-weight-medium">
                {{ profileHeaderData?.designation }}
              </div>
            </span>

            <span class="d-flex gap-x-2 align-center">
              <VIcon
                size="24"
                icon="tabler-map-pin"
              />
              <div class="text-body-1 font-weight-medium">
                {{ profileHeaderData?.location }}
              </div>
            </span>

            <span class="d-flex gap-x-2 align-center">
              <VIcon
                size="24"
                icon="tabler-calendar"
              />
              <div class="text-body-1 font-weight-medium">
                {{ profileHeaderData?.joiningDate }}
              </div>
            </span>
          </div>

          <div class="d-flex gap-2">
            <VBtn 
              v-if="!isEditing"
              prepend-icon="tabler-edit"
              @click="isEditing = true"
            >
              {{ $t('action_edit') }}
            </VBtn>
            
            <template v-else>
              <VBtn 
                color="success"
                prepend-icon="tabler-check"
                @click="isEditing = false"
              >
                {{ $t('action_save') }}
              </VBtn>
              <VBtn 
                variant="outlined"
                prepend-icon="tabler-x"
                @click="isEditing = false"
              >
                {{ $t('action_cancel') }}
              </VBtn>
            </template>
          </div>
        </div>
      </div>
    </VCardText>
  </VCard>
</template>

<style lang="scss">
.user-profile-avatar {
  border: 5px solid rgb(var(--v-theme-surface));
  background-color: rgb(var(--v-theme-surface)) !important;
  inset-block-start: -3rem;

  .v-img__img {
    border-radius: 0.125rem;
  }
}
</style>
