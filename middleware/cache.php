<?php

/**
 * Caching Middleware
 *
 * Provides caching functionality for API responses and database queries
 */

require_once '../config/redis.php';
require_once '../config/database.php';

class CacheMiddleware {
    private $cache;
    private $defaultTTL = 300; // 5 minutes default

    public function __construct() {
        $this->cache = RedisCache::getInstance();
    }

    /**
     * Cache database query results
     */
    public function cacheQuery(string $query, array $params, callable $callback, int $ttl = null) {
        if (!$this->cache->isConnected()) {
            return call_user_func($callback);
        }

        $cacheKey = $this->generateQueryCacheKey($query, $params);

        return $this->cache->remember($cacheKey, $callback, $ttl ?? $this->defaultTTL);
    }

    /**
     * Cache API response
     */
    public function cacheApiResponse(string $method, string $uri, array $data, callable $callback, int $ttl = null) {
        if (!$this->cache->isConnected()) {
            return call_user_func($callback);
        }

        $cacheKey = $this->generateApiCacheKey($method, $uri, $data);

        return $this->cache->remember($cacheKey, $callback, $ttl ?? $this->defaultTTL);
    }

    /**
     * Cache user-specific data
     */
    public function cacheUserData(string $userId, callable $callback, int $ttl = null) {
        if (!$this->cache->isConnected()) {
            return call_user_func($callback);
        }

        $cacheKey = "user_data:{$userId}";
        return $this->cache->remember($cacheKey, $callback, $ttl ?? 1800); // 30 minutes
    }

    /**
     * Cache dashboard statistics
     */
    public function cacheDashboardStats(string $userId, string $role, callable $callback, int $ttl = null) {
        if (!$this->cache->isConnected()) {
            return call_user_func($callback);
        }

        $cacheKey = "dashboard_stats:{$role}:{$userId}";
        return $this->cache->remember($cacheKey, $callback, $ttl ?? 300); // 5 minutes
    }

    /**
     * Cache product catalog
     */
    public function cacheProducts(callable $callback, int $ttl = null) {
        if (!$this->cache->isConnected()) {
            return call_user_func($callback);
        }

        $cacheKey = "products:catalog";
        return $this->cache->remember($cacheKey, $callback, $ttl ?? 3600); // 1 hour
    }

    /**
     * Cache loan data
     */
    public function cacheLoanData(string $loanId, callable $callback, int $ttl = null) {
        if (!$this->cache->isConnected()) {
            return call_user_func($callback);
        }

        $cacheKey = "loan:{$loanId}";
        return $this->cache->remember($cacheKey, $callback, $ttl ?? 600); // 10 minutes
    }

