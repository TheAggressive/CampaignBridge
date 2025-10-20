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
  constructor(private accessibility: AccessibilityManager) {}

  /**
   * Switch field to edit mode with UI updates
   */
  switchToEditMode(elements: FieldElements): void {
    // Update visibility
    if (elements.displayInput) {
      elements.displayInput.style.display = 'none';
      this.accessibility.updateAriaForMasked(elements);
    }
    if (elements.controls) elements.controls.style.display = 'none';

    if (elements.editInput) {
      elements.editInput.style.display = 'block';
      // Ensure edit input is editable
      elements.editInput.removeAttribute('readonly');
      elements.editInput.removeAttribute('disabled');
    }
    if (elements.editControls) elements.editControls.style.display = 'block';

    // Update accessibility
    this.accessibility.updateAriaForEdit(elements);

    // Focus management
    this.accessibility.manageFocus(elements, 'edit');
  }

  /**
   * Switch field back to display mode
   */
  switchToDisplayMode(elements: FieldElements): void {
    if (elements.editInput) {
      elements.editInput.style.display = 'none';
    }
    if (elements.editControls) {
      elements.editControls.style.display = 'none';
    }
    if (elements.displayInput) {
      elements.displayInput.style.display = 'block';
    }
    if (elements.controls) {
      elements.controls.style.display = 'block';
    }
  }

  /**
   * Cancel edit and return to secure display mode
   */
  cancelEditMode(elements: FieldElements, originalValue?: string): void {
    // Clear edit input
    if (elements.editInput) {
      elements.editInput.value = '';
      elements.editInput.style.display = 'none';
    }
    if (elements.editControls) {
      elements.editControls.style.display = 'none';
    }

    // Restore display value
    if (elements.displayInput && originalValue) {
      elements.displayInput.value = originalValue;
      elements.displayInput.style.display = 'block';
    }

    // Reset button states - only show reveal button for masked state
    if (elements.controls) elements.controls.style.display = 'block';
    if (elements.revealBtn) {
      elements.revealBtn.style.display = 'inline-block';
      this.accessibility.manageFocus(elements, 'reveal');
    }
    if (elements.hideBtn) {
      elements.hideBtn.style.display = 'none';
    }
    if (elements.editBtn) {
      elements.editBtn.style.display = 'none';
    }

    // Update accessibility
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
      revealBtn.style.display = 'inline-block';
    }
    if (hideBtn) {
      hideBtn.style.display = 'none';
    }
    if (editBtn) {
      editBtn.style.display = 'none';
    }

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
      elements.revealBtn.style.display = 'none';
    }
    if (elements.hideBtn) {
      elements.hideBtn.style.display = 'inline-block';
    }
    if (elements.editBtn) {
      elements.editBtn.style.display = 'inline-block';
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
      elements.displayInput.style.display = 'block';
    }

    // Reset edit mode
    if (elements.editInput) {
      elements.editInput.style.display = 'none';
      elements.editInput.value = '';
    }
    if (elements.editControls) {
      elements.editControls.style.display = 'none';
    }

    // Reset buttons
    if (elements.controls) elements.controls.style.display = 'block';
    if (elements.revealBtn) elements.revealBtn.style.display = 'inline-block';
    if (elements.editBtn) elements.editBtn.style.display = 'none';

    // Reset accessibility
    this.accessibility.updateAriaForMasked(elements);
  }
}
