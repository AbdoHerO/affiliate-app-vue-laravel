/**
 * Report Data Sanitization Utilities
 * Ensures all data passed to charts and tables is safe and valid
 */

export interface SanitizedNumber {
  value: number
  isValid: boolean
  original: any
}

export interface SanitizedChartData {
  labels: string[]
  datasets: Array<{
    label: string
    data: number[]
    borderColor?: string
    backgroundColor?: string
    fill?: boolean
  }>
  isEmpty: boolean
}

export interface SanitizedKPI {
  value: number
  delta: number | null
  currency?: string
  unit?: string
  isValid: boolean
}

/**
 * Safely convert any value to a number, with fallback
 */
export function safeNumber(value: any, fallback: number = 0): SanitizedNumber {
  if (value === null || value === undefined) {
    return { value: fallback, isValid: false, original: value }
  }

  const num = Number(value)
  
  if (isNaN(num) || !isFinite(num)) {
    return { value: fallback, isValid: false, original: value }
  }

  return { value: num, isValid: true, original: value }
}

/**
 * Safely convert array of values to numbers
 */
export function safeNumberArray(values: any[], fallback: number = 0): number[] {
  if (!Array.isArray(values)) {
    return [fallback]
  }

  const sanitized = values.map(v => safeNumber(v, fallback).value)
  
  // If all values are fallback, return single fallback to avoid empty charts
  if (sanitized.every(v => v === fallback) && sanitized.length > 1) {
    return [fallback]
  }

  return sanitized.length > 0 ? sanitized : [fallback]
}

/**
 * Sanitize chart data structure
 */
export function sanitizeChartData(data: any): SanitizedChartData {
  if (!data || typeof data !== 'object') {
    return {
      labels: ['No Data'],
      datasets: [{ label: 'No Data', data: [0] }],
      isEmpty: true,
    }
  }

  // Handle different chart data formats
  let labels: string[] = []
  let datasets: any[] = []

  // Format 1: { labels: [], datasets: [] }
  if (data.labels && data.datasets) {
    labels = Array.isArray(data.labels) ? data.labels.map(String) : ['No Data']
    datasets = Array.isArray(data.datasets) ? data.datasets : []
  }
  // Format 2: { labels: [], data: [] } (single series)
  else if (data.labels && data.data) {
    labels = Array.isArray(data.labels) ? data.labels.map(String) : ['No Data']
    datasets = [{
      label: data.label || 'Data',
      data: safeNumberArray(data.data),
      borderColor: data.borderColor,
      backgroundColor: data.backgroundColor,
      fill: data.fill,
    }]
  }
  // Format 3: Direct array of numbers
  else if (Array.isArray(data)) {
    const safeData = safeNumberArray(data)
    labels = safeData.map((_, index) => `Item ${index + 1}`)
    datasets = [{ label: 'Data', data: safeData }]
  }
  // Fallback
  else {
    labels = ['No Data']
    datasets = [{ label: 'No Data', data: [0] }]
  }

  // Sanitize datasets
  const sanitizedDatasets = datasets.map(dataset => ({
    label: String(dataset.label || 'Data'),
    data: safeNumberArray(dataset.data || []),
    borderColor: dataset.borderColor,
    backgroundColor: dataset.backgroundColor,
    fill: dataset.fill,
  }))

  // Check if data is empty (all zeros or no valid data)
  const hasValidData = sanitizedDatasets.some(dataset => 
    dataset.data.some(value => value !== 0)
  )

  return {
    labels,
    datasets: sanitizedDatasets,
    isEmpty: !hasValidData,
  }
}

/**
 * Sanitize KPI data with delta calculation
 */
export function sanitizeKPI(data: any): SanitizedKPI {
  if (!data || typeof data !== 'object') {
    return {
      value: 0,
      delta: null,
      isValid: false,
    }
  }

  const value = safeNumber(data.value || data.amount || data.total)
  const delta = data.delta !== undefined ? safeNumber(data.delta).value : null

  return {
    value: value.value,
    delta: delta,
    currency: data.currency,
    unit: data.unit,
    isValid: value.isValid,
  }
}

/**
 * Sanitize table data array
 */
export function sanitizeTableData(data: any[]): any[] {
  if (!Array.isArray(data)) {
    return []
  }

  return data.map(row => {
    if (!row || typeof row !== 'object') {
      return {}
    }

    const sanitizedRow: any = {}
    
    for (const [key, value] of Object.entries(row)) {
      // Sanitize numeric fields
      if (typeof value === 'number' || (typeof value === 'string' && !isNaN(Number(value)))) {
        sanitizedRow[key] = safeNumber(value).value
      }
      // Keep strings and other types as-is
      else {
        sanitizedRow[key] = value
      }
    }

    return sanitizedRow
  })
}

/**
 * Sanitize pagination data
 */
