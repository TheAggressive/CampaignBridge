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

// Utility functions
const qs = (selector, root = document) => root.querySelector(selector);
const qsa = (selector, root = document) =>
  Array.from(root.querySelectorAll(selector));

// Page detection utility
const isCampaignBridgePage = () => {
  const screen = document.body.className;
  return (
    screen.includes('campaignbridge') ||
    screen.includes('toplevel_page_campaignbridge') ||
    screen.includes('campaignbridge_page_')
  );
};

// More specific page detection
const getCampaignBridgePage = () => {
  const screen = document.body.className;
  if (screen.includes('toplevel_page_campaignbridge')) return 'templates';
  if (screen.includes('campaignbridge_page_campaignbridge-post-types'))
    return 'post-types';
  if (screen.includes('campaignbridge_page_campaignbridge-settings'))
    return 'settings';
  return null;
};

// Check if current page has specific functionality
const hasTemplateFunctionality = () => getCampaignBridgePage() === 'templates';
const hasPostTypeFunctionality = () => getCampaignBridgePage() === 'post-types';
const hasSettingsFunctionality = () => getCampaignBridgePage() === 'settings';

// URL parameter utilities
const getQueryParam = (name) => {
  const params = new URLSearchParams(window.location.search);
  return params.get(name);
};

const setQueryParam = (name, value) => {
  const url = new URL(window.location.href);
  if (value == null || value === '') {
    url.searchParams.delete(name);
  } else {
    url.searchParams.set(name, value);
  }
  history.replaceState({}, '', url.toString());
};

// Event delegation utility
const on = (eventName, selector, handler) => {
  document.addEventListener(eventName, (event) => {
    const target = event.target?.closest(selector);
    if (target) handler(event, target);
  });
};

// HTML escaping utility
const escapeHTML = (value) => {
  const div = document.createElement('div');
  div.textContent = value ?? '';
  return div.innerHTML;
};

// Centralized DOM element manager
class DOMManager {
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
      const currentPage = getCampaignBridgePage();
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
      const currentPage = getCampaignBridgePage();
      if (currentPage === 'templates' && ['previewModeInputs'].includes(key)) {
        console.warn(`DOM elements not found: ${key}`);
      }
    }
    return elements;
  }
}

// Email export and preview service
class EmailExportService {
  constructor() {
    // Only initialize if we're on the templates page (where email export exists)
    if (hasTemplateFunctionality()) {
      this.initialize();
    }
  }

  initialize() {
    this.setupExportButton();
    this.setupPreviewButton();
  }

  setupExportButton() {
    // Listen for export button clicks from email template blocks
    // Only set up if we're on a page with template blocks
    if (!hasTemplateFunctionality()) return;

    document.addEventListener('click', (event) => {
      if (event.target?.classList?.contains('cb-export-html')) {
        this.exportEmailHTML(event.target);
      }
    });
  }

  setupPreviewButton() {
    // Listen for preview button clicks from email template blocks
    // Only set up if we're on a page with template blocks
    if (!hasTemplateFunctionality()) return;

    document.addEventListener('click', (event) => {
      if (event.target?.classList?.contains('cb-preview-email')) {
        this.previewEmail(event.target);
      }
    });
  }

  async exportEmailHTML(button) {
    try {
      button.disabled = true;
      button.textContent = 'Generating...';

      // Find the email template block
      const templateBlock = button.closest('.cb-email-template');
      if (!templateBlock) {
        throw new Error('Email template block not found');
      }

      // Get the block content
      const blockContent = this.extractBlockContent(templateBlock);

      // Generate email HTML
      const emailHTML = await this.generateEmailHTML(blockContent);

      // Copy to clipboard
      await this.copyToClipboard(emailHTML);

      // Show success message
      this.showNotification('Email HTML copied to clipboard!', 'success');
    } catch (error) {
      console.error('Export failed:', error);
      this.showNotification('Failed to export email HTML', 'error');
    } finally {
      button.disabled = false;
      button.textContent = 'Export HTML';
    }
  }

