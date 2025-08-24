<script setup lang="ts">
import { useAuth } from '@/composables/useAuth'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

definePage({
  meta: {
    requiresAuth: true,
    requiresRole: 'admin',
  },
})

const { user } = useAuth()
</script>

<template>
  <div>
    <VCard>
      <VCardText>
        <h1>{{ t('simple_admin_dashboard') }}</h1>
        <p>{{ t('welcome_user', { name: user?.nom_complet }) }}</p>
        <p>{{ t('minimal_dashboard_test_message') }}</p>
      </VCardText>
    </VCard>
  </div>
</template>
