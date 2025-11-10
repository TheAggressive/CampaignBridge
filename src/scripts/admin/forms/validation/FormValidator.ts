/**
 * Real-time Form Validation System
 *
 * Provides instant feedback for form validation across all CampaignBridge form fields.
 * Supports multiple validation rules, custom validators, and accessibility features.
 *
 * @package CampaignBridge
 */

import {
  CustomValidationError,
  FieldValidationState,
  InvalidFormatError,
  LengthError,
  NumericRangeError,
  RequiredFieldError,
  ValidationConfig,
  ValidationError,
  ValidationResult,
  ValidationRule,
  ValidationSystemError,
} from './types';

/**
 * Comprehensive form validation manager
 */
export class FormValidator {
  private static instance: FormValidator;
  private fieldStates: Map<string, FieldValidationState> = new Map();
  private rules: Map<string, ValidationRule[]> = new Map();
  private debounceTimers: Map<string, number> = new Map();
  private eventListeners: Map<
    string,
    { element: HTMLElement; type: string; listener: EventListener }
  > = new Map();
  private domCache: Map<string, HTMLElement | null> = new Map();
  private validationCache: Map<
    string,
    { result: ValidationResult; timestamp: number }
  > = new Map();
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
   * Get cached DOM element or query it
   */
  private getCachedElement(fieldId: string): HTMLElement | null {
    if (!this.domCache.has(fieldId)) {
      const element = document.getElementById(fieldId);
      this.domCache.set(fieldId, element);
    }
    return this.domCache.get(fieldId) || null;
  }

  /**
   * Clear DOM cache for a field (useful when DOM changes)
   */
  private clearDomCache(fieldId?: string): void {
    if (fieldId) {
      this.domCache.delete(fieldId);
      // Also clear related caches
      this.validationCache.delete(fieldId);
    } else {
      this.domCache.clear();
      this.validationCache.clear();
    }
  }

  /**
   * Get cached validation result if still valid
   */
  private getCachedValidation(
    fieldId: string,
    value: string
  ): ValidationResult | null {
    const cacheKey = `${fieldId}:${value}`;
    const cached = this.validationCache.get(cacheKey);

    if (cached && Date.now() - cached.timestamp < 5000) {
      // 5 second cache
      return cached.result;
    }

    return null;
  }

