import { ApiClient } from '../services/ApiClient.js';
import { DOMManager } from '../utils/DOMManager.js';
import { escapeHTML } from '../utils/helpers.js';

// Response data extractors
const extractData = {
  items: (resp) => resp?.items ?? resp?.data?.items ?? [],
};

// Post management class
export class PostManager {
  constructor() {
    this.api = new ApiClient();

    // Only initialize if we're on the templates page (where post selection exists)
    if (this.hasTemplateFunctionality()) {
      this.initialize();
    }
  }

  hasTemplateFunctionality() {
    const screen = document.body.className;
    return screen.includes('toplevel_page_campaignbridge');
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
    this.on('change', '#campaignbridge-posts', () => {
      const select = DOMManager.getElement('postsSelect');
      this.renderSelectedChips(select);
    });

    this.on(
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

  // Event delegation utility
  on(eventName, selector, handler) {
    document.addEventListener(eventName, (event) => {
      const target = event.target?.closest(selector);
      if (target) handler(event, target);
    });
  }
}
