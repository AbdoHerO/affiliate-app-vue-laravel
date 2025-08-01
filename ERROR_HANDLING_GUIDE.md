# Error Handling Implementation Guide

## Overview

This implementation provides a comprehensive error handling system for the Laravel API + Vue 3 (TypeScript, Composition API, Vuetify) project. It normalizes API errors (422, 409, 401, 403, 404, 5xx, network issues) and shows exact message content to users, plus inline field errors for forms.

## Components

### 1. ErrorService (`src/services/ErrorService.ts`)

**Types:**
```typescript
export type FieldErrors = Record<string, string[]>;
export type NormalizedError = {
  status: number;
  message: string;
  errors?: FieldErrors;
  raw?: unknown;
};
```

**Functions:**
- `normalizePayload(payload, status?, statusText?)` - Normalizes any error payload into consistent shape
- `normalizeFromResponse(res: Response)` - Parses Response object and normalizes error
- `toUserMessage(err: NormalizedError)` - Converts normalized error to user-friendly message

### 2. useFormErrors Composable (`src/composables/useFormErrors.ts`)

**API:**
```typescript
const { errors, set, clear, asText } = useFormErrors<typeof form.value>();
```

- `errors` - Reactive object mapping field names to string arrays
- `set(fieldErrors?)` - Populates errors from API response
- `clear()` - Resets all errors
- `asText(fallback?)` - Joins all error messages with newlines

### 3. Updated useApi Composable

**Features:**
- Uses ErrorService for error normalization
- Ensures proper headers (Accept: application/json, Authorization)
- Handles FormData correctly (no forced Content-Type)
- Returns normalized errors in `apiError.value`

## Usage Examples

### In Vue Components

```typescript
import { useFormErrors } from '@/composables/useFormErrors';

// Setup form errors
const { errors: userErrors, set: setUserErrors, clear: clearUserErrors } = 
  useFormErrors<typeof userForm.value>();

// In API call handlers
if (apiError.value) {
  setUserErrors(apiError.value.errors);
  showError(apiError.value.message);
} else {
  clearUserErrors();
  // Success handling
}
```

### In Templates

```vue
<VTextField 
  v-model="userForm.nom_complet" 
  :error-messages="userErrors.nom_complet"
  label="Full Name" 
/>
<VTextField 
  v-model="userForm.email" 
  :error-messages="userErrors.email"
  label="Email" 
/>
```

### For File Uploads

```typescript
import { normalizeFromResponse } from '@/services/ErrorService';

if (!response.ok) {
  const nerr = await normalizeFromResponse(response);
  showError(nerr.message); // e.g., 409 -> "User already has a document of this type"
  return;
}
```

## Error Scenarios Handled

### 422 Validation Errors
**Input:**
```json
{
  "message": "The nom complet field is required. (and 3 more errors)",
  "errors": {
    "nom_complet": ["The nom complet field is required."],
    "email": ["The email has already been taken."],
    "password": ["The password field must be at least 8 characters."],
    "role": ["The role field is required."]
  }
}
```
**Result:** Snackbar shows aggregated message, inline errors under fields.

### 409 Conflict Errors
**Input:**
```json
{ "message": "User already has a document of this type" }
```
**Result:** Snackbar shows exactly this message.

### 401/403 Auth Errors
**Result:** Clear auth/permission error messages.

### 404 Not Found
**Result:** "Resource not found" message.

### 5xx Server Errors
**Result:** "Server error (500)" with status code.

### Network Failures
**Result:** Sensible fallback messages for non-JSON responses.

## Files Updated

1. **Created:**
   - `src/services/ErrorService.ts`
   - `src/composables/useFormErrors.ts`

2. **Modified:**
   - `src/composables/useApi.ts` - Integrated ErrorService
   - `src/pages/admin/users.vue` - Added form error handling
   - `src/pages/admin/roles.vue` - Added form error handling  
   - `src/pages/admin/kyc-documents.vue` - Updated upload error handling

## Testing

To test the error handling:

1. **422 Validation:** Submit forms with invalid data
2. **409 Conflict:** Try uploading duplicate documents
3. **401/403:** Access protected resources without auth
4. **404:** Request non-existent resources
5. **5xx:** Trigger server errors
6. **Network:** Test with network disconnected

## Benefits

- **Consistent Error Handling:** All errors normalized to same shape
- **User-Friendly Messages:** Exact server messages shown to users
- **Inline Validation:** Field-specific errors displayed under inputs
- **Type Safety:** Full TypeScript support
- **Reusable:** Easy to integrate in any component
- **Maintainable:** Centralized error logic
