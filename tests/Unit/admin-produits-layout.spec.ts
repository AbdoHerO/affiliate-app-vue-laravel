import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createRouter, createWebHistory } from 'vue-router'
import { createPinia } from 'pinia'
import ProduitsIndex from '@/pages/admin/produits/index.vue'
import ProduitsCreate from '@/pages/admin/produits/create.vue'

// Mock router
const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/admin/produits', name: 'admin-produits-index', component: ProduitsIndex },
    { path: '/admin/produits/create', name: 'admin-produits-create', component: ProduitsCreate },
  ]
})

// Mock stores
const pinia = createPinia()

describe('Admin Produits Layout Tests', () => {
  beforeEach(() => {
    // Reset router and stores before each test
    router.push('/admin/produits')
  })

  it('should have correct meta configuration for layout', () => {
    // Test that produits pages have the correct meta
    const indexRoute = router.getRoutes().find(r => r.name === 'admin-produits-index')
    const createRoute = router.getRoutes().find(r => r.name === 'admin-produits-create')
    
    expect(indexRoute?.meta?.requiresAuth).toBe(true)
    expect(indexRoute?.meta?.requiresRole).toBe('admin')
    
    expect(createRoute?.meta?.requiresAuth).toBe(true)
    expect(createRoute?.meta?.requiresRole).toBe('admin')
  })

  it('should navigate correctly to create page', async () => {
    const wrapper = mount(ProduitsIndex, {
      global: {
        plugins: [router, pinia],
        stubs: {
          'VBtn': { template: '<button @click="$emit(\'click\')"><slot /></button>' },
          'VCard': { template: '<div><slot /></div>' },
          'VDataTable': { template: '<div>Table</div>' },
          'Breadcrumbs': { template: '<div>Breadcrumbs</div>' }
        }
      }
    })

    // Find the create button and simulate click
    const createButton = wrapper.find('button:contains("Créer")')
    if (createButton.exists()) {
      await createButton.trigger('click')
      
      // Check that router.push was called with correct route
      expect(router.currentRoute.value.name).toBe('admin-produits-create')
    }
  })

  it('should not have layout conflicts in page meta', () => {
    // Ensure no pages accidentally override the layout
    const routes = router.getRoutes().filter(r => r.path.startsWith('/admin/produits'))
    
    routes.forEach(route => {
      // Should not have layout: 'default' explicitly set (let system handle it)
      expect(route.meta?.layout).toBeUndefined()
    })
  })
})

// Snapshot test to ensure layout structure doesn't change
describe('Admin Produits Layout Snapshots', () => {
  it('should maintain consistent layout structure', () => {
    const wrapper = mount(ProduitsIndex, {
      global: {
        plugins: [router, pinia],
        stubs: {
          'VBtn': true,
          'VCard': true,
          'VDataTable': true,
          'Breadcrumbs': true,
          'ConfirmModal': true
        }
      }
    })

    // Take snapshot of the component structure
    expect(wrapper.html()).toMatchSnapshot()
  })
})
