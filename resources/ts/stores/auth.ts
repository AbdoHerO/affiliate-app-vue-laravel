import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type { User, LoginCredentials, RegisterData } from '@/types/auth'

export const useAuthStore = defineStore('auth', () => {
  // State
  const user = ref<User | null>(null)
  const token = ref<string | null>(null)
  const isLoading = ref(false)
  const error = ref<string | null>(null)

  // Getters
  const isAuthenticated = computed(() => !!token.value && !!user.value)
  const userRoles = computed(() => user.value?.roles || [])
  const userPermissions = computed(() => user.value?.permissions || [])

  // Helper functions
  const hasRole = (role: string): boolean => {
    return userRoles.value.includes(role)
  }

  const hasPermission = (permission: string): boolean => {
    return userPermissions.value.includes(permission)
  }

  const hasAnyRole = (roles: string[]): boolean => {
    return roles.some(role => hasRole(role))
  }

  const hasAnyPermission = (permissions: string[]): boolean => {
    return permissions.some(permission => hasPermission(permission))
  }

  // Actions
  const setToken = (newToken: string) => {
    token.value = newToken
    localStorage.setItem('auth_token', newToken)
  }

  const setUser = (newUser: User) => {
    user.value = newUser
    localStorage.setItem('auth_user', JSON.stringify(newUser))
  }

  const clearAuth = () => {
    user.value = null
    token.value = null
    localStorage.removeItem('auth_token')
    localStorage.removeItem('auth_user')
  }

  const initializeAuth = () => {
    isLoading.value = true

    const storedToken = localStorage.getItem('auth_token')
    const storedUser = localStorage.getItem('auth_user')

    if (storedToken && storedUser) {
      token.value = storedToken
      try {
        user.value = JSON.parse(storedUser)
      } catch (e) {
        console.error('Failed to parse stored user data:', e)
        clearAuth()
      }
    }

    isLoading.value = false
  }

  const login = async (credentials: LoginCredentials): Promise<void> => {
    isLoading.value = true
    error.value = null

    try {
      const response = await fetch('/api/auth/login', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(credentials),
      })

      const data = await response.json()

      if (!response.ok) {
        throw new Error(data.message || 'Login failed')
      }

      setToken(data.token)
      setUser(data.user)
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'Login failed'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  const register = async (registerData: RegisterData): Promise<void> => {
    isLoading.value = true
    error.value = null

    try {
      const response = await fetch('/api/auth/register', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(registerData),
      })

      const data = await response.json()

      if (!response.ok) {
        throw new Error(data.message || 'Registration failed')
      }

      setToken(data.token)
      setUser(data.user)
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'Registration failed'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  const logout = async (): Promise<void> => {
    isLoading.value = true

    try {
      if (token.value) {
        await fetch('/api/auth/logout', {
          method: 'POST',
          headers: {
            'Authorization': `Bearer ${token.value}`,
            'Content-Type': 'application/json',
          },
        })
      }
    } catch (err) {
      console.error('Logout error:', err)
    } finally {
      clearAuth()
      isLoading.value = false
    }
  }

  const fetchUser = async (): Promise<void> => {
    if (!token.value) return

    try {
      const response = await fetch('/api/auth/user', {
        headers: {
          'Authorization': `Bearer ${token.value}`,
          'Content-Type': 'application/json',
        },
      })

      if (!response.ok) {
        throw new Error('Failed to fetch user')
      }

      const data = await response.json()
      setUser(data.user)
    } catch (err) {
      console.error('Failed to fetch user:', err)
      clearAuth()
    }
  }

  return {
    // State
    user,
    token,
    isLoading,
    error,

    // Getters
    isAuthenticated,
    userRoles,
    userPermissions,

    // Helper functions
    hasRole,
    hasPermission,
    hasAnyRole,
    hasAnyPermission,

    // Actions
    login,
    register,
    logout,
    fetchUser,
    initializeAuth,
    clearAuth,
  }
})
