/* Admin scripts for Rivian Tire Guide */
(function($) {
    'use strict';

    var efficiencyTimer = null;

    $(document).ready(function() {
        // Image preview on URL change (supports prefix-based input).
        var $imageInput = $('#image');
        var $imagePrefix = $('#image_prefix');
        if ($imageInput.length) {
            $imageInput.on('input', function() {
                var val = $(this).val().trim();
                var prefix = $imagePrefix.length ? $imagePrefix.val() : '';
                // Build full URL: if input already starts with http, use as-is; otherwise prepend prefix.
                var url = val;
                if (val && prefix && !/^https?:\/\//i.test(val)) {
                    url = prefix + val;
                }
                var $preview = $('#image-preview');
                var $container = $('#image-preview-container');

                if (url && url.match(/^https?:\/\/.+\.(jpg|jpeg|png|webp|gif)/i)) {
                    if ($preview.length) {
                        $preview.attr('src', url);
                        if ($container.length) {
                            $container.show();
                        }
                    }
                } else {
                    if ($container.length) {
                        $container.hide();
                    }
                }
            });
        }

        // Select all checkboxes.
        $('#cb-select-all').on('change', function() {
            $('input[name="tire_ids[]"]').prop('checked', this.checked);
        });

        // Confirm bulk delete.
        $('form').on('submit', function() {
            var action = $('select[name="rtg_bulk_action"]').val();
            if (action === 'delete') {
                var checked = $('input[name="tire_ids[]"]:checked').length;
                if (checked === 0) {
                    alert('No tires selected.');
                    return false;
                }
                return confirm('Delete ' + checked + ' tire(s)? This cannot be undone.');
            }
        });

        // Dismiss custom notices.
        $('.rtg-notice-dismiss').on('click', function() {
            $(this).closest('.rtg-notice').fadeOut(200, function() {
                $(this).remove();
            });
        });

        // --- Tag suggestion clicks ---
        $(document).on('click', '.rtg-tag-suggestion', function() {
            var tag = $(this).data('tag');
            var $tagsInput = $('#tags');
            var current = $tagsInput.val().trim();
            // Parse existing tags
            var tags = current ? current.split(',').map(function(t) { return t.trim(); }).filter(function(t) { return t.length > 0; }) : [];
            // Toggle: remove if already present, add if not
            var idx = tags.indexOf(tag);
            if (idx > -1) {
                tags.splice(idx, 1);
                $(this).css({ 'background': '#f5f5f7', 'color': '#1d1d1f' });
            } else {
                tags.push(tag);
                $(this).css({ 'background': '#0071e3', 'color': '#fff' });
            }
            $tagsInput.val(tags.join(', '));
        });

        // Highlight tags that are already selected on load
        var $tagsInput = $('#tags');
        if ($tagsInput.length && $tagsInput.val().trim()) {
            var currentTags = $tagsInput.val().split(',').map(function(t) { return t.trim(); });
            $('.rtg-tag-suggestion').each(function() {
                if (currentTags.indexOf($(this).data('tag')) > -1) {
                    $(this).css({ 'background': '#0071e3', 'color': '#fff' });
                }
            });
        }

        // --- Size → Diameter auto-fill (from data attribute mapped in Settings) ---
        $('#size').on('change', function() {
            var selected = $(this).find('option:selected');
            var diameter = selected.data('diameter') || '';
            $('#diameter').val(diameter);
        });

        // --- Load index → max load auto-fill ---
        $('#load_index').on('change', function() {
            var selected = $(this).find('option:selected');
            var maxLoad = selected.data('max-load') || '';
            $('#max_load_lb').val(maxLoad);
        });

        // --- Real-time efficiency calculator on the edit form ---
        // Uses AJAX to call the canonical PHP formula (eliminates duplicate JS logic).
        if ($('#rtg-efficiency-preview').length) {
            var effFields = '#size, #weight_lb, #tread, #load_range, #speed_rating, #utqg, #category, #three_pms';

            $(effFields).on('input change', function() {
                updateEfficiencyPreview();
            });

            // Run once on load to sync.
            updateEfficiencyPreview();
        }
    });

    function updateEfficiencyPreview() {
        // Debounce to avoid excessive AJAX calls during rapid input.
        if (efficiencyTimer) {
            clearTimeout(efficiencyTimer);
        }

        efficiencyTimer = setTimeout(function() {
            // Bail if rtgAdmin is not available (missing nonce localization).
            if (typeof rtgAdmin === 'undefined') {
                return;
            }

            $.post(rtgAdmin.ajaxurl, {
                action:       'rtg_calculate_efficiency',
                nonce:        rtgAdmin.nonce,
                size:         $('#size').val() || '',
                weight_lb:    $('#weight_lb').val() || '0',
                tread:        $('#tread').val() || '',
                load_range:   $('#load_range').val() || '',
                speed_rating: $('#speed_rating').val() || '',
                utqg:         $('#utqg').val() || '',
                category:     $('#category').val() || '',
                three_pms:    $('#three_pms').val() || 'No'
            }, function(response) {
                if (response.success) {
                    var score = response.data.efficiency_score;
                    var grade = response.data.efficiency_grade;

                    var gradeClasses = {
                        A: 'rtg-grade-a', B: 'rtg-grade-b', C: 'rtg-grade-c',
                        D: 'rtg-grade-d', F: 'rtg-grade-f'
                    };

                    $('#rtg-eff-grade')
                        .removeClass('rtg-grade-a rtg-grade-b rtg-grade-c rtg-grade-d rtg-grade-f rtg-grade-none')
                        .addClass(gradeClasses[grade] || '')
                        .text(grade);
                    $('#rtg-eff-score').text(score);
                }
            });
        }, 300);
    }

})(jQuery);
