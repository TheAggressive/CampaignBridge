/**
 * UI Manager
 *
 * Manages UI state transitions and visual feedback for encrypted fields
 *
 * @package CampaignBridge
 */

import type { AccessibilityManager } from './AccessibilityManager';
import type { FieldElements } from './types';
import { CLASSES } from './types';

/**
 * Manages UI states and transitions for encrypted fields
 */
export class UIManager {
  constructor(
    // eslint-disable-next-line no-unused-vars -- Parameter property automatically creates class property used via this.accessibility.
    private accessibility: AccessibilityManager
  ) {}

  /**
   * Helper method to hide an element using CSS classes
   */
  private hideElement(element: HTMLElement): void {
    // Remove any visibility classes
    element.classList.remove(
      CLASSES.CONTROLS_HIDDEN,
      CLASSES.DISPLAY_HIDDEN,
      CLASSES.REVEAL_BTN_HIDDEN,
      CLASSES.HIDE_BTN_VISIBLE,
      CLASSES.EDIT_VISIBLE,
      CLASSES.EDIT_CONTROLS_VISIBLE
    );
  }

  /**
   * Helper method to show an element using CSS classes
   */
  private showElement(element: HTMLElement, visibilityClass: string): void {
    element.classList.add(visibilityClass);
  }

  /**
   * Switch field to edit mode with UI updates
   */
  switchToEditMode(elements: FieldElements): void {
    // Update visibility using CSS classes
    if (elements.displayInput) {
      elements.displayInput.classList.add(CLASSES.DISPLAY_HIDDEN);
      this.accessibility.updateAriaForMasked(elements);
    }
    if (elements.controls)
      elements.controls.classList.add(CLASSES.CONTROLS_HIDDEN);

    if (elements.editInput) {
      this.showElement(elements.editInput, CLASSES.EDIT_VISIBLE);
      // Ensure edit input is editable
      elements.editInput.removeAttribute('readonly');
      elements.editInput.removeAttribute('disabled');
    }
    if (elements.editControls)
      this.showElement(elements.editControls, CLASSES.EDIT_CONTROLS_VISIBLE);

    // Update accessibility
    this.accessibility.updateAriaForEdit(elements);

    // Focus management
    this.accessibility.manageFocus(elements, 'edit');
  }

  /**
   * Switch field back to display mode
   */
  switchToDisplayMode(elements: FieldElements): void {
    if (elements.editInput) this.hideElement(elements.editInput);
    if (elements.editControls) this.hideElement(elements.editControls);
    if (elements.displayInput) {
      // Ensure display input is visible (remove any hidden classes)
      this.hideElement(elements.displayInput); // This removes visibility classes
    }
    if (elements.controls) {
      elements.controls.classList.remove(CLASSES.CONTROLS_HIDDEN);
    }
  }

  /**
   * Cancel edit and return to secure display mode
   */
  cancelEditMode(elements: FieldElements, originalValue?: string): void {
    // Clear edit input
    if (elements.editInput) {
      elements.editInput.value = '';
      this.hideElement(elements.editInput);
    }
    if (elements.editControls) this.hideElement(elements.editControls);

    // Restore display value
    if (elements.displayInput && originalValue) {
      elements.displayInput.value = originalValue;
      // Remove hidden class to show display input again
      elements.displayInput.classList.remove(CLASSES.DISPLAY_HIDDEN);
    }

    // Reset button states - restore masked state (reveal button visible)
    if (elements.controls) {
      elements.controls.classList.remove(CLASSES.CONTROLS_HIDDEN);
    }
    if (elements.revealBtn) {
      // Remove hidden class to show reveal button
      elements.revealBtn.classList.remove(CLASSES.REVEAL_BTN_HIDDEN);
    }
    if (elements.hideBtn) this.hideElement(elements.hideBtn);
    if (elements.editBtn) {
      // Edit button is visible by default, no special class needed
    }

    // Update accessibility for masked state
    this.accessibility.updateAriaForMasked(elements);
  }

  /**
   * Update field after successful save
   */
  updateFieldAfterSave(
    elements: FieldElements,
    data: { encrypted: string; masked: string }
  ): void {
    // Update hidden input with new encrypted value
    if (elements.hiddenInput) {
      elements.hiddenInput.value = data.encrypted;
    }

    // Update display input with new masked value
    if (elements.displayInput) {
      elements.displayInput.value = data.masked;
    }
  }

