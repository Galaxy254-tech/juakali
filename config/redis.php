<?php

/**
 * Redis Configuration and Caching System
 *
 * This file handles Redis connection and provides caching functionality
 * for the JuaKali Lend platform.
 */

class RedisCache {
    private static $instance = null;
    private $redis;
    private $connected = false;
    private $defaultTTL = 3600; // 1 hour default TTL

    /**
     * Get singleton instance
     */
    public static function getInstance(): RedisCache {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor to enforce singleton pattern
     */
    private function __construct() {
        $this->connect();
    }

    /**
     * Connect to Redis server
     */
    private function connect(): bool {
        try {
            // Check if Redis extension is available
            if (!extension_loaded('redis')) {
                error_log('Redis extension not loaded. Caching will be disabled.');
                return false;
            }

            $this->redis = new Redis();

            // Redis configuration
            $host = $_ENV['REDIS_HOST'] ?? '127.0.0.1';
            $port = $_ENV['REDIS_PORT'] ?? 6379;
            $password = $_ENV['REDIS_PASSWORD'] ?? null;
            $database = $_ENV['REDIS_DATABASE'] ?? 0;

            // Connect to Redis
            $connected = $this->redis->connect($host, $port, 2.0);

            if ($connected) {
                // Authenticate if password is provided
                if ($password) {
                    $this->redis->auth($password);
                }

                // Select database
                $this->redis->select($database);

                // Set connection options
                $this->redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_JSON);
                $this->redis->setOption(Redis::OPT_PREFIX, 'juakali:');

                $this->connected = true;
                error_log('Connected to Redis successfully');
                return true;
            }
        } catch (Exception $e) {
            error_log('Redis connection failed: ' . $e->getMessage());
        }

        $this->connected = false;
        return false;
    }

    /**
     * Check if Redis is connected
     */
    public function isConnected(): bool {
        return $this->connected && $this->redis && $this->redis->isConnected();
    }

    /**
     * Get Redis connection
     */
    public function getConnection(): ?Redis {
        return $this->isConnected() ? $this->redis : null;
    }