  async previewEmail(button) {
    try {
      button.disabled = true;
      button.textContent = 'Generating...';

      // Find the email template block
      const templateBlock = button.closest('.cb-email-template');
      if (!templateBlock) {
        throw new Error('Email template block not found');
      }

      // Get the block content
      const blockContent = this.extractBlockContent(templateBlock);

      // Generate email HTML
      const emailHTML = await this.generateEmailHTML(blockContent);

      // Open preview in new window
      this.openPreviewWindow(emailHTML);
    } catch (error) {
      console.error('Preview failed:', error);
      this.showNotification('Failed to generate preview', 'error');
    } finally {
      button.disabled = false;
      button.textContent = 'Preview Email';
    }
  }

  extractBlockContent(templateBlock) {
    // Extract block attributes and content
    const contentWrapper = templateBlock.querySelector('.cb-email-content');
    if (!contentWrapper) {
      throw new Error('Email content not found');
    }

    // Get template attributes
    const templateName =
      templateBlock.querySelector('.cb-email-template-name')?.textContent ||
      'Email Template';
    const emailWidth =
      templateBlock
        .querySelector('.cb-email-template-dimensions')
        ?.textContent?.match(/(\d+)px/)?.[1] || '600';

    // Convert blocks to structured data
    const blocks = this.convertDOMToBlocks(contentWrapper);

    return {
      templateName,
      emailWidth: parseInt(emailWidth),
      blocks,
      attributes: {
        backgroundColor: '#ffffff',
        textColor: '#333333',
        fontFamily: 'Arial, sans-serif',
        maxWidth: 600,
        padding: { top: 20, right: 20, bottom: 20, left: 20 },
      },
    };
  }

  convertDOMToBlocks(contentWrapper) {
    const blocks = [];

    // Convert WordPress blocks to structured data
    contentWrapper.querySelectorAll('[data-block]').forEach((blockElement) => {
      const blockName = blockElement.getAttribute('data-block');
      const blockAttributes = this.extractBlockAttributes(blockElement);
      const blockContent = this.extractBlockContent(blockElement);

      blocks.push({
        blockName,
        attrs: blockAttributes,
        innerContent: [blockContent],
        innerBlocks: [],
      });
    });

    return blocks;
  }

  extractBlockAttributes(blockElement) {
    const attributes = {};

    // Extract common attributes
    if (blockElement.style.backgroundColor) {
      attributes.backgroundColor = blockElement.style.backgroundColor;
    }
    if (blockElement.style.color) {
      attributes.textColor = blockElement.style.color;
    }
    if (blockElement.style.textAlign) {
      attributes.align = blockElement.style.textAlign;
    }
    if (blockElement.style.fontSize) {
      attributes.fontSize = blockElement.style.fontSize;
    }

    // Extract block-specific attributes
    if (blockElement.classList.contains('cb-email-post-slot')) {
      const slotKey =
        blockElement.querySelector('.cb-post-slot-key')?.textContent;
      if (slotKey) {
        attributes.slotKey = slotKey;
      }
    }

    return attributes;
  }

  extractBlockContent(blockElement) {
    // Extract text content while preserving HTML structure
    return blockElement.innerHTML;
  }

  async generateEmailHTML(blockData) {
    try {
      // Call the EmailGenerator service via REST API
      const response = await fetch(
        '/wp-json/campaignbridge/v1/email/generate',
        {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': window.wpApiSettings?.nonce || '',
          },
          body: JSON.stringify(blockData),
        }
      );

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }

