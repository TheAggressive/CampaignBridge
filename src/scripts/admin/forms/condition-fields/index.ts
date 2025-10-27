/**
 * Entry point for conditional fields functionality
 */

import { ConditionalEngine } from './condition-fields';

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
  // Find all forms with conditional fields (marked by data-conditional attribute)
  const conditionalForms = document.querySelectorAll('form[data-conditional]');

  conditionalForms.forEach(form => {
    const formId = form.getAttribute('id');
    if (formId) {
      new ConditionalEngine(formId);
    }
  });
});
