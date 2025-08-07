module.exports = {
  rules: {
    // Custom rule to prevent layout issues in admin pages
    'admin-layout-protection': {
      meta: {
        type: 'problem',
        docs: {
          description: 'Prevent layout configuration issues in admin pages',
          category: 'Possible Errors',
        },
        fixable: null,
        schema: []
      },
      create(context) {
        return {
          // Check for explicit layout configuration in admin pages
          Property(node) {
            const filename = context.getFilename()
            
            // Only check files in admin/produits directory
            if (!filename.includes('pages/admin/produits/')) {
              return
            }
            
            // Check for layout property in meta
            if (node.key && node.key.name === 'layout' && 
                node.parent && node.parent.parent && 
                node.parent.parent.key && node.parent.parent.key.name === 'meta') {
              
              context.report({
                node,
                message: '⚠️ Ne PAS spécifier layout explicitement dans les pages admin/produits. Laissez le système détecter automatiquement. Voir ticket #123.'
              })
            }
          },
          
          // Check for incorrect router.push usage
          CallExpression(node) {
            const filename = context.getFilename()
            
            if (!filename.includes('pages/admin/produits/')) {
              return
            }
            
            // Check for router.push with string instead of route name
            if (node.callee && 
                node.callee.type === 'MemberExpression' &&
                node.callee.object && node.callee.object.name === 'router' &&
                node.callee.property && node.callee.property.name === 'push' &&
                node.arguments.length > 0 &&
                node.arguments[0].type === 'Literal' &&
                typeof node.arguments[0].value === 'string' &&
                node.arguments[0].value.includes('/admin/produits/')) {
              
              context.report({
                node,
                message: 'Utilisez router.push({ name: "admin-produits-*" }) au lieu de chemins en dur pour une navigation fiable.'
              })
            }
          }
        }
      }
    }
  }
}
