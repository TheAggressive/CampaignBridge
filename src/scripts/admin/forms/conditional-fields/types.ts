/**
 * Type definitions for conditional fields functionality
 */

export interface FieldState {
  visible: boolean;
  required: boolean;
}

export interface FieldStateMap {
  [fieldId: string]: FieldState;
}

export interface ConditionalApiRequest {
  action: string;
  form_id: string;
  data: Record<string, string>;
  nonce: string;
}

export interface ConditionalApiResponse {
  success: boolean;
  fields?: FieldStateMap;
  message?: string;
}

export interface FormData {
  [fieldId: string]: string;
}

export interface ConditionalEngineConfig {
  formId: string;
  apiEndpoint?: string;
  ajaxAction?: string;
  debounceDelay?: number;
  requestTimeout?: number;
  cacheSize?: number;
  maxRetries?: number;
  enableDebugLogging?: boolean;
  enablePerformanceMonitoring?: boolean;
  validationRules?: Record<string, import('./validation').ValidationRule>;
}

export interface EvaluationResult {
  success: boolean;
  fields?: FieldStateMap;
  error?: string;
}

export interface AccessibilityAnnouncement {
  message: string;
  priority?: 'polite' | 'assertive';
}

export interface IConditionalAccessibility {
  handleFieldHidden(field: HTMLElement, fieldId: string): void;
  getFieldLabel(field: HTMLElement, fieldId: string): string;
  announceFieldChanges(changes: string[]): void;
  updateFieldAccessibility(
    field: HTMLElement,
    state: FieldState,
    fieldId: string
  ): void;
  announceValidationErrors(errors: string[]): void;
  clearValidationErrors(): void;
  enhanceFieldAria(
    field: HTMLElement,
    state: FieldState,
    fieldId: string
  ): void;
  setupFormLandmarks(): void;
  setKeyboardNavigation(enabled: boolean): void;
  getAccessibilityStatus(): {
    keyboardNavigation: boolean;
    skipLinks: number;
    liveRegions: number;
    errorAnnouncer: boolean;
  };
  destroy(): void;
}

// Testing interfaces for better testability and mocking
export interface IConditionalEngine {
  evaluateConditions(): Promise<void>;
  destroy(): void;
  getCacheStats(): ReturnType<
    typeof import('./cache').conditionalCache.getStats
  >;
}

export interface IConditionalStateManager {
  getCachedResult(formData: FormData): ConditionalApiResponse | null;
  cacheResult(formData: FormData, result: ConditionalApiResponse): void;
  collectFormData(
    form: HTMLFormElement,
    formId: string,
    validationRules?: Record<string, import('./validation').ValidationRule>
  ): FormData;
  getCacheStats(): ReturnType<
    typeof import('./cache').conditionalCache.getStats
  >;
  clearCache(): void;
  updateLastFormData(formData: FormData): void;
}

export interface IConditionalApiService {
  evaluateConditions(
    apiEndpoint: string,
    requestPayload: ConditionalApiRequest
  ): Promise<EvaluationResult>;
  isEvaluationInProgress(): boolean;
  cancelEvaluation(): void;
}

export interface IConditionalUIManager {
  initialize(): void;
  updateFields(
    fieldStates: FieldStateMap,
    accessibilityManager: IConditionalAccessibility
  ): void;
  showLoading(): void;
  hideLoading(): void;
  showError(message: string): void;
  hideError(): void;
  setRetryCallback(callback: () => void): void;
  showValidationErrors(
    errors: string[],
    accessibilityManager: IConditionalAccessibility
  ): void;
  clearValidationErrors(accessibilityManager: IConditionalAccessibility): void;
  destroy(): void;
}

export interface IConditionalDataCollector {
  getFormData(): Record<string, string>;
  setValidationRules(
    rules: Record<string, import('./validation').ValidationRule>
  ): void;
  parseFieldName(fullName: string): string | null;
}

export interface IConditionalCache {
  get(formData: FormData): ConditionalApiResponse | null;
  set(formData: FormData, result: ConditionalApiResponse): void;
  delete(formData: FormData): boolean;
  clear(): void;
  getStats(): {
    hits: number;
    misses: number;
    evictions: number;
    totalSize: number;
    itemCount: number;
    hitRate: number;
  };
}

