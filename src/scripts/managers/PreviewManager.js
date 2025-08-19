import { BaseManager } from '../core/BaseManager.js';

// Preview management class - handles only preview functionality
export class PreviewManager extends BaseManager {
  constructor(serviceContainer) {
    super(serviceContainer);
  }

  async doInitialize() {
    if (!this.isPageSupported('templates')) {
      return;
    }

    this.setupPreviewModeHandling();
    this.setupCopyButton();
    this.setupRefreshButton();
  }

  setupPreviewModeHandling() {
    document.addEventListener('change', (event) => {
      if (event.target?.name === 'cbPreviewMode') {
        this.applyPreviewMode(this.getPreviewMode());
      }
    });
  }

  getPreviewMode() {
    const checked = this.getElements('previewModeInputs').find(
      (input) => input.checked
    );
    return checked?.value || 'rendered';
  }

  applyPreviewMode(mode) {
    const frameWrap = this.getElement('previewRenderedWrap');
    const htmlBox = this.getElement('previewHtml');
    const copyBtn = this.getElement('copyHtmlButton');

    if (!frameWrap || !htmlBox || !copyBtn) return;

    const isHtml = mode === 'html';
    frameWrap.style.display = isHtml ? 'none' : '';
    htmlBox.style.display = isHtml ? '' : 'none';
    copyBtn.style.display = isHtml ? '' : 'none';
  }

  setupCopyButton() {
    const copyBtn = this.getElement('copyHtmlButton');
    if (copyBtn) {
      copyBtn.onclick = () => {
        const htmlBox = this.getElement('previewHtml');
        if (htmlBox) {
          htmlBox.select();
          document.execCommand('copy');
        }
      };
    }
  }

  setupRefreshButton() {
    const refreshBtn = this.getElement('refreshPreviewButton');
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
