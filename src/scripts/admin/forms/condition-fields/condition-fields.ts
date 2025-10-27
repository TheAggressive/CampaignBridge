/**
 * Client-side conditional field engine for CampaignBridge forms
 *
 * Uses API-driven approach - sends form data to server for evaluation
 * instead of client-side logic duplication.
 */
export class ConditionalEngine {
  private form: HTMLFormElement | null;
  private formId: string;
  private apiEndpoint: string;
  private ajaxAction: string;
  private evaluationInProgress: boolean = false;

  constructor(formId: string) {
    this.formId = formId;
    // Use WordPress AJAX URL
    this.apiEndpoint = (window as any).ajaxurl || '/wp-admin/admin-ajax.php';
    this.form = document.getElementById(formId) as HTMLFormElement;

    if (!this.form) {
      return;
    }

    // Get AJAX action from data attribute
    this.ajaxAction =
      this.form.getAttribute('data-conditional-action') ||
      'campaignbridge_evaluate_conditions';

    this.init();
  }

  private init(): void {
    // Wait for form to be fully rendered
    this.waitForFormReady(() => {
      // Initially hide all conditional fields to prevent FOUC
      this.hideAllConditionalFields();

      // Then evaluate conditions to show the ones that should be visible
      this.evaluateConditions();
    });

    // Bind events for future changes
    this.bindEvents();
  }

  private waitForFormReady(callback: () => void): void {
    const checkReady = () => {
      // Simple check - wait for form to have inputs
      if (this.form && this.form.querySelector('input, select, textarea')) {
        callback();
      } else {
        setTimeout(checkReady, 100);
      }
    };

    checkReady();
  }

  private bindEvents(): void {
    // Bind change events to all form inputs
    this.form.addEventListener('change', event => {
      const target = event.target as HTMLInputElement;
      if (target && target.name) {
        // Debounce evaluation to avoid excessive API calls
        this.debouncedEvaluate();
      }
    });

    // Also bind input events for text fields (real-time feedback)
    this.form.addEventListener('input', event => {
      const target = event.target as HTMLInputElement;
      if (
        target &&
        (target.type === 'text' ||
          target.type === 'email' ||
          target.type === 'url')
      ) {
        this.debouncedEvaluate();
      }
    });
  }

  private debouncedEvaluate = this.debounce(() => {
    this.evaluateConditions();
  }, 300);

  /**
   * Hide all conditional fields initially to prevent FOUC
   */
  private hideAllConditionalFields(): void {
    // Find elements with conditional-hidden class (containers that should be hidden)
    const hiddenElements = this.form.querySelectorAll(
      '.campaignbridge-conditional-hidden'
    );
    hiddenElements.forEach(element => {
      const targetElement = element as HTMLElement;

      // Check if already hidden by CSS
      const computedStyle = window.getComputedStyle(targetElement);
      const isAlreadyHidden = computedStyle.display === 'none';

      if (!isAlreadyHidden) {
        targetElement.style.display = 'none';
      }
    });
  }

  /**
   * Evaluate conditions by sending form data to server via AJAX
   */
  private evaluateConditions(): void {
    if (this.evaluationInProgress) {
      return; // Prevent concurrent evaluations
    }

    this.evaluationInProgress = true;

    const formData = this.getFormData();

    // Get nonce from the form's hidden input
    const nonceInput = this.form.querySelector(
      `input[name="${this.formId}_wpnonce"]`
    ) as HTMLInputElement;
    const nonce = nonceInput ? nonceInput.value : '';

    // Use jQuery AJAX for WordPress compatibility
    (window as any).jQuery.ajax({
      url: this.apiEndpoint,
      method: 'POST',
      data: {
        action: this.ajaxAction,
        form_id: this.formId,
        data: formData,
        nonce: nonce,
      },
      success: (result: any) => {
        // Security: Validate response structure
        if (typeof result !== 'object' || result === null) {
          return;
        }

        // Update field visibility and requirements based on server response
        if (
          result.success &&
          result.fields &&
          typeof result.fields === 'object'
        ) {
          this.updateFields(result.fields);
        }
      },
      error: (xhr: any, status: string, error: string) => {
        console.error('[ConditionalEngine] AJAX Error:', {
          status: xhr.status,
          statusText: xhr.statusText,
          responseText: xhr.responseText,
          error: error,
        });

        // Security: Don't retry on auth errors
        if (xhr.status === 401 || xhr.status === 403) {
          console.error(
            '[ConditionalEngine] Authentication/Security error - not retrying'
          );
          return;
        }

        // Could implement retry logic here for network errors
        // For now, just log the error
      },
      complete: () => {
        this.evaluationInProgress = false;
      },
    });
  }