export interface IConditionalValidator {
  addRule(fieldName: string, rule: import('./validation').ValidationRule): void;
  validateField(
    fieldName: string,
    value: any,
    context?: Record<string, any>
  ): import('./validation').ValidationResult;
  validateForm(data: Record<string, any>): {
    isValid: boolean;
    errors: string[];
    sanitizedData: Record<string, any>;
  };
  getCommonRules(): Record<string, import('./validation').ValidationRule>;
}

export interface IConditionalApiClient {
  evaluate(
    apiEndpoint: string,
    payload: ConditionalApiRequest
  ): Promise<ConditionalApiResponse>;
}

export interface IPerformanceMonitor {
  startTimer(operation: string): string;
  endTimer(timerId: string): { duration: number; operation: string };
  recordMetric(
    name: string,
    value: number,
    unit: string,
    context?: Record<string, any>
  ): void;
  recordApiCall(endpoint: string, duration: number, success: boolean): void;
  recordDomOperation(
    operation: string,
    operationsCount: number,
    duration: number
  ): void;
  recordMemoryUsage(): void;
  getStats(): {
    totalMetrics: number;
    activeTimers: number;
    memoryUsage?: {
      used: number;
      total: number;
      limit: number;
    };
    recentMetrics: import('./performance-monitor').PerformanceMetric[];
  };
  generateReport(): import('./performance-monitor').PerformanceReport;
  exportMetrics(): import('./performance-monitor').PerformanceMetric[];
  shouldLogMetric(
    metric: import('./performance-monitor').PerformanceMetric
  ): boolean;
}

export interface IConfigManager {
  getConfig(): ConditionalEngineConfig;
  updateConfig(newConfig: Partial<ConditionalEngineConfig>): void;
}

// Mock implementations for testing (exported for test utilities)
export class MockConditionalCache implements IConditionalCache {
  private cache = new Map<string, ConditionalApiResponse>();

  get(formData: FormData): ConditionalApiResponse | null {
    const key = JSON.stringify(formData);
    return this.cache.get(key) || null;
  }

  set(formData: FormData, result: ConditionalApiResponse): void {
    const key = JSON.stringify(formData);
    this.cache.set(key, result);
  }

  delete(formData: FormData): boolean {
    const key = JSON.stringify(formData);
    return this.cache.delete(key);
  }

  clear(): void {
    this.cache.clear();
  }

  getStats() {
    return {
      hits: 0,
      misses: 0,
      evictions: 0,
      totalSize: 0,
      itemCount: this.cache.size,
      hitRate: 0,
    };
  }
}

export class MockPerformanceMonitor implements IPerformanceMonitor {
  private metrics: import('./performance-monitor').PerformanceMetric[] = [];

  startTimer(operation: string): string {
    return `timer_${operation}_${Date.now()}`;
  }

  endTimer(timerId: string): { duration: number; operation: string } {
    return { duration: 100, operation: timerId.split('_')[1] };
  }

  recordMetric(
    name: string,
    value: number,
    unit: string,
    context?: Record<string, any>
  ): void {
    this.metrics.push({
      type: 'metric',
      name,
      value,
      unit,
      timestamp: Date.now(),
      context,
    });
  }

  recordApiCall(endpoint: string, duration: number, success: boolean): void {
    this.recordMetric('api_call', duration, 'ms', { endpoint, success });
  }

  recordDomOperation(
    operation: string,
    operationsCount: number,
    duration: number
  ): void {
    this.recordMetric('dom_operation', duration, 'ms', {
      operation,
      operationsCount,
    });
  }

  recordMemoryUsage(): void {
    // Mock memory recording
  }

  getStats() {
    return {
      totalMetrics: this.metrics.length,
      activeTimers: 0,
      memoryUsage: undefined,
      recentMetrics: this.metrics.slice(-10),
    };
  }

  generateReport(): import('./performance-monitor').PerformanceReport {
    return {
      summary: {
        totalMetrics: this.metrics.length,
        averageResponseTime: 100,
        memoryPeak: 0,
        errorRate: 0,
      },
      metrics: this.metrics,
      recommendations: [],
    };
  }

  exportMetrics(): import('./performance-monitor').PerformanceMetric[] {
    return [...this.metrics];
  }

  shouldLogMetric(
    metric: import('./performance-monitor').PerformanceMetric
  ): boolean {
    return metric.value > 1000; // Mock threshold
  }
}
