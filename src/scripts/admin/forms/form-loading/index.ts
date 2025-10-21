/**
 * Form Loading States
 *
 * Handles loading states for form submission including button text changes,
 * disabling buttons to prevent double-submission, and visual feedback.
 */

interface FormLoadingConfig {
  formId: string;
  loadingText: string;
  submitText: string;
}

export function initFormLoading(config: FormLoadingConfig): void {
  const { formId, loadingText, submitText } = config;

  function initializeLoadingScript(): void {
    const form = document.getElementById(formId);
    if (!form) {
      console.warn(`Form with ID ${formId} not found for loading script`);
      return;
    }

    const submitBtn = form.querySelector<HTMLInputElement>(
      'input[type="submit"]'
    );

    // Handle form submission loading state
    form.addEventListener('submit', (e: Event) => {
      if (submitBtn) {
        submitBtn.value = loadingText;
        submitBtn.disabled = true;
        submitBtn.style.opacity = '0.7';
      }
    });

    // Fallback: Re-enable submit button after 10 seconds
    form.addEventListener('submit', () => {
      setTimeout(() => {
        if (submitBtn) {
          submitBtn.value = submitText;
          submitBtn.disabled = false;
          submitBtn.style.opacity = '';
        }
      }, 10000);
    });
  }

  // Initialize when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeLoadingScript);
  } else {
    initializeLoadingScript();
  }
}

// Auto-initialize if config is available on window
declare global {
  interface Window {
    campaignbridgeFormLoading?: FormLoadingConfig;
  }
}

if (window.campaignbridgeFormLoading) {
  initFormLoading(window.campaignbridgeFormLoading);
}
