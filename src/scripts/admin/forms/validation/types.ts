/**
 * TypeScript types and interfaces for form validation functionality
 *
 * @package CampaignBridge
 */

/**
 * Validation rule configuration
 */
export interface ValidationRule {
  /** Rule name for identification */
  name: string;

  /** Rule type (required, email, minLength, etc.) */
  type:
    | 'required'
    | 'email'
    | 'url'
    | 'minLength'
    | 'maxLength'
    | 'pattern'
    | 'numeric'
    | 'min'
    | 'max'
    | 'custom';

  /** Rule-specific value (minLength: 5, pattern: regex, etc.) */
  value?: number | string;

  /** Regex pattern for pattern validation */
  pattern?: string;

  /** Custom validator function */
  validator?: (
    value: string,
    field: HTMLInputElement
  ) =>
    | Promise<{ isValid: boolean; error?: string }>
    | { isValid: boolean; error?: string };

  /** Error message to display when validation fails */
  message?: string;

  /** Stop validation chain on this rule failure */
  stopOnFail?: boolean;

  /** Additional rule-specific options */
  options?: Record<string, any>;
}

/**
 * Validation result
 */
export interface ValidationResult {
  /** Whether the field value is valid */
  isValid: boolean;

  /** Array of error messages */
  errors: string[];
}

/**
 * Field validation state
 */
export interface FieldValidationState {
  /** Current validation state */
  isValid: boolean;

  /** Whether the field has been modified */
  isDirty: boolean;

  /** Current error messages */
  errors: string[];

  /** Last validation timestamp */
  lastValidation: Date | null;
}

/**
 * Form validation configuration
 */
export interface ValidationConfig {
  /** Debounce delay for input validation (ms) */
  debounceDelay?: number;

  /** Whether to show success states */
  showSuccessStates?: boolean;

  /** Whether to enable accessibility features */
  enableAccessibility?: boolean;

  /** Custom validation messages */
  messages?: Record<string, string>;

  /** Custom CSS classes */
  classes?: {
    valid?: string;
    invalid?: string;
    validating?: string;
    error?: string;
    success?: string;
  };
}

/**
 * Validation rule presets for common use cases
 */
export const ValidationPresets = {
  /** Required field validation */
  required: (message?: string): ValidationRule => ({
    name: 'required',
    type: 'required',
    message: message || 'This field is required',
  }),

  /** Email validation */
  email: (message?: string): ValidationRule => ({
    name: 'email',
    type: 'email',
    message: message || 'Please enter a valid email address',
  }),

  /** URL validation */
  url: (message?: string): ValidationRule => ({
    name: 'url',
    type: 'url',
    message: message || 'Please enter a valid URL',
  }),

  /** Minimum length validation */
  minLength: (length: number, message?: string): ValidationRule => ({
    name: 'minLength',
    type: 'minLength',
    value: length,
    message: message || `Minimum length is ${length} characters`,
  }),

  /** Maximum length validation */
  maxLength: (length: number, message?: string): ValidationRule => ({
    name: 'maxLength',
    type: 'maxLength',
    value: length,
    message: message || `Maximum length is ${length} characters`,
  }),

  /** Pattern validation */
  pattern: (pattern: string, message?: string): ValidationRule => ({
    name: 'pattern',
    type: 'pattern',
    pattern,
    message: message || 'Invalid format',
  }),

  /** Numeric validation */
  numeric: (message?: string): ValidationRule => ({
    name: 'numeric',
    type: 'numeric',
    message: message || 'Please enter a valid number',
  }),

  /** Minimum value validation */
  min: (value: number, message?: string): ValidationRule => ({
    name: 'min',
    type: 'min',
    value,
    message: message || `Minimum value is ${value}`,
  }),

  /** Maximum value validation */
  max: (value: number, message?: string): ValidationRule => ({
    name: 'max',
    type: 'max',
    value,
    message: message || `Maximum value is ${value}`,
  }),
};

/**
 * Common validation patterns
 */
export const ValidationPatterns = {
  /** Phone number pattern */
  phone: /^[\+]?[1-9][\d]{0,15}$/,

  /** Postal code pattern (US) */
  postalCodeUS: /^\d{5}(-\d{4})?$/,

  /** Credit card number pattern */
  creditCard: /^\d{4}\s?\d{4}\s?\d{4}\s?\d{4}$/,

  /** Username pattern (alphanumeric, underscore, dash) */
  username: /^[a-zA-Z0-9_-]+$/,

  /** Password pattern (at least 8 chars, 1 uppercase, 1 lowercase, 1 number) */
  password: /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z\d@$!%*?&]{8,}$/,

  /** Hex color pattern */
  hexColor: /^#[0-9A-Fa-f]{6}$/,

  /** Slug pattern */
  slug: /^[a-z0-9-]+$/,

  /** API key pattern (alphanumeric with dashes/underscores) */
  apiKey: /^[a-zA-Z0-9_-]+$/,
};

/**
 * Validation event types
 */
export type ValidationEventType = 'input' | 'blur' | 'change' | 'submit';

/**
 * Validation feedback types
 */
export type ValidationFeedbackType = 'success' | 'error' | 'warning' | 'info';

/**
 * Field types that support validation
 */
export type ValidatableFieldType =
  | 'text'
  | 'email'
  | 'url'
  | 'password'
  | 'search'
  | 'tel'
  | 'number'
  | 'date'
  | 'time'
  | 'datetime-local'
  | 'month'
  | 'week'
  | 'color'
  | 'file'
  | 'textarea'
  | 'select';



