/**
 * Form Loading States Manager
 *
 * Handles loading states for form submission including button text changes,
 * disabling buttons to prevent double-submission, visual feedback, and accessibility.
 *
 * Features:
 * - Memory management with cleanup capabilities
 * - Accessibility support with ARIA attributes
 * - Configurable loading states and timeouts
 * - Error handling and state recovery
 * - Screen reader announcements
 */

interface FormLoadingConfig {
  formId: string;
  loadingText?: string;
  submitText?: string;
  disableOnSubmit?: boolean;
  showSpinner?: boolean;
  timeout?: number; // Auto-reset timeout in milliseconds
  enableAccessibility?: boolean;
}

interface FormLoadingState {
  isLoading: boolean;
  originalText: string;
  startTime: number;
}

/**
 * Form Loading Manager Class
 */
export class FormLoadingManager {
  private static instance: FormLoadingManager;
  private formStates: Map<string, FormLoadingState> = new Map();
  private eventListeners: Map<
    string,
    { element: HTMLElement; type: string; listener: EventListener }
  > = new Map();
  private domCache: Map<string, HTMLElement | null> = new Map();
  private config: FormLoadingConfig;
  private cleanupTimeouts: Map<string, number> = new Map();

  constructor(config: FormLoadingConfig) {
    this.config = {
      loadingText: 'Loading...',
      submitText: 'Submit',
      disableOnSubmit: true,
      showSpinner: true,
      timeout: 30000, // 30 seconds default
      enableAccessibility: true,
      ...config,
    };

    this.initialize();
  }

  public static getInstance(config: FormLoadingConfig): FormLoadingManager {
    if (!FormLoadingManager.instance) {
      FormLoadingManager.instance = new FormLoadingManager(config);
    }
    return FormLoadingManager.instance;
  }

  /**
   * Initialize the form loading functionality
   */
  private initialize(): void {
    const form = this.getCachedElement(this.config.formId);
    if (!form) {
      console.warn(
        `Form with ID ${this.config.formId} not found for loading script`
      );
      return;
    }

    this.setupFormSubmissionHandler();
    this.setupAccessibility();

    // Add loading class to form for styling
    form.classList.add('campaignbridge-form--loading-enabled');
  }

  /**
   * Set up form submission handler
   */
  private setupFormSubmissionHandler(): void {
    const form = this.getCachedElement(this.config.formId);
    if (!form) return;

    const submitHandler = () => {
      this.startLoading();
    };

    form.addEventListener('submit', submitHandler);

    this.eventListeners.set(`${this.config.formId}_submit`, {
      element: form,
      type: 'submit',
      listener: submitHandler,
    });
  }

  /**
   * Start loading state
   */
  public startLoading(): void {
    const form = this.getCachedElement(this.config.formId);
    const submitBtn = this.findSubmitButton();

    if (!form || !submitBtn) return;

    // Prevent multiple loading states
    if (this.isLoading()) return;

    const originalText =
      (submitBtn as HTMLInputElement).value ||
      submitBtn.textContent ||
      this.config.submitText!;

    // Set loading state
    this.formStates.set(this.config.formId, {
      isLoading: true,
      originalText: originalText,
      startTime: Date.now(),
    });

    // Update button
    this.updateButtonState(submitBtn, true);

    // Set up auto-reset timeout
    const timeoutId = setTimeout(() => {
      this.resetLoading();
    }, this.config.timeout);

    this.cleanupTimeouts.set(this.config.formId, timeoutId);

    // Update form state
    form.classList.add('campaignbridge-form--loading');

    // Announce to screen readers
    if (this.config.enableAccessibility) {
      this.announceLoadingState(true);
    }
  }

  /**
   * Reset loading state
   */
  public resetLoading(): void {
    const form = this.getCachedElement(this.config.formId);
    const submitBtn = this.findSubmitButton();

    if (!form || !submitBtn) return;

    const state = this.formStates.get(this.config.formId);
    if (!state || !state.isLoading) return;

    // Clear timeout
    const timeoutId = this.cleanupTimeouts.get(this.config.formId);
    if (timeoutId) {
      clearTimeout(timeoutId);
      this.cleanupTimeouts.delete(this.config.formId);
    }

    // Update button
    this.updateButtonState(submitBtn, false, state.originalText);

    // Clear state
    this.formStates.delete(this.config.formId);

    // Update form state
    form.classList.remove('campaignbridge-form--loading');

    // Announce to screen readers
    if (this.config.enableAccessibility) {
      this.announceLoadingState(false);
    }
  }

  /**
   * Check if form is currently loading
   */
  public isLoading(): boolean {
    const state = this.formStates.get(this.config.formId);
    return state?.isLoading || false;
  }

  /**
   * Get loading duration
   */
  public getLoadingDuration(): number {
    const state = this.formStates.get(this.config.formId);
    if (!state || !state.isLoading) return 0;

    return Date.now() - state.startTime;
  }