  /**
   * Revert field back to masked state
   */
  revertToMasked(
    field: HTMLElement,
    revealBtn: HTMLButtonElement | null,
    hideBtn: HTMLButtonElement | null,
    editBtn: HTMLButtonElement | null,
    displayInput: HTMLInputElement | null,
    originalValue?: string
  ): void {
    // Update the input value back to masked state
    if (displayInput && originalValue) {
      displayInput.value = originalValue;
    }

    // Reset ARIA attributes
    if (displayInput) {
      displayInput.setAttribute('aria-label', 'Masked encrypted value');
    }

    // Update button states
    if (revealBtn) {
      // Ensure reveal button is visible by removing hidden class
      revealBtn.classList.remove(CLASSES.REVEAL_BTN_HIDDEN);
    }
    if (hideBtn) this.hideElement(hideBtn);
    if (editBtn) this.hideElement(editBtn);

    // Update accessibility
    const elements = {
      field,
      revealBtn,
      editBtn,
      displayInput,
    } as FieldElements;
    this.accessibility.updateAriaForMasked(elements);
  }

  /**
   * Set button loading state
   */
  setButtonLoading(
    button: HTMLButtonElement,
    isLoading: boolean,
    loadingText?: string
  ): void {
    if (isLoading) {
      // Store original content
      button.dataset.originalHtml = button.innerHTML;
      button.disabled = true;

      // Set loading content based on button type
      if (button.classList.contains(CLASSES.REVEAL_BTN)) {
        button.innerHTML =
          '<span class="dashicons dashicons-update spin"></span> ' +
          (loadingText || campaignbridgeAdmin.i18n?.loading || 'Loading...');
      } else if (button.classList.contains(CLASSES.SAVE_BTN)) {
        button.textContent =
          loadingText || campaignbridgeAdmin.i18n?.saving || 'Saving...';
      }
    } else {
      // Restore original content
      button.disabled = false;
      if (button.dataset.originalHtml) {
        button.innerHTML = button.dataset.originalHtml;
        delete button.dataset.originalHtml;
      }
    }
  }

  /**
   * Show success state briefly
   */
  showSuccessFeedback(button: HTMLButtonElement): void {
    const originalContent = button.innerHTML;
    button.innerHTML = '<span class="dashicons dashicons-yes"></span> Saved';
    button.classList.add('button-success');

    setTimeout(() => {
      button.innerHTML = originalContent;
      button.classList.remove('button-success');
    }, 2000);
  }

  /**
   * Update button states after successful reveal
   */
  updateButtonsAfterReveal(elements: FieldElements): void {
    if (elements.revealBtn) {
      // Reveal button should be hidden when values are revealed
      elements.revealBtn.classList.add(CLASSES.REVEAL_BTN_HIDDEN);
    }
    if (elements.hideBtn) {
      this.showElement(elements.hideBtn, CLASSES.HIDE_BTN_VISIBLE);
    }
    if (elements.editBtn) {
      // Edit button is visible by default, no special class needed
    }

    this.accessibility.updateAriaForRevealed(elements);
  }

  /**
   * Reset field to initial secure state
   */
  resetToSecureState(elements: FieldElements, originalValue?: string): void {
    // Reset display
    if (elements.displayInput && originalValue) {
      elements.displayInput.value = originalValue;
      // Ensure display input is visible by removing hidden class
      elements.displayInput.classList.remove(CLASSES.DISPLAY_HIDDEN);
    }

    // Reset edit mode
    if (elements.editInput) {
      this.hideElement(elements.editInput);
      elements.editInput.value = '';
    }
    if (elements.editControls) this.hideElement(elements.editControls);

    // Reset buttons
    if (elements.controls) {
      elements.controls.classList.remove(CLASSES.CONTROLS_HIDDEN);
    }
    if (elements.revealBtn) {
      // Remove hidden class to show reveal button again
      elements.revealBtn.classList.remove(CLASSES.REVEAL_BTN_HIDDEN);
    }
    if (elements.editBtn) this.hideElement(elements.editBtn);

    // Reset accessibility
    this.accessibility.updateAriaForMasked(elements);
  }
}
