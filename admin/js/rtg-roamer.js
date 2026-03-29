/* Roamer Sync admin page interactions. */
(function ($) {
  'use strict';

  // Sync Now button.
  $('#rtg-roamer-sync-btn').on('click', function () {
    var $btn = $(this);
    var $status = $('#rtg-roamer-sync-status');
    var $spinner = $('#rtg-roamer-sync-spinner');

    $btn.prop('disabled', true);
    $status.hide();
    $spinner.show();

    $.post(rtgAdmin.ajaxurl, {
      action: 'rtg_roamer_sync_now',
      nonce: rtgAdmin.nonce
    }, function (response) {
      $spinner.hide();
      $btn.prop('disabled', false);

      if (response.success && response.data) {
        var d = response.data;
        if (d.status === 'success') {
          $status.html(
            '<div class="notice notice-success inline"><p>' +
            'Sync complete: <strong>' + d.matched + '</strong> matched, ' +
            '<strong>' + d.skipped + '</strong> ambiguous, ' +
            '<strong>' + d.unmatched + '</strong> unmatched ' +
            '(out of ' + d.total_roamer + ' Roamer tires).' +
            '</p></div>'
          ).show();
          // Reload to show updated tables.
          setTimeout(function () { location.reload(); }, 1500);
        } else {
          $status.html(
            '<div class="notice notice-error inline"><p>' +
            'Sync failed: ' + (d.message || 'Unknown error') +
            '</p></div>'
          ).show();
        }
      } else {
        $status.html(
          '<div class="notice notice-error inline"><p>Sync request failed.</p></div>'
        ).show();
      }
    }).fail(function () {
      $spinner.hide();
      $btn.prop('disabled', false);
      $status.html(
        '<div class="notice notice-error inline"><p>Network error during sync.</p></div>'
      ).show();
    });
  });

  // Assign button enable/disable based on select.
  $(document).on('change', '.rtg-roamer-assign-select', function () {
    var $row = $(this).closest('tr');
    var $btn = $row.find('.rtg-roamer-assign-btn');
    $btn.prop('disabled', !$(this).val());
  });

  // Assign action.
  $(document).on('click', '.rtg-roamer-assign-btn', function () {
    var $btn = $(this);
    var roamerId = $btn.data('roamer-id');
    var $row = $btn.closest('tr');
    var tireId = $row.find('.rtg-roamer-assign-select').val();

    if (!tireId || !roamerId) return;

    $btn.prop('disabled', true).text('Assigning...');

    $.post(rtgAdmin.ajaxurl, {
      action: 'rtg_roamer_assign',
      nonce: rtgAdmin.nonce,
      tire_id: tireId,
      roamer_tire_id: roamerId
    }, function (response) {
      if (response.success) {
        $row.css('background', '#d1fae5');
        $btn.text('Assigned').removeClass('button-primary');
        setTimeout(function () { location.reload(); }, 1000);
      } else {
        $btn.prop('disabled', false).text('Assign');
        alert('Failed: ' + (response.data || 'Unknown error'));
      }
    }).fail(function () {
      $btn.prop('disabled', false).text('Assign');
      alert('Network error.');
    });
  });

  // Unlink action.
  $(document).on('click', '.rtg-roamer-unlink', function () {
    var $btn = $(this);
    var tireId = $btn.data('tire-id');

    if (!confirm('Unlink this tire from Roamer data?')) return;

    $btn.prop('disabled', true).text('Unlinking...');

    $.post(rtgAdmin.ajaxurl, {
      action: 'rtg_roamer_unlink',
      nonce: rtgAdmin.nonce,
      tire_id: tireId
    }, function (response) {
      if (response.success) {
        $btn.closest('tr').fadeOut(300, function () { $(this).remove(); });
      } else {
        $btn.prop('disabled', false).text('Unlink');
        alert('Failed: ' + (response.data || 'Unknown error'));
      }
    }).fail(function () {
      $btn.prop('disabled', false).text('Unlink');
      alert('Network error.');
    });
  });

})(jQuery);
