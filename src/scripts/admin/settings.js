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
  initTabNavigation();
  initApiKeyMasking();
  initApiKeyVerification();
  initAudienceFetching();
  initApiKeyVisibility();
}

/**
 * Initialize tab navigation functionality.
 * - Handles switching between General and Providers tabs
 * - Manages active tab states and content visibility
 */
function initTabNavigation() {
  const tabLinks = document.querySelectorAll(".nav-tab");
  const tabContents = document.querySelectorAll(".tab-content");

  if (tabLinks.length === 0 || tabContents.length === 0) {
    return;
  }

  // Handle tab clicks
  tabLinks.forEach((tab) => {
    tab.addEventListener("click", (event) => {
      event.preventDefault();

      const targetTab = tab.getAttribute("data-tab");

      // Remove active class from all tabs and contents
      tabLinks.forEach((t) => t.classList.remove("nav-tab-active"));
      tabContents.forEach((c) => c.classList.remove("active"));

      // Add active class to clicked tab and corresponding content
      tab.classList.add("nav-tab-active");
      const targetContent = document.getElementById(targetTab);
      if (targetContent) {
        targetContent.classList.add("active");
      }
    });
  });
}

/**
 * Initialize API key field masking behavior.
 * - Shows masked placeholder when field is not focused
 * - Toggles visibility without exposing API key via REST API
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
  toggleButton.addEventListener("click", (event) => {
    event.preventDefault();

    if (apiKeyField.type === "password") {
      // Show the API key field - user can enter new key
      apiKeyField.type = "text";
      apiKeyField.focus();
      toggleButton.textContent = "Hide";
      showStatusMessage("Enter your API key", "info");
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

      // Send API key for testing if provided (will be validated server-side)
      const requestBody = {
        refresh: "1",
        ...(apiKey && { api_key: apiKey }), // Only include if API key is present
      };

      console.log("Making POST request to audiences endpoint");

      const response = await fetch(
        "/wp-json/campaignbridge/v1/mailchimp/audiences",
        {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "X-WP-Nonce": wpApiSettings ? wpApiSettings.nonce : "", // [SECURE]
          },
          body: JSON.stringify(requestBody),
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
 * Initialize API key visibility for audience section.
 * Shows audience dropdown when API key is present.
 */
function initApiKeyVisibility() {
  const apiKeyField = document.querySelector(".cb-settings__api-key-field");
  const audienceRow = document.querySelector(".cb-settings__audience-row");

  if (!apiKeyField || !audienceRow) {
    return;
  }

  // Function to check and update visibility
  function updateVisibility() {
    // Check if we have an API key saved (data-has-key) OR if user just entered one
    const savedApiKey = apiKeyField.getAttribute("data-has-key") === "1";
    const enteredApiKey = apiKeyField.value && apiKeyField.value.length > 20;

    if (savedApiKey || enteredApiKey) {
      audienceRow.style.display = "table-row";
    } else {
      audienceRow.style.display = "none";
    }
  }

  // Check visibility on page load
  updateVisibility();

  // Monitor API key field changes
  apiKeyField.addEventListener("input", updateVisibility);
  apiKeyField.addEventListener("blur", updateVisibility);
}

// Template fetching removed - templates are now managed in the template editor

// Note: WordPress provides wpApiSettings.nonce for authenticated AJAX requests
