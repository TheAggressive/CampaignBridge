/**
 * Entry point for conditional fields functionality
 */

import { ConditionalEngine } from './condition-fields';

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
  const conditionalData = (window as any).campaignbridgeConditionals;

  if (
    conditionalData &&
    conditionalData.formId &&
    conditionalData.conditionals
  ) {
    new ConditionalEngine(conditionalData.formId, conditionalData.conditionals);
  }
});
