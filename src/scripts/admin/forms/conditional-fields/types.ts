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
  handleFieldHidden(
    // eslint-disable-next-line no-unused-vars -- Parameter name in interface method signature is for documentation.
    field: HTMLElement,
    // eslint-disable-next-line no-unused-vars -- Parameter name in interface method signature is for documentation.
    fieldId: string
  ): void;
  getFieldLabel(
    // eslint-disable-next-line no-unused-vars -- Parameter name in interface method signature is for documentation.
    field: HTMLElement,
    // eslint-disable-next-line no-unused-vars -- Parameter name in interface method signature is for documentation.
    fieldId: string
  ): string;
  announceFieldChanges(
    // eslint-disable-next-line no-unused-vars -- Parameter name in interface method signature is for documentation.
    changes: string[]
  ): void;
  updateFieldAccessibility(
    // eslint-disable-next-line no-unused-vars -- Parameter name in interface method signature is for documentation.
    field: HTMLElement,
    // eslint-disable-next-line no-unused-vars -- Parameter name in interface method signature is for documentation.
    state: FieldState,
    // eslint-disable-next-line no-unused-vars -- Parameter name in interface method signature is for documentation.
    fieldId: string
  ): void;
  announceValidationErrors(
    // eslint-disable-next-line no-unused-vars -- Parameter name in interface method signature is for documentation.
    errors: string[]
  ): void;
  clearValidationErrors(): void;
  enhanceFieldAria(
    // eslint-disable-next-line no-unused-vars -- Parameter name in interface method signature is for documentation.
    field: HTMLElement,
    // eslint-disable-next-line no-unused-vars -- Parameter name in interface method signature is for documentation.
    state: FieldState,
    // eslint-disable-next-line no-unused-vars -- Parameter name in interface method signature is for documentation.
    fieldId: string
  ): void;
  setupFormLandmarks(): void;
  // eslint-disable-next-line no-unused-vars -- Parameter name in interface method signature is for documentation.
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
  getCachedResult(
    // eslint-disable-next-line no-unused-vars -- Parameter name in interface method signature is for documentation.
    formData: FormData
  ): ConditionalApiResponse | null;
  cacheResult(
    // eslint-disable-next-line no-unused-vars -- Parameter name in interface method signature is for documentation.
    formData: FormData,
    // eslint-disable-next-line no-unused-vars -- Parameter name in interface method signature is for documentation.
    result: ConditionalApiResponse
  ): void;
  collectFormData(
    // eslint-disable-next-line no-unused-vars -- Parameter name in interface method signature is for documentation.
    form: HTMLFormElement,
    // eslint-disable-next-line no-unused-vars -- Parameter name in interface method signature is for documentation.
    formId: string,
    // eslint-disable-next-line no-unused-vars -- Parameter name in interface method signature is for documentation.
    validationRules?: Record<string, import('./validation').ValidationRule>
  ): FormData;
  getCacheStats(): ReturnType<
    typeof import('./cache').conditionalCache.getStats
  >;
  clearCache(): void;
  updateLastFormData(
    // eslint-disable-next-line no-unused-vars -- Parameter name in interface method signature is for documentation.
    formData: FormData
  ): void;
}

export interface IConditionalApiService {
  evaluateConditions(
    // eslint-disable-next-line no-unused-vars -- Parameter name in interface method signature is for documentation.
    apiEndpoint: string,
    // eslint-disable-next-line no-unused-vars -- Parameter name in interface method signature is for documentation.
    requestPayload: ConditionalApiRequest
  ): Promise<EvaluationResult>;
  isEvaluationInProgress(): boolean;
  cancelEvaluation(): void;
}

export interface IConditionalUIManager {
  initialize(): void;
  updateFields(
    // eslint-disable-next-line no-unused-vars -- Parameter name in interface method signature is for documentation.
    fieldStates: FieldStateMap,
    // eslint-disable-next-line no-unused-vars -- Parameter name in interface method signature is for documentation.
    accessibilityManager: IConditionalAccessibility
  ): void;
  showLoading(): void;
  hideLoading(): void;
  showError(
    // eslint-disable-next-line no-unused-vars -- Parameter name in interface method signature is for documentation.
    message: string
  ): void;
  hideError(): void;
  setRetryCallback(
    // eslint-disable-next-line no-unused-vars -- Parameter name in interface method signature is for documentation.
    callback: () => void
  ): void;
  showValidationErrors(
    // eslint-disable-next-line no-unused-vars -- Parameter name in interface method signature is for documentation.
    errors: string[],
    // eslint-disable-next-line no-unused-vars -- Parameter name in interface method signature is for documentation.
    accessibilityManager: IConditionalAccessibility
  ): void;
  clearValidationErrors(
    // eslint-disable-next-line no-unused-vars -- Parameter name in interface method signature is for documentation.
    accessibilityManager: IConditionalAccessibility
  ): void;
  destroy(): void;
}

