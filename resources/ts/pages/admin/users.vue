<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import { useAuth } from '@/composables/useAuth'
import { useI18n } from 'vue-i18n'
import { useNotifications } from '@/composables/useNotifications'
import { useApi } from '@/composables/useApi'

definePage({
  meta: {
    requiresAuth: true,
    requiresRole: 'admin',
  },
})

const { hasPermission } = useAuth()
const api = useApi()               // ✅ get the instance once and reuse it
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
const users = ref<User[]>([])
const loading = ref(false)
const error = ref<string | null>(null)
const pagination = ref({
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 0,
})

// Filters
const filters = ref({
  search: '',
  role: '',
  statut: '',
})

const roles = ref<{ title: string; value: string }[]>([])

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

// -----------------------------
// API Functions (fixed)
// -----------------------------
const fetchUsers = async (page = 1) => {
  try {
    loading.value = true
    error.value = null

    const params = new URLSearchParams({
      page: page.toString(),
      per_page: pagination.value.per_page.toString(),
    })

    if (filters.value.search) params.set('search', filters.value.search)
    if (filters.value.role)   params.set('role', filters.value.role)
    if (filters.value.statut) params.set('statut', filters.value.statut)

    // ✅ always call the instance `api` and use params.toString()
    const { data, error: apiError } = await api<any>(`/admin/users?${params.toString()}`)

    if (apiError.value) {
      error.value = apiError.value.message || 'Failed to load users'
      showError(t('failed_to_load_users'))
      console.error('Users fetch error:', apiError.value)
    } else if (data.value) {
      users.value = data.value.users.map((user: any) => ({
        id: String(user.id),
        nom_complet: user.nom_complet,
        email: user.email,
        roles: user.roles?.map((r: any) => r.name) ?? [],
        statut: user.statut,
        kyc_statut: user.kyc_statut,
        created_at: user.created_at,
      }))

      pagination.value = data.value.pagination
    }
  } catch (err: any) {
    error.value = err.message || 'Failed to load users'
    showError(t('failed_to_load_users'))
    console.error('Users fetch error:', err)
  } finally {
    loading.value = false
  }
}

const fetchRoles = async () => {
  try {
    const { data, error: apiError } = await api<any>('/admin/users/roles/list')

    if (apiError.value) {
      console.error('Roles fetch error:', apiError.value)
    } else if (data.value?.roles) {
      roles.value = data.value.roles.map((role: any) => ({
        title: role.name.charAt(0).toUpperCase() + role.name.slice(1),
        value: role.name,
      }))
    }
  } catch (err) {
    console.error('Roles fetch error:', err)
  }
}

const createUser = async () => {
  try {
    loading.value = true

    const { data, error: apiError } = await api<any>('/admin/users', {
      method: 'POST',
      body: JSON.stringify({
        nom_complet: userForm.value.nom_complet,
        email: userForm.value.email,
        password: userForm.value.password,
        role: userForm.value.role,
        statut: userForm.value.statut,
        kyc_statut: userForm.value.kyc_statut,
      }),
      headers: { 'Content-Type': 'application/json' },
    })

    if (apiError.value) {
      showError(apiError.value.message || t('failed_to_create_user'))
      console.error('Create user error:', apiError.value)
    } else if (data.value) {
      showCreateDialog.value = false
      const name = userForm.value.nom_complet
      resetForm()
      await fetchUsers(pagination.value.current_page)
      showSuccess(t('user_created_successfully', { name }))
    }
  } catch (err: any) {
    showError(err.message || t('failed_to_create_user'))
    console.error('Create user error:', err)
  } finally {
    loading.value = false
  }
}

const updateUser = async () => {
  if (!selectedUser.value) return

  try {
    loading.value = true

    const payload: any = {
      nom_complet: userForm.value.nom_complet,
      email: userForm.value.email,
      role: userForm.value.role,
      statut: userForm.value.statut,
      kyc_statut: userForm.value.kyc_statut,
    }
    if (userForm.value.password) payload.password = userForm.value.password

    const { data, error: apiError } = await api<any>(`/admin/users/${selectedUser.value.id}`, {
      method: 'PUT',
      body: JSON.stringify(payload),
      headers: { 'Content-Type': 'application/json' },
    })

    if (apiError.value) {
      showError(apiError.value.message || t('failed_to_update_user'))
      console.error('Update user error:', apiError.value)
    } else if (data.value) {
      showEditDialog.value = false
      const name = userForm.value.nom_complet
      resetForm()
      await fetchUsers(pagination.value.current_page)
      showSuccess(t('user_updated_successfully', { name }))
    }
  } catch (err: any) {
    showError(err.message || t('failed_to_update_user'))
    console.error('Update user error:', err)
  } finally {
    loading.value = false
  }
}

const toggleUserStatus = async (user: User) => {
  try {
    const { data, error: apiError } = await api<any>(`/admin/users/${user.id}/toggle-status`, {
      method: 'POST',
    })

    if (apiError.value) {
      showError(apiError.value.message || 'Failed to toggle user status')
      console.error('Toggle status error:', apiError.value)
    } else if (data.value) {
      await fetchUsers(pagination.value.current_page)
      showSuccess(t('user_status_updated_successfully'))
    }
  } catch (err: any) {
    showError(err.message || 'Failed to toggle user status')
    console.error('Toggle status error:', err)
  }
}

const deleteUser = (user: User) => {
  showConfirm(
    t('confirm_delete'),
    t('confirm_delete_user', { name: user.nom_complet }),
    async () => {
      try {
        const { data, error: apiError } = await api<any>(`/admin/users/${user.id}`, {
          method: 'DELETE',
        })

        if (apiError.value) {
          showError(apiError.value.message || t('failed_to_delete_user'))
          console.error('Delete user error:', apiError.value)
        } else if (data.value) {
          await fetchUsers(pagination.value.current_page)
          showSuccess(t('user_deleted_successfully', { name: user.nom_complet }))
        }
      } catch (err: any) {
        showError(err.message || t('failed_to_delete_user'))
        console.error('Delete user error:', err)
      }
    },
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

const clearFilters = () => {
  filters.value = { search: '', role: '', statut: '' }
  fetchUsers(1)
}

// Load data on mount
onMounted(async () => {
  await Promise.all([fetchUsers(), fetchRoles()])
})

// Watch filters for real-time search
watch(
  filters,
  () => {
    fetchUsers(1)
  },
  { deep: true },
)
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
        <VTable v-if="!loading && users.length">
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
            <tr v-for="user in users" :key="user.id">
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

        <!-- Pagination -->
        <div v-if="pagination.total > pagination.per_page" class="d-flex justify-center mt-4">
          <VPagination
            v-model="pagination.current_page"
            :length="pagination.last_page"
            @update:model-value="fetchUsers"
          />
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
