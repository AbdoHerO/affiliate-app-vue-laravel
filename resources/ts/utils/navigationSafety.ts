/**
 * Navigation Safety Utilities
 * Provides enhanced navigation safety for Vue Router with comprehensive error handling
 */

import type { Router, RouteLocationRaw } from 'vue-router'
import { nextTick } from 'vue'

// Navigation error types
export enum NavigationErrorType {
  PROPERTY_ACCESS = 'property_access',
  COMPONENT_LIFECYCLE = 'component_lifecycle',
  AUTH_FAILURE = 'auth_failure',
  ROUTE_NOT_FOUND = 'route_not_found',
  UNKNOWN = 'unknown'
}

// Navigation safety configuration
interface NavigationConfig {
  maxRetries: number
  retryDelay: number
  enableLogging: boolean
  fallbackRoutes: Record<string, string>
}

const defaultConfig: NavigationConfig = {
  maxRetries: 3,
  retryDelay: 150,
  enableLogging: true,
  fallbackRoutes: {
    '/admin/stock': '/admin/dashboard',
    '/admin/support/tickets': '/admin/dashboard',
    '/admin/withdrawals': '/admin/dashboard',
    '/admin': '/admin/dashboard',
    '/affiliate': '/affiliate/dashboard',
    default: '/'
  }
}

export class NavigationSafety {
  private router: Router
  private config: NavigationConfig
  private navigationQueue: Array<{ route: RouteLocationRaw; resolve: Function; reject: Function }> = []
  private isProcessingQueue = false
  private errorHistory: Array<{ timestamp: number; error: string; route: string }> = []

  constructor(router: Router, config: Partial<NavigationConfig> = {}) {
    this.router = router
    this.config = { ...defaultConfig, ...config }
    this.setupErrorInterceptors()
  }

  /**
   * Safely navigate to a route with comprehensive error handling
   */
  async safeNavigate(
    to: RouteLocationRaw,
    options: {
      maxRetries?: number
      fallbackRoute?: string
      skipQueue?: boolean
    } = {}
  ): Promise<boolean> {
    const { maxRetries = this.config.maxRetries, fallbackRoute, skipQueue = false } = options

    if (!skipQueue) {
      return this.queueNavigation(to, maxRetries, fallbackRoute)
    }

    return this.executeNavigation(to, maxRetries, fallbackRoute)
  }

  /**
   * Queue navigation to prevent concurrent navigations
   */
  private queueNavigation(to: RouteLocationRaw, maxRetries: number, fallbackRoute?: string): Promise<boolean> {
    return new Promise((resolve, reject) => {
      this.navigationQueue.push({ route: to, resolve, reject })
      this.processNavigationQueue()
    })
  }

  /**
   * Process navigation queue sequentially
   */
  private async processNavigationQueue(): Promise<void> {
    if (this.isProcessingQueue || this.navigationQueue.length === 0) {
      return
    }

    this.isProcessingQueue = true

    while (this.navigationQueue.length > 0) {
      const { route, resolve, reject } = this.navigationQueue.shift()!

      try {
        const success = await this.executeNavigation(route)
        resolve(success)
      } catch (error) {
        reject(error)
      }

      // Small delay between navigations
      await new Promise(resolve => setTimeout(resolve, 50))
    }

    this.isProcessingQueue = false
  }

  /**
   * Execute navigation with retry logic
   */
  private async executeNavigation(
    to: RouteLocationRaw,
    maxRetries: number = this.config.maxRetries,
    fallbackRoute?: string
  ): Promise<boolean> {
    let attempts = 0
    let lastError: Error | null = null

    while (attempts <= maxRetries) {
      try {
        await this.router.push(to)
        this.log('Navigation successful', to)
        return true
      } catch (error: any) {
        attempts++
        lastError = error
        
        this.recordError(error, to)
        this.log(`Navigation attempt ${attempts} failed`, error?.message || error)

        // Determine if we should retry based on error type
        const errorType = this.classifyError(error)
        const shouldRetry = this.shouldRetryForErrorType(errorType, attempts, maxRetries)

        if (!shouldRetry) {
          break
        }

        // Wait before retry with exponential backoff
        await this.waitWithBackoff(attempts)
        
        // For component lifecycle errors, ensure Vue has time to cleanup
        if (errorType === NavigationErrorType.COMPONENT_LIFECYCLE) {
          await nextTick()
          await new Promise(resolve => setTimeout(resolve, 200))
        }
      }
    }

    // All retries failed, try fallback route
    if (fallbackRoute) {
      try {
        await this.router.push(fallbackRoute)
        this.log('Fallback navigation successful', fallbackRoute)
        return true
      } catch (fallbackError) {
        this.log('Fallback navigation failed', fallbackError)
      }
    }

    // Try automatic fallback based on failed route
    const autoFallback = this.getAutoFallbackRoute(to)
    if (autoFallback) {
      try {
        await this.router.push(autoFallback)
        this.log('Auto fallback navigation successful', autoFallback)
        return true
      } catch (autoFallbackError) {
        this.log('Auto fallback navigation failed', autoFallbackError)
      }
    }

    // Last resort: use window.location
    this.log('All navigation attempts failed, using window.location', lastError)
    const path = this.extractPathFromRoute(to) || autoFallback || '/'
    window.location.href = path
    return false
  }

  /**
   * Classify error type for appropriate handling
   */
  private classifyError(error: any): NavigationErrorType {
    const message = error?.message || error?.toString() || ''

    if (message.includes('emitsOptions') || message.includes('Cannot read properties of null')) {
      return NavigationErrorType.COMPONENT_LIFECYCLE
    }
    
    if (message.includes('startsWith') || message.includes('Cannot read properties of undefined')) {
      return NavigationErrorType.PROPERTY_ACCESS
    }
    
    if (message.includes('auth') || message.includes('permission')) {
      return NavigationErrorType.AUTH_FAILURE
    }
    
    if (message.includes('not found') || message.includes('404')) {
      return NavigationErrorType.ROUTE_NOT_FOUND
    }

    return NavigationErrorType.UNKNOWN
  }

