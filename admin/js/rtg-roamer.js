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
        } else if (d.status === 'locked' || d.status === 'disabled') {
          $status.html(
            '<div class="notice notice-warning inline"><p>' +
            $('<span>').text(d.message || 'Sync did not run.').html() +
            '</p></div>'
          ).show();
        } else {
          $status.html(
            '<div class="notice notice-error inline"><p>' +
            'Sync failed: ' + $('<span>').text(d.message || 'Unknown error').html() +
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

  // --- Collapsible sections ---

  $(document).on('click', '#rtg-linked-toggle', function () {
    $('#rtg-linked-section').slideToggle(200);
    $('#rtg-linked-arrow').toggleClass('rtg-arrow-open');
  });

  $(document).on('click', '#rtg-unlinked-toggle', function () {
    $('#rtg-unlinked-section').slideToggle(200);
    $('#rtg-unlinked-arrow').toggleClass('rtg-arrow-open');
  });

  // --- Unmatched Roamer tires: multi-select assign ---

  // Full option list captured once so the dropdown can be regrouped per
  // selection without losing anything.
  var unmatchedAllOptions = null;

  // Regroup the assign dropdown around the selected Roamer tire names:
  // name-matching guide tires float to a "Name matches" group on top, the
  // rest stay available under "All tires". With no selection (or no
  // matches) the plain full list is shown.
  function filterAssignOptions() {
    var $select = $('#rtg-unmatched-assign-tire');
    if (!$select.length) return;

    if (!unmatchedAllOptions) {
      unmatchedAllOptions = $select.find('option').slice(1).map(function () {
        return { value: this.value, label: $(this).text().replace(/\s+/g, ' ').trim() };
      }).get();
    }

    var names = $('.rtg-unmatched-cb:checked').map(function () {
      return $(this).data('name') || '';
    }).get().filter(Boolean);

    var current = $select.val();

    function norm(s) { return String(s).toLowerCase().replace(/[^a-z0-9]+/g, ' ').trim(); }
    function squash(s) { return norm(s).replace(/ /g, ''); }

    // A guide-tire label matches a Roamer name when the squashed name is a
    // substring (handles "M/S 2" vs "M/S2"), or when at least 60% of the
    // name's tokens appear in the label.
    function matches(label, name) {
      var n = norm(name);
      if (!n) return false;
      if (squash(label).indexOf(squash(name)) !== -1) return true;
      var tokens = n.split(' ');
      var haystack = ' ' + norm(label) + ' ';
      var hits = 0;
      for (var i = 0; i < tokens.length; i++) {
        if (haystack.indexOf(' ' + tokens[i] + ' ') !== -1) hits++;
      }
      return hits / tokens.length >= 0.6;
    }

    var matched = [];
    var rest = [];
    unmatchedAllOptions.forEach(function (o) {
      var isMatch = names.length > 0 && names.some(function (n) { return matches(o.label, n); });
      (isMatch ? matched : rest).push(o);
    });

    $select.empty().append($('<option>').val('').text('Assign selected to...'));

    function appendOptions($parent, items) {
      items.forEach(function (o) {
        $parent.append($('<option>').val(o.value).text(o.label));
      });
    }

    if (names.length > 0 && matched.length > 0) {
      var $matchGroup = $('<optgroup>').attr('label', 'Name matches (' + matched.length + ')');
      appendOptions($matchGroup, matched);
      $select.append($matchGroup);

      var $restGroup = $('<optgroup>').attr('label', 'All tires');
      appendOptions($restGroup, rest);
      $select.append($restGroup);
    } else {
      appendOptions($select, unmatchedAllOptions);
    }

    // Keep the current pick when it survived the regroup.
    if (current && $select.find('option[value="' + current + '"]').length) {
      $select.val(current);
    } else {
      $select.val('');
    }
  }

  function updateUnmatchedBar() {
    var checked = $('.rtg-unmatched-cb:checked');
    var $bar = $('#rtg-unmatched-assign-bar');
    var $count = $('#rtg-unmatched-selected-count');
    var $btn = $('#rtg-unmatched-assign-btn');
    var $hideBtn = $('#rtg-unmatched-hide-btn');
    var $select = $('#rtg-unmatched-assign-tire');

    if (checked.length > 0) {
      filterAssignOptions();
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

  // --- Hidden Roamer tires: toggle & restore ---

  $(document).on('click', '#rtg-hidden-toggle', function () {
    $('#rtg-hidden-section').slideToggle(200);
    $('#rtg-hidden-arrow').toggleClass('rtg-arrow-open');
  });

  function updateHiddenBar() {
    var checked = $('.rtg-hidden-cb:checked');
    var $bar = $('#rtg-hidden-restore-bar');
    var $count = $('#rtg-hidden-selected-count');

    if (checked.length > 0) {
      $bar.css('display', 'flex');
      $count.text(checked.length + ' selected');
    } else {
      $bar.hide();
    }
  }

  $(document).on('change', '.rtg-hidden-cb', updateHiddenBar);

  $(document).on('change', '#rtg-hidden-select-all', function () {
    $('.rtg-hidden-cb').prop('checked', $(this).prop('checked'));
    updateHiddenBar();
  });

  $(document).on('click', '#rtg-hidden-restore-btn', function () {
    var $btn = $(this);
    var roamerIds = [];

    $('.rtg-hidden-cb:checked').each(function () {
      roamerIds.push($(this).val());
    });

    if (roamerIds.length === 0) return;

    $btn.prop('disabled', true).text('Restoring...');

    $.post(rtgAdmin.ajaxurl, {
      action: 'rtg_roamer_restore',
      nonce: rtgAdmin.nonce,
      roamer_tire_ids: JSON.stringify(roamerIds)
    }, function (response) {
      if (response.success) {
        $btn.text('Restored');
        setTimeout(function () { location.reload(); }, 1000);
      } else {
        $btn.prop('disabled', false).text('Restore');
        alert('Failed: ' + (response.data || 'Unknown error'));
      }
    }).fail(function () {
      $btn.prop('disabled', false).text('Restore');
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
