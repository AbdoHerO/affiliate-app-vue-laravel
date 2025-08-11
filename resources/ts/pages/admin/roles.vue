<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useAuth } from '@/composables/useAuth'
import { useI18n } from 'vue-i18n'
import { useNotifications } from '@/composables/useNotifications'
import { useQuickConfirm } from '@/composables/useConfirmAction'
import { useApi } from '@/composables/useApi'
import { useFormErrors } from '@/composables/useFormErrors'

definePage({
  meta: {
    requiresAuth: true,
    requiresRole: 'admin',
  },
})

const { hasPermission } = useAuth()
const { t } = useI18n()
const { showSuccess, showError, snackbar } = useNotifications()
const { confirmCreate, confirmUpdate, confirmDelete } = useQuickConfirm()

// Data
const roles = ref<any[]>([])
const permissions = ref<any[]>([])
const loading = ref(false)
const error = ref<string | null>(null)

// Dialog states
const showCreateRoleDialog = ref(false)
const showEditRoleDialog = ref(false)
const showCreatePermissionDialog = ref(false)
const selectedRole = ref<any>(null)

// Form data
const roleForm = ref({
  name: '',
  permissions: [],
})

const permissionForm = ref({
  name: '',
})

// Form errors handling
const { errors: roleErrors, set: setRoleErrors, clear: clearRoleErrors } = useFormErrors<typeof roleForm.value>()
const { errors: permissionErrors, set: setPermissionErrors, clear: clearPermissionErrors } = useFormErrors<typeof permissionForm.value>()

// API Functions
const fetchRoles = async () => {
  try {
    loading.value = true
    error.value = null

    const { data, error: apiError } = await useApi<any>('/admin/roles')

    if (apiError.value) {
      const errorMessage = apiError.value.message || 'Failed to load roles'
      error.value = errorMessage
      showError(errorMessage)
      console.error('Roles fetch error:', apiError.value)
    } else if (data.value) {
      // Handle both data.roles and data.data formats
      roles.value = data.value.data || data.value.roles || []
      console.log('✅ Roles loaded successfully:', roles.value.length)
    }
  } catch (err: any) {
    error.value = err.message || 'Failed to load roles'
    showError(t('failed_to_load_roles'))
    console.error('Roles fetch error:', err)
  } finally {
    loading.value = false
  }
}

const fetchPermissions = async () => {
  try {
    const { data, error: apiError } = await useApi<any>('/admin/permissions')

    if (apiError.value) {
      const errorMessage = apiError.value.message || 'Failed to load permissions'
      showError(errorMessage)
      console.error('Permissions fetch error:', apiError.value)
    } else if (data.value) {
      // Handle both data.permissions and data.data formats
      permissions.value = data.value.data || data.value.permissions || []
      console.log('✅ Permissions loaded successfully:', permissions.value.length)
    }
  } catch (err: any) {
    const errorMessage = err.message || 'Failed to load permissions'
    showError(errorMessage)
    console.error('Permissions fetch error:', err)
  }
}

const createRole = async () => {
  // Show confirm dialog before creating
  const confirmed = await confirmCreate(t('role'))
  if (!confirmed) return

  try {
    loading.value = true

    const { data, error: apiError } = await useApi<any>('/admin/roles', {
      method: 'POST',
      body: JSON.stringify({
        name: roleForm.value.name,
        permissions: roleForm.value.permissions,
      }),
      headers: {
        'Content-Type': 'application/json',
      },
    })

    if (apiError.value) {
      setRoleErrors(apiError.value.errors)
      showError(apiError.value.message)
      console.error('Create role error:', apiError.value)
    } else if (data.value) {
      clearRoleErrors()
      showCreateRoleDialog.value = false
      resetRoleForm()
      await fetchRoles()
      showSuccess(t('role_created_successfully', { name: roleForm.value.name }))
    }
  } catch (err: any) {
    showError(err.message || t('failed_to_create_role'))
    console.error('Create role error:', err)
  } finally {
    loading.value = false
  }
}

// Update role
const updateRole = async () => {
  if (!selectedRole.value) return

  // Show confirm dialog before updating
  const confirmed = await confirmUpdate(t('role'), selectedRole.value.name)
  if (!confirmed) return

  try {
    loading.value = true

    const { data, error: apiError } = await useApi<any>(`/admin/roles/${selectedRole.value.id}`, {
      method: 'PUT',
      body: JSON.stringify({
        name: roleForm.value.name,
        permissions: roleForm.value.permissions,
      }),
      headers: {
        'Content-Type': 'application/json',
      },
    })

    if (apiError.value) {
      setRoleErrors(apiError.value.errors)
      showError(apiError.value.message)
      console.error('Update role error:', apiError.value)
    } else if (data.value) {
      clearRoleErrors()
      showEditRoleDialog.value = false
      resetRoleForm()
      await fetchRoles()
      showSuccess(t('role_updated_successfully', { name: roleForm.value.name }))
    }
  } catch (err: any) {
    showError(err.message || t('failed_to_update_role'))
    console.error('Update role error:', err)
  } finally {
    loading.value = false
  }
}

