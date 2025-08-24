import { ref, computed, onMounted, nextTick, type Ref, type ComputedRef } from 'vue'

export interface SafeChartOptions {
  minWidth?: number
  minHeight?: number
  delayMs?: number
}

/**
 * Composable to safely render ApexCharts without NaN width/height errors
 * Forces container dimensions and validates data before render
 */
export function useSafeApexChart(
  loading: ComputedRef<boolean> | Ref<boolean>,
  data: ComputedRef<any> | Ref<any>,
  options: SafeChartOptions = {}
) {
  const {
    minWidth = 200,
    minHeight = 150,
    delayMs = 100
  } = options

  const isChartReady = ref(false)
  const containerRef = ref<HTMLElement>()

  // Initialize chart after DOM is stable
  onMounted(async () => {
    await nextTick()
    
    // Additional delay to ensure parent layouts are computed
    setTimeout(() => {
      // Verify container has dimensions
      if (containerRef.value) {
        const rect = containerRef.value.getBoundingClientRect()
        if (rect.width > 0 && rect.height > 0) {
          isChartReady.value = true
        } else {
          // Force minimum dimensions if container collapsed
          if (containerRef.value) {
            containerRef.value.style.minWidth = `${minWidth}px`
            containerRef.value.style.minHeight = `${minHeight}px`
          }
          // Retry after forced sizing
          setTimeout(() => {
            isChartReady.value = true
          }, 50)
        }
      } else {
        // No container reference, proceed anyway
        isChartReady.value = true
      }
    }, delayMs)
  })

  // Comprehensive data validation
  const isValidData = computed(() => {
    if (loading.value) return false
    if (!data.value) return false
    
    // For series-based charts (line, area, bar)
    if (Array.isArray(data.value.chartData)) {
      const validNumbers = data.value.chartData.filter((v: any) => 
        typeof v === 'number' && isFinite(v)
      )
      return validNumbers.length > 0
    }
    
    // For charts with values array
    if (Array.isArray(data.value.values)) {
      const validNumbers = data.value.values.filter((v: any) => 
        typeof v === 'number' && isFinite(v)
      )
      return validNumbers.length > 0
    }
    
    // For charts with revenue/commissions arrays
    if (Array.isArray(data.value.revenue) || Array.isArray(data.value.commissions)) {
      const revenueValid = Array.isArray(data.value.revenue) ? 
        data.value.revenue.some((v: any) => typeof v === 'number' && isFinite(v)) : true
      const commissionsValid = Array.isArray(data.value.commissions) ? 
        data.value.commissions.some((v: any) => typeof v === 'number' && isFinite(v)) : true
      return revenueValid && commissionsValid
    }
    
    // For donut charts with verified/pending
    if (typeof data.value.verified === 'number' || typeof data.value.pending === 'number') {
      return (data.value.verified || 0) + (data.value.pending || 0) > 0
    }
    
    // For charts with barData/lineData
    if (Array.isArray(data.value.barData) || Array.isArray(data.value.lineData)) {
      const barValid = Array.isArray(data.value.barData) ? 
        data.value.barData.some((v: any) => typeof v === 'number' && isFinite(v)) : true
      const lineValid = Array.isArray(data.value.lineData) ? 
        data.value.lineData.some((v: any) => typeof v === 'number' && isFinite(v)) : true
      return barValid && lineValid
    }
    
    // For weekly data
    if (Array.isArray(data.value.weeklyData)) {
      return data.value.weeklyData.some((v: any) => typeof v === 'number' && isFinite(v))
    }
    
    // Default validation - check if data object has any numeric properties
    if (typeof data.value === 'object') {
      return Object.values(data.value).some(v => 
        typeof v === 'number' && isFinite(v)
      )
    }
    
    return false
  })

  // Safe series builder for different chart types
  const buildSafeSeries = (rawData: any, seriesType: 'single' | 'multiple' | 'donut' = 'single') => {
    if (!isValidData.value) {
      // Return minimal valid series to prevent ApexCharts errors
      switch (seriesType) {
        case 'donut':
          return [1, 0] // Minimal donut data
        case 'multiple':
          return [{ name: 'Data', data: [0] }]
        case 'single':
        default:
          return [{ data: [0] }]
      }
    }
    
    // Return actual data - let component handle specific formatting
    return rawData
  }

  // Container dimension validation
  const hasValidDimensions = computed(() => {
    if (!containerRef.value) return false
    const rect = containerRef.value.getBoundingClientRect()
    return rect.width >= minWidth && rect.height >= minHeight
  })

  // Whether chart should render
  const shouldRender = computed(() =>
    !loading.value && isChartReady.value && isValidData.value && hasValidDimensions.value
  )

  // Container style with forced dimensions
  const containerStyle = computed(() => ({
    minWidth: `${minWidth}px`,
    minHeight: `${minHeight}px`,
    width: '100%',
    position: 'relative' as const
  }))

  // Safe series data with fallbacks
  const safeSeries = computed(() => {
    if (!isValidData.value) {
      return [{ name: 'No Data', data: [0] }]
    }

    // Return original data if valid
    return data.value.series || data.value.chartData || [{ data: [0] }]
  })

  // Safe chart options with dimension enforcement
  const safeChartOptions = computed(() => {
    const baseOptions = {
      chart: {
        width: '100%',
        height: '100%',
        parentHeightOffset: 0,
        toolbar: { show: false },
        animations: {
          enabled: true,
          easing: 'easeinout',
          speed: 800,
        },
      },
      responsive: [
        {
          breakpoint: 1200,
          options: {
            chart: { height: Math.max(minHeight, 300) },
          },
        },
        {
          breakpoint: 768,
          options: {
            chart: { height: Math.max(minHeight, 250) },
          },
        },
        {
          breakpoint: 480,
          options: {
            chart: { height: Math.max(minHeight, 200) },
          },
        },
      ],
    }

    return baseOptions
  })

  return {
    isChartReady,
    isValidData,
    hasValidDimensions,
    shouldRender,
    containerRef,
    containerStyle,
    buildSafeSeries,
    safeSeries,
    safeChartOptions,
  }
}
