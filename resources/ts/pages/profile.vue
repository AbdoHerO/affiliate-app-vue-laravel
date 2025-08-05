<script setup lang="ts">
import UserProfileHeader from '@/views/pages/user-profile/UserProfileHeader.vue'
import About from '@/views/pages/user-profile/profile/About.vue'
import ProfileImageUpload from '@/components/ProfileImageUpload.vue'
import { useAuth } from '@/composables/useAuth'
import { useAuthStore } from '@/stores/auth'

// Route meta (handled by file-based routing)
// This page requires authentication

const { user, isLoading } = useAuth()
const authStore = useAuthStore()

// Tab management
const activeTab = ref('profile')

const tabs = [
  {
    icon: 'tabler-user-check',
    title: 'Profile',
    value: 'profile'
  },
  {
    icon: 'tabler-users',
    title: 'Teams',
    value: 'teams'
  },
  {
    icon: 'tabler-folder',
    title: 'Projects',
    value: 'projects'
  },
  {
    icon: 'tabler-link',
    title: 'Connections',
    value: 'connections'
  }
]

// Profile editing state
const isEditingProfile = ref(false)
const editForm = ref({
  nom_complet: '',
  email: '',
  telephone: '',
  adresse: '',
  photo_profil: ''
})

// Initialize edit form with user data
watch(user, (newUser) => {
  if (newUser) {
    editForm.value = {
      nom_complet: newUser.nom_complet,
      email: newUser.email,
      telephone: newUser.telephone || '',
      adresse: newUser.adresse || '',
      photo_profil: newUser.photo_profil || ''
    }
  }
}, { immediate: true })

