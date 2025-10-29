/**
 * Client-side conditional field engine for CampaignBridge forms
 *
 * Uses API-driven approach - sends form data to server for evaluation
 * instead of client-side logic duplication. Includes enhanced UX with
 * loading states, error handling, and accessibility features.
 */
export class ConditionalEngine {
  private form: HTMLFormElement | null;
  private formId: string;
  private apiEndpoint: string;
  private ajaxAction: string;
  private evaluationInProgress: boolean = false;
  private initialized: boolean = false;
  private debouncedEvaluate: () => void;
  private evaluationCache: Map<string, any> = new Map(); // Cache evaluation results
  private lastFormData: any = null; // Track last sent data for delta updates
  private loadingIndicator: HTMLElement | null = null;
  private errorContainer: HTMLElement | null = null;
  private retryButton: HTMLElement | null = null;
  private evaluationTimeout: number | null = null;

  constructor(formId: string) {
    this.formId = formId;
    this.apiEndpoint = (window as any).ajaxurl || '/wp-admin/admin-ajax.php';
    this.form = document.getElementById(formId) as HTMLFormElement;

    if (!this.form) {
      return;
    }

    this.ajaxAction =
      this.form.getAttribute('data-conditional-action') ||
      'campaignbridge_evaluate_conditions';
    this.init();
  }

  private init(): void {
    // Prevent multiple initializations
    if (this.initialized) {
      return;
    }
    this.initialized = true;

    // Create UI elements for better UX
    this.createLoadingIndicator();
    this.createErrorContainer();

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
    let callbackCalled = false;

    const checkReady = () => {
      // Check immediately if form is ready
      if (this.form && this.form.querySelector('input, select, textarea')) {
        if (!callbackCalled) {
          callbackCalled = true;
          callback();
        }
        return;
      }

      // If not ready, check again in 50ms (faster polling)
      setTimeout(checkReady, 50);
    };

    checkReady();
  }

  private bindEvents(): void {
    // Simple change event binding - evaluate on any form change
    this.form.addEventListener('change', () => {
      this.debouncedEvaluate();
    });
  }

  private debouncedEvaluate = this.debounce(() => {
    this.evaluateConditions();
  }, 100); // Optimized 100ms debounce for snappier UX

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
   * Evaluate conditions with intelligent caching and enhanced UX
   */
  private evaluateConditions(): void {
    if (this.evaluationInProgress) {
      return; // Prevent concurrent evaluations
    }

    const formData = this.getFormData();
    const cacheKey = JSON.stringify(formData);

    // Check client-side cache first
    if (this.evaluationCache.has(cacheKey)) {
      const cachedResult = this.evaluationCache.get(cacheKey);
      this.updateFields(cachedResult.fields);
      return;
    }

    // Show loading indicator for better UX
    this.showLoading();
    this.hideError(); // Hide any previous errors

    // Send full form data for accurate conditional evaluation
    const deltaData = formData;

    this.evaluationInProgress = true;
    this.lastFormData = formData;

    // Set timeout for the request
    this.evaluationTimeout = window.setTimeout(() => {
      if (this.evaluationInProgress) {
        this.evaluationInProgress = false;
        this.hideLoading();
        this.showError(
          'Request timed out. Please check your connection and try again.'
        );
      }
    }, 30000); // 30 second timeout

    // Send data to server for evaluation
    (window as any).jQuery.ajax({
      url: this.apiEndpoint,
      method: 'POST',
      timeout: 25000, // 25 seconds (jQuery timeout)
      data: {
        action: this.ajaxAction,
        form_id: this.formId,
        data: deltaData,
        nonce: this.getNonce(),
      },
      success: (result: any) => {
        this.hideLoading();

        if (result.success && result.fields) {
          // Cache successful results
          this.evaluationCache.set(cacheKey, result);

          // Limit cache size to prevent memory leaks
          if (this.evaluationCache.size > 10) {
            const firstKey = this.evaluationCache.keys().next().value;
            this.evaluationCache.delete(firstKey);
          }

          this.updateFields(result.fields);
        } else {
          // Handle server-side validation errors
          this.showError(
            'Server returned an invalid response. Please try again.'
          );
        }
      },
      error: (xhr: any, textStatus: string, errorThrown: string) => {
        this.hideLoading();

        console.error(
          '[ConditionalEngine] AJAX Error:',
          xhr.status,
          textStatus,
          errorThrown
        );

        let errorMessage = 'Failed to update form. Please try again.';

        // Provide specific error messages based on status
        if (xhr.status === 400) {
          errorMessage =
            'Invalid form data. Please check your input and try again.';
        } else if (xhr.status === 403) {
          errorMessage = 'You do not have permission to update this form.';
        } else if (xhr.status === 429) {
          errorMessage =
            'Too many requests. Please wait a moment before trying again.';
        } else if (xhr.status >= 500) {
          errorMessage = 'Server error occurred. Please try again later.';
        } else if (textStatus === 'timeout') {
          errorMessage =
            'Request timed out. Please check your connection and try again.';
        }

        this.showError(errorMessage);

        // For critical errors, also announce to screen readers
        if (xhr.status >= 500) {
          this.announceToScreenReader(
            'Form update failed due to server error. Some fields may not update correctly.'
          );
        }
      },
      complete: () => {
        this.evaluationInProgress = false;
        if (this.evaluationTimeout) {
          clearTimeout(this.evaluationTimeout);
          this.evaluationTimeout = null;
        }
      },
    });
  }

