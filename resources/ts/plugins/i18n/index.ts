import type { App } from 'vue'
import { createI18n } from 'vue-i18n'
import { cookieRef } from '@layouts/stores/config'
import { themeConfig } from '@themeConfig'

const messages = Object.fromEntries(
  Object.entries(
    import.meta.glob<{ default: any }>('./locales/*.json', { eager: true }))
    .map(([key, value]) => [key.slice(10, -5), value.default]),
)

let _i18n: any = null

export const getI18n = () => {
  if (_i18n === null) {
    _i18n = createI18n({
      legacy: false,
      locale: cookieRef('language', themeConfig.app.i18n.defaultLocale).value,
      fallbackLocale: 'en',
      messages,
    })
  }

  return _i18n
}

export default function (app: App) {
  const i18n = getI18n()
  app.use(i18n)
  
  // Set up locale change watchers for font management
  if (typeof window !== 'undefined') {
    // Watch for locale changes and update fonts
    const { global } = i18n
    
    // Apply font immediately based on current locale
    setTimeout(() => {
      try {
        import('@/composables/useFontManager').then(({ useFontManager }) => {
          const { setFontForLanguage } = useFontManager()
          setFontForLanguage(global.locale.value)
          
          // Set up watcher for future changes
          global.locale.value // This triggers reactivity setup
        })
      } catch (error) {
        console.warn('Font manager setup failed:', error)
      }
    }, 0)
  }
}