      const result = await response.json();
      return result.html || this.generateFallbackHTML(blockData);
    } catch (error) {
      console.warn('REST API failed, using fallback:', error);
      return this.generateFallbackHTML(blockData);
    }
  }

  generateFallbackHTML(blockData) {
    // Fallback HTML generation if REST API fails
    const { templateName, emailWidth, blocks, attributes } = blockData;

    let html = `
      <!DOCTYPE html>
      <html lang="en">
      <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>${templateName}</title>
        <style>
          body { margin: 0; padding: 0; font-family: ${attributes.fontFamily}; }
          .email-container { width: ${emailWidth}px; max-width: 100%; margin: 0 auto; background: ${attributes.backgroundColor}; }
          .email-content { padding: 20px; color: ${attributes.textColor}; }
        </style>
      </head>
      <body>
        <div class="email-container">
          <div class="email-content">
    `;

    // Convert blocks to HTML
    blocks.forEach((block) => {
      html += this.convertBlockToHTML(block);
    });

    html += `
          </div>
        </div>
      </body>
      </html>
    `;

    return html;
  }

  convertBlockToHTML(block) {
    switch (block.blockName) {
      case 'core/paragraph':
        return `<p style="margin: 0 0 16px 0; font-size: 14px; line-height: 1.6;">${block.innerContent[0]}</p>`;

      case 'core/heading':
        const level = block.attrs.level || 2;
        return `<h${level} style="margin: 0 0 16px 0; font-size: ${
          level === 1 ? '24px' : '20px'
        }; font-weight: 600;">${block.innerContent[0]}</h${level}>`;

      case 'campaignbridge/email-post-slot':
        return `<div style="margin: 20px 0; padding: 20px; border: 1px solid #e5e7eb; border-radius: 4px; background: #f8f9fa;">
          <p style="margin: 0; color: #6b7280; font-style: italic;">Dynamic content slot: ${
            block.attrs.slotKey || 'unnamed'
          }</p>
        </div>`;

      default:
        return `<div style="margin: 16px 0;">${block.innerContent[0]}</div>`;
    }
  }

  async copyToClipboard(text) {
    try {
      await navigator.clipboard.writeText(text);
    } catch (error) {
      // Fallback for older browsers
      const textArea = document.createElement('textarea');
      textArea.value = text;
      document.body.appendChild(textArea);
      textArea.select();
      document.execCommand('copy');
      document.body.removeChild(textArea);
    }
  }

  openPreviewWindow(html) {
    const previewWindow = window.open(
      '',
      '_blank',
      'width=800,height=600,scrollbars=yes,resizable=yes'
    );
    previewWindow.document.write(html);
    previewWindow.document.close();
    previewWindow.focus();
  }

  showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `cb-notification cb-notification-${type}`;
    notification.style.cssText = `
      position: fixed;
      top: 20px;
      right: 20px;
      padding: 12px 20px;
      border-radius: 4px;
      color: white;
      font-weight: 500;
      z-index: 9999;
      background: ${
        type === 'success'
          ? '#10b981'
          : type === 'error'
          ? '#ef4444'
          : '#3b82f6'
      };
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    `;
    notification.textContent = message;

    document.body.appendChild(notification);

    // Auto-remove after 3 seconds
    setTimeout(() => {
      if (notification.parentNode) {
        notification.parentNode.removeChild(notification);
      }
    }, 3000);
  }
}

// Centralized error handling service
class ErrorHandler {
  static errorTypes = {
    NETWORK: 'network',
    VALIDATION: 'validation',
    PERMISSION: 'permission',
    NOT_FOUND: 'not_found',
    UNKNOWN: 'unknown',
  };

  static handleError(
    error,
    context = '',
    fallbackMessage = 'An error occurred'
  ) {
    const errorInfo = this.analyzeError(error);

    // Log error for debugging
    console.error(`[${context}] Error:`, errorInfo);

    // Show user-friendly message
    this.showErrorMessage(
      errorInfo.userMessage || fallbackMessage,
      errorInfo.type
    );

    // Track error if analytics are available
    this.trackError(errorInfo, context);

    return errorInfo;
  }

