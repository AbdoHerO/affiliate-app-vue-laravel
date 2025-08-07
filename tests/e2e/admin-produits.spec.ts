import { test, expect } from '@playwright/test'

test.describe('Admin Produits CRUD', () => {
  test.beforeEach(async ({ page }) => {
    // Login as admin
    await page.goto('/login')
    await page.fill('input[type="email"]', 'admin@example.com')
    await page.fill('input[type="password"]', 'password')
    await page.click('button[type="submit"]')
    await page.waitForURL('/admin')
  })

  test('should display admin layout (sidebar + header) on products list page', async ({ page }) => {
    // Navigate to products page
    await page.goto('/admin/produits')
    
    // Check that admin layout is present
    await expect(page.locator('.v-navigation-drawer')).toBeVisible()
    await expect(page.locator('.v-app-bar')).toBeVisible()
    
    // Check that the page title is correct
    await expect(page.locator('h1')).toContainText('Produits')
  })

  test('should navigate to create product page when clicking create button', async ({ page }) => {
    // Navigate to products page
    await page.goto('/admin/produits')
    
    // Click the create button
    await page.click('button:has-text("Créer un Produit")')
    
    // Check that we navigated to the create page
    await expect(page).toHaveURL('/admin/produits/create')
    
    // Check that the create form is visible
    await expect(page.locator('form')).toBeVisible()
    await expect(page.locator('h1')).toContainText('Créer un Produit')
    
    // Check that admin layout is still present
    await expect(page.locator('.v-navigation-drawer')).toBeVisible()
    await expect(page.locator('.v-app-bar')).toBeVisible()
  })

  test('should maintain admin layout after hard refresh on products pages', async ({ page }) => {
    // Test products list page
    await page.goto('/admin/produits')
    await page.reload()
    await expect(page.locator('.v-navigation-drawer')).toBeVisible()
    await expect(page.locator('.v-app-bar')).toBeVisible()
    
    // Test create page
    await page.goto('/admin/produits/create')
    await page.reload()
    await expect(page.locator('.v-navigation-drawer')).toBeVisible()
    await expect(page.locator('.v-app-bar')).toBeVisible()
  })

  test('should have working breadcrumbs navigation', async ({ page }) => {
    await page.goto('/admin/produits/create')
    
    // Check breadcrumbs are present
    await expect(page.locator('[data-testid="breadcrumbs"]')).toBeVisible()
    
    // Click on "Produits" breadcrumb to go back
    await page.click('a:has-text("Produits")')
    await expect(page).toHaveURL('/admin/produits')
  })
})
