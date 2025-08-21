# Laravel 11 + Vue 3 + Sanctum: Session Cart Empty After Add - Need Expert Help

## 🚨 CRITICAL ISSUE: Cart Always Empty Despite Successful Add

I have a **Laravel 11 + Vue 3 + Sanctum** affiliate platform where the cart appears empty even after successful add-to-cart operations. Need expert debugging help.

## 📋 CURRENT BEHAVIOR (BROKEN)

1. **Add to cart**: `POST /api/affiliate/cart/add` → **200 OK** ✅
   ```json
   {
     "message": "Produit ajouté au panier",
     "cart_item": {
       "produit_id": "0198c936-5bac-72bf-b72c-ef341fdd3fb6",
       "variante_id": "0198c937-9a9f-7216-8b43-9e2d04093eab",
       "qty": 3,
       "added_at": "2025-08-20T23:57:48.549258Z"
     },
     "success": true
   }
   ```

2. **Fetch cart** (immediately after): `GET /api/affiliate/cart/summary` → **200 OK** but **EMPTY** ❌
   ```json
   {"items_count":0,"total_amount":0,"items":[]}
   ```

## 🔧 CURRENT CONFIGURATION

### Backend (Laravel 11)

**Routes** (`routes/api.php`):
```php
Route::middleware(['auth:sanctum', 'role:affiliate'])->prefix('affiliate')->group(function () {
    Route::prefix('cart')->group(function () {
        Route::post('add', [CartController::class, 'addItem']);
        Route::get('summary', [CartController::class, 'summary']);
    });
});
```

**Cart Storage** (`CartController.php`):
```php
// addItem method
$cart = Session::get('affiliate_cart', []);
$cart[$itemKey] = [...];
Session::put('affiliate_cart', $cart);

// summary method  
$cart = Session::get('affiliate_cart', []);
// Returns empty array
```

**Session Config** (`.env`):
```env
SESSION_DRIVER=database
SESSION_LIFETIME=120
SANCTUM_STATEFUL_DOMAINS=localhost,localhost:8000,127.0.0.1,127.0.0.1:8000,::1
```

**CORS Config** (`config/cors.php`):
```php
'supports_credentials' => true,
'allowed_origins' => [
    'http://localhost:8000',
    'http://127.0.0.1:8000',
],
```

**Sanctum Config** (`config/sanctum.php`):
```php
'guard' => ['web'],
'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', '')),
```

### Frontend (Vue 3 + TypeScript)

**API Client** (`useApi.ts`):
```typescript
export const useApi = createFetch({
  baseUrl: '/api',
  fetchOptions: {
    headers: { Accept: 'application/json' },
    credentials: 'include', // Session cookies
  },
  options: {
    async beforeFetch({ options }) {
      // Add Bearer token
      const token = authStore.token || localStorage.getItem('auth_token')
      if (token) {
        options.headers = { ...options.headers, Authorization: `Bearer ${token}` }
      }
      
      // Add CSRF token
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
      if (csrfToken) {
        options.headers = { ...options.headers, 'X-CSRF-TOKEN': csrfToken }
      }
    }
  }
})
```

**Cart Store** (`stores/affiliate/cart.ts`):
```typescript
const addItem = async (item: { produit_id: string; variante_id?: string; qty: number }) => {
  const { data, error } = await useApi('affiliate/cart/add', {
    method: 'POST',
    body: JSON.stringify(item),
    headers: { 'Content-Type': 'application/json' }
  })
  
  if (!error.value) {
    await fetchCart() // This returns empty!
  }
}

const fetchCart = async () => {
  const { data, error } = await useApi('affiliate/cart/summary', { method: 'GET' })
  // data.value always shows empty cart
}
```

## 🔍 DEBUGGING ATTEMPTS

### 1. Session Logging Added
```php
// In CartController
Log::info('🛒 ADD - Session ID: ' . Session::getId());
Log::info('📋 SUMMARY - Session ID: ' . Session::getId());
```

### 2. Network Analysis
- Both requests return 200 OK
- Session cookies appear to be sent
- No CORS errors in console
- Requests happen within milliseconds of each other

### 3. Session Driver Test
- Database sessions table exists and populated
- Manual session test works in CLI
- `Session::put()` and `Session::get()` work in isolation

## ❓ SPECIFIC QUESTIONS FOR COPILOT

1. **Session Mismatch**: Why would `Session::put()` in addItem and `Session::get()` in summary (same session ID) return different data?

2. **Sanctum + Sessions**: Is there a conflict between Bearer token auth and session-based cart storage?

3. **API vs Web Routes**: Should cart functionality be in `web.php` instead of `api.php` for session persistence?

4. **Middleware Order**: Could middleware order be causing session data loss between requests?

5. **Race Condition**: Could the immediate `fetchCart()` call be happening before session is fully saved?

## 🎯 EXPECTED SOLUTION

Need the cart to:
- ✅ Store items in session during `addItem`
- ✅ Retrieve same items during `summary` 
- ✅ Persist across multiple requests
- ✅ Work with Sanctum authentication

## 📊 ENVIRONMENT

- **Laravel**: 11.x
- **PHP**: 8.2
- **Vue**: 3.x with TypeScript
- **Sanctum**: Latest
- **Session Driver**: Database
- **Server**: Laravel dev server (php artisan serve)
- **Frontend**: Vite dev server

## 🚨 URGENCY

This is blocking the entire cart functionality. Users can add items but never see them in cart, making checkout impossible.

**Please provide a step-by-step solution with code examples.**
