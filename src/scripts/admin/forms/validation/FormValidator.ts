/**
 * Real-time Form Validation System
 *
 * Provides instant feedback for form validation across all CampaignBridge form fields.
 * Supports multiple validation rules, custom validators, and accessibility features.
 *
 * @package CampaignBridge
 */

import {
  FieldValidationState,
  ValidationConfig,
  ValidationResult,
  ValidationRule,
} from './types';

/**
 * Comprehensive form validation manager
 */
export class FormValidator {
  private static instance: FormValidator;
  private fieldStates: Map<string, FieldValidationState> = new Map();
  private rules: Map<string, ValidationRule[]> = new Map();
  private debounceTimers: Map<string, number> = new Map();
  private config: ValidationConfig;

  constructor(config: ValidationConfig = {}) {
    this.config = {
      debounceDelay: 300,
      showSuccessStates: true,
      enableAccessibility: true,
      ...config,
    };
  }

  /**
   * Get singleton instance
   */
  static getInstance(config?: ValidationConfig): FormValidator {
    if (!FormValidator.instance) {
      FormValidator.instance = new FormValidator(config);
    }
    return FormValidator.instance;
  }

  /**
   * Initialize validation for a form field
   */
  initializeField(
    fieldId: string,
    rules: ValidationRule[],
    options: {
      validateOnInput?: boolean;
      validateOnBlur?: boolean;
      validateOnChange?: boolean;
    } = {}
  ): void {
    const field = document.getElementById(fieldId) as HTMLInputElement;
    if (!field) return;

    // Store validation rules
    this.rules.set(fieldId, rules);

    // Initialize field state
    this.fieldStates.set(fieldId, {
      isValid: true,
      isDirty: false,
      errors: [],
      lastValidation: null,
    });

    // Set default options
    const config = {
      validateOnInput: true,
      validateOnBlur: true,
      validateOnChange: true,
      ...options,
    };

    // Add event listeners
    if (config.validateOnInput) {
      field.addEventListener('input', () => this.handleValidation(fieldId));
    }

    if (config.validateOnBlur) {
      field.addEventListener('blur', () =>
        this.handleValidation(fieldId, true)
      );
    }

    if (config.validateOnChange) {
      field.addEventListener('change', () => this.handleValidation(fieldId));
    }

    // Add validation attributes for accessibility
    if (this.config.enableAccessibility) {
      this.setupAccessibility(field);
    }
  }

  /**
   * Validate a specific field
   */
  async validateField(
    fieldId: string,
    force: boolean = false
  ): Promise<ValidationResult> {
    const field = document.getElementById(fieldId) as HTMLInputElement;
    const rules = this.rules.get(fieldId);

    if (!field || !rules) {
      return { isValid: true, errors: [] };
    }

    // Add validating state
    const fieldContainer = field.closest('.campaignbridge-field-wrapper');
    if (fieldContainer) {
      fieldContainer.classList.add('campaignbridge-field-wrapper--validating');
    }

    const value = field.value;
    const errors: string[] = [];

    // Run all validation rules
    for (const rule of rules) {
      const result = await this.validateRule(rule, value, field);
      if (!result.isValid && result.error) {
        errors.push(result.error);
        if (rule.stopOnFail) break;
      }
    }

    const isValid = errors.length === 0;
    const result: ValidationResult = { isValid, errors };

    // Update field state
    const state = this.fieldStates.get(fieldId)!;
    state.isValid = isValid;
    state.errors = errors;
    state.isDirty = state.isDirty || value.length > 0;
    state.lastValidation = new Date();

    // Update UI
    this.updateFieldUI(fieldId, result);

    return result;
  }

  /**
   * Validate entire form
   */
  async validateForm(formId: string): Promise<boolean> {
    const form = document.getElementById(formId);
    if (!form) return false;

    const fields = Array.from(form.querySelectorAll('input, select, textarea'))
      .map(el => el.id)
      .filter(id => this.rules.has(id));

    let isFormValid = true;

    for (const fieldId of fields) {
      const result = await this.validateField(fieldId, true);
      if (!result.isValid) {
        isFormValid = false;
      }
    }

    return isFormValid;
  }