  /**
   * Determine if we should retry based on error type
   */
  private shouldRetryForErrorType(errorType: NavigationErrorType, attempt: number, maxRetries: number): boolean {
    switch (errorType) {
      case NavigationErrorType.COMPONENT_LIFECYCLE:
        return attempt <= Math.min(maxRetries, 2) // Limit lifecycle retries
      case NavigationErrorType.PROPERTY_ACCESS:
        return attempt <= maxRetries
      case NavigationErrorType.AUTH_FAILURE:
        return false // Don't retry auth failures
      case NavigationErrorType.ROUTE_NOT_FOUND:
        return false // Don't retry route not found
      default:
        return attempt <= maxRetries
    }
  }

  /**
   * Wait with exponential backoff
   */
  private async waitWithBackoff(attempt: number): Promise<void> {
    const delay = this.config.retryDelay * Math.pow(2, attempt - 1)
    await new Promise(resolve => setTimeout(resolve, Math.min(delay, 1000)))
  }

  /**
   * Get automatic fallback route based on failed route
   */
  private getAutoFallbackRoute(failedRoute: RouteLocationRaw): string | null {
    const path = this.extractPathFromRoute(failedRoute)
    if (!path) return this.config.fallbackRoutes.default

    // Check for specific route patterns
    for (const [pattern, fallback] of Object.entries(this.config.fallbackRoutes)) {
      if (pattern !== 'default' && path.startsWith(pattern)) {
        return fallback
      }
    }

    return this.config.fallbackRoutes.default
  }

  /**
   * Extract path from route object
   */
  private extractPathFromRoute(route: RouteLocationRaw): string | null {
    if (typeof route === 'string') {
      return route
    }
    
    if (typeof route === 'object' && route) {
      if ('path' in route && typeof route.path === 'string') {
        return route.path
      }
      if ('name' in route && typeof route.name === 'string') {
        return `/${route.name.replace(/-/g, '/')}`
      }
    }

    return null
  }

  /**
   * Record error for analysis
   */
  private recordError(error: any, route: RouteLocationRaw): void {
    const errorRecord = {
      timestamp: Date.now(),
      error: error?.message || error?.toString() || 'Unknown error',
      route: this.extractPathFromRoute(route) || 'Unknown route'
    }

    this.errorHistory.push(errorRecord)

    // Keep only last 50 errors
    if (this.errorHistory.length > 50) {
      this.errorHistory = this.errorHistory.slice(-50)
    }
  }

  /**
   * Get error statistics
   */
  public getErrorStats(): {
    totalErrors: number
    recentErrors: number
    errorsByType: Record<string, number>
    mostFrequentError: string | null
  } {
    const fiveMinutesAgo = Date.now() - 5 * 60 * 1000
    const recentErrors = this.errorHistory.filter(e => e.timestamp > fiveMinutesAgo)
    
    const errorsByType: Record<string, number> = {}
    let mostFrequentError: string | null = null
    let maxCount = 0

    this.errorHistory.forEach(error => {
      errorsByType[error.error] = (errorsByType[error.error] || 0) + 1
      if (errorsByType[error.error] > maxCount) {
        maxCount = errorsByType[error.error]
        mostFrequentError = error.error
      }
    })

    return {
      totalErrors: this.errorHistory.length,
      recentErrors: recentErrors.length,
      errorsByType,
      mostFrequentError
    }
  }

  /**
   * Setup error interceptors for router
   */
  private setupErrorInterceptors(): void {
    // Intercept router errors
    this.router.onError((error, to, from) => {
      this.recordError(error, to)
      this.log('Router error intercepted', {
        error: error?.message || error,
        to: this.extractPathFromRoute(to),
        from: this.extractPathFromRoute(from)
      })
    })
  }

  /**
   * Safe logging utility
   */
  private log(message: string, data?: any): void {
    if (this.config.enableLogging) {
      console.log(`🔧 [Navigation Safety] ${message}`, data || '')
    }
  }

  /**
   * Clear error history
   */
  public clearErrorHistory(): void {
    this.errorHistory = []
  }

  /**
   * Check if route is safe to navigate to
   */
  public isRouteSafe(route: RouteLocationRaw): boolean {
    try {
      const path = this.extractPathFromRoute(route)
      if (!path) return false

      // Check if route has recent errors
      const fiveMinutesAgo = Date.now() - 5 * 60 * 1000
      const recentRouteErrors = this.errorHistory.filter(
        e => e.timestamp > fiveMinutesAgo && e.route === path
      )

      return recentRouteErrors.length < 3
    } catch (error) {
      return false
    }
  }

  /**
   * Force refresh if navigation is completely broken
   */
  public forceRefresh(): void {
    this.log('Force refreshing page due to navigation failure')
    window.location.reload()
  }
}

// Export singleton instance creator
let navigationSafetyInstance: NavigationSafety | null = null

export function createNavigationSafety(router: Router, config?: Partial<NavigationConfig>): NavigationSafety {
  if (!navigationSafetyInstance) {
    navigationSafetyInstance = new NavigationSafety(router, config)
  }
  return navigationSafetyInstance
}

export function useNavigationSafety(): NavigationSafety {
  if (!navigationSafetyInstance) {
    throw new Error('NavigationSafety not initialized. Call createNavigationSafety first.')
  }
  return navigationSafetyInstance
}
