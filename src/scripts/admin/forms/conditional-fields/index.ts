/**
 * Entry point for conditional fields functionality
 */

import { ConditionalEngine } from './conditional-fields';

export { conditionalCache } from './cache';
export { ConditionalEngine } from './conditional-fields';
export { performanceMonitor } from './performance-monitor';
export { DataSanitizer, FormValidator } from './validation';

// Mock implementations for testing
export { MockConditionalCache, MockPerformanceMonitor } from './types';

// Type exports organized by module
export type { CacheStats } from './cache';
export type {
  PerformanceMetric,
  PerformanceReport,
} from './performance-monitor';
export type {
  AccessibilityAnnouncement,
  ConditionalApiRequest,
  ConditionalApiResponse,
  ConditionalEngineConfig,
  EvaluationResult,
  FieldState,
  FieldStateMap,
  FormData,
  IConditionalAccessibility,
  IConditionalApiClient,
  IConditionalApiService,
  IConditionalCache,
  IConditionalDataCollector,
  IConditionalEngine,
  IConditionalStateManager,
  IConditionalUIManager,
  IConditionalValidator,
  IConfigManager,
  IPerformanceMonitor,
} from './types';
export type {
  FieldValidationRules,
  ValidationResult,
  ValidationRule,
} from './validation';

// Expose ConditionalEngine globally for WordPress compatibility
(window as any).ConditionalEngine = ConditionalEngine;

// Initialize when DOM is ready.
document.addEventListener('DOMContentLoaded', () => {
  // Find all forms with conditional fields (marked by data-conditional attribute).
  const conditionalForms = document.querySelectorAll('form[data-conditional]');

  conditionalForms.forEach(form => {
    const formId = form.getAttribute('id');
    if (!formId) {
      return;
    }

    // Feature flag: default path is enabled; allow opting into explicit "v2".
    const engineAttr = form.getAttribute('data-conditional-engine');
    if (engineAttr && engineAttr !== 'v2') {
      // Unknown/disabled value: skip initialization gracefully.
      return;
    }

    new ConditionalEngine(formId);
  });
});
