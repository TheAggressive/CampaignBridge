/**
 * Encrypted Fields Handler - Main Orchestrator
 *
 * Main class that orchestrates all encrypted field functionality
 *
 * @package CampaignBridge
 */

import { AccessibilityManager } from './AccessibilityManager';
import { ApiClient } from './ApiClient';
import { EventHandler } from './EventHandler';
import { FieldElementsManager } from './FieldElements';
import { StateManager } from './StateManager';
import type { FieldElements, HandlerConfig } from './types';
import { CLASSES } from './types';
import { UIManager } from './UIManager';
import { ValidationManager } from './ValidationManager';

/**
 * Main handler for encrypted fields functionality
 */
export class EncryptedFieldsHandler {
  private config: HandlerConfig;
  private fieldElements: FieldElementsManager;
  private stateManager: StateManager;
  private accessibility: AccessibilityManager;
  private validation: ValidationManager;
  private uiManager: UIManager;
  private eventHandler: EventHandler;
  private apiClient: ApiClient;

  constructor() {
    // Configuration
    this.config = {
      REVEAL_TIMEOUT_MS: campaignbridgeAdmin?.security?.revealTimeout || 10000,
      MAX_VALUE_LENGTH: 1000,
      MAX_RETRIES: campaignbridgeAdmin?.security?.maxRetries || 1,
      REQUEST_TIMEOUT_MS:
        campaignbridgeAdmin?.security?.requestTimeout || 30000,
    };

    // Initialize managers
    this.fieldElements = new FieldElementsManager();
    this.stateManager = new StateManager();
    this.accessibility = new AccessibilityManager();
    this.validation = new ValidationManager();
    this.uiManager = new UIManager(this.accessibility);
    this.apiClient = new ApiClient(
      this.stateManager,
      this.validation,
      this.accessibility
    );

    // Initialize event handler with bound methods
    this.eventHandler = new EventHandler(
      this.fieldElements,
      this.stateManager,
      this.accessibility,
      this.uiManager,
      {
        handleReveal: this.handleReveal.bind(this),
        handleHide: this.handleHide.bind(this),
        handleEdit: this.handleEdit.bind(this),
        handleSave: this.handleSave.bind(this),
        handleCancel: this.handleCancel.bind(this),
        handleFormSubmit: this.handleFormSubmit.bind(this),
      }
    );

    this.init();
  }

  /**
   * Initialize the handler
   */
  private init(): void {
    this.accessibility.initialize();
    this.initializeFields();
    this.eventHandler.bindEvents();
  }

  /**
   * Initialize encrypted fields on page load
   */
  private initializeFields(): void {
    const displayInputs = this.fieldElements.getAllDisplayInputs();
    this.stateManager.initializeFromFields(displayInputs);

    // Set up accessibility for each field
    displayInputs.forEach(input => {
      const field = input.closest(`.${CLASSES.FIELD}`) as HTMLElement;
      if (field) {
        this.fieldElements.setupFieldAccessibility(
          field,
          field.dataset.fieldId
        );
      }
    });
  }

  /**
   * Handle reveal button click
   */
  private async handleReveal(button: HTMLButtonElement): Promise<void> {
    if (!button) return;

    const field = button.closest(`.${CLASSES.FIELD}`) as HTMLElement;
    if (!field) return;

    const elements = this.fieldElements.getFieldElements(field);
    if (!elements.displayInput || !elements.hiddenInput) {
      this.apiClient.showError('Invalid field configuration');
      return;
    }

    const encryptedValue = elements.hiddenInput.value;
    const fieldId = field.dataset.fieldId || '';

    // Security validation
    if (!encryptedValue || !fieldId) {
      this.apiClient.showError('Invalid field configuration');
      return;
    }

    // Clear any existing timeout for this field
    this.stateManager.clearTimeout(fieldId);

    // Show loading state
    this.uiManager.setButtonLoading(button, true);

    try {
      const response = await this.apiClient.decryptField(
        fieldId,
        encryptedValue
      );

      if (response.success && response.data?.decrypted) {
        this.handleSuccessfulDecryption(
          elements,
          fieldId,
          encryptedValue,
          response.data.decrypted
        );
      } else {
        throw new Error(
          (response.data as { message?: string })?.message ||
            'Failed to decrypt field'
        );
      }
    } catch (error) {
      this.logError('Decryption failed', { error: error.message, fieldId });
      this.apiClient.showError(
        (error as Error).message || 'Network error occurred'
      );
    } finally {
      this.uiManager.setButtonLoading(button, false);
    }
  }

