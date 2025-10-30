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
