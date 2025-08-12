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

  const getQueryParam = (name) => {
    const params = new URLSearchParams(window.location.search);
    return params.get(name);
  };

  const setQueryParam = (name, value) => {
    const url = new URL(window.location.href);
    if (value == null || value === '') url.searchParams.delete(name);
    else url.searchParams.set(name, value);
    history.replaceState({}, '', url.toString());
  };

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
  const api = async (path, { method = 'GET', body } = {}) => {
    const url = `${
      window.wpApiSettings?.root || '/wp-json/'
    }campaignbridge/v1${path}`;
    const headers = { 'Content-Type': 'application/json' };
    if (window.wpApiSettings?.nonce)
      headers['X-WP-Nonce'] = window.wpApiSettings.nonce;
    const res = await fetch(url, {
      method,
      headers,
      credentials: 'same-origin',
      body: body ? JSON.stringify(body) : undefined,
    });
    const json = await res.json();
    return json;
  };

  // Normalize REST response shapes
  const itemsFrom = (resp) =>
    (resp && resp.items) || (resp && resp.data && resp.data.items) || [];
  const sectionsFrom = (resp) =>
    (resp && resp.sections) || (resp && resp.data && resp.data.sections) || [];
  const slotsFrom = (resp) =>
    (resp && resp.slots) || (resp && resp.data && resp.data.slots) || [];
  const htmlFrom = (resp) =>
    (resp && resp.html) || (resp && resp.data && resp.data.html) || '';

  const collectSlotsMap = () => {
    const map = {};
    qsa('#campaignbridge-mapping-body select').forEach((sel) => {
      const name = sel.getAttribute('name') || '';
      const match = name.match(/sections_map\[(.+)\]/);
      if (match) {
        map[match[1]] = sel.value ? Number(sel.value) : 0;
      }
    });
    return map;
  };

  const ensurePreviewBox = () => {
    let previewBox = qs('#campaignbridge-preview');
    let previewArea = qs('#campaignbridge-preview-html');
    const mappingWrap = qs('#campaignbridge-mapping');
    if (!previewBox && mappingWrap) {
      previewBox = document.createElement('div');
      previewBox.id = 'campaignbridge-preview';
      previewBox.className = 'cb-preview-box';
      previewBox.style.marginTop = '16px';
      previewBox.innerHTML =
        '<p><button type="button" class="button" id="campaignbridge-preview-btn">Preview Email</button></p><div id="campaignbridge-preview-html" style="border:1px solid #dcdcde;background:#fff;padding:16px;max-height:480px;overflow:auto;"></div>';
      mappingWrap.parentNode.insertBefore(previewBox, mappingWrap.nextSibling);
      previewArea = qs('#campaignbridge-preview-html');
    }
    return previewArea;
  };

  const findMappingSelectByKey = (key) => {
    const selects = qsa('#campaignbridge-mapping-body select');
    for (const sel of selects) {
      const name = sel.getAttribute('name') || '';
      if (name === `sections_map[${key}]`) return sel;
    }
    return null;
  };

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

  const selectedItemsFromSelect = (selectEl) => {
    if (!selectEl) return [];
    return Array.from(selectEl.selectedOptions || []).map((o) => ({
      id: o.value,
      label: o.textContent || '',
    }));
  };

  const renderSelectedChips = (selectEl) => {
    const chipsWrap = qs('#cb-selected-posts-chips');
    if (!chipsWrap) return;
    const items = selectedItemsFromSelect(selectEl);
    chipsWrap.innerHTML = items
      .map(
        (it) =>
          '<span class="cb-chip" draggable="true" data-id="' +
          escapeHTML(String(it.id)) +
          '">' +
          escapeHTML(it.label) +
          '<span class="cb-chip-remove" title="Remove" aria-label="Remove">×</span></span>'
      )
      .join('');
  };

  /**
   * Fetch posts for a given post type.
   * @param {string} postType WP post type slug
   * @returns {Promise<any>}
   */
  const fetchPosts = (postType) =>
    api(`/posts?post_type=${encodeURIComponent(postType || '')}`);

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
        '<tr data-key="' +
        safe +
        '"><td><label class="screen-reader-text" for="map-' +
        safe +
        '">Slot key</label><code>' +
        safe +
        '</code></td><td><select id="map-' +
        safe +
        '" name="sections_map[' +
        safe +
        ']" style="width:100%">' +
        optHtml +
        '</select></td></tr>';
    });
    body.innerHTML = rows;
    wrap.style.display = 'block';

    // Populate slot dropdown (left column) for Assign-to-slot control
    const slotSelect = qs('#campaignbridge-slot-select');
    if (slotSelect) {
      slotSelect.innerHTML = sections
        .map(
          (k) =>
            '<option value="' +
            escapeHTML(k) +
            '">' +
            escapeHTML(k) +
            '</option>'
        )
        .join('');
    }
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
    if (!postType) {
      select.innerHTML = '';
      return;
    }
    select.disabled = true;
    select.innerHTML = '<option>Loading…</option>';
    try {
      const resp = await fetchPosts(postType);
      select.innerHTML = Array.isArray(resp?.items)
        ? renderOptions(resp.items)
        : '';
    } catch (e) {
      select.innerHTML = '';
    } finally {
      select.disabled = false;
    }
    renderSelectedChips(select);
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

    on('change', '#campaignbridge-posts', () => {
      const sel = qs('#campaignbridge-posts');
      renderSelectedChips(sel);
    });

    on('click', '#cb-selected-posts-chips .cb-chip-remove', (e, el) => {
      const chip = el.closest('.cb-chip');
      if (!chip) return;
      const id = chip.getAttribute('data-id');
      const sel = qs('#campaignbridge-posts');
      if (sel && id) {
        Array.from(sel.options).forEach((opt) => {
          if (opt.value === id) opt.selected = false;
        });
        sel.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });

    // Template slots mapping and preview (slot-based templates)
    (function setupTemplateSlots() {
      let tplId = getQueryParam('tpl');

      // Inline template select and iframe wiring (right column)
      const tplSelect = qs('#campaignbridge-template-select');
      const tplIframe = qs('#campaignbridge-template-iframe');
      const refreshBtn = qs('#campaignbridge-refresh-slots');
      const newBtn = qs('#campaignbridge-new-template');

      const setIframeSrc = (id) => {
        if (tplIframe)
          tplIframe.src = id
            ? `${
                window.ajaxurl?.replace('admin-ajax.php', '') || '/wp-admin/'
              }post.php?post=${encodeURIComponent(id)}&action=edit`
            : '';
      };

      if (tplSelect) {
        if (tplId) tplSelect.value = String(tplId);
        tplSelect.addEventListener('change', () => {
          tplId = tplSelect.value || '';
          setQueryParam('tpl', tplId);
          setIframeSrc(tplId);
          // When template changes, refresh slots list
          if (tplId) {
            api(`/templates/${encodeURIComponent(tplId)}/slots`).then(
              (resp) => {
                const slots = slotsFrom(resp) || [];
                const items = [];
                const postSelect = qs('#campaignbridge-posts');
                if (postSelect) {
                  qsa('option', postSelect).forEach((opt) => {
                    items.push({ id: opt.value, label: opt.textContent });
                  });
                }
                const keys = slots.map((s) => s.key);
                renderMapping(keys, items);
                // Refresh preview after template change
                const previewArea = qs('#campaignbridge-preview-html');
                if (previewArea) {
                  previewArea.innerHTML = '<p>Loading preview…</p>';
                  api(`/templates/${encodeURIComponent(tplId)}/preview`, {
                    method: 'POST',
                    body: { slots_map: collectSlotsMap() },
                  }).then((r) => {
                    previewArea.innerHTML = htmlFrom(r) || '';
                  });
                }
              }
            );
          }
        });
      }

      if (newBtn) {
        newBtn.addEventListener('click', async () => {
          // Create new template via window.open in the iframe
          const url = `${
            window.ajaxurl?.replace('admin-ajax.php', '') || '/wp-admin/'
          }post-new.php?post_type=cb_template`;
          if (tplIframe) tplIframe.src = url;
          setQueryParam('tpl', '');
          if (tplSelect) tplSelect.value = '';
        });
      }

      if (refreshBtn) {
        refreshBtn.addEventListener('click', () => {
          if (!tplId) return;
          api(`/templates/${encodeURIComponent(tplId)}/slots`).then((resp) => {
            const slots = slotsFrom(resp) || [];
            const items = [];
            const postSelect = qs('#campaignbridge-posts');
            if (postSelect) {
              qsa('option', postSelect).forEach((opt) => {
                items.push({ id: opt.value, label: opt.textContent });
              });
            }
            const keys = slots.map((s) => s.key);
            renderMapping(keys, items);
          });
        });
      }

      if (!tplId) return;

      let mappingWrap = document.getElementById('campaignbridge-mapping');
      let mappingBody = document.getElementById('campaignbridge-mapping-body');
      if (!mappingWrap) {
        const container = document.createElement('div');
        container.id = 'campaignbridge-mapping';
        container.innerHTML =
          '<h3 class="cb-mapping-title">Slot Mapping</h3><table class="widefat striped cb-mapping-table"><thead><tr><th style="width:50%;">Slot key</th><th>Post</th></tr></thead><tbody id="campaignbridge-mapping-body"></tbody></table>';
        const anchor =
          document.querySelector('.cb-field:last-of-type') ||
          document.querySelector('.wrap');
        (anchor || document.body).appendChild(container);
        mappingWrap = container;
        mappingBody = document.getElementById('campaignbridge-mapping-body');
      }

      api(`/templates/${encodeURIComponent(tplId)}/slots`).then((resp) => {
        const slots = slotsFrom(resp);
        if (!slots || !slots.length) return;
        const items = [];
        const postSelect = qs('#campaignbridge-posts');
        if (postSelect) {
          qsa('option', postSelect).forEach((opt) => {
            items.push({ id: opt.value, label: opt.textContent });
          });
        }
        const keys = slots.map((s) => s.key);
        renderMapping(keys, items);

        // Enable DnD: from post select and chips to mapping selects
        const chipsWrap = qs('#cb-selected-posts-chips');
        const mappingBody = qs('#campaignbridge-mapping-body');
        if (chipsWrap && mappingBody) {
          chipsWrap.addEventListener('dragstart', (ev) => {
            const chip = ev.target && ev.target.closest('.cb-chip');
            if (!chip) return;
            ev.dataTransfer.setData(
              'text/plain',
              chip.getAttribute('data-id') || ''
            );
            ev.dataTransfer.effectAllowed = 'copyMove';
          });
          const postSelectEl = qs('#campaignbridge-posts');
          if (postSelectEl) {
            postSelectEl.addEventListener('dragstart', (ev) => {
              const opt = ev.target && ev.target.closest('option');
              if (!opt) return;
              ev.dataTransfer.setData('text/plain', opt.value || '');
              ev.dataTransfer.effectAllowed = 'copyMove';
            });
          }
          mappingBody.addEventListener('dragover', (ev) => {
            const sel = ev.target && ev.target.closest('select');
            if (!sel) return;
            ev.preventDefault();
            ev.dataTransfer.dropEffect = 'copy';
            sel.classList.add('is-drag-over');
          });
          mappingBody.addEventListener('dragleave', (ev) => {
            const sel = ev.target && ev.target.closest('select');
            if (sel) sel.classList.remove('is-drag-over');
          });
          mappingBody.addEventListener('drop', (ev) => {
            const sel = ev.target && ev.target.closest('select');
            if (!sel) return;
            ev.preventDefault();
            sel.classList.remove('is-drag-over');
            const id = ev.dataTransfer.getData('text/plain');
            if (id) sel.value = String(id);
          });
        }
      });

      let previewBox = document.getElementById('campaignbridge-preview');
      if (!previewBox) {
        previewBox = document.createElement('div');
        previewBox.id = 'campaignbridge-preview';
        previewBox.className = 'cb-preview-box';
        previewBox.style.marginTop = '16px';
        previewBox.innerHTML =
          '<p><button type="button" class="button" id="campaignbridge-preview-btn">Preview Email</button></p><div id="campaignbridge-preview-html" style="border:1px solid #dcdcde;background:#fff;padding:16px;max-height:480px;overflow:auto;"></div>';
        mappingWrap.parentNode.insertBefore(
          previewBox,
          mappingWrap.nextSibling
        );
      }

      // Initial preview load
      (async () => {
        const previewArea = ensurePreviewBox();
        if (!previewArea) return;
        previewArea.innerHTML = '<p>Loading preview…</p>';
        try {
          const resp = await api(
            `/templates/${encodeURIComponent(tplId)}/preview`,
            { method: 'POST', body: { slots_map: collectSlotsMap() } }
          );
          const html = htmlFrom(resp);
          previewArea.innerHTML = html || '';
        } catch (e) {
          previewArea.innerHTML = '';
        }
      })();

      on('click', '#campaignbridge-preview-btn', async () => {
        const map = collectSlotsMap();
        const previewArea = qs('#campaignbridge-preview-html');
        previewArea.innerHTML = '<p>Generating preview…</p>';
        try {
          const resp = await api(
            `/templates/${encodeURIComponent(tplId)}/preview`,
            { method: 'POST', body: { slots_map: map } }
          );
          const html = htmlFrom(resp);
          previewArea.innerHTML = html || '<p>Failed to render preview.</p>';
        } catch (e) {
          previewArea.innerHTML = '<p>Failed to render preview.</p>';
        }
      });

      // Autofill mapping from selected posts in order
      const rerenderPreviewDebounced = (() => {
        let t = null;
        return () => {
          clearTimeout(t);
          t = setTimeout(async () => {
            const tplIdLocal = getQueryParam('tpl');
            if (!tplIdLocal) return;
            const previewArea = qs('#campaignbridge-preview-html');
            if (!previewArea) return;
            const map = collectSlotsMap();
            previewArea.innerHTML = '<p>Updating…</p>';
            try {
              const resp = await api(
                `/templates/${encodeURIComponent(tplIdLocal)}/preview`,
                { method: 'POST', body: { slots_map: map } }
              );
              const html = htmlFrom(resp);
              previewArea.innerHTML = html || '';
            } catch (e) {
              previewArea.innerHTML = '';
            }
          }, 250);
        };
      })();

      on('click', '#campaignbridge-autofill', () => {
        const selects = qsa('#campaignbridge-mapping-body select');
        if (!selects.length) return;
        const postSel = qs('#campaignbridge-posts');
        const selected = Array.from(postSel?.selectedOptions || []).map(
          (o) => ({ id: o.value })
        );
        selects.forEach((sel, idx) => {
          sel.value = selected[idx] ? String(selected[idx].id) : '';
        });
        rerenderPreviewDebounced();
      });

      // Live update preview on mapping changes and drops
      on('change', '#campaignbridge-mapping-body select', () => {
        rerenderPreviewDebounced();
      });
      const mappingBodyEl = qs('#campaignbridge-mapping-body');
      if (mappingBodyEl) {
        mappingBodyEl.addEventListener('drop', () =>
          rerenderPreviewDebounced()
        );
        mappingBodyEl.addEventListener('click', (ev) => {
          const row = ev.target && ev.target.closest('tr');
          if (!row) return;
          qsa('#campaignbridge-mapping-body tr').forEach((r) =>
            r.classList.remove('is-active')
          );
          row.classList.add('is-active');
        });
      }

      // Assign-to-slot control (left panel)
      on('click', '#campaignbridge-assign-to-slot', () => {
        const postSel = qs('#campaignbridge-posts');
        const slotSel = qs('#campaignbridge-slot-select');
        if (!postSel || !slotSel || !slotSel.value) return;
        const selectedOpt =
          postSel.selectedOptions && postSel.selectedOptions[0];
        if (!selectedOpt) return;
        const key = slotSel.value;
        const target = qs(
          `#campaignbridge-mapping-body select[name="sections_map[${CSS.escape(
            key
          )}]"]`
        );
        if (target) {
          target.value = selectedOpt.value;
          target.dispatchEvent(new Event('change', { bubbles: true }));
        }
      });
    })();

    on('click', '#campaignbridge-show-sections', (e, btn) => {
      const box = qs('#campaignbridge-sections');
      if (!box) return;
      btn.disabled = true;
      btn.textContent = 'Loading…';
      api('/mailchimp/sections')
        .then((resp) => {
          const sections = sectionsFrom(resp);
          if (sections && sections.length) {
            let html = '<ul style="margin:0;">';
            sections.forEach((k) => {
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
            renderMapping(sections, items);
          } else if (
            resp &&
            (resp.message || (resp.data && resp.data.message))
          ) {
            const msg = resp.message || resp.data.message;
            box.innerHTML = '<p>' + escapeHTML(msg) + '</p>';
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
      api('/mailchimp/audiences?refresh=1')
        .then((resp) => {
          const items = itemsFrom(resp);
          if (items.length) populateSelect(sel, items);
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
      api('/mailchimp/templates?refresh=1')
        .then((resp) => {
          const items = itemsFrom(resp);
          if (items.length) populateSelect(sel, items);
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
        api('/mailchimp/audiences').then((resp) => {
          const items = itemsFrom(resp);
          if (items.length) populateSelect(audSel, items);
          toggleResetVisibility();
        });
      }

      const tplSel = qs('#campaignbridge-mailchimp-templates');
      if (tplSel && (!tplSel.value || tplSel.value === '')) {
        api('/mailchimp/templates').then((resp) => {
          const items = itemsFrom(resp);
          if (items.length) populateSelect(tplSel, items);
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
        (async () => {
          const resp = await api('/mailchimp/verify', { method: 'POST' });
          if (resp?.ok) setVerifyStatus('ok', 'Connected');
        })();
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
