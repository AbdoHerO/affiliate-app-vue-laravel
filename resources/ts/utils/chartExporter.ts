/**
 * Chart Export Utilities
 * Provides functionality to export charts as PNG images
 */

export interface ChartExportOptions {
  filename?: string
  width?: number
  height?: number
  backgroundColor?: string
  scale?: number
  format?: 'png' | 'jpeg' | 'svg'
  quality?: number
}

/**
 * Export ApexCharts chart to PNG
 */
export async function exportApexChart(
  chartRef: any,
  options: ChartExportOptions = {}
): Promise<void> {
  const {
    filename = 'chart.png',
    width = 800,
    height = 400,
    format = 'png',
    quality = 1,
  } = options

  try {
    if (chartRef && chartRef.chart) {
      await chartRef.chart.dataURI({
        width,
        height,
        type: format,
        quality,
      }).then((uri: { imgURI: string }) => {
        downloadImage(uri.imgURI, filename)
      })
    } else {
      throw new Error('Chart reference not found')
    }
  } catch (error) {
    console.error('Error exporting chart:', error)
    throw error
  }
}

/**
 * Export HTML element as image using html2canvas
 */
export async function exportElementAsImage(
  element: HTMLElement,
  options: ChartExportOptions = {}
): Promise<void> {
  const {
    filename = 'chart.png',
    width,
    height,
    backgroundColor = '#ffffff',
    scale = 2,
  } = options

  try {
    // Check if html2canvas is available
    let html2canvas: any

    try {
      // Try to import html2canvas
      const html2canvasModule = await import('html2canvas')
      html2canvas = html2canvasModule.default || html2canvasModule
    } catch (importError) {
      console.warn('html2canvas not available, falling back to alternative method')
      // Fallback: use browser's built-in screenshot API if available
      await fallbackElementExport(element, filename)
      return
    }

    const canvas = await html2canvas(element, {
      width,
      height,
      backgroundColor,
      scale,
      useCORS: true,
      allowTaint: true,
      logging: false,
      onclone: (clonedDoc: Document) => {
        // Ensure fonts are loaded in cloned document
        const clonedElement = clonedDoc.querySelector('[data-export]') as HTMLElement
        if (clonedElement) {
          clonedElement.style.fontFamily = 'inherit'
        }
      },
    })

    // Convert canvas to blob and download
    canvas.toBlob((blob: Blob | null) => {
      if (blob) {
        const url = URL.createObjectURL(blob)
        downloadImage(url, filename)
        URL.revokeObjectURL(url)
      } else {
        throw new Error('Failed to create blob from canvas')
      }
    }, 'image/png', 0.95)
  } catch (error) {
    console.error('Error exporting element as image:', error)
    throw new Error(`Failed to export image: ${error instanceof Error ? error.message : 'Unknown error'}`)
  }
}

/**
 * Fallback export method when html2canvas is not available
 */
async function fallbackElementExport(element: HTMLElement, filename: string): Promise<void> {
  try {
    // Try using the experimental getDisplayMedia API
    if ('getDisplayMedia' in navigator.mediaDevices) {
      console.info('Using screen capture API as fallback')
      // This would require user interaction to select the area
      throw new Error('Screen capture requires user interaction - please install html2canvas for automatic export')
    }

    // Final fallback: create a simple text representation
    const textContent = element.textContent || element.innerText || 'Chart content'
    const blob = new Blob([`Chart Export - ${new Date().toISOString()}\n\n${textContent}`], {
      type: 'text/plain'
    })
    const url = URL.createObjectURL(blob)
    downloadImage(url, filename.replace('.png', '.txt'))
    URL.revokeObjectURL(url)

    console.warn('Exported as text file - install html2canvas for image export')
  } catch (error) {
    throw new Error('Export failed: html2canvas package is required for image export. Please run: npm install html2canvas')
  }
}

/**
 * Export chart container by selector
 */
export async function exportChartBySelector(
  selector: string,
  options: ChartExportOptions = {}
): Promise<void> {
  const element = document.querySelector(selector) as HTMLElement
  
  if (!element) {
    throw new Error(`Element with selector "${selector}" not found`)
  }

  return exportElementAsImage(element, options)
}

/**
 * Download image from data URI or blob URL
 */
function downloadImage(dataUri: string, filename: string): void {
  const link = document.createElement('a')
  link.href = dataUri
  link.download = filename.endsWith('.png') ? filename : `${filename}.png`
  link.style.visibility = 'hidden'
  
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
}

/**
 * Export multiple charts as a combined image
 */
