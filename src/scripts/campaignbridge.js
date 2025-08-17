/**
 * Admin UI interactions for CampaignBridge.
 *
 * Modern ES6 implementation with:
 * - DOM querying and event delegation
 * - REST API integration
 * - Safe HTML escaping when rendering dynamic content
 * - Template management and preview
 */

// Utility functions
const qs = (selector, root = document) => root.querySelector(selector);
const qsa = (selector, root = document) =>
  Array.from(root.querySelectorAll(selector));

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
  sections: (resp) => resp?.sections ?? resp?.data?.sections ?? [],
  slots: (resp) => resp?.slots ?? resp?.data?.slots ?? [],
  html: (resp) => resp?.html ?? resp?.data?.html ?? '',
};

// Template management class
class TemplateManager {
  constructor() {
    this.api = new ApiClient();
    this.currentTemplateId = null;
    this.initialize();
  }

  initialize() {
    this.setupTemplateSelection();
    this.setupNewTemplateButton();
    this.loadInitialTemplate();
  }

  setupTemplateSelection() {
    const templateSelect = qs('#campaignbridge-template-select');
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
    const newButton = qs('#campaignbridge-new-template');
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
    const templateSelect = qs('#campaignbridge-template-select');
    if (!templateSelect) return;

    const option = document.createElement('option');
    option.value = template.id;
    option.textContent = template.title?.rendered || 'New Email Template';
    templateSelect.appendChild(option);
  }

  selectTemplate(templateId) {
    const templateSelect = qs('#campaignbridge-template-select');
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
    const container = qs('#campaignbridge-template-container');
    if (container) {
      container.innerHTML = `
        <div class="cb-editor-empty">
          <p>Select a template to edit or create a new one.</p>
        </div>
      `;
    }
  }

  showErrorState(message) {
    const container = qs('#campaignbridge-template-container');
    if (container) {
      container.innerHTML = `
        <div class="cb-editor-error">
          <p>${escapeHTML(message)}</p>
        </div>
      `;
    }
  }

