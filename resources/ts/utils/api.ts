import { ofetch } from 'ofetch'
import { handle401Unauthorized } from './authHandler'

export const $api = ofetch.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api',
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
  },
  async onRequest({ options }) {
    // Get token from localStorage to avoid circular dependency
    const token = localStorage.getItem('auth_token')

    if (token) {
      options.headers = {
        ...options.headers,
        'Authorization': `Bearer ${token}`
      }
    }
  },
  async onResponseError({ response }) {
    // Handle 401 Unauthorized errors
    if (response.status === 401) {
      await handle401Unauthorized()
    }
  },
})