export async function exportMultipleCharts(
  chartSelectors: string[],
  options: ChartExportOptions & {
    layout?: 'horizontal' | 'vertical' | 'grid'
    spacing?: number
    title?: string
  } = {}
): Promise<void> {
  const {
    filename = 'charts.png',
    layout = 'vertical',
    spacing = 20,
    backgroundColor = '#ffffff',
    title,
  } = options

  try {
    const elements = chartSelectors
      .map(selector => document.querySelector(selector) as HTMLElement)
      .filter(Boolean)

    if (elements.length === 0) {
      throw new Error('No chart elements found')
    }

    // Create a container for all charts
    const container = document.createElement('div')
    container.style.position = 'absolute'
    container.style.top = '-9999px'
    container.style.left = '-9999px'
    container.style.backgroundColor = backgroundColor
    container.style.padding = '20px'
    
    if (layout === 'horizontal') {
      container.style.display = 'flex'
      container.style.flexDirection = 'row'
      container.style.gap = `${spacing}px`
    } else if (layout === 'vertical') {
      container.style.display = 'flex'
      container.style.flexDirection = 'column'
      container.style.gap = `${spacing}px`
    } else {
      container.style.display = 'grid'
      container.style.gridTemplateColumns = 'repeat(2, 1fr)'
      container.style.gap = `${spacing}px`
    }

    // Add title if provided
    if (title) {
      const titleElement = document.createElement('h2')
      titleElement.textContent = title
      titleElement.style.margin = '0 0 20px 0'
      titleElement.style.textAlign = 'center'
      titleElement.style.fontSize = '24px'
      titleElement.style.fontWeight = 'bold'
      container.appendChild(titleElement)
    }

    // Clone and append chart elements
    elements.forEach(element => {
      const clone = element.cloneNode(true) as HTMLElement
      clone.style.margin = '0'
      container.appendChild(clone)
    })

    document.body.appendChild(container)

    // Export the container
    await exportElementAsImage(container, {
      ...options,
      filename,
    })

    // Clean up
    document.body.removeChild(container)
  } catch (error) {
    console.error('Error exporting multiple charts:', error)
    throw error
  }
}

/**
 * Export dashboard summary as image
 */
export async function exportDashboardSummary(
  options: ChartExportOptions & {
    includeKPIs?: boolean
    includeCharts?: boolean
    title?: string
  } = {}
): Promise<void> {
  const {
    filename = 'dashboard-summary.png',
    includeKPIs = true,
    includeCharts = true,
    title = 'Dashboard Summary',
  } = options

  const selectors: string[] = []

  if (includeKPIs) {
    // Add KPI cards selector (adjust based on your component structure)
    const kpiContainer = document.querySelector('[data-export="kpi-cards"]')
    if (kpiContainer) {
      selectors.push('[data-export="kpi-cards"]')
    }
  }

  if (includeCharts) {
    // Add chart selectors (adjust based on your component structure)
    const chartContainers = document.querySelectorAll('[data-export="chart"]')
    chartContainers.forEach((_, index) => {
      selectors.push(`[data-export="chart"]:nth-child(${index + 1})`)
    })
  }

  if (selectors.length === 0) {
    throw new Error('No exportable elements found. Make sure to add data-export attributes to your components.')
  }

  return exportMultipleCharts(selectors, {
    ...options,
    filename,
    title,
    layout: 'vertical',
  })
}

/**
 * Prepare chart for export by adding data attributes
 */
export function prepareChartForExport(
  element: HTMLElement,
  exportType: 'kpi-cards' | 'chart' | 'table'
): void {
  element.setAttribute('data-export', exportType)
}

/**
 * Export chart with loading state handling
 */
export async function exportChartWithLoading(
  chartRef: any,
  options: ChartExportOptions = {},
  onStart?: () => void,
  onComplete?: () => void,
  onError?: (error: Error) => void
): Promise<void> {
  try {
    onStart?.()
    await exportApexChart(chartRef, options)
    onComplete?.()
  } catch (error) {
    const exportError = error instanceof Error ? error : new Error('Export failed')
    onError?.(exportError)
    throw exportError
  }
}

/**
 * Check if export functionality is available
 */
export function isExportAvailable(): { available: boolean; reason?: string } {
  // Check if we're in a browser environment
  if (typeof window === 'undefined') {
    return { available: false, reason: 'Not in browser environment' }
  }

  // Check if required APIs are available
  if (!document.createElement || !URL.createObjectURL) {
    return { available: false, reason: 'Required browser APIs not available' }
  }

  return { available: true }
}

/**
 * Get user-friendly error message for export failures
 */
export function getExportErrorMessage(error: Error): string {
  const message = error.message.toLowerCase()

  if (message.includes('html2canvas')) {
    return 'Image export requires the html2canvas library. Please contact your administrator.'
  }

  if (message.includes('network') || message.includes('cors')) {
    return 'Export failed due to network restrictions. Please try again.'
  }

  if (message.includes('permission') || message.includes('security')) {
    return 'Export failed due to security restrictions. Please check your browser settings.'
  }

  if (message.includes('memory') || message.includes('size')) {
    return 'Export failed due to image size. Try reducing the export dimensions.'
  }

  return 'Export failed. Please try again or contact support if the problem persists.'
}

/**
 * Batch export multiple chart types
 */
export async function batchExportCharts(
  exports: Array<{
    type: 'apex' | 'element' | 'selector'
    target: any | HTMLElement | string
    filename: string
    options?: ChartExportOptions
  }>,
  onProgress?: (current: number, total: number) => void
): Promise<void> {
  for (let i = 0; i < exports.length; i++) {
    const exportItem = exports[i]
    
    try {
      onProgress?.(i + 1, exports.length)
      
      switch (exportItem.type) {
        case 'apex':
          await exportApexChart(exportItem.target, {
            filename: exportItem.filename,
            ...exportItem.options,
          })
          break
        case 'element':
          await exportElementAsImage(exportItem.target, {
            filename: exportItem.filename,
            ...exportItem.options,
          })
          break
        case 'selector':
          await exportChartBySelector(exportItem.target, {
            filename: exportItem.filename,
            ...exportItem.options,
          })
          break
      }
      
      // Add small delay between exports to avoid overwhelming the browser
      await new Promise(resolve => setTimeout(resolve, 500))
    } catch (error) {
      console.error(`Error exporting ${exportItem.filename}:`, error)
      // Continue with other exports even if one fails
    }
  }
}