  static analyzeError(error) {
    if (error.name === 'TypeError' && error.message.includes('fetch')) {
      return {
        type: this.errorTypes.NETWORK,
        userMessage:
          'Network connection failed. Please check your internet connection.',
        technicalMessage: error.message,
        recoverable: true,
      };
    }

    if (error.status === 403 || error.status === 401) {
      return {
        type: this.errorTypes.PERMISSION,
        userMessage: "You don't have permission to perform this action.",
        technicalMessage: `HTTP ${error.status}: ${error.statusText}`,
        recoverable: false,
      };
    }

    if (error.status === 404) {
      return {
        type: this.errorTypes.NOT_FOUND,
        userMessage: 'The requested resource was not found.',
        technicalMessage: `HTTP ${error.status}: ${error.statusText}`,
        recoverable: true,
      };
    }

    if (error.name === 'ValidationError') {
      return {
        type: this.errorTypes.VALIDATION,
        userMessage: 'Please check your input and try again.',
        technicalMessage: error.message,
        recoverable: true,
      };
    }

    return {
      type: this.errorTypes.UNKNOWN,
      userMessage: 'An unexpected error occurred. Please try again.',
      technicalMessage: error.message || error.toString(),
      recoverable: true,
    };
  }

  static showErrorMessage(message, type = 'error') {
    // Create error notification
    const notification = document.createElement('div');
    notification.className = `cb-notification cb-notification-${type}`;
    notification.style.cssText = `
      position: fixed;
      top: 20px;
      right: 20px;
      padding: 12px 20px;
      border-radius: 4px;
      color: white;
      font-weight: 500;
      z-index: 9999;
      background: ${type === 'error' ? '#ef4444' : '#f59e0b'};
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
      max-width: 400px;
      word-wrap: break-word;
    `;
    notification.textContent = message;

    document.body.appendChild(notification);

    // Auto-remove after 5 seconds for errors
    setTimeout(() => {
      if (notification.parentNode) {
        notification.parentNode.removeChild(notification);
      }
    }, 5000);
  }

  static trackError(errorInfo, context) {
    // Send error to analytics if available
    if (window.gtag) {
      window.gtag('event', 'exception', {
        description: `${context}: ${errorInfo.technicalMessage}`,
        fatal: !errorInfo.recoverable,
      });
    }

    // Send to WordPress error log if available
    if (window.console && window.console.error) {
      window.console.error(`CampaignBridge Error [${context}]:`, errorInfo);
    }
  }

  static async withErrorHandling(
    operation,
    context = '',
    fallbackMessage = ''
  ) {
    try {
      return await operation();
    } catch (error) {
      this.handleError(error, context, fallbackMessage);
      throw error; // Re-throw for caller to handle if needed
    }
  }

  static withErrorHandlingSync(operation, context = '', fallbackMessage = '') {
    try {
      return operation();
    } catch (error) {
      this.handleError(error, context, fallbackMessage);
      throw error;
    }
  }
}

// REST API client
class ApiClient {
  constructor() {
    this.baseUrl = window.wpApiSettings?.root || '/wp-json/';
    this.nonce = window.wpApiSettings?.nonce;
  }

  async request(path, { method = 'GET', body } = {}) {
    const url = `${this.baseUrl}campaignbridge/v1${path}`;
    const headers = { 'Content-Type': 'application/json' };

    if (this.nonce) {
      headers['X-WP-Nonce'] = this.nonce;
    }

    const response = await fetch(url, {
      method,
      headers,
      credentials: 'same-origin',
      body: body ? JSON.stringify(body) : undefined,
    });

    return response.json();
  }

  async get(path) {
    return this.request(path, { method: 'GET' });
  }

  async post(path, body) {
    return this.request(path, { method: 'POST', body });
  }
}

// Response data extractors
const extractData = {
  items: (resp) => resp?.items ?? resp?.data?.items ?? [],
  html: (resp) => resp?.html ?? resp?.data?.html ?? '',
};

// Template management class
class TemplateManager {
  constructor() {
    this.api = new ApiClient();
    this.currentTemplateId = null;

    // Only initialize if we're on the templates page
    if (hasTemplateFunctionality()) {
      this.initialize();
    }
  }

