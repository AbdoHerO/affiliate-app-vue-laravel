import { useI18n } from 'vue-i18n';

export type FieldErrors = Record<string, string[]>;

export type NormalizedError = {
  status: number;
  message: string;
  errors?: FieldErrors;
  raw?: unknown;
};

// Helper function to get translated error message
export function getTranslatedErrorMessage(key: string, status?: number): string {
  // Try to get i18n instance
  try {
    const { t } = useI18n();
    if (status && key === 'error_server_error') {
      return t(key, { status });
    }
    return t(key);
  } catch {
    // Fallback to English if i18n is not available
    const fallbacks: Record<string, string> = {
      'error_validation_failed': 'Validation failed',
      'error_conflict': 'Conflict occurred',
      'error_authentication_required': 'Authentication required',
      'error_access_forbidden': 'Access forbidden',
      'error_resource_not_found': 'Resource not found',
      'error_server_error': `Server error (${status || 'unknown'})`,
      'error_generic': 'An error occurred',
    };
    return fallbacks[key] || 'An error occurred';
  }
}

export function normalizePayload(
  payload: any,
  status = 0,
  statusText = 'Error'
): NormalizedError {
  const fieldErrors: FieldErrors | undefined = payload?.errors;
  let message = payload?.message;

  // If no message from server, use field errors or generate appropriate error message
  if (!message) {
    if (fieldErrors && Object.keys(fieldErrors).length > 0) {
      message = Object.values(fieldErrors).flat().join(' | ');
    } else {
      // Use translation key for status-based errors
      const errorKey = status === 422 ? 'error_validation_failed'
        : status === 409 ? 'error_conflict'
        : status === 401 ? 'error_authentication_required'
        : status === 403 ? 'error_access_forbidden'
        : status === 404 ? 'error_resource_not_found'
        : status >= 500 ? 'error_server_error'
        : 'error_generic';

      message = getTranslatedErrorMessage(errorKey, status);
    }
  }

  return { status, message, errors: fieldErrors, raw: payload };
}

export async function normalizeFromResponse(res: Response): Promise<NormalizedError> {
  const ct = res.headers.get('content-type') || '';
  let body: any = null;
  
  try {
    if (res.status === 204) {
      // No Content - don't try to parse JSON
      body = null;
    } else if (ct.includes('application/json')) {
      body = await res.json();
    } else {
      body = await res.text();
    }
  } catch (parseError) {
    // Ignore parse errors - body will remain null
    console.warn('Failed to parse response body:', parseError);
  }
  
  return normalizePayload(body, res.status, res.statusText);
}

export function toUserMessage(err: NormalizedError): string {
  if (err.errors && Object.keys(err.errors).length) {
    return Object.values(err.errors).flat().join('\n');
  }
  return err.message || getTranslatedErrorMessage('error_generic');
}
