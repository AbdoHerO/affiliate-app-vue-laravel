<template>
  <div>
    <!-- Error state -->
    <VAlert
      v-if="hasError"
      type="error"
      closable
      @click:close="clearError"
      class="mb-4"
    >
      <VAlertTitle>{{ $t('common.error_occurred') }}</VAlertTitle>
      <div>{{ errorMessage }}</div>
      <template #append>
        <VBtn
          variant="outlined"
          size="small"
          @click="retry"
          class="ml-2"
        >
          {{ $t('common.retry') }}
        </VBtn>
        <VBtn
          variant="outlined"
          size="small"
          color="secondary"
          @click="goToDashboard"
          class="ml-2"
        >
          {{ $t('common.go_to_dashboard') }}
        </VBtn>
      </template>
    </VAlert>

    <!-- Normal content -->
    <div v-if="!hasError">
      <slot />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onErrorCaptured, nextTick } from 'vue'
import { useI18n } from 'vue-i18n'
import { useSafeNavigation } from '@/composables/useSafeNavigation'
import { useNotifications } from '@/composables/useNotifications'

interface Props {
  fallbackRoute?: string
  maxRetries?: number
  showErrorAlert?: boolean
}

interface Emits {
  (e: 'error', error: Error): void
  (e: 'retry'): void
}

const props = withDefaults(defineProps<Props>(), {
  fallbackRoute: '/admin/dashboard',
  maxRetries: 3,
  showErrorAlert: true
})

const emit = defineEmits<Emits>()

// Composables
const { t } = useI18n()
const { safePush, emergencyReset } = useSafeNavigation()
const { showError } = useNotifications()

// State
const hasError = ref(false)
const errorMessage = ref('')
const retryCount = ref(0)

// Error capture
onErrorCaptured((err: Error, instance, info) => {
  console.error('🚨 [Error Boundary] Component error captured:', err, info)
  
  // Emit error event
  emit('error', err)
  
  // Handle specific Vue router errors
  const message = err.message || err.toString()
  
  if (message.includes('emitsOptions') || message.includes('Cannot read properties of null')) {
    console.error('🚨 [Error Boundary] Component lifecycle error detected')
    
    // For lifecycle errors, try to recover after nextTick
    nextTick(() => {
      if (retryCount.value < props.maxRetries) {
        console.log('🔄 [Error Boundary] Attempting automatic recovery...')
        retryCount.value++
        hasError.value = false
        return
      }
      
      // Max retries reached, show error
      handleError(err)
    })
    
    return false // Prevent error propagation
  }
  
  if (message.includes('startsWith') || message.includes('Cannot read properties of undefined')) {
    console.error('🚨 [Error Boundary] Property access error detected')
    handleError(err)
    return false
  }
  
  // Other errors
  handleError(err)
  return false
})

// Methods
const handleError = (error: Error) => {
  hasError.value = true
  errorMessage.value = error.message || t('common.unknown_error')
  
  if (props.showErrorAlert) {
    showError(errorMessage.value)
  }
  
  // If too many retries, suggest dashboard navigation
  if (retryCount.value >= props.maxRetries) {
    setTimeout(() => {
      if (hasError.value) {
        console.warn('🔄 [Error Boundary] Max retries reached, suggesting dashboard navigation')
        showError(t('common.too_many_errors_redirecting'))
        goToDashboard()
      }
    }, 5000)
  }
}

const clearError = () => {
  hasError.value = false
  errorMessage.value = ''
  retryCount.value = 0
}

const retry = async () => {
  if (retryCount.value >= props.maxRetries) {
    showError(t('common.max_retries_reached'))
    goToDashboard()
    return
  }
  
  retryCount.value++
  hasError.value = false
  errorMessage.value = ''
  
  emit('retry')
  
  // Wait a bit before allowing the component to retry
  await new Promise(resolve => setTimeout(resolve, 500))
}

const goToDashboard = async () => {
  try {
    await safePush(props.fallbackRoute, {
      fallback: '/admin/dashboard',
      skipSafetyCheck: true
    })
  } catch (error) {
    console.error('🚨 [Error Boundary] Dashboard navigation failed:', error)
    emergencyReset('/admin/dashboard')
  }
}

// Expose methods for parent components
defineExpose({
  clearError,
  retry,
  goToDashboard,
  hasError: () => hasError.value,
  retryCount: () => retryCount.value
})
</script>
