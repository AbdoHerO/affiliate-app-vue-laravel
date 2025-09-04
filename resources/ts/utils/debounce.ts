/**
 * Debounce utility to prevent rapid successive function calls
 */
export function debounce<T extends (...args: any[]) => any>(
  func: T,
  wait: number,
  immediate = false
): (...args: Parameters<T>) => void {
  let timeout: NodeJS.Timeout | null = null
  
  return function executedFunction(...args: Parameters<T>) {
    const later = () => {
      timeout = null
      if (!immediate) func(...args)
    }
    
    const callNow = immediate && !timeout
    
    if (timeout) clearTimeout(timeout)
    timeout = setTimeout(later, wait)
    
    if (callNow) func(...args)
  }
}

/**
 * Throttle utility to limit function execution frequency
 */
export function throttle<T extends (...args: any[]) => any>(
  func: T,
  limit: number
): (...args: Parameters<T>) => void {
  let inThrottle: boolean
  
  return function executedFunction(...args: Parameters<T>) {
    if (!inThrottle) {
      func(...args)
      inThrottle = true
      setTimeout(() => inThrottle = false, limit)
    }
  }
}

/**
 * Create a function that can only be called once within a time window
 */
export function once<T extends (...args: any[]) => any>(
  func: T,
  resetAfter = 1000
): (...args: Parameters<T>) => ReturnType<T> | undefined {
  let called = false
  let result: ReturnType<T>
  
  return function executedFunction(...args: Parameters<T>): ReturnType<T> | undefined {
    if (!called) {
      called = true
      result = func(...args)
      
      // Reset after specified time
      setTimeout(() => {
        called = false
      }, resetAfter)
      
      return result
    }
    
    return undefined
  }
}
