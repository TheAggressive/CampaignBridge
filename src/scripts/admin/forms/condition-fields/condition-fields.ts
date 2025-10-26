/**
 * Client-side conditional field engine for CampaignBridge forms
 */
export interface ConditionalRule {
  field: string;
  operator: string;
  value?: any;
}

export interface ConditionalConfig {
  type: 'show_when' | 'hide_when' | 'required_when';
  conditions: ConditionalRule[];
}

export interface FieldConditional {
  [fieldId: string]: ConditionalConfig;
}

export class ConditionalEngine {
  private form: HTMLFormElement | null;
  private conditionals: FieldConditional;
  private formId: string;

  constructor(formId: string, conditionals: FieldConditional) {
    this.formId = formId;
    this.conditionals = conditionals;
    this.form = document.getElementById(formId) as HTMLFormElement;

    if (!this.form) {
      console.error('Form not found with ID:', formId);
      return;
    }

    this.init();
  }

  private init(): void {
    // Wait for form to be fully rendered, then evaluate initial conditions
    this.waitForFormReady(() => {
      this.evaluateAllConditions();
    });

    // Bind events for future changes
    this.bindEvents();
  }

  private waitForFormReady(callback: () => void): void {
    const checkReady = () => {
      // Check if all conditional fields exist in DOM
      const conditionalFields = Object.keys(this.conditionals);
      const allFieldsExist = conditionalFields.every(fieldId => {
        const fieldName = `${this.formId}[${fieldId}]`;
        const field = this.form.querySelector(`[name="${fieldName}"]`);
        const container = field?.closest(
          '.campaignbridge-field-wrapper, .campaignbridge-field, .form-field, tr'
        );
        return field && container;
      });

      if (allFieldsExist) {
        callback();
      } else {
        setTimeout(checkReady, 50);
      }
    };

    checkReady();
  }

  private bindEvents(): void {
    // Use event delegation for dynamic content
    this.form.addEventListener('change', event => {
      const target = event.target as HTMLElement;
      if (target.matches('input, select, textarea')) {
        // Small delay to ensure checkbox state is updated
        setTimeout(() => {
          this.evaluateAllConditions();
        }, 10);
      }
    });

    this.form.addEventListener('click', event => {
      const target = event.target as HTMLElement;
      if (target.matches('input[type="checkbox"], input[type="radio"]')) {
        setTimeout(() => {
          this.evaluateAllConditions();
        }, 10);
      }
    });
  }

  private evaluateAllConditions(): void {
    const formData = this.getFormData();

    Object.keys(this.conditionals).forEach(fieldId => {
      const conditional = this.conditionals[fieldId];
      // Fields are named like "form_id[field_name]"
      const fieldName = `${this.formId}[${fieldId}]`;
      const field = this.form.querySelector(
        `[name="${fieldName}"]`
      ) as HTMLElement;
      const fieldContainer = field?.closest(
        '.campaignbridge-field-wrapper, .campaignbridge-field, .form-field, tr'
      ) as HTMLElement;

      if (field && fieldContainer) {
        const shouldShow = this.evaluateCondition(conditional, formData);
        const isRequired = conditional.type === 'required_when' && shouldShow;

        // Update visibility
        if (shouldShow) {
          this.showField(field);
        } else {
          this.hideField(field);
        }

        // Update required state for required_when conditionals
        fieldContainer.setAttribute(
          'data-conditional-required',
          isRequired ? 'true' : 'false'
        );
        field.setAttribute(
          'data-conditional-required',
          isRequired ? 'true' : 'false'
        );

        // Handle HTML required attribute for browser validation
        // Hidden fields should never be required to prevent validation errors
        if (shouldShow && isRequired) {
          field.setAttribute('required', 'required');
          field.setAttribute('aria-required', 'true');
        } else {
          field.removeAttribute('required');
          field.removeAttribute('aria-required');
        }
      }
    });
  }

  private evaluateCondition(
    conditional: ConditionalConfig,
    formData: any
  ): boolean {
    if (!conditional.conditions || !Array.isArray(conditional.conditions)) {
      return true;
    }

    const logicType = conditional.type || 'show_when';

    // Evaluate if all conditions are met
    let allConditionsMet = true;
    for (const rule of conditional.conditions) {
      const fieldValue = formData[rule.field] || '';
      const operator = rule.operator || 'equals';
      const expectedValue = rule.value || '';

      const matches = this.evaluateRule(fieldValue, operator, expectedValue);

      // All conditions in the array must match (AND logic)
      if (!matches) {
        allConditionsMet = false;
        break;
      }
    }

    // Apply logic based on condition type
    switch (logicType) {
      case 'show_when':
        return allConditionsMet; // Show when conditions are met
      case 'hide_when':
        return !allConditionsMet; // Hide when conditions are met (inverse)
      case 'required_when':
        return true; // Visibility not affected by required_when
      default:
        return true;
    }
  }

