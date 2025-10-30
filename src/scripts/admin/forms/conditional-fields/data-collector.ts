/**
 * Collects form data for conditional evaluation, matching current behavior.
 */

import { DataSanitizer, FormValidator } from './validation';

export class ConditionalDataCollector {
  private validator: FormValidator;

  constructor(
    private form: HTMLFormElement,
    private formId: string
  ) {
    this.validator = new FormValidator();
    this.setupDefaultValidationRules();
  }

  /**
   * Set up default validation rules for common field types
   */
  private setupDefaultValidationRules(): void {
    // Add basic validation for common field patterns
    const commonRules = FormValidator.getCommonRules();

    // Apply basic sanitization rules to all fields
    this.validator.addRule('*', {
      customValidator: value => {
        // Basic validation - ensure value is not dangerously long
        if (typeof value === 'string' && value.length > 10000) {
          return false;
        }
        return true;
      },
      errorMessage: 'Input data is too large',
    });
  }

  public getFormData(): Record<string, string> {
    const data: Record<string, string> = {};
    const inputs = this.form.querySelectorAll('input, select, textarea');

    const checkboxNames = new Set<string>();

    inputs.forEach(el => {
      const input = el as HTMLInputElement;
      if (input.type === 'checkbox' && input.name) {
        checkboxNames.add(input.name);
      }
    });

    inputs.forEach(el => {
      const input = el as
        | HTMLInputElement
        | HTMLSelectElement
        | HTMLTextAreaElement;
      const fullName = (input as HTMLInputElement).name;

      if (
        (input as HTMLInputElement).type === 'hidden' &&
        checkboxNames.has(fullName)
      ) {
        return;
      }

      let value: any = (input as HTMLInputElement).value;

      if ((input as HTMLInputElement).type === 'checkbox') {
        value = (input as HTMLInputElement).checked ? '1' : '0';
      } else if ((input as HTMLInputElement).type === 'radio') {
        if (!(input as HTMLInputElement).checked) {
          return;
        }
      }

      if (fullName) {
        const fieldId = this.parseFieldName(fullName);
        if (fieldId) {
          // Validate and sanitize the value
          const validationResult = this.validator.validateField(
            fieldId,
            value,
            {}
          );

          if (validationResult.isValid) {
            // Use sanitized value if available, otherwise sanitize manually
            const sanitizedValue =
              validationResult.sanitizedValue !== undefined
                ? validationResult.sanitizedValue
                : DataSanitizer.sanitizeHtml(String(value));

            data[fieldId] = String(sanitizedValue);
          } else {
            // Log validation error but still include the data (fail gracefully)
            console.warn(
              `Field validation failed for ${fieldId}:`,
              validationResult.errorMessage
            );
            data[fieldId] = DataSanitizer.sanitizeHtml(String(value));
          }
        }
      }
    });

    return data;
  }

  /**
   * Set custom validation rules for specific fields
   */
  public setValidationRules(
    rules: Record<string, import('./validation').ValidationRule>
  ): void {
    Object.entries(rules).forEach(([fieldName, rule]) => {
      this.validator.addRule(fieldName, rule);
    });
  }

  /**
   * Get the current validation rules
   */
  public getValidationRules(): Record<
    string,
    import('./validation').ValidationRule
  > {
    // This would need to be exposed from FormValidator
    // For now, return empty object
    return {};
  }

  private parseFieldName(fullName: string): string | null {
    const match = fullName.match(new RegExp(`^${this.formId}\\[(.+)\\]$`));
    if (match) {
      return match[1];
    }
    return fullName;
  }
}