  initialize() {
    this.setupTemplateSelection();
    this.setupNewTemplateButton();
    this.loadInitialTemplate();
  }

  setupTemplateSelection() {
    const templateSelect = DOMManager.getElement('templateSelect');
    if (!templateSelect) return;

    const initialTemplateId = getQueryParam('tpl');
    if (initialTemplateId) {
      templateSelect.value = String(initialTemplateId);
    }

    templateSelect.addEventListener('change', (event) => {
      this.currentTemplateId = event.target.value || '';
      setQueryParam('tpl', this.currentTemplateId);
      this.loadTemplate(this.currentTemplateId);

      if (this.currentTemplateId) {
        this.updateLivePreview(this.currentTemplateId);
      }
    });
  }

  setupNewTemplateButton() {
    const newButton = DOMManager.getElement('newTemplateButton');
    if (!newButton) return;

    newButton.addEventListener('click', async () => {
      try {
        const response = await this.api.post('/templates', {
          title: 'New Email Template',
          content: '',
          status: 'draft',
        });

        if (response?.id) {
          this.addTemplateToSelect(response);
          this.selectTemplate(response.id);
        }
      } catch (error) {
        console.error('Error creating new template:', error);
      }
    });
  }

  addTemplateToSelect(template) {
    const templateSelect = DOMManager.getElement('templateSelect');
    if (!templateSelect) return;

    const option = document.createElement('option');
    option.value = template.id;
    option.textContent = template.title?.rendered || 'New Email Template';
    templateSelect.appendChild(option);
  }

  selectTemplate(templateId) {
    const templateSelect = DOMManager.getElement('templateSelect');
    if (!templateSelect) return;

    templateSelect.value = templateId;
    this.currentTemplateId = templateId;
    setQueryParam('tpl', templateId);
    this.loadTemplate(templateId);
  }

  async loadTemplate(templateId) {
    if (!templateId) {
      this.showEmptyState();
      return;
    }

    try {
      // Load template content and initialize editor
      await this.updateLivePreview(templateId);
    } catch (error) {
      console.error('Error loading template:', error);
      this.showErrorState('Failed to load template');
    }
  }

  loadInitialTemplate() {
    const templateId = getQueryParam('tpl');
    if (templateId) {
      this.currentTemplateId = templateId;
      this.loadTemplate(templateId);
    }
  }

  showEmptyState() {
    const container = DOMManager.getElement('templateContainer');
    if (container) {
      container.innerHTML = `
        <div class="cb-editor-empty">
          <p>Select a template to edit or create a new one.</p>
        </div>
      `;
    }
  }

  showErrorState(message) {
    const container = DOMManager.getElement('templateContainer');
    if (container) {
      container.innerHTML = `
        <div class="cb-editor-error">
          <p>${escapeHTML(message)}</p>
        </div>
      `;
    }
  }

  async updateLivePreview(templateId) {
    const previewFrame = DOMManager.getElement('previewFrame');
    const htmlBox = DOMManager.getElement('previewHtml');

    if (!previewFrame || !htmlBox) return;

    try {
      const response = await this.api.post(
        `/templates/${templateId}/preview`,
        {}
      );

      const html = extractData.html(response);

      // Update preview frame
      try {
        const doc =
          previewFrame.contentDocument || previewFrame.contentWindow?.document;
        if (doc) {
          doc.open();
          doc.write(html);
          doc.close();
        }
      } catch (error) {
        console.warn('Could not update preview frame:', error);
      }

      // Update HTML box
      htmlBox.value = html;
      this.applyPreviewMode(this.getPreviewMode());
    } catch (error) {
      console.error('Error updating preview:', error);
    }
  }

  getPreviewMode() {
    const checked = DOMManager.getElements('previewModeInputs').find(
      (input) => input.checked
    );
    return checked?.value || 'rendered';
  }

