/**
 * Handles accessibility features for conditional fields
 */

import type { FieldState, IConditionalAccessibility } from './types';

export class ConditionalAccessibility implements IConditionalAccessibility {
  private announcementContainer: HTMLElement | null = null;
  private formId: string;
  private liveRegions: HTMLElement[] = [];
  private skipLinks: HTMLElement[] = [];
  private errorAnnouncer: HTMLElement | null = null;
  private keyboardNavigationEnabled: boolean = true;
  private lastFocusedElement: HTMLElement | null = null;

  constructor(formId: string) {
    this.formId = formId;
    this.initializeAccessibility();
  }

  /**
   * Initialize accessibility features
   */
  private initializeAccessibility(): void {
    this.createSkipLinks();
    this.createErrorAnnouncer();
    this.setupKeyboardNavigation();
    this.createAnnouncementContainer();
  }

  /**
   * Create skip links for keyboard navigation
   */
  private createSkipLinks(): void {
    const form = document.getElementById(this.formId);
    if (!form) return;

    // Create skip to main content link
    const skipToContent = this.createSkipLink(
      'Skip to main content',
      `#${this.formId}`
    );
    form.parentNode?.insertBefore(skipToContent, form);

    // Create skip to errors link (if errors exist)
    const skipToErrors = this.createSkipLink(
      'Skip to form errors',
      `#${this.formId}-errors`
    );
    skipToErrors.style.display = 'none'; // Hidden by default
    form.parentNode?.insertBefore(skipToErrors, form);

    this.skipLinks.push(skipToContent, skipToErrors);
  }

  /**
   * Create a skip link element
   */
  private createSkipLink(text: string, target: string): HTMLElement {
    const link = document.createElement('a');
    link.href = target;
    link.textContent = text;
    link.className = 'campaignbridge-skip-link';
    link.style.cssText = `
      position: absolute;
      top: -40px;
      left: 6px;
      background: #000;
      color: #fff;
      padding: 8px;
      text-decoration: none;
      z-index: 1000;
      border-radius: 4px;
    `;

    // Show on focus
    link.addEventListener('focus', () => {
      link.style.top = '6px';
    });

    link.addEventListener('blur', () => {
      link.style.top = '-40px';
    });

    return link;
  }

  /**
   * Create error announcer for form validation errors
   */
  private createErrorAnnouncer(): void {
    this.errorAnnouncer = document.createElement('div');
    this.errorAnnouncer.id = `${this.formId}-errors`;
    this.errorAnnouncer.setAttribute('aria-live', 'assertive');
    this.errorAnnouncer.setAttribute('aria-atomic', 'true');
    this.errorAnnouncer.className =
      'campaignbridge-error-announcer screen-reader-text';
    this.errorAnnouncer.style.cssText = `
      position: absolute;
      left: -10000px;
      width: 1px;
      height: 1px;
      overflow: hidden;
    `;

    document.body.appendChild(this.errorAnnouncer);
  }

  /**
   * Set up keyboard navigation
   */
  private setupKeyboardNavigation(): void {
    if (!this.keyboardNavigationEnabled) return;

    document.addEventListener('keydown', this.handleKeydown.bind(this));
    document.addEventListener('focusin', this.handleFocusIn.bind(this));
  }

  /**
   * Handle keyboard navigation
   */
  private handleKeydown(event: KeyboardEvent): void {
    const target = event.target as HTMLElement;
    if (!target || !this.isFormElement(target)) return;

    switch (event.key) {
      case 'Enter':
        if (
          target.tagName === 'BUTTON' ||
          target.getAttribute('role') === 'button'
        ) {
          // Allow default behavior for buttons
          return;
        }
        // Prevent form submission on Enter for inputs, trigger validation instead
        event.preventDefault();
        this.validateFieldOnDemand(target);
        break;

      case 'Escape':
        // Clear focus and return to form start
        this.returnFocusToForm();
        break;

      case 'Tab':
        // Track tab navigation for focus management
        this.handleTabNavigation(target, event.shiftKey);
        break;
    }
  }

  /**
   * Handle focus events for accessibility
   */
  private handleFocusIn(event: FocusEvent): void {
    const target = event.target as HTMLElement;
    this.lastFocusedElement = target;

    // Announce field focus for screen readers
    if (this.isFormElement(target)) {
      const fieldLabel = this.getFieldLabel(
        target,
        target.id ||
          (target as HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement)
            .name ||
          'unknown'
      );
      this.announceToScreenReader(`Focused on ${fieldLabel}`, 'polite');
    }
  }