  /**
   * Add custom validation rule
   */
  addRule(fieldId: string, rule: ValidationRule): void {
    const existing = this.rules.get(fieldId) || [];
    existing.push(rule);
    this.rules.set(fieldId, existing);
  }

  /**
   * Remove validation rule
   */
  removeRule(fieldId: string, ruleName: string): void {
    const rules = this.rules.get(fieldId);
    if (rules) {
      const filtered = rules.filter(rule => rule.name !== ruleName);
      this.rules.set(fieldId, filtered);
    }
  }

  /**
   * Get field validation state
   */
  getFieldState(fieldId: string): FieldValidationState | null {
    return this.fieldStates.get(fieldId) || null;
  }

  /**
   * Reset field validation
   */
  resetField(fieldId: string): void {
    const state = this.fieldStates.get(fieldId);
    if (state) {
      state.isValid = true;
      state.isDirty = false;
      state.errors = [];
      state.lastValidation = null;
    }

    this.updateFieldUI(fieldId, { isValid: true, errors: [] });
  }

  /**
   * Handle validation with debouncing
   */
  private handleValidation(fieldId: string, force: boolean = false): void {
    // Clear existing timer
    const existingTimer = this.debounceTimers.get(fieldId);
    if (existingTimer) {
      clearTimeout(existingTimer);
    }

    if (force) {
      // Immediate validation on blur
      this.validateField(fieldId);
    } else {
      // Debounced validation on input
      const timer = setTimeout(() => {
        this.validateField(fieldId);
      }, this.config.debounceDelay);

      this.debounceTimers.set(fieldId, timer);
    }
  }

  /**
   * Validate a single rule
   */
  private async validateRule(
    rule: ValidationRule,
    value: string,
    field: HTMLInputElement
  ): Promise<{ isValid: boolean; error?: string }> {
    try {
      // Built-in validation rules
      switch (rule.type) {
        case 'required':
          if (!value.trim()) {
            return {
              isValid: false,
              error: rule.message || 'This field is required',
            };
          }
          break;

        case 'email':
          if (value && !this.isValidEmail(value)) {
            return {
              isValid: false,
              error: rule.message || 'Please enter a valid email address',
            };
          }
          break;

        case 'url':
          if (value && !this.isValidUrl(value)) {
            return {
              isValid: false,
              error: rule.message || 'Please enter a valid URL',
            };
          }
          break;

        case 'minLength':
          if (value && value.length < Number(rule.value || 0)) {
            return {
              isValid: false,
              error:
                rule.message || `Minimum length is ${rule.value} characters`,
            };
          }
          break;

        case 'maxLength':
          if (value && value.length > Number(rule.value || 0)) {
            return {
              isValid: false,
              error:
                rule.message || `Maximum length is ${rule.value} characters`,
            };
          }
          break;

        case 'pattern':
          if (value && rule.pattern && !new RegExp(rule.pattern).test(value)) {
            return { isValid: false, error: rule.message || 'Invalid format' };
          }
          break;

        case 'numeric':
          if (value && isNaN(Number(value))) {
            return {
              isValid: false,
              error: rule.message || 'Please enter a valid number',
            };
          }
          break;

        case 'min':
          if (
            value &&
            !isNaN(Number(value)) &&
            Number(value) < Number(rule.value || 0)
          ) {
            return {
              isValid: false,
              error: rule.message || `Minimum value is ${rule.value}`,
            };
          }
          break;

        case 'max':
          if (
            value &&
            !isNaN(Number(value)) &&
            Number(value) > Number(rule.value || 0)
          ) {
            return {
              isValid: false,
              error: rule.message || `Maximum value is ${rule.value}`,
            };
          }
          break;

        case 'custom':
          if (rule.validator) {
            const result = await rule.validator(value, field);
            if (!result.isValid) {
              return { isValid: false, error: result.error || rule.message };
            }
          }
          break;
      }

      return { isValid: true };
    } catch (error) {
      return {
        isValid: false,
        error: rule.message || 'Validation error occurred',
      };
    }
  }

