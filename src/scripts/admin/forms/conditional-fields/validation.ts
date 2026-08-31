/**
 * Input validation and sanitization utilities for form data
 */

export interface ValidationRule {
  required?: boolean;
  minLength?: number;
  maxLength?: number;
  pattern?: RegExp;
  // eslint-disable-next-line no-unused-vars -- Parameter name in type definition is for documentation.
  customValidator?: (value: unknown) => boolean;
  errorMessage?: string;
}

export interface ValidationResult {
  isValid: boolean;
  errorMessage?: string;
  normalizedValue?: unknown;
}

export interface FieldValidationRules {
  [fieldName: string]: ValidationRule;
}

export class FormValidator {
  private rules: FieldValidationRules = {};

  /**
   * Set validation rules for fields
   */
  public setRules(rules: FieldValidationRules): void {
    this.rules = { ...rules };
  }

  /**
   * Add validation rule for a specific field
   */
  public addRule(fieldName: string, rule: ValidationRule): void {
    this.rules[fieldName] = rule;
  }

  /**
   * Validate form data against rules
   */
  public validateFormData(formData: Record<string, unknown>): ValidationResult {
    const fieldNames = new Set([
      ...Object.keys(formData),
      ...Object.keys(this.rules).filter(fieldName => fieldName !== '*'),
    ]);

    for (const fieldName of fieldNames) {
      const result = this.validateField(fieldName, formData[fieldName]);
      if (!result.isValid) {
        return result;
      }
    }

    return { isValid: true };
  }

  /**
   * Validate and sanitize a single field
   */
  public validateField(
    fieldName: string,
    value: unknown,
    rule: ValidationRule = {}
  ): ValidationResult {
    const effectiveRule = {
      ...(this.rules['*'] ?? {}),
      ...(this.rules[fieldName] ?? {}),
      ...rule,
    };

    // Check required fields
    if (
      effectiveRule.required &&
      (value === null || value === undefined || value === '')
    ) {
      return {
        isValid: false,
        errorMessage: effectiveRule.errorMessage || `${fieldName} is required`,
      };
    }

    // Skip further validation if value is empty and not required
    if (value === null || value === undefined || value === '') {
      return { isValid: true, normalizedValue: value };
    }

    // Client-side normalization improves UX only. The authenticated server
    // endpoint remains responsible for security validation and sanitization.
    const normalizedValue = this.normalizeValue(value);

    // Apply validations
    if (
      effectiveRule.minLength !== undefined &&
      typeof normalizedValue === 'string' &&
      normalizedValue.length < effectiveRule.minLength
    ) {
      return {
        isValid: false,
        normalizedValue,
        errorMessage:
          effectiveRule.errorMessage ||
          `${fieldName} must be at least ${effectiveRule.minLength} characters`,
      };
    }

    if (
      effectiveRule.maxLength !== undefined &&
      typeof normalizedValue === 'string' &&
      normalizedValue.length > effectiveRule.maxLength
    ) {
      return {
        isValid: false,
        normalizedValue,
        errorMessage:
          effectiveRule.errorMessage ||
          `${fieldName} must be no more than ${effectiveRule.maxLength} characters`,
      };
    }

    if (
      effectiveRule.pattern &&
      typeof normalizedValue === 'string' &&
      !this.matchesPattern(effectiveRule.pattern, normalizedValue)
    ) {
      return {
        isValid: false,
        normalizedValue,
        errorMessage:
          effectiveRule.errorMessage || `${fieldName} format is invalid`,
      };
    }

    if (
      effectiveRule.customValidator &&
      !effectiveRule.customValidator(normalizedValue)
    ) {
      return {
        isValid: false,
        normalizedValue,
        errorMessage:
          effectiveRule.errorMessage || `${fieldName} validation failed`,
      };
    }

    return {
      isValid: true,
      normalizedValue,
    };
  }

  /**
   * Normalize a scalar form value without attempting security sanitization.
   *
   * Browser input is untrusted even after this method runs. Security controls
   * belong to the authenticated server boundary, where WordPress can validate
   * the request against the registered field schema.
   */
  private normalizeValue(value: unknown): unknown {
    if (value === null || value === undefined) {
      return value;
    }

    if (typeof value !== 'string') {
      return value;
    }

    // eslint-disable-next-line no-control-regex -- Non-printing controls are not meaningful form values.
    return value.replace(/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/g, '').trim();
  }

  /**
   * Test a pattern without leaking state from global or sticky expressions.
   */
  private matchesPattern(pattern: RegExp, value: string): boolean {
    const statelessPattern = new RegExp(
      pattern.source,
      pattern.flags.replace(/[gy]/g, '')
    );

    return statelessPattern.test(value);
  }

  /**
   * Common validation rules
   */
  public static getCommonRules(): Record<string, ValidationRule> {
    return {
      email: {
        pattern: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
        maxLength: 254,
        errorMessage: 'Please enter a valid email address',
      },
      url: {
        pattern:
          /^https?:\/\/(www\.)?[-a-zA-Z0-9@:%._+~#=]{1,256}\.[a-zA-Z0-9()]{1,6}\b([-a-zA-Z0-9()@:%_+.~#?&//=]*)$/,
        maxLength: 2048,
        errorMessage: 'Please enter a valid URL',
      },
      phone: {
        pattern: /^[+]?[1-9][\d]{0,15}$/,
        minLength: 7,
        maxLength: 15,
        errorMessage: 'Please enter a valid phone number',
      },
      postalCode: {
        pattern: /^[A-Za-z0-9\s-]{3,10}$/,
        errorMessage: 'Please enter a valid postal code',
      },
      name: {
        minLength: 2,
        maxLength: 100,
        pattern: /^[a-zA-Z\s-.']+$/,
        errorMessage: 'Please enter a valid name',
      },
      text: {
        maxLength: 1000,
        errorMessage: 'Text is too long',
      },
      number: {
        customValidator: value => !isNaN(Number(value)),
        errorMessage: 'Please enter a valid number',
      },
      positiveNumber: {
        customValidator: value => !isNaN(Number(value)) && Number(value) > 0,
        errorMessage: 'Please enter a positive number',
      },
    };
  }
}
