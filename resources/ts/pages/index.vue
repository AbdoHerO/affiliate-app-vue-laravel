<script setup lang="ts">
import { useAuth } from '@/composables/useAuth'
import { useRouter } from 'vue-router'

const { t, locale } = useI18n()
const { user, isAuthenticated, hasRole } = useAuth()
const router = useRouter()

// Redirect authenticated users to their appropriate dashboard
onMounted(() => {
  if (isAuthenticated.value) {
    if (hasRole('admin')) {
      router.push({ name: 'admin-dashboard' })
    } else if (hasRole('affiliate')) {
      router.push({ name: 'affiliate-dashboard' })
    }
  }
})
</script>

<template>
  <div>
    <!-- Authentication Status Card -->
    <VCard
      class="mb-6"
      title="🔐 Authentication Status"
    >
      <VCardText>
        <div class="mb-4">
          <strong>Authenticated:</strong>
          <VChip
            :color="isAuthenticated ? 'success' : 'error'"
            size="small"
            class="ml-2"
          >
            {{ isAuthenticated ? 'Yes' : 'No' }}
          </VChip>
        </div>
        <div v-if="isAuthenticated" class="mb-4">
          <strong>User:</strong> {{ user?.name }}<br>
          <strong>Email:</strong> {{ user?.email }}<br>
          <strong>Roles:</strong> {{ user?.roles.join(', ') }}<br>
          <strong>Permissions:</strong> {{ user?.permissions.join(', ') }}
        </div>
        <div v-else class="mb-4">
          <VBtn
            color="primary"
            to="/login"
          >
            Login to Test Authentication
          </VBtn>
        </div>
      </VCardText>
    </VCard>

    <!-- i18n Test Card -->
    <VCard
      class="mb-6"
      title="🌍 i18n Integration Test"
    >
      <VCardText>
        <div class="mb-4">
          <strong>Current Language:</strong> {{ locale }}
        </div>
        <div class="mb-4">
          <strong>Translation Test:</strong>
          <ul class="mt-2">
            <li><strong>Dashboard:</strong> {{ $t('Dashboard') }}</li>
            <li><strong>Settings:</strong> {{ $t('Settings') }}</li>
            <li><strong>User:</strong> {{ $t('User') }}</li>
            <li><strong>Analytics:</strong> {{ $t('Analytics') }}</li>
          </ul>
        </div>
        <div class="mb-4">
          <strong>Affiliate Platform Translations:</strong>
          <ul class="mt-2">
            <li><strong>Affiliate Platform:</strong> {{ $t('Affiliate Platform') }}</li>
            <li><strong>Affiliates:</strong> {{ $t('Affiliates') }}</li>
            <li><strong>Orders:</strong> {{ $t('Orders') }}</li>
            <li><strong>Commissions:</strong> {{ $t('Commissions') }}</li>
            <li><strong>Products:</strong> {{ $t('Products') }}</li>
            <li><strong>Welcome:</strong> {{ $t('Welcome') }}</li>
          </ul>
        </div>
        <div class="text-success">
          ✅ If you can see translated text above, i18n is working correctly!
        </div>
      </VCardText>
    </VCard>

    <VCard
      class="mb-6"
      title="Kick start your project 🚀"
    >
      <VCardText>All the best for your new project.</VCardText>
      <VCardText>
        Please make sure to read our <a
          href="https://demos.pixinvent.com/vuexy-vuejs-admin-template/documentation/"
          target="_blank"
          rel="noopener noreferrer"
          class="text-decoration-none"
        >
          Template Documentation
        </a> to understand where to go from here and how to use our template.
      </VCardText>
    </VCard>

    <VCard title="Want to integrate JWT? 🔒">
      <VCardText>We carefully crafted JWT flow so you can implement JWT with ease and with minimum efforts.</VCardText>
      <VCardText>Please read our  JWT Documentation to get more out of JWT authentication.</VCardText>
    </VCard>
  </div>
</template>
