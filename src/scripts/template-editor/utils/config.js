/**
 * Configuration utility for the CampaignBridge Template Editor.
 *
 * Centralizes configuration detection and provides a clean interface
 * for WordPress integration. Detects values that can be determined
 * client-side while respecting server-provided security tokens.
 */

/**
 * Base configuration from WordPress (server-provided values)
 * Now minimal since most values are detected via WordPress APIs
 */
const BASE_CONFIG = window.CB_TM || {};

/**
 * Complete configuration object with all detected and provided values
 */
export const config = {
  // Client-detected values
  postType: "cb_email_template", // Hardcoded since this plugin is specifically for email templates
};

/**
 * Validates that essential configuration is present
 *
 * @throws {Error} If required configuration is missing
 */
export function validateConfig() {
  const required = ["postType"];

  for (const key of required) {
    if (!config[key]) {
      throw new Error(
        `Missing required configuration: ${key}. Check your WordPress setup.`,
      );
    }
  }
}

// Validate configuration on module load
validateConfig();

export default config;
