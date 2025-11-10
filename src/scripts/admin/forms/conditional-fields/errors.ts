/**
 * Custom error classes for better error handling and type safety
 */

export class ConditionalFieldError extends Error {
  constructor(
    message: string,
    // eslint-disable-next-line no-unused-vars -- Parameter property automatically creates class property.
    public readonly code: string,
    // eslint-disable-next-line no-unused-vars -- Parameter property automatically creates class property.
    public readonly details?: Record<string, any>
  ) {
    super(message);
    this.name = 'ConditionalFieldError';
  }
}

export class ApiTimeoutError extends ConditionalFieldError {
  constructor(timeout: number) {
    super(
      `Request timed out after ${timeout}ms. Please check your connection and try again.`,
      'API_TIMEOUT',
      { timeout }
    );
    this.name = 'ApiTimeoutError';
  }
}

export class ApiValidationError extends ConditionalFieldError {
  constructor(message: string, details?: Record<string, any>) {
    super(message, 'API_VALIDATION_ERROR', details);
    this.name = 'ApiValidationError';
  }
}

export class NetworkError extends ConditionalFieldError {
  constructor(status: number, statusText: string) {
    super(`Network error: ${status} ${statusText}`, 'NETWORK_ERROR', {
      status,
      statusText,
    });
    this.name = 'NetworkError';
  }
}

export class RateLimitError extends ConditionalFieldError {
  constructor(retryAfter?: number) {
    super(
      'Too many requests. Please wait a moment before trying again.',
      'RATE_LIMIT_EXCEEDED',
      { retryAfter }
    );
    this.name = 'RateLimitError';
  }
}

export class FormNotFoundError extends ConditionalFieldError {
  constructor(formId: string) {
    super(`Form with ID "${formId}" not found`, 'FORM_NOT_FOUND', { formId });
    this.name = 'FormNotFoundError';
  }
}

export class InvalidFieldStateError extends ConditionalFieldError {
  constructor(fieldId: string, reason: string) {
    super(
      `Invalid field state for "${fieldId}": ${reason}`,
      'INVALID_FIELD_STATE',
      { fieldId, reason }
    );
    this.name = 'InvalidFieldStateError';
  }
}

export class AccessibilityError extends ConditionalFieldError {
  constructor(message: string, details?: Record<string, any>) {
    super(message, 'ACCESSIBILITY_ERROR', details);
    this.name = 'AccessibilityError';
  }
}
