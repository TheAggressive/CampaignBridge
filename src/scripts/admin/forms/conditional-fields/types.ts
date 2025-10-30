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
}
