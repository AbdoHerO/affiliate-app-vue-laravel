import { createFetch } from '@vueuse/core'
import { destr } from 'destr'
import { useCookie } from '@/@core/composable/useCookie'
import { useAuthStore } from '@/stores/auth'
import { useRouter } from 'vue-router'
import { normalizeFromResponse } from '@/services/ErrorService'

export const useApi = createFetch({
  baseUrl: import.meta.env.VITE_API_BASE_URL || '/api',
  fetchOptions: {
    headers: {
      Accept: 'application/json',
    },
  },
  options: {
    refetch: true,
    async beforeFetch({ options }) {
      // Get auth store instance
      const authStore = useAuthStore()

      // Try to get token from auth store first, then localStorage, then cookie
      const authToken = authStore.token || localStorage.getItem('auth_token')
      const accessToken = useCookie('accessToken').value

      const token = authToken || accessToken

      // Ensure headers object exists and set Accept header
      options.headers = {
        Accept: 'application/json',
        ...options.headers,
      }

      if (token) {
        options.headers = {
          ...options.headers,
          Authorization: `Bearer ${token}`,
        }
        console.log('🔑 [API Interceptor] Adding auth token to request:', token.substring(0, 10) + '...')
      } else {
        console.warn('⚠️ [API Interceptor] No auth token found')
      }

      // Do NOT force Content-Type when body is FormData
      const isFormData = options.body instanceof FormData
      if (!isFormData && !(options.headers as any)['Content-Type']) {
        options.headers = {
          ...options.headers,
          'Content-Type': 'application/json',
        }
      }

      return { options }
    },
    async afterFetch(ctx) {
      const { data, response } = ctx

      // Handle authentication errors globally
      if (response.status === 401) {
        console.error('🚫 [API Interceptor] 401 Unauthorized - clearing auth and redirecting to login')
        const authStore = useAuthStore()
        authStore.clearAuth()

        // Redirect to login if not already there
        const router = useRouter()
        if (router.currentRoute.value.name !== 'login') {
          router.push({ name: 'login' })
        }
      }

      // Parse data if it's JSON
      let parsedData = null
      try {
        parsedData = destr(data)
      }
      catch (error) {
        console.error('Error parsing response data:', error)
      }

      return { data: parsedData, response }
    },
    async onFetchError(ctx) {
      const { response } = ctx

      console.error('🚫 [API Interceptor] Fetch error:', {
        status: response?.status,
        statusText: response?.statusText,
      })

      // Handle specific error cases
      if (response?.status === 401) {
        const authStore = useAuthStore()
        authStore.clearAuth()
      }

      // Use ErrorService to normalize the error
      if (response) {
        const normalizedError = await normalizeFromResponse(response)
        ctx.error = normalizedError
      }

      return ctx
    },
  },
})
