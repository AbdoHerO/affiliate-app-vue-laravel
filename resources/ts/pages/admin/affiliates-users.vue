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
  () => fetchUsers(pagination.value.current_page)
)

// User interface
interface UserForm {
  nom_complet: string
  email: string
  password: string
  telephone: string
  adresse: string
  photo_profil: string
  statut: 'actif' | 'inactif' | 'bloque'
  email_verifie: boolean
  kyc_statut: 'non_requis' | 'en_attente' | 'valide' | 'refuse'
  rib: string
  bank_type: string
  roles: string[]
  permissions?: string[]
  remember_token?: string
  created_at: string
  updated_at?: string
}

// Reactive data
const showCreateDialog = ref(false)
const showEditDialog = ref(false)
const showViewDialog = ref(false)
const selectedUser = ref<User | null>(null)

const userForm = ref({
  nom_complet: '',
  email: '',
  password: '',
  telephone: '',
  adresse: '',
  cin: '',
  photo_profil: '',
  role: 'affiliate', // Default to affiliate role
  statut: 'actif' as User['statut'],
  kyc_statut: 'non_requis' as User['kyc_statut'],
  rib: '',
  bank_type: '',
})

// Form errors handling
const { errors: userErrors, set: setUserErrors, clear: clearUserErrors } = useFormErrors<typeof userForm.value>()

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

// Watch for soft delete filter changes
watch(softDeleteFilter, () => {
  fetchUsers(1)
})

