/**
 * Performance monitoring and metrics collection for conditional fields
 */

import { configManager } from './config';

export interface PerformanceMetric {
  name: string;
  value: number;
  unit: 'ms' | 'bytes' | 'count' | 'ratio';
  timestamp: number;
  context?: Record<string, any>;
}

export interface PerformanceReport {
  period: {
    start: number;
    end: number;
    duration: number;
  };
  metrics: PerformanceMetric[];
  summary: {
    totalApiCalls: number;
    averageApiResponseTime: number;
    cacheHitRate: number;
    errorRate: number;
    memoryUsage: number;
  };
}

export class PerformanceMonitor {
  private metrics: PerformanceMetric[] = [];
  private activeTimers = new Map<string, number>();
  private readonly maxMetricsHistory: number;
  private readonly isEnabled: boolean;

  constructor() {
    const config = configManager.getConfig();
    this.maxMetricsHistory = 1000; // Keep last 1000 metrics
    this.isEnabled = config.enablePerformanceMonitoring;
  }

  /**
   * Start timing an operation
   */
  public startTimer(operation: string, context?: Record<string, any>): void {
    if (!this.isEnabled) return;

    const timerId = `${operation}_${Date.now()}`;
    this.activeTimers.set(timerId, performance.now());

    if (context) {
      this.recordMetric(`${operation}_start`, 0, 'ms', context);
    }
  }

  /**
   * End timing an operation and record the duration
   */
  public endTimer(operation: string, context?: Record<string, any>): number {
    if (!this.isEnabled) return 0;

    const timerId = this.findActiveTimer(operation);
    if (!timerId) return 0;

    const startTime = this.activeTimers.get(timerId)!;
    const duration = performance.now() - startTime;

    this.activeTimers.delete(timerId);
    this.recordMetric(`${operation}_duration`, duration, 'ms', context);

    return duration;
  }

  /**
   * Record a custom metric
   */
  public recordMetric(
    name: string,
    value: number,
    unit: 'ms' | 'bytes' | 'count' | 'ratio',
    context?: Record<string, any>
  ): void {
    if (!this.isEnabled) return;

    const metric: PerformanceMetric = {
      name,
      value,
      unit,
      timestamp: Date.now(),
      context,
    };

    this.metrics.push(metric);

    // Maintain history limit
    if (this.metrics.length > this.maxMetricsHistory) {
      this.metrics.shift();
    }

    // Log significant metrics
    if (this.shouldLogMetric(metric)) {
      this.logMetric(metric);
    }
  }

  /**
   * Record API call metrics
   */
  public recordApiCall(
    endpoint: string,
    duration: number,
    success: boolean,
    cached: boolean = false
  ): void {
    this.recordMetric('api_call', duration, 'ms', {
      endpoint,
      success,
      cached,
      timestamp: Date.now(),
    });

    if (cached) {
      this.recordMetric('cache_hit', 1, 'count', { endpoint });
    } else {
      this.recordMetric('cache_miss', 1, 'count', { endpoint });
    }

    if (!success) {
      this.recordMetric('api_error', 1, 'count', { endpoint });
    }
  }

  /**
   * Record DOM manipulation metrics
   */
  public recordDomOperation(
    operation: string,
    elementCount: number,
    duration: number
  ): void {
    this.recordMetric('dom_operation', duration, 'ms', {
      operation,
      elementCount,
    });
  }

  /**
   * Record memory usage
   */
  public recordMemoryUsage(): void {
    // Type assertion for Chrome DevTools memory API
    const memoryInfo = (performance as any).memory;
    if (!memoryInfo) return;

    const { usedJSHeapSize, totalJSHeapSize, jsHeapSizeLimit } = memoryInfo;

    this.recordMetric('memory_used', usedJSHeapSize, 'bytes');
    this.recordMetric('memory_total', totalJSHeapSize, 'bytes');
    this.recordMetric('memory_limit', jsHeapSizeLimit, 'bytes');
    this.recordMetric(
      'memory_utilization',
      usedJSHeapSize / jsHeapSizeLimit,
      'ratio'
    );
  }

