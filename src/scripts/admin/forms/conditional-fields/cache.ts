/**
 * High-performance caching system with LRU eviction and hash-based keys
 */

import { configManager } from './config';

interface CacheEntry<T> {
  value: T;
  timestamp: number;
  accessCount: number;
  lastAccessed: number;
  size: number; // Approximate size in bytes
}

export interface CacheStats {
  hits: number;
  misses: number;
  evictions: number;
  totalSize: number;
  itemCount: number;
  hitRate: number;
}

export class LRUCache<T> {
  private cache = new Map<string, CacheEntry<T>>();
  private accessOrder = new Set<string>(); // For LRU ordering
  private maxSize: number;
  private maxItems: number;
  private stats: CacheStats = {
    hits: 0,
    misses: 0,
    evictions: 0,
    totalSize: 0,
    itemCount: 0,
    hitRate: 0,
  };

  constructor(maxSize: number = 10 * 1024 * 1024, maxItems: number = 100) {
    // 10MB default, 100 items
    this.maxSize = maxSize;
    this.maxItems = maxItems;
  }

  /**
   * Generate a fast hash for cache keys
   */
  private fastHash(obj: any): string {
    // Use a simple but fast hash for cache keys
    // This is much faster than JSON.stringify + crypto hash
    const str = this.canonicalString(obj);
    let hash = 0;
    for (let i = 0; i < str.length; i++) {
      const char = str.charCodeAt(i);
      hash = (hash << 5) - hash + char;
      hash = hash & hash; // Convert to 32-bit integer
    }
    return hash.toString(36); // Base36 for shorter strings
  }

  /**
   * Create a canonical string representation for consistent hashing
   */
  private canonicalString(obj: any): string {
    if (obj === null || obj === undefined) {
      return String(obj);
    }

    if (
      typeof obj === 'string' ||
      typeof obj === 'number' ||
      typeof obj === 'boolean'
    ) {
      return String(obj);
    }

    if (Array.isArray(obj)) {
      return '[' + obj.map(item => this.canonicalString(item)).join(',') + ']';
    }

    if (typeof obj === 'object') {
      // Sort keys for consistent ordering
      const keys = Object.keys(obj).sort();
      const pairs = keys.map(
        key => `"${key}":${this.canonicalString(obj[key])}`
      );
      return '{' + pairs.join(',') + '}';
    }

    return String(obj);
  }

  /**
   * Estimate the size of an object in bytes
   */
  private estimateSize(obj: any): number {
    const str = JSON.stringify(obj);
    return str ? str.length * 2 : 0; // Rough estimate: 2 bytes per character
  }

  /**
   * Get a value from the cache
   */
  get(key: any): T | undefined {
    const cacheKey = this.fastHash(key);

    const entry = this.cache.get(cacheKey);
    if (entry) {
      // Update access statistics
      entry.accessCount++;
      entry.lastAccessed = Date.now();

      // Move to end of access order (most recently used)
      this.accessOrder.delete(cacheKey);
      this.accessOrder.add(cacheKey);

      this.stats.hits++;
      this.updateHitRate();

      return entry.value;
    }

    this.stats.misses++;
    this.updateHitRate();

    return undefined;
  }

  /**
   * Set a value in the cache
   */
  set(key: any, value: T, customSize?: number): void {
    const cacheKey = this.fastHash(key);
    const size = customSize ?? this.estimateSize(value);

    // Check if we're updating an existing entry
    const existingEntry = this.cache.get(cacheKey);
    if (existingEntry) {
      // Update existing entry
      this.stats.totalSize -= existingEntry.size;
      this.stats.totalSize += size;

      existingEntry.value = value;
      existingEntry.timestamp = Date.now();
      existingEntry.size = size;

      // Move to end of access order
      this.accessOrder.delete(cacheKey);
      this.accessOrder.add(cacheKey);

      return;
    }

    // Add new entry
    const entry: CacheEntry<T> = {
      value,
      timestamp: Date.now(),
      accessCount: 0,
      lastAccessed: Date.now(),
      size,
    };

    this.cache.set(cacheKey, entry);
    this.accessOrder.add(cacheKey);
    this.stats.itemCount++;
    this.stats.totalSize += size;

    // Evict if necessary
    this.evictIfNeeded();
  }