// Watch for filter changes
watch(filters, () => {
  fetchUsers(1)
}, { deep: true })

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

    // Only show affiliate users in this view
    params.set('role', 'affiliate')

    const url = `/admin/users?${params.toString()}`
    const { data, error: apiError } = await useApi<any>(url)

    if (apiError.value) {
      const errorMessage = apiError.value.message || t('failed_to_load_users')
      error.value = errorMessage
      showError(errorMessage)
      console.error('Users fetch error:', apiError.value)
    } else if (data.value) {
      // Handle both data.users and data.data formats
      const usersData = data.value.data || data.value.users || []
      const paginationData = data.value.pagination || data.value

      users.value = usersData.map((user: any) => ({
        id: user.id,
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

      pagination.value = {
        current_page: paginationData.current_page || 1,
        last_page: paginationData.last_page || 1,
        per_page: paginationData.per_page || 15,
        total: paginationData.total || 0,
      }

      console.log('✅ Affiliate users loaded successfully:', users.value.length)
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
  // Show confirm dialog before creating
  const confirmed = await confirmCreate(t('affiliate_user'))
  if (!confirmed) return

  try {
    loading.value = true
    clearUserErrors()

    const { data, error: apiError } = await useApi<any>('/admin/users', {
      method: 'POST',
      body: JSON.stringify({
        nom_complet: userForm.value.nom_complet,
        email: userForm.value.email,
        password: userForm.value.password,
        telephone: userForm.value.telephone,
        adresse: userForm.value.adresse,
        cin: userForm.value.cin,
        photo_profil: userForm.value.photo_profil,
        role: userForm.value.role,
        statut: userForm.value.statut,
        kyc_statut: userForm.value.kyc_statut,
        rib: userForm.value.rib,
        bank_type: userForm.value.bank_type,
      }),
      headers: { 'Content-Type': 'application/json' },
    })

    if (apiError.value) {
      if (apiError.value.errors) {
        setUserErrors(apiError.value.errors)
      } else {
        const errorMessage = apiError.value.message || t('failed_to_create_user')
        showError(errorMessage)
      }
    } else {
      showSuccess(t('user_created_successfully'))
      showCreateDialog.value = false
      resetForm()
      await fetchUsers(pagination.value.current_page)
    }
  } catch (err: any) {
    showError(err.message || t('failed_to_create_user'))
  } finally {
    loading.value = false
  }
}

const updateUser = async () => {
  if (!selectedUser.value) return

  // Show confirm dialog before updating
  const confirmed = await confirmUpdate(t('affiliate_user'))
  if (!confirmed) return

  try {
    loading.value = true
    clearUserErrors()

    const payload = {
      nom_complet: userForm.value.nom_complet,
      email: userForm.value.email,
      telephone: userForm.value.telephone,
      adresse: userForm.value.adresse,
      cin: userForm.value.cin,
      photo_profil: userForm.value.photo_profil,
      role: userForm.value.role,
      statut: userForm.value.statut,
      kyc_statut: userForm.value.kyc_statut,
      rib: userForm.value.rib,
      bank_type: userForm.value.bank_type,
    }
    if ((userForm.value as any).password) (payload as any).password = (userForm.value as any).password

    const { data, error: apiError } = await useApi<any>(`/admin/users/${selectedUser.value.id}`, {
      method: 'PUT',
      body: JSON.stringify(payload),
      headers: { 'Content-Type': 'application/json' },
    })

    if (apiError.value) {
      if (apiError.value.errors) {
        setUserErrors(apiError.value.errors)
      } else {
        const errorMessage = apiError.value.message || t('failed_to_update_user')
        showError(errorMessage)
      }
    } else {
      showSuccess(t('user_updated_successfully'))
      showEditDialog.value = false
      resetForm()
      await fetchUsers(pagination.value.current_page)
    }
  } catch (err: any) {
    showError(err.message || t('failed_to_update_user'))
  } finally {
    loading.value = false
  }
}

// Helper functions
const resetForm = () => {
  userForm.value = {
    nom_complet: '',
    email: '',
    password: '',
    telephone: '',
    adresse: '',
    cin: '',
    photo_profil: '',
    role: 'affiliate', // Default to affiliate role
    statut: 'actif',
    kyc_statut: 'non_requis',
    rib: '',
    bank_type: '',
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
    role: user.roles[0] || 'affiliate',
    statut: user.statut,
    kyc_statut: user.kyc_statut,
    rib: user.rib || '',
    bank_type: user.bank_type || '',
  }
  showEditDialog.value = true
}

const openViewDialog = (user: User) => {
  selectedUser.value = user
  showViewDialog.value = true
}

const clearFilters = () => {
  filters.value = { search: '', statut: '' }
  fetchUsers(1)
}

// Load data on mount
onMounted(async () => {
  await Promise.all([fetchUsers(), fetchRoles()])
})
</script>

<template>
  <div>
    <!-- Page Header -->
    <div class="d-flex justify-space-between align-center mb-6">
      <div>
        <h1 class="text-h4 font-weight-bold mb-1">{{ t('affiliate_users_management') }}</h1>
        <p class="text-body-1 mb-0">{{ t('manage_affiliate_users_description') }}</p>
      </div>
      <VBtn
        color="primary"
        prepend-icon="tabler-plus"
        @click="showCreateDialog = true"
      >
        {{ t('add_affiliate_user') }}
      </VBtn>
    </div>

    <!-- Soft Delete Filter -->
    <SoftDeleteFilter v-model="softDeleteFilter" class="mb-6" />

    <!-- Filters Card -->
    <VCard class="mb-6">
      <VCardText>
        <VRow>
          <VCol cols="12" md="6">
            <VTextField
              v-model="filters.search"
              :label="t('search')"
              :placeholder="t('search_users')"
              prepend-inner-icon="tabler-search"
              clearable
            />
          </VCol>
          <VCol cols="12" md="4">
            <VSelect
              v-model="filters.statut"
              :label="t('status')"
              :placeholder="t('all_status')"
              :items="statusOptions"
              clearable
            />
          </VCol>
          <VCol cols="12" md="2">
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
              <th>{{ t('bank_type') }}</th>
              <th>{{ t('rib') }}</th>
              <th>{{ t('role') }}</th>
              <th>{{ t('status') }}</th>
              <th>{{ t('record_status') }}</th>
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
                <div class="d-flex align-center">
                  <VIcon icon="tabler-building-bank" size="16" class="me-2" />
                  {{ user.bank_type ? user.bank_type : t('bank_not_provided') }}
                </div>
              </td>
              <td>
                <div style="font-family: monospace; font-size: 0.875rem;">
                  {{ user.rib ? user.rib : t('bank_not_provided') }}
                </div>
              </td>
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
              <td>
                <VChip
                  :color="getStatusColor(user)"
                  size="small"
                  variant="elevated"
                >
                  {{ getStatusText(user) }}
                </VChip>
              </td>
              <td>
                <VChip
                  :color="user.kyc_statut === 'valide' ? 'success' : user.kyc_statut === 'refuse' ? 'error' : 'warning'"
                  size="small"
                >
                  {{ user.kyc_statut }}
                </VChip>
              </td>
              <td>{{ user.created_at ? new Date(user.created_at).toLocaleDateString() : t('not_available') }}</td>
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
          <h3 class="text-h6 mb-2">{{ t('no_affiliate_users_found') }}</h3>
          <p class="text-body-2 mb-4">{{ t('no_affiliate_users_description') }}</p>
          <VBtn color="primary" @click="showCreateDialog = true">
            {{ t('add_first_affiliate_user') }}
          </VBtn>
        </div>

        <!-- Error State -->
        <div v-if="error" class="text-center py-8">
          <VIcon icon="tabler-alert-circle" size="64" class="mb-4" color="error" />
          <h3 class="text-h6 mb-2">{{ t('error_loading_users') }}</h3>
          <p class="text-body-2 mb-4">{{ error }}</p>
          <VBtn color="primary" @click="fetchUsers(1)">
            {{ t('retry') }}
          </VBtn>
        </div>
      </VCardText>

      <!-- Pagination -->
      <VCardActions v-if="!loading && users.length && pagination.last_page > 1">
        <VSpacer />
        <VPagination
          v-model="pagination.current_page"
          :length="pagination.last_page"
          :total-visible="7"
          @update:model-value="fetchUsers"
        />
        <VSpacer />
      </VCardActions>
    </VCard>

    <!-- Create User Dialog -->
    <VDialog v-model="showCreateDialog" max-width="600">
      <VCard>
        <VCardTitle class="d-flex align-center">
          <VIcon icon="tabler-user-plus" class="me-2" />
          {{ t('add_affiliate_user') }}
        </VCardTitle>
        <VCardText>
          <VForm @submit.prevent="createUser">
            <VTextField v-model="userForm.nom_complet" :label="t('full_name')" :placeholder="t('enter_full_name')" :error-messages="userErrors.nom_complet" required class="mb-4" />
            <VTextField v-model="userForm.email" :label="t('email')" :placeholder="t('enter_email')" :error-messages="userErrors.email" type="email" required class="mb-4" />
            <VTextField v-model="userForm.password" :label="t('password')" :placeholder="t('enter_password')" :error-messages="userErrors.password" type="password" required class="mb-4" />
            <VTextField v-model="userForm.telephone" :label="t('phone')" :placeholder="t('enter_phone')" :error-messages="userErrors.telephone" class="mb-4" />
            <VTextarea v-model="userForm.adresse" :label="t('address')" :placeholder="t('enter_address')" :error-messages="userErrors.adresse" rows="3" class="mb-4" />
            <VTextField v-model="userForm.cin" :label="t('cin_number')" :placeholder="t('enter_cin')" :error-messages="userErrors.cin" class="mb-4" />

            <!-- Bank Information -->
            <VRow class="mb-4">
              <VCol cols="12" md="6">
                <VSelect
                  v-model="userForm.bank_type"
                  :label="t('bank_type')"
                  :placeholder="t('select_bank')"
                  :error-messages="userErrors.bank_type"
                  :items="[
                    { value: 'attijari', text: t('banks.attijariwafa') },
                    { value: 'populaire', text: t('banks.banque_populaire') },
                    { value: 'bmce', text: t('banks.bmce_bank') },
                    { value: 'bmci', text: t('banks.bmci') },
                    { value: 'cih', text: t('banks.cih_bank') },
                    { value: 'credit_agricole', text: t('banks.credit_agricole') },
                    { value: 'credit_maroc', text: t('banks.credit_du_maroc') },
                    { value: 'societe_generale', text: t('banks.societe_generale') },
                    { value: 'autre', text: t('other') }
                  ]"
                  item-title="text"
                  item-value="value"
                  clearable
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="userForm.rib"
                  :label="t('rib')"
                  :placeholder="t('enter_rib')"
                  :error-messages="userErrors.rib"
                  style="font-family: monospace;"
                />
              </VCol>
            </VRow>

            <ProfileImageUpload
              v-model="userForm.photo_profil"
              :label="t('profile_image')"
              :error-messages="userErrors.photo_profil"
              class="mb-4"
            />
            <!-- Role is hidden and set to 'affiliate' by default -->
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
        <VCardTitle class="d-flex align-center">
          <VIcon icon="tabler-edit" class="me-2" />
          {{ t('edit_affiliate_user') }}
        </VCardTitle>
        <VCardText>
          <VForm @submit.prevent="updateUser">
            <VTextField v-model="userForm.nom_complet" :label="t('full_name')" :placeholder="t('enter_full_name')" :error-messages="userErrors.nom_complet" required class="mb-4" />
            <VTextField v-model="userForm.email" :label="t('email')" :placeholder="t('enter_email')" :error-messages="userErrors.email" type="email" required class="mb-4" />
            <VTextField v-model="userForm.password" :label="t('password')" :placeholder="t('leave_empty_to_keep_current')" :error-messages="userErrors.password" type="password" class="mb-4" />
            <VTextField v-model="userForm.telephone" :label="t('phone')" :placeholder="t('enter_phone')" :error-messages="userErrors.telephone" class="mb-4" />
            <VTextarea v-model="userForm.adresse" :label="t('address')" :placeholder="t('enter_address')" :error-messages="userErrors.adresse" rows="3" class="mb-4" />
            <VTextField v-model="userForm.cin" :label="t('cin_number')" :placeholder="t('enter_cin')" :error-messages="userErrors.cin" class="mb-4" />

            <!-- Bank Information -->
            <VRow class="mb-4">
              <VCol cols="12" md="6">
                <VSelect
                  v-model="userForm.bank_type"
                  :label="t('bank_type')"
                  :placeholder="t('select_bank')"
                  :error-messages="userErrors.bank_type"
                  :items="[
                    { value: 'attijari', text: t('banks.attijariwafa') },
                    { value: 'populaire', text: t('banks.banque_populaire') },
                    { value: 'bmce', text: t('banks.bmce_bank') },
                    { value: 'bmci', text: t('banks.bmci') },
                    { value: 'cih', text: t('banks.cih_bank') },
                    { value: 'credit_agricole', text: t('banks.credit_agricole') },
                    { value: 'credit_maroc', text: t('banks.credit_du_maroc') },
                    { value: 'societe_generale', text: t('banks.societe_generale') },
                    { value: 'autre', text: t('other') }
                  ]"
                  item-title="text"
                  item-value="value"
                  clearable
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="userForm.rib"
                  :label="t('rib')"
                  :placeholder="t('enter_rib')"
                  :error-messages="userErrors.rib"
                  style="font-family: monospace;"
                />
              </VCol>
            </VRow>

            <ProfileImageUpload
              v-model="userForm.photo_profil"
              :label="t('profile_image')"
              :error-messages="userErrors.photo_profil"
              class="mb-4"
            />
            <!-- Role is hidden and set to 'affiliate' by default -->
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
          {{ t('view_affiliate_user') }}
        </VCardTitle>
        <VCardText>
          <VRow v-if="selectedUser">
            <!-- Profile Image -->
            <VCol cols="12" class="text-center mb-4">
              <VAvatar size="80">
                <VImg
                  :src="getAvatarUrl(selectedUser.photo_profil)"
                  :alt="selectedUser.nom_complet"
                  cover
                />
              </VAvatar>
            </VCol>

            <!-- Basic Information -->
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
                <strong>{{ t('address') }}:</strong>
                <div>{{ selectedUser.adresse || '-' }}</div>
              </div>
            </VCol>

            <!-- Bank Information -->
            <VCol cols="12" md="6">
              <div class="mb-3">
                <strong>{{ t('bank_type') }}:</strong>
                <div class="d-flex align-center">
                  <VIcon icon="tabler-building-bank" size="16" class="me-2" />
                  {{ selectedUser.bank_type || t('bank_not_provided') }}
                </div>
              </div>
            </VCol>
            <VCol cols="12" md="6">
              <div class="mb-3">
                <strong>{{ t('rib') }}:</strong>
                <div style="font-family: monospace;">{{ selectedUser.rib || t('bank_not_provided') }}</div>
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
                <div>{{ selectedUser.created_at ? new Date(selectedUser.created_at).toLocaleDateString() : t('not_available') }}</div>
              </div>
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="outlined" @click="showViewDialog = false">{{ t('close') }}</VBtn>
          <VBtn color="primary" @click="selectedUser && openEditDialog(selectedUser); showViewDialog = false">{{ t('edit') }}</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Confirm Action Dialog -->
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