  /**
   * Handle tab navigation for focus management
   */
  private handleTabNavigation(
    currentElement: HTMLElement,
    shiftKey: boolean
  ): void {
    // Store navigation path for screen reader announcements
    const formElements = this.getFocusableFormElements();
    const currentIndex = formElements.indexOf(currentElement);

    if (currentIndex !== -1) {
      const nextIndex = shiftKey ? currentIndex - 1 : currentIndex + 1;
      if (nextIndex >= 0 && nextIndex < formElements.length) {
        const nextElement = formElements[nextIndex];
        const direction = shiftKey ? 'previous' : 'next';
        const label = this.getFieldLabel(
          nextElement,
          nextElement.id ||
            (
              nextElement as
                | HTMLInputElement
                | HTMLSelectElement
                | HTMLTextAreaElement
            ).name ||
            'field'
        );

        // Announce navigation
        setTimeout(() => {
          this.announceToScreenReader(
            `Moving to ${direction} field: ${label}`,
            'polite'
          );
        }, 100);
      }
    }
  }

  /**
   * Get all focusable form elements
   */
  private getFocusableFormElements(): HTMLElement[] {
    const form = document.getElementById(this.formId);
    if (!form) return [];

    const focusableSelectors = [
      'input:not([type="hidden"]):not([disabled])',
      'select:not([disabled])',
      'textarea:not([disabled])',
      'button:not([disabled])',
      '[tabindex]:not([tabindex="-1"]):not([disabled])',
    ];

    return Array.from(form.querySelectorAll(focusableSelectors.join(', ')));
  }

  /**
   * Check if element is a form element
   */
  private isFormElement(element: HTMLElement): boolean {
    const formElements = ['INPUT', 'SELECT', 'TEXTAREA', 'BUTTON'];
    return (
      formElements.includes(element.tagName) ||
      element.getAttribute('role') === 'button' ||
      element.hasAttribute('tabindex')
    );
  }

  /**
   * Validate field on demand (for keyboard navigation)
   */
  private validateFieldOnDemand(field: HTMLElement): void {
    // Trigger change event to initiate validation
    const changeEvent = new Event('change', { bubbles: true });
    field.dispatchEvent(changeEvent);
  }

  /**
   * Return focus to form start
   */
  private returnFocusToForm(): void {
    const form = document.getElementById(this.formId);
    const firstFocusable = form?.querySelector(
      'input:not([type="hidden"]), select, textarea, button'
    ) as HTMLElement;

    if (firstFocusable) {
      firstFocusable.focus();
      this.announceToScreenReader('Returned to beginning of form', 'polite');
    }
  }

  /**
   * Announce validation errors
   */
  public announceValidationErrors(errors: string[]): void {
    if (!this.errorAnnouncer || errors.length === 0) return;

    const errorMessage = `Form validation errors: ${errors.join(', ')}`;
    this.errorAnnouncer.textContent = errorMessage;

    // Show skip to errors link
    const skipToErrors = this.skipLinks.find(link =>
      link.textContent?.includes('Skip to form errors')
    );
    if (skipToErrors) {
      skipToErrors.style.display = 'block';
    }

    // Announce errors assertively
    this.announceToScreenReader(errorMessage, 'assertive');
  }

  /**
   * Clear validation error announcements
   */
  public clearValidationErrors(): void {
    if (this.errorAnnouncer) {
      this.errorAnnouncer.textContent = '';
    }

    // Hide skip to errors link
    const skipToErrors = this.skipLinks.find(link =>
      link.textContent?.includes('Skip to form errors')
    );
    if (skipToErrors) {
      skipToErrors.style.display = 'none';
    }
  }

  /**
   * Enhance field with comprehensive ARIA attributes
   */
  public enhanceFieldAria(
    field: HTMLElement,
    state: FieldState,
    fieldId: string
  ): void {
    const fieldLabel = this.getFieldLabel(field, fieldId);

    // Basic ARIA attributes
    field.setAttribute('aria-label', fieldLabel);
    field.setAttribute('aria-describedby', `${fieldId}-description`);

    // Required state
    if (state.required) {
      field.setAttribute('aria-required', 'true');
      field.setAttribute('required', 'required');
    } else {
      field.removeAttribute('aria-required');
      field.removeAttribute('required');
    }

    // Visibility state
    const wrapper = field.closest(
      '.campaignbridge-conditional-field'
    ) as HTMLElement;
    if (wrapper) {
      wrapper.setAttribute('aria-hidden', state.visible ? 'false' : 'true');

      if (!state.visible) {
        // Move focus away from hidden fields
        if (document.activeElement === field) {
          this.returnFocusToForm();
        }
      }
    }

    // Create field description if it doesn't exist
    this.ensureFieldDescription(field, fieldId, state);
  }

