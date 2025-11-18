'use client';

import { useState, useEffect, useCallback, useRef } from 'react';

interface CacheEntry<T> {
  data: T;
  timestamp: number;
  ttl: number;
  expiresAt: number;
}

interface CacheOptions {
  ttl?: number; // Time to live in milliseconds
  staleWhileRevalidate?: boolean; // Return stale data while revalidating
  onError?: (error: Error) => void;
}

interface UseCacheResult<T> {
  data: T | null;
  isLoading: boolean;
  error: Error | null;
  refetch: () => Promise<void>;
  invalidate: () => void;
  isStale: boolean;
}

class ClientCache {
  private cache = new Map<string, CacheEntry<any>>();
  private static instance: ClientCache;

  private constructor() {}

  public static getInstance(): ClientCache {
    if (!ClientCache.instance) {
      ClientCache.instance = new ClientCache();
    }
    return ClientCache.instance;
  }

  public set<T>(key: string, data: T, ttl: number = 300000): void { // Default 5 minutes
    const now = Date.now();
    const entry: CacheEntry<T> = {
      data,
      timestamp: now,
      ttl,
      expiresAt: now + ttl,
    };

    this.cache.set(key, entry);
  }

  public get<T>(key: string): T | null {
    const entry = this.cache.get(key);
    if (!entry) {
      return null;
    }

    const now = Date.now();
    if (now > entry.expiresAt) {
      this.cache.delete(key);
      return null;
    }

    return entry.data as T;
  }

  public getWithStaleCheck<T>(key: string): { data: T | null; isStale: boolean } {
    const entry = this.cache.get(key);
    if (!entry) {
      return { data: null, isStale: false };
    }

    const now = Date.now();
    const isStale = now > entry.expiresAt;

    if (isStale) {
      // Keep stale data but mark as expired
      return { data: entry.data as T, isStale: true };
    }

    return { data: entry.data as T, isStale: false };
  }

  public delete(key: string): boolean {
    return this.cache.delete(key);
  }

  public clear(): void {
    this.cache.clear();
  }

  public has(key: string): boolean {
    const entry = this.cache.get(key);
    if (!entry) {
      return false;
    }

    const now = Date.now();
    if (now > entry.expiresAt) {
      this.cache.delete(key);
      return false;
    }

    return true;
  }

  public invalidatePattern(pattern: string): number {
    let invalidated = 0;
    const regex = new RegExp(pattern);

    for (const [key] of this.cache.entries()) {
      if (regex.test(key)) {
        this.cache.delete(key);
        invalidated++;
      }
    }

    return invalidated;
  }

  public cleanup(): number {
    let cleaned = 0;
    const now = Date.now();

    for (const [key, entry] of this.cache.entries()) {
      if (now > entry.expiresAt) {
        this.cache.delete(key);
        cleaned++;
      }
    }

    return cleaned;
  }

  public getStats(): {
    size: number;
    memoryUsage: number;
    entries: Array<{ key: string; size: number; age: number; ttl: number }>;
  } {
    const now = Date.now();
    const entries = [];
    let totalSize = 0;

    for (const [key, entry] of this.cache.entries()) {
      const size = JSON.stringify(entry.data).length;
      const age = now - entry.timestamp;

      entries.push({
        key,
        size,
        age,
        ttl: entry.ttl,
      });

      totalSize += size;
    }

    return {
      size: this.cache.size,
      memoryUsage: totalSize,
      entries: entries.slice(0, 10), // Return first 10 entries
    };
  }
}

/**
 * React hook for client-side caching
 */
