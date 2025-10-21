/**
 * Event Handler
 *
 * Manages event handling for encrypted fields with proper delegation and accessibility
 *
 * @package CampaignBridge
 */

import type { AccessibilityManager } from './AccessibilityManager';
import type { FieldElementsManager } from './FieldElements';
import type { StateManager } from './StateManager';
import type { EventHandlerMap } from './types';
import { CLASSES } from './types';
import type { UIManager } from './UIManager';

/**
 * Handles events for encrypted fields
 */
export class EventHandler {
  private eventHandlers: EventHandlerMap;

  constructor(
    private fieldElements: FieldElementsManager,
    private stateManager: StateManager,
    private accessibility: AccessibilityManager,
    private uiManager: UIManager,
    private handlers: {
      handleReveal: (button: HTMLButtonElement) => Promise<void>;
      handleHide: (button: HTMLButtonElement) => void;
      handleEdit: (button: HTMLButtonElement) => void;
      handleSave: (button: HTMLButtonElement) => Promise<void>;
      handleCancel: (button: HTMLButtonElement) => void;
      handleFormSubmit: () => void;
    }
  ) {
    this.eventHandlers = {
      [CLASSES.REVEAL_BTN]: this.handlers.handleReveal.bind(this.handlers),
      [CLASSES.HIDE_BTN]: this.handlers.handleHide.bind(this.handlers),
      [CLASSES.EDIT_BTN]: this.handlers.handleEdit.bind(this.handlers),
      [CLASSES.SAVE_BTN]: this.handlers.handleSave.bind(this.handlers),
      [CLASSES.CANCEL_BTN]: this.handlers.handleCancel.bind(this.handlers),
    };
  }

  /**
   * Bind all event handlers
   */
  bindEvents(): void {
    // Use event delegation for better performance
    document.addEventListener('click', this.handleClick.bind(this), true);
    document.addEventListener('keydown', this.handleKeyDown.bind(this), true);
    document.addEventListener(
      'submit',
      this.handlers.handleFormSubmit.bind(this.handlers)
    );
  }

  /**
   * Centralized click handler with security checks
   */
  private handleClick(event: Event): void {
    const target = event.target as HTMLElement;

    // Security: Only handle events from our trusted elements
    if (!target || !target.matches) {
      return;
    }

    // Find the matching action and execute it
    for (const [className, handler] of Object.entries(this.eventHandlers)) {
      const element = target.closest(`.${className}`) as HTMLElement;
      if (element) {
        event.preventDefault();
        handler(element);
        return;
      }
    }
  }

  /**
   * Handle keyboard navigation for accessibility
   */
  private handleKeyDown(event: KeyboardEvent): void {
    const target = event.target as HTMLElement;

    // Handle Escape key for canceling operations
    if (event.key === 'Escape') {
      const field = target.closest(`.${CLASSES.FIELD}`) as HTMLElement;
      if (field) {
        const elements = this.fieldElements.getFieldElements(field);

        // If in edit mode, cancel edit
        if (
          elements.editInput &&
          elements.editInput.classList.contains(CLASSES.EDIT_VISIBLE)
        ) {
          event.preventDefault();
          this.handlers.handleCancel(elements.cancelBtn || elements.saveBtn);
          return;
        }

        // If data is revealed, hide it immediately
        if (elements.displayInput) {
          const fieldId = field.dataset.fieldId;
          if (fieldId && this.stateManager.hasRevealedValue(fieldId)) {
            event.preventDefault();
            this.uiManager.revertToMasked(
              field,
              elements.revealBtn,
              elements.hideBtn,
              elements.editBtn,
              elements.displayInput,
              this.stateManager.getOriginalValue(fieldId)
            );
            this.stateManager.clearTimeout(fieldId);
            this.stateManager.clearRevealedValue(fieldId);
            this.accessibility.announceToScreenReader(
              'Sensitive data hidden immediately'
            );
            return;
          }
        }
      }
    }

    // Handle Enter/Space on buttons
    if (
      (event.key === 'Enter' || event.key === ' ') &&
      target.matches(
        `.${CLASSES.REVEAL_BTN}, .${CLASSES.EDIT_BTN}, .${CLASSES.SAVE_BTN}, .${CLASSES.CANCEL_BTN}`
      )
    ) {
      event.preventDefault();
      (target as HTMLButtonElement).click();
    }
  }

  /**
   * Unbind all event handlers
   */
  unbindEvents(): void {
    document.removeEventListener('click', this.handleClick.bind(this), true);
    document.removeEventListener(
      'keydown',
      this.handleKeyDown.bind(this),
      true
    );
    document.removeEventListener(
      'submit',
      this.handlers.handleFormSubmit.bind(this.handlers)
    );
  }
}
