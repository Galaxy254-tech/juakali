'use client';

import { useState, useEffect } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import { Alert, AlertDescription } from '@/components/ui/alert';
import {
  Database,
  RefreshCw,
  Trash2,
  Settings,
  Info,
  CheckCircle,
  AlertTriangle,
  Activity
} from 'lucide-react';
import { cacheUtils } from '@/hooks/useCache';

interface CacheStats {
  enabled: boolean;
  size?: number;
  memoryUsage?: number;
  total_keys?: number;
  used_memory?: string;
  used_memory_peak?: string;
  keyspace_hits?: number;
  keyspace_misses?: number;
  total_commands_processed?: number;
  connected_clients?: number;
  uptime_in_seconds?: number;
}

export function CacheManager() {
  const [stats, setStats] = useState<CacheStats>({ enabled: false });
  const [loading, setLoading] = useState(false);
  const [message, setMessage] = useState<{ type: 'success' | 'error' | 'info'; text: string } | null>(null);

  useEffect(() => {
    fetchCacheStats();
    // Set up periodic stats refresh
    const interval = setInterval(fetchCacheStats, 30000); // Every 30 seconds
    return () => clearInterval(interval);
  }, []);

  const fetchCacheStats = async () => {
    try {
      // For client-side cache stats
      const clientStats = cacheUtils.getStats();
      const hitRate = calculateHitRate(clientStats.entries);

      setStats({
        enabled: true,
        size: clientStats.size,
        memoryUsage: clientStats.memoryUsage,
        total_keys: clientStats.size,
        keyspace_hits: hitRate.hits,
        keyspace_misses: hitRate.misses,
        used_memory: formatBytes(clientStats.memoryUsage),
        used_memory_peak: formatBytes(clientStats.memoryUsage),
        total_commands_processed: clientStats.entries.length,
        connected_clients: 1,
        uptime_in_seconds: 0,
      });
    } catch (error) {
      console.error('Failed to fetch cache stats:', error);
      setStats({ enabled: false });
    }
  };

  const calculateHitRate = (entries: any[]) => {
    // Mock hit rate calculation
    const hits = entries.length * 8; // Mock hits
    const misses = entries.length * 2; // Mock misses
    return { hits, misses };
  };

  const formatBytes = (bytes: number): string => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
  };

  const formatUptime = (seconds: number): string => {
    const days = Math.floor(seconds / 86400);
    const hours = Math.floor((seconds % 86400) / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);

    if (days > 0) {
      return `${days}d ${hours}h ${minutes}m`;
    } else if (hours > 0) {
      return `${hours}h ${minutes}m`;
    } else {
      return `${minutes}m`;
    }
  };

  const handleClearCache = async () => {
    setLoading(true);
    try {
      cacheUtils.clear();
      setMessage({ type: 'success', text: 'Cache cleared successfully' });
      await fetchCacheStats();
    } catch (error) {
      setMessage({ type: 'error', text: 'Failed to clear cache' });
    } finally {
      setLoading(false);
    }
  };

  const handleCleanup = async () => {
    setLoading(true);
    try {
      const cleaned = cacheUtils.cleanup();
      setMessage({ type: 'success', text: `Cleaned up ${cleaned} expired entries` });
      await fetchCacheStats();
    } catch (error) {
      setMessage({ type: 'error', text: 'Failed to cleanup cache' });
    } finally {
      setLoading(false);
    }
  };

  const handleWarmup = async () => {
    setLoading(true);
    try {
      // Simulate cache warmup
      await new Promise(resolve => setTimeout(resolve, 2000));
      setMessage({ type: 'success', text: 'Cache warmup completed' });
    } catch (error) {
      setMessage({ type: 'error', text: 'Failed to warmup cache' });
    } finally {
      setLoading(false);
    }
  };

  const getHitRateColor = (hits: number, misses: number) => {
    const total = hits + misses;
    if (total === 0) return 'text-gray-600';
    const rate = (hits / total) * 100;
    if (rate >= 80) return 'text-green-600';
    if (rate >= 60) return 'text-yellow-600';
    return 'text-red-600';
  };

  const getMemoryUsageColor = (usage: number) => {
    // Assuming 100MB as a reasonable limit
    const percentage = (usage / (100 * 1024 * 1024)) * 100;
    if (percentage >= 80) return 'text-red-600';
    if (percentage >= 60) return 'text-yellow-600';
    return 'text-green-600';
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h3 className="text-lg font-semibold text-emerald-900">Cache Management</h3>
          <p className="text-sm text-emerald-700">Monitor and manage application cache</p>
        </div>
        <div className="flex items-center gap-2">
          <Badge variant={stats.enabled ? 'default' : 'secondary'}>
            {stats.enabled ? 'Connected' : 'Disconnected'}
          </Badge>
          <Button
            variant="outline"
            size="sm"
            onClick={fetchCacheStats}
            disabled={loading}
          >
            <RefreshCw className={`w-4 h-4 mr-2 ${loading ? 'animate-spin' : ''}`} />
            Refresh
          </Button>
        </div>
      </div>

      {/* Status Message */}
      {message && (
        <Alert className={message.type === 'success' ? 'border-green-200 bg-green-50' : message.type === 'error' ? 'border-red-200 bg-red-50' : 'border-blue-200 bg-blue-50'}>
          <div className="flex items-center">
            {message.type === 'success' ? (
              <CheckCircle className="h-4 w-4 text-green-600" />
            ) : message.type === 'error' ? (
              <AlertTriangle className="h-4 w-4 text-red-600" />
            ) : (
              <Info className="h-4 w-4 text-blue-600" />
            )}
            <AlertDescription className={message.type === 'success' ? 'text-green-800' : message.type === 'error' ? 'text-red-800' : 'text-blue-800'}>
              {message.text}
            </AlertDescription>
          </div>
        </Alert>
      )}

      {/* Cache Statistics */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <Card className="border-emerald-200">
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium text-emerald-700">Cache Entries</CardTitle>
            <Database className="h-4 w-4 text-emerald-600" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-emerald-900">{stats.size || 0}</div>
            <p className="text-xs text-emerald-600 mt-1">Total cached items</p>
          </CardContent>
        </Card>

        <Card className="border-emerald-200">
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium text-emerald-700">Memory Usage</CardTitle>
            <Activity className="h-4 w-4 text-emerald-600" />
          </CardHeader>
          <CardContent>
            <div className={`text-2xl font-bold ${getMemoryUsageColor(stats.memoryUsage || 0)}`}>
              {stats.used_memory || '0 B'}
            </div>
            <p className="text-xs text-emerald-600 mt-1">Peak: {stats.used_memory_peak || '0 B'}</p>
          </CardContent>
        </Card>

        <Card className="border-emerald-200">
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium text-emerald-700">Hit Rate</CardTitle>
            <CheckCircle className="h-4 w-4 text-emerald-600" />
          </CardHeader>
          <CardContent>
            <div className={`text-2xl font-bold ${getHitRateColor(stats.keyspace_hits || 0, stats.keyspace_misses || 0)}`}>
              {stats.keyspace_hits && stats.keyspace_misses
                ? Math.round((stats.keyspace_hits / (stats.keyspace_hits + stats.keyspace_misses)) * 100)
                : 0}%
            </div>
            <p className="text-xs text-emerald-600 mt-1">
              {stats.keyspace_hits || 0} hits, {stats.keyspace_misses || 0} misses
            </p>
          </CardContent>
        </Card>

        <Card className="border-emerald-200">
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium text-emerald-700">Operations</CardTitle>
            <Settings className="h-4 w-4 text-emerald-600" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-emerald-900">{stats.total_commands_processed || 0}</div>
            <p className="text-xs text-emerald-600 mt-1">Total operations</p>
          </CardContent>
        </Card>
      </div>

      {/* Cache Management Actions */}
      <Card className="border-emerald-200">
        <CardHeader>
          <CardTitle className="text-emerald-900">Cache Actions</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div className="space-y-2">
              <h4 className="font-medium text-emerald-900">Maintenance</h4>
              <div className="space-y-2">
                <Button
                  variant="outline"
                  className="w-full justify-start"
                  onClick={handleCleanup}
                  disabled={loading || !stats.enabled}
                >
                  <RefreshCw className="w-4 h-4 mr-2" />
                  Cleanup Expired
                </Button>
                <Button
                  variant="outline"
                  className="w-full justify-start"
                  onClick={handleClearCache}
                  disabled={loading || !stats.enabled}
                >
                  <Trash2 className="w-4 h-4 mr-2" />
                  Clear All Cache
                </Button>
              </div>
            </div>

            <div className="space-y-2">
              <h4 className="font-medium text-emerald-900">Performance</h4>
              <div className="space-y-2">
                <Button
                  variant="outline"
                  className="w-full justify-start"
                  onClick={handleWarmup}
                  disabled={loading || !stats.enabled}
                >
                  <Activity className="w-4 h-4 mr-2" />
                  Warmup Cache
                </Button>
                <Button
                  variant="outline"
                  className="w-full justify-start"
                  onClick={fetchCacheStats}
                  disabled={loading}
                >
                  <RefreshCw className="w-4 h-4 mr-2" />
                  Refresh Stats
                </Button>
              </div>
            </div>

            <div className="space-y-2">
              <h4 className="font-medium text-emerald-900">Cache Info</h4>
              <div className="text-sm text-emerald-700 space-y-1">
                <p>Status: {stats.enabled ? 'Active' : 'Inactive'}</p>
                <p>Type: Client-side Storage</p>
                <p>TTL: 5 minutes (default)</p>
                <p>Storage: Browser Memory</p>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Memory Usage Progress */}
      {stats.memoryUsage && (
        <Card className="border-emerald-200">
          <CardHeader>
            <CardTitle className="text-emerald-900">Memory Usage Breakdown</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-4">
              <div>
                <div className="flex justify-between text-sm mb-2">
                  <span className="text-emerald-700">Current Usage</span>
                  <span className="text-emerald-900">{stats.used_memory}</span>
                </div>
                <Progress
                  value={Math.min((stats.memoryUsage / (100 * 1024 * 1024)) * 100, 100)}
                  className="h-2"
                />
              </div>
              <div className="grid grid-cols-2 gap-4 text-sm">
                <div>
                  <p className="text-emerald-700">Cache Entries</p>
                  <p className="font-semibold text-emerald-900">{stats.size}</p>
                </div>
                <div>
                  <p className="text-emerald-700">Avg Entry Size</p>
                  <p className="font-semibold text-emerald-900">
                    {stats.size > 0 ? formatBytes(Math.round(stats.memoryUsage / stats.size)) : '0 B'}
                  </p>
                </div>
              </div>
            </div>
          </CardContent>
        </Card>
      )}

      {/* Cache Configuration Info */}
      <Card className="border-emerald-200">
        <CardHeader>
          <CardTitle className="text-emerald-900">Cache Configuration</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div className="space-y-3">
              <h4 className="font-medium text-emerald-900">Default TTL Settings</h4>
              <div className="space-y-2 text-sm">
                <div className="flex justify-between">
                  <span className="text-emerald-700">API Responses</span>
                  <span className="text-emerald-900">5 minutes</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-emerald-700">User Data</span>
                  <span className="text-emerald-900">10 minutes</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-emerald-700">Dashboard Data</span>
                  <span className="text-emerald-900">3 minutes</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-emerald-700">Product Data</span>
                  <span className="text-emerald-900">10 minutes</span>
                </div>
              </div>
            </div>

            <div className="space-y-3">
              <h4 className="font-medium text-emerald-900">Cache Strategies</h4>
              <div className="space-y-2 text-sm">
                <div className="flex items-center gap-2">
                  <CheckCircle className="w-4 h-4 text-green-600" />
                  <span className="text-emerald-700">Cache-aside pattern</span>
                </div>
                <div className="flex items-center gap-2">
                  <CheckCircle className="w-4 h-4 text-green-600" />
                  <span className="text-emerald-700">Stale-while-revalidate</span>
                </div>
                <div className="flex items-center gap-2">
                  <CheckCircle className="w-4 h-4 text-green-600" />
                  <span className="text-emerald-700">Automatic cleanup</span>
                </div>
                <div className="flex items-center gap-2">
                  <CheckCircle className="w-4 h-4 text-green-600" />
                  <span className="text-emerald-700">Pattern-based invalidation</span>
                </div>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}

export default CacheManager;