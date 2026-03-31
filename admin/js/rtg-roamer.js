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

  // --- Unmatched Roamer tires: multi-select assign ---

  function updateUnmatchedBar() {
    var checked = $('.rtg-unmatched-cb:checked');
    var $bar = $('#rtg-unmatched-assign-bar');
    var $count = $('#rtg-unmatched-selected-count');
    var $btn = $('#rtg-unmatched-assign-btn');
    var $hideBtn = $('#rtg-unmatched-hide-btn');
    var $select = $('#rtg-unmatched-assign-tire');

    if (checked.length > 0) {
      $bar.css('display', 'flex');
      $count.text(checked.length + ' selected');
      $btn.prop('disabled', !$select.val());
      $hideBtn.prop('disabled', false);
    } else {
      $bar.hide();
    }
  }

  $(document).on('change', '.rtg-unmatched-cb', updateUnmatchedBar);

  $('#rtg-unmatched-select-all').on('change', function () {
    $('.rtg-unmatched-cb').prop('checked', $(this).prop('checked'));
    updateUnmatchedBar();
  });

  $('#rtg-unmatched-assign-tire').on('change', function () {
    var checked = $('.rtg-unmatched-cb:checked');
    $('#rtg-unmatched-assign-btn').prop('disabled', !$(this).val() || checked.length === 0);
  });

  $('#rtg-unmatched-assign-btn').on('click', function () {
    var $btn = $(this);
    var tireId = $('#rtg-unmatched-assign-tire').val();
    var roamerIds = [];

    $('.rtg-unmatched-cb:checked').each(function () {
      roamerIds.push($(this).val());
    });

    if (!tireId || roamerIds.length === 0) return;

    $btn.prop('disabled', true).text('Assigning...');

    $.post(rtgAdmin.ajaxurl, {
      action: 'rtg_roamer_assign',
      nonce: rtgAdmin.nonce,
      tire_id: tireId,
      roamer_tire_ids: JSON.stringify(roamerIds)
    }, function (response) {
      if (response.success) {
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

  // --- Hide unmatched Roamer tires permanently ---

  $('#rtg-unmatched-hide-btn').on('click', function () {
    var $btn = $(this);
    var roamerIds = [];

    $('.rtg-unmatched-cb:checked').each(function () {
      roamerIds.push($(this).val());
    });

    if (roamerIds.length === 0) return;

    if (!confirm('Hide ' + roamerIds.length + ' tire(s) permanently? They won\u2019t appear in future syncs. You can restore them from the plugin settings.')) {
      return;
    }

    $btn.prop('disabled', true).text('Hiding...');

    $.post(rtgAdmin.ajaxurl, {
      action: 'rtg_roamer_hide',
      nonce: rtgAdmin.nonce,
      roamer_tire_ids: JSON.stringify(roamerIds)
    }, function (response) {
      if (response.success) {
        $btn.text('Hidden');
        setTimeout(function () { location.reload(); }, 1000);
      } else {
        $btn.prop('disabled', false).text('Hide');
        alert('Failed: ' + (response.data || 'Unknown error'));
      }
    }).fail(function () {
      $btn.prop('disabled', false).text('Hide');
      alert('Network error.');
    });
  });

})(jQuery);