const saveProfile = async () => {
  try {
    // Make API call to update the user profile
    const response = await fetch('/api/profile', {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${authStore.token}`,
        'Accept': 'application/json',
      },
      body: JSON.stringify(editForm.value)
    })

    const result = await response.json()

    if (result.success) {
      // Update the user data in the auth store
      // You would typically call a method to refresh user data
      isEditingProfile.value = false

      // Show success message
      console.log('Profile updated successfully')
      alert('Profile updated successfully!')
    } else {
      throw new Error(result.message || 'Profile update failed')
    }
  } catch (error) {
    console.error('Error saving profile:', error)
    alert('Error saving profile: ' + (error instanceof Error ? error.message : 'Unknown error'))
  }
}

const cancelEdit = () => {
  // Reset form to original values
  if (user.value) {
    editForm.value = {
      nom_complet: user.value.nom_complet,
      email: user.value.email,
      telephone: user.value.telephone || '',
      adresse: user.value.adresse || '',
      photo_profil: user.value.photo_profil || ''
    }
  }
  isEditingProfile.value = false
}
</script>

<template>
  <div>
    <!-- Loading state -->
    <div v-if="isLoading" class="d-flex justify-center align-center" style="min-height: 400px;">
      <VProgressCircular
        indeterminate
        color="primary"
        size="64"
      />
    </div>

    <!-- Profile content -->
    <div v-else-if="user">
      <!-- Profile Header -->
      <UserProfileHeader />

      <!-- Profile Tabs -->
      <VTabs
        v-model="activeTab"
        class="v-tabs-pill mb-6"
      >
        <VTab
          v-for="tab in tabs"
          :key="tab.value"
          :value="tab.value"
        >
          <VIcon
            size="20"
            start
            :icon="tab.icon"
          />
          {{ $t(tab.title.toLowerCase()) }}
        </VTab>
      </VTabs>

      <!-- Tab Content -->
      <VWindow v-model="activeTab">
        <!-- Profile Tab -->
        <VWindowItem value="profile">
          <VRow>
            <VCol
              cols="12"
              lg="4"
            >
              <!-- About Section -->
              <About />
            </VCol>

            <VCol
              cols="12"
              lg="8"
            >
              <!-- Edit Profile Form -->
              <VCard>
                <VCardText>
                  <div class="d-flex justify-space-between align-center mb-6">
                    <h5 class="text-h5">
                      {{ $t('profile_information') }}
                    </h5>
                    <VBtn
                      v-if="!isEditingProfile"
                      prepend-icon="tabler-edit"
                      @click="isEditingProfile = true"
                    >
                      {{ $t('action_edit') }}
                    </VBtn>
                  </div>

                  <VForm v-if="isEditingProfile">
                    <VRow>
                      <VCol cols="12">
                        <ProfileImageUpload
                          v-model="editForm.photo_profil"
                          :label="$t('profile_image')"
                          class="mb-4"
                        />
                      </VCol>

                      <VCol cols="12" md="6">
                        <VTextField
                          v-model="editForm.nom_complet"
                          :label="$t('form_full_name')"
                          :placeholder="$t('placeholder_enter_full_name')"
                        />
                      </VCol>

                      <VCol cols="12" md="6">
                        <VTextField
                          v-model="editForm.email"
                          :label="$t('form_email')"
                          :placeholder="$t('placeholder_enter_email')"
                          type="email"
                        />
                      </VCol>

                      <VCol cols="12" md="6">
                        <VTextField
                          v-model="editForm.telephone"
                          :label="$t('form_phone')"
                          :placeholder="$t('placeholder_enter_phone')"
                        />
                      </VCol>

                      <VCol cols="12" md="6">
                        <VTextField
                          v-model="editForm.adresse"
                          :label="$t('form_address')"
                          :placeholder="$t('placeholder_enter_address')"
                        />
                      </VCol>

                      <VCol cols="12">
                        <div class="d-flex gap-4">
                          <VBtn
                            color="primary"
                            @click="saveProfile"
                          >
                            {{ $t('action_save') }}
                          </VBtn>
                          <VBtn
                            variant="outlined"
                            @click="cancelEdit"
                          >
                            {{ $t('action_cancel') }}
                          </VBtn>
                        </div>
                      </VCol>
                    </VRow>
                  </VForm>

                  <!-- Display Mode -->
                  <div v-else>
                    <VRow>
                      <VCol cols="12" md="6">
                        <div class="mb-4">
                          <label class="text-body-2 text-disabled">{{ $t('form_full_name') }}</label>
                          <p class="text-body-1 mb-0">{{ user.nom_complet }}</p>
                        </div>
                      </VCol>

                      <VCol cols="12" md="6">
                        <div class="mb-4">
                          <label class="text-body-2 text-disabled">{{ $t('form_email') }}</label>
                          <p class="text-body-1 mb-0">{{ user.email }}</p>
                        </div>
                      </VCol>

                      <VCol cols="12" md="6">
                        <div class="mb-4">
                          <label class="text-body-2 text-disabled">{{ $t('form_phone') }}</label>
                          <p class="text-body-1 mb-0">{{ user.telephone || 'Not provided' }}</p>
                        </div>
                      </VCol>

                      <VCol cols="12" md="6">
                        <div class="mb-4">
                          <label class="text-body-2 text-disabled">{{ $t('form_address') }}</label>
                          <p class="text-body-1 mb-0">{{ user.adresse || 'Not provided' }}</p>
                        </div>
                      </VCol>
                    </VRow>
                  </div>
                </VCardText>
              </VCard>
            </VCol>
          </VRow>
        </VWindowItem>

        <!-- Teams Tab -->
        <VWindowItem value="teams">
          <VCard>
            <VCardText>
              <div class="text-center py-16">
                <VIcon
                  icon="tabler-users"
                  size="64"
                  class="mb-4 text-disabled"
                />
                <h5 class="text-h5 mb-2">{{ $t('status_coming_soon') }}</h5>
                <p class="text-body-1 text-disabled">
                  Teams functionality will be available soon.
                </p>
              </div>
            </VCardText>
          </VCard>
        </VWindowItem>

        <!-- Projects Tab -->
        <VWindowItem value="projects">
          <VCard>
            <VCardText>
              <div class="text-center py-16">
                <VIcon
                  icon="tabler-folder"
                  size="64"
                  class="mb-4 text-disabled"
                />
                <h5 class="text-h5 mb-2">{{ $t('status_coming_soon') }}</h5>
                <p class="text-body-1 text-disabled">
                  Projects functionality will be available soon.
                </p>
              </div>
            </VCardText>
          </VCard>
        </VWindowItem>

        <!-- Connections Tab -->
        <VWindowItem value="connections">
          <VCard>
            <VCardText>
              <div class="text-center py-16">
                <VIcon
                  icon="tabler-link"
                  size="64"
                  class="mb-4 text-disabled"
                />
                <h5 class="text-h5 mb-2">{{ $t('status_coming_soon') }}</h5>
                <p class="text-body-1 text-disabled">
                  Connections functionality will be available soon.
                </p>
              </div>
            </VCardText>
          </VCard>
        </VWindowItem>
      </VWindow>
    </div>

    <!-- Error state -->
    <div v-else class="text-center py-16">
      <VIcon
        icon="tabler-user-x"
        size="64"
        class="mb-4 text-disabled"
      />
      <h5 class="text-h5 mb-2">{{ $t('error_generic') }}</h5>
      <p class="text-body-1 text-disabled">
        Unable to load profile information.
      </p>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.v-tabs-pill {
  .v-tab {
    border-radius: 0.375rem;
  }
}
</style>
