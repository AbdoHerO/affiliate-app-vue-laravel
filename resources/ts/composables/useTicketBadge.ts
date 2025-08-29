import { ref, onMounted, onUnmounted } from 'vue'
import { $api } from '@/utils/api'
import { useAuth } from '@/composables/useAuth'

export function useTicketBadge() {
  const { user, hasRole } = useAuth()
  
  // State
  const pendingCount = ref(0)
  const loading = ref(false)
  const error = ref<string | null>(null)
  
  // Polling interval (5 minutes)
  const POLL_INTERVAL = 5 * 60 * 1000
  let pollTimer: NodeJS.Timeout | null = null

  // Methods
  const fetchPendingCount = async (): Promise<void> => {
    if (!user || !user.value) return

    loading.value = true
    error.value = null

    try {
      let endpoint = ''

      if (hasRole('admin')) {
        endpoint = '/api/admin/support/tickets/pending-count'
      } else if (hasRole('affiliate')) {
        endpoint = '/api/affiliate/tickets/pending-count'
      } else {
        return
      }

      const response = await $api(endpoint)
      
      if (response.success) {
        pendingCount.value = response.count || 0
      } else {
        error.value = response.message || 'Failed to fetch ticket count'
        pendingCount.value = 0
      }
    } catch (err: any) {
      console.error('Error fetching ticket count:', err)
      error.value = err.response?.data?.message || 'Failed to fetch ticket count'
      pendingCount.value = 0
    } finally {
      loading.value = false
    }
  }

  const startPolling = (): void => {
    if (pollTimer) {
      clearInterval(pollTimer)
    }
    
    pollTimer = setInterval(fetchPendingCount, POLL_INTERVAL)
  }

  const stopPolling = (): void => {
    if (pollTimer) {
      clearInterval(pollTimer)
      pollTimer = null
    }
  }

  const refresh = (): void => {
    fetchPendingCount()
  }

  const incrementCount = (): void => {
    pendingCount.value += 1
  }

  const decrementCount = (): void => {
    if (pendingCount.value > 0) {
      pendingCount.value -= 1
    }
  }

  const resetCount = (): void => {
    pendingCount.value = 0
  }

  // Lifecycle
  onMounted(() => {
    if (user && user.value && (hasRole('admin') || hasRole('affiliate'))) {
      fetchPendingCount()
      startPolling()
    }
  })

  onUnmounted(() => {
    stopPolling()
  })

  return {
    // State
    pendingCount,
    loading,
    error,
    
    // Methods
    fetchPendingCount,
    refresh,
    incrementCount,
    decrementCount,
    resetCount,
    startPolling,
    stopPolling,
  }
}