// Delete role
const deleteRole = async (role: any) => {
  // Show confirm dialog before deleting
  const confirmed = await confirmDelete(t('role'), role.name)
  if (!confirmed) return

  try {
    loading.value = true

    const { data, error: apiError } = await useApi<any>(`/admin/roles/${role.id}`, {
      method: 'DELETE',
    })

    if (apiError.value) {
      let errorMessage = apiError.value.message || t('failed_to_delete_role')
      showError(errorMessage)
      console.error('Delete role error:', apiError.value)
    } else if (data.value) {
      await fetchRoles()
      showSuccess(t('role_deleted_successfully', { name: role.name }))
    }
  } catch (err: any) {
    showError(err.message || t('failed_to_delete_role'))
    console.error('Delete role error:', err)
  } finally {
    loading.value = false
  }
}

// Create permission
const createPermission = async () => {
  // Show confirm dialog before creating
  const confirmed = await confirmCreate(t('permission'))
  if (!confirmed) return

  try {
    loading.value = true

    const { data, error: apiError } = await useApi<any>('/admin/permissions', {
      method: 'POST',
      body: JSON.stringify({
        name: permissionForm.value.name,
      }),
      headers: {
        'Content-Type': 'application/json',
      },
    })

    if (apiError.value) {
      setPermissionErrors(apiError.value.errors)
      showError(apiError.value.message)
      console.error('Create permission error:', apiError.value)
    } else if (data.value) {
      clearPermissionErrors()
      showCreatePermissionDialog.value = false
      resetPermissionForm()
      await fetchPermissions()
      showSuccess(t('permission_created_successfully', { name: permissionForm.value.name }))
    }
  } catch (err: any) {
    showError(err.message || t('failed_to_create_permission'))
    console.error('Create permission error:', err)
  } finally {
    loading.value = false
  }
}

// Delete permission
const deletePermission = async (permission: any) => {
  // Show confirm dialog before deleting
  const confirmed = await confirmDelete(t('permission'), permission.name)
  if (!confirmed) return

  try {
    loading.value = true

    const { data, error: apiError } = await useApi<any>(`/admin/permissions/${permission.id}`, {
      method: 'DELETE',
    })

    if (apiError.value) {
      let errorMessage = apiError.value.message || t('failed_to_delete_permission')
      showError(errorMessage)
      console.error('Delete permission error:', apiError.value)
    } else if (data.value) {
      await fetchPermissions()
      showSuccess(t('permission_deleted_successfully', { name: permission.name }))
    }
  } catch (err: any) {
    showError(err.message || t('failed_to_delete_permission'))
    console.error('Delete permission error:', err)
  } finally {
    loading.value = false
  }
}

// Form helpers
const resetRoleForm = () => {
  roleForm.value = {
    name: '',
    permissions: [],
  }
  selectedRole.value = null
}

const resetPermissionForm = () => {
  permissionForm.value = {
    name: '',
  }
}

const openEditRoleDialog = (role: any) => {
  selectedRole.value = role
  roleForm.value = {
    name: role.name,
    permissions: role.permissions || [],
  }
  showEditRoleDialog.value = true
}

// Load data on mount
onMounted(async () => {
  await Promise.all([
    fetchRoles(),
    fetchPermissions()
  ])
})
</script>