  /**
   * Cache validation result
   */
  private setCachedValidation(
    fieldId: string,
    value: string,
    result: ValidationResult
  ): void {
    const cacheKey = `${fieldId}:${value}`;
    this.validationCache.set(cacheKey, {
      result,
      timestamp: Date.now(),
    });
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
    const field = this.getCachedElement(fieldId) as HTMLInputElement;
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

    // Add event listeners with stored references for cleanup
    if (config.validateOnInput) {
      const inputListener = () => this.handleValidation(fieldId);
      field.addEventListener('input', inputListener);
      this.eventListeners.set(`${fieldId}-input`, {
        element: field,
        type: 'input',
        listener: inputListener,
      });
    }

    if (config.validateOnBlur) {
      const blurListener = () => this.handleValidation(fieldId, true);
      field.addEventListener('blur', blurListener);
      this.eventListeners.set(`${fieldId}-blur`, {
        element: field,
        type: 'blur',
        listener: blurListener,
      });
    }

    if (config.validateOnChange) {
      const changeListener = () => this.handleValidation(fieldId);
      field.addEventListener('change', changeListener);
      this.eventListeners.set(`${fieldId}-change`, {
        element: field,
        type: 'change',
        listener: changeListener,
      });
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
    const field = this.getCachedElement(fieldId) as HTMLInputElement;
    const rules = this.rules.get(fieldId);

    if (!field || !rules) {
      return { isValid: true, errors: [] };
    }

    const fieldValue = field.value;

    // Check cache first (unless forced), but don't cache empty field validations
    // as they can change based on required rules
    if (!force && fieldValue !== '') {
      const cachedResult = this.getCachedValidation(fieldId, fieldValue);
      if (cachedResult) {
        // Still update the UI even with cached result
        this.updateFieldUI(fieldId, cachedResult);
        return cachedResult;
      }
    }

    // Add validating state
    const fieldContainer = field.closest('.campaignbridge-field-wrapper');
    if (fieldContainer) {
      fieldContainer.classList.add('campaignbridge-field-wrapper--validating');
    }

    const errors: string[] = [];

    // Run all validation rules
    for (const rule of rules) {
      const result = await this.validateRule(rule, fieldValue, field);
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
    state.isDirty = state.isDirty || fieldValue.length > 0;
    state.lastValidation = new Date();

    // Update UI
    this.updateFieldUI(fieldId, result);

    // Cache the result (but not for empty fields to avoid stale validation)
    if (fieldValue !== '') {
      this.setCachedValidation(fieldId, fieldValue, result);
    }

    return result;
  }

  /**
   * Validate entire form
   */
  async validateForm(
    formId: string,
    groups?: string | string[]
  ): Promise<boolean> {
    const form = document.getElementById(formId);
    if (!form) return false;

    const fields = Array.from(form.querySelectorAll('input, select, textarea'))
      .map(el => el.id)
      .filter(
        id => this.rules.has(id) && this.fieldBelongsToGroups(id, groups)
      );

    let isFormValid = true;
    let errorCount = 0;

    for (const fieldId of fields) {
      const result = await this.validateField(fieldId, true);
      if (!result.isValid) {
        isFormValid = false;
        errorCount += result.errors.length;
      }
    }

    // Announce form validation status to screen readers
    if (this.config.enableAccessibility) {
      this.announceFormValidationStatus(
        form,
        isFormValid,
        errorCount,
        fields.length
      );
    }

    return isFormValid;
  }

  /**
   * Announce form validation status to screen readers
   */
  private announceFormValidationStatus(
    form: HTMLElement,
    isValid: boolean,
    errorCount: number,
    totalFields: number
  ): void {
    // Create or find status announcement element
    let statusElement = form.querySelector(
      '.campaignbridge-form__status'
    ) as HTMLElement;

    if (!statusElement) {
      statusElement = document.createElement('div');
      statusElement.className = 'campaignbridge-form__status';
      statusElement.setAttribute('role', 'status');
      statusElement.setAttribute('aria-live', 'polite');
      statusElement.setAttribute('aria-atomic', 'true');
      statusElement.style.position = 'absolute';
      statusElement.style.left = '-10000px';
      statusElement.style.width = '1px';
      statusElement.style.height = '1px';
      statusElement.style.overflow = 'hidden';
      form.appendChild(statusElement);
    }

    if (isValid) {
      statusElement.textContent = `Form validation successful. All ${totalFields} fields are valid.`;
    } else {
      statusElement.textContent = `Form validation failed. ${errorCount} validation ${errorCount === 1 ? 'error' : 'errors'} found in ${totalFields} fields. Please review and correct the highlighted fields.`;
    }
  }

  /**
   * Validate only fields belonging to specific group(s)
   */
  async validateGroup(
    groups: string | string[]
  ): Promise<{ [fieldId: string]: ValidationResult }> {
    const results: { [fieldId: string]: ValidationResult } = {};
    const groupArray = Array.isArray(groups) ? groups : [groups];

    for (const [fieldId] of this.rules.entries()) {
      // Check if any rule belongs to the requested groups.
      if (this.fieldBelongsToGroups(fieldId, groupArray)) {
        results[fieldId] = await this.validateField(fieldId, true);
      }
    }

    return results;
  }

  /**
   * Check if a field belongs to specified group(s)
   */
  private fieldBelongsToGroups(
    fieldId: string,
    groups?: string | string[]
  ): boolean {
    if (!groups) return true; // No group filter means all fields

    const rules = this.rules.get(fieldId);
    if (!rules) return false;

    const groupArray = Array.isArray(groups) ? groups : [groups];

    // Check if any rule has a group that matches
    return rules.some(rule => {
      if (!rule.groups) return false;

      const ruleGroups = Array.isArray(rule.groups)
        ? rule.groups
        : [rule.groups];
      return ruleGroups.some(ruleGroup => groupArray.includes(ruleGroup));
    });
  }

  /**
   * Get all available validation groups
   */
  getValidationGroups(): string[] {
    const groups = new Set<string>();

    for (const rules of this.rules.values()) {
      for (const rule of rules) {
        if (rule.groups) {
          const ruleGroups = Array.isArray(rule.groups)
            ? rule.groups
            : [rule.groups];
          ruleGroups.forEach(group => groups.add(group));
        }
      }
    }

    return Array.from(groups);
  }

  /**
   * Get fields belonging to specific group(s)
   */
  getFieldsByGroup(groups: string | string[]): string[] {
    const groupArray = Array.isArray(groups) ? groups : [groups];
    const fields: string[] = [];

    for (const [fieldId] of this.rules.entries()) {
      if (this.fieldBelongsToGroups(fieldId, groupArray)) {
        fields.push(fieldId);
      }
    }

    return fields;
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
   * Remove validation from a field and clean up resources
   */
  removeField(fieldId: string): void {
    // Clear any pending debounce timers
    const existingTimer = this.debounceTimers.get(fieldId);
    if (existingTimer) {
      clearTimeout(existingTimer);
      this.debounceTimers.delete(fieldId);
    }

    // Remove event listeners
    const eventKeys = Array.from(this.eventListeners.keys()).filter(key =>
      key.startsWith(`${fieldId}-`)
    );
    eventKeys.forEach(key => {
      const listenerInfo = this.eventListeners.get(key);
      if (listenerInfo) {
        listenerInfo.element.removeEventListener(
          listenerInfo.type,
          listenerInfo.listener
        );
      }
      this.eventListeners.delete(key);
    });

    // Clear field state and rules
    this.fieldStates.delete(fieldId);
    this.rules.delete(fieldId);
  }

  /**
   * Clear all debounce timers
   */
  private clearAllTimers(): void {
    this.debounceTimers.forEach(timerId => {
      clearTimeout(timerId);
    });
    this.debounceTimers.clear();
  }

  /**
   * Remove all event listeners
   */
  private removeAllEventListeners(): void {
    this.eventListeners.forEach(listenerInfo => {
      listenerInfo.element.removeEventListener(
        listenerInfo.type,
        listenerInfo.listener
      );
    });
    this.eventListeners.clear();
  }

  /**
   * Destroy the validator instance and clean up all resources
   */
  destroy(): void {
    // Clear all timers
    this.clearAllTimers();

    // Remove all event listeners
    this.removeAllEventListeners();

    // Clear all caches and maps
    this.fieldStates.clear();
    this.rules.clear();
    this.domCache.clear();
    this.validationCache.clear();

    // Reset singleton instance if this is the current instance
    if (FormValidator.instance === this) {
      FormValidator.instance = undefined as any;
    }
  }

  /**
   * Reset the singleton instance (useful for testing)
   */
  static resetInstance(): void {
    if (FormValidator.instance) {
      FormValidator.instance.destroy();
    }
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

    const field = this.getCachedElement(fieldId) as HTMLInputElement;
    if (!field) return;

    const currentValue = field.value;
    const previousState = this.fieldStates.get(fieldId);

    // Force immediate validation if field becomes empty (critical UX issue)
    // or if transitioning from valid to potentially invalid state
    const shouldForceValidate =
      force ||
      (currentValue === '' && previousState?.isValid) ||
      (currentValue !== '' && !previousState?.isValid);

    if (shouldForceValidate) {
      // Immediate validation for critical state changes
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
      // Built-in validation rules with specific error types
      switch (rule.type) {
        case 'required':
          if (!value.trim()) {
            const error = new RequiredFieldError(
              field.id,
              this.getFieldLabel(field)
            );
            return {
              isValid: false,
              error: rule.message || error.message,
            };
          }
          break;

        case 'email':
          if (value && !this.isValidEmail(value)) {
            const error = new InvalidFormatError(
              field.id,
              'valid email address',
              rule.name
            );
            return {
              isValid: false,
              error: rule.message || error.message,
            };
          }
          break;

        case 'url':
          if (value && !this.isValidUrl(value)) {
            const error = new InvalidFormatError(
              field.id,
              'valid URL',
              rule.name
            );
            return {
              isValid: false,
              error: rule.message || error.message,
            };
          }
          break;

        case 'minLength':
          if (value && value.length < Number(rule.value || 0)) {
            const error = new LengthError(
              field.id,
              value.length,
              Number(rule.value || 0),
              true
            );
            return {
              isValid: false,
              error: rule.message || error.message,
            };
          }
          break;

        case 'maxLength':
          if (value && value.length > Number(rule.value || 0)) {
            const error = new LengthError(
              field.id,
              value.length,
              Number(rule.value || 0),
              false
            );
            return {
              isValid: false,
              error: rule.message || error.message,
            };
          }
          break;

        case 'pattern':
          if (value && rule.pattern && !new RegExp(rule.pattern).test(value)) {
            const error = new InvalidFormatError(
              field.id,
              `pattern: ${rule.pattern}`,
              rule.name
            );
            return { isValid: false, error: rule.message || error.message };
          }
          break;

        case 'numeric':
          if (value && isNaN(Number(value))) {
            const error = new InvalidFormatError(
              field.id,
              'numeric value',
              rule.name
            );
            return {
              isValid: false,
              error: rule.message || error.message,
            };
          }
          break;

        case 'min':
          if (
            value &&
            !isNaN(Number(value)) &&
            Number(value) < Number(rule.value || 0)
          ) {
            const error = new NumericRangeError(
              field.id,
              Number(value),
              Number(rule.value || 0),
              true
            );
            return {
              isValid: false,
              error: rule.message || error.message,
            };
          }
          break;

        case 'max':
          if (
            value &&
            !isNaN(Number(value)) &&
            Number(value) > Number(rule.value || 0)
          ) {
            const error = new NumericRangeError(
              field.id,
              Number(value),
              Number(rule.value || 0),
              false
            );
            return {
              isValid: false,
              error: rule.message || error.message,
            };
          }
          break;

        case 'custom':
          if (rule.validator) {
            const result = await rule.validator(value, field);
            if (!result.isValid) {
              const error = new CustomValidationError(
                field.id,
                result.error || 'Custom validation failed',
                rule.name
              );
              return { isValid: false, error: rule.message || error.message };
            }
          }
          break;
      }

      return { isValid: true };
    } catch (error) {
      // Handle validation system errors
      if (error instanceof ValidationError) {
        return {
          isValid: false,
          error: error.message,
        };
      }

      // Handle unexpected system errors
      const systemError = new ValidationSystemError(
        'Unexpected validation error occurred',
        'VALIDATION_SYSTEM_ERROR',
        { originalError: error, fieldId: field.id, ruleType: rule.type }
      );

      return {
        isValid: false,
        error: rule.message || systemError.message,
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
      errorContainer.setAttribute('aria-atomic', 'true');
      fieldContainer.appendChild(errorContainer);
    }

    // Create success message container for screen readers
    let successContainer = fieldContainer.querySelector(
      '.campaignbridge-field__success'
    ) as HTMLElement;
    if (!successContainer) {
      successContainer = document.createElement('div');
      successContainer.className = 'campaignbridge-field__success';
      successContainer.setAttribute('role', 'status');
      successContainer.setAttribute('aria-live', 'polite');
      successContainer.setAttribute('aria-atomic', 'true');
      successContainer.style.position = 'absolute';
      successContainer.style.left = '-10000px';
      successContainer.style.width = '1px';
      successContainer.style.height = '1px';
      successContainer.style.overflow = 'hidden';
      fieldContainer.appendChild(successContainer);
    }

    // Set aria-describedby if label exists
    const label = fieldContainer.querySelector('.campaignbridge-field__label');
    if (label) {
      const labelId = `${field.id}-label`;
      label.id = labelId;
      field.setAttribute('aria-labelledby', labelId);
    }

    // Link field to containers
    const containerIds = [];
    if (errorContainer) {
      errorContainer.id = `${field.id}-errors`;
      containerIds.push(errorContainer.id);
    }
    if (successContainer) {
      successContainer.id = `${field.id}-success`;
      containerIds.push(successContainer.id);
    }

    if (containerIds.length > 0) {
      field.setAttribute('aria-describedby', containerIds.join(' '));
    }

    // Add field type specific accessibility
    this.setupFieldTypeAccessibility(field);

    // Add keyboard navigation support
    this.setupKeyboardNavigation(field, fieldContainer);
  }

  /**
   * Setup field-type specific accessibility attributes
   */
  private setupFieldTypeAccessibility(field: HTMLInputElement): void {
    const fieldType = field.type;

    switch (fieldType) {
      case 'email':
        field.setAttribute(
          'aria-label',
          field.getAttribute('aria-label') || 'Email address'
        );
        field.setAttribute('autocomplete', 'email');
        break;

      case 'tel':
        field.setAttribute(
          'aria-label',
          field.getAttribute('aria-label') || 'Phone number'
        );
        field.setAttribute('autocomplete', 'tel');
        break;

      case 'url':
        field.setAttribute(
          'aria-label',
          field.getAttribute('aria-label') || 'Website URL'
        );
        field.setAttribute('autocomplete', 'url');
        break;

      case 'number':
        field.setAttribute(
          'aria-label',
          field.getAttribute('aria-label') || 'Number'
        );
        if (field.min) {
          field.setAttribute('aria-valuemin', field.min);
        }
        if (field.max) {
          field.setAttribute('aria-valuemax', field.max);
        }
        break;

      case 'password':
        field.setAttribute(
          'aria-label',
          field.getAttribute('aria-label') || 'Password'
        );
        field.setAttribute('autocomplete', 'current-password');
        break;

      case 'date':
      case 'datetime-local':
      case 'time':
        field.setAttribute(
          'aria-label',
          field.getAttribute('aria-label') ||
            `${fieldType.replace('-', ' ')} input`
        );
        break;
    }
  }

  /**
   * Setup keyboard navigation support
   */
  private setupKeyboardNavigation(
    field: HTMLInputElement,
    // eslint-disable-next-line no-unused-vars -- Reserved for future container-based navigation enhancements.
    container: Element
  ): void {
    // Add keyboard event listeners for better navigation
    const keyboardHandler = (event: KeyboardEvent) => {
      // Enhanced Enter key handling for form submission
      if (event.key === 'Enter' && !event.shiftKey) {
        // Find the next logical field or submit button
        const nextField = this.findNextFocusableField(field);
        if (nextField && nextField !== field) {
          event.preventDefault();
          nextField.focus();
        }
      }

      // Escape key to clear field and validation
      if (event.key === 'Escape') {
        field.value = '';
        field.dispatchEvent(new Event('input', { bubbles: true }));
        field.dispatchEvent(new Event('change', { bubbles: true }));
      }
    };

    field.addEventListener('keydown', keyboardHandler);
    this.eventListeners.set(`${field.id}-keyboard`, {
      element: field,
      type: 'keydown',
      listener: keyboardHandler,
    });
  }

  /**
   * Find next focusable field in the form
   */
  private findNextFocusableField(
    currentField: HTMLInputElement
  ): HTMLElement | null {
    const form = currentField.closest('form');
    if (!form) return null;

    const focusableElements = form.querySelectorAll(
      'input:not([type="hidden"]), select, textarea, button:not([disabled])'
    );

    const currentIndex = Array.from(focusableElements).indexOf(currentField);

    if (currentIndex >= 0 && currentIndex < focusableElements.length - 1) {
      return focusableElements[currentIndex + 1] as HTMLElement;
    }

    return null;
  }

  /**
   * Update accessibility attributes based on validation
   */
  private updateAccessibility(
    field: HTMLInputElement,
    result: ValidationResult
  ): void {
    const fieldContainer = field.closest('.campaignbridge-field-wrapper');
    if (!fieldContainer) return;

    // Update aria-invalid
    if (result.isValid) {
      field.removeAttribute('aria-invalid');
    } else {
      field.setAttribute('aria-invalid', 'true');
    }

    // Announce validation status to screen readers
    const successContainer = fieldContainer.querySelector(
      '.campaignbridge-field__success'
    ) as HTMLElement;
    if (successContainer) {
      if (result.isValid && this.config.showSuccessStates) {
        const fieldLabel = this.getFieldLabel(field);
        successContainer.textContent = `${fieldLabel} validation passed`;
      } else {
        successContainer.textContent = '';
      }
    }

    // Update error container accessibility
    const errorContainer = fieldContainer.querySelector(
      '.campaignbridge-field__errors'
    ) as HTMLElement;
    if (errorContainer) {
      if (!result.isValid && result.errors.length > 0) {
        // Ensure errors are announced
        errorContainer.setAttribute('aria-live', 'assertive');
      } else {
        errorContainer.setAttribute('aria-live', 'polite');
      }
    }

    // Add validation status to field description
    const currentDescribedBy = field.getAttribute('aria-describedby') || '';
    const statusId = `${field.id}-status`;

    if (result.isValid && this.config.showSuccessStates) {
      // Add success status to describedby
      if (!currentDescribedBy.includes(statusId)) {
        field.setAttribute(
          'aria-describedby',
          currentDescribedBy ? `${currentDescribedBy} ${statusId}` : statusId
        );
      }
    } else {
      // Remove success status from describedby
      const newDescribedBy = currentDescribedBy
        .split(' ')
        .filter(id => id !== statusId)
        .join(' ');

      if (newDescribedBy) {
        field.setAttribute('aria-describedby', newDescribedBy);
      } else {
        field.removeAttribute('aria-describedby');
      }
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

  /**
   * Get field label for error messages
   */
  private getFieldLabel(field: HTMLInputElement): string {
    // Try to find label by 'for' attribute
    const label = document.querySelector(`label[for="${field.id}"]`);
    if (label) {
      return label.textContent?.trim() || 'This field';
    }

    // Try to find label as parent or sibling
    const container = field.closest('.campaignbridge-field-wrapper');
    if (container) {
      const labelElement = container.querySelector(
        '.campaignbridge-field__label'
      );
      if (labelElement) {
        return labelElement.textContent?.trim() || 'This field';
      }
    }

    return 'This field';
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