  /**
   * Handle successful decryption
   */
  private handleSuccessfulDecryption(
    elements: FieldElements,
    fieldId: string,
    encryptedValue: string,
    decryptedValue: string
  ): void {
    // Store the decrypted value for canceling edit operations
    this.stateManager.storeRevealedValue(fieldId, decryptedValue);

    // Update display - replace readonly input with span temporarily
    if (elements.displayInput) {
      // Temporarily remove readonly to allow value change
      elements.displayInput.removeAttribute('readonly');

      // Set the decrypted value
      elements.displayInput.value = decryptedValue;
      elements.displayInput.setAttribute('readonly', 'readonly');

      // Update ARIA attributes for revealed state
      elements.displayInput.setAttribute(
        'aria-label',
        'Revealed sensitive value - will auto-hide for security'
      );

      // Force a re-render by triggering input event
      const inputEvent = new Event('input', { bubbles: true });
      elements.displayInput.dispatchEvent(inputEvent);
    }

    // Update UI and accessibility
    this.uiManager.updateButtonsAfterReveal(elements);
    this.accessibility.announceToScreenReader(
      'Sensitive value revealed. Press Escape to hide immediately, or use Edit button.'
    );

    // Auto-hide after timeout for security
    const timeoutId = setTimeout(() => {
      const originalValue = this.stateManager.getOriginalValue(fieldId);
      this.uiManager.revertToMasked(
        elements.field,
        elements.revealBtn,
        elements.hideBtn,
        elements.editBtn,
        elements.displayInput,
        originalValue
      );
      this.stateManager.clearTimeout(fieldId);
      this.stateManager.clearRevealedValue(fieldId);
      this.accessibility.announceToScreenReader(
        'Sensitive data automatically hidden for security'
      );
    }, this.config.REVEAL_TIMEOUT_MS);

    this.stateManager.storeTimeout(fieldId, timeoutId);
  }

  /**
   * Handle hide button click - immediately hide revealed values
   */
  private handleHide(button: HTMLButtonElement): void {
    if (!button) return;

    const field = button.closest(`.${CLASSES.FIELD}`) as HTMLElement;
    if (!field) return;

    const elements = this.fieldElements.getFieldElements(field);
    const fieldId = field.dataset.fieldId || '';

    // Clear any pending reveal timeout
    this.stateManager.clearTimeout(fieldId);

    // Immediately revert to masked state
    const originalValue = this.stateManager.getOriginalValue(fieldId);
    this.uiManager.revertToMasked(
      field,
      elements.revealBtn,
      elements.hideBtn,
      elements.editBtn,
      elements.displayInput,
      originalValue
    );

    // Clear revealed value for security
    this.stateManager.clearRevealedValue(fieldId);

    // Announce state change
    this.accessibility.announceToScreenReader(
      'Sensitive data hidden for security'
    );
  }

  /**
   * Handle edit button click
   */
  private handleEdit(button: HTMLButtonElement): void {
    if (!button) return;

    const field = button.closest(`.${CLASSES.FIELD}`) as HTMLElement;
    if (!field) return;

    const elements = this.fieldElements.getFieldElements(field);
    if (!elements.editInput) return;

    const fieldId = field.dataset.fieldId;

    // Clear any pending reveal timeout
    if (fieldId) {
      this.stateManager.clearTimeout(fieldId);
    }

    // Update accessibility
    this.accessibility.announceToScreenReader(
      'Edit mode activated. Use Save to confirm changes or Cancel to discard.'
    );

    // Switch to edit mode
    this.uiManager.switchToEditMode(elements);
  }

