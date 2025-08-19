import { BaseManager } from '../core/BaseManager.js';
import { escapeHTML } from '../utils/helpers.js';

// Response data extractors
const extractData = {
  items: (resp) => resp?.items ?? resp?.data?.items ?? [],
};

// Post management class - handles only post operations
export class PostManager extends BaseManager {
  constructor(serviceContainer) {
    super(serviceContainer);
  }

  async doInitialize() {
    if (!this.isPageSupported('templates')) {
      return;
    }

    this.setupPostTypeSelection();
    this.loadInitialPosts();
    this.setupPostSelection();
  }

  initialize() {
    this.setupPostTypeSelection();
    this.loadInitialPosts();
    this.setupPostSelection();
  }

  setupPostTypeSelection() {
    const postTypeEl = this.getElement('postTypeSelect');
    if (!postTypeEl) return;

    postTypeEl.addEventListener('change', () => this.loadPosts());
  }

  async loadPosts() {
    const typeEl = this.getElement('postTypeSelect');
    const select = this.getElement('postsSelect');

    if (!typeEl || !select) return;

    const postType = typeEl.value;
    if (!postType) {
      select.innerHTML = '';
      return;
    }

    select.disabled = true;
    select.innerHTML = '<option>Loading…</option>';

    try {
      const apiClient = this.getService('apiClient');
      const response = await apiClient.get(
        `/posts?post_type=${encodeURIComponent(postType)}`
      );
      const items = extractData.items(response);

      select.innerHTML = this.renderOptions(items);
    } catch (error) {
      this.getService('errorHandler').handleError(
        error,
        'PostManager.loadPosts',
        'Failed to load posts'
      );
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
    const chipsWrap = this.getElement('selectedPostsChips');
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
    const postsSelect = this.getElement('postsSelect');
    if (postsSelect) {
      this.loadPosts();
    }
  }

  setupPostSelection() {
    this.addEventListener('change', '#campaignbridge-posts', () => {
      const select = this.getElement('postsSelect');
      this.renderSelectedChips(select);
    });

    this.addEventListener(
      'click',
      '#cb-selected-posts-chips .cb-chip-remove',
      (event, element) => {
        const chip = element.closest('.cb-chip');
        if (!chip) return;

        const id = chip.getAttribute('data-id');
        const select = this.getElement('postsSelect');

        if (select && id) {
          Array.from(select.options).forEach((option) => {
            if (option.value === id) option.selected = false;
          });
          select.dispatchEvent(new Event('change', { bubbles: true }));
        }
      }
    );
  }

  // Event delegation utility - now inherited from BaseManager
}
