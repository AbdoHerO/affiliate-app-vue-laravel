# Route Navigation Fix

## Issue
Getting error: `Uncaught (in promise) Error: No match for {"name":"affiliate-orders","params":{}}`

## Root Cause
The routes are properly defined in `typed-router.d.ts` but the browser cache or Vue router cache may be causing issues.

## Solution

### 1. Clear Browser Cache
- **Hard Refresh**: Press `Ctrl+Shift+R` (Windows/Linux) or `Cmd+Shift+R` (Mac)
- **Clear Browser Cache**: Go to Developer Tools > Application > Storage > Clear Storage
- **Incognito Mode**: Test in a new incognito/private window

### 2. Restart Development Server
```bash
# Kill any running processes
npm run dev

# Or if using Laravel Sail
./vendor/bin/sail npm run dev
```

### 3. Verify Routes are Generated
Check that routes exist in `typed-router.d.ts`:
```typescript
'affiliate-orders': RouteRecordInfo<'affiliate-orders', '/affiliate/orders', Record<never, never>, Record<never, never>>,
'affiliate-orders-id': RouteRecordInfo<'affiliate-orders-id', '/affiliate/orders/:id', { id: ParamValue<true> }, { id: ParamValue<false> }>,
'affiliate-payments': RouteRecordInfo<'affiliate-payments', '/affiliate/payments', Record<never, never>, Record<never, never>>,
'affiliate-tickets': RouteRecordInfo<'affiliate-tickets', '/affiliate/tickets', Record<never, never>, Record<never, never>>,
'affiliate-tickets-id': RouteRecordInfo<'affiliate-tickets-id', '/affiliate/tickets/:id', { id: ParamValue<true> }, { id: ParamValue<false> }>,
```

### 4. Alternative Navigation Method
If the issue persists, use direct path navigation instead of route names:

```typescript
// In useNavigation.ts - temporary fix
const affiliateNavigation = computed(() => [
  {
    title: 'Dashboard',
    to: '/affiliate/dashboard', // Use path instead of route name
    icon: 'tabler-dashboard',
  },
  {
    title: 'Mes Commandes',
    to: '/affiliate/orders', // Use path instead of route name
    icon: 'tabler-shopping-cart',
  },
  {
    title: 'Mes Paiements',
    to: '/affiliate/payments', // Use path instead of route name
    icon: 'tabler-credit-card',
  },
  {
    title: 'Support',
    to: '/affiliate/tickets', // Use path instead of route name
    icon: 'tabler-help',
  },
])
```

### 5. Force Route Regeneration
```bash
# Delete node_modules and reinstall
rm -rf node_modules
npm install

# Rebuild the project
npm run build
npm run dev
```

## Verification Steps

1. **Check Route Generation**: Verify routes exist in `typed-router.d.ts`
2. **Test Navigation**: Try navigating to `/affiliate/orders` directly in browser
3. **Check Console**: Look for any JavaScript errors in browser console
4. **Test Other Routes**: Verify other affiliate routes work (`/affiliate/payments`, `/affiliate/tickets`)

## Expected Result
After applying these fixes, navigation should work correctly:
- Clicking "Mes Commandes" should navigate to `/affiliate/orders`
- Clicking "Mes Paiements" should navigate to `/affiliate/payments`  
- Clicking "Support" should navigate to `/affiliate/tickets`

## Status
✅ Routes are properly defined in typed-router.d.ts
✅ Page files exist in correct locations
✅ Build completed successfully
🔄 Browser cache clearing needed
