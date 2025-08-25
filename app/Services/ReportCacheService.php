<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ReportCacheService
{
    /**
     * Cache TTL in seconds
     */
    const DEFAULT_TTL = 300; // 5 minutes
    const LONG_TTL = 3600; // 1 hour
    const SHORT_TTL = 60; // 1 minute

    /**
     * Cache key prefixes
     */
    const PREFIX_SALES = 'sales_reports_';
    const PREFIX_AFFILIATE = 'affiliate_reports_';
    const PREFIX_DASHBOARD = 'dashboard_';

    /**
     * Generate cache key for reports
     */
    public static function generateKey(string $type, array $filters = [], string $suffix = ''): string
    {
        // Sort filters for consistent key generation
        ksort($filters);
        
        // Remove sensitive or irrelevant data
        $cleanFilters = array_filter($filters, function ($value, $key) {
            return !in_array($key, ['page', 'per_page']) && $value !== null && $value !== '';
        }, ARRAY_FILTER_USE_BOTH);

        $keyParts = [
            $type,
            md5(serialize($cleanFilters)),
            $suffix,
        ];

        return implode('_', array_filter($keyParts));
    }

    /**
     * Get cached data with fallback
     */
    public static function remember(
        string $key,
        callable $callback,
        int $ttl = self::DEFAULT_TTL,
        bool $forceRefresh = false
    ) {
        if ($forceRefresh) {
            Cache::forget($key);
        }

        try {
            return Cache::remember($key, $ttl, function () use ($callback, $key) {
                Log::info("Cache miss for key: {$key}");
                $result = $callback();
                
                // Validate result before caching
                if (self::isValidCacheData($result)) {
                    return $result;
                }
                
                Log::warning("Invalid data returned for cache key: {$key}");
                return null;
            });
        } catch (\Exception $e) {
            Log::error("Cache error for key {$key}: " . $e->getMessage());
            
            // Return fresh data on cache error
            return $callback();
        }
    }

    /**
     * Cache sales report data
     */
    public static function cacheSalesData(
        string $endpoint,
        array $filters,
        callable $callback,
        int $ttl = self::DEFAULT_TTL
    ) {
        $key = self::generateKey(self::PREFIX_SALES . $endpoint, $filters);
        return self::remember($key, $callback, $ttl);
    }

    /**
     * Cache affiliate performance data
     */
    public static function cacheAffiliateData(
        string $endpoint,
        array $filters,
        callable $callback,
        int $ttl = self::DEFAULT_TTL
    ) {
        $key = self::generateKey(self::PREFIX_AFFILIATE . $endpoint, $filters);
        return self::remember($key, $callback, $ttl);
    }

    /**
     * Cache dashboard data
     */
    public static function cacheDashboardData(
        string $endpoint,
        array $filters,
        callable $callback,
        int $ttl = self::SHORT_TTL
    ) {
        $key = self::generateKey(self::PREFIX_DASHBOARD . $endpoint, $filters);
        return self::remember($key, $callback, $ttl);
    }

    /**
     * Invalidate related caches
     */
    public static function invalidateReportCaches(array $tags = []): void
    {
        $patterns = [
            self::PREFIX_SALES . '*',
            self::PREFIX_AFFILIATE . '*',
            self::PREFIX_DASHBOARD . '*',
        ];

        foreach ($patterns as $pattern) {
            try {
                // Note: This is a simplified approach. In production, consider using cache tags
                // or a more sophisticated cache invalidation strategy
                Cache::flush(); // This flushes all cache - use with caution
                Log::info("Invalidated cache pattern: {$pattern}");
            } catch (\Exception $e) {
                Log::error("Failed to invalidate cache pattern {$pattern}: " . $e->getMessage());
            }
        }
    }

    /**
     * Warm up cache for common report queries
     */
    public static function warmUpCache(): void
    {
        $commonFilters = [
            // Last 7 days
            [
                'date_start' => Carbon::now()->subDays(7)->toDateString(),
                'date_end' => Carbon::now()->toDateString(),
            ],
            // Last 30 days
            [
                'date_start' => Carbon::now()->subDays(30)->toDateString(),
                'date_end' => Carbon::now()->toDateString(),
            ],
            // This month
            [
                'date_start' => Carbon::now()->startOfMonth()->toDateString(),
                'date_end' => Carbon::now()->toDateString(),
            ],
        ];

        foreach ($commonFilters as $filters) {
            try {
                // Warm up sales summary
                $key = self::generateKey(self::PREFIX_SALES . 'summary', $filters);
                if (!Cache::has($key)) {
                    // This would call the actual service method
                    Log::info("Warming up cache for key: {$key}");
                }

                // Warm up affiliate summary
                $key = self::generateKey(self::PREFIX_AFFILIATE . 'summary', $filters);
                if (!Cache::has($key)) {
                    Log::info("Warming up cache for key: {$key}");
                }
            } catch (\Exception $e) {
                Log::error("Failed to warm up cache: " . $e->getMessage());
            }
        }
    }

    /**
     * Get cache statistics
     */
    public static function getCacheStats(): array
    {
        try {
            // This would depend on your cache driver
            // For Redis, you could use Redis::info()
            // For file cache, you could scan the cache directory
            
            return [
                'driver' => config('cache.default'),
                'status' => 'active',
                'keys_count' => 'N/A', // Would need driver-specific implementation
                'memory_usage' => 'N/A', // Would need driver-specific implementation
                'hit_rate' => 'N/A', // Would need tracking implementation
            ];
        } catch (\Exception $e) {
            return [
                'driver' => config('cache.default'),
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Clear expired cache entries
     */
    public static function clearExpiredCache(): int
    {
        $cleared = 0;
        
        try {
            // This would depend on your cache driver
            // For file cache, you could scan and remove expired files
            // For Redis, expired keys are automatically removed
            
            Log::info("Cleared {$cleared} expired cache entries");
        } catch (\Exception $e) {
            Log::error("Failed to clear expired cache: " . $e->getMessage());
        }

        return $cleared;
    }

    /**
     * Validate data before caching
     */
    private static function isValidCacheData($data): bool
    {
        // Check if data is not null
        if ($data === null) {
            return false;
        }

        // Check if data is an array or object
        if (!is_array($data) && !is_object($data)) {
            return false;
        }

        // Check for specific invalid patterns
        if (is_array($data)) {
            // Check for arrays with only null values
            if (count($data) > 0 && count(array_filter($data)) === 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get optimal TTL based on data type and time range
     */
    public static function getOptimalTTL(string $dataType, array $filters = []): int
    {
        $dateStart = $filters['date_start'] ?? null;
        $dateEnd = $filters['date_end'] ?? null;

        if ($dateStart && $dateEnd) {
            $start = Carbon::parse($dateStart);
            $end = Carbon::parse($dateEnd);
            $daysDiff = $start->diffInDays($end);

            // Longer date ranges can be cached longer
            if ($daysDiff > 90) {
                return self::LONG_TTL;
            } elseif ($daysDiff > 7) {
                return self::DEFAULT_TTL;
            } else {
                return self::SHORT_TTL;
            }
        }

        // Default TTL based on data type
        switch ($dataType) {
            case 'summary':
            case 'kpi':
                return self::SHORT_TTL; // KPIs change frequently
            
            case 'charts':
            case 'series':
                return self::DEFAULT_TTL; // Charts can be cached longer
            
            case 'tables':
            case 'leaderboard':
                return self::SHORT_TTL; // Tables change frequently
            
            case 'segments':
            case 'analytics':
                return self::LONG_TTL; // Analytics can be cached longer
            
            default:
                return self::DEFAULT_TTL;
        }
    }

    /**
     * Create cache tags for better invalidation
     */
    public static function createCacheTags(array $filters = []): array
    {
        $tags = ['reports'];

        // Add date-based tags
        if (isset($filters['date_start'])) {
            $date = Carbon::parse($filters['date_start']);
            $tags[] = 'date_' . $date->format('Y-m');
        }

        // Add entity-based tags
        if (isset($filters['affiliate_ids']) && !empty($filters['affiliate_ids'])) {
            $tags[] = 'affiliates';
        }

        if (isset($filters['product_ids']) && !empty($filters['product_ids'])) {
            $tags[] = 'products';
        }

        if (isset($filters['category_ids']) && !empty($filters['category_ids'])) {
            $tags[] = 'categories';
        }

        return $tags;
    }
}
