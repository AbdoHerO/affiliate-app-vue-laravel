<script setup lang="ts">
import { useAuth } from '@/composables/useAuth'
import { useApi } from '@/composables/useApi'
import AppTextField from '@core/components/app-form-elements/AppTextField.vue'

definePage({
  meta: {
    requiresAuth: true,
    requiresRole: 'admin',
  },
})

const { hasPermission } = useAuth()
const { api } = useApi()

// Data
const roles = ref([])
const permissions = ref([])
const loading = ref(false)
const error = ref(null)

// Dialog states
const showCreateRoleDialog = ref(false)
const showEditRoleDialog = ref(false)
const showCreatePermissionDialog = ref(false)
const selectedRole = ref(null)

// Form data
const roleForm = ref({
  name: '',
  permissions: [],
})

const permissionForm = ref({
  name: '',
})

// Fetch roles
const fetchRoles = async () => {
  try {
    loading.value = true
    const response = await api.get('/admin/roles')
    roles.value = response.data.roles
  } catch (err) {
    error.value = 'Failed to load roles'
    console.error('Roles fetch error:', err)
  } finally {
    loading.value = false
  }
}

// Fetch permissions
const fetchPermissions = async () => {
  try {
    const response = await api.get('/admin/permissions')
    permissions.value = response.data.permissions
  } catch (err) {
    console.error('Permissions fetch error:', err)
  }
}

// Create role
const createRole = async () => {
  try {
    await api.post('/admin/roles', roleForm.value)
    showCreateRoleDialog.value = false
    resetRoleForm()
    await fetchRoles()
    // Show success message
  } catch (err) {
    console.error('Create role error:', err)
  }
}

// Update role
const updateRole = async () => {
  try {
    await api.put(`/admin/roles/${selectedRole.value.id}`, roleForm.value)
    showEditRoleDialog.value = false
    resetRoleForm()
    await fetchRoles()
    // Show success message
  } catch (err) {
    console.error('Update role error:', err)
  }
}

// Delete role
const deleteRole = async (role) => {
  if (confirm(`Are you sure you want to delete the role "${role.name}"?`)) {
    try {
      await api.delete(`/admin/roles/${role.id}`)
      await fetchRoles()
      // Show success message
    } catch (err) {
      console.error('Delete role error:', err)
    }
  }
}

// Create permission
const createPermission = async () => {
  try {
    await api.post('/admin/permissions', permissionForm.value)
    showCreatePermissionDialog.value = false
    resetPermissionForm()
    await fetchPermissions()
    // Show success message
  } catch (err) {
    console.error('Create permission error:', err)
  }
}

// Delete permission
const deletePermission = async (permission) => {
  if (confirm(`Are you sure you want to delete the permission "${permission.name}"?`)) {
    try {
      await api.delete(`/admin/permissions/${permission.id}`)
      await fetchPermissions()
      // Show success message
    } catch (err) {
      console.error('Delete permission error:', err)
    }
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

const openEditRoleDialog = (role) => {
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
            <AppTextField
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
            <AppTextField
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
            <AppTextField
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
  </div>
</template>
