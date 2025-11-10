/**
 * Manages UI state and DOM manipulation for conditional fields
 */

import { performanceMonitor } from './performance-monitor';
import type {
  ConditionalEngineConfig,
  FieldState,
  FieldStateMap,
  IConditionalAccessibility,
} from './types';

export class ConditionalUIManager {
  private form: HTMLFormElement;
  private formId: string;
  private loadingIndicator: HTMLElement | null = null;
  private errorContainer: HTMLElement | null = null;
  private retryButton: HTMLButtonElement | null = null;
  private eventListeners: Array<{
    element: EventTarget;
    type: string;
    handler: EventListener;
  }> = [];

  constructor(form: HTMLFormElement, config: ConditionalEngineConfig) {
    this.form = form;
    this.formId = config.formId;
  }

  /**
   * Initialize UI elements
   */
  public initialize(): void {
    this.createLoadingIndicator();
    this.createErrorContainer();
    this.hideAllConditionalFields();
  }

  /**
   * Hide all conditional fields initially to prevent FOUC
   */
  public hideAllConditionalFields(): void {
    const hiddenElements = this.form.querySelectorAll(
      '.campaignbridge-conditional-hidden'
    );
    hiddenElements.forEach(element => {
      const targetElement = element as HTMLElement;
      const computedStyle = window.getComputedStyle(targetElement);
      const isAlreadyHidden = computedStyle.display === 'none';

      if (!isAlreadyHidden) {
        targetElement.style.display = 'none';
      }
    });
  }

  /**
   * Update field visibility and requirements based on server response
   */
  public updateFields(
    fieldStates: FieldStateMap,
    accessibilityManager: IConditionalAccessibility
  ): void {
    const startTime = performance.now();
    const changes = this.processFieldUpdates(fieldStates, accessibilityManager);

    // Announce changes to screen readers
    if (changes.length > 0) {
      accessibilityManager.announceFieldChanges(changes);
    }

    // Record performance metrics
    const duration = performance.now() - startTime;
    const operationsCount = Object.keys(fieldStates).length;
    performanceMonitor.recordDomOperation(
      'field_update',
      operationsCount,
      duration
    );
  }

  /**
   * Process field updates and return change announcements
   */
  private processFieldUpdates(
    fieldStates: FieldStateMap,
    accessibilityManager: IConditionalAccessibility
  ): string[] {
    const changes: string[] = [];

    // Set up form landmarks for accessibility
    accessibilityManager.setupFormLandmarks();

    Object.entries(fieldStates).forEach(([fieldId, state]) => {
      const fieldResult = this.processSingleFieldUpdate(
        fieldId,
        state,
        accessibilityManager
      );

      if (fieldResult.changeMessage) {
        changes.push(fieldResult.changeMessage);
      }
    });

    return changes;
  }

  /**
   * Process a single field update
   */
  private processSingleFieldUpdate(
    fieldId: string,
    state: FieldState,
    accessibilityManager: IConditionalAccessibility
  ): { changeMessage?: string } {
    const field = this.findFieldElement(fieldId);
    if (!field) return {};

    const conditionalWrapper = field.closest(
      '.campaignbridge-conditional-field'
    ) as HTMLElement;

    const wasVisible = this.isFieldVisible(conditionalWrapper);
    const isVisible = state.visible;

    // Update field visibility and requirements
    this.updateFieldVisibilityAndRequirements(
      field,
      conditionalWrapper,
      state,
      fieldId,
      accessibilityManager
    );

    // Handle focus management when fields disappear
    if (wasVisible && !isVisible) {
      accessibilityManager.handleFieldHidden(field, fieldId);
    }

    // Return change message for announcements
    if (wasVisible !== isVisible) {
      const fieldLabel = accessibilityManager.getFieldLabel(field, fieldId);
      return {
        changeMessage: isVisible
          ? `${fieldLabel} is now available`
          : `${fieldLabel} is now hidden`,
      };
    }

    return {};
  }

  /**
   * Find field element by field ID
   */
  private findFieldElement(fieldId: string): HTMLElement | null {
    const fieldName = `${this.formId}[${fieldId}]`;
    return this.form.querySelector(`[name="${fieldName}"]`) as HTMLElement;
  }

  /**
   * Check if a field is currently visible
   */
  private isFieldVisible(conditionalWrapper: HTMLElement | null): boolean {
    return !conditionalWrapper?.classList.contains(
      'campaignbridge-conditional-hidden'
    );
  }

  /**
   * Update field visibility and requirements in one operation
   */
  private updateFieldVisibilityAndRequirements(
    field: HTMLElement,
    conditionalWrapper: HTMLElement | null,
    state: FieldState,
    fieldId: string,
    accessibilityManager: IConditionalAccessibility
  ): void {
    // Update visibility
    if (state.visible) {
      this.showField(field, conditionalWrapper);
    } else {
      this.hideField(field, conditionalWrapper);
    }

    // Update requirements
    this.updateFieldRequirements(
      field,
      state,
      fieldId,
      [], // Changes array not needed here
      accessibilityManager
    );
  }

