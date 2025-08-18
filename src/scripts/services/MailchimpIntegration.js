import { DOMManager } from '../utils/DOMManager.js';
import { escapeHTML } from '../utils/helpers.js';
import { ApiClient } from './ApiClient.js';

// Response data extractors
const extractData = {
  items: (resp) => resp?.items ?? resp?.data?.items ?? [],
};

// Mailchimp integration class
export class MailchimpIntegration {
  constructor() {
    this.api = new ApiClient();

    // Only initialize if we're on the settings page (where Mailchimp config exists)
    if (this.hasSettingsFunctionality()) {
      this.initialize();
    }
  }

  hasSettingsFunctionality() {
    const screen = document.body.className;
    return screen.includes('campaignbridge_page_campaignbridge-settings');
  }

  initialize() {
    this.setupAudienceReset();
    this.setupTemplateReset();
    this.autoPopulateData();
    this.setupVerification();
  }

  setupAudienceReset() {
    this.on(
      'click',
      '#campaignbridge-fetch-audiences',
      async (event, button) => {
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
      }
    );
  }

  setupTemplateReset() {
    this.on(
      'click',
      '#campaignbridge-fetch-templates',
      async (event, button) => {
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
      }
    );
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

  // Event delegation utility
  on(eventName, selector, handler) {
    document.addEventListener(eventName, (event) => {
      const target = event.target?.closest(selector);
      if (target) handler(event, target);
    });
  }
}