  applyPreviewMode(mode) {
    const frameWrap = DOMManager.getElement('previewRenderedWrap');
    const htmlBox = DOMManager.getElement('previewHtml');
    const copyBtn = DOMManager.getElement('copyHtmlButton');

    if (!frameWrap || !htmlBox || !copyBtn) return;

    const isHtml = mode === 'html';
    frameWrap.style.display = isHtml ? 'none' : '';
    htmlBox.style.display = isHtml ? '' : 'none';
    copyBtn.style.display = isHtml ? '' : 'none';
  }
}

// Post management class
class PostManager {
  constructor() {
    this.api = new ApiClient();

    // Only initialize if we're on the templates page (where post selection exists)
    if (hasTemplateFunctionality()) {
      this.initialize();
    }
  }

  initialize() {
    this.setupPostTypeSelection();
    this.loadInitialPosts();
    this.setupPostSelection();
  }

  setupPostTypeSelection() {
    const postTypeEl = DOMManager.getElement('postTypeSelect');
    if (!postTypeEl) return;

    postTypeEl.addEventListener('change', () => this.loadPosts());
  }

  async loadPosts() {
    const typeEl = DOMManager.getElement('postTypeSelect');
    const select = DOMManager.getElement('postsSelect');

    if (!typeEl || !select) return;

    const postType = typeEl.value;
    if (!postType) {
      select.innerHTML = '';
      return;
    }

    select.disabled = true;
    select.innerHTML = '<option>Loading…</option>';

    try {
      const response = await this.api.get(
        `/posts?post_type=${encodeURIComponent(postType)}`
      );
      const items = extractData.items(response);

      select.innerHTML = this.renderOptions(items);
    } catch (error) {
      console.error('Error loading posts:', error);
      select.innerHTML = '';
    } finally {
      select.disabled = false;
    }

    this.renderSelectedChips(select);
  }

  renderOptions(items) {
    if (!Array.isArray(items)) return '';

    return items
      .map(
        (item) => `
        <option value="${escapeHTML(String(item.id))}">
          ${escapeHTML(item.label)}
        </option>
      `
      )
      .join('');
  }

  renderSelectedChips(select) {
    const chipsWrap = DOMManager.getElement('selectedPostsChips');
    if (!chipsWrap) return;

    const items = this.getSelectedItems(select);
    chipsWrap.innerHTML = items
      .map(
        (item) => `
        <span class="cb-chip" draggable="true" data-id="${escapeHTML(
          String(item.id)
        )}">
          ${escapeHTML(item.label)}
          <span class="cb-chip-remove" title="Remove" aria-label="Remove">×</span>
        </span>
      `
      )
      .join('');
  }

  getSelectedItems(select) {
    if (!select) return [];
    return Array.from(select.selectedOptions || []).map((option) => ({
      id: option.value,
      label: option.textContent || '',
    }));
  }

  loadInitialPosts() {
    const postsSelect = DOMManager.getElement('postsSelect');
    if (postsSelect) {
      this.loadPosts();
    }
  }

  setupPostSelection() {
    on('change', '#campaignbridge-posts', () => {
      const select = DOMManager.getElement('postsSelect');
      this.renderSelectedChips(select);
    });

    on(
      'click',
      '#cb-selected-posts-chips .cb-chip-remove',
      (event, element) => {
        const chip = element.closest('.cb-chip');
        if (!chip) return;

        const id = chip.getAttribute('data-id');
        const select = DOMManager.getElement('postsSelect');

        if (select && id) {
          Array.from(select.options).forEach((option) => {
            if (option.value === id) option.selected = false;
          });
          select.dispatchEvent(new Event('change', { bubbles: true }));
        }
      }
    );
  }
}

// Mailchimp integration class
class MailchimpIntegration {
  constructor() {
    this.api = new ApiClient();

    // Only initialize if we're on the settings page (where Mailchimp config exists)
    if (hasSettingsFunctionality()) {
      this.initialize();
    }
  }

  initialize() {
    this.setupAudienceReset();
    this.setupTemplateReset();
    this.autoPopulateData();
    this.setupVerification();
  }