export function sanitizePagination(pagination: any) {
  if (!pagination || typeof pagination !== 'object') {
    return {
      current_page: 1,
      per_page: 15,
      total: 0,
      last_page: 1,
    }
  }

  return {
    current_page: Math.max(1, safeNumber(pagination.current_page, 1).value),
    per_page: Math.max(1, safeNumber(pagination.per_page, 15).value),
    total: Math.max(0, safeNumber(pagination.total, 0).value),
    last_page: Math.max(1, safeNumber(pagination.last_page, 1).value),
  }
}

/**
 * Comprehensive report data sanitizer
 */
export function sanitizeReportData(reportData: any) {
  if (!reportData || typeof reportData !== 'object') {
    return {
      summary: {},
      charts: {},
      tables: {},
      isEmpty: true,
    }
  }

  const sanitized: any = {
    isEmpty: false,
  }

  // Sanitize summary/KPI data
  if (reportData.summary) {
    sanitized.summary = {}
    for (const [key, value] of Object.entries(reportData.summary)) {
      sanitized.summary[key] = sanitizeKPI(value)
    }
  }

  // Sanitize chart data
  if (reportData.charts) {
    sanitized.charts = {}
    for (const [key, value] of Object.entries(reportData.charts)) {
      sanitized.charts[key] = sanitizeChartData(value)
    }
  }

  // Sanitize table data
  if (reportData.tables) {
    sanitized.tables = {}
    for (const [key, value] of Object.entries(reportData.tables)) {
      if (Array.isArray(value)) {
        sanitized.tables[key] = sanitizeTableData(value)
      } else if (value && typeof value === 'object' && value.data) {
        sanitized.tables[key] = {
          ...value,
          data: sanitizeTableData(value.data),
          pagination: sanitizePagination(value.pagination),
        }
      }
    }
  }

  return sanitized
}

/**
 * Format number for display with proper locale and currency
 */
export function formatDisplayNumber(
  value: number, 
  options: {
    currency?: string
    unit?: string
    decimals?: number
    locale?: string
  } = {}
): string {
  const {
    currency,
    unit,
    decimals = 0,
    locale = 'en-US',
  } = options

  const safeValue = safeNumber(value).value

  if (currency) {
    if (currency === 'MAD' || currency === 'DH') {
      return new Intl.NumberFormat('fr-MA', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
      }).format(safeValue) + ' DH'
    }
    return new Intl.NumberFormat(locale, {
      style: 'currency',
      currency: currency,
      minimumFractionDigits: decimals,
      maximumFractionDigits: decimals,
    }).format(safeValue)
  }

  const formatted = new Intl.NumberFormat(locale, {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals,
  }).format(safeValue)

  return unit ? `${formatted} ${unit}` : formatted
}

/**
 * Get trend icon and color based on delta value
 */
export function getTrendDisplay(delta: number | null) {
  if (delta === null || delta === undefined) {
    return {
      icon: 'tabler-minus',
      color: 'secondary',
      text: 'N/A',
    }
  }

  const safeDelta = safeNumber(delta).value

  if (safeDelta > 0) {
    return {
      icon: 'tabler-trending-up',
      color: 'success',
      text: `+${Math.abs(safeDelta).toFixed(1)}%`,
    }
  } else if (safeDelta < 0) {
    return {
      icon: 'tabler-trending-down',
      color: 'error',
      text: `-${Math.abs(safeDelta).toFixed(1)}%`,
    }
  } else {
    return {
      icon: 'tabler-minus',
      color: 'secondary',
      text: '0%',
    }
  }
}

/**
 * Sanitize data specifically for SalesAreaChart component
 */
export function sanitizeAreaChartData(data: any, title: string, subtitle: string, value: any, growth: any, color = 'success'): any {
  return {
    title: String(title || ''),
    subtitle: String(subtitle || ''),
    value: String(value || '0'),
    growth: String(growth || '+0%'),
    chartData: Array.isArray(data) ? data.map(safeNumber).map(n => n.value) : [0],
    color: String(color || 'success')
  }
}

/**
 * Sanitize data specifically for SessionAnalyticsDonut component
 */
export function sanitizeDonutChartData(verified: any, pending: any, centerLabel: string): any {
  const verifiedNum = safeNumber(verified).value
  const pendingNum = safeNumber(pending).value
  const total = verifiedNum + pendingNum

  return {
    verified: verifiedNum,
    pending: pendingNum,
    centerMetric: total > 0 ? Math.round((verifiedNum / total) * 100) : 0,
    centerLabel: String(centerLabel || 'Rate')
  }
}

/**
 * Sanitize data specifically for ProfitLineChart component
 * (Same format as SalesAreaChart)
 */
export function sanitizeProfitLineChartData(data: any, title: string, subtitle: string, value: any, growth: any, color = 'info'): any {
  return sanitizeAreaChartData(data, title, subtitle, value, growth, color)
}
