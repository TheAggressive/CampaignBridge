/**
 * CampaignBridge Settings Page JavaScript (ES6)
 *
 * Handles API key field masking, verification, and form enhancements.
 * Modern ES6 implementation without jQuery dependency.
 */

"use strict";

/**
 * Initialize all settings page functionality when DOM is ready
 */
function initSettingsPage() {
  initApiKeyMasking();
  initApiKeyVerification();
  initAudienceFetching();
  initTemplateFetching();
  initApiKeyVisibility();
}

/**
 * Initialize API key field masking behavior.
 * - Shows masked placeholder when field is not focused
 * - Securely retrieves and populates field when "Show" button is clicked
 * - Restores masking when focus is lost and field is empty
 */
function initApiKeyMasking() {
  const apiKeyField = document.querySelector(".cb-settings__api-key-field");
  const toggleButton = document.querySelector(".cb-settings__api-key-toggle");

  if (!apiKeyField || !toggleButton) {
    return;
  }

  // Store original placeholder for restoration
  const originalPlaceholder = apiKeyField.getAttribute("placeholder");
  const hasKey = apiKeyField.getAttribute("data-has-key") === "1";

  // Handle Show/Hide button click
  toggleButton.addEventListener("click", async (event) => {
    event.preventDefault();

    if (apiKeyField.type === "password") {
      // Show the API key - make secure AJAX call using WordPress REST API auth
      try {
        // For API key retrieval, use simple GET request (WordPress handles auth)
        console.log("Making API request to retrieve API key");

        const response = await fetch(
          "/wp-json/campaignbridge/v1/mailchimp/api-key",
          {
            method: "GET",
          },
        );

        console.log("API response status:", response.status);

        if (response.ok) {
          const data = await response.json();
          apiKeyField.value = data.api_key || "";
          apiKeyField.type = "text";
          toggleButton.textContent = "Hide";
          showStatusMessage("API key loaded successfully", "success");
        } else {
          const errorText = await response.text();
          console.error("API request failed:", response.status, errorText);
          showStatusMessage(
            `Failed to retrieve API key (${response.status})`,
            "error",
          );
        }
      } catch (error) {
        console.error("Error retrieving API key:", error);
      }
    } else {
      // Hide the API key - restore masked state
      apiKeyField.value = "";
      apiKeyField.type = "password";
      apiKeyField.setAttribute("placeholder", originalPlaceholder);
      toggleButton.textContent = "Show";
      showStatusMessage("API key hidden", "success");
    }
  });

  // Handle blur event - restore masking if field is empty
  apiKeyField.addEventListener("blur", (event) => {
    const field = event.target;
    const currentValue = field.value;

    // If field is empty after blur, restore password type and masked placeholder
    if (!currentValue) {
      field.type = "password";
      field.setAttribute("placeholder", originalPlaceholder);
      toggleButton.textContent = "Show";
    }
  });
}

/**
 * Initialize API key verification functionality.
 * - Validates API key format on input
 * - Provides visual feedback
 */
function initApiKeyVerification() {
  const apiKeyField = document.querySelector(".cb-settings__api-key-field");
  const statusSpan = document.querySelector(".cb-settings__verify-status");

  if (!apiKeyField || !statusSpan) {
    return;
  }

  // Handle input event - validate API key format
  apiKeyField.addEventListener("input", (event) => {
    const apiKey = event.target.value;
    validateApiKey(apiKey, statusSpan);
  });
}

/**
 * Validate API key format and update status indicator.
 * More flexible validation that handles partial input and various formats.
 *
 * @param {string} apiKey The API key to validate
 * @param {HTMLElement} statusElement The status element to update
 */
function validateApiKey(apiKey, statusElement) {
  // Remove existing status classes
  statusElement.classList.remove(
    "cb-settings__verify-status--valid",
    "cb-settings__verify-status--invalid",
  );

  if (!apiKey) {
    statusElement.textContent = "";
    return;
  }

  // Check if it looks like a complete API key (has dashes and proper length)
  const hasDashes = apiKey.includes("-");
  const totalLength = apiKey.replace(/[^a-f0-9-]/gi, "").length;

  // If it has dashes and is reasonably long, check format
  if (hasDashes && totalLength >= 30) {
    // Mailchimp API key format: xxxxxxxx-xxxxxx-xxxxxx-xxxxxx (32 chars total)
    const mailchimpPattern =
      /^[a-f0-9]{8}-[a-f0-9]{6}-[a-f0-9]{6}-[a-f0-9]{6}$/i;

    if (mailchimpPattern.test(apiKey)) {
      statusElement.classList.add("cb-settings__verify-status--valid");
      statusElement.textContent = "✓ Valid Mailchimp API key";
      return;
    }
  }

  // For partial input or other formats, show helpful message
  if (apiKey.length < 10) {
    statusElement.classList.add("cb-settings__verify-status--invalid");
    statusElement.textContent = "Enter your full API key";
  } else if (!hasDashes) {
    statusElement.classList.add("cb-settings__verify-status--invalid");
    statusElement.textContent = "API key should contain dashes";
  } else {
    // For any reasonably complete key, assume it's valid
    statusElement.classList.add("cb-settings__verify-status--valid");
    statusElement.textContent = "✓ API key entered - click Refresh to test";
  }
}

