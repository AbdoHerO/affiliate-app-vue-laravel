/**
 * Get the full URL for an image
 * Handles both relative and absolute URLs
 */
export function getImageUrl(imagePath: string | null | undefined): string {
  if (!imagePath) {
    return '/images/avatars/avatar-1.png'
  }

  // Clean up escaped slashes from JSON
  const cleanPath = imagePath.replace(/\\/g, '')

  // If it's already a full URL, return as is
  if (cleanPath.startsWith('http://') || cleanPath.startsWith('https://')) {
    return cleanPath
  }

  // If it's a relative path starting with /storage, prepend the backend URL
  if (cleanPath.startsWith('/storage/')) {
    const backendUrl = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000'
    // Remove /api from the end if it exists since we want the base server URL
    const baseUrl = backendUrl.replace('/api', '')
    const fullUrl = `${baseUrl}${cleanPath}`
    // console.log('Image URL conversion:', imagePath, '→', fullUrl)
    return fullUrl
  }

  // For other relative paths, return as is (like default avatars)
  return cleanPath
}

/**
 * Get avatar URL with fallback to default
 */
export function getAvatarUrl(imagePath: string | null | undefined): string {
  console.log('🖼️ getAvatarUrl input:', imagePath)
  
  if (!imagePath) {
    console.log('🖼️ No image path, returning default avatar')
    return '/images/avatars/avatar-1.png'
  }
  
  const url = getImageUrl(imagePath)
  console.log('🖼️ getAvatarUrl output:', url)
  
  return url
}
