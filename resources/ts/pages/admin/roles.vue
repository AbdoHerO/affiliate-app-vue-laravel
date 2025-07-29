<script setup lang="ts">
import { useAuth } from '@/composables/useAuth'
import { useI18n } from 'vue-i18n'
import { useNotifications } from '@/composables/useNotifications'

definePage({
  meta: {
    requiresAuth: true,
    requiresRole: 'admin',
  },
})

const { hasPermission } = useAuth()
const { t } = useI18n()
const { showSuccess, showError, showConfirm, snackbar, confirmDialog } = useNotifications()

// Mock data
const roles = ref([
  {
    id: '1',
    name: 'admin',
    display_name: 'Administrator',
    description: 'Full system access',
    permissions: ['manage users', 'manage roles', 'manage affiliates', 'manage orders'],
    users_count: 2,
    created_at: '2024-01-01',
  },
  {
    id: '2',
    name: 'affiliate',
    display_name: 'Affiliate',
    description: 'Affiliate user access',
    permissions: ['view orders', 'view commissions'],
    users_count: 15,
    created_at: '2024-01-01',
  },
])

const permissions = ref([
  { id: '1', name: 'manage users', description: 'Can manage users' },
  { id: '2', name: 'manage roles', description: 'Can manage roles and permissions' },
  { id: '3', name: 'manage affiliates', description: 'Can manage affiliates' },
  { id: '4', name: 'manage orders', description: 'Can manage orders' },
  { id: '5', name: 'view orders', description: 'Can view orders' },
  { id: '6', name: 'view commissions', description: 'Can view commissions' },
])

const loading = ref(false)

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

// Mock data is already loaded in refs above

// Create role
const createRole = () => {
  const newRole = {
    id: Date.now().toString(),
    name: roleForm.value.name,
    display_name: roleForm.value.name.charAt(0).toUpperCase() + roleForm.value.name.slice(1),
    description: `${roleForm.value.name} role`,
    permissions: [],
    users_count: 0,
    created_at: new Date().toISOString(),
  }
  roles.value.push(newRole)
  showCreateRoleDialog.value = false
  resetRoleForm()
  showSuccess(t('role_created_successfully', { name: newRole.name }))
}

// Update role
const updateRole = () => {
  if (!selectedRole.value) return
  const index = roles.value.findIndex(r => r.id === selectedRole.value!.id)
  if (index !== -1) {
    roles.value[index] = {
      ...roles.value[index],
      name: roleForm.value.name,
      display_name: roleForm.value.name.charAt(0).toUpperCase() + roleForm.value.name.slice(1),
    }
  }
  showEditRoleDialog.value = false
  resetRoleForm()
  showSuccess(t('role_updated_successfully', { name: roleForm.value.name }))
}

// Delete role
const deleteRole = (role: any) => {
  showConfirm(
    t('confirm_delete'),
    t('confirm_delete_role', { name: role.name }),
    () => {
      const index = roles.value.findIndex(r => r.id === role.id)
      if (index !== -1) {
        roles.value.splice(index, 1)
        showSuccess(t('role_deleted_successfully', { name: role.name }))
      }
    }
  )
}

// Create permission
const createPermission = () => {
  const newPermission = {
    id: Date.now().toString(),
    name: permissionForm.value.name,
    description: `${permissionForm.value.name} permission`,
  }
  permissions.value.push(newPermission)
  showCreatePermissionDialog.value = false
  resetPermissionForm()
  showSuccess(t('permission_created_successfully', { name: newPermission.name }))
}

// Delete permission
const deletePermission = (permission: any) => {
  showConfirm(
    t('confirm_delete'),
    t('confirm_delete_permission', { name: permission.name }),
    () => {
      const index = permissions.value.findIndex(p => p.id === permission.id)
      if (index !== -1) {
        permissions.value.splice(index, 1)
        showSuccess(t('permission_deleted_successfully', { name: permission.name }))
      }
    }
  )
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

// Data is already loaded in refs, no need for onMounted
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
          <VBtn variant="outlined" @click="showCreateRoleDialog = false">Cancel</VBtn>
          <VBtn color="primary" @click="createRole">Create Role</VBtn>
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
          <VBtn variant="outlined" @click="showEditRoleDialog = false">Cancel</VBtn>
          <VBtn color="primary" @click="updateRole">Update Role</VBtn>
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
              required
            />
          </VForm>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="outlined" @click="showCreatePermissionDialog = false">Cancel</VBtn>
          <VBtn color="primary" @click="createPermission">Create Permission</VBtn>
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