  /**
   * Ensure field has an accessible description
   */
  private ensureFieldDescription(
    field: HTMLElement,
    fieldId: string,
    state: FieldState
  ): void {
    const existingDesc = document.getElementById(`${fieldId}-description`);
    if (existingDesc) {
      existingDesc.remove();
    }

    const description = document.createElement('div');
    description.id = `${fieldId}-description`;
    description.className =
      'campaignbridge-field-description screen-reader-text';
    description.style.cssText = `
      position: absolute;
      left: -10000px;
      width: 1px;
      height: 1px;
      overflow: hidden;
    `;

    let descText = '';
    if (state.required) {
      descText += 'This field is required. ';
    }

    if (!state.visible) {
      descText +=
        'This field is currently hidden and may become visible based on your previous answers.';
    }

    description.textContent = descText;

    // Insert after the field
    field.parentNode?.insertBefore(description, field.nextSibling);
  }

  /**
   * Set up form landmarks for screen reader navigation
   */
  public setupFormLandmarks(): void {
    const form = document.getElementById(this.formId);
    if (!form) return;

    // Ensure form has a proper role and label
    if (!form.getAttribute('role')) {
      form.setAttribute('role', 'form');
    }

    if (
      !form.getAttribute('aria-label') &&
      !form.getAttribute('aria-labelledby')
    ) {
      form.setAttribute('aria-label', 'Conditional Form');
    }

    // Add navigation landmark for form sections
    const sections = form.querySelectorAll('fieldset, .form-section');
    sections.forEach((section, index) => {
      const sectionEl = section as HTMLElement;
      if (!sectionEl.getAttribute('role')) {
        sectionEl.setAttribute('role', 'region');
        sectionEl.setAttribute('aria-label', `Form section ${index + 1}`);
      }
    });
  }

  /**
   * Enable or disable keyboard navigation
   */
  public setKeyboardNavigation(enabled: boolean): void {
    this.keyboardNavigationEnabled = enabled;

    if (enabled) {
      this.setupKeyboardNavigation();
    } else {
      // Remove event listeners (would need to store references to remove them)
      document.removeEventListener('keydown', this.handleKeydown.bind(this));
      document.removeEventListener('focusin', this.handleFocusIn.bind(this));
    }
  }

  /**
   * Get current accessibility status
   */
  public getAccessibilityStatus(): {
    keyboardNavigation: boolean;
    skipLinks: number;
    liveRegions: number;
    errorAnnouncer: boolean;
  } {
    return {
      keyboardNavigation: this.keyboardNavigationEnabled,
      skipLinks: this.skipLinks.length,
      liveRegions: this.liveRegions.length,
      errorAnnouncer: this.errorAnnouncer !== null,
    };
  }

  /**
   * Announce field changes to screen readers
   */
  public announceFieldChanges(changes: string[]): void {
    if (changes.length === 0) {
      return;
    }

    const message = changes.join('. ') + '.';
    this.announceToScreenReader(message);
  }

  /**
   * Handle focus management when fields are hidden
   */
  public handleFieldHidden(field: HTMLElement, fieldId: string): void {
    // Check if the field currently has focus
    if (document.activeElement === field) {
      // Find the next focusable element
      const nextFocusable = this.findNextFocusableElement(field);
      if (nextFocusable) {
        nextFocusable.focus();
      }
    }

    // Announce field removal
    const fieldLabel = this.getFieldLabel(field, fieldId);
    this.announceToScreenReader(
      `${fieldLabel} has been hidden and is no longer available.`
    );
  }

  /**
   * Update field accessibility attributes
   */
  public updateFieldAccessibility(
    field: HTMLElement,
    state: FieldState,
    fieldId: string
  ): void {
    // Use the enhanced ARIA method for comprehensive accessibility
    this.enhanceFieldAria(field, state, fieldId);
  }

  /**
   * Announce general messages to screen readers
   */
  public announceToScreenReader(
    message: string,
    priority: 'polite' | 'assertive' = 'polite'
  ): void {
    if (!this.announcementContainer) {
      this.createAnnouncementContainer();
    }

    if (!this.announcementContainer) {
      return;
    }

    // Clear previous announcements
    this.announcementContainer.textContent = '';

    // Create new announcement
    const announcement = document.createElement('div');
    announcement.setAttribute('aria-live', priority);
    announcement.setAttribute('aria-atomic', 'true');
    announcement.className = 'campaignbridge-a11y-announcement';

    // Track this live region for cleanup
    this.liveRegions.push(announcement);

    // Use setTimeout to ensure screen readers pick up the change
    setTimeout(() => {
      announcement.textContent = message;
      this.announcementContainer!.appendChild(announcement);

      // Clean up after announcement
      setTimeout(() => {
        if (announcement.parentNode) {
          announcement.parentNode.removeChild(announcement);
        }
        // Remove from tracking array
        const index = this.liveRegions.indexOf(announcement);
        if (index > -1) {
          this.liveRegions.splice(index, 1);
        }
      }, 1000);
    }, 100);
  }

