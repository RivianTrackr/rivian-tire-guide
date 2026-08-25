/* Tire Discovery admin page interactions. */
(function ($) {
  'use strict';

  // Run Discovery Now.
  $('#rtg-catalog-sync-btn').on('click', function () {
    var $btn = $(this);
    var $status = $('#rtg-catalog-sync-status');

    $btn.prop('disabled', true).text('Running...');
    $status.hide();

    $.post(rtgAdmin.ajaxurl, {
      action: 'rtg_catalog_sync_now',
      nonce: rtgAdmin.nonce
    }, function (response) {
      $btn.prop('disabled', false).text('Run Discovery Now');

      if (response.success && response.data) {
        var d = response.data;

        if (d.status === 'disabled') {
          $status.html(
            '<div class="notice notice-warning inline"><p>' +
            'Discovery is turned off in settings.' +
            '</p></div>'
          ).show();
          return;
        }

        if (d.status === 'error') {
          $status.html(
            '<div class="notice notice-error inline"><p>' +
            'Discovery failed: ' + (d.message || 'Unknown error') +
            '</p></div>'
          ).show();
          return;
        }

        $status.html(
          '<div class="notice notice-success inline"><p>' +
          'Checked <strong>' + d.fetched + '</strong> products — ' +
          '<strong>' + d.newly_surfaced + '</strong> newly surfaced, ' +
          d.qualified + ' awaiting review, ' +
          d.existing + ' already in the guide, ' +
          d.rejected + ' near misses.' +
          '</p></div>'
        ).show();

        // Reload so the table and counts reflect the run.
        setTimeout(function () { location.reload(); }, 1500);
      } else {
        $status.html(
          '<div class="notice notice-error inline"><p>Discovery request failed.</p></div>'
        ).show();
      }
    }).fail(function (xhr) {
      $btn.prop('disabled', false).text('Run Discovery Now');

      // Name what actually happened. "Network error" covered a timed-out
      // request, a PHP fatal and a permissions failure alike, which made a run
      // that outlived its request indistinguishable from one that crashed.
      var detail;
      if (!xhr || xhr.status === 0) {
        detail = 'The request ended without a reply — usually the run outlived the ' +
                 'server\'s time limit. Lower the run budget in settings and try again; ' +
                 'the rotation means a shorter run still makes progress.';
      } else if (xhr.status >= 500) {
        detail = 'The server returned ' + xhr.status + ' ' + escapeHTML(xhr.statusText || '') +
                 '. That is an error inside the run rather than a timeout — the PHP error log ' +
                 'will name it.';
      } else {
        detail = 'The server returned ' + xhr.status + ' ' + escapeHTML(xhr.statusText || '') + '.';
      }

      $status.html(
        '<div class="notice notice-error inline"><p><strong>Discovery did not finish.</strong> ' +
        detail + '</p></div>'
      ).show();
    });
  });

  // Test the CJ connection.
  $('#rtg-cj-test-btn').on('click', function () {
    var $btn = $(this);
    var $out = $('#rtg-cj-test-result');

    $btn.prop('disabled', true).text('Testing...');
    $out.hide();

    $.post(rtgAdmin.ajaxurl, {
      action: 'rtg_cj_test_connection',
      nonce: rtgAdmin.nonce,
      keyword: $('#rtg-cj-test-keyword').val() || '',
      offset: $('#rtg-cj-test-offset').val() || 0
    }, function (response) {
      $btn.prop('disabled', false).text('Test Connection');

      if (!response.success || !response.data) {
        $out.html('<div class="notice notice-error inline"><p>Test request failed.</p></div>').show();
        return;
      }

      var d = response.data;
      var html;

      if (d.ok) {
        html = '<div class="notice notice-success inline"><p>' + escapeHTML(d.message) + '</p></div>';

        // What came back, as titles. The question this button answers is
        // whether a keyword matches or merely ranks, and that is legible in
        // the titles long before it is legible in raw JSON.
        if (d.titles && d.titles.length) {
          html += '<p style="margin:8px 0 4px;font-weight:600;">What that keyword returned:</p>';
          html += '<pre style="max-height:320px;overflow:auto;padding:10px;background:#f5f5f7;' +
                  'border:1px solid #d2d2d7;border-radius:6px;font-size:12px;line-height:1.5;">' +
                  escapeHTML(d.titles.join('\n')) + '</pre>';
        }

        if (d.sample && d.sample.length) {
          html += '<p style="margin:8px 0 4px;font-weight:600;">One mapped product in full:</p>';
          html += '<pre style="max-height:260px;overflow:auto;padding:10px;background:#f5f5f7;' +
                  'border:1px solid #d2d2d7;border-radius:6px;font-size:12px;">' +
                  escapeHTML(JSON.stringify(d.sample, null, 2)) + '</pre>';
        }
      } else {
        html = '<div class="notice notice-error inline"><p>' + escapeHTML(d.message) + '</p></div>';
      }

      // An empty-but-successful response usually means the query returned a
      // shape the mapping didn't recognize, so show the raw body to work from.
      if (d.body) {
        html += '<p style="margin:8px 0 4px;font-weight:600;">Raw response from CJ:</p>';
        html += '<pre style="max-height:260px;overflow:auto;padding:10px;background:#f5f5f7;' +
                'border:1px solid #d2d2d7;border-radius:6px;font-size:12px;">' +
                escapeHTML(d.body) + '</pre>';
      }

      $out.html(html).show();
    }).fail(function () {
      $btn.prop('disabled', false).text('Test Connection');
      $out.html('<div class="notice notice-error inline"><p>Network error during the test.</p></div>').show();
    });
  });

  /** Escape text before it goes into the diagnostics panel. */
  function escapeHTML(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (ch) {
      return {
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
      }[ch];
    });
  }

  // Apply one decision to everything the current filter matches — the
  // database query, not just the rows on screen, and the confirm says so.
  $('#rtg-bulk-candidates').on('click', function () {
    var $btn = $(this);
    var to = $btn.data('to');
    var scope = [];

    if ($btn.data('brand')) { scope.push('brand ' + $btn.data('brand')); }
    if ($btn.data('size')) { scope.push('size ' + $btn.data('size')); }
    if ($btn.data('vehicle')) { scope.push($btn.data('vehicle')); }

    var what = scope.length ? scope.join(', ') : 'EVERY candidate in this tab';
    if (!confirm(
      (to === 'dismissed' ? 'Dismiss' : 'Restore') + ' all candidates matching: ' + what +
      '?\n\nThis applies to everything the filter matches in the database, not only the rows shown. ' +
      'It is reversible from the ' + (to === 'dismissed' ? 'Dismissed' : 'Awaiting Review') + ' tab.'
    )) {
      return;
    }

    $btn.prop('disabled', true).text('Working...');

    $.post(rtgAdmin.ajaxurl, {
      action: 'rtg_candidate_bulk',
      nonce: rtgAdmin.nonce,
      status: $btn.data('status'),
      brand: $btn.data('brand'),
      size: $btn.data('size'),
      vehicle: $btn.data('vehicle'),
      to: to
    }, function (response) {
      if (response.success) {
        location.reload();
      } else {
        $btn.prop('disabled', false).text('Try again');
        alert((response.data && String(response.data)) || 'Bulk update failed.');
      }
    }).fail(function () {
      $btn.prop('disabled', false).text('Try again');
    });
  });

  // Adopt a retailer's model spelling as an alias, from the coverage report.
  $(document).on('click', '.rtg-adopt-alias', function () {
    var $btn = $(this);

    $btn.prop('disabled', true).text('Adopting...');

    $.post(rtgAdmin.ajaxurl, {
      action: 'rtg_adopt_model_alias',
      nonce: rtgAdmin.nonce,
      tire_id: $btn.data('tire-id'),
      alias: $btn.data('alias')
    }, function (response) {
      if (response.success) {
        $btn.replaceWith(
          '<span style="color:var(--rtg-success);font-size:11px;font-weight:600;">' +
          escapeHTML((response.data && response.data.message) || 'Adopted.') + '</span>'
        );
      } else {
        $btn.prop('disabled', false).text('Adopt as alias');
        alert((response.data && String(response.data)) || 'Could not adopt the alias.');
      }
    }).fail(function () {
      $btn.prop('disabled', false).text('Adopt as alias');
    });
  });

  // Dismiss / Restore a candidate.
  $(document).on('click', '.rtg-candidate-action', function () {
    var $btn = $(this);
    var $row = $btn.closest('tr');
    var id = $row.data('candidate-id');
    var status = $btn.data('status');

    if (!id) {
      return;
    }

    $btn.prop('disabled', true);

    $.post(rtgAdmin.ajaxurl, {
      action: 'rtg_candidate_set_status',
      nonce: rtgAdmin.nonce,
      candidate_id: id,
      status: status
    }, function (response) {
      if (response.success) {
        // The row no longer belongs in the view being filtered on, so drop it
        // rather than leaving a stale row behind.
        $row.fadeOut(200, function () { $(this).remove(); });
      } else {
        $btn.prop('disabled', false);
        window.alert(
          (response.data && typeof response.data === 'string')
            ? response.data
            : 'Could not update that candidate.'
        );
      }
    }).fail(function () {
      $btn.prop('disabled', false);
      window.alert('Network error while updating the candidate.');
    });
  });
})(jQuery);
