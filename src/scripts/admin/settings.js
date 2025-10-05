/**
 * CampaignBridge Settings Page JavaScript (ES6)
 *
 * Handles interactive functionality for the settings page including
 * API key field toggling and form enhancements using modern ES6 JavaScript.
 *
 * @package CampaignBridge\Admin\Scripts
 * @since 0.1.0
 */

/**
 * Main settings functionality object
 */
class CampaignBridgeSettings {
  /**
   * Initialize all settings functionality when DOM is ready
   */
  static init() {
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", () => {
        this.initApiKeyToggles();
        this.initProviderPreview();
      });
    } else {
      this.initApiKeyToggles();
      this.initProviderPreview();
    }
  }

  /**
   * Initialize provider preview functionality
   */
  static initProviderPreview() {
    const providerSelect = document.querySelector(
      ".campaignbridge-provider-select",
    );

    if (!providerSelect) {
      return;
    }

    // Prevent rapid changes by debouncing
    let timeoutId = null;

    // Listen for changes on the provider dropdown
    providerSelect.addEventListener("change", (event) => {
      const selectedProvider = event.target.value;
      const previewUrl = event.target.dataset.previewUrl;

      // Clear any pending preview
      if (timeoutId) {
        clearTimeout(timeoutId);
      }

      if (selectedProvider && previewUrl) {
        try {
          // Validate URL and provider value
          new URL(previewUrl); // Will throw if invalid URL

          if (!/^[a-zA-Z0-9_-]+$/.test(selectedProvider)) {
            console.warn("Invalid provider value:", selectedProvider);
            return;
          }

          // Debounce the reload to prevent rapid changes
          timeoutId = setTimeout(() => {
            // Reload the page with the view parameter
            const url = new URL(previewUrl);
            console.log("Setting view parameter to:", selectedProvider);
            url.searchParams.set("view", selectedProvider);
            console.log("URL after setting view:", url.toString());

            // Preserve other URL parameters (excluding old preview parameter)
            const currentUrl = new URL(window.location);
            console.log(
              "Current URL parameters:",
              Array.from(currentUrl.searchParams.entries()),
            );
            currentUrl.searchParams.forEach((value, key) => {
              if (key !== "view" && key !== "provider_preview") {
                console.log("Copying parameter:", key, "=", value);
                url.searchParams.set(key, value);
              } else {
                console.log("Skipping parameter:", key);
              }
            });

            const finalUrl = url.toString();
            console.log("Final URL:", finalUrl);
            // Reload the page with the view parameter
            window.location.href = finalUrl;
          }, 300); // 300ms debounce
        } catch (error) {
          console.error("Error constructing preview URL:", error);
          // Fall back to form submission if URL construction fails
          const form = providerSelect.closest("form");
          if (form) {
            form.submit();
          }
        }
      }
    });
  }

  /**
   * Initialize API key field toggle functionality
   */
  static initApiKeyToggles() {
    const containers = document.querySelectorAll(
      ".campaignbridge-api-key-field",
    );

    containers.forEach((container) => {
      const hasKey = container.dataset.hasKey === "true";

      if (hasKey) {
        this.setupApiKeyToggle(container);
      }
    });
  }

  /**
   * Set up toggle functionality for an API key field
   *
   * @param {HTMLElement} container The API key field container
   */
  static setupApiKeyToggle(container) {
    const display = container.querySelector(".api-key-display");
    const edit = container.querySelector(".api-key-edit");
    const toggle = container.querySelector(".api-key-toggle");
    const input = container.querySelector(".api-key-input");
    const save = container.querySelector(".api-key-save");
    const cancel = container.querySelector(".api-key-cancel");

    // Toggle to edit mode
    toggle.addEventListener("click", (e) => {
      e.preventDefault();
      this.toggleToEdit(display, edit);
    });

    // Save button removed - API key updates in real-time, user clicks Save Settings

    // Cancel editing
    cancel.addEventListener("click", (e) => {
      e.preventDefault();
      this.cancelEdit(display, edit, input);
    });
  }

  /**
   * Toggle from display mode to edit mode
   *
   * @param {HTMLElement} display The display container
   * @param {HTMLElement} edit The edit container
   */
  static toggleToEdit(display, edit) {
    display.style.display = "none";
    edit.style.display = "block";
    edit.querySelector(".api-key-input").focus();
  }

  /**
   * Update the hidden API key field with the current input value
   *
   * @param {string} value The API key value
   */
  static updateApiKeyField(value) {
    try {
      // Remove any existing hidden API key fields to avoid duplicates
      const existingHiddenFields = document.querySelectorAll(
        'input[type="hidden"][name="campaignbridge_settings[api_key]"]',
      );
      existingHiddenFields.forEach((field) => field.remove());

      // Create a new hidden input field with the correct value
      const hiddenField = document.createElement("input");
      hiddenField.type = "hidden";
      hiddenField.name = "campaignbridge_settings[api_key]";
      hiddenField.value = value;

      // Find the form and add the hidden field
      const form = document.querySelector('form[action="options.php"]');
      if (form) {
        form.appendChild(hiddenField);
        console.log("Created hidden API key field with value:", value);
      } else {
        console.error("Form not found to add hidden field");
      }

      // Also try to update any existing visible field
      const visibleField = document.querySelector(
        'input[name="campaignbridge_settings[api_key]"]:not([type="hidden"])',
      );
      if (visibleField) {
        visibleField.value = value;
        console.log("Also updated visible field");
      }
    } catch (error) {
      console.error("Error creating hidden API key field:", error);
    }
  }

  /**
   * Submit the settings form instead of making AJAX call
   *
   * @param {string} newValue The new API key value
   * @param {HTMLElement} container The main container
   * @param {HTMLElement} display The display container
   * @param {HTMLElement} edit The edit container
   * @param {HTMLInputElement} input The input field
   */
  static submitSettingsForm(newValue, container, display, edit, input) {
    // Find the form - try multiple selectors for reliability
    let form = document.querySelector('form[action="options.php"]');

    // Fallback: look for forms with method="post"
    if (!form) {
      const forms = document.querySelectorAll('form[method="post"]');
      for (let i = 0; i < forms.length; i++) {
        if (forms[i].action && forms[i].action.includes("options.php")) {
          form = forms[i];
          break;
        }
      }
    }

    if (!form) {
      this.showError(input, "Settings form not found");
      return;
    }

    // Wait a bit to ensure the form is fully loaded and interactive
    setTimeout(() => {
      this.performFormSubmission(form, newValue, input);
    }, 100);
  }

  /**
   * Perform the actual form submission
   *
   * @param {HTMLFormElement} form The form element to submit
   * @param {string} newValue The API key value to set
   * @param {HTMLInputElement} input The input field (for error display)
   */
  static performFormSubmission(form, newValue, input) {
    console.log("Performing form submission with API key:", newValue);

    // Validate that we have a form element
    if (!form || form.tagName !== "FORM") {
      console.error("Invalid form element:", form);
      this.showError(input, "Invalid form element found");
      return;
    }

    // Set the API key value in the form
    const apiKeyField = form.querySelector('input[name*="[api_key]"]');
    if (apiKeyField) {
      console.log(
        "Found API key field:",
        apiKeyField.name,
        "current value:",
        apiKeyField.value,
      );
      apiKeyField.value = newValue;
      console.log("Set API key field value to:", apiKeyField.value);
    } else {
      console.warn("API key field not found in form");
      // List all input fields in the form for debugging
      const allInputs = form.querySelectorAll("input");
      console.log(
        "All input fields in form:",
        Array.from(allInputs).map((input) => `${input.name}: ${input.value}`),
      );
    }

    // Try form submission with detailed logging
    console.log("About to submit form...");
    console.log("Form action:", form.action);
    console.log("Form method:", form.method);
    console.log("Form has submit method:", typeof form.submit === "function");

    // Check if form has preventDefault handlers
    console.log("Form has submit event listeners");

    try {
      // Method 1: Try to submit by clicking the actual submit button WordPress created
      const submitButton = form.querySelector(
        'input[type="submit"], button[type="submit"]',
      );
      console.log("Submit button found:", !!submitButton);
      if (submitButton) {
        console.log(
          "Submit button type:",
          submitButton.type,
          "tagName:",
          submitButton.tagName,
        );
        console.log("Clicking submit button...");
        submitButton.click();
        console.log("Submit button clicked successfully");
      } else {
        console.warn("No submit button found");
        // Method 2: Try direct form submit
        if (typeof form.submit === "function") {
          console.log("Calling form.submit()...");
          form.submit();
          console.log("Form.submit() called successfully");
        } else {
          throw new Error("Form has no submit method");
        }
      }
    } catch (error) {
      console.error("Form submission failed:", error);
      this.showError(
        input,
        "Failed to submit form. Please try clicking the Save Settings button manually.",
      );
    }
  }

  /**
   * Complete the save operation - just switch back to display mode
   *
   * @param {HTMLElement} container The main container
   * @param {HTMLElement} display The display container
   * @param {HTMLElement} edit The edit container
   * @param {HTMLInputElement} input The input field
   * @param {string} newValue The new API key value
   */
  static completeSave(container, display, edit, input, newValue) {
    // Update the masked display
    const maskedValue = this.maskApiKey(newValue);
    display.querySelector(".api-key-masked").value = maskedValue;

    // Switch back to display mode
    edit.style.display = "none";
    display.style.display = "flex";

    // Reset edit form
    input.value = "";
    edit.querySelectorAll(".button").forEach((button) => {
      button.disabled = false;
    });
    input.disabled = false;

    // Show confirmation
    this.showSuccess("API key updated. Click 'Save Settings' to save changes.");
  }

  /**
   * Cancel editing and return to display mode
   *
   * @param {HTMLElement} display The display container
   * @param {HTMLElement} edit The edit container
   * @param {HTMLInputElement} input The input field
   */
  static cancelEdit(display, edit, input) {
    edit.style.display = "none";
    display.style.display = "flex";
    input.value = "";
  }

  /**
   * Mask an API key for display
   *
   * @param {string} apiKey The API key to mask
   * @return {string} The masked API key
   */
  static maskApiKey(apiKey) {
    if (apiKey.length <= 8) {
      return "•".repeat(apiKey.length);
    }
    return "•".repeat(apiKey.length - 4) + apiKey.substring(apiKey.length - 4);
  }

  /**
   * Show an error message
   *
   * @param {HTMLElement} element The element to show error near
   * @param {string} message The error message
   */
  static showError(element, message) {
    this.showMessage(element, message, "error");
  }

  /**
   * Show a success message
   *
   * @param {string} message The success message
   */
  static showSuccess(message) {
    // Create a temporary success notice
    const notice = document.createElement("div");
    notice.className = "notice notice-success is-dismissible";
    notice.innerHTML = `<p>${message}</p>`;

    const h1 = document.querySelector(".wrap h1");
    if (h1) {
      h1.parentNode.insertBefore(notice, h1.nextSibling);
    }

    // Auto-dismiss after 3 seconds
    setTimeout(() => {
      notice.style.opacity = "0";
      setTimeout(() => {
        notice.remove();
      }, 300);
    }, 3000);
  }

  /**
   * Show a message near an element
   *
   * @param {HTMLElement} element The element to show message near
   * @param {string} message The message to show
   * @param {string} type The message type (error, success, warning)
   */
  static showMessage(element, message, type) {
    // Remove existing message
    const existing = element.parentNode.querySelector(
      ".campaignbridge-settings__message",
    );
    if (existing) {
      existing.remove();
    }

    const messageDiv = document.createElement("div");
    messageDiv.className = `campaignbridge-settings__message campaignbridge-settings__message--${type}`;
    messageDiv.textContent = message;

    element.parentNode.insertBefore(messageDiv, element.nextSibling);

    setTimeout(() => {
      messageDiv.style.opacity = "0";
      setTimeout(() => {
        messageDiv.remove();
      }, 300);
    }, 3000);
  }
}

// Initialize when DOM is ready
CampaignBridgeSettings.init();
