#!/usr/bin/env node

const puppeteer = require('puppeteer')

async function testProduitsNavigation() {
  console.log('🧪 Test de navigation admin/produits...')
  
  const browser = await puppeteer.launch({ 
    headless: false, // Pour voir le test en action
    defaultViewport: { width: 1280, height: 720 }
  })
  
  try {
    const page = await browser.newPage()
    
    // 1. Test: Accès direct à /admin/produits
    console.log('📍 Test 1: Accès direct à /admin/produits')
    await page.goto('http://localhost:5173/admin/produits')
    
    // Attendre que la page se charge
    await page.waitForTimeout(2000)
    
    // Vérifier la présence de la sidebar
    const sidebar = await page.$('.v-navigation-drawer')
    if (sidebar) {
      console.log('✅ Sidebar présente')
    } else {
      console.error('❌ Sidebar manquante')
      return false
    }
    
    // Vérifier la présence du header
    const header = await page.$('.v-app-bar')
    if (header) {
      console.log('✅ Header présent')
    } else {
      console.error('❌ Header manquant')
      return false
    }
    
    // Vérifier le titre de la page
    const title = await page.$eval('h1', el => el.textContent)
    if (title && title.includes('Produits')) {
      console.log('✅ Titre correct:', title)
    } else {
      console.error('❌ Titre incorrect:', title)
      return false
    }
    
    // 2. Test: Clic sur "Créer un produit"
    console.log('📍 Test 2: Navigation vers création')
    
    const createButton = await page.$('button:has-text("Créer")')
    if (createButton) {
      await createButton.click()
      console.log('✅ Bouton "Créer" cliqué')
    } else {
      // Essayer avec un sélecteur plus spécifique
      await page.click('button[prepend-icon="tabler-plus"]')
      console.log('✅ Bouton création trouvé et cliqué')
    }
    
    // Attendre la navigation
    await page.waitForTimeout(1000)
    
    // Vérifier l'URL
    const currentUrl = page.url()
    if (currentUrl.includes('/admin/produits/create')) {
      console.log('✅ Navigation vers create réussie:', currentUrl)
    } else {
      console.error('❌ Navigation échouée. URL actuelle:', currentUrl)
      return false
    }
    
    // Vérifier que le layout est toujours présent
    const sidebarCreate = await page.$('.v-navigation-drawer')
    const headerCreate = await page.$('.v-app-bar')
    
    if (sidebarCreate && headerCreate) {
      console.log('✅ Layout admin maintenu sur la page create')
    } else {
      console.error('❌ Layout admin perdu sur la page create')
      return false
    }
    
    // 3. Test: Hard refresh
    console.log('📍 Test 3: Hard refresh')
    await page.reload({ waitUntil: 'networkidle0' })
    
    const sidebarRefresh = await page.$('.v-navigation-drawer')
    const headerRefresh = await page.$('.v-app-bar')
    
    if (sidebarRefresh && headerRefresh) {
      console.log('✅ Layout admin maintenu après refresh')
    } else {
      console.error('❌ Layout admin perdu après refresh')
      return false
    }
    
    console.log('🎉 Tous les tests passés avec succès!')
    return true
    
  } catch (error) {
    console.error('❌ Erreur pendant les tests:', error.message)
    return false
  } finally {
    await browser.close()
  }
}

// Exécuter les tests
testProduitsNavigation().then(success => {
  process.exit(success ? 0 : 1)
})
