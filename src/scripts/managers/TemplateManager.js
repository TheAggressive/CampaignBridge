import { BaseManager } from '../core/BaseManager.js';
import { escapeHTML, getQueryParam, setQueryParam } from '../utils/helpers.js';

// Response data extractors
const extractData = {
  html: (resp) => resp?.html ?? resp?.data?.html ?? '',
};

// Template management class - handles only template operations
export class TemplateManager extends BaseManager {
  constructor(serviceContainer) {
    super(serviceContainer);
    this.currentTemplateId = null;
  }

  async doInitialize() {
    if (!this.isPageSupported('templates')) {
      return;
    }

    this.setupTemplateSelection();
    this.setupNewTemplateButton();
    this.loadInitialTemplate();
  }

  setupTemplateSelection() {
    const templateSelect = this.getElement('templateSelect');
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
    const newButton = this.getElement('newTemplateButton');
    if (!newButton) return;

    newButton.addEventListener('click', async () => {
      try {
        const apiClient = this.getService('apiClient');
        const response = await apiClient.post('/templates', {
          title: 'New Email Template',
          content: '',
          status: 'draft',
        });

        if (response?.id) {
          this.addTemplateToSelect(response);
          this.selectTemplate(response.id);
        }
      } catch (error) {
        this.getService('errorHandler').handleError(
          error,
          'TemplateManager.setupNewTemplateButton',
          'Failed to create new template'
        );
      }
    });
  }

  addTemplateToSelect(template) {
    const templateSelect = this.getElement('templateSelect');
    if (!templateSelect) return;

    const option = document.createElement('option');
    option.value = template.id;
    option.textContent = template.title?.rendered || 'New Email Template';
    templateSelect.appendChild(option);
  }

  selectTemplate(templateId) {
    const templateSelect = this.getElement('templateSelect');
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
      this.getService('errorHandler').handleError(
        error,
        'TemplateManager.loadTemplate',
        'Failed to load template'
      );
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
    const container = this.getElement('templateContainer');
    if (container) {
      container.innerHTML = `
        <div class="cb-editor-empty">
          <p>Select a template to edit or create a new one.</p>
        </div>
      `;
    }
  }

  showErrorState(message) {
    const container = this.getElement('templateContainer');
    if (container) {
      container.innerHTML = `
        <div class="cb-editor-error">
          <p>${escapeHTML(message)}</p>
        </div>
      `;
    }
  }

  async updateLivePreview(templateId) {
    const previewFrame = this.getElement('previewFrame');
    const htmlBox = this.getElement('previewHtml');

    if (!previewFrame || !htmlBox) return;

    try {
      const apiClient = this.getService('apiClient');
      const response = await apiClient.post(
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
      this.getService('errorHandler').handleError(
        error,
        'TemplateManager.updateLivePreview',
        'Failed to update preview'
      );
    }
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
}
