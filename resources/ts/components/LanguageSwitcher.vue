<template>
  <VMenu>
    <template #activator="{ props }">
      <VBtn
        v-bind="props"
        variant="text"
        icon
        size="small"
      >
        <VIcon :icon="currentLanguageIcon" />
      </VBtn>
    </template>

    <VList>
      <VListItem
        v-for="language in languages"
        :key="language.code"
        :active="currentLocale === language.code"
        @click="changeLanguage(language.code)"
      >
        <template #prepend>
          <VIcon :icon="language.icon" />
        </template>
        <VListItemTitle>{{ t(language.name) }}</VListItemTitle>
      </VListItem>
    </VList>
  </VMenu>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const { locale, t } = useI18n()

const languages = [
  {
    code: 'en',
    name: 'english',
    icon: 'flag-icon:us',
  },
  {
    code: 'fr', 
    name: 'french',
    icon: 'flag-icon:fr',
  },
  {
    code: 'ar',
    name: 'arabic',
    icon: 'flag-icon:sa',
  },
]

const currentLocale = computed(() => locale.value)

const currentLanguageIcon = computed(() => {
  const current = languages.find(lang => lang.code === currentLocale.value)
  return current?.icon || 'flag-icon:us'
})

const changeLanguage = (langCode: string) => {
  locale.value = langCode
  
  // Save to localStorage for persistence
  localStorage.setItem('locale', langCode)
  
  // Update document direction for RTL languages
  if (langCode === 'ar') {
    document.documentElement.setAttribute('dir', 'rtl')
    document.documentElement.setAttribute('lang', 'ar')
  } else {
    document.documentElement.setAttribute('dir', 'ltr')
    document.documentElement.setAttribute('lang', langCode)
  }
}

// Initialize language on component mount
const initializeLanguage = () => {
  const savedLocale = localStorage.getItem('locale') || 'en'
  if (savedLocale !== currentLocale.value) {
    changeLanguage(savedLocale)
  }
}

// Call initialization
initializeLanguage()
</script>