  /**
   * Generate performance report for a time period
   */
  public generateReport(hours: number = 1): PerformanceReport {
    const endTime = Date.now();
    const startTime = endTime - hours * 60 * 60 * 1000;

    const periodMetrics = this.metrics.filter(m => m.timestamp >= startTime);

    const apiCalls = periodMetrics.filter(m => m.name === 'api_call');
    const cacheHits = periodMetrics.filter(m => m.name === 'cache_hit');
    const cacheMisses = periodMetrics.filter(m => m.name === 'cache_miss');
    const apiErrors = periodMetrics.filter(m => m.name === 'api_error');
    const memoryMetrics = periodMetrics.filter(m => m.name === 'memory_used');

    const totalCacheRequests = cacheHits.length + cacheMisses.length;
    const cacheHitRate =
      totalCacheRequests > 0 ? cacheHits.length / totalCacheRequests : 0;
    const errorRate =
      apiCalls.length > 0 ? apiErrors.length / apiCalls.length : 0;

    const avgApiResponseTime =
      apiCalls.length > 0
        ? apiCalls.reduce((sum, m) => sum + m.value, 0) / apiCalls.length
        : 0;

    const avgMemoryUsage =
      memoryMetrics.length > 0
        ? memoryMetrics.reduce((sum, m) => sum + m.value, 0) /
          memoryMetrics.length
        : 0;

    return {
      period: {
        start: startTime,
        end: endTime,
        duration: endTime - startTime,
      },
      metrics: periodMetrics,
      summary: {
        totalApiCalls: apiCalls.length,
        averageApiResponseTime: avgApiResponseTime,
        cacheHitRate,
        errorRate,
        memoryUsage: avgMemoryUsage,
      },
    };
  }

  /**
   * Get performance statistics
   */
  public getStats(): {
    totalMetrics: number;
    activeTimers: number;
    memoryUsage?: { used: number; total: number; limit: number };
    recentMetrics: PerformanceMetric[];
  } {
    return {
      totalMetrics: this.metrics.length,
      activeTimers: this.activeTimers.size,
      memoryUsage: (performance as any).memory
        ? {
            used: (performance as any).memory.usedJSHeapSize,
            total: (performance as any).memory.totalJSHeapSize,
            limit: (performance as any).memory.jsHeapSizeLimit,
          }
        : undefined,
      recentMetrics: this.metrics.slice(-10),
    };
  }

  /**
   * Clear all metrics and timers
   */
  public clear(): void {
    this.metrics = [];
    this.activeTimers.clear();
  }

  /**
   * Export metrics for external analysis
   */
  public exportMetrics(): PerformanceMetric[] {
    return [...this.metrics];
  }

  private findActiveTimer(operation: string): string | null {
    for (const [timerId] of this.activeTimers) {
      if (timerId.startsWith(`${operation}_`)) {
        return timerId;
      }
    }
    return null;
  }

  private shouldLogMetric(metric: PerformanceMetric): boolean {
    // Log slow API calls (>500ms)
    if (metric.name === 'api_call_duration' && metric.value > 500) {
      return true;
    }

    // Log high memory usage (>50MB)
    if (metric.name === 'memory_used' && metric.value > 50 * 1024 * 1024) {
      return true;
    }

    // Log cache hit rates below 50%
    if (metric.name === 'cache_hit_rate' && metric.value < 0.5) {
      return true;
    }

    return false;
  }

  private logMetric(metric: PerformanceMetric): void {
    const config = configManager.getConfig();
    if (!config.enableDebugLogging) return;

    console.warn(
      `[Performance] ${metric.name}: ${metric.value}${metric.unit}`,
      metric.context || ''
    );
  }
}

// Export singleton instance
export const performanceMonitor = new PerformanceMonitor();