    /**
     * Set cache value with TTL
     */
    public function set(string $key, $value, int $ttl = null): bool {
        if (!$this->isConnected()) {
            return false;
        }

        try {
            $ttl = $ttl ?? $this->defaultTTL;
            return $this->redis->setex($key, $ttl, $value);
        } catch (Exception $e) {
            error_log('Redis set error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get cache value
     */
    public function get(string $key) {
        if (!$this->isConnected()) {
            return null;
        }

        try {
            return $this->redis->get($key);
        } catch (Exception $e) {
            error_log('Redis get error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Delete cache key
     */
    public function delete(string $key): bool {
        if (!$this->isConnected()) {
            return false;
        }

        try {
            return $this->redis->del($key) > 0;
        } catch (Exception $e) {
            error_log('Redis delete error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if key exists
     */
    public function exists(string $key): bool {
        if (!$this->isConnected()) {
            return false;
        }

        try {
            return $this->redis->exists($key) > 0;
        } catch (Exception $e) {
            error_log('Redis exists error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Set cache with tags for easier invalidation
     */
    public function setWithTags(string $key, $value, array $tags, int $ttl = null): bool {
        if (!$this->isConnected()) {
            return false;
        }

        try {
            // Set the main value
            $result = $this->set($key, $value, $ttl);

            // Add key to each tag set
            foreach ($tags as $tag) {
                $this->redis->sadd("tag:{$tag}", $key);
                // Set tag to expire after a reasonable time (7 days)
                $this->redis->expire("tag:{$tag}", 604800);
            }

            return $result;
        } catch (Exception $e) {
            error_log('Redis setWithTags error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Invalidate cache by tags
     */
    public function invalidateByTag(string $tag): int {
        if (!$this->isConnected()) {
            return 0;
        }

        try {
            $keys = $this->redis->smembers("tag:{$tag}");
            $deleted = 0;

            if (!empty($keys)) {
                // Delete all keys in the tag
                $deleted = $this->redis->del(...$keys);
                // Remove the tag set itself
                $this->redis->del("tag:{$tag}");
            }

            return $deleted;
        } catch (Exception $e) {
            error_log('Redis invalidateByTag error: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get or set cache (cache-aside pattern)
     */
    public function remember(string $key, callable $callback, int $ttl = null) {
        if (!$this->isConnected()) {
            // Fallback to callback if Redis is not available
            return call_user_func($callback);
        }

        try {
            $value = $this->get($key);

            if ($value !== null) {
                return $value;
            }

            // Cache miss - execute callback and cache result
            $value = call_user_func($callback);
            $this->set($key, $value, $ttl);

            return $value;
        } catch (Exception $e) {
            error_log('Redis remember error: ' . $e->getMessage());
            // Fallback to callback
            return call_user_func($callback);
        }
    }

    /**
     * Increment numeric value
     */
    public function increment(string $key, int $value = 1): int {
        if (!$this->isConnected()) {
            return 0;
        }

        try {
            return $this->redis->incrBy($key, $value);
        } catch (Exception $e) {
            error_log('Redis increment error: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Decrement numeric value
     */
    public function decrement(string $key, int $value = 1): int {
        if (!$this->isConnected()) {
            return 0;
        }

        try {
            return $this->redis->decrBy($key, $value);
        } catch (Exception $e) {
            error_log('Redis decrement error: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Clear all cache
     */
    public function clear(): bool {
        if (!$this->isConnected()) {
            return false;
        }

        try {
            return $this->redis->flushDB();
        } catch (Exception $e) {
            error_log('Redis clear error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get cache statistics
     */
    public function getStats(): array {
        if (!$this->isConnected()) {
            return ['connected' => false];
        }

        try {
            $info = $this->redis->info();
            return [
                'connected' => true,
                'used_memory' => $info['used_memory_human'] ?? 'N/A',
                'used_memory_peak' => $info['used_memory_peak_human'] ?? 'N/A',
                'keyspace_hits' => $info['keyspace_hits'] ?? 0,
                'keyspace_misses' => $info['keyspace_misses'] ?? 0,
                'total_commands_processed' => $info['total_commands_processed'] ?? 0,
                'connected_clients' => $info['connected_clients'] ?? 0,
                'uptime_in_seconds' => $info['uptime_in_seconds'] ?? 0,
            ];
        } catch (Exception $e) {
            error_log('Redis getStats error: ' . $e->getMessage());
            return ['connected' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Close Redis connection
     */
    public function close(): void {
        if ($this->redis && $this->isConnected()) {
            $this->redis->close();
            $this->connected = false;
        }
    }

    /**
     * Destructor - close connection
     */
    public function __destruct() {
        $this->close();
    }
}

/**
 * Cache helper functions
 */

/**
 * Cache user data
 */
function cacheUserData(string $userId, array $userData, int $ttl = 1800): bool {
    return RedisCache::getInstance()->set("user:{$userId}", $userData, $ttl);
}

/**
 * Get cached user data
 */
function getCachedUserData(string $userId) {
    return RedisCache::getInstance()->get("user:{$userId}");
}

/**
 * Cache dashboard stats
 */
function cacheDashboardStats(string $userId, string $role, array $stats, int $ttl = 300): bool {
    $key = "dashboard:{$role}:{$userId}";
    return RedisCache::getInstance()->set($key, $stats, $ttl);
}

/**
 * Get cached dashboard stats
 */
function getCachedDashboardStats(string $userId, string $role) {
    $key = "dashboard:{$role}:{$userId}";
    return RedisCache::getInstance()->get($key);
}

/**
 * Cache API response
 */
function cacheApiResponse(string $endpoint, array $params, $data, int $ttl = 300): bool {
    $key = "api:" . md5($endpoint . serialize($params));
    return RedisCache::getInstance()->set($key, $data, $ttl);
}

/**
 * Get cached API response
 */
function getCachedApiResponse(string $endpoint, array $params) {
    $key = "api:" . md5($endpoint . serialize($params));
    return RedisCache::getInstance()->get($key);
}

/**
 * Cache product data
 */
function cacheProductData(string $productId, array $productData, int $ttl = 3600): bool {
    return RedisCache::getInstance()->setWithTags(
        "product:{$productId}",
        $productData,
        ['products', 'catalog'],
        $ttl
    );
}

/**
 * Invalidate product cache
 */
function invalidateProductCache(string $productId): bool {
    return RedisCache::getInstance()->delete("product:{$productId}");
}

/**
 * Invalidate all product cache
 */
function invalidateAllProductCache(): int {
    return RedisCache::getInstance()->invalidateByTag('products');
}

/**
 * Rate limiting with Redis
 */
function checkRateLimit(string $identifier, int $limit, int $windowSeconds): bool {
    $cache = RedisCache::getInstance();
    $key = "rate_limit:{$identifier}";

    if (!$cache->isConnected()) {
        return true; // Allow if Redis is not available
    }

    $current = $cache->get($key);

    if ($current === null) {
        // First request in window
        $cache->set($key, 1, $windowSeconds);
        return true;
    }

    if ($current >= $limit) {
        return false; // Rate limit exceeded
    }

    // Increment counter
    $cache->increment($key);
    return true;
}

/**
 * Session storage with Redis
 */
function setSessionData(string $sessionId, array $data, int $ttl = 7200): bool {
    return RedisCache::getInstance()->set("session:{$sessionId}", $data, $ttl);
}

function getSessionData(string $sessionId) {
    return RedisCache::getInstance()->get("session:{$sessionId}");
}

function deleteSessionData(string $sessionId): bool {
    return RedisCache::getInstance()->delete("session:{$sessionId}");
}

?>