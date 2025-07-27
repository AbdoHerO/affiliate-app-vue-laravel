import { useAuthStore } from '@/stores/auth'
import { useRouter } from 'vue-router'
import type { LoginCredentials, RegisterData, UserRole, Permission } from '@/types/auth'

export const useAuth = () => {
  const authStore = useAuthStore()
  const router = useRouter()

  const login = async (credentials: LoginCredentials) => {
    try {
      await authStore.login(credentials)

      // Redirect based on user role
      if (authStore.hasRole('admin')) {
        router.push({ name: 'admin-dashboard' })
      } else if (authStore.hasRole('affiliate')) {
        router.push({ name: 'affiliate-dashboard' })
      } else {
        router.push({ name: 'root' })
      }
    } catch (error) {
      throw error
    }
  }

  const register = async (registerData: RegisterData) => {
    try {
      await authStore.register(registerData)

      // Redirect to affiliate dashboard after registration
      router.push({ name: 'affiliate-dashboard' })
    } catch (error) {
      throw error
    }
  }

  const logout = async () => {
    await authStore.logout()
    router.push({ name: 'login' })
  }

  const requireAuth = () => {
    if (!authStore.isAuthenticated) {
      router.push({ name: 'login' })
      return false
    }
    return true
  }

  const requireRole = (role: UserRole) => {
    if (!requireAuth()) return false

    if (!authStore.hasRole(role)) {
      router.push({ name: 'unauthorized' })
      return false
    }
    return true
  }

  const requirePermission = (permission: Permission) => {
    if (!requireAuth()) return false

    if (!authStore.hasPermission(permission)) {
      router.push({ name: 'unauthorized' })
      return false
    }
    return true
  }

  const requireAnyRole = (roles: UserRole[]) => {
    if (!requireAuth()) return false

    if (!authStore.hasAnyRole(roles)) {
      router.push({ name: 'unauthorized' })
      return false
    }
    return true
  }

  const requireAnyPermission = (permissions: Permission[]) => {
    if (!requireAuth()) return false

    if (!authStore.hasAnyPermission(permissions)) {
      router.push({ name: 'unauthorized' })
      return false
    }
    return true
  }

  return {
    // Store state
    user: authStore.user,
    isAuthenticated: authStore.isAuthenticated,
    isLoading: authStore.isLoading,
    error: authStore.error,
    userRoles: authStore.userRoles,
    userPermissions: authStore.userPermissions,

    // Store methods
    hasRole: authStore.hasRole,
    hasPermission: authStore.hasPermission,
    hasAnyRole: authStore.hasAnyRole,
    hasAnyPermission: authStore.hasAnyPermission,

    // Actions
    login,
    register,
    logout,
    fetchUser: authStore.fetchUser,
    initializeAuth: authStore.initializeAuth,

    // Guards
    requireAuth,
    requireRole,
    requirePermission,
    requireAnyRole,
    requireAnyPermission,
  }
}
