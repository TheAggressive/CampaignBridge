/**
 * Manages conditional field state, caching, and form data collection
 */

import { conditionalCache } from './cache';
import { ConditionalDataCollector } from './data-collector';
import type {
  ConditionalApiResponse,
  ConditionalEngineConfig,
  FormData,
} from './types';

export class ConditionalStateManager {
  private lastFormData: FormData | null = null;

  constructor(
    // eslint-disable-next-line no-unused-vars -- Reserved for future use; cache is now managed by ConditionalCache singleton.
    config: ConditionalEngineConfig
  ) {
    // Cache is now managed by ConditionalCache singleton.
  }

  /**
   * Get cached evaluation result for form data
   */
  public getCachedResult(formData: FormData): ConditionalApiResponse | null {
    return conditionalCache.get(formData) ?? null;
  }

  /**
   * Cache evaluation result
   */
  public cacheResult(formData: FormData, result: ConditionalApiResponse): void {
    conditionalCache.set(formData, result);
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
  public collectFormData(
    form: HTMLFormElement,
    formId: string,
    validationRules?: Record<string, import('./validation').ValidationRule>
  ): FormData {
    const collector = new ConditionalDataCollector(form, formId);

    // Apply custom validation rules if provided
    if (validationRules) {
      collector.setValidationRules(validationRules);
    }

    return collector.getFormData();
  }

  /**
   * Get cache performance statistics
   */
  public getCacheStats(): import('./cache').CacheStats {
    return conditionalCache.getStats();
  }

  /**
   * Clear all cached data
   */
  public clearCache(): void {
    conditionalCache.clear();
    this.lastFormData = null;
  }
}
