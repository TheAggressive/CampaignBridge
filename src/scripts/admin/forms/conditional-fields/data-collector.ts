/**
 * Collects form data for conditional evaluation, matching current behavior.
 */

import { DataSanitizer, FormValidator } from './validation';

export class ConditionalDataCollector {
  private validator: FormValidator;

  constructor(
    // eslint-disable-next-line no-unused-vars -- Parameter property automatically creates class property.
    private form: HTMLFormElement,
    // eslint-disable-next-line no-unused-vars -- Parameter property automatically creates class property.
    private formId: string
  ) {
    this.validator = new FormValidator();
    this.setupDefaultValidationRules();
  }

  /**
   * Set up default validation rules for common field types
   */
  private setupDefaultValidationRules(): void {
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
    const checkboxNames = this.collectCheckboxNames(inputs);

    inputs.forEach(input => {
      const fieldData = this.processInputElement(input, checkboxNames);
      if (fieldData) {
        data[fieldData.fieldId] = fieldData.value;
      }
    });

    return data;
  }

  /**
   * Collect all checkbox input names to handle hidden fields properly
   */
  private collectCheckboxNames(inputs: NodeListOf<Element>): Set<string> {
    const checkboxNames = new Set<string>();

    inputs.forEach(el => {
      const input = el as HTMLInputElement;
      if (input.type === 'checkbox' && input.name) {
        checkboxNames.add(input.name);
      }
    });

    return checkboxNames;
  }

  /**
   * Process a single input element and return field data if valid
   */
  private processInputElement(
    input: Element,
    checkboxNames: Set<string>
  ): { fieldId: string; value: string } | null {
    const htmlInput = input as
      | HTMLInputElement
      | HTMLSelectElement
      | HTMLTextAreaElement;
    const fullName = (htmlInput as HTMLInputElement).name;

    if (!fullName) return null;

    // Skip hidden fields that correspond to checkboxes
    if (
      this.shouldSkipHiddenCheckboxField(htmlInput, fullName, checkboxNames)
    ) {
      return null;
    }

    // Skip encrypted field display inputs (they have modified names and are readonly)
    if (this.shouldSkipEncryptedDisplayInput(htmlInput, fullName)) {
      return null;
    }

    const value = this.extractInputValue(htmlInput);
    if (value === null) return null; // Radio button not checked

    const fieldId = this.parseFieldName(fullName);
    if (!fieldId) return null;

    return {
      fieldId,
      value: this.validateAndSanitizeValue(fieldId, value),
    };
  }

  /**
   * Check if this hidden field should be skipped (checkbox handling)
   */
  private shouldSkipHiddenCheckboxField(
    input: HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement,
    fullName: string,
    checkboxNames: Set<string>
  ): boolean {
    return (
      (input as HTMLInputElement).type === 'hidden' &&
      checkboxNames.has(fullName)
    );
  }

  /**
   * Check if this input should be skipped (encrypted field display input)
   */
  private shouldSkipEncryptedDisplayInput(
    input: HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement,
    fullName: string
  ): boolean {
    const htmlInput = input as HTMLInputElement;

    // Skip encrypted field display inputs - they have modified names and are readonly
    if (htmlInput.readOnly && fullName.includes('_edit_input')) {
      return true;
    }

    // Skip if this is a readonly input within an encrypted field container
    if (
      htmlInput.readOnly &&
      input.closest('.campaignbridge-encrypted-field__display')
    ) {
      return true;
    }

    return false;
  }

  /**
   * Extract the value from an input element
   */
  private extractInputValue(
    input: HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement
  ): any {
    const htmlInput = input as HTMLInputElement;

    if (htmlInput.type === 'checkbox') {
      return htmlInput.checked ? '1' : '0';
    } else if (htmlInput.type === 'radio') {
      return htmlInput.checked ? htmlInput.value : null;
    } else {
      return htmlInput.value;
    }
  }

  /**
   * Validate and sanitize a field value
   */
  private validateAndSanitizeValue(fieldId: string, value: any): string {
    const validationResult = this.validator.validateField(fieldId, value, {});

    if (validationResult.isValid) {
      // Use sanitized value if available, otherwise sanitize manually
      return validationResult.sanitizedValue !== undefined
        ? String(validationResult.sanitizedValue)
        : DataSanitizer.sanitizeHtml(String(value));
    } else {
      // Log validation error but still include the data (fail gracefully)
      console.warn(
        `Field validation failed for ${fieldId}:`,
        validationResult.errorMessage
      );
      return DataSanitizer.sanitizeHtml(String(value));
    }
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