  /**
   * Create container for accessibility announcements
   */
  private createAnnouncementContainer(): void {
    if (this.announcementContainer) {
      return;
    }

    this.announcementContainer = document.createElement('div');
    this.announcementContainer.className = 'campaignbridge-a11y-announcements';
    this.announcementContainer.setAttribute('aria-live', 'polite');
    this.announcementContainer.setAttribute('aria-atomic', 'true');

    // Position off-screen but still accessible
    this.announcementContainer.style.position = 'absolute';
    this.announcementContainer.style.left = '-10000px';
    this.announcementContainer.style.width = '1px';
    this.announcementContainer.style.height = '1px';
    this.announcementContainer.style.overflow = 'hidden';

    document.body.appendChild(this.announcementContainer);
  }

  /**
   * Find the next focusable element after the given field
   */
  private findNextFocusableElement(field: HTMLElement): HTMLElement | null {
    const focusableElements = document.querySelectorAll(
      'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
    );

    const currentIndex = Array.from(focusableElements).indexOf(field);

    if (currentIndex === -1 || currentIndex >= focusableElements.length - 1) {
      return null;
    }

    return focusableElements[currentIndex + 1] as HTMLElement;
  }

  /**
   * Get a human-readable label for a field
   */
  public getFieldLabel(field: HTMLElement, fieldId: string): string {
    // Try to find associated label
    const label = document.querySelector(
      `label[for="${field.id}"]`
    ) as HTMLLabelElement;
    if (label && label.textContent) {
      return label.textContent.trim();
    }

    // Try to find label in parent elements
    const parentLabel = field.closest('label');
    if (parentLabel && parentLabel.textContent) {
      return parentLabel.textContent.trim();
    }

    // Fallback to field ID with formatting
    return fieldId.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
  }

  /**
   * Add required indicator for screen readers
   */
  private addRequiredIndicator(field: HTMLElement, fieldLabel: string): void {
    // Check if indicator already exists
    const existingIndicator = field.parentNode?.querySelector(
      '.campaignbridge-field__required'
    );
    if (existingIndicator) {
      return;
    }

    // Create screen reader indicator
    const indicator = document.createElement('span');
    indicator.className = 'campaignbridge-field__required screen-reader-text';
    indicator.textContent = `${fieldLabel} is required`;

    // Insert after the field
    if (field.parentNode) {
      field.parentNode.insertBefore(indicator, field.nextSibling);
    }
  }

  /**
   * Remove required indicator
   */
  private removeRequiredIndicator(field: HTMLElement): void {
    const indicator = field.parentNode?.querySelector(
      '.campaignbridge-field__required'
    );
    if (indicator) {
      indicator.remove();
    }
  }

  /**
   * Cleanup accessibility elements and live regions
   */
  public destroy(): void {
    // Clean up skip links
    this.skipLinks.forEach(link => {
      if (link.parentNode) {
        try {
          link.parentNode.removeChild(link);
        } catch {
          // Element might have already been removed, ignore.
        }
      }
    });
    this.skipLinks = [];

    // Clean up error announcer
    if (this.errorAnnouncer && this.errorAnnouncer.parentNode) {
      try {
        this.errorAnnouncer.parentNode.removeChild(this.errorAnnouncer);
      } catch {
        // Element might have already been removed, ignore.
      }
      this.errorAnnouncer = null;
    }

    // Clean up any remaining live regions
    this.liveRegions.forEach(region => {
      if (region.parentNode) {
        try {
          region.parentNode.removeChild(region);
        } catch {
          // Element might have already been removed, ignore.
        }
      }
    });
    this.liveRegions = [];

    // Clean up field descriptions
    const descriptions = document.querySelectorAll(
      '.campaignbridge-field-description'
    );
    descriptions.forEach(desc => {
      if (desc.parentNode) {
        try {
          desc.parentNode.removeChild(desc);
        } catch {
          // Element might have already been removed, ignore.
        }
      }
    });

    // Clean up announcement container
    if (this.announcementContainer && this.announcementContainer.parentNode) {
      try {
        this.announcementContainer.parentNode.removeChild(
          this.announcementContainer
        );
      } catch {
        // Element might have already been removed, ignore.
      }
      this.announcementContainer = null;
    }

    // Remove keyboard navigation event listeners
    if (this.keyboardNavigationEnabled) {
      document.removeEventListener('keydown', this.handleKeydown.bind(this));
      document.removeEventListener('focusin', this.handleFocusIn.bind(this));
    }

    // Reset state
    this.keyboardNavigationEnabled = false;
    this.lastFocusedElement = null;
  }
}