<template>
  <div>
    <!-- Header -->
    <VCard class="mb-6">
      <VCardText>
        <div class="d-flex justify-space-between align-center">
          <div>
            <h2 class="text-h4 mb-2">Roles & Permissions</h2>
            <p class="text-body-1 mb-0">Manage system roles and permissions</p>
          </div>
          <div class="d-flex gap-2">
            <VBtn
              color="secondary"
              prepend-icon="tabler-plus"
              variant="outlined"
              :disabled="!hasPermission('manage users')"
              @click="showCreatePermissionDialog = true"
            >
              Add Permission
            </VBtn>
            <VBtn
              color="primary"
              prepend-icon="tabler-plus"
              :disabled="!hasPermission('manage users')"
              @click="showCreateRoleDialog = true"
            >
              Add Role
            </VBtn>
          </div>
        </div>
      </VCardText>
    </VCard>

    <VRow>
      <!-- Roles Section -->
      <VCol cols="12" md="8">
        <VCard>
          <VCardText>
            <h5 class="text-h5 mb-4">Roles</h5>
            
            <!-- Loading State -->
            <div v-if="loading" class="text-center py-8">
              <VProgressCircular indeterminate color="primary" />
              <p class="mt-4">Loading roles...</p>
            </div>

            <!-- Roles List -->
            <div v-else-if="roles.length">
              <VRow>
                <VCol
                  v-for="role in roles"
                  :key="role.id"
                  cols="12"
                  md="6"
                >
                  <VCard variant="outlined">
                    <VCardText>
                      <div class="d-flex justify-space-between align-start mb-3">
                        <div>
                          <h6 class="text-h6 mb-1">{{ role.name }}</h6>
                          <p class="text-body-2 mb-0">{{ role.users_count }} users</p>
                        </div>
                        <div class="d-flex gap-1">
                          <VBtn
                            icon
                            size="small"
                            color="primary"
                            variant="text"
                            @click="openEditRoleDialog(role)"
                          >
                            <VIcon icon="tabler-edit" />
                          </VBtn>
                          <VBtn
                            v-if="!['admin', 'affiliate'].includes(role.name)"
                            icon
                            size="small"
                            color="error"
                            variant="text"
                            @click="deleteRole(role)"
                          >
                            <VIcon icon="tabler-trash" />
                          </VBtn>
                        </div>
                      </div>
                      
                      <div class="d-flex flex-wrap gap-1">
                        <VChip
                          v-for="permission in role.permissions"
                          :key="permission"
                          size="small"
                          color="primary"
                          variant="tonal"
                        >
                          {{ permission }}
                        </VChip>
                      </div>
                    </VCardText>
                  </VCard>
                </VCol>
              </VRow>
            </div>

            <!-- Empty State -->
            <div v-else class="text-center py-8">
              <VIcon icon="tabler-shield" size="64" class="mb-4" color="disabled" />
              <h6 class="text-h6 mb-2">No roles found</h6>
              <p class="text-body-2">Create your first role to get started</p>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Permissions Section -->
      <VCol cols="12" md="4">
        <VCard>
          <VCardText>
            <h5 class="text-h5 mb-4">Permissions</h5>
            
            <div v-if="permissions.length">
              <div
                v-for="permission in permissions"
                :key="permission.id"
                class="d-flex justify-space-between align-center mb-2 pa-2 rounded"
                style="border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity))"
              >
                <span class="text-body-2">{{ permission.name }}</span>
                <VBtn
                  icon
                  size="small"
                  color="error"
                  variant="text"
                  @click="deletePermission(permission)"
                >
                  <VIcon icon="tabler-trash" size="16" />
                </VBtn>
              </div>
            </div>
            
            <div v-else class="text-center py-4">
              <p class="text-body-2">No permissions found</p>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Create Role Dialog -->
    <VDialog v-model="showCreateRoleDialog" max-width="600">
      <VCard>
        <VCardTitle>Create New Role</VCardTitle>
        <VCardText>
          <VForm @submit.prevent="createRole">
            <VTextField
              v-model="roleForm.name"
              label="Role Name"
              placeholder="Enter role name"
              :error-messages="roleErrors.name"
              required
              class="mb-4"
            />
            
            <h6 class="text-h6 mb-3">Permissions</h6>
            <div class="d-flex flex-wrap gap-2">
              <VCheckbox
                v-for="permission in permissions"
                :key="permission.id"
                v-model="roleForm.permissions"
                :value="permission.name"
                :label="permission.name"
                density="compact"
              />
            </div>
          </VForm>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="outlined" type="button" @click="showCreateRoleDialog = false">Cancel</VBtn>
          <VBtn color="primary" type="button" :loading="loading" @click="createRole">Create Role</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Edit Role Dialog -->
    <VDialog v-model="showEditRoleDialog" max-width="600">
      <VCard>
        <VCardTitle>Edit Role</VCardTitle>
        <VCardText>
          <VForm @submit.prevent="updateRole">
            <VTextField
              v-model="roleForm.name"
              label="Role Name"
              placeholder="Enter role name"
              :error-messages="roleErrors.name"
              required
              class="mb-4"
            />
            
            <h6 class="text-h6 mb-3">Permissions</h6>
            <div class="d-flex flex-wrap gap-2">
              <VCheckbox
                v-for="permission in permissions"
                :key="permission.id"
                v-model="roleForm.permissions"
                :value="permission.name"
                :label="permission.name"
                density="compact"
              />
            </div>
          </VForm>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="outlined" type="button" @click="showEditRoleDialog = false">Cancel</VBtn>
          <VBtn color="primary" type="button" :loading="loading" @click="updateRole">Update Role</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Create Permission Dialog -->
    <VDialog v-model="showCreatePermissionDialog" max-width="400">
      <VCard>
        <VCardTitle>Create New Permission</VCardTitle>
        <VCardText>
          <VForm @submit.prevent="createPermission">
            <VTextField
              v-model="permissionForm.name"
              label="Permission Name"
              placeholder="Enter permission name"
              :error-messages="permissionErrors.name"
              required
            />
          </VForm>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="outlined" type="button" @click="showCreatePermissionDialog = false">Cancel</VBtn>
          <VBtn color="primary" type="button" :loading="loading" @click="createPermission">Create Permission</VBtn>
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


  </div>
</template>