  /**
   * Show a conditional field
   */
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
  }

  /**
   * Hide a conditional field
   */
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
  }

  /**
   * Update field requirements and announce changes
   */
  private updateFieldRequirements(
    field: HTMLElement,
    state: { visible: boolean; required: boolean },
    fieldId: string,
    changes: string[],
    accessibilityManager: IConditionalAccessibility
  ): void {
    const wasRequired = field.hasAttribute('required');
    const isRequired = state.required;

    if (isRequired !== wasRequired) {
      if (isRequired) {
        changes.push(
          `${accessibilityManager.getFieldLabel(field, fieldId)} is now required`
        );
      } else {
        changes.push(
          `${accessibilityManager.getFieldLabel(field, fieldId)} is no longer required`
        );
      }
    }

    // Update accessibility attributes
    accessibilityManager.updateFieldAccessibility(field, state, fieldId);
  }

  /**
   * Show loading indicator
   */
  public showLoading(): void {
    if (this.loadingIndicator) {
      this.loadingIndicator.style.display = 'block';
    }
  }

  /**
   * Hide loading indicator
   */
  public hideLoading(): void {
    if (this.loadingIndicator) {
      this.loadingIndicator.style.display = 'none';
      this.form.removeAttribute('aria-busy');
    }
  }

  /**
   * Show validation errors with accessibility announcements
   */
  public showValidationErrors(
    errors: string[],
    accessibilityManager: IConditionalAccessibility
  ): void {
    // Clear existing errors first
    this.hideError();

    if (errors.length === 0) {
      return;
    }

    // Create error container if it doesn't exist
    if (!this.errorContainer) {
      this.createErrorContainer();
    }

    if (this.errorContainer) {
      this.errorContainer.innerHTML = `
        <div class="campaignbridge-error-message" role="alert" aria-live="assertive">
          <strong>Error:</strong>
          <ul>
            ${errors.map(error => `<li>${this.escapeHtml(error)}</li>`).join('')}
          </ul>
        </div>
      `;

      this.errorContainer.style.display = 'block';

      // Announce errors to screen readers
      accessibilityManager.announceValidationErrors(errors);
    }
  }

  /**
   * Clear validation errors
   */
  public clearValidationErrors(
    accessibilityManager: IConditionalAccessibility
  ): void {
    if (this.errorContainer) {
      this.errorContainer.style.display = 'none';
      this.errorContainer.innerHTML = '';
    }

    accessibilityManager.clearValidationErrors();
  }

  /**
   * Escape HTML for safe display
   */
  private escapeHtml(text: string): string {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  /**
   * Show error message
   */
  public showError(message: string): void {
    if (this.errorContainer) {
      this.errorContainer.textContent = message;
      this.errorContainer.style.display = 'block';
    }

    // Create retry button if it doesn't exist
    if (!this.retryButton) {
      this.createRetryButton();
    }

    if (this.retryButton) {
      this.retryButton.style.display = 'inline-block';
    }
  }

  /**
   * Hide error message
   */
  public hideError(): void {
    if (this.errorContainer) {
      this.errorContainer.style.display = 'none';
    }

    if (this.retryButton) {
      this.retryButton.style.display = 'none';
    }
  }

  /**
   * Create loading indicator element
   */
  private createLoadingIndicator(): void {
    if (this.loadingIndicator) {
      return;
    }

    this.loadingIndicator = document.createElement('div');
    this.loadingIndicator.className = 'campaignbridge-conditional-loading';
    this.loadingIndicator.innerHTML = '<span class="spinner">Loading...</span>';
    this.loadingIndicator.style.display = 'none';

    this.form.appendChild(this.loadingIndicator);
  }

  /**
   * Create error container element
   */
  private createErrorContainer(): void {
    if (this.errorContainer) {
      return;
    }

    this.errorContainer = document.createElement('div');
    this.errorContainer.className =
      'campaignbridge-conditional-error notice notice-error';
    this.errorContainer.style.display = 'none';

    // Insert after form or at the end
    const formParent = this.form.parentNode;
    if (formParent) {
      formParent.insertBefore(this.errorContainer, this.form.nextSibling);
    }
  }

  /**
   * Create retry button for error recovery
   */
  private createRetryButton(): void {
    if (this.retryButton) {
      return;
    }

    this.retryButton = document.createElement('button') as HTMLButtonElement;
    this.retryButton.type = 'button';
    this.retryButton.className = 'button campaignbridge-conditional-retry';
    this.retryButton.textContent = 'Retry';
    this.retryButton.style.display = 'none';

    // Insert after error container
    if (this.errorContainer && this.errorContainer.parentNode) {
      this.errorContainer.parentNode.insertBefore(
        this.retryButton,
        this.errorContainer.nextSibling
      );
    }
  }

  /**
   * Add event listener with tracking for cleanup
   */
  private addEventListener(
    element: EventTarget,
    type: string,
    handler: EventListener
  ): void {
    element.addEventListener(type, handler);
    this.eventListeners.push({ element, type, handler });
  }

  /**
   * Set retry button callback
   */
  public setRetryCallback(callback: () => void): void {
    if (this.retryButton) {
      this.addEventListener(this.retryButton, 'click', callback);
    }
  }

  /**
   * Cleanup UI elements and event listeners
   */
  public destroy(): void {
    // Remove all tracked event listeners
    this.eventListeners.forEach(({ element, type, handler }) => {
      element.removeEventListener(type, handler);
    });
    this.eventListeners = [];

    // Remove DOM elements safely
    [this.loadingIndicator, this.errorContainer, this.retryButton].forEach(
      element => {
        if (element && element.parentNode) {
          try {
            element.parentNode.removeChild(element);
          } catch {
            // Element might have already been removed, ignore
          }
        }
      }
    );

    // Clear references
    this.loadingIndicator = null;
    this.errorContainer = null;
    this.retryButton = null;
  }
}
