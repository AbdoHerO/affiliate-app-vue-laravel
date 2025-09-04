<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import { useAuth } from '@/composables/useAuth'
import { useI18n } from 'vue-i18n'
import { useNotifications } from '@/composables/useNotifications'
import { useQuickConfirm } from '@/composables/useConfirmAction'
import ConfirmActionDialog from '@/components/common/ConfirmActionDialog.vue'
import { useApi } from '@/composables/useApi'
import { useFormErrors } from '@/composables/useFormErrors'
import ProfileImageUpload from '@/components/ProfileImageUpload.vue'
import { getAvatarUrl } from '@/utils/imageUtils'
import { useUserSoftDelete } from '@/composables/useSoftDelete'
import SoftDeleteFilter from '@/components/common/SoftDeleteFilter.vue'
import SoftDeleteActions from '@/components/common/SoftDeleteActions.vue'
import type { User } from '@/types/auth'

definePage({
  meta: {
    requiresAuth: true,
    requiresRole: 'admin',
  },
})

const { hasPermission } = useAuth()
const { t } = useI18n()
const { showSuccess, showError, snackbar } = useNotifications()
const {
  confirmCreate,
  confirmUpdate,
  confirmDelete,
  isDialogVisible: isConfirmDialogVisible,
  isLoading: isConfirmLoading,
  dialogTitle,
  dialogText,
  dialogIcon,
  dialogColor,
  confirmButtonText,
  cancelButtonText,
  handleConfirm,
  handleCancel
} = useQuickConfirm()

// Soft delete functionality
const {
  filter: softDeleteFilter,
  getQueryParams,
  isSoftDeleted,
  getStatusColor,
  getStatusText
} = useUserSoftDelete(
  () => fetchUsers(pagination.value.current_page), // onSuccess
  (error) => console.error('Soft delete error:', error) // onError
)

type User = {
  id: string
  nom_complet: string
  email: string
  telephone?: string
  adresse?: string
  photo_profil?: string
  statut: 'actif' | 'inactif' | 'bloque'
  email_verifie: boolean
  kyc_statut: 'non_requis' | 'en_attente' | 'valide' | 'refuse'
  rib?: string
  bank_type?: string
  roles: string[]
  permissions?: string[]
  remember_token?: string
  created_at: string
  updated_at?: string
  deleted_at?: string
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
  { title: t('blocked'), value: 'bloque' },
]

const kycStatusOptions = [
  { title: t('not_required'), value: 'non_requis' },
  { title: t('pending'), value: 'en_attente' },
  { title: t('approved'), value: 'valide' },
  { title: t('rejected'), value: 'refuse' },
]

// Dialog states
const showCreateDialog = ref(false)
const showEditDialog = ref(false)
const showViewDialog = ref(false)
const selectedUser = ref<User | null>(null)

// Form data
const userForm = ref({
  nom_complet: '',
  email: '',
  password: '',
  telephone: '',
  adresse: '',
  cin: '',
  photo_profil: '',
  role: '',
  statut: 'actif' as User['statut'],
  kyc_statut: 'non_requis' as User['kyc_statut'],
})

// Form errors handling
const { errors: userErrors, set: setUserErrors, clear: clearUserErrors } = useFormErrors<typeof userForm.value>()

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
      ...getQueryParams() // Add soft delete filter
    })

    if (filters.value.search) params.set('search', filters.value.search)
    if (filters.value.statut) params.set('statut', filters.value.statut)

    // Handle role filtering - exclude affiliate users from this view
    if (filters.value.role) {
      // If a specific role is selected, use it (but never allow affiliate)
      if (filters.value.role !== 'affiliate') {
        params.set('role', filters.value.role)
      }
    } else {
      // If no role filter, exclude affiliate users
      params.set('exclude_role', 'affiliate')
    }

    const url = `/admin/users?${params.toString()}`
    const { data, error: apiError } = await useApi<any>(url)

    if (apiError.value) {
      error.value = apiError.value.message || t('failed_to_load_users')
      showError(t('failed_to_load_users'))
      console.error('Users fetch error:', apiError.value)

      // Handle authentication errors
      if (apiError.value.status === 401) {
        showError(t('error_authentication_required'))
        // Optionally redirect to login
      }
    } else if (data.value) {
      users.value = data.value.users.map((user: any): User => ({
        id: String(user.id),
        nom_complet: user.nom_complet,
        email: user.email,
        telephone: user.telephone,
        adresse: user.adresse,
        cin: user.cin,
        photo_profil: user.photo_profil,
        roles: user.roles?.map((r: any) => r.name) ?? [],
        statut: user.statut,
        email_verifie: user.email_verifie,
        kyc_statut: user.kyc_statut,
        rib: user.rib,
        bank_type: user.bank_type,
        created_at: user.created_at,
        updated_at: user.updated_at,
        deleted_at: user.deleted_at,
        permissions: user.permissions ?? [],
        remember_token: user.remember_token,
      }))

      pagination.value = data.value.pagination
    }
  } catch (err: any) {
    error.value = err.message || t('failed_to_load_users')
    showError(t('failed_to_load_users'))
    console.error('Users fetch error:', err)
  } finally {
    loading.value = false
  }
}

