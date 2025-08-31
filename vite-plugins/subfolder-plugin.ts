import type { Plugin } from 'vite'

export function createSubfolderPlugin(base: string): Plugin {
  return {
    name: 'subfolder-plugin',
    config(config) {
      if (!config.base) {
        config.base = base
      }
    },
    generateBundle(options, bundle) {
      // Handle dynamic imports for subfolder
      Object.keys(bundle).forEach(fileName => {
        const chunk = bundle[fileName]
        if (chunk.type === 'chunk') {
          // Ensure dynamic imports work with subfolder
          chunk.code = chunk.code.replace(
            /import\s*\(\s*['"`]([^'"`]+)['"`]\s*\)/g,
            `import('${base}build/$1')`
          )
        }
      })
    }
  }
}