  /**
   * Delete a value from the cache
   */
  delete(key: any): boolean {
    const cacheKey = this.fastHash(key);
    const entry = this.cache.get(cacheKey);

    if (entry) {
      this.stats.totalSize -= entry.size;
      this.stats.itemCount--;
      this.cache.delete(cacheKey);
      this.accessOrder.delete(cacheKey);
      return true;
    }

    return false;
  }

  /**
   * Check if a key exists in the cache
   */
  has(key: any): boolean {
    const cacheKey = this.fastHash(key);
    return this.cache.has(cacheKey);
  }

  /**
   * Clear all entries from the cache
   */
  clear(): void {
    this.cache.clear();
    this.accessOrder.clear();
    this.stats = {
      hits: 0,
      misses: 0,
      evictions: 0,
      totalSize: 0,
      itemCount: 0,
      hitRate: 0,
    };
  }

  /**
   * Get cache statistics
   */
  getStats(): CacheStats {
    return { ...this.stats };
  }

  /**
   * Get all cache keys (for debugging)
   */
  keys(): any[] {
    return Array.from(this.cache.keys()).map(key => {
      // Try to reverse the hash (limited success, but useful for debugging)
      return key;
    });
  }

  /**
   * Evict entries using LRU strategy when limits are exceeded
   */
  private evictIfNeeded(): void {
    // Evict by size limit
    while (this.stats.totalSize > this.maxSize && this.accessOrder.size > 0) {
      this.evictLRU();
    }

    // Evict by item count limit
    while (this.stats.itemCount > this.maxItems && this.accessOrder.size > 0) {
      this.evictLRU();
    }
  }

  /**
   * Evict the least recently used item
   */
  private evictLRU(): void {
    const lruKey = this.accessOrder.values().next().value;
    if (lruKey) {
      const entry = this.cache.get(lruKey);
      if (entry) {
        this.stats.totalSize -= entry.size;
        this.stats.itemCount--;
        this.stats.evictions++;
        this.cache.delete(lruKey);
        this.accessOrder.delete(lruKey);
      }
    }
  }

  /**
   * Update the hit rate calculation
   */
  private updateHitRate(): void {
    const total = this.stats.hits + this.stats.misses;
    this.stats.hitRate = total > 0 ? this.stats.hits / total : 0;
  }

  /**
   * Compress large objects (future enhancement)
   */
  private compress(value: T): string {
    // Placeholder for compression - could use LZ-string or similar
    return JSON.stringify(value);
  }

  /**
   * Decompress objects (future enhancement)
   */
  private decompress(compressed: string): T {
    // Placeholder for decompression
    return JSON.parse(compressed);
  }
}

/**
 * Specialized cache for conditional field evaluation results
 */
export class ConditionalCache {
  private cache: LRUCache<any>;
  private readonly enableCompression: boolean;
  private readonly compressionThreshold: number;

  constructor() {
    const config = configManager.getConfig();
    this.cache = new LRUCache(
      config.cacheSize * 1024, // Convert KB to bytes
      config.cacheSize // Max items = cache size setting
    );
    this.enableCompression = false; // Can be enabled in config later
    this.compressionThreshold = 1024; // Compress objects > 1KB
  }

  /**
   * Get cached evaluation result
   */
  get(formData: Record<string, any>): any {
    return this.cache.get(formData);
  }

  /**
   * Cache evaluation result
   */
  set(formData: Record<string, any>, result: any): void {
    // Estimate result size for better cache management
    const resultSize = this.estimateResultSize(result);
    this.cache.set(formData, result, resultSize);
  }

  /**
   * Check if result is cached
   */
  has(formData: Record<string, any>): boolean {
    return this.cache.has(formData);
  }

  /**
   * Clear all cached results
   */
  clear(): void {
    this.cache.clear();
  }

  /**
   * Get cache performance statistics
   */
  getStats(): CacheStats {
    return this.cache.getStats();
  }

  /**
   * Estimate the size of an evaluation result
   */
  private estimateResultSize(result: any): number {
    if (!result || !result.fields) {
      return 64; // Base size for empty result
    }

    // Estimate based on number of fields
    const fieldCount = Object.keys(result.fields).length;
    return 128 + fieldCount * 64; // Base + per-field overhead
  }
}

// Export singleton instance
export const conditionalCache = new ConditionalCache();