const fetchRoles = async () => {
  try {
    const { data, error: apiError } = await useApi<any>('/admin/users/roles/list')

    if (apiError.value) {
      console.error('Roles fetch error:', apiError.value)
    } else if (data.value?.roles) {
      roles.value = data.value.roles
        .filter((role: any) => role.name !== 'affiliate') // Exclude affiliate role
        .map((role: any) => ({
          title: role.name.charAt(0).toUpperCase() + role.name.slice(1),
          value: role.name,
        }))
    }
  } catch (err) {
    console.error('Roles fetch error:', err)
  }
}

const createUser = async () => {
  // Show confirm dialog before creating
  const confirmed = await confirmCreate(t('user'))
  if (!confirmed) return

  try {
    loading.value = true

    const { data, error: apiError } = await useApi<any>('/admin/users', {
      method: 'POST',
      body: JSON.stringify({
        nom_complet: userForm.value.nom_complet,
        email: userForm.value.email,
        password: userForm.value.password,
        password_confirmation: userForm.value.password,
        telephone: userForm.value.telephone,
        adresse: userForm.value.adresse,
        cin: userForm.value.cin,
        photo_profil: userForm.value.photo_profil,
        role: userForm.value.role,
        statut: userForm.value.statut,
        kyc_statut: userForm.value.kyc_statut,
      }),
      headers: { 'Content-Type': 'application/json' },
    })

    if (apiError.value) {
      setUserErrors(apiError.value.errors)
      showError(apiError.value.message)
      console.error('Create user error:', apiError.value)
    } else if (data.value) {
      clearUserErrors()
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

  // Show confirm dialog before updating
  const confirmed = await confirmUpdate(t('user'), selectedUser.value.nom_complet)
  if (!confirmed) return

  try {
    loading.value = true

    const payload: any = {
      nom_complet: userForm.value.nom_complet,
      email: userForm.value.email,
      telephone: userForm.value.telephone,
      adresse: userForm.value.adresse,
      cin: userForm.value.cin,
      photo_profil: userForm.value.photo_profil,
      role: userForm.value.role,
      statut: userForm.value.statut,
      kyc_statut: userForm.value.kyc_statut,
    }
    if (userForm.value.password) payload.password = userForm.value.password

    const { data, error: apiError } = await useApi<any>(`/admin/users/${selectedUser.value.id}`, {
      method: 'PUT',
      body: JSON.stringify(payload),
      headers: { 'Content-Type': 'application/json' },
    })

    if (apiError.value) {
      setUserErrors(apiError.value.errors)
      showError(apiError.value.message)
      console.error('Update user error:', apiError.value)
    } else if (data.value) {
      clearUserErrors()
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
    const { data, error: apiError } = await useApi<any>(`/admin/users/${user.id}/toggle-status`, {
      method: 'POST',
    })

    if (apiError.value) {
      showError(apiError.value.message || t('alerts.admin.failed_toggle_user_status'))
      console.error('Toggle status error:', apiError.value)
    } else if (data.value) {
      await fetchUsers(pagination.value.current_page)
      showSuccess(t('user_status_updated_successfully'))
    }
  } catch (err: any) {
    showError(err.message || t('alerts.admin.failed_toggle_user_status'))
    console.error('Toggle status error:', err)
  }
}

const deleteUser = async (user: User) => {
  // Show confirm dialog before deleting
  const confirmed = await confirmDelete(t('user'), user.nom_complet)
  if (!confirmed) return

  try {
    const { data, error: apiError } = await useApi<any>(`/admin/users/${user.id}`, {
      method: 'DELETE',
    })

    if (apiError.value) {
      // Handle foreign key constraint errors specifically
      if (apiError.value.message && (
          apiError.value.message.includes('foreign key constraint') ||
          apiError.value.message.includes('Integrity constraint violation') ||
          apiError.value.message.includes('1451')
        )) {
        showError(
          `Impossible de supprimer l'utilisateur "${user.nom_complet}" car il a des données liées (avis, commandes, etc.). ` +
          `Veuillez d'abord supprimer ou réassigner ses données.`
        )
      } else {
        showError(apiError.value.message || t('failed_to_delete_user'))
      }
      console.error('Delete user error:', apiError.value)
    } else if (data.value) {
      await fetchUsers(pagination.value.current_page)
      showSuccess(t('user_deleted_successfully', { name: user.nom_complet }))
    }
  } catch (err: any) {
    // Handle network/unexpected errors
    if (err.message && (
        err.message.includes('foreign key') ||
        err.message.includes('constraint') ||
        err.message.includes('1451')
      )) {
      showError(
        `Impossible de supprimer l'utilisateur "${user.nom_complet}" en raison de relations de données. ` +
        `Veuillez contacter un administrateur.`
      )
    } else {
      showError(err.message || t('failed_to_delete_user'))
    }
    console.error('Delete user error:', err)
  }
}

// Form helpers
const resetForm = () => {
  userForm.value = {
    nom_complet: '',
    email: '',
    password: '',
    telephone: '',
    adresse: '',
    cin: '',
    photo_profil: '',
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
    telephone: user.telephone || '',
    adresse: user.adresse || '',
    cin: user.cin || '',
    photo_profil: user.photo_profil || '',
    role: user.roles[0] || '',
    statut: user.statut,
    kyc_statut: user.kyc_statut,
  }
  showEditDialog.value = true
}

const openViewDialog = (user: User) => {
  selectedUser.value = user
  showViewDialog.value = true
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

// Watch soft delete filter
watch(
  softDeleteFilter,
  () => {
    fetchUsers(1)
  }
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
          <VCol cols="12" md="3">
            <VTextField
              v-model="filters.search"
              :placeholder="t('search_users')"
              prepend-inner-icon="tabler-search"
              clearable
            />
          </VCol>
          <VCol cols="12" md="2">
            <VSelect
              v-model="filters.role"
              :items="[{ title: t('admin_users_all_roles'), value: '' }, ...roles]"
              :placeholder="t('admin_users_filter_by_role')"
            />
          </VCol>
          <VCol cols="12" md="2">
            <VSelect
              v-model="filters.statut"
              :items="statusOptions"
              :placeholder="t('filter_by_status')"
            />
          </VCol>
          <VCol cols="12" md="2">
            <SoftDeleteFilter
              v-model="softDeleteFilter"
              @update:model-value="fetchUsers(1)"
            />
          </VCol>
          <VCol cols="12" md="3">
            <VBtn block variant="outlined" @click="clearFilters">
              {{ t('clear') }}
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
              <th>{{ t('profile_image') }}</th>
              <th>{{ t('name') }}</th>
              <th>{{ t('email') }}</th>
              <th>{{ t('phone') }}</th>
              <th>{{ t('role') }}</th>
              <th>{{ t('status') }}</th>
              <!-- <th>{{ t('record_status') }}</th> -->
              <th>{{ t('kyc_status') }}</th>
              <th>{{ t('created') }}</th>
              <th>{{ t('actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="user in users" :key="user.id">
              <td>
                <VAvatar size="40">
                  <VImg
                    :src="getAvatarUrl(user.photo_profil)"
                    :alt="user.nom_complet"
                    cover
                  />
                </VAvatar>
              </td>
              <td>{{ user.nom_complet }}</td>
              <td>{{ user.email }}</td>
              <td>{{ user.telephone || '-' }}</td>
              <td>
                <VChip :color="user.roles[0] === 'admin' ? 'error' : 'primary'" size="small">
                  {{ user.roles[0] || t('no_role') }}
                </VChip>
              </td>
              <td>
                <VChip
                  :color="user.statut === 'actif' ? 'success' : user.statut === 'bloque' ? 'error' : 'warning'"
                  size="small"
                >
                  {{ user.statut }}
                </VChip>
              </td>
              <!-- <td>
                <VChip
                  :color="getStatusColor(user)"
                  size="small"
                  variant="elevated"
                >
                  {{ getStatusText(user) }}
                </VChip>
              </td> -->
              <td>
                <VChip
                  :color="user.kyc_statut === 'valide' ? 'success' : user.kyc_statut === 'refuse' ? 'error' : 'warning'"
                  size="small"
                >
                  {{ user.kyc_statut }}
                </VChip>
              </td>
              <td>{{ new Date(user.created_at).toLocaleDateString() }}</td>
              <td>
                <SoftDeleteActions
                  :item="user"
                  entity-name="user"
                  api-endpoint="/admin/users"
                  item-name-field="nom_complet"
                  @deleted="fetchUsers(pagination.current_page)"
                  @restored="fetchUsers(pagination.current_page)"
                  @permanently-deleted="fetchUsers(pagination.current_page)"
                  @edit="openEditDialog"
                  @view="openViewDialog"
                />
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
            <VTextField v-model="userForm.nom_complet" :label="t('full_name')" :placeholder="t('enter_full_name')" :error-messages="userErrors.nom_complet" required class="mb-4" />
            <VTextField v-model="userForm.email" :label="t('email')" :placeholder="t('enter_email')" :error-messages="userErrors.email" type="email" required class="mb-4" />
            <VTextField v-model="userForm.password" :label="t('password')" :placeholder="t('enter_password')" :error-messages="userErrors.password" type="password" required class="mb-4" />
            <VTextField v-model="userForm.telephone" :label="t('phone')" :placeholder="t('enter_phone')" :error-messages="userErrors.telephone" class="mb-4" />
            <VTextarea v-model="userForm.adresse" :label="t('address')" :placeholder="t('enter_address')" :error-messages="userErrors.adresse" rows="3" class="mb-4" />
            <VTextField v-model="userForm.cin" :label="t('cin_number')" :placeholder="t('enter_cin')" :error-messages="userErrors.cin" class="mb-4" />



            <ProfileImageUpload
              v-model="userForm.photo_profil"
              :label="t('profile_image')"
              :error-messages="userErrors.photo_profil"
              class="mb-4"
            />
            <VSelect v-model="userForm.role" :items="roles" :label="t('role')" :placeholder="t('select_role')" :error-messages="userErrors.role" required class="mb-4" />
            <VSelect v-model="userForm.statut" :items="statusOptions.slice(1)" :label="t('status')" :placeholder="t('select_status')" required class="mb-4" />
            <VSelect v-model="userForm.kyc_statut" :items="kycStatusOptions" :label="t('kyc_status')" :placeholder="t('select_kyc_status')" required />
          </VForm>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="outlined" type="button" @click="showCreateDialog = false">{{ t('cancel') }}</VBtn>
          <VBtn color="primary" type="button" :loading="loading" @click="createUser">{{ t('create_user') }}</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Edit User Dialog -->
    <VDialog v-model="showEditDialog" max-width="600">
      <VCard>
        <VCardTitle>{{ t('edit_user') }}</VCardTitle>
        <VCardText>
          <VForm @submit.prevent="updateUser">
            <VTextField v-model="userForm.nom_complet" :label="t('full_name')" :placeholder="t('enter_full_name')" :error-messages="userErrors.nom_complet" required class="mb-4" />
            <VTextField v-model="userForm.email" :label="t('email')" :placeholder="t('enter_email')" :error-messages="userErrors.email" type="email" required class="mb-4" />
            <VTextField v-model="userForm.telephone" :label="t('phone')" :placeholder="t('enter_phone')" :error-messages="userErrors.telephone" class="mb-4" />
            <VTextarea v-model="userForm.adresse" :label="t('address')" :placeholder="t('enter_address')" :error-messages="userErrors.adresse" rows="3" class="mb-4" />
            <VTextField v-model="userForm.cin" :label="t('cin_number')" :placeholder="t('enter_cin')" :error-messages="userErrors.cin" class="mb-4" />



            <ProfileImageUpload
              v-model="userForm.photo_profil"
              :label="t('profile_image')"
              :error-messages="userErrors.photo_profil"
              class="mb-4"
            />
            <VSelect v-model="userForm.role" :items="roles" :label="t('role')" :placeholder="t('select_role')" :error-messages="userErrors.role" required class="mb-4" />
            <VSelect v-model="userForm.statut" :items="statusOptions.slice(1)" :label="t('status')" :placeholder="t('select_status')" required class="mb-4" />
            <VSelect v-model="userForm.kyc_statut" :items="kycStatusOptions" :label="t('kyc_status')" :placeholder="t('select_kyc_status')" required />
          </VForm>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="outlined" type="button" @click="showEditDialog = false">{{ t('cancel') }}</VBtn>
          <VBtn color="primary" type="button" :loading="loading" @click="updateUser">{{ t('update_user') }}</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- View User Dialog -->
    <VDialog v-model="showViewDialog" max-width="600">
      <VCard>
        <VCardTitle class="d-flex align-center">
          <VIcon icon="tabler-eye" class="me-2" />
          {{ t('view_user') }}
        </VCardTitle>
        <VCardText>
          <VRow v-if="selectedUser">
            <!-- Profile Image -->
            <VCol cols="12" class="text-center mb-4">
              <VAvatar size="120">
                <VImg
                  :src="getAvatarUrl(selectedUser.photo_profil)"
                  :alt="selectedUser.nom_complet"
                  cover
                />
              </VAvatar>
            </VCol>

            <!-- User Information -->
            <VCol cols="12" md="6">
              <div class="mb-3">
                <strong>{{ t('full_name') }}:</strong>
                <div>{{ selectedUser.nom_complet }}</div>
              </div>
            </VCol>
            <VCol cols="12" md="6">
              <div class="mb-3">
                <strong>{{ t('email') }}:</strong>
                <div>{{ selectedUser.email }}</div>
              </div>
            </VCol>
            <VCol cols="12" md="6">
              <div class="mb-3">
                <strong>{{ t('phone') }}:</strong>
                <div>{{ selectedUser.telephone || '-' }}</div>
              </div>
            </VCol>
            <VCol cols="12" md="6">
              <div class="mb-3">
                <strong>{{ t('role') }}:</strong>
                <VChip :color="selectedUser.roles[0] === 'admin' ? 'error' : 'primary'" size="small">
                  {{ selectedUser.roles[0] || t('no_role') }}
                </VChip>
              </div>
            </VCol>
            <VCol cols="12">
              <div class="mb-3">
                <strong>{{ t('address') }}:</strong>
                <div>{{ selectedUser.adresse || '-' }}</div>
              </div>
            </VCol>



            <!-- Status Information -->
            <VCol cols="12" md="6">
              <div class="mb-3">
                <strong>{{ t('status') }}:</strong>
                <VChip
                  :color="selectedUser.statut === 'actif' ? 'success' : selectedUser.statut === 'bloque' ? 'error' : 'warning'"
                  size="small"
                >
                  {{ selectedUser.statut }}
                </VChip>
              </div>
            </VCol>
            <VCol cols="12" md="6">
              <div class="mb-3">
                <strong>{{ t('kyc_status') }}:</strong>
                <VChip
                  :color="selectedUser.kyc_statut === 'valide' ? 'success' : selectedUser.kyc_statut === 'refuse' ? 'error' : 'warning'"
                  size="small"
                >
                  {{ selectedUser.kyc_statut }}
                </VChip>
              </div>
            </VCol>
            <VCol cols="12" md="6">
              <div class="mb-3">
                <strong>{{ t('email_verified') }}:</strong>
                <VChip
                  :color="selectedUser.email_verifie ? 'success' : 'warning'"
                  size="small"
                >
                  {{ selectedUser.email_verifie ? t('yes') : t('no') }}
                </VChip>
              </div>
            </VCol>
            <VCol cols="12" md="6">
              <div class="mb-3">
                <strong>{{ t('created') }}:</strong>
                <div>{{ new Date(selectedUser.created_at).toLocaleDateString() }}</div>
              </div>
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="outlined" @click="showViewDialog = false">{{ t('close') }}</VBtn>
          <VBtn color="primary" @click="openEditDialog(selectedUser); showViewDialog = false">{{ t('edit') }}</VBtn>
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

    <!-- Confirm Dialog -->
    <ConfirmActionDialog
      :is-dialog-visible="isConfirmDialogVisible"
      :is-loading="isConfirmLoading"
      :dialog-title="dialogTitle"
      :dialog-text="dialogText"
      :dialog-icon="dialogIcon"
      :dialog-color="dialogColor"
      :confirm-button-text="confirmButtonText"
      :cancel-button-text="cancelButtonText"
      @confirm="handleConfirm"
      @cancel="handleCancel"
    />

  </div>
</template>
