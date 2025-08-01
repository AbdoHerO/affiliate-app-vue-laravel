export type FieldErrors = Record<string, string[]>;

export type NormalizedError = {
  status: number;
  message: string;
  errors?: FieldErrors;
  raw?: unknown;
};

export function normalizePayload(
  payload: any,
  status = 0,
  statusText = 'Error'
): NormalizedError {
  const fieldErrors: FieldErrors | undefined = payload?.errors;
  let message =
    payload?.message ||
    (fieldErrors ? Object.values(fieldErrors).flat().join(' | ') : '') ||
    (status === 422 ? 'Validation failed'
     : status === 409 ? 'Conflict'
     : status === 401 ? 'Authentication required'
     : status === 403 ? 'Access forbidden'
     : status === 404 ? 'Resource not found'
     : status >= 500 ? `Server error (${status})`
     : statusText || 'Error');

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
  return err.message || 'Error';
}
