<script setup lang="ts">
import { ref } from 'vue'
import axios from '@/plugins/axios'
import { $api } from '@/utils/api'
import { useApi } from '@/composables/useApi'
import { useApiErrorHandler } from '@/composables/useApiErrorHandler'

definePage({
  meta: {
    public: true,
    layout: 'blank',
  },
})

const { handleError } = useApiErrorHandler()
const isLoading = ref(false)
const results = ref<string[]>([])

const addResult = (message: string) => {
  results.value.push(`${new Date().toLocaleTimeString()}: ${message}`)
}

const testAxios401 = async () => {
  isLoading.value = true
  addResult('Testing Axios 401 handling...')
  
  try {
    await axios.get('/test-401-endpoint')
    addResult('❌ Axios: No error thrown')
  } catch (error) {
    addResult('✅ Axios: 401 error caught and handled')
  } finally {
    isLoading.value = false
  }
}

const testApi401 = async () => {
  isLoading.value = true
  addResult('Testing $api 401 handling...')
  
  try {
    await $api('/test-401-endpoint')
    addResult('❌ $api: No error thrown')
  } catch (error) {
    addResult('✅ $api: 401 error caught and handled')
  } finally {
    isLoading.value = false
  }
}

const testUseApi401 = async () => {
  isLoading.value = true
  addResult('Testing useApi 401 handling...')
  
  try {
    const { data, error } = await useApi('/test-401-endpoint').json()
    if (error.value) {
      addResult('✅ useApi: 401 error caught and handled')
    } else {
      addResult('❌ useApi: No error thrown')
    }
  } catch (error) {
    addResult('✅ useApi: 401 error caught and handled')
  } finally {
    isLoading.value = false
  }
}

const testFetch401 = async () => {
  isLoading.value = true
  addResult('Testing fetch 401 handling...')
  
  try {
    const response = await fetch('/api/test-401-endpoint', {
      headers: {
        'Authorization': 'Bearer invalid-token'
      }
    })
    
    if (response.status === 401) {
      addResult('✅ Fetch: 401 response detected')
    } else {
      addResult('❌ Fetch: No 401 response')
    }
  } catch (error) {
    addResult('✅ Fetch: 401 error caught and handled')
  } finally {
    isLoading.value = false
  }
}

const clearResults = () => {
  results.value = []
}
</script>

<template>
  <div class="pa-6">
    <VCard>
      <VCardTitle>
        🧪 Test 401 Authentication Handling
      </VCardTitle>
      
      <VCardText>
        <p class="mb-4">
          This page tests the 401 authentication handling across different API clients.
          Each test should automatically logout and redirect to login when a 401 error occurs.
        </p>
        
        <div class="d-flex flex-wrap gap-3 mb-4">
          <VBtn
            color="primary"
            :loading="isLoading"
            @click="testAxios401"
          >
            Test Axios 401
          </VBtn>
          
          <VBtn
            color="secondary"
            :loading="isLoading"
            @click="testApi401"
          >
            Test $api 401
          </VBtn>
          
          <VBtn
            color="info"
            :loading="isLoading"
            @click="testUseApi401"
          >
            Test useApi 401
          </VBtn>
          
          <VBtn
            color="warning"
            :loading="isLoading"
            @click="testFetch401"
          >
            Test Fetch 401
          </VBtn>
          
          <VBtn
            color="error"
            variant="outlined"
            @click="clearResults"
          >
            Clear Results
          </VBtn>
        </div>
        
        <VCard
          v-if="results.length > 0"
          variant="outlined"
        >
          <VCardTitle class="text-h6">
            Test Results
          </VCardTitle>
          <VCardText>
            <div
              v-for="(result, index) in results"
              :key="index"
              class="mb-1 font-mono text-sm"
            >
              {{ result }}
            </div>
          </VCardText>
        </VCard>
      </VCardText>
    </VCard>
  </div>
</template>

<style scoped>
.font-mono {
  font-family: 'Courier New', monospace;
}
</style>
