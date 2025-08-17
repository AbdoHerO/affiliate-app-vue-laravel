<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useApi } from '@/composables/useApi'

interface User {
  id: string
  nom_complet: string
  email: string
  photo_profil?: string
}

interface Props {
  modelValue?: string | null
  label?: string
  placeholder?: string
  clearable?: boolean
  disabled?: boolean
  variant?: 'filled' | 'outlined' | 'plain' | 'underlined' | 'solo' | 'solo-inverted' | 'solo-filled'
  density?: 'default' | 'comfortable' | 'compact'
  hideDetails?: boolean
  errorMessages?: string[]
}

interface Emits {
  (e: 'update:modelValue', value: string | null): void
}

const props = withDefaults(defineProps<Props>(), {
  label: 'Assignee',
  placeholder: 'Search for a user...',
  clearable: true,
  disabled: false,
  variant: 'outlined',
  density: 'default',
  hideDetails: false,
})

const emit = defineEmits<Emits>()

const { t } = useI18n()
const { api } = useApi()

// State
const users = ref<User[]>([])
const loading = ref(false)
const search = ref('')

// Methods
const searchUsers = async (query: string) => {
  if (!query || query.length < 2) {
    users.value = []
    return
  }

  loading.value = true
  try {
    const response = await api.get('/admin/users', {
      params: {
        q: query,
        per_page: 20,
        role: 'admin', // Only show admin users for assignment
      }
    })

    if (response.data.success) {
      users.value = response.data.data
    }
  } catch (error) {
    console.error('Failed to search users:', error)
    users.value = []
  } finally {
    loading.value = false
  }
}

// Load initial admin users
const loadAdminUsers = async () => {
  loading.value = true
  try {
    const response = await api.get('/admin/users', {
      params: {
        per_page: 50,
        role: 'admin',
      }
    })

    if (response.data.success) {
      users.value = response.data.data
    }
  } catch (error) {
    console.error('Failed to load admin users:', error)
  } finally {
    loading.value = false
  }
}

// Watch search input
watch(search, (newSearch) => {
  if (newSearch) {
    searchUsers(newSearch)
  } else {
    loadAdminUsers()
  }
}, { debounce: 300 })

// Load initial data
onMounted(() => {
  loadAdminUsers()
})

// Computed
const selectedUser = ref<User | null>(null)

// Watch model value to find selected user
watch(() => props.modelValue, (newValue) => {
  if (newValue) {
    selectedUser.value = users.value.find(u => u.id === newValue) || null
  } else {
    selectedUser.value = null
  }
}, { immediate: true })

// Handle selection
const handleSelection = (user: User | null) => {
  emit('update:modelValue', user?.id || null)
}
</script>

<template>
  <VAutocomplete
    :model-value="selectedUser"
    :items="users"
    :loading="loading"
    :label="label"
    :placeholder="placeholder"
    :clearable="clearable"
    :disabled="disabled"
    :variant="variant"
    :density="density"
    :hide-details="hideDetails"
    :error-messages="errorMessages"
    item-title="nom_complet"
    item-value="id"
    return-object
    @update:model-value="handleSelection"
    @update:search="search = $event"
  >
    <template #item="{ props: itemProps, item }">
      <VListItem v-bind="itemProps">
        <template #prepend>
          <VAvatar size="32">
            <VImg
              v-if="item.raw.photo_profil"
              :src="item.raw.photo_profil"
              :alt="item.raw.nom_complet"
            />
            <VIcon
              v-else
              icon="tabler-user"
              size="18"
            />
          </VAvatar>
        </template>
        
        <VListItemTitle>{{ item.raw.nom_complet }}</VListItemTitle>
        <VListItemSubtitle>{{ item.raw.email }}</VListItemSubtitle>
      </VListItem>
    </template>

    <template #selection="{ item }">
      <div class="d-flex align-center">
        <VAvatar size="24" class="me-2">
          <VImg
            v-if="item.raw.photo_profil"
            :src="item.raw.photo_profil"
            :alt="item.raw.nom_complet"
          />
          <VIcon
            v-else
            icon="tabler-user"
            size="14"
          />
        </VAvatar>
        <span>{{ item.raw.nom_complet }}</span>
      </div>
    </template>

    <template #no-data>
      <VListItem>
        <VListItemTitle>
          {{ search ? t('no_users_found') : t('type_to_search') }}
        </VListItemTitle>
      </VListItem>
    </template>
  </VAutocomplete>
</template>
