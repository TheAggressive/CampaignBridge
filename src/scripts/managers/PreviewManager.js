import { DOMManager } from '../utils/DOMManager.js';

// Preview management class
export class PreviewManager {
  constructor() {
    // Only initialize if we're on the templates page (where preview functionality exists)
    if (this.hasTemplateFunctionality()) {
      this.setupPreviewModeHandling();
      this.setupCopyButton();
      this.setupRefreshButton();
    }
  }

  hasTemplateFunctionality() {
    const screen = document.body.className;
    return screen.includes('toplevel_page_campaignbridge');
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
        const templateId = this.getQueryParam('tpl');
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

  // URL parameter utilities
  getQueryParam(name) {
    const params = new URLSearchParams(window.location.search);
    return params.get(name);
  }
}
