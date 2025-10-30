/**
 * Handles accessibility features for conditional fields
 */

export class ConditionalAccessibility {
  private announcementContainer: HTMLElement | null = null;
  private formId: string;

  constructor(formId: string) {
    this.formId = formId;
  }

  /**
   * Initialize accessibility features
   */
  public initialize(): void {
    this.createAnnouncementContainer();
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
    state: { visible: boolean; required: boolean },
    fieldId: string
  ): void {
    const fieldLabel = this.getFieldLabel(field, fieldId);

    // Update required attribute and aria-required
    if (state.required) {
      field.setAttribute('aria-required', 'true');
      field.setAttribute('required', 'required');

      // Add required indicator for screen readers
      this.addRequiredIndicator(field, fieldLabel);
    } else {
      field.removeAttribute('aria-required');
      field.removeAttribute('required');

      // Remove required indicator
      this.removeRequiredIndicator(field);
    }

    // Update aria-hidden based on visibility
    const wrapper = field.closest(
      '.campaignbridge-conditional-field'
    ) as HTMLElement;
    if (wrapper) {
      wrapper.setAttribute('aria-hidden', state.visible ? 'false' : 'true');
    }
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

    // Use setTimeout to ensure screen readers pick up the change
    setTimeout(() => {
      announcement.textContent = message;
      this.announcementContainer!.appendChild(announcement);

      // Clean up after announcement
      setTimeout(() => {
        if (announcement.parentNode) {
          announcement.parentNode.removeChild(announcement);
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
  private getFieldLabel(field: HTMLElement, fieldId: string): string {
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
   * Cleanup accessibility elements
   */
  public destroy(): void {
    if (this.announcementContainer && this.announcementContainer.parentNode) {
      this.announcementContainer.parentNode.removeChild(
        this.announcementContainer
      );
      this.announcementContainer = null;
    }
  }
}
