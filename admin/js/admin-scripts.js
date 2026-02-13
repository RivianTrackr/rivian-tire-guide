/* Admin scripts for Rivian Tire Guide */
(function($) {
    'use strict';

    $(document).ready(function() {
        // Image preview on URL change.
        var $imageInput = $('#image');
        if ($imageInput.length) {
            $imageInput.on('input', function() {
                var url = $(this).val().trim();
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

        // --- Real-time efficiency calculator on the edit form ---
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
        var size       = $('#size').val() || '';
        var weightLb   = parseFloat($('#weight_lb').val()) || 0;
        var tread      = $('#tread').val() || '';
        var loadRange  = ($('#load_range').val() || '').toUpperCase().trim();
        var speedRaw   = ($('#speed_rating').val() || '').trim();
        var utqg       = ($('#utqg').val() || '').trim();
        var category   = $('#category').val() || '';
        var threePms   = $('#three_pms').val() || 'No';

        // Width score from size (e.g., "275/60R20" → 275).
        var widthVal = 0;
        var slashIdx = size.indexOf('/');
        if (slashIdx > 0) {
            widthVal = parseFloat(size.substring(0, slashIdx)) || 0;
        }
        var widthScore = widthVal > 0 ? (305 - widthVal) / 30 : 0;

        // Weight score.
        var weightScore = weightLb > 0 ? (70 - weightLb) / 40 : 0;

        // Tread score (e.g., "10/32" → 10).
        var treadVal = 0;
        var treadSlash = tread.indexOf('/');
        if (treadSlash > 0) {
            treadVal = parseFloat(tread.substring(0, treadSlash)) || 0;
        }
        var treadScore = treadVal > 0 ? (20 - treadVal) / 11 : 0;

        // Load range score.
        var loadScores = { SL: 1, HL: 0.9, XL: 0.9, RF: 0.7, D: 0.3, E: 0, F: 0 };
        var loadScore = loadScores.hasOwnProperty(loadRange) ? loadScores[loadRange] : 0;

        // Speed rating score (first character).
        var speedChar = speedRaw.length > 0 ? speedRaw.charAt(0).toUpperCase() : '';
        var speedScores = { P: 1, Q: 0.95, R: 0.9, S: 0.85, T: 0.8, H: 0.7, V: 0.6 };
        var speedScore = speedChar && speedScores.hasOwnProperty(speedChar) ? speedScores[speedChar] : 0.5;

        // UTQG score (first number from "620 A B").
        var utqgVal = 0;
        if (utqg) {
            var utqgParts = utqg.split(' ');
            utqgVal = parseInt(utqgParts[0], 10) || 0;
        }
        var utqgScore = utqgVal === 0 ? 0.5 : (utqgVal - 420) / 400;

        // Category score.
        var catScores = { 'All-Season': 1, 'Performance': 1, 'All-Terrain': 0.5, 'Winter': 0 };
        var catScore = catScores.hasOwnProperty(category) ? catScores[category] : 0;

        // 3PMS score (No = better for efficiency).
        var pmsScore = threePms === 'No' ? 1 : 0;

        // Weighted total.
        var total = (
            weightScore * 0.26 +
            treadScore  * 0.16 +
            loadScore   * 0.16 +
            speedScore  * 0.10 +
            utqgScore   * 0.10 +
            catScore    * 0.10 +
            pmsScore    * 0.05 +
            widthScore  * 0.03
        );

        var score = Math.round(total * 100);

        // Determine grade.
        var grade;
        if (score >= 80) grade = 'A';
        else if (score >= 65) grade = 'B';
        else if (score >= 50) grade = 'C';
        else if (score >= 35) grade = 'D';
        else if (score >= 20) grade = 'E';
        else grade = 'F';

        // Grade CSS class.
        var gradeClasses = {
            A: 'rtg-grade-a', B: 'rtg-grade-b', C: 'rtg-grade-c',
            D: 'rtg-grade-d', E: 'rtg-grade-e', F: 'rtg-grade-f'
        };

        // Update display.
        var $grade = $('#rtg-eff-grade');
        var $score = $('#rtg-eff-score');

        $grade
            .removeClass('rtg-grade-a rtg-grade-b rtg-grade-c rtg-grade-d rtg-grade-e rtg-grade-f rtg-grade-none')
            .addClass(gradeClasses[grade])
            .text(grade);
        $score.text(score);
    }

})(jQuery);