    /**
     * Invalidate cache by pattern
     */
    public function invalidatePattern(string $pattern): int {
        if (!$this->cache->isConnected()) {
            return 0;
        }

        try {
            $redis = $this->cache->getConnection();
            $keys = $redis->keys($pattern);

            if (!empty($keys)) {
                return $redis->del($keys);
            }

            return 0;
        } catch (Exception $e) {
            error_log('Cache invalidate pattern error: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Invalidate user cache
     */
    public function invalidateUserCache(string $userId): bool {
        $patterns = [
            "user_data:{$userId}",
            "dashboard_stats:*:{$userId}",
            "user_sessions:{$userId}*",
            "api_cache:*user={$userId}*"
        ];

        $totalInvalidated = 0;
        foreach ($patterns as $pattern) {
            $totalInvalidated += $this->invalidatePattern($pattern);
        }

        return $totalInvalidated > 0;
    }

    /**
     * Invalidate product cache
     */
    public function invalidateProductCache(string $productId = null): bool {
        if ($productId) {
            // Invalidate specific product
            $result = $this->cache->delete("product:{$productId}");
            $this->invalidatePattern("products:*:product_id={$productId}*");
            return $result;
        } else {
            // Invalidate all product cache
            return $this->invalidatePattern("products:*") > 0;
        }
    }

    /**
     * Invalidate loan cache
     */
    public function invalidateLoanCache(string $loanId = null): bool {
        if ($loanId) {
            // Invalidate specific loan
            $result = $this->cache->delete("loan:{$loanId}");
            $this->invalidatePattern("loan_data:*:loan_id={$loanId}*");
            return $result;
        } else {
            // Invalidate all loan cache
            return $this->invalidatePattern("loan:*") > 0;
        }
    }

    /**
     * Warm up cache with common data
     */
    public function warmupCache(): void {
        if (!$this->cache->isConnected()) {
            return;
        }

        try {
            // Cache common lookup data
            $this->warmupProductsCache();
            $this->warmupExchangeRates();
            $this->warmupSystemSettings();
        } catch (Exception $e) {
            error_log('Cache warmup error: ' . $e->getMessage());
        }
    }

    /**
     * Warm up products cache
     */
    private function warmupProductsCache(): void {
        $callback = function() {
            $db = new Database();
            $db->connect();
            $db->query('SELECT id, name, price, category, supplier_id FROM products WHERE status = "active" LIMIT 100');
            return $db->resultSet();
        };

        $this->cacheProducts($callback, 3600);
    }

    /**
     * Warm up exchange rates
     */
    private function warmupExchangeRates(): void {
        $callback = function() {
            return [
                'USD' => 110.5,
                'EUR' => 120.8,
                'GBP' => 140.2,
                'updated_at' => date('Y-m-d H:i:s')
            ];
        };

        $this->cache->set('exchange_rates', $callback(), 3600);
    }

    /**
     * Warm up system settings
     */
    private function warmupSystemSettings(): void {
        $callback = function() {
            return [
                'min_loan_amount' => 5000,
                'max_loan_amount' => 500000,
                'default_interest_rate' => 12.5,
                'supported_countries' => ['KE', 'UG', 'TZ', 'RW']
            ];
        };

        $this->cache->set('system_settings', $callback(), 86400); // 24 hours
    }

    /**
     * Generate cache key for database queries
     */
    private function generateQueryCacheKey(string $query, array $params): string {
        $queryHash = md5($query);
        $paramsHash = md5(serialize($params));
        return "query:{$queryHash}:{$paramsHash}";
    }

    /**
     * Generate cache key for API requests
     */
    private function generateApiCacheKey(string $method, string $uri, array $data): string {
        $uriHash = md5($uri);
        $dataHash = md5(serialize($data));
        return "api:{$method}:{$uriHash}:{$dataHash}";
    }

    /**
     * Get cache statistics
     */
    public function getCacheStats(): array {
        if (!$this->cache->isConnected()) {
            return ['enabled' => false];
        }

        $stats = $this->cache->getStats();
        $stats['enabled'] = true;

        // Add additional cache-specific stats
        try {
            $redis = $this->cache->getConnection();
            $stats['total_keys'] = $redis->dbSize();
            $stats['memory_usage'] = $redis->info('memory')['used_memory_human'] ?? 'N/A';
        } catch (Exception $e) {
            error_log('Error getting detailed cache stats: ' . $e->getMessage());
        }

        return $stats;
    }

    /**
     * Clear expired cache entries
     */
    public function clearExpired(): int {
        if (!$this->cache->isConnected()) {
            return 0;
        }

        // Redis automatically handles expired keys, but we can trigger cleanup
        try {
            $redis = $this->cache->getConnection();
            // This will trigger cleanup of expired keys
            $redis->eval("return 1", 0);
            return 0; // Redis doesn't provide count of cleaned keys
        } catch (Exception $e) {
            error_log('Cache cleanup error: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Cache analytics data
     */
    public function cacheAnalytics(string $userId, string $type, array $data, int $ttl = null): bool {
        if (!$this->cache->isConnected()) {
            return false;
        }

        $cacheKey = "analytics:{$type}:{$userId}";
        return $this->cache->set($cacheKey, $data, $ttl ?? 1800); // 30 minutes
    }

    /**
     * Get cached analytics data
     */
    public function getCachedAnalytics(string $userId, string $type) {
        if (!$this->cache->isConnected()) {
            return null;
        }

        $cacheKey = "analytics:{$type}:{$userId}";
        return $this->cache->get($cacheKey);
    }

    /**
     * Cache notification data
     */
    public function cacheNotifications(string $userId, array $notifications, int $ttl = null): bool {
        if (!$this->cache->isConnected()) {
            return false;
        }

        $cacheKey = "notifications:{$userId}";
        return $this->cache->set($cacheKey, $notifications, $ttl ?? 300); // 5 minutes
    }

    /**
     * Get cached notifications
     */
    public function getCachedNotifications(string $userId) {
        if (!$this->cache->isConnected()) {
            return null;
        }

        $cacheKey = "notifications:{$userId}";
        return $this->cache->get($cacheKey);
    }
}

// Global cache middleware instance
$cacheMiddleware = new CacheMiddleware();

/**
 * Helper function to get cache middleware instance
 */
function getCacheMiddleware(): CacheMiddleware {
    global $cacheMiddleware;
    return $cacheMiddleware;
}

?>