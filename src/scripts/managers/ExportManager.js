/**
 * Export Manager - Handles email export functionality
 * Follows Single Responsibility Principle - only handles exports
 *
 * @package CampaignBridge
 */

import { BaseManager } from '../core/BaseManager.js';

export class ExportManager extends BaseManager {
  constructor(serviceContainer) {
    super(serviceContainer);
    this.exportQueue = [];
    this.isExporting = false;
  }

  async doInitialize() {
    if (!this.isPageSupported('templates')) {
      return;
    }

    this.setupExportHandlers();
  }

  setupExportHandlers() {
    // Listen for export button clicks from email template blocks
    this.addEventListener('click', '.cb-export-html', (event, button) => {
      this.handleExportClick(event, button);
    });

    // Listen for preview button clicks
    this.addEventListener('click', '.cb-preview-email', (event, button) => {
      this.handlePreviewClick(event, button);
    });
  }

  async handleExportClick(event, button) {
    try {
      button.disabled = true;
      button.textContent = 'Generating...';

      const templateBlock = button.closest('.cb-email-template');
      if (!templateBlock) {
        throw new Error('Email template block not found');
      }

      const blockContent = this.extractBlockContent(templateBlock);
      const emailHTML = await this.generateEmailHTML(blockContent);

      await this.copyToClipboard(emailHTML);
      this.showNotification('Email HTML copied to clipboard!', 'success');
    } catch (error) {
      this.getService('errorHandler').handleError(
        error,
        'ExportManager.handleExportClick',
        'Export failed'
      );
    } finally {
      button.disabled = false;
      button.textContent = 'Export HTML';
    }
  }

  async handlePreviewClick(event, button) {
    try {
      button.disabled = true;
      button.textContent = 'Generating...';

      const templateBlock = button.closest('.cb-email-template');
      if (!templateBlock) {
        throw new Error('Email template block not found');
      }

      const blockContent = this.extractBlockContent(templateBlock);
      const emailHTML = await this.generateEmailHTML(blockContent);

      this.openPreviewWindow(emailHTML);
    } catch (error) {
      this.getService('errorHandler').handleError(
        error,
        'ExportManager.handlePreviewClick',
        'Preview failed'
      );
    } finally {
      button.disabled = false;
      button.textContent = 'Preview Email';
    }
  }

  extractBlockContent(templateBlock) {
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
    return blockElement.innerHTML;
  }

  async generateEmailHTML(blockData) {
    try {
      // Try to use the EmailGenerator service first
      const emailGenerator = this.getService('emailGenerator');
      if (
        emailGenerator &&
        typeof emailGenerator.generateEmailHTML === 'function'
      ) {
        return await emailGenerator.generateEmailHTML(
          blockData.blocks,
          blockData.attributes
        );
      }

      // Fallback to REST API
      return await this.generateEmailViaAPI(blockData);
    } catch (error) {
      // Final fallback to local generation
      return this.generateFallbackHTML(blockData);
    }
  }

  async generateEmailViaAPI(blockData) {
    const response = await fetch('/wp-json/campaignbridge/v1/email/generate', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': window.wpApiSettings?.nonce || '',
      },
      body: JSON.stringify(blockData),
    });

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}: ${response.statusText}`);
    }

    const result = await response.json();
    return result.html;
  }

  generateFallbackHTML(blockData) {
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

    setTimeout(() => {
      if (notification.parentNode) {
        notification.parentNode.removeChild(notification);
      }
    }, 3000);
  }
}
