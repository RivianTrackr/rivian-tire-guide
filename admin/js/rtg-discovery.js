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
    }).fail(function () {
      $btn.prop('disabled', false).text('Run Discovery Now');
      $status.html(
        '<div class="notice notice-error inline"><p>Network error during discovery.</p></div>'
      ).show();
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
