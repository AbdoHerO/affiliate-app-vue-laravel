<script setup lang="ts">
import { ref, computed } from 'vue'
import { useAuth } from '@/composables/useAuth'
import { useI18n } from 'vue-i18n'
import { useNotifications } from '@/composables/useNotifications'
// import { useApi } from '@/composables/useApi' // <- not used here, keep commented to avoid side effects

definePage({
  meta: {
    requiresAuth: true,
    requiresRole: 'admin',
  },
})

const { hasPermission } = useAuth()
// const { api } = useApi() // <- not used; uncomment only when you actually call it
const { t } = useI18n()
const { showSuccess, showError, showConfirm, snackbar, confirmDialog } = useNotifications()

type User = {
  id: string
  nom_complet: string
  email: string
  roles: string[]
  statut: 'actif' | 'inactif' | 'suspendu'
  kyc_statut: 'approuve' | 'refuse' | 'en_attente' | 'non_requis'
  created_at: string
}

// Data
const users = ref<User[]>([
  { id: '1', nom_complet: 'John Doe',  email: 'john@example.com', roles: ['admin'],     statut: 'actif',    kyc_statut: 'approuve',   created_at: '2024-01-01' },
  { id: '2', nom_complet: 'Jane Smith',email: 'jane@example.com', roles: ['affiliate'], statut: 'actif',    kyc_statut: 'en_attente', created_at: '2024-01-02' },
  { id: '3', nom_complet: 'Bob Wilson',email: 'bob@example.com',  roles: ['affiliate'], statut: 'suspendu', kyc_statut: 'refuse',     created_at: '2024-01-03' },
])

const loading = ref(false)
const error = ref<unknown>(null)

// Filters
const filters = ref({
  search: '',
  role: '',
  statut: '',
})

const roles = ref([
  { title: 'Admin', value: 'admin' },
  { title: 'Affiliate', value: 'affiliate' },
])

const statusOptions = [
  { title: t('all_status'), value: '' },
  { title: t('active'), value: 'actif' },
  { title: t('inactive'), value: 'inactif' },
  { title: t('suspended'), value: 'suspendu' },
]

// Dialog states
const showCreateDialog = ref(false)
const showEditDialog = ref(false)
const selectedUser = ref<User | null>(null)

// Form data
const userForm = ref({
  nom_complet: '',
  email: '',
  password: '',
  role: '',
  statut: 'actif' as User['statut'],
  kyc_statut: 'non_requis' as User['kyc_statut'],
})

// Mock functions (replace with real API later)
const createUser = () => {
  const newUser: User = {
    id: Date.now().toString(),
    nom_complet: userForm.value.nom_complet,
    email: userForm.value.email,
    roles: userForm.value.role ? [userForm.value.role] : [],
    statut: userForm.value.statut,
    kyc_statut: userForm.value.kyc_statut,
    created_at: new Date().toISOString(),
  }
  users.value.push(newUser)
  showCreateDialog.value = false
  resetForm()
  showSuccess(t('user_created_successfully', { name: newUser.nom_complet }))
}

const updateUser = () => {
  if (!selectedUser.value) return
  const index = users.value.findIndex(u => u.id === selectedUser.value!.id)
  if (index !== -1) {
    users.value[index] = {
      ...users.value[index],
      nom_complet: userForm.value.nom_complet,
      email: userForm.value.email,
      roles: userForm.value.role ? [userForm.value.role] : [],
      statut: userForm.value.statut,
      kyc_statut: userForm.value.kyc_statut,
    }
  }
  showEditDialog.value = false
  resetForm()
  showSuccess(t('user_updated_successfully', { name: userForm.value.nom_complet }))
}

const toggleUserStatus = (user: User) => {
  const index = users.value.findIndex(u => u.id === user.id)
  if (index !== -1) {
    users.value[index].statut = users.value[index].statut === 'actif' ? 'suspendu' : 'actif'
  }
}

const deleteUser = (user: User) => {
  showConfirm(
    t('confirm_delete'),
    t('confirm_delete_user', { name: user.nom_complet }),
    () => {
      const index = users.value.findIndex(u => u.id === user.id)
      if (index !== -1) {
        users.value.splice(index, 1)
        showSuccess(t('user_deleted_successfully', { name: user.nom_complet }))
      }
    }
  )
}