  /**
   * Update field UI based on validation result
   */
  private updateFieldUI(fieldId: string, result: ValidationResult): void {
    const field = document.getElementById(fieldId);
    const fieldContainer = field?.closest('.campaignbridge-field-wrapper');
    const errorContainer = fieldContainer?.querySelector(
      '.campaignbridge-field__errors'
    );

    if (!fieldContainer) return;

    // Remove existing validation classes (including validating state)
    fieldContainer.classList.remove(
      'campaignbridge-field-wrapper--valid',
      'campaignbridge-field-wrapper--invalid',
      'campaignbridge-field-wrapper--validating'
    );

    // Add appropriate validation class
    if (result.isValid && this.config.showSuccessStates) {
      fieldContainer.classList.add('campaignbridge-field-wrapper--valid');
    } else if (!result.isValid) {
      fieldContainer.classList.add('campaignbridge-field-wrapper--invalid');
    }

    // Update error messages
    if (errorContainer) {
      errorContainer.innerHTML = '';

      if (!result.isValid && result.errors.length > 0) {
        result.errors.forEach(error => {
          const errorElement = document.createElement('div');
          errorElement.className = 'campaignbridge-field__error';
          errorElement.textContent = error;
          errorContainer.appendChild(errorElement);
        });
      }
    }

    // Update accessibility attributes
    if (this.config.enableAccessibility && field instanceof HTMLInputElement) {
      this.updateAccessibility(field, result);
    }
  }

  /**
   * Setup accessibility attributes
   */
  private setupAccessibility(field: HTMLInputElement): void {
    const fieldContainer = field.closest('.campaignbridge-field-wrapper');
    if (!fieldContainer) return;

    // Create error container if it doesn't exist
    let errorContainer = fieldContainer.querySelector(
      '.campaignbridge-field__errors'
    );
    if (!errorContainer) {
      errorContainer = document.createElement('div');
      errorContainer.className = 'campaignbridge-field__errors';
      errorContainer.setAttribute('role', 'alert');
      errorContainer.setAttribute('aria-live', 'polite');
      fieldContainer.appendChild(errorContainer);
    }

    // Set aria-describedby if label exists
    const label = fieldContainer.querySelector('.campaignbridge-field__label');
    if (label) {
      const labelId = `${field.id}-label`;
      label.id = labelId;
      field.setAttribute('aria-labelledby', labelId);
    }

    // Link field to error container
    errorContainer.id = `${field.id}-errors`;
    field.setAttribute('aria-describedby', `${field.id}-errors`);
  }

  /**
   * Update accessibility attributes based on validation
   */
  private updateAccessibility(
    field: HTMLInputElement,
    result: ValidationResult
  ): void {
    if (result.isValid) {
      field.removeAttribute('aria-invalid');
    } else {
      field.setAttribute('aria-invalid', 'true');
    }
  }

  /**
   * Email validation helper
   */
  private isValidEmail(email: string): boolean {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
  }

  /**
   * URL validation helper
   */
  private isValidUrl(url: string): boolean {
    try {
      new URL(url);
      return true;
    } catch {
      return false;
    }
  }
}

/**
 * Initialize form validation globally
 */
export function initializeFormValidation(): void {
  // Auto-initialize validation for fields with data attributes
  const fields = document.querySelectorAll('[data-validation]');

  fields.forEach(field => {
    const fieldId = field.id;
    const validationData = field.getAttribute('data-validation');

    if (!fieldId || !validationData) return;

    try {
      const rules: ValidationRule[] = JSON.parse(validationData);
      const validator = FormValidator.getInstance();

      validator.initializeField(fieldId, rules);
    } catch (error) {
      console.error(
        `Failed to initialize validation for field ${fieldId}:`,
        error
      );
    }
  });
}