  private getNonce(): string {
    const nonceInput = this.form.querySelector(
      `input[name="${this.formId}_wpnonce"]`
    ) as HTMLInputElement;
    return nonceInput ? nonceInput.value : '';
  }

  /**
   * Clear evaluation cache (useful for debugging or when form structure changes)
   */
  public clearCache(): void {
    this.evaluationCache.clear();
    this.lastFormData = null;
  }

  /**
   * Update field visibility and requirements based on server response
   */
  private updateFields(fieldStates: {
    [fieldId: string]: { visible: boolean; required: boolean };
  }): void {
    // Track changes for accessibility announcements.
    const changes: string[] = [];

    // Update each field based on its new state
    Object.entries(fieldStates).forEach(([fieldId, state]) => {
      const fieldName = `${this.formId}[${fieldId}]`;
      const field = this.form.querySelector(
        `[name="${fieldName}"]`
      ) as HTMLElement;

      if (field) {
        const conditionalWrapper = field.closest(
          '.campaignbridge-conditional-field'
        ) as HTMLElement;

        // Get current visibility state.
        const wasVisible = !conditionalWrapper?.classList.contains(
          'campaignbridge-conditional-hidden'
        );
        const isVisible = state.visible;

        // Update visibility based on the state
        if (state.visible) {
          this.showField(field, conditionalWrapper);
        } else {
          this.hideField(field, conditionalWrapper);
        }

        // Update required attribute and announce changes
        this.updateFieldRequirements(field, state, fieldId, changes);

        // Handle focus management when fields disappear
        if (wasVisible && !isVisible) {
          this.handleFieldHidden(field, fieldId);
        }

        // Track changes for announcements
        if (wasVisible !== isVisible) {
          const fieldLabel = this.getFieldLabel(field, fieldId);
          if (isVisible) {
            changes.push(`${fieldLabel} is now available`);
          } else {
            changes.push(`${fieldLabel} is now hidden`);
          }
        }
      }
    });

    // Announce changes to screen readers
    if (changes.length > 0) {
      this.announceChanges(changes);
    }
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

    // Remove inert attribute to make element focusable and accessible again
    targetElement.inert = false;

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

    targetElement.classList.remove(
      'campaignbridge-field--hidden',
      'campaignbridge-field--hiding'
    );
    targetElement.classList.add('campaignbridge-field--visible');
    targetElement.style.display = '';
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
      conditionalWrapper.style.display = 'none';
      conditionalWrapper.inert = true;
      return;
    }

