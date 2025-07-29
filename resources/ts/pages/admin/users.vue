<script setup lang="ts">
import { useAuth } from '@/composables/useAuth'
import { useApi } from '@/composables/useApi'
import AppTextField from '@core/components/app-form-elements/AppTextField.vue'
import AppSelect from '@core/components/app-form-elements/AppSelect.vue'
import TablePagination from '@core/components/TablePagination.vue'

definePage({
  meta: {
    requiresAuth: true,
    requiresRole: 'admin',
  },
})

const { hasPermission } = useAuth()
const { api } = useApi()

// Data
const users = ref([])
const loading = ref(false)
const error = ref(null)
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

const roles = ref([])
const statusOptions = [
  { title: 'All Status', value: '' },
  { title: 'Active', value: 'actif' },
  { title: 'Inactive', value: 'inactif' },
  { title: 'Suspended', value: 'suspendu' },
]

// Dialog states
const showCreateDialog = ref(false)
const showEditDialog = ref(false)
const selectedUser = ref(null)

// Form data
const userForm = ref({
  nom_complet: '',
  email: '',
  password: '',
  role: '',
  statut: 'actif',
  kyc_statut: 'non_requis',
})

// Fetch users
const fetchUsers = async (page = 1) => {
  try {
    loading.value = true
    const params = {
      page,
      per_page: pagination.value.per_page,
      ...filters.value,
    }
    
    const response = await api.get('/admin/users', { params })
    users.value = response.data.users
    pagination.value = response.data.pagination
  } catch (err) {
    error.value = 'Failed to load users'
    console.error('Users fetch error:', err)
  } finally {
    loading.value = false
  }
}

// Fetch roles
const fetchRoles = async () => {
  try {
    const response = await api.get('/admin/users/roles/list')
    roles.value = response.data.roles.map(role => ({
      title: role.name.charAt(0).toUpperCase() + role.name.slice(1),
      value: role.name,
    }))
  } catch (err) {
    console.error('Roles fetch error:', err)
  }
}

// Create user
const createUser = async () => {
  try {
    await api.post('/admin/users', userForm.value)
    showCreateDialog.value = false
    resetForm()
    await fetchUsers()
    // Show success message
  } catch (err) {
    console.error('Create user error:', err)
  }
}

// Update user
const updateUser = async () => {
  try {
    await api.put(`/admin/users/${selectedUser.value.id}`, userForm.value)
    showEditDialog.value = false
    resetForm()
    await fetchUsers()
    // Show success message
  } catch (err) {
    console.error('Update user error:', err)
  }
}

// Toggle user status
const toggleUserStatus = async (user) => {
  try {
    await api.post(`/admin/users/${user.id}/toggle-status`)
    await fetchUsers()
    // Show success message
  } catch (err) {
    console.error('Toggle status error:', err)
  }
}

// Delete user
const deleteUser = async (user) => {
  if (confirm(`Are you sure you want to delete ${user.nom_complet}?`)) {
    try {
      await api.delete(`/admin/users/${user.id}`)
      await fetchUsers()
      // Show success message
    } catch (err) {
      console.error('Delete user error:', err)
    }
  }
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

const openEditDialog = (user) => {
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

// Search and filter
const applyFilters = () => {
  fetchUsers(1)
}

const clearFilters = () => {
  filters.value = {
    search: '',
    role: '',
    statut: '',
  }
  fetchUsers(1)
}

// Load data on mount
onMounted(async () => {
  await Promise.all([
    fetchUsers(),
    fetchRoles()
  ])
})

// Watch filters
watch(filters, () => {
  applyFilters()
}, { deep: true })
</script>

<template>
  <div>
    <!-- Header -->
    <VCard class="mb-6">
      <VCardText>
        <div class="d-flex justify-space-between align-center">
          <div>
            <h2 class="text-h4 mb-2">User Management</h2>
            <p class="text-body-1 mb-0">Manage all users in the system</p>
          </div>
          <VBtn
            color="primary"
            prepend-icon="tabler-plus"
            :disabled="!hasPermission('manage users')"
            @click="showCreateDialog = true"
          >
            Add User
          </VBtn>
        </div>
      </VCardText>
    </VCard>

    <!-- Filters -->
    <VCard class="mb-6">
      <VCardText>
        <VRow>
          <VCol cols="12" md="4">
            <AppTextField
              v-model="filters.search"
              placeholder="Search users..."
              prepend-inner-icon="tabler-search"
              clearable
            />
          </VCol>
          <VCol cols="12" md="3">
            <AppSelect
              v-model="filters.role"
              :items="[{ title: 'All Roles', value: '' }, ...roles]"
              placeholder="Filter by role"
            />
          </VCol>
          <VCol cols="12" md="3">
            <AppSelect
              v-model="filters.statut"
              :items="statusOptions"
              placeholder="Filter by status"
            />
          </VCol>
          <VCol cols="12" md="2">
            <VBtn
              block
              variant="outlined"
              @click="clearFilters"
            >
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
              <th>Name</th>
              <th>Email</th>
              <th>Role</th>
              <th>Status</th>
              <th>KYC Status</th>
              <th>Created</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="user in users" :key="user.id">
              <td>{{ user.nom_complet }}</td>
              <td>{{ user.email }}</td>
              <td>
                <VChip
                  :color="user.roles[0] === 'admin' ? 'error' : 'primary'"
                  size="small"
                >
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
                  <VBtn
                    icon
                    size="small"
                    color="primary"
                    variant="text"
                    @click="openEditDialog(user)"
                  >
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
                  <VBtn
                    icon
                    size="small"
                    color="error"
                    variant="text"
                    @click="deleteUser(user)"
                  >
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
          <p class="mt-4">Loading users...</p>
        </div>

        <!-- Empty State -->
        <div v-else class="text-center py-8">
          <VIcon icon="tabler-users" size="64" class="mb-4" color="disabled" />
          <h6 class="text-h6 mb-2">No users found</h6>
          <p class="text-body-2">Try adjusting your search criteria</p>
        </div>

        <!-- Pagination -->
        <TablePagination
          v-if="pagination.total > pagination.per_page"
          v-model:page="pagination.current_page"
          :length="pagination.last_page"
          @update:page="fetchUsers"
        />
      </VCardText>
    </VCard>
  </div>
</template>