  /**
   * Handle save button click
   */
  private async handleSave(button: HTMLButtonElement): Promise<void> {
    if (!button) return;

    const field = button.closest(`.${CLASSES.FIELD}`) as HTMLElement;
    if (!field) return;

    const elements = this.fieldElements.getFieldElements(field);
    if (!elements.editInput || !elements.hiddenInput) return;

    const newValue = elements.editInput.value.trim();
    const fieldId = field.dataset.fieldId;

    // Validate input
    const validation = this.validation.validateForSave(
      newValue,
      elements.editInput
    );
    if (!validation.isValid) {
      if (validation.error) {
        this.apiClient.showError(validation.error);
        this.validation.setValidationError(
          elements.editInput,
          validation.error
        );
      }
      return;
    }

    // Clear validation errors
    this.validation.clearValidation(elements.editInput);

    // Show loading state
    this.uiManager.setButtonLoading(button, true);

    try {
      const response = await this.apiClient.encryptField(
        fieldId || '',
        newValue
      );

      if (
        response.success &&
        response.data?.encrypted &&
        response.data?.masked
      ) {
        this.uiManager.updateFieldAfterSave(elements, response.data);
        this.uiManager.switchToDisplayMode(elements);
        // After saving, restore to revealed state (user had revealed data to edit)
        this.uiManager.updateButtonsAfterReveal(elements);
        this.uiManager.showSuccessFeedback(button);
        this.accessibility.announceToScreenReader('Changes saved successfully');
      } else {
        throw new Error(
          (response.data as { message?: string })?.message ||
            'Failed to save field'
        );
      }
    } catch (error) {
      this.logError('Save failed', { error: error.message, fieldId });
      this.apiClient.showError(
        (error as Error).message || 'Network error occurred'
      );
    } finally {
      this.uiManager.setButtonLoading(button, false);
    }
  }

  /**
   * Handle cancel button click
   */
  private handleCancel(button: HTMLButtonElement): void {
    if (!button) return;

    const field = button.closest(`.${CLASSES.FIELD}`) as HTMLElement;
    if (!field) return;

    const elements = this.fieldElements.getFieldElements(field);
    const fieldId = field.dataset.fieldId;

    // Clear any pending reveal timeout
    if (fieldId) {
      this.stateManager.clearTimeout(fieldId);
    }

    // Return to secure masked state
    this.uiManager.cancelEditMode(
      elements,
      this.stateManager.getOriginalValue(fieldId)
    );

    // Clear revealed value for security
    if (fieldId) {
      this.stateManager.clearRevealedValue(fieldId);
    }

    // Announce state change
    this.accessibility.announceToScreenReader(
      'Sensitive data hidden for security'
    );
  }

  /**
   * Handle form submission
   */
  private handleFormSubmit(): void {
    // Clear all pending timeouts and state for security
    this.stateManager.clearAll();
    this.accessibility.announceToScreenReader(
      'Form submitted - all sensitive data cleared'
    );
  }

  /**
   * Get current state summary (for debugging)
   */
  getStateSummary() {
    return this.stateManager.getStateSummary();
  }

  /**
   * Log error with context
   */
  private logError(message: string, context: any = {}): void {
    const errorData = {
      message,
      context,
      timestamp: new Date().toISOString(),
      component: 'EncryptedFieldsHandler',
    };

    // Use console.error for client-side logging (could be enhanced to send to server)
    console.error('[EncryptedFields]', errorData);
  }

  /**
   * Cleanup method
   */
  destroy(): void {
    this.eventHandler.unbindEvents();
    this.stateManager.clearAll();
    this.accessibility.cleanup();
  }
}
