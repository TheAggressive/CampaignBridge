/**
 * CampaignBridge Form Validation System
 *
 * Main entry point for real-time form validation functionality.
 * Provides comprehensive validation for all form field types with instant feedback.
 *
 * @package CampaignBridge
 */

// Export everything for programmatic use
export { FormValidator, initializeFormValidation } from './FormValidator';
export { ValidationPatterns, ValidationPresets } from './types';
export type {
  FieldValidationState,
  ValidatableFieldType,
  ValidationConfig,
  ValidationEventType,
  ValidationFeedbackType,
  ValidationResult,
  ValidationRule,
} from './types';

// Initialize validation automatically on page load
import { initializeFormValidation } from './FormValidator';

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initializeFormValidation);
} else {
  initializeFormValidation();
}
