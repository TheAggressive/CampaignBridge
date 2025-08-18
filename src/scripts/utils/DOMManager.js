// Utility functions
const qs = (selector, root = document) => root.querySelector(selector);
const qsa = (selector, root = document) =>
  Array.from(root.querySelectorAll(selector));

// Centralized DOM element manager
export class DOMManager {
  static elements = {
    // Template elements
    templateSelect: () => qs('#campaignbridge-template-select'),
    templateContainer: () => qs('#campaignbridge-template-container'),
    newTemplateButton: () => qs('#campaignbridge-new-template'),

    // Preview elements
    previewFrame: () => qs('#cb-preview-frame'),
    previewHtml: () => qs('#cb-preview-html'),
    previewRenderedWrap: () => qs('#cb-preview-rendered-wrap'),
    previewModeInputs: () => qsa('input[name="cbPreviewMode"]'),
    copyHtmlButton: () => qs('#cb-copy-html'),
    refreshPreviewButton: () => qs('#cb-refresh-preview'),

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
        currentPage === 'templates' &&
        [
          'templateSelect',
          'templateContainer',
          'newTemplateButton',
          'previewFrame',
          'previewHtml',
          'previewRenderedWrap',
          'previewModeInputs',
          'copyHtmlButton',
          'refreshPreviewButton',
          'postTypeSelect',
          'postsSelect',
          'selectedPostsChips',
        ].includes(key)
      ) {
        console.warn(`DOM element not found: ${key}`);
      } else if (
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
      if (currentPage === 'templates' && ['previewModeInputs'].includes(key)) {
        console.warn(`DOM elements not found: ${key}`);
      }
    }
    return elements;
  }

  // Page detection utility
  static getCampaignBridgePage() {
    const screen = document.body.className;
    if (screen.includes('toplevel_page_campaignbridge')) return 'templates';
    if (screen.includes('campaignbridge_page_campaignbridge-post-types'))
      return 'post-types';
    if (screen.includes('campaignbridge_page_campaignbridge-settings'))
      return 'settings';
    return null;
  }
}
