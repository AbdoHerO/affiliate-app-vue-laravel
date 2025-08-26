import axios from 'axios'
import { handle401Unauthorized } from '@/utils/authHandler'

// Create axios instance with default config
const axiosInstance = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api',
  timeout: 10000,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
})

// Request interceptor
axiosInstance.interceptors.request.use(
  (config) => {
    // Add CSRF token if available
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
    if (token) {
      config.headers['X-CSRF-TOKEN'] = token
    }

    // Add auth token if available
    const authToken = localStorage.getItem('auth_token')
    if (authToken) {
      config.headers['Authorization'] = `Bearer ${authToken}`
    }

    return config
  },
  (error) => {
    return Promise.reject(error)
  }
)

// Response interceptor
axiosInstance.interceptors.response.use(
  (response) => {
    return response
  },
  async (error) => {
    // Handle common errors
    if (error.response?.status === 401) {
      await handle401Unauthorized()
    }

    if (error.response?.status === 403) {
      // Forbidden - show error message
      console.error('Access forbidden:', error.response.data)
    }

    if (error.response?.status === 422) {
      // Validation errors - let the component handle them
      return Promise.reject(error)
    }

    if (error.response?.status >= 500) {
      // Server errors
      console.error('Server error:', error.response.data)
    }

    return Promise.reject(error)
  }
)

export default axiosInstance
