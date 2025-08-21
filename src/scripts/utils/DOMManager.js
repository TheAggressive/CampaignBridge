// Utility functions
const qs = (selector, root = document) => root.querySelector(selector);
const qsa = (selector, root = document) =>
  Array.from(root.querySelectorAll(selector));

// Centralized DOM element manager
export class DOMManager {
  static elements = {
    // Post elements
    postTypeSelect: () => qs('#campaignbridge-post-type'),
    postsSelect: () => qs('#campaignbridge-posts'),
    selectedPostsChips: () => qs('#cb-selected-posts-chips'),

    // Mailchimp elements
    mailchimpAudienceSelect: () => qs('#campaignbridge-mailchimp-audience'),
    mailchimpTemplatesSelect: () => qs('#campaignbridge-mailchimp-templates'),
    mailchimpApiKey: () => qs('#campaignbridge-mailchimp-api-key'),
    verifyStatus: () => qs('#campaignbridge-verify-status'),
    fetchAudiencesButton: () => qs('#campaignbridge-fetch-audiences'),
    fetchTemplatesButton: () => qs('#campaignbridge-fetch-templates'),
  };

  static getElement(key) {
    const element = this.elements[key]();
    if (!element) {
      // Only log warning if we're on a page where this element should exist
      const currentPage = this.getCampaignBridgePage();
      if (
        currentPage === 'settings' &&
        [
          'mailchimpAudienceSelect',
          'mailchimpTemplatesSelect',
          'mailchimpApiKey',
          'verifyStatus',
          'fetchAudiencesButton',
          'fetchTemplatesButton',
        ].includes(key)
      ) {
        console.warn(`DOM element not found: ${key}`);
      }
    }
    return element;
  }

  static getElements(key) {
    const elements = this.elements[key]();
    if (!elements || elements.length === 0) {
      // Only log warning if we're on a page where these elements should exist
      const currentPage = this.getCampaignBridgePage();
      // No specific element checks needed for remaining pages
    }
    return elements;
  }

  // Page detection utility
  static getCampaignBridgePage() {
    const screen = document.body.className;
    if (screen.includes('toplevel_page_campaignbridge')) return 'post-types';
    if (screen.includes('campaignbridge_page_campaignbridge-post-types'))
      return 'post-types';
    if (screen.includes('campaignbridge_page_campaignbridge-settings'))
      return 'settings';
    return null;
  }
}
