/**
 * Entry point for conditional fields functionality
 */

import { ConditionalEngine } from './conditional-fields';

export { ConditionalEngine } from './conditional-fields';
export type {
  AccessibilityAnnouncement,
  ConditionalApiRequest,
  ConditionalApiResponse,
  ConditionalEngineConfig,
  EvaluationResult,
  FieldState,
  FieldStateMap,
  FormData,
} from './types';

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
