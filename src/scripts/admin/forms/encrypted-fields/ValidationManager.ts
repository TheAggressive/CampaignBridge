/**
 * Validation Manager
 *
 * Handles input validation for encrypted fields
 *
 * @package CampaignBridge
 */

import type { ValidationResult } from './types';

/**
 * Manages input validation for encrypted fields
 */
export class ValidationManager {
  private readonly MAX_VALUE_LENGTH = 1000;

  /**
   * Validate input before saving with accessibility support
   */
  validateForSave(
    value: string,
    inputElement: HTMLInputElement
  ): ValidationResult {
    // Clear previous validation state
    this.clearValidation(inputElement);

    if (!value.trim()) {
      return this.createErrorResult('Please enter a value', 'empty');
    }

    if (value.length > this.MAX_VALUE_LENGTH) {
      return this.createErrorResult(
        `Value is too long (maximum ${this.MAX_VALUE_LENGTH} characters)`,
        'too_long'
      );
    }

    if (this.containsSuspiciousContent(value)) {
      return this.createErrorResult(
        'Value contains potentially unsafe content',
        'suspicious'
      );
    }

    return { isValid: true };
  }

  /**
   * Validate request data before sending
   */
  validateRequestData(data: Record<string, any>): ValidationResult {
    // Validate nonce presence
    if (!data._wpnonce || typeof data._wpnonce !== 'string') {
      return this.createErrorResult('Security token missing', 'security');
    }

    // Validate field-specific requirements
    if (data.action?.includes('decrypt') && !data.encrypted_value) {
      return this.createErrorResult(
        'Encrypted value is required',
        'missing_data'
      );
    }

    if (data.action?.includes('encrypt') && !data.new_value) {
      return this.createErrorResult('New value is required', 'missing_data');
    }

    // Validate length constraints
    Object.keys(data).forEach(key => {
      if (
        typeof data[key] === 'string' &&
        data[key].length > this.MAX_VALUE_LENGTH
      ) {
        return this.createErrorResult(
          `Field ${key} exceeds maximum length`,
          'too_long'
        );
      }
    });

    return { isValid: true };
  }

  /**
   * Check for potentially suspicious content
   */
  containsSuspiciousContent(value: string): boolean {
    const suspiciousPatterns = [
      /<script/i,
      /javascript:/i,
      /vbscript:/i,
      /on\w+\s*=/i,
      /data:\s*text\/html/i,
    ];

    return suspiciousPatterns.some(pattern => pattern.test(value));
  }

  /**
   * Set validation error on input element
   */
  setValidationError(
    inputElement: HTMLInputElement,
    // eslint-disable-next-line no-unused-vars -- Parameter name in method signature is for documentation.
    errorMessage: string
  ): void {
    inputElement.setAttribute('aria-invalid', 'true');
    inputElement.setAttribute(
      'aria-describedby',
      `error-${inputElement.id || 'field'}`
    );
  }

  /**
   * Clear validation state from input element
   */
  clearValidation(inputElement: HTMLInputElement): void {
    inputElement.removeAttribute('aria-invalid');
    inputElement.removeAttribute('aria-describedby');
  }

  /**
   * Create error result object
   */
  private createErrorResult(message: string, field?: string): ValidationResult {
    return {
      isValid: false,
      error: message,
      field: field || 'general',
    };
  }

  /**
   * Get validation constraints
   */
  getConstraints(): {
    maxLength: number;
    allowedPatterns: string[];
    blockedPatterns: RegExp[];
  } {
    return {
      maxLength: this.MAX_VALUE_LENGTH,
      allowedPatterns: [],
      blockedPatterns: [
        /<script/i,
        /javascript:/i,
        /vbscript:/i,
        /on\w+\s*=/i,
        /data:\s*text\/html/i,
      ],
    };
  }
}