export interface IConditionalDataCollector {
  getFormData(): Record<string, string>;
  setValidationRules(
    // eslint-disable-next-line no-unused-vars -- Parameter name in interface method signature is for documentation.
    rules: Record<string, import('./validation').ValidationRule>
  ): void;
  // eslint-disable-next-line no-unused-vars -- Parameter name in interface method signature is for documentation.
  parseFieldName(fullName: string): string | null;
}

export interface IConditionalCache {
  // eslint-disable-next-line no-unused-vars -- Parameter name in interface method signature is for documentation.
  get(formData: FormData): ConditionalApiResponse | null;
  // eslint-disable-next-line no-unused-vars -- Parameter name in interface method signature is for documentation.
  set(formData: FormData, result: ConditionalApiResponse): void;
  // eslint-disable-next-line no-unused-vars -- Parameter name in interface method signature is for documentation.
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
  addRule(
    // eslint-disable-next-line no-unused-vars -- Parameter name in interface method signature is for documentation.
    fieldName: string,
    // eslint-disable-next-line no-unused-vars -- Parameter name in interface method signature is for documentation.
    rule: import('./validation').ValidationRule
  ): void;
  validateField(
    // eslint-disable-next-line no-unused-vars -- Parameter name in interface method signature is for documentation.
    fieldName: string,
    // eslint-disable-next-line no-unused-vars -- Parameter name in interface method signature is for documentation.
    value: any,
    // eslint-disable-next-line no-unused-vars -- Parameter name in interface method signature is for documentation.
    context?: Record<string, any>
  ): import('./validation').ValidationResult;
  validateForm(
    // eslint-disable-next-line no-unused-vars -- Parameter name in interface method signature is for documentation.
    data: Record<string, any>
  ): {
    isValid: boolean;
    errors: string[];
    sanitizedData: Record<string, any>;
  };
  getCommonRules(): Record<string, import('./validation').ValidationRule>;
}

export interface IConditionalApiClient {
  evaluate(
    // eslint-disable-next-line no-unused-vars -- Parameter name in interface method signature is for documentation.
    apiEndpoint: string,
    // eslint-disable-next-line no-unused-vars -- Parameter name in interface method signature is for documentation.
    payload: ConditionalApiRequest
  ): Promise<ConditionalApiResponse>;
}

export interface IPerformanceMonitor {
  startTimer(
    // eslint-disable-next-line no-unused-vars -- Parameter name in interface method signature is for documentation.
    operation: string
  ): string;
  endTimer(
    // eslint-disable-next-line no-unused-vars -- Parameter name in interface method signature is for documentation.
    timerId: string
  ): { duration: number; operation: string };
  recordMetric(
    // eslint-disable-next-line no-unused-vars -- Parameter name in interface method signature is for documentation.
    name: string,
    // eslint-disable-next-line no-unused-vars -- Parameter name in interface method signature is for documentation.
    value: number,
    // eslint-disable-next-line no-unused-vars -- Parameter name in interface method signature is for documentation.
    unit: string,
    // eslint-disable-next-line no-unused-vars -- Parameter name in interface method signature is for documentation.
    context?: Record<string, any>
  ): void;
  recordApiCall(
    // eslint-disable-next-line no-unused-vars -- Parameter name in interface method signature is for documentation.
    endpoint: string,
    // eslint-disable-next-line no-unused-vars -- Parameter name in interface method signature is for documentation.
    duration: number,
    // eslint-disable-next-line no-unused-vars -- Parameter name in interface method signature is for documentation.
    success: boolean
  ): void;
  recordDomOperation(
    // eslint-disable-next-line no-unused-vars -- Parameter name in interface method signature is for documentation.
    operation: string,
    // eslint-disable-next-line no-unused-vars -- Parameter name in interface method signature is for documentation.
    operationsCount: number,
    // eslint-disable-next-line no-unused-vars -- Parameter name in interface method signature is for documentation.
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
    // eslint-disable-next-line no-unused-vars -- Parameter name in interface method signature is for documentation.
    metric: import('./performance-monitor').PerformanceMetric
  ): boolean;
}

export interface IConfigManager {
  getConfig(): ConditionalEngineConfig;
  updateConfig(
    // eslint-disable-next-line no-unused-vars -- Parameter name in interface method signature is for documentation.
    newConfig: Partial<ConditionalEngineConfig>
  ): void;
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
    unit: 'ms' | 'bytes' | 'count' | 'ratio',
    context?: Record<string, any>
  ): void {
    this.metrics.push({
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
      period: {
        start: Date.now() - 3600000,
        end: Date.now(),
        duration: 3600000,
      },
      metrics: this.metrics,
      summary: {
        totalApiCalls: 0,
        averageApiResponseTime: 100,
        cacheHitRate: 0,
        errorRate: 0,
        memoryUsage: 0,
      },
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