// Form helpers
const resetForm = () => {
  userForm.value = {
    nom_complet: '',
    email: '',
    password: '',
    role: '',
    statut: 'actif',
    kyc_statut: 'non_requis',
  }
  selectedUser.value = null
}

const openEditDialog = (user: User) => {
  selectedUser.value = user
  userForm.value = {
    nom_complet: user.nom_complet,
    email: user.email,
    password: '',
    role: user.roles[0] || '',
    statut: user.statut,
    kyc_statut: user.kyc_statut,
  }
  showEditDialog.value = true
}

// Computed filtered users
const filteredUsers = computed(() => {
  return users.value.filter(user => {
    const query = filters.value.search.trim().toLowerCase()
    const matchesSearch =
      !query ||
      user.nom_complet.toLowerCase().includes(query) ||
      user.email.toLowerCase().includes(query)

    const matchesRole = !filters.value.role || user.roles.includes(filters.value.role)
    const matchesStatus = !filters.value.statut || user.statut === filters.value.statut

    return matchesSearch && matchesRole && matchesStatus
  })
})

const clearFilters = () => {
  filters.value = { search: '', role: '', statut: '' }
}
</script>

<template>
  <div>
    <!-- Header -->
    <VCard class="mb-6">
      <VCardText>
        <div class="d-flex justify-space-between align-center">
          <div>
            <h2 class="text-h4 mb-2">{{ t('user_management') }}</h2>
            <p class="text-body-1 mb-0">{{ t('manage_all_users') }}</p>
          </div>
          <VBtn
            color="primary"
            prepend-icon="tabler-plus"
            :disabled="!hasPermission('manage users')"
            @click="showCreateDialog = true"
          >
            {{ t('add_user') }}
          </VBtn>
        </div>
      </VCardText>
    </VCard>

    <!-- Filters -->
    <VCard class="mb-6">
      <VCardText>
        <VRow>
          <VCol cols="12" md="4">
            <VTextField
              v-model="filters.search"
              :placeholder="t('search_users')"
              prepend-inner-icon="tabler-search"
              clearable
            />
          </VCol>
          <VCol cols="12" md="3">
            <VSelect
              v-model="filters.role"
              :items="[{ title: 'All Roles', value: '' }, ...roles]"
              placeholder="Filter by role"
            />
          </VCol>
          <VCol cols="12" md="3">
            <VSelect
              v-model="filters.statut"
              :items="statusOptions"
              placeholder="Filter by status"
            />
          </VCol>
          <VCol cols="12" md="2">
            <VBtn block variant="outlined" @click="clearFilters">
              Clear
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Users Table -->
    <VCard>
      <VCardText>
        <VTable v-if="!loading && filteredUsers.length">
          <thead>
            <tr>
              <th>{{ t('name') }}</th>
              <th>{{ t('email') }}</th>
              <th>{{ t('role') }}</th>
              <th>{{ t('status') }}</th>
              <th>{{ t('kyc_status') }}</th>
              <th>{{ t('created') }}</th>
              <th>{{ t('actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="user in filteredUsers" :key="user.id">
              <td>{{ user.nom_complet }}</td>
              <td>{{ user.email }}</td>
              <td>
                <VChip :color="user.roles[0] === 'admin' ? 'error' : 'primary'" size="small">
                  {{ user.roles[0] || 'No Role' }}
                </VChip>
              </td>
              <td>
                <VChip
                  :color="user.statut === 'actif' ? 'success' : user.statut === 'suspendu' ? 'error' : 'warning'"
                  size="small"
                >
                  {{ user.statut }}
                </VChip>
              </td>
              <td>
                <VChip
                  :color="user.kyc_statut === 'approuve' ? 'success' : user.kyc_statut === 'refuse' ? 'error' : 'warning'"
                  size="small"
                >
                  {{ user.kyc_statut }}
                </VChip>
              </td>
              <td>{{ new Date(user.created_at).toLocaleDateString() }}</td>
              <td>
                <div class="d-flex gap-2">
                  <VBtn icon size="small" color="primary" variant="text" @click="openEditDialog(user)">
                    <VIcon icon="tabler-edit" />
                  </VBtn>
                  <VBtn
                    icon
                    size="small"
                    :color="user.statut === 'actif' ? 'warning' : 'success'"
                    variant="text"
                    @click="toggleUserStatus(user)"
                  >
                    <VIcon :icon="user.statut === 'actif' ? 'tabler-user-off' : 'tabler-user-check'" />
                  </VBtn>
                  <VBtn icon size="small" color="error" variant="text" @click="deleteUser(user)">
                    <VIcon icon="tabler-trash" />
                  </VBtn>
                </div>
              </td>
            </tr>
          </tbody>
        </VTable>

        <!-- Loading State -->
        <div v-else-if="loading" class="text-center py-8">
          <VProgressCircular indeterminate color="primary" />
          <p class="mt-4">{{ t('loading_users') }}...</p>
        </div>

        <!-- Empty State -->
        <div v-else class="text-center py-8">
          <VIcon icon="tabler-users" size="64" class="mb-4" color="disabled" />
          <h6 class="text-h6 mb-2">{{ t('no_users_found') }}</h6>
          <p class="text-body-2">{{ t('try_adjusting_search') }}</p>
        </div>
      </VCardText>
    </VCard>

    <!-- Create User Dialog -->
    <VDialog v-model="showCreateDialog" max-width="600">
      <VCard>
        <VCardTitle>{{ t('create_new_user') }}</VCardTitle>
        <VCardText>
          <VForm @submit.prevent="createUser">
            <VTextField v-model="userForm.nom_complet" :label="t('full_name')" :placeholder="t('enter_full_name')" required class="mb-4" />
            <VTextField v-model="userForm.email" :label="t('email')" :placeholder="t('enter_email')" type="email" required class="mb-4" />
            <VTextField v-model="userForm.password" :label="t('password')" :placeholder="t('enter_password')" type="password" required class="mb-4" />
            <VSelect v-model="userForm.role" :items="roles" :label="t('role')" :placeholder="t('select_role')" required class="mb-4" />
            <VSelect v-model="userForm.statut" :items="statusOptions.slice(1)" :label="t('status')" :placeholder="t('select_status')" required />
          </VForm>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="outlined" @click="showCreateDialog = false">{{ t('cancel') }}</VBtn>
          <VBtn color="primary" @click="createUser">{{ t('create_user') }}</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Edit User Dialog -->
    <VDialog v-model="showEditDialog" max-width="600">
      <VCard>
        <VCardTitle>{{ t('edit_user') }}</VCardTitle>
        <VCardText>
          <VForm @submit.prevent="updateUser">
            <VTextField v-model="userForm.nom_complet" :label="t('full_name')" :placeholder="t('enter_full_name')" required class="mb-4" />
            <VTextField v-model="userForm.email" :label="t('email')" :placeholder="t('enter_email')" type="email" required class="mb-4" />
            <VSelect v-model="userForm.role" :items="roles" :label="t('role')" :placeholder="t('select_role')" required class="mb-4" />
            <VSelect v-model="userForm.statut" :items="statusOptions.slice(1)" :label="t('status')" :placeholder="t('select_status')" required />
          </VForm>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="outlined" @click="showEditDialog = false">{{ t('cancel') }}</VBtn>
          <VBtn color="primary" @click="updateUser">{{ t('update_user') }}</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Success/Error Snackbar -->
    <VSnackbar
      v-model="snackbar.show"
      :color="snackbar.color"
      :timeout="snackbar.timeout"
      location="top end"
    >
      {{ snackbar.message }}
    </VSnackbar>

    <!-- Confirmation Dialog -->
    <VDialog v-model="confirmDialog.show" max-width="500">
      <VCard class="text-center px-10 py-6">
        <VCardText>
          <VBtn
            icon
            variant="outlined"
            color="warning"
            class="my-4"
            style="block-size: 88px; inline-size: 88px; pointer-events: none;"
          >
            <span class="text-5xl">!</span>
          </VBtn>
          <h6 class="text-lg font-weight-medium">
            {{ confirmDialog.title }}
          </h6>
          <p class="mt-2">{{ confirmDialog.message }}</p>
        </VCardText>
        <VCardText class="d-flex align-center justify-center gap-2">
          <VBtn variant="elevated" @click="confirmDialog.onConfirm">
            {{ confirmDialog.confirmText }}
          </VBtn>
          <VBtn color="secondary" variant="tonal" @click="confirmDialog.onCancel">
            {{ confirmDialog.cancelText }}
          </VBtn>
        </VCardText>
      </VCard>
    </VDialog>
  </div>
</template>