    // Legacy handling for non-wrapped fields - hide instantly
    targetElement.classList.remove(
      'campaignbridge-field--visible',
      'campaignbridge-field--showing'
    );
    targetElement.classList.add('campaignbridge-field--hidden');
    targetElement.style.display = 'none';
    targetElement.inert = true;
  }

  /**
   * Update field requirements with accessibility announcements
   */
  private updateFieldRequirements(
    field: HTMLElement,
    state: { visible: boolean; required: boolean },
    fieldId: string,
    changes: string[]
  ): void {
    const wasRequired = field.hasAttribute('aria-required');

    if (state.visible && state.required) {
      field.setAttribute('required', 'required');
      field.setAttribute('aria-required', 'true');

      // Announce requirement change
      if (!wasRequired) {
        const fieldLabel = this.getFieldLabel(field, fieldId);
        changes.push(`${fieldLabel} is now required`);
      }
    } else {
      field.removeAttribute('required');
      field.removeAttribute('aria-required');

      // Announce requirement change
      if (wasRequired) {
        const fieldLabel = this.getFieldLabel(field, fieldId);
        changes.push(`${fieldLabel} is no longer required`);
      }
    }
  }

  /**
   * Handle focus management when a field is hidden
   */
  private handleFieldHidden(field: HTMLElement, fieldId: string): void {
    // If the hidden field currently has focus, move focus to a logical next element
    if (document.activeElement === field) {
      // Find the next focusable element
      const focusableElements = this.form.querySelectorAll(
        'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
      );

      const currentIndex = Array.from(focusableElements).indexOf(
        field as Element
      );
      if (currentIndex !== -1 && currentIndex < focusableElements.length - 1) {
        (focusableElements[currentIndex + 1] as HTMLElement).focus();
      }
    }
  }

  /**
   * Get human-readable field label for announcements
   */
  private getFieldLabel(field: HTMLElement, fieldId: string): string {
    // Try to find associated label
    const label =
      this.form.querySelector(`label[for="${field.id}"]`) ||
      field.closest('tr')?.querySelector('th label') ||
      field.closest('.campaignbridge-field')?.querySelector('label');

    if (label) {
      return (label as HTMLElement).textContent?.trim() || fieldId;
    }

    // Fallback to field ID with spaces
    return fieldId.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
  }

  /**
   * Announce changes to screen readers
   */
  private announceChanges(changes: string[]): void {
    // Create or update a live region for announcements
    let liveRegion = this.form.querySelector(
      '.campaignbridge-a11y-announcements'
    ) as HTMLElement;

    if (!liveRegion) {
      liveRegion = document.createElement('div');
      liveRegion.className = 'campaignbridge-a11y-announcements';
      liveRegion.setAttribute('aria-live', 'polite');
      liveRegion.setAttribute('aria-atomic', 'true');
      liveRegion.style.position = 'absolute';
      liveRegion.style.left = '-10000px';
      liveRegion.style.width = '1px';
      liveRegion.style.height = '1px';
      liveRegion.style.overflow = 'hidden';
      this.form.appendChild(liveRegion);
    }

    // Announce the changes
    liveRegion.textContent = changes.join('. ') + '.';
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
   * Create loading indicator for better UX
   */
  private createLoadingIndicator(): void {
    if (!this.form) return;

    this.loadingIndicator = document.createElement('div');
    this.loadingIndicator.className = 'campaignbridge-conditional-loading';
    this.loadingIndicator.innerHTML = `
      <div class="loading-content">
        <div class="spinner"></div>
        <span class="loading-text">Updating form...</span>
      </div>
    `;
    this.loadingIndicator.style.cssText = `
      display: none;
      position: absolute;
      top: 10px;
      right: 10px;
      background: #fff;
      border: 1px solid #ddd;
      border-radius: 4px;
      padding: 8px 12px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
      z-index: 1000;
      font-size: 12px;
      color: #666;
    `;

    this.form.style.position = 'relative';
    this.form.appendChild(this.loadingIndicator);
  }

  /**
   * Create error container for handling failures gracefully
   */
  private createErrorContainer(): void {
    if (!this.form) return;

    this.errorContainer = document.createElement('div');
    this.errorContainer.className = 'campaignbridge-conditional-error';
    this.errorContainer.style.cssText = `
      display: none;
      background: #ffe6e6;
      border: 1px solid #d63638;
      border-radius: 4px;
      padding: 12px 16px;
      margin: 10px 0;
      color: #d63638;
      font-size: 13px;
    `;

    this.retryButton = document.createElement('button');
    this.retryButton.type = 'button';
    this.retryButton.className = 'button button-small';
    this.retryButton.textContent = 'Retry';
    this.retryButton.style.marginLeft = '10px';
    this.retryButton.addEventListener('click', () => {
      this.hideError();
      this.evaluateConditions();
    });

    this.errorContainer.appendChild(this.retryButton);

    // Insert error container at the top of the form
    const firstElement = this.form.firstElementChild;
    if (firstElement) {
      this.form.insertBefore(this.errorContainer, firstElement);
    } else {
      this.form.appendChild(this.errorContainer);
    }
  }

  /**
   * Show loading indicator
   */
  private showLoading(): void {
    if (this.loadingIndicator) {
      this.loadingIndicator.style.display = 'block';
    }
  }

  /**
   * Hide loading indicator
   */
  private hideLoading(): void {
    if (this.loadingIndicator) {
      this.loadingIndicator.style.display = 'none';
    }
  }

  /**
   * Show error message
   */
  private showError(message: string): void {
    if (this.errorContainer) {
      this.errorContainer.textContent = message;
      this.errorContainer.appendChild(this.retryButton!);
      this.errorContainer.style.display = 'block';

      // Announce to screen readers
      this.announceToScreenReader(`Error: ${message}`);
    }
  }

  /**
   * Hide error message
   */
  private hideError(): void {
    if (this.errorContainer) {
      this.errorContainer.style.display = 'none';
    }
  }

  /**
   * Announce message to screen readers
   */
  private announceToScreenReader(message: string): void {
    const announcement = document.createElement('div');
    announcement.setAttribute('aria-live', 'polite');
    announcement.setAttribute('aria-atomic', 'true');
    announcement.style.position = 'absolute';
    announcement.style.left = '-10000px';
    announcement.style.width = '1px';
    announcement.style.height = '1px';
    announcement.style.overflow = 'hidden';
    announcement.textContent = message;

    document.body.appendChild(announcement);

    // Remove after announcement
    setTimeout(() => {
      document.body.removeChild(announcement);
    }, 1000);
  }

  /**
   * Clear cached evaluation results
   */
  private clearCache(): void {
    this.lastFormData = null;
    this.lastResult = null;
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
