<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useAuth } from '@/composables/useAuth'
import { useApi } from '@/composables/useApi'
import { useAuthStore } from '@/stores/auth'

definePage({
  meta: {
    public: true,
    layout: 'blank',
  },
})

const { isAuthenticated, hasRole, hasPermission } = useAuth()
const authStore = useAuthStore()

const testResults = ref<Array<{
  name: string
  status: 'pending' | 'success' | 'error'
  message: string
  data?: any
}>>([])

const isLoading = ref(false)

const addResult = (name: string, status: 'success' | 'error', message: string, data?: any) => {
  testResults.value.push({ name, status, message, data })
}

const clearResults = () => {
  testResults.value = []
}

const testLogin = async () => {
  try {
    addResult('Login Test', 'pending', 'Testing login...')
    
    await authStore.login({
      email: 'admin@cod.test',
      password: 'password'
    })
    
    if (authStore.isAuthenticated) {
      addResult('Login Test', 'success', 'Login successful!', {
        user: authStore.user,
        token: authStore.token?.substring(0, 20) + '...'
      })
    } else {
      addResult('Login Test', 'error', 'Login failed - not authenticated')
    }
  } catch (error: any) {
    addResult('Login Test', 'error', `Login failed: ${error.message}`)
  }
}

const testApiCall = async () => {
  try {
    addResult('API Test', 'pending', 'Testing authenticated API call...')
    
    const { data, error } = await useApi<any>('/admin/users?per_page=5')
    
    if (error.value) {
      addResult('API Test', 'error', `API call failed: ${error.value.message || 'Unknown error'}`, error.value)
    } else {
      addResult('API Test', 'success', 'API call successful!', {
        userCount: data.value?.data?.length || 0,
        totalUsers: data.value?.total || 0
      })
    }
  } catch (error: any) {
    addResult('API Test', 'error', `API call failed: ${error.message}`)
  }
}

const testCreateUser = async () => {
  try {
    addResult('Create User Test', 'pending', 'Testing user creation...')
    
    const { data, error } = await useApi<any>('/admin/users', {
      method: 'POST',
      body: JSON.stringify({
        nom_complet: 'Test User ' + Date.now(),
        email: `test${Date.now()}@example.com`,
        password: 'password123',
        password_confirmation: 'password123',
        role: 'affiliate',
        statut: 'actif',
        kyc_statut: 'non_requis'
      })
    })
    
    if (error.value) {
      addResult('Create User Test', 'error', `User creation failed: ${error.value.message || 'Unknown error'}`, error.value)
    } else {
      addResult('Create User Test', 'success', 'User created successfully!', data.value)
    }
  } catch (error: any) {
    addResult('Create User Test', 'error', `User creation failed: ${error.message}`)
  }
}

const runAllTests = async () => {
  isLoading.value = true
  clearResults()
  
  try {
    // Test 1: Login
    await testLogin()
    await new Promise(resolve => setTimeout(resolve, 1000))
    
    // Test 2: API Call
    await testApiCall()
    await new Promise(resolve => setTimeout(resolve, 1000))
    
    // Test 3: Create User
    await testCreateUser()
    
  } catch (error) {
    console.error('Test suite error:', error)
  } finally {
    isLoading.value = false
  }
}

const logout = async () => {
  await authStore.logout()
  clearResults()
}

onMounted(() => {
  addResult('Auth Status', isAuthenticated.value ? 'success' : 'error', 
    isAuthenticated.value ? 'User is authenticated' : 'User is not authenticated', {
      user: authStore.user,
      token: authStore.token ? authStore.token.substring(0, 20) + '...' : null
    })
})
</script>

<template>
  <div class="pa-6">
    <VCard>
      <VCardTitle class="d-flex align-center gap-2">
        <VIcon icon="tabler-test-pipe" />
        Authentication & API Test Suite
      </VCardTitle>
      
      <VCardText>
        <div class="mb-4">
          <h3 class="mb-2">Current Auth Status:</h3>
          <VChip
            :color="isAuthenticated ? 'success' : 'error'"
            variant="tonal"
            class="me-2"
          >
            {{ isAuthenticated ? 'Authenticated' : 'Not Authenticated' }}
          </VChip>
          
          <VChip
            v-if="isAuthenticated && authStore.user"
            color="info"
            variant="tonal"
            class="me-2"
          >
            {{ authStore.user.nom_complet }}
          </VChip>
          
          <VChip
            v-if="isAuthenticated && hasRole('admin')"
            color="primary"
            variant="tonal"
          >
            Admin Role
          </VChip>
        </div>

        <div class="d-flex gap-2 mb-4 flex-wrap">
          <VBtn
            color="primary"
            :loading="isLoading"
            @click="runAllTests"
          >
            <VIcon start icon="tabler-play" />
            Run All Tests
          </VBtn>
          
          <VBtn
            variant="outlined"
            @click="testLogin"
            :disabled="isLoading"
          >
            Test Login
          </VBtn>
          
          <VBtn
            variant="outlined"
            @click="testApiCall"
            :disabled="isLoading || !isAuthenticated"
          >
            Test API
          </VBtn>
          
          <VBtn
            variant="outlined"
            @click="testCreateUser"
            :disabled="isLoading || !isAuthenticated"
          >
            Test Create User
          </VBtn>
          
          <VBtn
            v-if="isAuthenticated"
            color="error"
            variant="outlined"
            @click="logout"
            :disabled="isLoading"
          >
            Logout
          </VBtn>
          
          <VBtn
            variant="outlined"
            @click="clearResults"
            :disabled="isLoading"
          >
            Clear Results
          </VBtn>
        </div>

        <div v-if="testResults.length > 0">
          <h3 class="mb-3">Test Results:</h3>
          <VTimeline density="compact">
            <VTimelineItem
              v-for="(result, index) in testResults"
              :key="index"
              :dot-color="result.status === 'success' ? 'success' : result.status === 'error' ? 'error' : 'warning'"
              size="small"
            >
              <div class="d-flex align-center gap-2 mb-1">
                <strong>{{ result.name }}</strong>
                <VChip
                  :color="result.status === 'success' ? 'success' : result.status === 'error' ? 'error' : 'warning'"
                  size="small"
                  variant="tonal"
                >
                  {{ result.status }}
                </VChip>
              </div>
              
              <p class="text-body-2 mb-2">{{ result.message }}</p>
              
              <VExpansionPanels
                v-if="result.data"
                variant="accordion"
                class="mb-2"
              >
                <VExpansionPanel>
                  <VExpansionPanelTitle>
                    <VIcon start icon="tabler-code" />
                    View Data
                  </VExpansionPanelTitle>
                  <VExpansionPanelText>
                    <pre class="text-caption">{{ JSON.stringify(result.data, null, 2) }}</pre>
                  </VExpansionPanelText>
                </VExpansionPanel>
              </VExpansionPanels>
            </VTimelineItem>
          </VTimeline>
        </div>
      </VCardText>
    </VCard>
  </div>
</template>

<style scoped>
pre {
  background: #f5f5f5;
  padding: 8px;
  border-radius: 4px;
  overflow-x: auto;
  max-height: 200px;
}
</style>
