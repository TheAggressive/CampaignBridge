/* global CampaignBridge, jQuery */
(function ($) {
  function renderOptions(items) {
    var html = '';
    items.forEach(function (item) {
      html +=
        '<option value="' +
        item.id +
        '">' +
        $('<div>').text(item.label).html() +
        '</option>';
    });
    return html;
  }

  function fetchPosts(postType) {
    return $.post(CampaignBridge.ajaxUrl, {
      action: 'campaignbridge_fetch_posts',
      nonce: CampaignBridge.nonce,
      post_type: postType,
    });
  }

  function renderMapping(sections, items) {
    var $wrap = $('#campaignbridge-mapping');
    var $body = $('#campaignbridge-mapping-body');
    if (!sections || !sections.length) {
      $wrap.hide();
      return;
    }
    var optHtml = '<option value="">— Select a post —</option>';
    items.forEach(function (it) {
      optHtml +=
        '<option value="' +
        it.id +
        '">' +
        $('<div>').text(it.label).html() +
        '</option>';
    });
    var rows = '';
    sections.forEach(function (s) {
      rows +=
        '<tr><td><code>' +
        $('<div>').text(s).html() +
        '</code></td><td><select name="sections_map[' +
        $('<div>').text(s).html() +
        ']" style="width:100%">' +
        optHtml +
        '</select></td></tr>';
    });
    $body.html(rows);
    $wrap.show();
  }

  function populateSelect($select, items) {
    var current = $select.val();
    var html = '<option value="">—</option>';
    items.forEach(function (it) {
      html +=
        '<option value="' +
        it.id +
        '">' +
        $('<div>')
          .text(it.name || it.label)
          .html() +
        '</option>';
    });
    $select.html(html);
    if (current) {
      $select.val(current);
    }
    toggleResetVisibility();
  }

  function toggleResetVisibility() {
    var $audSel = $('#campaignbridge-mailchimp-audience');
    var $audBtn = $('#campaignbridge-fetch-audiences');
    if ($audSel.length && $audBtn.length) {
      if ($audSel.val()) {
        $audBtn.show();
      } else {
        $audBtn.hide();
      }
    }
    var $tplSel = $('#campaignbridge-mailchimp-templates');
    var $tplBtn = $('#campaignbridge-fetch-templates');
    if ($tplSel.length && $tplBtn.length) {
      if ($tplSel.val()) {
        $tplBtn.show();
      } else {
        $tplBtn.hide();
      }
    }
  }

  function loadPosts() {
    var postType = $('#campaignbridge-post-type').val();
    var $select = $('#campaignbridge-posts');
    $select.prop('disabled', true).html('<option>Loading…</option>');
    fetchPosts(postType)
      .done(function (resp) {
        if (resp && resp.success && resp.data && resp.data.items) {
          $select.html(renderOptions(resp.data.items));
        } else {
          $select.html('');
        }
      })
      .fail(function () {
        $select.html('');
      })
      .always(function () {
        $select.prop('disabled', false);
      });
  }

  $(document).on('change', '#campaignbridge-post-type', loadPosts);
  $(function () {
    if ($('#campaignbridge-posts').length) {
      loadPosts();
    }
    toggleResetVisibility();
    $(document).on('click', '#campaignbridge-show-sections', function () {
      var $btn = $(this);
      var $box = $('#campaignbridge-sections');
      $btn.prop('disabled', true).text('Loading…');
      $.post(CampaignBridge.ajaxUrl, {
        action: 'campaignbridge_fetch_sections',
        nonce: CampaignBridge.nonce,
      })
        .done(function (resp) {
          if (resp && resp.success && resp.data && resp.data.sections) {
            var html = '<ul style="margin:0;">';
            resp.data.sections.forEach(function (k) {
              html += '<li><code>' + $('<div>').text(k).html() + '</code></li>';
            });
            html += '</ul>';
            $box.html(html).show();

            // Build mapping UI options using the current post list if available.
            var items = [];
            $('#campaignbridge-posts option').each(function () {
              items.push({ id: $(this).val(), label: $(this).text() });
            });
            renderMapping(resp.data.sections, items);
          } else if (resp && resp.data && resp.data.message) {
            $box
              .html('<p>' + $('<div>').text(resp.data.message).html() + '</p>')
              .show();
          } else {
            $box.html('<p>No sections found.</p>').show();
          }
        })
        .fail(function () {
          $box.html('<p>Failed to load sections.</p>').show();
        })
        .always(function () {
          $btn.prop('disabled', false).text('Show Mailchimp Template Sections');
        });
    });
    // Mailchimp: reset audiences (clear and repopulate)
    $(document).on('click', '#campaignbridge-fetch-audiences', function () {
      var $btn = $(this);
      var $sel = $('#campaignbridge-mailchimp-audience');
      $btn.prop('disabled', true).text('Resetting…');
      $sel.val('');
      $.post(CampaignBridge.ajaxUrl, {
        action: 'campaignbridge_fetch_mailchimp_audiences',
        nonce: CampaignBridge.nonce,
      })
        .done(function (resp) {
          if (resp && resp.success && resp.data && resp.data.items) {
            populateSelect($sel, resp.data.items);
          }
        })
        .always(function () {
          $btn.prop('disabled', false).text('Reset Audiences');
          toggleResetVisibility();
        });
    });

    // Mailchimp: reset templates (clear and repopulate)
    $(document).on('click', '#campaignbridge-fetch-templates', function () {
      var $btn = $(this);
      var $sel = $('#campaignbridge-mailchimp-templates');
      $btn.prop('disabled', true).text('Resetting…');
      $sel.val('');
      $.post(CampaignBridge.ajaxUrl, {
        action: 'campaignbridge_fetch_mailchimp_templates',
        nonce: CampaignBridge.nonce,
      })
        .done(function (resp) {
          if (resp && resp.success && resp.data && resp.data.items) {
            populateSelect($sel, resp.data.items);
          }
        })
        .always(function () {
          $btn.prop('disabled', false).text('Reset Templates');
          toggleResetVisibility();
        });
    });

    // Auto-populate audiences and templates on page load if fields exist
    (function autoPopulateMailchimp() {
      var $audSel = $('#campaignbridge-mailchimp-audience');
      if ($audSel.length && (!$audSel.val() || $audSel.val() === '')) {
        $.post(CampaignBridge.ajaxUrl, {
          action: 'campaignbridge_fetch_mailchimp_audiences',
          nonce: CampaignBridge.nonce,
        }).done(function (resp) {
          if (resp && resp.success && resp.data && resp.data.items) {
            populateSelect($audSel, resp.data.items);
          }
          toggleResetVisibility();
        });
      }

      var $tplSel = $('#campaignbridge-mailchimp-templates');
      if ($tplSel.length && (!$tplSel.val() || $tplSel.val() === '')) {
        $.post(CampaignBridge.ajaxUrl, {
          action: 'campaignbridge_fetch_mailchimp_templates',
          nonce: CampaignBridge.nonce,
        }).done(function (resp) {
          if (resp && resp.success && resp.data && resp.data.items) {
            populateSelect($tplSel, resp.data.items);
          }
          toggleResetVisibility();
        });
      }
    })();

    // Show/hide reset buttons when user changes selection
    $(document).on(
      'change',
      '#campaignbridge-mailchimp-audience, #campaignbridge-mailchimp-templates',
      toggleResetVisibility
    );

    // Auto-verify Mailchimp connection on load if API key exists + on change (debounced)
    function setVerifyStatus(state, message) {
      var $status = $('#campaignbridge-verify-status');
      if (!$status.length) return;
      if (state === 'loading') {
        $status
          .removeClass('cb-status-ok cb-status-err')
          .html(
            '<span class="spinner is-active cb-inline-spinner"></span> Verifying…'
          );
        return;
      }
      var isOk = state === 'ok';
      var text = message || (isOk ? 'Connected' : 'No connection');
      $status
        .toggleClass('cb-status-ok', isOk)
        .toggleClass('cb-status-err', !isOk)
        .html(
          '<span class="cb-pill">' +
            (isOk ? '✔' : '✖') +
            '</span>' +
            $('<div>').text(text).html()
        );
    }

    var verifyTimer = null;
    function verifyMailchimp() {
      setVerifyStatus('loading');
      $.post(CampaignBridge.ajaxUrl, {
        action: 'campaignbridge_verify_mailchimp',
        nonce: CampaignBridge.nonce,
        api_key: $('#campaignbridge-mailchimp-api-key').val() || '',
      })
        .done(function (resp) {
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
        .fail(function () {
          setVerifyStatus('err', 'Not connected');
        });
    }

    (function autoVerifyMailchimp() {
      var $api = $('#campaignbridge-mailchimp-api-key');
      if ($api.length && $api.val()) {
        verifyMailchimp();
      }
    })();

    $(document).on(
      'input change',
      '#campaignbridge-mailchimp-api-key',
      function () {
        if (verifyTimer) clearTimeout(verifyTimer);
        verifyTimer = setTimeout(verifyMailchimp, 600);
      }
    );
  });
})(jQuery);
