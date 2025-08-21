import { createFetch } from '@vueuse/core'
import { destr } from 'destr'
import { useCookie } from '@/@core/composable/useCookie'
import { useAuthStore } from '@/stores/auth'
import { useRouter } from 'vue-router'
import { normalizeFromResponse } from '@/services/ErrorService'

// Safe API base URL resolution to prevent VueUse joinPaths startsWith error
const getApiBaseUrl = (): string => {
  const envUrl = import.meta.env.VITE_API_BASE_URL
  
  // Ensure we return a valid string to prevent VueUse joinPaths issues
  if (typeof envUrl === 'string' && envUrl.trim().length > 0) {
    return envUrl.trim()
  }
  
  // Fallback to a valid string
  return '/api'
}

export const useApi = createFetch({
  baseUrl: getApiBaseUrl(),
  fetchOptions: {
    headers: {
      Accept: 'application/json',
    },
    credentials: 'include', // Include cookies for session-based auth
  },
  options: {
    refetch: true,
    async beforeFetch({ url, options }) {
      // Dev guard: warn about double /api paths
      if (import.meta.env.DEV && url.startsWith('/api/')) {
        console.warn(`⚠️ [useApi] URL starts with '/api/' but baseURL is already '${getApiBaseUrl()}'. This will result in '/api/api/...' URLs. Use '${url.substring(5)}' instead.`)
      }
      // Get auth store instance
      const authStore = useAuthStore()

      // Try to get token from auth store first, then localStorage
      const token = authStore.token || localStorage.getItem('auth_token')

      // Ensure headers object exists and set Accept header
      options.headers = {
        Accept: 'application/json',
        ...options.headers,
      }

      if (token) {
        options.headers = { ...options.headers, Authorization: `Bearer ${token}` }
      }

      // Add CSRF token for session-based requests if available
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
      if (csrfToken) {
        options.headers = { ...options.headers, 'X-CSRF-TOKEN': csrfToken }
      }

      // Do NOT force Content-Type when body is FormData
      const body: any = (options as any).body
      const isFormData = typeof FormData !== 'undefined' && body instanceof FormData
        || (body && typeof body === 'object' && typeof body.append === 'function' && typeof body.get === 'function' && (body[Symbol.toStringTag] === 'FormData' || Object.prototype.toString.call(body) === '[object FormData]'))

      if (isFormData) {
        // Ensure we didn't accidentally set a JSON content type earlier
        if ((options.headers as any)['Content-Type']) {
          const { ['Content-Type']: _removed, ...rest } = options.headers as any
          options.headers = rest
        }
      } else if (!(options.headers as any)['Content-Type']) {
        // Only auto‑set JSON Content-Type when the caller did not specify one and it's not FormData
        options.headers = {
          ...options.headers,
          'Content-Type': 'application/json',
        }
      } else if ((options.headers as any)['Content-Type'] === 'application/json' && body instanceof FormData) {
        // Fallback safeguard (shouldn't happen if detection worked)
        const { ['Content-Type']: _removed, ...rest } = options.headers as any
        options.headers = rest
      }

      return { options }
    },
    async afterFetch(ctx) {
      const { data, response } = ctx

      // Handle authentication errors globally
      if (response.status === 401) {
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
      try { parsedData = destr(data) } catch (_) { /* ignore parse errors */ }

      return { data: parsedData, response }
    },
    async onFetchError(ctx) {
      const { response } = ctx

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