  /**
   * Update button state
   */
  private updateButtonState(
    button: HTMLElement,
    isLoading: boolean,
    originalText?: string
  ): void {
    if (button instanceof HTMLInputElement) {
      // Input button
      if (isLoading) {
        button.value = this.config.loadingText!;
        button.disabled = this.config.disableOnSubmit!;
      } else {
        button.value = originalText || this.config.submitText!;
        button.disabled = false;
      }
    } else {
      // Button element
      if (isLoading) {
        button.textContent = this.config.loadingText!;
        button.setAttribute('disabled', 'disabled');
      } else {
        button.textContent = originalText || this.config.submitText!;
        button.removeAttribute('disabled');
      }
    }

    // Add/remove loading class
    if (isLoading) {
      button.classList.add('campaignbridge-btn--loading');
    } else {
      button.classList.remove('campaignbridge-btn--loading');
    }

    // Add spinner if enabled
    if (this.config.showSpinner!) {
      if (isLoading) {
        this.addSpinner(button);
      } else {
        this.removeSpinner(button);
      }
    }

    // Update accessibility attributes
    if (this.config.enableAccessibility!) {
      button.setAttribute('aria-busy', isLoading ? 'true' : 'false');
    }
  }

  /**
   * Add loading spinner to button
   */
  private addSpinner(button: HTMLElement): void {
    // Remove existing spinner
    this.removeSpinner(button);

    const spinner = document.createElement('span');
    spinner.className = 'campaignbridge-spinner';
    spinner.setAttribute('aria-hidden', 'true');

    // Insert spinner before text content
    if (button.firstChild) {
      button.insertBefore(spinner, button.firstChild);
    } else {
      button.appendChild(spinner);
    }
  }

  /**
   * Remove loading spinner from button
   */
  private removeSpinner(button: HTMLElement): void {
    const spinner = button.querySelector('.campaignbridge-spinner');
    if (spinner) {
      spinner.remove();
    }
  }

  /**
   * Find submit button in form
   */
  private findSubmitButton(): HTMLElement | null {
    const form = this.getCachedElement(this.config.formId);
    if (!form) return null;

    // Try different selectors for submit buttons
    const selectors = [
      'input[type="submit"]',
      'button[type="submit"]',
      '.campaignbridge-submit-btn',
      'button:not([type]), input[type="button"]', // Fallback for buttons without explicit type
    ];

    for (const selector of selectors) {
      const button = form.querySelector<HTMLElement>(selector);
      if (button) return button;
    }

    return null;
  }

  /**
   * Set up accessibility features
   */
  private setupAccessibility(): void {
    if (!this.config.enableAccessibility) return;

    const form = this.getCachedElement(this.config.formId);
    if (!form) return;

    // Add live region for announcements
    let liveRegion = form.querySelector('.campaignbridge-form__status');
    if (!liveRegion) {
      liveRegion = document.createElement('div');
      liveRegion.className = 'campaignbridge-form__status';
      liveRegion.setAttribute('aria-live', 'polite');
      liveRegion.setAttribute('aria-atomic', 'true');
      (liveRegion as HTMLElement).style.position = 'absolute';
      (liveRegion as HTMLElement).style.left = '-10000px';
      (liveRegion as HTMLElement).style.width = '1px';
      (liveRegion as HTMLElement).style.height = '1px';
      (liveRegion as HTMLElement).style.overflow = 'hidden';

      form.appendChild(liveRegion);
    }
  }

  /**
   * Announce loading state to screen readers
   */
  private announceLoadingState(isLoading: boolean): void {
    const form = this.getCachedElement(this.config.formId);
    if (!form) return;

    const liveRegion = form.querySelector('.campaignbridge-form__status');
    if (liveRegion) {
      liveRegion.textContent = isLoading
        ? 'Form submission in progress, please wait...'
        : 'Form ready for submission.';
    }
  }

  /**
   * Get cached DOM element
   */
  private getCachedElement(id: string): HTMLElement | null {
    if (!this.domCache.has(id)) {
      this.domCache.set(id, document.getElementById(id));
    }
    return this.domCache.get(id) || null;
  }

  /**
   * Clear DOM cache
   */
  private clearDomCache(): void {
    this.domCache.clear();
  }

  /**
   * Remove field event listeners
   */
  private removeFieldEventListeners(): void {
    this.eventListeners.forEach(listenerInfo => {
      listenerInfo.element.removeEventListener(
        listenerInfo.type,
        listenerInfo.listener
      );
    });
    this.eventListeners.clear();
  }

  /**
   * Clear all timeouts
   */
  private clearAllTimeouts(): void {
    this.cleanupTimeouts.forEach(timeoutId => {
      clearTimeout(timeoutId);
    });
    this.cleanupTimeouts.clear();
  }

  /**
   * Destroy the loading manager and clean up resources
   */
  public destroy(): void {
    this.removeFieldEventListeners();
    this.clearAllTimeouts();
    this.clearDomCache();

    const form = this.getCachedElement(this.config.formId);
    if (form) {
      form.classList.remove('campaignbridge-form--loading-enabled');
      form.classList.remove('campaignbridge-form--loading');
    }

    this.formStates.clear();

    if (FormLoadingManager.instance === this) {
      FormLoadingManager.instance = null!;
    }
  }
}

/**
 * Legacy function for backward compatibility
 */
export function initFormLoading(config: FormLoadingConfig): void {
  FormLoadingManager.getInstance(config);
}

// Auto-initialize if config is available on window
declare global {
  // eslint-disable-next-line no-unused-vars -- Global type declaration for TypeScript, used at runtime.
  interface Window {
    campaignbridgeFormLoading?: FormLoadingConfig;
  }
}

if (window.campaignbridgeFormLoading) {
  FormLoadingManager.getInstance(window.campaignbridgeFormLoading);
}
