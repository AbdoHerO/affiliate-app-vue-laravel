/**
 * CSV Export Utilities
 * Provides functionality to export table data to CSV format
 */

export interface CSVColumn {
  key: string
  title: string
  formatter?: (value: any) => string
}

export interface CSVExportOptions {
  filename?: string
  columns?: CSVColumn[]
  includeHeaders?: boolean
  delimiter?: string
  dateFormat?: string
  numberFormat?: {
    locale?: string
    currency?: string
    decimals?: number
  }
}

/**
 * Convert array of objects to CSV string
 */
export function arrayToCSV(
  data: any[],
  options: CSVExportOptions = {}
): string {
  const {
    columns,
    includeHeaders = true,
    delimiter = ',',
    dateFormat = 'YYYY-MM-DD HH:mm:ss',
  } = options

  if (!data || data.length === 0) {
    return ''
  }

  // Auto-detect columns if not provided
  const csvColumns = columns || Object.keys(data[0]).map(key => ({
    key,
    title: key.charAt(0).toUpperCase() + key.slice(1).replace(/_/g, ' '),
  }))

  const rows: string[] = []

  // Add headers
  if (includeHeaders) {
    const headers = csvColumns.map(col => escapeCSVValue(col.title))
    rows.push(headers.join(delimiter))
  }

  // Add data rows
  data.forEach(row => {
    const values = csvColumns.map(col => {
      const value = row[col.key]
      
      if (col.formatter) {
        return escapeCSVValue(col.formatter(value))
      }
      
      return escapeCSVValue(formatValue(value, options))
    })
    
    rows.push(values.join(delimiter))
  })

  return rows.join('\n')
}

/**
 * Format value for CSV export
 */
function formatValue(value: any, options: CSVExportOptions): string {
  if (value === null || value === undefined) {
    return ''
  }

  // Handle dates
  if (value instanceof Date) {
    return value.toISOString().split('T')[0] + ' ' + value.toTimeString().split(' ')[0]
  }

  // Handle numbers
  if (typeof value === 'number') {
    if (options.numberFormat?.currency) {
      return new Intl.NumberFormat(options.numberFormat.locale || 'en-US', {
        style: 'currency',
        currency: options.numberFormat.currency === 'MAD' ? 'USD' : options.numberFormat.currency,
        minimumFractionDigits: options.numberFormat.decimals || 2,
        maximumFractionDigits: options.numberFormat.decimals || 2,
      }).format(value).replace('$', options.numberFormat.currency === 'MAD' ? 'MAD ' : '$')
    } else {
      return new Intl.NumberFormat(options.numberFormat?.locale || 'en-US', {
        minimumFractionDigits: options.numberFormat?.decimals || 0,
        maximumFractionDigits: options.numberFormat?.decimals || 2,
      }).format(value)
    }
  }

  // Handle booleans
  if (typeof value === 'boolean') {
    return value ? 'Yes' : 'No'
  }

  // Handle objects
  if (typeof value === 'object') {
    return JSON.stringify(value)
  }

  return String(value)
}

/**
 * Escape CSV value (handle commas, quotes, newlines)
 */
