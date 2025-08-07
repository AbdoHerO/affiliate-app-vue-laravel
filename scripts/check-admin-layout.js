#!/usr/bin/env node

import fs from 'fs'

console.log('🔍 Vérification de la configuration layout admin/produits...')

// Vérifier que les routes existent
const requiredRoutes = [
  'resources/ts/pages/admin/produits/index.vue',
  'resources/ts/pages/admin/produits/create.vue',
  'resources/ts/pages/admin/produits/[id]/edit.vue',
  'resources/ts/pages/admin/produits/[id]/index.vue'
]

let hasErrors = false

requiredRoutes.forEach(routePath => {
  if (!fs.existsSync(routePath)) {
    console.error(`❌ Route manquante: ${routePath}`)
    hasErrors = true
  } else {
    console.log(`✅ Route présente: ${routePath}`)

    // Vérifier le contenu du fichier
    const content = fs.readFileSync(routePath, 'utf8')

    // Vérifier la présence du commentaire de protection
    if (!content.includes('⚠️ Ne PAS changer la meta layout sous peine de casser la sidebar')) {
      console.error(`❌ ${routePath}: Commentaire de protection manquant`)
      hasErrors = true
    }

    // Vérifier requiresAuth et requiresRole
    if (!content.includes('requiresAuth: true')) {
      console.error(`❌ ${routePath}: requiresAuth: true manquant`)
      hasErrors = true
    }

    if (!content.includes('requiresRole: \'admin\'')) {
      console.error(`❌ ${routePath}: requiresRole: 'admin' manquant`)
      hasErrors = true
    }
  }
})

// Vérifier qu'il n'y a pas de fichiers en double
const duplicateFiles = [
  'resources/ts/pages/admin/produits.vue',
  'resources/ts/pages/admin/produits/Edit.vue',
  'resources/ts/pages/admin/produits/Show.vue'
]

duplicateFiles.forEach(filePath => {
  if (fs.existsSync(filePath)) {
    console.error(`❌ Fichier en double détecté: ${filePath} - doit être supprimé`)
    hasErrors = true
  }
})

if (hasErrors) {
  console.error('\n❌ Des erreurs ont été détectées dans la configuration admin/produits')
  process.exit(1)
} else {
  console.log('\n✅ Configuration admin/produits correcte')
  process.exit(0)
}