// Initialize when DOM is ready
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", initSettingsPage);
} else {
  initSettingsPage();
}

/**
 * Initialize audience fetching functionality.
 * - Handles "Reset Audiences" button clicks
 * - Fetches and populates audience dropdown via AJAX
 */
function initAudienceFetching() {
  const fetchButton = document.querySelector("#campaignbridge-fetch-audiences");
  const audienceSelect = document.querySelector(
    "#campaignbridge-mailchimp-audience",
  );
  const apiKeyField = document.querySelector(
    "#campaignbridge-mailchimp-api-key",
  );

  if (!fetchButton || !audienceSelect || !apiKeyField) {
    return;
  }

  fetchButton.addEventListener("click", async (event) => {
    event.preventDefault();

    try {
      // Show loading state
      fetchButton.textContent = "Loading...";
      fetchButton.disabled = true;

      // Get the API key from the field (user just entered it)
      const apiKey = apiKeyField.value;

      console.log("API key from field:", apiKey ? "present" : "missing");
      console.log("API key length:", apiKey ? apiKey.length : 0);

      const params = new URLSearchParams({ refresh: "1" });
      if (apiKey) {
        params.append("api_key", apiKey);
      }

      console.log(
        "Making fetch request to:",
        `/wp-json/campaignbridge/v1/mailchimp/audiences?${params.toString()}`,
      );
      console.log("Final params:", params.toString());

      const response = await fetch(
        `/wp-json/campaignbridge/v1/mailchimp/audiences?${params.toString()}`,
        {
          method: "GET",
        },
      );

      console.log("Response status:", response.status);
      console.log(
        "Response headers:",
        Object.fromEntries(response.headers.entries()),
      );

      if (!response.ok) {
        const errorText = await response.text();
        console.error(
          "Response not ok. Status:",
          response.status,
          "Body:",
          errorText,
        );
        showStatusMessage(
          `Failed to fetch audiences (${response.status})`,
          "error",
        );
        return;
      }

      const data = await response.json();

      console.log("Audience data received:", data);

      // Clear existing options (except the first empty one)
      while (audienceSelect.children.length > 1) {
        audienceSelect.removeChild(audienceSelect.lastChild);
      }

      // Populate with new audiences
      if (data.items && Array.isArray(data.items)) {
        console.log("Populating", data.items.length, "audiences");
        data.items.forEach((audience) => {
          const option = document.createElement("option");
          option.value = audience.id;
          option.textContent = audience.name || `Audience ${audience.id}`;
          audienceSelect.appendChild(option);
        });

        // Show success message
        showStatusMessage("Audiences updated successfully", "success");
      } else {
        console.log("No audiences found in response");
        showStatusMessage("No audiences found", "warning");
      }
    } catch (error) {
      console.error("Error fetching audiences:", error);
      showStatusMessage("Error fetching audiences", "error");
    } finally {
      // Restore button state
      fetchButton.textContent = "Refresh";
      fetchButton.disabled = false;
    }
  });
}

/**
 * Show a temporary status message to the user.
 *
 * @param {string} message The message to display
 * @param {string} type The message type ('success', 'error', 'warning')
 */
