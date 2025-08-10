/* global CampaignBridge */
/**
 * Admin UI interactions for CampaignBridge.
 *
 * Replaces jQuery usage with small vanilla helpers for:
 * - DOM querying and event delegation
 * - POST requests to WordPress ajax
 * - Safe HTML escaping when rendering dynamic content
 */
(function () {
  'use strict';

  /**
   * Query a single element.
   * @param {string} selector CSS selector
   * @param {ParentNode} [root=document] Search root
   * @returns {Element|null}
   */
  const qs = (selector, root) => (root || document).querySelector(selector);

  /**
   * Query all matching elements as an Array.
   * @param {string} selector CSS selector
   * @param {ParentNode} [root=document] Search root
   * @returns {Element[]}
   */
  const qsa = (selector, root) =>
    Array.from((root || document).querySelectorAll(selector));

  /**
   * Delegate events from document to elements matching a selector.
   * @param {string} eventName Event type (e.g., 'click')
   * @param {string} selector CSS selector to match targets
   * @param {(event: Event, target: Element) => void} handler Handler invoked with the original event and the matched target
   */
  const on = (eventName, selector, handler) => {
    document.addEventListener(eventName, (event) => {
      const target = event.target && event.target.closest(selector);
      if (target) handler(event, target);
    });
  };

  /**
   * Escape a string for safe HTML insertion.
   * @param {string|number|null|undefined} value Value to escape
   * @returns {string} Escaped HTML string
   */
  const escapeHTML = (value) => {
    const div = document.createElement('div');
    div.textContent = value == null ? '' : String(value);
    return div.innerHTML;
  };

  /**
   * POST to WordPress admin-ajax with URL-encoded body.
   * @param {Record<string, string>} data Key-value payload
   * @returns {Promise<any>} Parsed JSON response
   */
  const post = (data) =>
    fetch(CampaignBridge.ajaxUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
      },
      credentials: 'same-origin',
      body: new URLSearchParams(data).toString(),
    }).then((res) => res.json());

  /**
   * Build <option> HTML for a list of items.
   * @param {{id: string|number, label: string}[]} items Items to render
   * @returns {string}
   */
  const renderOptions = (items) =>
    items
      .map(
        (item) =>
          '<option value="' +
          escapeHTML(String(item.id)) +
          '">' +
          escapeHTML(item.label) +
          '</option>'
      )
      .join('');

  /**
   * Fetch posts for a given post type.
   * @param {string} postType WP post type slug
   * @returns {Promise<any>}
   */
  const fetchPosts = (postType) =>
    post({
      action: 'campaignbridge_fetch_posts',
      nonce: CampaignBridge.nonce,
      post_type: postType,
    });

  /**
   * Render the section-to-post mapping UI.
   * @param {string[]} sections Section keys from Mailchimp template
   * @param {{id: string|number, label: string}[]} items Post items to choose from
   */
  const renderMapping = (sections, items) => {
    const wrap = document.getElementById('campaignbridge-mapping');
    const body = document.getElementById('campaignbridge-mapping-body');
    if (!wrap || !body) return;
    if (!sections || !sections.length) {
      wrap.style.display = 'none';
      return;
    }
    let optHtml = '<option value="">— Select a post —</option>';
    items.forEach((it) => {
      optHtml +=
        '<option value="' +
        escapeHTML(String(it.id)) +
        '">' +
        escapeHTML(it.label) +
        '</option>';
    });
    let rows = '';
    sections.forEach((s) => {
      const safe = escapeHTML(s);
      rows +=
        '<tr><td><code>' +
        safe +
        '</code></td><td><select name="sections_map[' +
        safe +
        ']" style="width:100%">' +
        optHtml +
        '</select></td></tr>';
    });
    body.innerHTML = rows;
    wrap.style.display = 'block';
  };

  /**
   * Replace options of a <select> with provided items while preserving existing selection if possible.
   * @param {HTMLSelectElement} selectEl Select element to populate
   * @param {{id: string|number, label?: string, name?: string}[]} items Items to render
   */
  const populateSelect = (selectEl, items) => {
    if (!selectEl) return;
    const current = selectEl.value;
    let html = '<option value="">—</option>';
    items.forEach((it) => {
      html +=
        '<option value="' +
        escapeHTML(String(it.id)) +
        '">' +
        escapeHTML(it.name || it.label) +
        '</option>';
    });
    selectEl.innerHTML = html;
    if (current) {
      selectEl.value = current;
    }
    toggleResetVisibility();
  };

  /**
   * Show or hide Mailchimp reset buttons based on selection state.
   */
  const toggleResetVisibility = () => {
    const audSel = qs('#campaignbridge-mailchimp-audience');
    const audBtn = qs('#campaignbridge-fetch-audiences');
    if (audSel && audBtn) {
      if (audSel.value) {
        audBtn.style.display = '';
      } else {
        audBtn.style.display = 'none';
      }
    }
    const tplSel = qs('#campaignbridge-mailchimp-templates');
    const tplBtn = qs('#campaignbridge-fetch-templates');
    if (tplSel && tplBtn) {
      if (tplSel.value) {
        tplBtn.style.display = '';
      } else {
        tplBtn.style.display = 'none';
      }
    }
  };

  /**
   * Load posts into the posts <select> based on current post type.
   */
  const loadPosts = async () => {
    const typeEl = qs('#campaignbridge-post-type');
    const select = qs('#campaignbridge-posts');
    if (!typeEl || !select) return;
    const postType = typeEl.value;
    select.disabled = true;
    select.innerHTML = '<option>Loading…</option>';
    try {
      const resp = await fetchPosts(postType);
      select.innerHTML =
        resp && resp.success && resp.data && resp.data.items
          ? renderOptions(resp.data.items)
          : '';
    } catch (e) {
      select.innerHTML = '';
    } finally {
      select.disabled = false;
    }
  };

  /**
   * Update the inline Mailchimp verification status UI.
   * @param {'loading'|'ok'|'err'} state Status code
   * @param {string} [message] Optional message to display
   */
  const setVerifyStatus = (state, message) => {
    const status = qs('#campaignbridge-verify-status');
    if (!status) return;
    if (state === 'loading') {
      status.classList.remove('cb-status-ok');
      status.classList.remove('cb-status-err');
      status.innerHTML =
        '<span class="spinner is-active cb-inline-spinner"></span> Verifying…';
      return;
    }
    const isOk = state === 'ok';
    const text = message || (isOk ? 'Connected' : 'No connection');
    status.classList.toggle('cb-status-ok', isOk);
    status.classList.toggle('cb-status-err', !isOk);
    status.innerHTML =
      '<span class="cb-pill">' +
      (isOk ? '✔' : '✖') +
      '</span>' +
      escapeHTML(text);
  };

  let verifyTimer = null;
  /**
   * Verify Mailchimp credentials via ajax and reflect the result in UI.
   */
  const verifyMailchimp = async () => {
    setVerifyStatus('loading');
    const apiInput = qs('#campaignbridge-mailchimp-api-key');
    const apiKey = apiInput ? apiInput.value || '' : '';
    try {
      const resp = await post({
        action: 'campaignbridge_verify_mailchimp',
        nonce: CampaignBridge.nonce,
        api_key: apiKey,
      });
      if (resp && resp.success) {
        setVerifyStatus('ok', 'Connected');
      } else {
        const msg =
          resp && resp.data && resp.data.message
            ? resp.data.message
            : 'Not connected';
        setVerifyStatus('err', msg);
      }
    } catch (e) {
      setVerifyStatus('err', 'Not connected');
    }
  };

  document.addEventListener('DOMContentLoaded', function () {
    const postTypeEl = qs('#campaignbridge-post-type');
    if (postTypeEl) {
      postTypeEl.addEventListener('change', loadPosts);
    }
    if (qs('#campaignbridge-posts')) {
      loadPosts();
    }

    toggleResetVisibility();

    on('click', '#campaignbridge-show-sections', (e, btn) => {
      const box = qs('#campaignbridge-sections');
      if (!box) return;
      btn.disabled = true;
      btn.textContent = 'Loading…';
      post({
        action: 'campaignbridge_fetch_sections',
        nonce: CampaignBridge.nonce,
      })
        .then((resp) => {
          if (resp && resp.success && resp.data && resp.data.sections) {
            let html = '<ul style="margin:0;">';
            resp.data.sections.forEach((k) => {
              html += '<li><code>' + escapeHTML(k) + '</code></li>';
            });
            html += '</ul>';
            box.innerHTML = html;
            box.style.display = 'block';

            const items = [];
            const postSelect = qs('#campaignbridge-posts');
            if (postSelect) {
              qsa('option', postSelect).forEach((opt) => {
                items.push({ id: opt.value, label: opt.textContent });
              });
            }
            renderMapping(resp.data.sections, items);
          } else if (resp && resp.data && resp.data.message) {
            box.innerHTML = '<p>' + escapeHTML(resp.data.message) + '</p>';
            box.style.display = 'block';
          } else {
            box.innerHTML = '<p>No sections found.</p>';
            box.style.display = 'block';
          }
        })
        .catch(() => {
          box.innerHTML = '<p>Failed to load sections.</p>';
          box.style.display = 'block';
        })
        .finally(() => {
          btn.disabled = false;
          btn.textContent = 'Show Mailchimp Template Sections';
        });
    });

    on('click', '#campaignbridge-fetch-audiences', (e, btn) => {
      const sel = qs('#campaignbridge-mailchimp-audience');
      if (!sel) return;
      btn.disabled = true;
      btn.textContent = 'Resetting…';
      sel.value = '';
      post({
        action: 'campaignbridge_fetch_mailchimp_audiences',
        nonce: CampaignBridge.nonce,
      })
        .then((resp) => {
          if (resp && resp.success && resp.data && resp.data.items) {
            populateSelect(sel, resp.data.items);
          }
        })
        .finally(() => {
          btn.disabled = false;
          btn.textContent = 'Reset Audiences';
          toggleResetVisibility();
        });
    });

    on('click', '#campaignbridge-fetch-templates', (e, btn) => {
      const sel = qs('#campaignbridge-mailchimp-templates');
      if (!sel) return;
      btn.disabled = true;
      btn.textContent = 'Resetting…';
      sel.value = '';
      post({
        action: 'campaignbridge_fetch_mailchimp_templates',
        nonce: CampaignBridge.nonce,
      })
        .then((resp) => {
          if (resp && resp.success && resp.data && resp.data.items) {
            populateSelect(sel, resp.data.items);
          }
        })
        .finally(() => {
          btn.disabled = false;
          btn.textContent = 'Reset Templates';
          toggleResetVisibility();
        });
    });

    (function autoPopulateMailchimp() {
      const audSel = qs('#campaignbridge-mailchimp-audience');
      if (audSel && (!audSel.value || audSel.value === '')) {
        post({
          action: 'campaignbridge_fetch_mailchimp_audiences',
          nonce: CampaignBridge.nonce,
        }).then((resp) => {
          if (resp && resp.success && resp.data && resp.data.items) {
            populateSelect(audSel, resp.data.items);
          }
          toggleResetVisibility();
        });
      }

      const tplSel = qs('#campaignbridge-mailchimp-templates');
      if (tplSel && (!tplSel.value || tplSel.value === '')) {
        post({
          action: 'campaignbridge_fetch_mailchimp_templates',
          nonce: CampaignBridge.nonce,
        }).then((resp) => {
          if (resp && resp.success && resp.data && resp.data.items) {
            populateSelect(tplSel, resp.data.items);
          }
          toggleResetVisibility();
        });
      }
    })();

    const audSelect = qs('#campaignbridge-mailchimp-audience');
    if (audSelect) audSelect.addEventListener('change', toggleResetVisibility);
    const tplSelect = qs('#campaignbridge-mailchimp-templates');
    if (tplSelect) tplSelect.addEventListener('change', toggleResetVisibility);

    (function autoVerifyMailchimp() {
      const apiInput = qs('#campaignbridge-mailchimp-api-key');
      if (apiInput && apiInput.value) {
        verifyMailchimp();
      }
      if (apiInput) {
        ['input', 'change'].forEach((ev) => {
          apiInput.addEventListener(ev, () => {
            if (verifyTimer) clearTimeout(verifyTimer);
            verifyTimer = setTimeout(verifyMailchimp, 600);
          });
        });
      }
    })();
  });
})();
