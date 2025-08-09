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
  function qs(selector, root) {
    return (root || document).querySelector(selector);
  }

  /**
   * Query all matching elements as an Array.
   * @param {string} selector CSS selector
   * @param {ParentNode} [root=document] Search root
   * @returns {Element[]}
   */
  function qsa(selector, root) {
    return Array.from((root || document).querySelectorAll(selector));
  }

  /**
   * Delegate events from document to elements matching a selector.
   * @param {string} eventName Event type (e.g., 'click')
   * @param {string} selector CSS selector to match targets
   * @param {(event: Event, target: Element) => void} handler Handler invoked with the original event and the matched target
   */
  function on(eventName, selector, handler) {
    document.addEventListener(eventName, function (event) {
      var target = event.target && event.target.closest(selector);
      if (target) handler(event, target);
    });
  }

  /**
   * Escape a string for safe HTML insertion.
   * @param {string|number|null|undefined} value Value to escape
   * @returns {string} Escaped HTML string
   */
  function escapeHTML(value) {
    var div = document.createElement('div');
    div.textContent = value == null ? '' : String(value);
    return div.innerHTML;
  }

  /**
   * POST to WordPress admin-ajax with URL-encoded body.
   * @param {Record<string, string>} data Key-value payload
   * @returns {Promise<any>} Parsed JSON response
   */
  function postRequest(data) {
    return fetch(CampaignBridge.ajaxUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
      },
      credentials: 'same-origin',
      body: new URLSearchParams(data).toString(),
    }).then(function (res) {
      return res.json();
    });
  }

  /**
   * Build <option> HTML for a list of items.
   * @param {{id: string|number, label: string}[]} items Items to render
   * @returns {string}
   */
  function renderOptions(items) {
    var html = '';
    items.forEach(function (item) {
      html +=
        '<option value="' +
        escapeHTML(String(item.id)) +
        '">' +
        escapeHTML(item.label) +
        '</option>';
    });
    return html;
  }

  /**
   * Fetch posts for a given post type.
   * @param {string} postType WP post type slug
   * @returns {Promise<any>}
   */
  function fetchPosts(postType) {
    return postRequest({
      action: 'campaignbridge_fetch_posts',
      nonce: CampaignBridge.nonce,
      post_type: postType,
    });
  }

  /**
   * Render the section-to-post mapping UI.
   * @param {string[]} sections Section keys from Mailchimp template
   * @param {{id: string|number, label: string}[]} items Post items to choose from
   */
  function renderMapping(sections, items) {
    var wrap = document.getElementById('campaignbridge-mapping');
    var body = document.getElementById('campaignbridge-mapping-body');
    if (!wrap || !body) return;
    if (!sections || !sections.length) {
      wrap.style.display = 'none';
      return;
    }
    var optHtml = '<option value="">— Select a post —</option>';
    items.forEach(function (it) {
      optHtml +=
        '<option value="' +
        escapeHTML(String(it.id)) +
        '">' +
        escapeHTML(it.label) +
        '</option>';
    });
    var rows = '';
    sections.forEach(function (s) {
      var safe = escapeHTML(s);
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
  }

  /**
   * Replace options of a <select> with provided items while preserving existing selection if possible.
   * @param {HTMLSelectElement} selectEl Select element to populate
   * @param {{id: string|number, label?: string, name?: string}[]} items Items to render
   */
  function populateSelect(selectEl, items) {
    if (!selectEl) return;
    var current = selectEl.value;
    var html = '<option value="">—</option>';
    items.forEach(function (it) {
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
  }

  /**
   * Show or hide Mailchimp reset buttons based on selection state.
   */
  function toggleResetVisibility() {
    var audSel = qs('#campaignbridge-mailchimp-audience');
    var audBtn = qs('#campaignbridge-fetch-audiences');
    if (audSel && audBtn) {
      if (audSel.value) {
        audBtn.style.display = '';
      } else {
        audBtn.style.display = 'none';
      }
    }
    var tplSel = qs('#campaignbridge-mailchimp-templates');
    var tplBtn = qs('#campaignbridge-fetch-templates');
    if (tplSel && tplBtn) {
      if (tplSel.value) {
        tplBtn.style.display = '';
      } else {
        tplBtn.style.display = 'none';
      }
    }
  }

  /**
   * Load posts into the posts <select> based on current post type.
   */
  function loadPosts() {
    var typeEl = qs('#campaignbridge-post-type');
    var select = qs('#campaignbridge-posts');
    if (!typeEl || !select) return;
    var postType = typeEl.value;
    select.disabled = true;
    select.innerHTML = '<option>Loading…</option>';
    fetchPosts(postType)
      .then(function (resp) {
        if (resp && resp.success && resp.data && resp.data.items) {
          select.innerHTML = renderOptions(resp.data.items);
        } else {
          select.innerHTML = '';
        }
      })
      .catch(function () {
        select.innerHTML = '';
      })
      .finally(function () {
        select.disabled = false;
      });
  }

  /**
   * Update the inline Mailchimp verification status UI.
   * @param {'loading'|'ok'|'err'} state Status code
   * @param {string} [message] Optional message to display
   */
  function setVerifyStatus(state, message) {
    var status = qs('#campaignbridge-verify-status');
    if (!status) return;
    if (state === 'loading') {
      status.classList.remove('cb-status-ok');
      status.classList.remove('cb-status-err');
      status.innerHTML =
        '<span class="spinner is-active cb-inline-spinner"></span> Verifying…';
      return;
    }
    var isOk = state === 'ok';
    var text = message || (isOk ? 'Connected' : 'No connection');
    status.classList.toggle('cb-status-ok', isOk);
    status.classList.toggle('cb-status-err', !isOk);
    status.innerHTML =
      '<span class="cb-pill">' +
      (isOk ? '✔' : '✖') +
      '</span>' +
      escapeHTML(text);
  }

  var verifyTimer = null;
  /**
   * Verify Mailchimp credentials via ajax and reflect the result in UI.
   */
  function verifyMailchimp() {
    setVerifyStatus('loading');
    var apiInput = qs('#campaignbridge-mailchimp-api-key');
    var apiKey = apiInput ? apiInput.value || '' : '';
    postRequest({
      action: 'campaignbridge_verify_mailchimp',
      nonce: CampaignBridge.nonce,
      api_key: apiKey,
    })
      .then(function (resp) {
        if (resp && resp.success) {
          setVerifyStatus('ok', 'Connected');
        } else {
          var msg =
            resp && resp.data && resp.data.message
              ? resp.data.message
              : 'Not connected';
          setVerifyStatus('err', msg);
        }
      })
      .catch(function () {
        setVerifyStatus('err', 'Not connected');
      });
  }

  document.addEventListener('DOMContentLoaded', function () {
    var postTypeEl = qs('#campaignbridge-post-type');
    if (postTypeEl) {
      postTypeEl.addEventListener('change', loadPosts);
    }
    if (qs('#campaignbridge-posts')) {
      loadPosts();
    }

    toggleResetVisibility();

    on('click', '#campaignbridge-show-sections', function (e, btn) {
      var box = qs('#campaignbridge-sections');
      if (!box) return;
      btn.disabled = true;
      btn.textContent = 'Loading…';
      postRequest({
        action: 'campaignbridge_fetch_sections',
        nonce: CampaignBridge.nonce,
      })
        .then(function (resp) {
          if (resp && resp.success && resp.data && resp.data.sections) {
            var html = '<ul style="margin:0;">';
            resp.data.sections.forEach(function (k) {
              html += '<li><code>' + escapeHTML(k) + '</code></li>';
            });
            html += '</ul>';
            box.innerHTML = html;
            box.style.display = 'block';

            var items = [];
            var postSelect = qs('#campaignbridge-posts');
            if (postSelect) {
              qsa('option', postSelect).forEach(function (opt) {
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
        .catch(function () {
          box.innerHTML = '<p>Failed to load sections.</p>';
          box.style.display = 'block';
        })
        .finally(function () {
          btn.disabled = false;
          btn.textContent = 'Show Mailchimp Template Sections';
        });
    });

    on('click', '#campaignbridge-fetch-audiences', function (e, btn) {
      var sel = qs('#campaignbridge-mailchimp-audience');
      if (!sel) return;
      btn.disabled = true;
      btn.textContent = 'Resetting…';
      sel.value = '';
      postRequest({
        action: 'campaignbridge_fetch_mailchimp_audiences',
        nonce: CampaignBridge.nonce,
      })
        .then(function (resp) {
          if (resp && resp.success && resp.data && resp.data.items) {
            populateSelect(sel, resp.data.items);
          }
        })
        .finally(function () {
          btn.disabled = false;
          btn.textContent = 'Reset Audiences';
          toggleResetVisibility();
        });
    });

    on('click', '#campaignbridge-fetch-templates', function (e, btn) {
      var sel = qs('#campaignbridge-mailchimp-templates');
      if (!sel) return;
      btn.disabled = true;
      btn.textContent = 'Resetting…';
      sel.value = '';
      postRequest({
        action: 'campaignbridge_fetch_mailchimp_templates',
        nonce: CampaignBridge.nonce,
      })
        .then(function (resp) {
          if (resp && resp.success && resp.data && resp.data.items) {
            populateSelect(sel, resp.data.items);
          }
        })
        .finally(function () {
          btn.disabled = false;
          btn.textContent = 'Reset Templates';
          toggleResetVisibility();
        });
    });

    (function autoPopulateMailchimp() {
      var audSel = qs('#campaignbridge-mailchimp-audience');
      if (audSel && (!audSel.value || audSel.value === '')) {
        postRequest({
          action: 'campaignbridge_fetch_mailchimp_audiences',
          nonce: CampaignBridge.nonce,
        }).then(function (resp) {
          if (resp && resp.success && resp.data && resp.data.items) {
            populateSelect(audSel, resp.data.items);
          }
          toggleResetVisibility();
        });
      }

      var tplSel = qs('#campaignbridge-mailchimp-templates');
      if (tplSel && (!tplSel.value || tplSel.value === '')) {
        postRequest({
          action: 'campaignbridge_fetch_mailchimp_templates',
          nonce: CampaignBridge.nonce,
        }).then(function (resp) {
          if (resp && resp.success && resp.data && resp.data.items) {
            populateSelect(tplSel, resp.data.items);
          }
          toggleResetVisibility();
        });
      }
    })();

    var audSelect = qs('#campaignbridge-mailchimp-audience');
    if (audSelect) audSelect.addEventListener('change', toggleResetVisibility);
    var tplSelect = qs('#campaignbridge-mailchimp-templates');
    if (tplSelect) tplSelect.addEventListener('change', toggleResetVisibility);

    (function autoVerifyMailchimp() {
      var apiInput = qs('#campaignbridge-mailchimp-api-key');
      if (apiInput && apiInput.value) {
        verifyMailchimp();
      }
      if (apiInput) {
        ['input', 'change'].forEach(function (ev) {
          apiInput.addEventListener(ev, function () {
            if (verifyTimer) clearTimeout(verifyTimer);
            verifyTimer = setTimeout(verifyMailchimp, 600);
          });
        });
      }
    })();
  });
})();
