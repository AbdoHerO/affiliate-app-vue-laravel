# Fix "Return value must be of type array" Errors in AffiliateDashboardController

## Problem Description

The `AffiliateDashboardController.php` file has multiple methods with `array` return type declarations that are returning Laravel Collections instead of arrays, causing **"Return value must be of type array"** 500 errors.

## Root Cause

Methods using `->map()` on Eloquent Collections return Collection objects, but the method signatures declare `array` return types. Laravel Collections need to be converted to arrays using `->toArray()`.

## Required Fixes

### 1. Fix Collection Returns in Table Methods

**Methods that need fixing:**
- `getMyLeads()` - line ~718
- `getMyOrders()` - line ~744  
- `getMyCommissions()` - line ~768

**Pattern to fix:**
```php
// BEFORE (returns Collection):
->map(function ($item) {
    return [...];
});

// AFTER (returns array):
->map(function ($item) {
    return [...];
})
->toArray();
```

### 2. Fix Collection Returns in Stats Methods

**Methods that might need fixing:**
- `getReferralStats()` - check `$topProducts` and `$recentClicks` variables
- Any method returning Collections from `->map()` operations

### 3. Specific Locations to Check

1. **Line ~343**: `$topProducts` in `getReferralStats()`
2. **Line ~362**: `$recentClicks` in `getReferralStats()`  
3. **Line ~485**: `$topProducts` in `getOrderStats()`
4. **Line ~716**: Return statement in `getMyLeads()`
5. **Line ~742**: Return statement in `getMyOrders()`
6. **Line ~766**: Return statement in `getMyCommissions()`

## Solution Pattern

For each method with `array` return type:

1. **Find `->map()` calls** that create Collections
2. **Add `->toArray()`** after the `->map()` chain
3. **Ensure return statements** return arrays, not Collections

## Example Fix

```php
// BEFORE:
private function getMyLeads(User $user, array $filters): array
{
    $leads = ReferralAttribution::where('referrer_affiliate_id', $affiliateProfile?->id)
        ->with('newUser:id,nom_complet,email,created_at')
        ->orderBy('created_at', 'desc')
        ->limit($filters['per_page'])
        ->get()
        ->map(function ($attribution) {
            return [
                'id' => $attribution->id,
                'name' => $newUser->nom_complet ?? 'Unknown',
                // ... other fields
            ];
        });

    return $leads; // ❌ Returns Collection
}

// AFTER:
private function getMyLeads(User $user, array $filters): array
{
    $leads = ReferralAttribution::where('referrer_affiliate_id', $affiliateProfile?->id)
        ->with('newUser:id,nom_complet,email,created_at')
        ->orderBy('created_at', 'desc')
        ->limit($filters['per_page'])
        ->get()
        ->map(function ($attribution) {
            return [
                'id' => $attribution->id,
                'name' => $newUser->nom_complet ?? 'Unknown',
                // ... other fields
            ];
        })
        ->toArray(); // ✅ Convert to array

    return $leads; // ✅ Returns array
}
```

## Testing

After fixes:
1. Clear Laravel cache: `php artisan cache:clear`
2. Test both dashboards: `/admin/dashboard` and `/affiliate/dashboard`
3. Check browser console for 500 errors
4. Verify all KPIs, charts, and tables load correctly

## Expected Outcome

- ✅ No more "Return value must be of type array" errors
- ✅ All dashboard APIs return proper JSON arrays
- ✅ Frontend receives correctly formatted data
- ✅ All dashboard features work without 500 errors

## Files to Modify

- `app/Http/Controllers/Api/AffiliateDashboardController.php`

## Detailed Fix Instructions

### Method 1: `getReferralStats()` around line 298
```php
// Find these variables and ensure they return arrays:
$topProducts = // ... ->map(...);
// Should be: ->map(...)->toArray();

$recentClicks = // ... ->map(...);
// Should be: ->map(...)->toArray();
```

### Method 2: `getMyLeads()` around line 694
```php
// The return statement should return an array:
return $leads; // where $leads comes from ->map()->toArray()
```

### Method 3: `getMyOrders()` around line 721
```php
// The return statement should return an array:
return $orders; // where $orders comes from ->map()->toArray()
```

### Method 4: `getMyCommissions()` around line 747
```php
// The return statement should return an array:
return $commissions; // where $commissions comes from ->map()->toArray()
```

### Method 5: `getReferralClicks()` around line 771
```php
// Already fixed, but verify:
return $clicks; // where $clicks comes from ->map()->toArray()
```

## Quick Search Pattern

Search for these patterns in the file:
1. `->map(function` - Look for map operations
2. `): array` - Find methods that should return arrays
3. `return $` - Check what's being returned

## Verification Commands

```bash
# Clear cache
php artisan cache:clear

# Test endpoints
curl http://localhost:8000/api/affiliate/dashboard/stats
curl http://localhost:8000/api/affiliate/dashboard/tables?type=my_leads
```

## Priority

**HIGH** - This is blocking dashboard functionality with 500 errors.
