import { setupLayouts } from 'virtual:meta-layouts'
import type { App } from 'vue'

import type { RouteRecordRaw } from 'vue-router/auto'
import { createRouter, createWebHistory } from 'vue-router/auto'
import { setupRouterGuards } from '@/plugins/router/guards_production'

const router = createRouter({
  history: createWebHistory('/'), // Root domain - no subfolder
  scrollBehavior(to) {
    if (to.hash)
      return { el: to.hash, behavior: 'smooth', top: 60 }

    return { top: 0 }
  },
  extendRoutes: routes => setupLayouts(routes as unknown as RouteRecordRaw[]),
})

// Setup route guards
setupRouterGuards(router)

export { router }

export default function (app: App) {
  app.use(router)
}