  /**
   * Update field visibility and requirements based on server response
   */
  private updateFields(fieldStates: {
    [fieldId: string]: { visible: boolean; required: boolean };
  }): void {
    Object.entries(fieldStates).forEach(([fieldId, state]) => {
      const fieldName = `${this.formId}[${fieldId}]`;
      const field = this.form.querySelector(
        `[name="${fieldName}"]`
      ) as HTMLElement;

      if (field) {
        const conditionalWrapper = field.closest(
          '.campaignbridge-conditional-field'
        ) as HTMLElement;

        // Update visibility
        if (state.visible) {
          this.showField(field, conditionalWrapper);
        } else {
          this.hideField(field, conditionalWrapper);
        }

        // Update required attribute
        if (state.visible && state.required) {
          field.setAttribute('required', 'required');
          field.setAttribute('aria-required', 'true');
        } else {
          field.removeAttribute('required');
          field.removeAttribute('aria-required');
        }
      }
    });
  }

  private showField(
    field: HTMLElement,
    conditionalWrapper?: HTMLElement
  ): void {
    const targetElement =
      conditionalWrapper ||
      (field.closest(
        '.campaignbridge-field-wrapper, .campaignbridge-field, .form-field, tr'
      ) as HTMLElement);

    if (!targetElement) {
      return;
    }

    // Remove FOUC prevention class and show the element
    targetElement.classList.remove('campaignbridge-conditional-hidden');
    targetElement.style.display = '';

    // Handle conditional wrapper specially
    if (conditionalWrapper) {
      conditionalWrapper.classList.add('campaignbridge-conditional-visible');
      return;
    }

    // Legacy handling for non-wrapped fields
    if (
      !targetElement.classList.contains('campaignbridge-field') &&
      !targetElement.classList.contains('form-field') &&
      targetElement.tagName !== 'TR'
    ) {
      targetElement.classList.add('campaignbridge-field');
    }

    targetElement.classList.remove('campaignbridge-field--hidden');
    targetElement.classList.add(
      'campaignbridge-field--visible',
      'campaignbridge-field--showing'
    );
    targetElement.style.display = '';

    setTimeout(() => {
      targetElement.classList.remove('campaignbridge-field--showing');
    }, 300);
  }

  private hideField(
    field: HTMLElement,
    conditionalWrapper?: HTMLElement
  ): void {
    const targetElement =
      conditionalWrapper ||
      (field.closest(
        '.campaignbridge-field-wrapper, .campaignbridge-field, .form-field, tr'
      ) as HTMLElement);

    if (!targetElement) {
      return;
    }

    // Handle conditional wrapper specially
    if (conditionalWrapper) {
      conditionalWrapper.classList.remove('campaignbridge-conditional-visible');
      conditionalWrapper.classList.add('campaignbridge-conditional-hidden');
      return;
    }

    // Legacy handling for non-wrapped fields
    targetElement.classList.remove('campaignbridge-field--visible');
    targetElement.classList.add(
      'campaignbridge-field--hidden',
      'campaignbridge-field--hiding'
    );

    setTimeout(() => {
      targetElement.classList.remove('campaignbridge-field--hiding');
      targetElement.style.display = 'none';
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

  /**
   * Utility method to debounce function calls
   */
  private debounce<T extends (...args: any[]) => any>(
    func: T,
    wait: number
  ): (...args: Parameters<T>) => void {
    let timeout: NodeJS.Timeout;
    return (...args: Parameters<T>) => {
      clearTimeout(timeout);
      timeout = setTimeout(() => func(...args), wait);
    };
  }
}
