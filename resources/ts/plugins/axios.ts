import axios from 'axios'

// Create axios instance with default config
const axiosInstance = axios.create({
  baseURL: '/api',
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
  (error) => {
    // Handle common errors
    if (error.response?.status === 401) {
      // Unauthorized - redirect to login
      localStorage.removeItem('auth_token')
      window.location.href = '/login'
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