function showStatusMessage(message, type) {
  // Create or find status message container
  let statusContainer = document.querySelector(".cb-settings__status-message");
  if (!statusContainer) {
    statusContainer = document.createElement("div");
    statusContainer.className = "cb-settings__status-message";
    statusContainer.style.cssText = `
      margin-top: 8px;
      padding: 8px 12px;
      border-radius: 4px;
      font-size: 13px;
      font-weight: 500;
    `;

    // Insert after the API key field
    const apiKeyContainer = document.querySelector(
      ".cb-settings__api-key-container",
    );
    if (apiKeyContainer) {
      apiKeyContainer.parentNode.insertBefore(
        statusContainer,
        apiKeyContainer.nextSibling,
      );
    }
  }

  // Set message and styling based on type
  statusContainer.textContent = message;

  switch (type) {
    case "success":
      statusContainer.style.backgroundColor = "#d1fae5";
      statusContainer.style.color = "#065f46";
      statusContainer.style.border = "1px solid #a7f3d0";
      break;
    case "error":
      statusContainer.style.backgroundColor = "#fee2e2";
      statusContainer.style.color = "#991b1b";
      statusContainer.style.border = "1px solid #fecaca";
      break;
    case "warning":
      statusContainer.style.backgroundColor = "#fef3c7";
      statusContainer.style.color = "#92400e";
      statusContainer.style.border = "1px solid #fde68a";
      break;
  }

  // Auto-hide after 3 seconds
  setTimeout(() => {
    if (statusContainer && statusContainer.parentNode) {
      statusContainer.remove();
    }
  }, 3000);
}

/**
 * Initialize API key visibility for audience/template sections.
 * Shows audience and template dropdowns when API key is present.
 */
function initApiKeyVisibility() {
  const apiKeyField = document.querySelector(".cb-settings__api-key-field");
  const audienceRow = document.querySelector(".cb-settings__audience-row");
  const templateRow = document.querySelector(".cb-settings__template-row");

  if (!apiKeyField || !audienceRow || !templateRow) {
    return;
  }

  // Function to check and update visibility
  function updateVisibility() {
    // Check if we have an API key saved (data-has-key) OR if user just entered one
    const savedApiKey = apiKeyField.getAttribute("data-has-key") === "1";
    const enteredApiKey = apiKeyField.value && apiKeyField.value.length > 20;

    if (savedApiKey || enteredApiKey) {
      audienceRow.style.display = "table-row";
      templateRow.style.display = "table-row";
    } else {
      audienceRow.style.display = "none";
      templateRow.style.display = "none";
    }
  }

  // Check visibility on page load
  updateVisibility();

  // Monitor API key field changes
  apiKeyField.addEventListener("input", updateVisibility);
  apiKeyField.addEventListener("blur", updateVisibility);
}

/**
 * Initialize template fetching functionality.
 * - Handles "Reset Templates" button clicks
 * - Fetches and populates template dropdown via AJAX
 */
function initTemplateFetching() {
  const fetchButton = document.querySelector("#campaignbridge-fetch-templates");
  const templateSelect = document.querySelector(
    "#campaignbridge-mailchimp-templates",
  );
  const apiKeyField = document.querySelector(
    "#campaignbridge-mailchimp-api-key",
  );

  if (!fetchButton || !templateSelect || !apiKeyField) {
    return;
  }

  fetchButton.addEventListener("click", async (event) => {
    event.preventDefault();

    try {
      // Show loading state
      fetchButton.textContent = "Loading...";
      fetchButton.disabled = true;

      // Get the API key from the field (user just entered it)
      const apiKey = apiKeyField.value;

      console.log(
        "Template fetch - API key from field:",
        apiKey ? "present" : "missing",
      );

      const params = new URLSearchParams({ refresh: "1" });
      if (apiKey) {
        params.append("api_key", apiKey);
      }

      const response = await fetch(
        `/wp-json/campaignbridge/v1/mailchimp/templates?${params.toString()}`,
        {
          method: "GET",
        },
      );

      if (!response.ok) {
        const errorText = await response.text();
        console.error("Template fetch failed:", response.status, errorText);
        showStatusMessage(
          `Failed to fetch templates (${response.status})`,
          "error",
        );
        return;
      }

      const data = await response.json();

      // Clear existing options (except the first empty one)
      while (templateSelect.children.length > 1) {
        templateSelect.removeChild(templateSelect.lastChild);
      }

      // Populate with new templates
      if (data.items && Array.isArray(data.items)) {
        data.items.forEach((template) => {
          const option = document.createElement("option");
          option.value = template.id;
          option.textContent = template.name || `Template ${template.id}`;
          templateSelect.appendChild(option);
        });

        // Show success message
        showStatusMessage("Templates updated successfully", "success");
      } else {
        showStatusMessage("No templates found", "warning");
      }
    } catch (error) {
      console.error("Error fetching templates:", error);
      showStatusMessage("Error fetching templates", "error");
    } finally {
      // Restore button state
      fetchButton.textContent = "Refresh";
      fetchButton.disabled = false;
    }
  });
}

// Note: WordPress provides wpApiSettings.nonce for authenticated AJAX requests
