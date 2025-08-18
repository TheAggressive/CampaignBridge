import { ApiClient } from '../services/ApiClient.js';
import { DOMManager } from '../utils/DOMManager.js';
import { escapeHTML, getQueryParam, setQueryParam } from '../utils/helpers.js';

// Response data extractors
const extractData = {
  html: (resp) => resp?.html ?? resp?.data?.html ?? '',
};

// Template management class
export class TemplateManager {
  constructor() {
    this.api = new ApiClient();
    this.currentTemplateId = null;

    // Only initialize if we're on the templates page
    if (this.hasTemplateFunctionality()) {
      this.initialize();
    }
  }

  hasTemplateFunctionality() {
    const screen = document.body.className;
    return screen.includes('toplevel_page_campaignbridge');
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
