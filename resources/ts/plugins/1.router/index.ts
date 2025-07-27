import { setupLayouts } from 'virtual:meta-layouts'
import type { App } from 'vue'

import type { RouteRecordRaw } from 'vue-router/auto'

import { createRouter, createWebHistory } from 'vue-router/auto'
import { useAuthStore } from '@/stores/auth'

function recursiveLayouts(route: RouteRecordRaw): RouteRecordRaw {
  if (route.children) {
    for (let i = 0; i < route.children.length; i++)
      route.children[i] = recursiveLayouts(route.children[i])

    return route
  }

  return setupLayouts([route])[0]
}

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  scrollBehavior(to) {
    if (to.hash)
      return { el: to.hash, behavior: 'smooth', top: 60 }

    return { top: 0 }
  },
  extendRoutes: pages => [
    ...[...pages].map(route => recursiveLayouts(route)),
  ],
})

// Route guards
router.beforeEach((to, from, next) => {
  const authStore = useAuthStore()

  // Initialize auth from localStorage if not already done
  if (!authStore.isAuthenticated) {
    authStore.initializeAuth()
  }

  // Check if route requires authentication
  const requiresAuth = to.meta.requiresAuth
  const requiresRole = to.meta.requiresRole
  const requiresPermission = to.meta.requiresPermission
  const isPublic = to.meta.public

  // Allow public routes
  if (isPublic) {
    return next()
  }

  // Redirect to login if authentication is required but user is not authenticated
  if (requiresAuth && !authStore.isAuthenticated) {
    return next({ name: 'login' })
  }

  // Check role requirements
  if (requiresRole && !authStore.hasRole(requiresRole)) {
    return next({ name: 'unauthorized' })
  }

  // Check permission requirements
  if (requiresPermission && !authStore.hasPermission(requiresPermission)) {
    return next({ name: 'unauthorized' })
  }

  // Redirect authenticated users away from login page
  if (to.path === '/login' && authStore.isAuthenticated) {
    if (authStore.hasRole('admin')) {
      return next({ name: 'admin-dashboard' })
    } else if (authStore.hasRole('affiliate')) {
      return next({ name: 'affiliate-dashboard' })
    }
    return next({ name: 'root' })
  }

  next()
})

export { router }

export default function (app: App) {
  app.use(router)
}