  setupAudienceReset() {
    on('click', '#campaignbridge-fetch-audiences', async (event, button) => {
      const select = DOMManager.getElement('mailchimpAudienceSelect');
      if (!select) return;

      button.disabled = true;
      button.textContent = 'Resetting…';
      select.value = '';

      try {
        const response = await this.api.get('/mailchimp/audiences?refresh=1');
        const items = extractData.items(response);

        if (items.length) {
          this.populateSelect(select, items);
        }
      } finally {
        button.disabled = false;
        button.textContent = 'Reset Audiences';
        this.toggleResetVisibility();
      }
    });
  }

  setupTemplateReset() {
    on('click', '#campaignbridge-fetch-templates', async (event, button) => {
      const select = DOMManager.getElement('mailchimpTemplatesSelect');
      if (!select) return;

      button.disabled = true;
      button.textContent = 'Resetting…';
      select.value = '';

      try {
        const response = await this.api.get('/mailchimp/templates?refresh=1');
        const items = extractData.items(response);

        if (items.length) {
          this.populateSelect(select, items);
        }
      } finally {
        button.disabled = false;
        button.textContent = 'Reset Templates';
        this.toggleResetVisibility();
      }
    });
  }

  populateSelect(select, items) {
    if (!select) return;

    const current = select.value;
    let html = '<option value="">—</option>';

    items.forEach((item) => {
      html += `
        <option value="${escapeHTML(String(item.id))}">
          ${escapeHTML(item.name || item.label)}
        </option>
      `;
    });

    select.innerHTML = html;

    if (current) {
      select.value = current;
    }

    this.toggleResetVisibility();
  }

  toggleResetVisibility() {
    const audSel = DOMManager.getElement('mailchimpAudienceSelect');
    const audBtn = DOMManager.getElement('fetchAudiencesButton');

    if (audSel && audBtn) {
      audBtn.style.display = audSel.value ? '' : 'none';
    }

    const tplSel = DOMManager.getElement('mailchimpTemplatesSelect');
    const tplBtn = DOMManager.getElement('fetchTemplatesButton');

    if (tplSel && tplBtn) {
      tplBtn.style.display = tplSel.value ? '' : 'none';
    }
  }

  async autoPopulateData() {
    // Auto-populate audiences
    const audSel = DOMManager.getElement('mailchimpAudienceSelect');
    if (audSel && !audSel.value) {
      try {
        const response = await this.api.get('/mailchimp/audiences');
        const items = extractData.items(response);
        if (items.length) {
          this.populateSelect(audSel, items);
          this.toggleResetVisibility();
        }
      } catch (error) {
        console.warn('Could not auto-populate audiences:', error);
      }
    }

    // Auto-populate templates
    const tplSel = DOMManager.getElement('mailchimpTemplatesSelect');
    if (tplSel && !tplSel.value) {
      try {
        const response = await this.api.get('/mailchimp/templates');
        const items = extractData.items(response);
        if (items.length) {
          this.populateSelect(tplSel, items);
          this.toggleResetVisibility();
        }
      } catch (error) {
        console.warn('Could not auto-populate templates:', error);
      }
    }
  }

  setupVerification() {
    const apiInput = DOMManager.getElement('mailchimpApiKey');
    if (!apiInput) return;

    // Auto-verify if key exists
    if (apiInput.value) {
      this.verifyMailchimp();
    }

    // Setup verification on input change
    ['input', 'change'].forEach((eventType) => {
      apiInput.addEventListener(eventType, () => {
        if (this.verifyTimer) clearTimeout(this.verifyTimer);
        this.verifyTimer = setTimeout(() => this.verifyMailchimp(), 600);
      });
    });
  }

