/**
 * Field Elements Manager
 *
 * Handles DOM element selection and management for encrypted fields
 *
 * @package CampaignBridge
 */

import type { FieldElements } from './types';
import { CLASSES } from './types';

/**
 * Manages DOM element interactions for encrypted fields
 */
export class FieldElementsManager {
  /**
   * Get all DOM elements for a field
   */
  getFieldElements(field: HTMLElement): FieldElements {
    return {
      field,
      displayInput: field.querySelector<HTMLInputElement>(
        `.${CLASSES.DISPLAY}`
      ),
      editInput: field.querySelector<HTMLInputElement>(`.${CLASSES.EDIT}`),
      hiddenInput: field.querySelector<HTMLInputElement>(
        'input[type="hidden"]'
      ),
      controls: field.querySelector<HTMLElement>(`.${CLASSES.CONTROLS}`),
      editControls: field.querySelector<HTMLElement>(
        `.${CLASSES.EDIT_CONTROLS}`
      ),
      revealBtn: field.querySelector<HTMLButtonElement>(
        `.${CLASSES.REVEAL_BTN}`
      ),
      hideBtn: field.querySelector<HTMLButtonElement>(`.${CLASSES.HIDE_BTN}`),
      editBtn: field.querySelector<HTMLButtonElement>(`.${CLASSES.EDIT_BTN}`),
      saveBtn: field.querySelector<HTMLButtonElement>(`.${CLASSES.SAVE_BTN}`),
      cancelBtn: field.querySelector<HTMLButtonElement>(
        `.${CLASSES.CANCEL_BTN}`
      ),
    };
  }

  /**
   * Find all encrypted field containers on the page
   */
  getAllEncryptedFields(): NodeListOf<HTMLElement> {
    return document.querySelectorAll<HTMLElement>(`.${CLASSES.FIELD}`);
  }

  /**
   * Find all display inputs for initialization
   */
  getAllDisplayInputs(): NodeListOf<HTMLInputElement> {
    return document.querySelectorAll<HTMLInputElement>(`.${CLASSES.DISPLAY}`);
  }

  /**
   * Set up initial accessibility attributes for a field
   */
  setupFieldAccessibility(field: HTMLElement, fieldId?: string): void {
    // Set field attributes
    field.setAttribute('aria-label', 'Encrypted field - value hidden');

    // Set display input attributes
    const displayInput = field.querySelector<HTMLInputElement>(
      `.${CLASSES.DISPLAY}`
    );
    if (displayInput) {
      displayInput.setAttribute('aria-label', 'Masked encrypted value');
      displayInput.setAttribute('readonly', 'readonly');
    }

    // Set up reveal button accessibility
    const revealBtn = field.querySelector<HTMLButtonElement>(
      `.${CLASSES.REVEAL_BTN}`
    );
    if (revealBtn) {
      revealBtn.setAttribute('aria-expanded', 'false');
      revealBtn.setAttribute('aria-label', 'Reveal hidden value');
      if (fieldId) {
        revealBtn.setAttribute('aria-controls', fieldId);
      }
    }

    // Set up edit button accessibility (initially hidden)
    const editBtn = field.querySelector<HTMLButtonElement>(
      `.${CLASSES.EDIT_BTN}`
    );
    if (editBtn) {
      editBtn.setAttribute('aria-expanded', 'false');
      editBtn.setAttribute('aria-label', 'Edit encrypted value (reveal first)');
    }
  }

  /**
   * Update field accessibility for revealed state
   */
  updateFieldForRevealed(
    field: HTMLElement,
    displayInput?: HTMLInputElement
  ): void {
    field.setAttribute(
      'aria-label',
      'Encrypted field - sensitive value revealed'
    );
    if (displayInput) {
      displayInput.setAttribute(
        'aria-label',
        'Revealed sensitive value - will auto-hide for security'
      );
    }
  }

  /**
   * Update field accessibility for edit mode
   */
  updateFieldForEdit(field: HTMLElement, editInput?: HTMLInputElement): void {
    field.setAttribute('aria-label', 'Encrypted field - editing mode');

    if (editInput) {
      editInput.setAttribute(
        'aria-label',
        'Edit encrypted value - enter new value'
      );
      editInput.setAttribute('aria-required', 'true');
      editInput.removeAttribute('aria-hidden');
    }
  }

  /**
   * Reset field accessibility to initial state
   */
  resetFieldAccessibility(
    field: HTMLElement,
    displayInput?: HTMLInputElement
  ): void {
    field.setAttribute('aria-label', 'Encrypted field - value hidden');
    field.removeAttribute('aria-describedby');

    if (displayInput) {
      displayInput.setAttribute('aria-label', 'Masked encrypted value');
      displayInput.removeAttribute('aria-hidden');
    }
  }
}
