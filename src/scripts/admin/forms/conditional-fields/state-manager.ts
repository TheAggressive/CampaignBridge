/**
 * Manages conditional field state, caching, and form data collection
 */

import { ConditionalDataCollector } from './data-collector';
import type {
  ConditionalApiResponse,
  ConditionalEngineConfig,
  FormData,
} from './types';

export class ConditionalStateManager {
  private evaluationCache = new Map<string, ConditionalApiResponse>();
  private lastFormData: FormData | null = null;
  private readonly maxCacheSize: number;

  constructor(config: ConditionalEngineConfig) {
    this.maxCacheSize = config.cacheSize ?? 10;
  }

  /**
   * Get cached evaluation result for form data
   */
  public getCachedResult(formData: FormData): ConditionalApiResponse | null {
    const cacheKey = JSON.stringify(formData);
    return this.evaluationCache.get(cacheKey) ?? null;
  }

  /**
   * Cache evaluation result
   */
  public cacheResult(formData: FormData, result: ConditionalApiResponse): void {
    const cacheKey = JSON.stringify(formData);
    this.evaluationCache.set(cacheKey, result);

    // Maintain cache size limit
    if (this.evaluationCache.size > this.maxCacheSize) {
      const firstKey = this.evaluationCache.keys().next().value;
      this.evaluationCache.delete(firstKey);
    }
  }

  /**
   * Check if form data has changed since last evaluation
   */
  public hasFormDataChanged(formData: FormData): boolean {
    if (!this.lastFormData) {
      return true;
    }

    return JSON.stringify(formData) !== JSON.stringify(this.lastFormData);
  }

  /**
   * Update last form data
   */
  public updateLastFormData(formData: FormData): void {
    this.lastFormData = { ...formData };
  }

  /**
   * Collect form data using the data collector
   */
  public collectFormData(form: HTMLFormElement, formId: string): FormData {
    const collector = new ConditionalDataCollector(form, formId);
    return collector.getFormData();
  }

  /**
   * Clear all cached data
   */
  public clearCache(): void {
    this.evaluationCache.clear();
    this.lastFormData = null;
  }

  /**
   * Get cache statistics for debugging
   */
  public getCacheStats(): { size: number; maxSize: number } {
    return {
      size: this.evaluationCache.size,
      maxSize: this.maxCacheSize,
    };
  }
}