export function useCache<T>(
  key: string,
  fetcher: () => Promise<T>,
  options: CacheOptions = {}
): UseCacheResult<T> {
  const {
    ttl = 300000, // 5 minutes default
    staleWhileRevalidate = true,
    onError,
  } = options;

  const [data, setData] = useState<T | null>(null);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<Error | null>(null);
  const [isStale, setIsStale] = useState(false);

  const cache = ClientCache.getInstance();
  const fetchPromiseRef = useRef<Promise<T> | null>(null);

  const fetchData = useCallback(async (forceRefresh = false) => {
    // Check cache first
    if (!forceRefresh) {
      if (staleWhileRevalidate) {
        const cached = cache.getWithStaleCheck<T>(key);
        if (cached.data) {
          setData(cached.data);
          setIsStale(cached.isStale);
          if (cached.isStale) {
            // Data is stale, fetch in background
            setIsLoading(true);
          } else {
            // Data is fresh
            setError(null);
            return;
          }
        }
      } else {
        const cached = cache.get<T>(key);
        if (cached) {
          setData(cached);
          setIsStale(false);
          setError(null);
          return;
        }
      }
    }

    // Prevent duplicate fetches
    if (fetchPromiseRef.current) {
      try {
        await fetchPromiseRef.current;
        return;
      } catch {
        // Continue with new fetch if previous one failed
      }
    }

    setIsLoading(true);
    setError(null);

    try {
      fetchPromiseRef.current = fetcher();
      const result = await fetchPromiseRef.current;

      // Cache the result
      cache.set(key, result, ttl);

      setData(result);
      setIsStale(false);
      setError(null);
    } catch (err) {
      const error = err instanceof Error ? err : new Error('Unknown error');
      setError(error);
      onError?.(error);
    } finally {
      setIsLoading(false);
      fetchPromiseRef.current = null;
    }
  }, [key, fetcher, ttl, staleWhileRevalidate, onError, cache]);

  const refetch = useCallback(() => {
    return fetchData(true);
  }, [fetchData]);

  const invalidate = useCallback(() => {
    cache.delete(key);
    setData(null);
    setIsStale(false);
  }, [key, cache]);

  useEffect(() => {
    fetchData();
  }, [fetchData]);

  return {
    data,
    isLoading,
    error,
    refetch,
    invalidate,
    isStale,
  };
}

/**
 * Hook for caching API responses
 */
export function useApiCache<T>(
  endpoint: string,
  fetcher: () => Promise<T>,
  dependencies: any[] = [],
  options: CacheOptions = {}
): UseCacheResult<T> {
  const cacheKey = `api:${endpoint}:${JSON.stringify(dependencies)}`;

  return useCache(cacheKey, fetcher, options);
}

/**
 * Hook for caching user data
 */
export function useUserCache<T>(
  userId: string,
  fetcher: () => Promise<T>,
  options: CacheOptions = {}
): UseCacheResult<T> {
  const cacheKey = `user:${userId}`;

  return useCache(cacheKey, fetcher, {
    ttl: 600000, // 10 minutes for user data
    ...options,
  });
}

/**
 * Hook for caching dashboard data
 */
export function useDashboardCache<T>(
  userId: string,
  dashboardType: string,
  fetcher: () => Promise<T>,
  options: CacheOptions = {}
): UseCacheResult<T> {
  const cacheKey = `dashboard:${dashboardType}:${userId}`;

  return useCache(cacheKey, fetcher, {
    ttl: 180000, // 3 minutes for dashboard data
    staleWhileRevalidate: true,
    ...options,
  });
}

/**
 * Hook for caching product data
 */
export function useProductCache<T>(
  productId: string,
  fetcher: () => Promise<T>,
  options: CacheOptions = {}
): UseCacheResult<T> {
  const cacheKey = `product:${productId}`;

  return useCache(cacheKey, fetcher, {
    ttl: 600000, // 10 minutes for product data
    ...options,
  });
}

/**
 * Global cache utilities
 */
export const cacheUtils = {
  /**
   * Get cache instance
   */
  getInstance: () => ClientCache.getInstance(),

  /**
   * Clear all cache
   */
  clear: () => ClientCache.getInstance().clear(),

  /**
   * Invalidate cache by pattern
   */
  invalidatePattern: (pattern: string) => ClientCache.getInstance().invalidatePattern(pattern),

  /**
   * Cleanup expired entries
   */
  cleanup: () => ClientCache.getInstance().cleanup(),

  /**
   * Get cache statistics
   */
  getStats: () => ClientCache.getInstance().getStats(),

  /**
   * Invalidate user-specific cache
   */
  invalidateUserCache: (userId: string) => {
    const cache = ClientCache.getInstance();
    const patterns = [
      `user:${userId}`,
      `dashboard:*:${userId}`,
      `api:*user=${userId}*`,
      `notifications:${userId}`,
      `analytics:*:${userId}`,
    ];

    let totalInvalidated = 0;
    patterns.forEach(pattern => {
      totalInvalidated += cache.invalidatePattern(pattern.replace('*', '.*'));
    });

    return totalInvalidated;
  },

  /**
   * Invalidate product cache
   */
  invalidateProductCache: (productId?: string) => {
    const cache = ClientCache.getInstance();
    if (productId) {
      return cache.delete(`product:${productId}`);
    } else {
      return cache.invalidatePattern('product:.*');
    }
  },

  /**
   * Preload cache with data
   */
  preload: async <T>(key: string, fetcher: () => Promise<T>, ttl?: number) => {
    const cache = ClientCache.getInstance();

    // Check if already cached
    if (cache.has(key)) {
      return;
    }

    try {
      const data = await fetcher();
      cache.set(key, data, ttl);
    } catch (error) {
      console.warn(`Failed to preload cache for key ${key}:`, error);
    }
  },
};

export default useCache;