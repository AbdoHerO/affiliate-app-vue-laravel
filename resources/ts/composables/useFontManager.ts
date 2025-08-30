import { watch } from 'vue'
import { useI18n } from 'vue-i18n'

/**
 * Font Management Composable
 * Automatically switches font family based on the current locale
 */
export function useFontManager() {
  const { locale } = useI18n()

  // Font families for different languages
  const fonts = {
    ar: '"Cairo", "Tahoma", "Arial Unicode MS", sans-serif',
    en: '"Public Sans", sans-serif, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif',
    fr: '"Public Sans", sans-serif, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif'
  }

  /**
   * Update CSS custom properties and body font family
   */
  const updateFontFamily = (language: string) => {
    const fontFamily = fonts[language as keyof typeof fonts] || fonts.en
    
    // Update CSS custom property
    document.documentElement.style.setProperty('--font-family-custom', fontFamily)
    
    // Update body font family directly
    document.body.style.fontFamily = fontFamily
    
    // Update any existing font-family CSS variables
    document.documentElement.style.setProperty('--v-theme-font-family', fontFamily)
    
    console.log(`Font family updated to: ${fontFamily}`)
  }

  /**
   * Initialize font manager
   */
  const initFontManager = () => {
    // Set initial font
    updateFontFamily(locale.value)
    
    // Watch for locale changes
    watch(locale, (newLocale) => {
      updateFontFamily(newLocale)
    }, { immediate: true })
  }

  /**
   * Manually set font for a specific language
   */
  const setFontForLanguage = (language: string) => {
    updateFontFamily(language)
  }

  return {
    initFontManager,
    setFontForLanguage,
    fonts
  }
}
