import { setupLayouts } from 'virtual:meta-layouts'
import type { App } from 'vue'

import type { RouteRecordRaw } from 'vue-router/auto'
import { createRouter, createWebHistory } from 'vue-router/auto'
import { setupRouterGuards } from '@/plugins/router/guards'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  scrollBehavior(to) {
    if (to.hash)
      return { el: to.hash, behavior: 'smooth', top: 60 }

    return { top: 0 }
  },
  // IMPORTANT: Apply layouts to the entire routes array, not only leaf nodes.
  // This ensures parent index routes that have children (e.g., /admin/affiliates, /admin/orders/pre)
  // are also wrapped with the default layout and show the sidebar/header.
  extendRoutes: routes => setupLayouts(routes as unknown as RouteRecordRaw[]),
})

// Setup route guards
setupRouterGuards(router)

export { router }

export default function (app: App) {
  app.use(router)
}