function escapeCSVValue(value: string): string {
  if (typeof value !== 'string') {
    value = String(value)
  }

  // If value contains delimiter, quotes, or newlines, wrap in quotes and escape internal quotes
  if (value.includes(',') || value.includes('"') || value.includes('\n') || value.includes('\r')) {
    return '"' + value.replace(/"/g, '""') + '"'
  }

  return value
}

/**
 * Download CSV file
 */
export function downloadCSV(
  data: any[],
  options: CSVExportOptions = {}
): void {
  const {
    filename = 'export.csv',
  } = options

  const csvContent = arrayToCSV(data, options)
  
  // Create blob with BOM for proper UTF-8 encoding in Excel
  const BOM = '\uFEFF'
  const blob = new Blob([BOM + csvContent], { type: 'text/csv;charset=utf-8;' })
  
  // Create download link
  const link = document.createElement('a')
  const url = URL.createObjectURL(blob)
  
  link.setAttribute('href', url)
  link.setAttribute('download', filename.endsWith('.csv') ? filename : `${filename}.csv`)
  link.style.visibility = 'hidden'
  
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  
  // Clean up
  URL.revokeObjectURL(url)
}

/**
 * Export sales orders to CSV
 */
export function exportSalesOrdersCSV(
  orders: any[],
  filename?: string
): void {
  const columns: CSVColumn[] = [
    { key: 'order_ref', title: 'Order Reference' },
    { key: 'date', title: 'Date' },
    { key: 'affiliate_name', title: 'Affiliate' },
    { key: 'customer_name', title: 'Customer' },
    { key: 'status', title: 'Status' },
    { key: 'items_count', title: 'Items Count' },
    { 
      key: 'total', 
      title: 'Total (MAD)',
      formatter: (value) => formatValue(value, { numberFormat: { currency: 'MAD', decimals: 2 } })
    },
    { 
      key: 'commission', 
      title: 'Commission (MAD)',
      formatter: (value) => formatValue(value, { numberFormat: { currency: 'MAD', decimals: 2 } })
    },
  ]

  downloadCSV(orders, {
    filename: filename || `sales-orders-${new Date().toISOString().split('T')[0]}.csv`,
    columns,
  })
}

/**
 * Export affiliate leaderboard to CSV
 */
export function exportAffiliateLeaderboardCSV(
  affiliates: any[],
  filename?: string
): void {
  const columns: CSVColumn[] = [
    { key: 'name', title: 'Affiliate Name' },
    { key: 'email', title: 'Email' },
    { key: 'orders_count', title: 'Orders Count' },
    { 
      key: 'delivered_rate', 
      title: 'Delivered Rate (%)',
      formatter: (value) => `${value}%`
    },
    { 
      key: 'total_sales', 
      title: 'Total Sales (MAD)',
      formatter: (value) => formatValue(value, { numberFormat: { currency: 'MAD', decimals: 0 } })
    },
    { 
      key: 'total_commission', 
      title: 'Total Commission (MAD)',
      formatter: (value) => formatValue(value, { numberFormat: { currency: 'MAD', decimals: 0 } })
    },
    { 
      key: 'total_payouts', 
      title: 'Total Payouts (MAD)',
      formatter: (value) => formatValue(value, { numberFormat: { currency: 'MAD', decimals: 0 } })
    },
    { 
      key: 'avg_order_value', 
      title: 'Average Order Value (MAD)',
      formatter: (value) => formatValue(value, { numberFormat: { currency: 'MAD', decimals: 0 } })
    },
    { 
      key: 'return_rate', 
      title: 'Return Rate (%)',
      formatter: (value) => `${value}%`
    },
  ]

  downloadCSV(affiliates, {
    filename: filename || `affiliate-leaderboard-${new Date().toISOString().split('T')[0]}.csv`,
    columns,
  })
}

/**
 * Export commission ledger to CSV
 */
export function exportCommissionLedgerCSV(
  commissions: any[],
  filename?: string
): void {
  const columns: CSVColumn[] = [
    { key: 'affiliate_name', title: 'Affiliate Name' },
    { key: 'order_ref', title: 'Order Reference' },
    { key: 'product_name', title: 'Product Name' },
    { key: 'type', title: 'Commission Type' },
    { 
      key: 'base_amount', 
      title: 'Base Amount (MAD)',
      formatter: (value) => formatValue(value, { numberFormat: { currency: 'MAD', decimals: 2 } })
    },
    { 
      key: 'rate', 
      title: 'Rate (%)',
      formatter: (value) => `${value}%`
    },
    { 
      key: 'commission', 
      title: 'Commission (MAD)',
      formatter: (value) => formatValue(value, { numberFormat: { currency: 'MAD', decimals: 2 } })
    },
    { key: 'status', title: 'Status' },
    { key: 'rule_code', title: 'Rule Code' },
    { key: 'date', title: 'Date' },
  ]

  downloadCSV(commissions, {
    filename: filename || `commission-ledger-${new Date().toISOString().split('T')[0]}.csv`,
    columns,
  })
}

/**
 * Export top products to CSV
 */
export function exportTopProductsCSV(
  products: any[],
  filename?: string
): void {
  const columns: CSVColumn[] = [
    { key: 'name', title: 'Product Name' },
    { key: 'orders_count', title: 'Orders Count' },
    { key: 'total_quantity', title: 'Total Quantity Sold' },
    { 
      key: 'total_revenue', 
      title: 'Total Revenue (MAD)',
      formatter: (value) => formatValue(value, { numberFormat: { currency: 'MAD', decimals: 0 } })
    },
  ]

  downloadCSV(products, {
    filename: filename || `top-products-${new Date().toISOString().split('T')[0]}.csv`,
    columns,
  })
}

/**
 * Export top affiliates to CSV
 */
export function exportTopAffiliatesCSV(
  affiliates: any[],
  filename?: string
): void {
  const columns: CSVColumn[] = [
    { key: 'name', title: 'Affiliate Name' },
    { key: 'email', title: 'Email' },
    { key: 'orders_count', title: 'Orders Count' },
    { 
      key: 'delivered_rate', 
      title: 'Delivered Rate (%)',
      formatter: (value) => `${value}%`
    },
    { 
      key: 'total_sales', 
      title: 'Total Sales (MAD)',
      formatter: (value) => formatValue(value, { numberFormat: { currency: 'MAD', decimals: 0 } })
    },
    { 
      key: 'total_commission', 
      title: 'Total Commission (MAD)',
      formatter: (value) => formatValue(value, { numberFormat: { currency: 'MAD', decimals: 0 } })
    },
  ]

  downloadCSV(affiliates, {
    filename: filename || `top-affiliates-${new Date().toISOString().split('T')[0]}.csv`,
    columns,
  })
}