  async verifyMailchimp() {
    const status = DOMManager.getElement('verifyStatus');
    if (!status) return;

    this.setVerifyStatus('loading');

    try {
      const response = await this.api.post('/mailchimp/verify');

      if (response?.ok) {
        this.setVerifyStatus('ok', 'Connected');
      } else {
        const message = response?.data?.message || 'Not connected';
        this.setVerifyStatus('err', message);
      }
    } catch (error) {
      this.setVerifyStatus('err', 'Not connected');
    }
  }

  setVerifyStatus(state, message) {
    const status = DOMManager.getElement('verifyStatus');
    if (!status) return;

    status.classList.remove('cb-status-ok', 'cb-status-err');

    if (state === 'loading') {
      status.innerHTML = `
        <span class="spinner is-active cb-inline-spinner"></span> Verifying…
      `;
      return;
    }

    const isOk = state === 'ok';
    const text = message || (isOk ? 'Connected' : 'No connection');

    status.classList.toggle('cb-status-ok', isOk);
    status.classList.toggle('cb-status-err', !isOk);

    status.innerHTML = `
      <span class="cb-pill">${isOk ? '✔' : '✖'}</span>
      ${escapeHTML(text)}
    `;
  }
}

// Preview management class
class PreviewManager {
  constructor() {
    // Only initialize if we're on the templates page (where preview functionality exists)
    if (hasTemplateFunctionality()) {
      this.setupPreviewModeHandling();
      this.setupCopyButton();
      this.setupRefreshButton();
    }
  }

  setupPreviewModeHandling() {
    document.addEventListener('change', (event) => {
      if (event.target?.name === 'cbPreviewMode') {
        this.applyPreviewMode(this.getPreviewMode());
      }
    });
  }

  getPreviewMode() {
    const checked = DOMManager.getElements('previewModeInputs').find(
      (input) => input.checked
    );
    return checked?.value || 'rendered';
  }

  applyPreviewMode(mode) {
    const frameWrap = DOMManager.getElement('previewRenderedWrap');
    const htmlBox = DOMManager.getElement('previewHtml');
    const copyBtn = DOMManager.getElement('copyHtmlButton');

    if (!frameWrap || !htmlBox || !copyBtn) return;

    const isHtml = mode === 'html';
    frameWrap.style.display = isHtml ? 'none' : '';
    htmlBox.style.display = isHtml ? '' : 'none';
    copyBtn.style.display = isHtml ? '' : 'none';
  }

  setupCopyButton() {
    const copyBtn = DOMManager.getElement('copyHtmlButton');
    if (copyBtn) {
      copyBtn.onclick = () => {
        const htmlBox = DOMManager.getElement('previewHtml');
        if (htmlBox) {
          htmlBox.select();
          document.execCommand('copy');
        }
      };
    }
  }

  setupRefreshButton() {
    const refreshBtn = DOMManager.getElement('refreshPreviewButton');
    if (refreshBtn) {
      refreshBtn.onclick = () => {
        const templateId = getQueryParam('tpl');
        if (templateId) {
          // Trigger preview update
          const event = new CustomEvent('refreshPreview', {
            detail: { templateId },
          });
          document.dispatchEvent(event);
        }
      };
    }
  }
}

// Main application class
class CampaignBridgeApp {
  constructor() {
    try {
      const currentPage = getCampaignBridgePage();

      if (currentPage) {
        console.log(`CampaignBridge: Initializing on ${currentPage} page`);

        // Initialize managers based on current page
        if (hasTemplateFunctionality()) {
          this.templateManager = new TemplateManager();
          this.postManager = new PostManager();
          this.previewManager = new PreviewManager();
          this.emailExportService = new EmailExportService();
        }

        if (hasSettingsFunctionality()) {
          this.mailchimpIntegration = new MailchimpIntegration();
        }

        console.log('🚀 CampaignBridge initialized successfully!');
      } else {
        console.log(
          'CampaignBridge: Not on a relevant page, skipping initialization'
        );
      }
    } catch (error) {
      ErrorHandler.handleError(
        error,
        'App Initialization',
        'Failed to initialize CampaignBridge'
      );
    }
  }
}

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