  private evaluateRule(
    fieldValue: any,
    operator: string,
    expectedValue: any
  ): boolean {
    switch (operator) {
      case 'equals':
        return fieldValue == expectedValue; // eslint-disable-line eqeqeq
      case 'not_equals':
        return fieldValue != expectedValue; // eslint-disable-line eqeqeq
      case 'is_checked':
        return (
          fieldValue !== '' &&
          fieldValue !== null &&
          fieldValue !== undefined &&
          fieldValue !== false &&
          fieldValue !== '0'
        );
      case 'not_checked':
        return (
          fieldValue === '' ||
          fieldValue === null ||
          fieldValue === undefined ||
          fieldValue === false ||
          fieldValue === '0'
        );
      case 'contains':
        return String(fieldValue).indexOf(String(expectedValue)) !== -1;
      case 'greater_than':
        return parseFloat(fieldValue) > parseFloat(expectedValue);
      case 'less_than':
        return parseFloat(fieldValue) < parseFloat(expectedValue);
      default:
        return false;
    }
  }

  private showField(field: HTMLElement): void {
    const fieldContainer = field.closest(
      '.campaignbridge-field-wrapper, .campaignbridge-field, .form-field, tr'
    ) as HTMLElement;

    if (!fieldContainer) {
      return;
    }

    // Ensure the container has the base campaignbridge-field class for CSS targeting
    if (
      !fieldContainer.classList.contains('campaignbridge-field') &&
      !fieldContainer.classList.contains('form-field') &&
      fieldContainer.tagName !== 'TR'
    ) {
      fieldContainer.classList.add('campaignbridge-field');
    }

    fieldContainer.classList.remove('campaignbridge-field--hidden');
    fieldContainer.classList.add(
      'campaignbridge-field--visible',
      'campaignbridge-field--showing'
    );
    fieldContainer.style.display = ''; // Ensure it's visible

    // Remove showing class after animation completes
    setTimeout(() => {
      fieldContainer.classList.remove('campaignbridge-field--showing');
    }, 300);
  }

  private hideField(field: HTMLElement): void {
    const fieldContainer = field.closest(
      '.campaignbridge-field-wrapper, .campaignbridge-field, .form-field, tr'
    ) as HTMLElement;

    if (!fieldContainer) {
      return;
    }

    fieldContainer.classList.remove('campaignbridge-field--visible');
    fieldContainer.classList.add(
      'campaignbridge-field--hidden',
      'campaignbridge-field--hiding'
    );

    // Remove hiding class after animation completes
    setTimeout(() => {
      fieldContainer.classList.remove('campaignbridge-field--hiding');
      fieldContainer.style.display = 'none'; // Ensure it's hidden after animation
    }, 300);
  }

  private getFormData(): any {
    const data: any = {};
    const inputs = this.form.querySelectorAll('input, select, textarea');

    // Track checkbox names to avoid processing hidden inputs with same name
    const checkboxNames = new Set<string>();

    // First pass: collect checkbox names
    inputs.forEach(element => {
      const input = element as HTMLInputElement;
      if (input.type === 'checkbox' && input.name) {
        checkboxNames.add(input.name);
      }
    });

    // Second pass: process inputs, skipping hidden inputs that match checkbox names
    inputs.forEach(element => {
      const input = element as
        | HTMLInputElement
        | HTMLSelectElement
        | HTMLTextAreaElement;
      const fullName = input.name;

      // Skip hidden inputs that have the same name as a checkbox
      if (input.type === 'hidden' && checkboxNames.has(fullName)) {
        return;
      }

      let value: any = input.value;

      if (input.type === 'checkbox') {
        value = (input as HTMLInputElement).checked ? '1' : '0';
      } else if (input.type === 'radio') {
        if (!(input as HTMLInputElement).checked) {
          return; // Skip setting for unchecked radios
        }
      }

      if (fullName) {
        // Parse WordPress array-style names like "form_id[field_name]" to just "field_name"
        const fieldId = this.parseFieldName(fullName);
        if (fieldId) {
          data[fieldId] = value;
        }
      }
    });

    return data;
  }

  private parseFieldName(fullName: string): string | null {
    // Handle WordPress array-style names like "form_id[field_name]"
    const match = fullName.match(new RegExp(`^${this.formId}\\[(.+)\\]$`));
    if (match) {
      return match[1];
    }
    return fullName; // Fallback for non-array style names
  }
}