  async updateLivePreview(templateId) {
    const previewFrame = qs('#cb-preview-frame');
    const htmlBox = qs('#cb-preview-html');

    if (!previewFrame || !htmlBox) return;

    try {
      const response = await this.api.post(`/templates/${templateId}/preview`, {
        slots_map: {},
      });

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
    const checked = qs('input[name="cbPreviewMode"]:checked');
    return checked?.value || 'rendered';
  }

  applyPreviewMode(mode) {
    const frameWrap = qs('#cb-preview-rendered-wrap');
    const htmlBox = qs('#cb-preview-html');
    const copyBtn = qs('#cb-copy-html');

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
    this.initialize();
  }

  initialize() {
    this.setupPostTypeSelection();
    this.loadInitialPosts();
    this.setupPostSelection();
  }

  setupPostTypeSelection() {
    const postTypeEl = qs('#campaignbridge-post-type');
    if (!postTypeEl) return;

    postTypeEl.addEventListener('change', () => this.loadPosts());
  }

  async loadPosts() {
    const typeEl = qs('#campaignbridge-post-type');
    const select = qs('#campaignbridge-posts');

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
    const chipsWrap = qs('#cb-selected-posts-chips');
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
    const postsSelect = qs('#campaignbridge-posts');
    if (postsSelect) {
      this.loadPosts();
    }
  }

  setupPostSelection() {
    on('change', '#campaignbridge-posts', () => {
      const select = qs('#campaignbridge-posts');
      this.renderSelectedChips(select);
    });

    on(
      'click',
      '#cb-selected-posts-chips .cb-chip-remove',
      (event, element) => {
        const chip = element.closest('.cb-chip');
        if (!chip) return;

        const id = chip.getAttribute('data-id');
        const select = qs('#campaignbridge-posts');

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
    this.initialize();
  }

  initialize() {
    this.setupSectionsButton();
    this.setupAudienceReset();
    this.setupTemplateReset();
    this.autoPopulateData();
    this.setupVerification();
  }

  setupSectionsButton() {
    on('click', '#campaignbridge-show-sections', async (event, button) => {
      const box = qs('#campaignbridge-sections');
      if (!box) return;

      button.disabled = true;
      button.textContent = 'Loading…';

      try {
        const response = await this.api.get('/mailchimp/sections');
        const sections = extractData.sections(response);

        if (sections?.length) {
          this.renderSections(sections);
          this.renderMapping(sections);
        } else {
          const message =
            response?.message ||
            response?.data?.message ||
            'No sections found.';
          box.innerHTML = `<p>${escapeHTML(message)}</p>`;
        }
      } catch (error) {
        box.innerHTML = '<p>Failed to load sections.</p>';
      } finally {
        box.innerHTML = '<p>No sections found.</p>';
        button.disabled = false;
        button.textContent = 'Show Mailchimp Template Sections';
      }
    });
  }

  renderSections(sections) {
    const box = qs('#campaignbridge-sections');
    if (!box) return;

    const html = `
      <ul style="margin:0;">
        ${sections
          .map((section) => `<li><code>${escapeHTML(section)}</code></li>`)
          .join('')}
      </ul>
    `;

    box.innerHTML = html;
    box.style.display = 'block';
  }

  renderMapping(sections) {
    const wrap = qs('#campaignbridge-mapping');
    const body = qs('#campaignbridge-mapping-body');

    if (!wrap || !body) return;

    const items = this.getPostItems();
    if (!items.length) return;

    const options = this.renderOptions(items);
    const rows = sections
      .map(
        (section) => `
        <tr data-key="${escapeHTML(section)}">
          <td>
            <label class="screen-reader-text" for="map-${escapeHTML(
              section
            )}">Slot key</label>
            <code>${escapeHTML(section)}</code>
          </td>
          <td>
            <select id="map-${escapeHTML(
              section
            )}" name="sections_map[${escapeHTML(section)}]" style="width:100%">
              ${options}
            </select>
          </td>
        </tr>
      `
      )
      .join('');

    body.innerHTML = rows;
    wrap.style.display = 'block';
  }

  renderOptions(items) {
    return `
      <option value="">— Select a post —</option>
      ${items
        .map(
          (item) => `
        <option value="${escapeHTML(String(item.id))}">
          ${escapeHTML(item.label)}
        </option>
      `
        )
        .join('')}
    `;
  }

  getPostItems() {
    const postSelect = qs('#campaignbridge-posts');
    if (!postSelect) return [];

    return Array.from(postSelect.options).map((option) => ({
      id: option.value,
      label: option.textContent,
    }));
  }

  setupAudienceReset() {
    on('click', '#campaignbridge-fetch-audiences', async (event, button) => {
      const select = qs('#campaignbridge-mailchimp-audience');
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
      const select = qs('#campaignbridge-mailchimp-templates');
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
    const audSel = qs('#campaignbridge-mailchimp-audience');
    const audBtn = qs('#campaignbridge-fetch-audiences');

    if (audSel && audBtn) {
      audBtn.style.display = audSel.value ? '' : 'none';
    }

    const tplSel = qs('#campaignbridge-mailchimp-templates');
    const tplBtn = qs('#campaignbridge-fetch-templates');

    if (tplSel && tplBtn) {
      tplBtn.style.display = tplSel.value ? '' : 'none';
    }
  }

  async autoPopulateData() {
    // Auto-populate audiences
    const audSel = qs('#campaignbridge-mailchimp-audience');
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
    const tplSel = qs('#campaignbridge-mailchimp-templates');
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
    const apiInput = qs('#campaignbridge-mailchimp-api-key');
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
    const status = qs('#campaignbridge-verify-status');
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
    const status = qs('#campaignbridge-verify-status');
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
    const checked = qs('input[name="cbPreviewMode"]:checked');
    return checked?.value || 'rendered';
  }

  applyPreviewMode(mode) {
    const frameWrap = qs('#cb-preview-rendered-wrap');
    const htmlBox = qs('#cb-preview-html');
    const copyBtn = qs('#cb-copy-html');

    if (!frameWrap || !htmlBox || !copyBtn) return;

    const isHtml = mode === 'html';
    frameWrap.style.display = isHtml ? 'none' : '';
    htmlBox.style.display = isHtml ? '' : 'none';
    copyBtn.style.display = isHtml ? '' : 'none';
  }

  setupCopyButton() {
    const copyBtn = qs('#cb-copy-html');
    if (copyBtn) {
      copyBtn.onclick = () => {
        const htmlBox = qs('#cb-preview-html');
        if (htmlBox) {
          htmlBox.select();
          document.execCommand('copy');
        }
      };
    }
  }

  setupRefreshButton() {
    const refreshBtn = qs('#cb-refresh-preview');
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
    this.templateManager = new TemplateManager();
    this.postManager = new PostManager();
    this.mailchimpIntegration = new MailchimpIntegration();
    this.previewManager = new PreviewManager();

    console.log('🚀 CampaignBridge initialized successfully!');
  }
}

// Initialize the application when DOM is ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => {
    new CampaignBridgeApp();
  });
} else {
  new CampaignBridgeApp();
}
