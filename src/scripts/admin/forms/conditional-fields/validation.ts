/**
 * Input validation and sanitization utilities for form data
 */

export interface ValidationRule {
  required?: boolean;
  minLength?: number;
  maxLength?: number;
  pattern?: RegExp;
  // eslint-disable-next-line no-unused-vars -- Parameter name in type definition is for documentation.
  customValidator?: (value: any) => boolean;
  errorMessage?: string;
}

export interface ValidationResult {
  isValid: boolean;
  errorMessage?: string;
  sanitizedValue?: any;
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
  public validateFormData(formData: Record<string, any>): ValidationResult {
    for (const [fieldName, value] of Object.entries(formData)) {
      const rule = this.rules[fieldName];
      if (rule) {
        const result = this.validateField(fieldName, value, rule);
        if (!result.isValid) {
          return result;
        }
      }
    }
    return { isValid: true };
  }

  /**
   * Validate and sanitize a single field
   */
  public validateField(
    fieldName: string,
    value: any,
    rule: ValidationRule
  ): ValidationResult {
    // Check required fields
    if (
      rule.required &&
      (value === null || value === undefined || value === '')
    ) {
      return {
        isValid: false,
        errorMessage: rule.errorMessage || `${fieldName} is required`,
      };
    }

    // Skip further validation if value is empty and not required
    if (value === null || value === undefined || value === '') {
      return { isValid: true, sanitizedValue: value };
    }

    // Sanitize the value first
    let sanitizedValue = this.sanitizeValue(value, rule);

    // Apply validations
    if (
      rule.minLength &&
      typeof sanitizedValue === 'string' &&
      sanitizedValue.length < rule.minLength
    ) {
      return {
        isValid: false,
        errorMessage:
          rule.errorMessage ||
          `${fieldName} must be at least ${rule.minLength} characters`,
      };
    }

    if (
      rule.maxLength &&
      typeof sanitizedValue === 'string' &&
      sanitizedValue.length > rule.maxLength
    ) {
      return {
        isValid: false,
        errorMessage:
          rule.errorMessage ||
          `${fieldName} must be no more than ${rule.maxLength} characters`,
      };
    }

    if (
      rule.pattern &&
      typeof sanitizedValue === 'string' &&
      !rule.pattern.test(sanitizedValue)
    ) {
      return {
        isValid: false,
        errorMessage: rule.errorMessage || `${fieldName} format is invalid`,
      };
    }

    if (rule.customValidator && !rule.customValidator(sanitizedValue)) {
      return {
        isValid: false,
        errorMessage: rule.errorMessage || `${fieldName} validation failed`,
      };
    }

    return {
      isValid: true,
      sanitizedValue,
    };
  }

  /**
   * Sanitize a value based on its type and rules
   */
  private sanitizeValue(
    value: any,
    // eslint-disable-next-line no-unused-vars -- Reserved for future rule-based sanitization.
    rule: ValidationRule
  ): any {
    if (value === null || value === undefined) {
      return value;
    }

    // Convert to string for sanitization
    let stringValue = String(value);

    // Basic HTML sanitization - remove script tags and other dangerous content
    stringValue = stringValue.replace(
      /<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi,
      ''
    );
    stringValue = stringValue.replace(
      /<iframe\b[^<]*(?:(?!<\/iframe>)<[^<]*)*<\/iframe>/gi,
      ''
    );
    stringValue = stringValue.replace(
      /<object\b[^<]*(?:(?!<\/object>)<[^<]*)*<\/object>/gi,
      ''
    );
    stringValue = stringValue.replace(
      /<embed\b[^<]*(?:(?!<\/embed>)<[^<]*)*<\/embed>/gi,
      ''
    );

    // Remove null bytes and other control characters
    // eslint-disable-next-line no-control-regex -- Control characters are intentionally removed for security sanitization.
    stringValue = stringValue.replace(/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/g, '');

    // Trim whitespace
    stringValue = stringValue.trim();

    // Try to convert back to appropriate type
    if (
      typeof value === 'number' ||
      (typeof value === 'string' && /^\d+$/.test(value))
    ) {
      const numValue = parseInt(stringValue, 10);
      if (!isNaN(numValue)) {
        return numValue;
      }
    }

    if (typeof value === 'boolean' || value === 'true' || value === 'false') {
      return value === 'true' || value === true;
    }

    return stringValue;
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

/**
 * Sanitization utilities
 */
export class DataSanitizer {
  /**
   * Sanitize HTML content (basic protection against XSS)
   */
  public static sanitizeHtml(input: string): string {
    if (typeof input !== 'string') {
      return '';
    }

    // Remove dangerous tags
    let sanitized = input
      .replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '')
      .replace(/<iframe\b[^<]*(?:(?!<\/iframe>)<[^<]*)*<\/iframe>/gi, '')
      .replace(/<object\b[^<]*(?:(?!<\/object>)<[^<]*)*<\/object>/gi, '')
      .replace(/<embed\b[^<]*(?:(?!<\/embed>)<[^<]*)*<\/embed>/gi, '')
      .replace(/<form\b[^<]*(?:(?!<\/form>)<[^<]*)*<\/form>/gi, '')
      .replace(/<input\b[^<]*(?:(?!<\/input>)<[^<]*)*<\/input>/gi, '')
      .replace(/<button\b[^<]*(?:(?!<\/button>)<[^<]*)*<\/button>/gi, '');

    // Remove event handlers
    sanitized = sanitized.replace(/on\w+="[^"]*"/gi, '');
    sanitized = sanitized.replace(/on\w+='[^']*'/gi, '');

    return sanitized;
  }

  /**
   * Sanitize SQL-like inputs (basic protection)
   */
  public static sanitizeSqlInput(input: string): string {
    if (typeof input !== 'string') {
      return '';
    }

    // Remove or escape dangerous SQL keywords (basic protection)
    return input
      .replace(/;/g, '')
      .replace(/--/g, '')
      .replace(/\/\*/g, '')
      .replace(/\*\//g, '')
      .replace(/union/i, '')
      .replace(/select/i, '')
      .replace(/drop/i, '')
      .replace(/delete/i, '')
      .replace(/update/i, '')
      .replace(/insert/i, '');
  }

  /**
   * Sanitize filename
   */
  public static sanitizeFilename(filename: string): string {
    if (typeof filename !== 'string') {
      return '';
    }

    return filename
      .replace(/[<>:"/\\|?*]/g, '') // Remove invalid characters
      .replace(/\s+/g, '_') // Replace spaces with underscores
      .substring(0, 255); // Limit length
  }

  /**
   * Deep sanitize object properties
   */
  public static sanitizeObject(obj: any, maxDepth: number = 5): any {
    if (maxDepth <= 0) return obj;

    if (typeof obj === 'string') {
      return this.sanitizeHtml(obj);
    }

    if (Array.isArray(obj)) {
      return obj.map(item => this.sanitizeObject(item, maxDepth - 1));
    }

    if (obj !== null && typeof obj === 'object') {
      const sanitized: any = {};
      for (const [key, value] of Object.entries(obj)) {
        if (typeof key === 'string') {
          sanitized[key] = this.sanitizeObject(value, maxDepth - 1);
        }
      }
      return sanitized;
    }

    return obj;
  }
}
