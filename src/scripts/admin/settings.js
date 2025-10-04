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
      });
    } else {
      this.initApiKeyToggles();
    }
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

    // Save changes
    save.addEventListener("click", (e) => {
      e.preventDefault();
      this.saveApiKey(container, display, edit, input);
    });

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
   * Save the API key changes
   *
   * @param {HTMLElement} container The main container
   * @param {HTMLElement} display The display container
   * @param {HTMLElement} edit The edit container
   * @param {HTMLInputElement} input The input field
   */
  static saveApiKey(container, display, edit, input) {
    const newValue = input.value.trim();

    // Basic validation
    if (!newValue) {
      this.showError(input, "API key cannot be empty");
      return;
    }

    // Validate Mailchimp API key pattern
    const mailchimpPattern = /^[a-f0-9]{32}-us[0-9]+$/;
    if (!mailchimpPattern.test(newValue)) {
      this.showError(
        input,
        "Invalid Mailchimp API key format. Please check your Mailchimp API key.",
      );
      return;
    }

    // Show loading state
    edit.querySelectorAll(".button").forEach((button) => {
      button.disabled = true;
    });
    input.disabled = true;

    // Make AJAX call to save settings
    this.saveSettingsAjax(newValue)
      .then((response) => {
        console.log("CampaignBridgeSettings: AJAX response:", response);
        this.completeSave(container, display, edit, input, newValue);
      })
      .catch((error) => {
        console.error("CampaignBridgeSettings: AJAX error:", error);
        this.showError(input, error.message || "Failed to save API key");
        // Reset loading state
        edit.querySelectorAll(".button").forEach((button) => {
          button.disabled = false;
        });
        input.disabled = false;
      });
  }

  /**
   * Complete the save operation
   *
   * @param {HTMLElement} container The main container
   * @param {HTMLElement} display The display container
   * @param {HTMLElement} edit The edit container
   * @param {HTMLInputElement} input The input field
   * @param {string} newValue The new API key value
   */
  static completeSave(container, display, edit, input, newValue) {
    // Update the masked display (simulate getting masked value from server)
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

    // Show success message
    this.showSuccess("API key updated successfully");
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
   * Save settings via AJAX to WordPress admin-ajax.php
   *
   * @param {string} apiKey The API key to save
   * @return {Promise} Promise that resolves on success, rejects on error
   */
  static saveSettingsAjax(apiKey) {
    const ajaxUrl = window.campaignbridgeSettings?.ajaxUrl || "";
    const nonce = window.campaignbridgeSettings?.nonce || "";
    const action =
      window.campaignbridgeSettings?.action || "campaignbridge_save_settings";

    return fetch(ajaxUrl, {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: new URLSearchParams({
        action: action,
        nonce: nonce,
        api_key: apiKey,
        tab: "providers",
      }),
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
      })
      .then((data) => {
        if (!data.success) {
          throw new Error(data.data || "Failed to save settings");
        }
        return data;
      })
      .catch((error) => {
        console.error("CampaignBridgeSettings: AJAX error:", error);
        throw error;
      });
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
