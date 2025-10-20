/**
 * Accessibility Manager
 *
 * Handles all accessibility features including ARIA attributes, screen reader support, and keyboard navigation
 *
 * @package CampaignBridge
 */

import type { FieldElements } from './types';
import { CLASSES } from './types';

/**
 * Manages accessibility features for encrypted fields
 */
export class AccessibilityManager {
  private liveRegion: HTMLElement | null = null;

  /**
   * Initialize accessibility infrastructure
   */
  initialize(): void {
    this.createLiveRegion();
  }

  /**
   * Create or get the live region for screen reader announcements
   */
  private createLiveRegion(): void {
    if (!this.liveRegion) {
      this.liveRegion = document.getElementById(
        'encrypted-fields-live-region'
      ) as HTMLElement;

      if (!this.liveRegion) {
        this.liveRegion = document.createElement('div');
        this.liveRegion.id = 'encrypted-fields-live-region';
        this.liveRegion.setAttribute('aria-live', 'polite');
        this.liveRegion.setAttribute('aria-atomic', 'true');
        this.liveRegion.style.position = 'absolute';
        this.liveRegion.style.left = '-10000px';
        this.liveRegion.style.width = '1px';
        this.liveRegion.style.height = '1px';
        this.liveRegion.style.overflow = 'hidden';
        document.body.appendChild(this.liveRegion);
      }
    }
  }

  /**
   * Announce message to screen readers
   */
  announceToScreenReader(message: string): void {
    if (this.liveRegion) {
      this.liveRegion.textContent = message;
    }
  }

  /**
   * Update ARIA attributes for revealed state
   */
  updateAriaForRevealed(elements: FieldElements): void {
    elements.field.setAttribute(
      'aria-label',
      'Encrypted field - sensitive value revealed'
    );

    if (elements.displayInput) {
      elements.displayInput.setAttribute(
        'aria-label',
        'Revealed sensitive value - will auto-hide for security'
      );
    }

    if (elements.revealBtn) {
      elements.revealBtn.setAttribute('aria-expanded', 'true');
    }

    if (elements.editBtn) {
      elements.editBtn.setAttribute('aria-expanded', 'true');
      elements.editBtn.setAttribute('aria-label', 'Edit revealed value');
    }
  }

  /**
   * Update ARIA attributes for edit mode
   */
  updateAriaForEdit(elements: FieldElements): void {
    elements.field.setAttribute('aria-label', 'Encrypted field - editing mode');

    if (elements.editInput) {
      elements.editInput.setAttribute(
        'aria-label',
        'Edit encrypted value - enter new value'
      );
      elements.editInput.setAttribute('aria-required', 'true');
      elements.editInput.removeAttribute('aria-hidden');

      // Set up validation error container
      const fieldId = elements.field.dataset.fieldId;
      if (fieldId) {
        const errorId = `error-${fieldId}`;
        elements.editInput.setAttribute('aria-describedby', errorId);
      }
    }

    if (elements.editControls) {
      elements.editControls.setAttribute('aria-label', 'Edit controls');
    }
  }

  /**
   * Update ARIA attributes for masked state
   */
  updateAriaForMasked(elements: FieldElements): void {
    elements.field.setAttribute('aria-label', 'Encrypted field - value hidden');
    elements.field.removeAttribute('aria-describedby');

    if (elements.displayInput) {
      elements.displayInput.setAttribute(
        'aria-label',
        'Masked encrypted value'
      );
      elements.displayInput.removeAttribute('aria-hidden');
    }

    if (elements.revealBtn) {
      elements.revealBtn.setAttribute('aria-expanded', 'false');
      elements.revealBtn.setAttribute('aria-label', 'Reveal hidden value');
    }

    if (elements.editBtn) {
      elements.editBtn.setAttribute('aria-expanded', 'false');
    }
  }

  /**
   * Set validation error on input
   */
  setValidationError(inputElement: HTMLInputElement): void {
    inputElement.setAttribute('aria-invalid', 'true');
  }

  /**
   * Clear validation error from input
   */
  clearValidationError(inputElement: HTMLInputElement): void {
    inputElement.removeAttribute('aria-invalid');
    inputElement.removeAttribute('aria-describedby');
  }

  /**
   * Handle keyboard navigation for accessibility
   */
  handleKeyboardNavigation(
    event: KeyboardEvent,
    elements: FieldElements
  ): boolean {
    // Handle Escape key for canceling operations
    if (event.key === 'Escape') {
      // If in edit mode, cancel edit
      if (
        elements.editInput &&
        elements.editInput.classList.contains(CLASSES.EDIT_VISIBLE)
      ) {
        event.preventDefault();
        return true; // Signal that escape was handled
      }

      // If data is revealed, hide it immediately
      if (elements.displayInput) {
        // This will be handled by the caller
        event.preventDefault();
        return true;
      }
    }

    // Handle Enter/Space on buttons
    const target = event.target as HTMLElement;
    if (
      (event.key === 'Enter' || event.key === ' ') &&
      target.matches('button')
    ) {
      event.preventDefault();
      (target as HTMLButtonElement).click();
      return true;
    }

    return false;
  }

  /**
   * Manage focus for accessibility
   */
  manageFocus(
    elements: FieldElements,
    focusTarget: 'edit' | 'reveal' | 'none' = 'none'
  ): void {
    // Remove focus from all elements first
    if (elements.saveBtn) elements.saveBtn.blur();
    if (elements.cancelBtn) elements.cancelBtn.blur();

    switch (focusTarget) {
      case 'edit':
        setTimeout(() => {
          elements.editInput?.focus();
          this.announceToScreenReader('Edit input focused - type your changes');
        }, 10);
        break;
      case 'reveal':
        elements.revealBtn?.focus();
        break;
    }
  }

  /**
   * Clean up accessibility elements
   */
  cleanup(): void {
    if (this.liveRegion && this.liveRegion.parentNode) {
      this.liveRegion.parentNode.removeChild(this.liveRegion);
      this.liveRegion = null;
    }
  }
}
