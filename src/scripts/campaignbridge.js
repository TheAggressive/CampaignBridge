/**
 * Admin UI interactions for CampaignBridge.
 *
 * Modern ES6 implementation with:
 * - DOM querying and event delegation
 * - REST API integration
 * - Safe HTML escaping when rendering dynamic content
 * - Template management and preview
 * - Page-specific initialization to prevent console warnings
 *
 * Page Detection:
 * - Templates page: Main email template builder with preview
 * - Post Types page: Post type inclusion/exclusion settings
 * - Settings page: Mailchimp and provider configuration
 *
 * Each manager only initializes on pages where its functionality is needed,
 * preventing DOM element not found warnings on irrelevant pages.
 */

import { CampaignBridgeApp } from './CampaignBridgeApp.js';

// Initialize the application when DOM is ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => {
    try {
      new CampaignBridgeApp();
    } catch (error) {
      console.error('CampaignBridge: Failed to initialize application:', error);
    }
  });
} else {
  try {
    new CampaignBridgeApp();
  } catch (error) {
    console.error('CampaignBridge: Failed to initialize application:', error);
  }
}
